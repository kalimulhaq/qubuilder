<?php

namespace Kalimulhaq\Qubuilder\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * - `BelongsToMany`: a pivot key or a `withPivot()` column gets the pivot table;
 *   every other column, and `*`, gets the related table. `created_at` and
 *   `updated_at` go to the related table; the pivot's stay reachable as `pivot.*`.
 * - Any other relation whose query has joins: the related table.
 * - A relation without joins: the filters are returned unchanged.
 *
 * Left as they are: an entry that already contains a dot, a `raw:` sort, a `raw`
 * condition, a `has` / `doesnthave` condition (its field is a relation and its value
 * belongs to another model), and anything that is not a plain column name, a JSON
 * path (`settings->theme`) or a `column as alias` pair. The `field` operator compares
 * two columns, so its value is qualified too.
 *
 * @internal Used by `Includes`; not part of the public API.
 */
class ColumnQualifier
{
    /**
     * Qualify the column names in an include's filters for the given relation.
     *
     * @param  Relation<Model, Model, mixed>  $relation  The relation being eager-loaded.
     * @param  array<string, mixed>  $filters  The include's `select`, `filter`, `sort`, `group`, ... keys.
     * @return array<string, mixed>
     */
    public static function qualify(Relation $relation, array $filters): array
    {
        $related = $relation->getRelated();
        $pivotTable = null;
        $pivotColumns = [];

        if ($relation instanceof BelongsToMany) {
            $pivotTable = $relation->getTable();
            $pivotColumns = array_diff(
                array_merge(
                    [$relation->getForeignPivotKeyName(), $relation->getRelatedPivotKeyName()],
                    $relation->getPivotColumns()
                ),
                [$relation->createdAt(), $relation->updatedAt()]
            );
        } elseif (empty($relation->getQuery()->getQuery()->joins)) {
            return $filters;
        }

        $qualify = function (string $column) use ($related, $pivotTable, $pivotColumns): string {
            [$name, $alias] = self::splitAlias($column);

            if (! self::isPlainColumn($name)) {
                return $column;
            }

            $qualified = $pivotTable !== null && in_array(Str::before($name, '->'), $pivotColumns, true)
                ? $pivotTable.'.'.$name
                : $related->qualifyColumn($name);

            return $alias === null ? $qualified : $qualified.' as '.$alias;
        };

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
            $filters['filter'] = self::qualifyFilter($filters['filter'], $qualify);
        }

        return $filters;
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
    private static function qualifyFilter(mixed $filter, Closure $qualify): mixed
    {
        if (! is_array($filter)) {
            return $filter;
        }

        if (isset($filter['field'])) {
            return self::qualifyCondition($filter, $qualify);
        }

        foreach ($filter as $key => $item) {
            $filter[$key] = self::qualifyFilter($item, $qualify);
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
