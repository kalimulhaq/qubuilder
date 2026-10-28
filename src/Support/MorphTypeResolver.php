<?php

namespace Kalimulhaq\Qubuilder\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Chooses the morph types a `has` / `doesnthave` filter on a `MorphTo` should search.
 *
 * One sub-query is built per morph type, so a type whose model cannot run the
 * sub-filter makes the whole statement fail on MySQL ("Unknown column", "Call to
 * undefined method"). The types are chosen before the query is built:
 *
 * 1. Candidates: the keys of the model's public `{relation}Map()` when it has one.
 *    Else the classes of the registered morph map, which is app-wide: they are narrowed
 *    by a type condition (step 2) when there is one, and otherwise limited to the types
 *    actually stored in the morph type column (one `SELECT DISTINCT`, the query Laravel's
 *    own `whereHasMorph('*')` runs). With no morph map either, nothing is known and the
 *    caller searches every stored type (`whereHasMorph('*')`), guarding each one.
 *    Only classes from the relation map or the morph map are ever instantiated, never a
 *    class name taken from the request.
 * 2. Narrowing: a condition on the relation's morph type column (`bookmarkable_type =
 *    Article`, or `in`) that holds for the clause, passed in as context (see `Where`).
 *    Values match a candidate's morph alias or class name the way MySQL compares
 *    strings: case-insensitive, trailing spaces ignored. When a value matches no
 *    candidate, the condition is not used, so narrowing can never drop a matching row.
 * 3. Checking: each type must be able to run the whole sub-filter (`supports()`):
 *    every nested `has` names a relation of the model it runs on, and every column
 *    exists in the table it is read from.
 *
 * Columns are read from the database schema (`TableColumns`), once per table and connection, because
 * `$fillable` usually leaves out real columns (`company_id`, audit columns) and would
 * drop a valid type. A check that cannot decide keeps the type.
 *
 * @internal Used by `WhereClause`; not part of the public API.
 */
class MorphTypeResolver
{
    /**
     * The model classes to search for a morph `has`, or null when no type is known.
     *
     * @param  Model  $model  The model the filter runs on (the owner of the `MorphTo`).
     * @param  string  $name  The relation name (`bookmarkable`).
     * @param  MorphTo<*, *>  $relation  The relation instance.
     * @param  mixed  $filter  The `has` sub-filter.
     * @param  array<string, array<int, mixed>>  $context  Column values that hold for the clause.
     * @return array<int, string>|null
     */
    public static function resolve(Model $model, string $name, MorphTo $relation, mixed $filter, array $context): ?array
    {
        $types = self::mapped($model, $name);

        if ($types !== []) {
            $types = self::narrow($model, $relation, $types, $context) ?? $types;
        } else {
            $known = array_values(Relation::morphMap());

            if ($known === []) {
                return null;
            }

            $types = self::narrow($model, $relation, $known, $context) ?? self::stored($model, $relation, $known);
        }

        return array_values(array_filter($types, fn (string $class) => self::supports(new $class, $filter)));
    }

    /**
     * Whether every condition of a filter tree can run against the given model.
     *
     * Walks AND and OR groups alike: an unknown column fails the whole statement,
     * whichever branch it sits in. `$via` is the relation that reached the model, so a
     * column of its pivot or intermediate table is accepted too.
     *
     * @param  Relation<*, *, *>|null  $via
     */
    public static function supports(Model $model, mixed $filter, ?Relation $via = null): bool
    {
        if (! is_array($filter)) {
            return true;
        }

        if (isset($filter['field'])) {
            return self::supportsCondition($model, $filter, $via);
        }

        foreach ($filter as $item) {
            if (is_array($item) && ! self::supports($model, $item, $via)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The classes of the model's public `{relation}Map()` keys, or [] without such a map.
     *
     * A map key is a morph alias or a class name; a key that resolves to no model class is
     * ignored, as no stored row can use it.
     *
     * @return array<int, string>
     */
    private static function mapped(Model $model, string $name): array
    {
        $mapMethod = $name.'Map';

        if (method_exists($model, $mapMethod)) {
            $classes = [];

            foreach (array_keys($model->{$mapMethod}()) as $type) {
                $class = Relation::getMorphedModel($type) ?? $type;

                if (is_string($class) && is_subclass_of($class, Model::class)) {
                    $classes[] = $class;
                }
            }

            return array_values(array_unique($classes));
        }

        return [];
    }

    /**
     * The classes among `$known` that rows of the model actually store in the morph type column.
     *
     * Runs one `SELECT DISTINCT` on the morph type column, as Laravel's own
     * `whereHasMorph('*')` does. A stored value outside `$known` is skipped: no class
     * from the database is instantiated unless the morph map names it.
     *
     * @param  MorphTo<*, *>  $relation
     * @param  array<int, string>  $known
     * @return array<int, string>
     */
    private static function stored(Model $model, MorphTo $relation, array $known): array
    {
        $classes = [];

        foreach ($model->newModelQuery()->distinct()->pluck($relation->getMorphType()) as $type) {
            $value = $type instanceof \BackedEnum ? (string) $type->value : (string) $type;
            $class = Relation::getMorphedModel($value) ?? $value;

            if (in_array($class, $known, true)) {
                $classes[] = $class;
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Keep the candidates named by a condition on the relation's morph type column.
     *
     * Looks the column up in the context under its bare and its table-qualified name. A
     * value matches a candidate when it equals the candidate's morph alias or class name,
     * compared case-insensitively and without trailing spaces (as MySQL's default
     * collations do). Returns null when there is no such condition, or when any value
     * matches no candidate: narrowing on a value it cannot place could drop rows the SQL
     * would keep.
     *
     * @param  MorphTo<*, *>  $relation
     * @param  array<int, string>  $candidates
     * @param  array<string, array<int, mixed>>  $context
     * @return array<int, string>|null
     */
    private static function narrow(Model $model, MorphTo $relation, array $candidates, array $context): ?array
    {
        $column = $relation->getMorphType();
        $values = null;

        foreach (array_unique([$column, $model->qualifyColumn($column)]) as $key) {
            if (array_key_exists($key, $context)) {
                $values = array_merge($values ?? [], $context[$key]);
            }
        }

        if ($values === null) {
            return null;
        }

        $byName = [];

        foreach ($candidates as $class) {
            $byName[self::comparable($class)] = $class;
        }

        foreach (Relation::morphMap() as $alias => $class) {
            if (in_array($class, $candidates, true)) {
                $byName[self::comparable((string) $alias)] = $class;
            }
        }

        $kept = [];

        foreach ($values as $value) {
            $class = $byName[self::comparable((string) $value)] ?? null;

            if ($class === null) {
                return null;
            }

            $kept[] = $class;
        }

        return array_values(array_unique($kept));
    }

    /**
     * A type name as MySQL's default collations compare it: case-insensitive, no trailing spaces.
     */
    private static function comparable(string $name): string
    {
        return Str::lower(rtrim($name));
    }

    /**
     * Whether one condition can run against the given model.
     *
     * A `has` / `doesnthave` needs each segment of its relation path to be a relation of
     * the model it is called on (`isRelation()`, which also sees `resolveRelationUsing()`);
     * its sub-filter is then checked on the related model. A `MorphTo` segment, a relation
     * method that throws, or one that returns no relation stops the descent and counts as
     * supported. A `raw` condition is not checked. Any other condition needs its column
     * (each column for `any` / `all` / `none`, and the second column of `field`) to exist.
     *
     * @param  array<string, mixed>  $condition
     * @param  Relation<*, *, *>|null  $via
     */
    private static function supportsCondition(Model $model, array $condition, ?Relation $via): bool
    {
        $field = $condition['field'];
        $value = $condition['value'] ?? null;
        $op = explode('|', Str::lower((string) ($condition['op'] ?? '=')))[0];

        if (in_array($op, ['has', 'doesnthave'], true)) {
            if (! is_string($field)) {
                return true;
            }

            $current = $model;
            $relation = null;

            foreach (explode('.', $field) as $segment) {
                if (! $current->isRelation($segment)) {
                    return false;
                }

                try {
                    $relation = $current->{$segment}();
                } catch (\Throwable) {
                    return true;
                }

                if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                    return true;
                }

                $current = $relation->getRelated();
            }

            return ! is_array($value) || self::supports($current, $value, $relation);
        }

        if ($op === 'raw') {
            return true;
        }

        $columns = is_array($field) ? $field : [$field];

        if ($op === 'field' && is_string($value)) {
            $columns[] = $value;
        }

        foreach ($columns as $column) {
            if (is_string($column) && ! self::columnMayExist($model, $column, $via)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a column in a filter exists in a table the query on `$model` can read it from.
     *
     * Those tables are the model's own, plus the pivot table of a `BelongsToMany` or the
     * intermediate table of a `HasManyThrough` / `HasOneThrough` that reached it. A JSON
     * path (`settings->theme`) is checked on its column. A name qualified with a table
     * outside that list is not checked. A table whose columns cannot be read counts as
     * having the column.
     *
     * @param  Relation<*, *, *>|null  $via
     */
    private static function columnMayExist(Model $model, string $column, ?Relation $via): bool
    {
        $column = Str::before($column, '->');
        $tables = [$model->getTable()];

        if ($via instanceof BelongsToMany) {
            $tables[] = $via->getTable();
        }

        // HasOneThrough extends HasManyThrough on Laravel 11.0 and a shared base class later;
        // naming both keeps the check working on every supported version.
        if ($via instanceof HasManyThrough || $via instanceof HasOneThrough) {
            $tables[] = $via->getParent()->getTable();
        }

        if (str_contains($column, '.')) {
            $table = Str::beforeLast($column, '.');

            if (! in_array($table, $tables, true)) {
                return true;
            }

            $tables = [$table];
            $column = Str::afterLast($column, '.');
        }

        foreach ($tables as $table) {
            if (TableColumns::has($model->getConnection(), $table, $column) !== false) {
                return true;
            }
        }

        return false;
    }
}
