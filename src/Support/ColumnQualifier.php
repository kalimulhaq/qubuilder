<?php

namespace Kalimulhaq\Qubuilder\Support;

use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Qualifies the column names of an include's filters with the table they belong to.
 *
 * An include on a relation whose query joins another table (`BelongsToMany`,
 * `HasManyThrough`, `HasOneThrough`) cannot use bare column names: `id`,
 * `created_at` or `updated_at` exist on both tables, so `select`, `sort`, `group`
 * and `filter` fail with "column is ambiguous", and an explicit `SELECT *` over the
 * join returns the pivot or intermediate row's `id` as the related model's.
 *
 * Rules, applied to `select` entries (a missing select counts as `*`), `sort` keys,
 * `group` entries and the `field` of every `filter` condition:
 * - A pivot key or a `withPivot()` column gets the pivot table.
 * - A column the related table has gets the related table, also when the pivot or
 *   intermediate table has it too: `id`, `created_at` and `updated_at` mean the
 *   related model's (the pivot's stay reachable as `pivot.*`). `*` does too.
 * - A column only the pivot or intermediate table has gets that table, so an
 *   undeclared pivot column or an intermediate column keeps working.
 * - When the schema cannot be read, the related table, as before. Columns are read
 *   once per table and connection (`TableColumns`).
 * - A relation without joins: the filters are returned unchanged.
 *
 * Left as they are: an entry that already contains a dot, a `raw:` sort, a `raw`
 * condition, a `has` / `doesnthave` condition (its field is a relation and its value
 * belongs to another model), and anything that is not a plain column name, a JSON
 * path (`settings->theme`) or a `column as alias` pair. The `field` operator compares
 * two columns, so its value is qualified too.
 *
 * `qualify()` handles an include's keys; `qualifyFilter()` handles a filter tree alone,
 * for a `has` / `doesnthave` sub-filter and an aggregate include's `filter`.
 *
 * @internal Used by `Includes` and `WhereClause`; not part of the public API.
 */
class ColumnQualifier
{
    /**
     * Qualify the column names in an include's filters for the given relation.
     *
     * @param  Relation<*, *, *>  $relation  The relation being eager-loaded (Larastan types the `with()` callback parameter with wildcards).
     * @param  array<string, mixed>  $filters  The include's `select`, `filter`, `sort`, `group`, ... keys.
     * @return array<string, mixed>
     */
    public static function qualify(Relation $relation, array $filters): array
    {
        $qualify = self::columnQualifier($relation);

        if ($qualify === null) {
            return $filters;
        }

        // A missing or empty select means `*` (see `Select`), which must become `table.*`
        // here: a bare `*` over the join returns the pivot row's keys as the model's.
        $select = isset($filters['select']) && is_array($filters['select']) ? array_filter($filters['select']) : [];
        $filters['select'] = self::qualifyList($select === [] ? ['*'] : $filters['select'], $qualify);

        if (isset($filters['group']) && is_array($filters['group'])) {
            $filters['group'] = self::qualifyList($filters['group'], $qualify);
        }

        if (isset($filters['sort']) && is_array($filters['sort'])) {
            $filters['sort'] = self::qualifySort($filters['sort'], $qualify);
        }

        if (isset($filters['filter']) && is_array($filters['filter'])) {
            $filters['filter'] = self::qualifyFilterTree($filters['filter'], $qualify);
        }

        return $filters;
    }

    /**
     * Qualify the column names of a filter tree for the given relation.
     *
     * Used for a `has` / `doesnthave` sub-filter and an aggregate include's `filter`: the
     * same rule as `qualify()`, without the `select` default only an include needs. A
     * relation without joins returns the filter unchanged.
     *
     * @param  Relation<*, *, *>  $relation  The relation the filter runs on.
     */
    public static function qualifyFilter(Relation $relation, mixed $filter): mixed
    {
        $qualify = self::columnQualifier($relation);

        return $qualify === null ? $filter : self::qualifyFilterTree($filter, $qualify);
    }

    /**
     * The function that qualifies one column name for the relation, or null without joins.
     *
     * The join table is the pivot of a `BelongsToMany` or the intermediate table of a
     * `HasManyThrough` / `HasOneThrough`. A column goes to:
     * 1. the pivot table when it is a pivot key or a `withPivot()` column;
     * 2. the related table when that table has it (it also wins when both tables do, so
     *    `created_at` and `updated_at` mean the related model's);
     * 3. the join table when only that table has it (an undeclared pivot column, an
     *    intermediate column such as `orders.status` under `items`);
     * 4. the related table when the schema cannot be read, as before.
     * Columns are read from the schema once per table and connection (`TableColumns`).
     *
     * @param  Relation<*, *, *>  $relation
     * @return (Closure(string): string)|null
     */
    private static function columnQualifier(Relation $relation): ?Closure
    {
        $related = $relation->getRelated();
        $joinTable = null;
        $pivotColumns = [];

        if ($relation instanceof BelongsToMany) {
            $joinTable = $relation->getTable();
            $pivotColumns = array_diff(
                array_merge(
                    [$relation->getForeignPivotKeyName(), $relation->getRelatedPivotKeyName()],
                    $relation->getPivotColumns()
                ),
                [$relation->createdAt(), $relation->updatedAt()]
            );
        } elseif ($relation instanceof HasManyThrough || $relation instanceof HasOneThrough) {
            // HasOneThrough extends HasManyThrough on Laravel 11.0 and a shared base class later.
            $joinTable = $relation->getParent()->getTable();
        } elseif (empty($relation->getQuery()->getQuery()->joins)) {
            return null;
        }

        $connection = $related->getConnection();
        $relatedTable = $related->getTable();

        return function (string $column) use ($connection, $relatedTable, $joinTable, $pivotColumns): string {
            [$name, $alias] = self::splitAlias($column);

            if (! self::isPlainColumn($name)) {
                return $column;
            }

            $base = Str::before($name, '->');

            $table = match (true) {
                $joinTable !== null && in_array($base, $pivotColumns, true) => $joinTable,
                $name === '*', $joinTable === null => $relatedTable,
                TableColumns::has($connection, $relatedTable, $base) !== false => $relatedTable,
                TableColumns::has($connection, $joinTable, $base) === true => $joinTable,
                default => $relatedTable,
            };

            return $table.'.'.$name.($alias === null ? '' : ' as '.$alias);
        };
    }

    /**
     * Qualify every string entry of a column list (`select`, `group`).
     *
     * @param  array<int|string, mixed>  $columns
     * @param  Closure(string): string  $qualify
     * @return array<int|string, mixed>
     */
    private static function qualifyList(array $columns, Closure $qualify): array
    {
        foreach ($columns as $key => $column) {
            if (is_string($column)) {
                $columns[$key] = $qualify($column);
            }
        }

        return $columns;
    }

    /**
     * Qualify the column keys of a sort map, keeping `raw:` expressions and the order.
     *
     * @param  array<int|string, mixed>  $sort
     * @param  Closure(string): string  $qualify
     * @return array<int|string, mixed>
     */
    private static function qualifySort(array $sort, Closure $qualify): array
    {
        $result = [];

        foreach ($sort as $name => $direction) {
            $key = is_string($name) && ! str_starts_with($name, 'raw:') ? $qualify($name) : $name;
            $result[$key] = $direction;
        }

        return $result;
    }

    /**
     * Walk a filter tree (conditions and AND/OR groups) and qualify each condition.
     *
     * @param  Closure(string): string  $qualify
     */
    private static function qualifyFilterTree(mixed $filter, Closure $qualify): mixed
    {
        if (! is_array($filter)) {
            return $filter;
        }

        if (isset($filter['field'])) {
            return self::qualifyCondition($filter, $qualify);
        }

        foreach ($filter as $key => $item) {
            $filter[$key] = self::qualifyFilterTree($item, $qualify);
        }

        return $filter;
    }

    /**
     * Qualify the `field` (and, for the `field` operator, the `value`) of one condition.
     *
     * @param  array<string, mixed>  $condition
     * @param  Closure(string): string  $qualify
     * @return array<string, mixed>
     */
    private static function qualifyCondition(array $condition, Closure $qualify): array
    {
        $primaryOp = explode('|', strtolower((string) ($condition['op'] ?? '=')))[0];

        if (in_array($primaryOp, ['has', 'doesnthave', 'raw'], true)) {
            return $condition;
        }

        if (is_array($condition['field'])) {
            $condition['field'] = self::qualifyList($condition['field'], $qualify);
        } elseif (is_string($condition['field'])) {
            $condition['field'] = $qualify($condition['field']);
        }

        if ($primaryOp === 'field' && is_string($condition['value'] ?? null)) {
            $condition['value'] = $qualify($condition['value']);
        }

        return $condition;
    }

    /**
     * Split `column as alias` into its two parts; the alias is null when there is none.
     *
     * @return array{0: string, 1: string|null}
     */
    private static function splitAlias(string $column): array
    {
        if (preg_match('/^(.+?)\s+as\s+(\S+)$/i', $column, $m) === 1) {
            return [$m[1], $m[2]];
        }

        return [$column, null];
    }

    /**
     * Whether a name is `*`, a plain column name, or a JSON path on one (`settings->theme`).
     *
     * A name with a dot is already qualified; anything else (an expression, a space) is
     * not a column name this class should touch.
     */
    private static function isPlainColumn(string $name): bool
    {
        return $name === '*' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(->[^\s.]+)?$/', $name) === 1;
    }
}
