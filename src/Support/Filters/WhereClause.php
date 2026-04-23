<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

/**
 * Compiles a single filter condition into an Eloquent where call.
 *
 * Supports comparison, list, null, LIKE, date/time, JSON, column comparison,
 * raw expressions, relationship existence, and multi-column operators.
 * Piped operators (`op|subOp`) allow a secondary operator to be passed alongside
 * the primary one (e.g. `has|>=`, `field|>`, `any|_like_`).
 *
 * Polymorphic (`MorphTo`) relations are detected automatically. When a `has` or
 * `doesnthave` filter targets a MorphTo relation, the type list is pre-filtered in
 * PHP (zero DB queries) using model metadata (`$fillable`, `$casts`, `$attributes`,
 * timestamps, PK) so `whereHasMorph` only generates subqueries for types that can
 * actually match. When no morph map is registered, `whereHasMorph('*', ...)` is used
 * and incompatible types are excluded inside the callback via `WHERE 0=1`.
 */
class WhereClause
{
    /**
     * The column name (or array of columns for `any`/`all`/`none`).
     *
     * @var string|array
     */
    private $field;

    /**
     * The value to compare against. May be scalar, array, or a nested filter array.
     *
     * @var mixed
     */
    private $value;

    /**
     * The primary operator (e.g. `=`, `in`, `has`, `_like_`).
     *
     * @var string
     */
    private $op;

    /**
     * The secondary operator extracted from a piped `op` string (e.g. `>=` from `has|>=`).
     *
     * @var string
     */
    private $subOp;

    /**
     * The logical conjunction for this clause ('AND' or 'OR').
     *
     * @var string
     */
    private $conjunction;

    /**
     * @param  array        $condition    Associative array with `field`, `op`, and optionally `value`.
     * @param  string|null  $conjunction  'AND' or 'OR' (default: 'AND').
     */
    public function __construct(array $condition, ?string $conjunction = 'AND')
    {
        $this->field = $condition['field'];
        $this->value = $condition['value'] ?? null;
        $this->conjunction = ! empty($conjunction) ? $conjunction : 'AND';

        if (! empty($condition['op'])) {
            $parts = Str::of($condition['op'])->lower()->explode('|');
            $this->op = $parts->get(0, '=');
            $this->subOp = $parts->get(1, '=');
        } else {
            $this->op = '=';
            $this->subOp = '=';
        }
    }

    /**
     * Apply this condition to the builder using the resolved operator and conjunction.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function build(Builder $builder): Builder
    {
        $fieldsArr = Arr::wrap($this->field);
        $valuesArr = Arr::wrap($this->value);

        return match ($this->op) {
            'any'              => $builder->{$this->where('Any')}($fieldsArr, $this->subOp, $this->value),
            'all'              => $builder->{$this->where('All')}($fieldsArr, $this->subOp, $this->value),
            'none'             => $builder->{$this->where('None')}($fieldsArr, $this->subOp, $this->value),
            'in'               => $builder->{$this->where('In')}($this->field, $valuesArr),
            'not_in'           => $builder->{$this->where('NotIn')}($this->field, $valuesArr),
            'between'          => $builder->{$this->where('Between')}($this->field, $valuesArr),
            'not_between'      => $builder->{$this->where('NotBetween')}($this->field, $valuesArr),
            'null'             => $builder->{$this->where('Null')}($this->field),
            'not_null'         => $builder->{$this->where('NotNull')}($this->field),
            'date'             => $builder->{$this->where('Date')}($this->field, $this->value),
            'year'             => $builder->{$this->where('Year')}($this->field, $this->value),
            'month'            => $builder->{$this->where('Month')}($this->field, $this->value),
            'day'              => $builder->{$this->where('Day')}($this->field, $this->value),
            'time'             => $builder->{$this->where('Time')}($this->field, $this->value),
            'has'              => $this->whereHas($builder),
            'doesnthave'       => $this->whereDoesntHave($builder),
            'json_contains'    => $builder->{$this->where('JsonContains')}($this->field, $this->value),
            'json_not_contains' => $builder->{$this->where('JsonDoesntContain')}($this->field, $this->value),
            '_like'            => $builder->{$this->where()}($this->field, 'like', '%'.$this->value),
            'like_'            => $builder->{$this->where()}($this->field, 'like', $this->value.'%'),
            '_like_'           => $builder->{$this->where()}($this->field, 'like', '%'.$this->value.'%'),
            'raw'              => $builder->{$this->whereRaw()}($this->field, $this->value),
            'field'            => $builder->{$this->whereColumn()}($this->field, $this->subOp, $this->value),
            default            => $builder->{$this->where()}($this->field, $this->op, $this->value),
        };
    }

    private function whereHas(Builder $builder): Builder
    {
        if (is_iterable($this->value) && $this->isMorphToRelation($builder)) {
            return $this->applyAutoMorphQuery($builder, false);
        }

        $or = $this->conjunction === 'OR';

        if (is_iterable($this->value)) {
            $method = $or ? 'orWhereHas' : 'whereHas';

            return $builder->{$method}($this->field, fn (Builder $sub) => Qubuilder::make(['filter' => $this->value], $sub)->query());
        }

        $method = $or ? 'orHas' : 'has';

        return $builder->{$method}($this->field, $this->subOp, (int) $this->value);
    }

    private function whereDoesntHave(Builder $builder): Builder
    {
        if (is_iterable($this->value) && $this->isMorphToRelation($builder)) {
            return $this->applyAutoMorphQuery($builder, true);
        }

        $or = $this->conjunction === 'OR';

        if (is_iterable($this->value)) {
            $method = $or ? 'orWhereDoesntHave' : 'whereDoesntHave';

            return $builder->{$method}($this->field, fn (Builder $sub) => Qubuilder::make(['filter' => $this->value], $sub)->query());
        }

        $method = $or ? 'orDoesntHave' : 'doesntHave';

        return $builder->{$method}($this->field);
    }

    /**
     * Check whether `$this->field` resolves to a MorphTo relation on the builder's model.
     */
    private function isMorphToRelation(Builder $builder): bool
    {
        $model = $builder->getModel();

        if (! method_exists($model, $this->field)) {
            return false;
        }

        try {
            return $model->{$this->field}() instanceof MorphTo;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Handle `has`/`doesnthave` on a MorphTo relation.
     *
     * **When a morph map is registered** (recommended): resolves valid types in PHP
     * using `method_exists()` — no extra DB query, no UNION for excluded types.
     *
     * **When no morph map is registered**: falls back to `whereHasMorph('*', ...)`,
     * which runs one `SELECT DISTINCT _type` query. Types that lack a required
     * relation are excluded via `WHERE 0=1` inside the callback.
     */
    private function applyAutoMorphQuery(Builder $builder, bool $doesntHave): Builder
    {
        $or = $this->conjunction === 'OR';

        $method = match (true) {
            $doesntHave && $or => 'orWhereDoesntHaveMorph',
            $doesntHave        => 'whereDoesntHaveMorph',
            $or                => 'orWhereHasMorph',
            default            => 'whereHasMorph',
        };

        $value           = $this->value;
        $neededRelations = $this->extractHasRelations($value);
        $neededFields    = $this->extractDirectFields($value);
        $resolvedTypes   = $this->filterMorphTypes($neededRelations);

        // Morph map is configured: pre-filter by relations then by model field metadata.
        // No DB queries; excluded types generate no sub-query at all.
        if ($resolvedTypes !== null) {
            $resolvedTypes = $this->filterMorphTypesByFields($resolvedTypes, $neededFields);

            if (empty($resolvedTypes)) {
                return $builder;
            }

            return $builder->{$method}(
                $this->field,
                $resolvedTypes,
                fn (Builder $sub) => Qubuilder::make(['filter' => $value], $sub)->query()
            );
        }

        // No morph map: use '*' (one SELECT DISTINCT query).
        // Guard each type in the callback so unsupported ones produce no rows.
        return $builder->{$method}(
            $this->field,
            '*',
            function (Builder $sub) use ($value, $neededRelations, $neededFields) {
                $model = $sub->getModel();

                foreach ($neededRelations as $relation) {
                    if (! method_exists($model, $relation)) {
                        $sub->whereRaw('0 = 1');

                        return;
                    }
                }

                foreach ($neededFields as $field) {
                    if (! $this->modelMayHaveField($model, $field)) {
                        $sub->whereRaw('0 = 1');

                        return;
                    }
                }

                Qubuilder::make(['filter' => $value], $sub)->query();
            }
        );
    }

    /**
     * Determine whether a model likely has a given field as a real column.
     *
     * Uses only PHP-level model metadata — zero DB queries. Checks (in order):
     * primary key, timestamp columns, `$fillable`, `$casts`, and `$attributes`.
     * If `$fillable` is empty (model uses `$guarded` approach), returns `true`
     * conservatively because columns cannot be inferred from metadata alone.
     */
    private function modelMayHaveField(object $model, string $field): bool
    {
        if ($field === $model->getKeyName()) {
            return true;
        }

        if ($model->usesTimestamps() && in_array($field, [
            $model->getCreatedAtColumn(),
            $model->getUpdatedAtColumn(),
        ], true)) {
            return true;
        }

        $fillable = $model->getFillable();

        if (! empty($fillable)) {
            return in_array($field, $fillable, true)
                || array_key_exists($field, $model->getCasts());
        }

        if (array_key_exists($field, $model->getCasts())
            || array_key_exists($field, $model->getAttributes())
        ) {
            return true;
        }

        return true; // $fillable empty — guarded model, cannot exclude safely
    }

    /**
     * Filter a list of FQCN classes to those whose models likely have all required fields.
     *
     * Instantiates each model once (no DB queries) and delegates to `modelMayHaveField`.
     *
     * @param  array<int, string>  $classes
     * @param  array<int, string>  $neededFields
     * @return array<int, string>
     */
    private function filterMorphTypesByFields(array $classes, array $neededFields): array
    {
        if (empty($neededFields)) {
            return $classes;
        }

        return array_values(array_filter($classes, function (string $class) use ($neededFields) {
            $model = new $class;

            foreach ($neededFields as $field) {
                if (! $this->modelMayHaveField($model, $field)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Resolve morph types eligible to be queried for the given sub-relations.
     *
     * When a morph map is registered, filters it in PHP using `method_exists()`
     * — no DB query, no model instantiation.
     *
     * Returns `null` when no morph map is configured (caller should use `'*'`).
     * Returns `[]` when morph map is set but no type supports all required relations.
     * Returns `string[]` (FQCNs) when at least one type is valid.
     *
     * @param  array<int, string>  $neededRelations
     * @return array<int, string>|null
     */
    private function filterMorphTypes(array $neededRelations): ?array
    {
        $morphMap = Relation::morphMap();

        if (empty($morphMap)) {
            return null;
        }

        $valid = [];

        foreach ($morphMap as $class) {
            foreach ($neededRelations as $relation) {
                if (! method_exists($class, $relation)) {
                    continue 2;
                }
            }

            $valid[] = $class;
        }

        return $valid;
    }

    /**
     * Recursively collect relation names from `has`/`doesnthave` conditions in a filter array.
     *
     * Recurses into AND/OR groups but stops at `has` sub-filter values — those belong
     * to a deeper model and are irrelevant to the current morph type check.
     *
     * @return array<int, string>
     */
    private function extractHasRelations(mixed $filter): array
    {
        if (! is_array($filter)) {
            return [];
        }

        $relations = [];

        foreach ($filter as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            if (isset($condition['field'])) {
                $primaryOp = explode('|', strtolower($condition['op'] ?? '='))[0];
                if (in_array($primaryOp, ['has', 'doesnthave'], true)) {
                    $relations[] = $condition['field'];
                }
            } else {
                // AND / OR group — recurse into it
                $relations = array_merge($relations, $this->extractHasRelations($condition));
            }
        }

        return array_unique($relations);
    }

    /**
     * Recursively collect direct column field names from non-relation conditions in a filter array.
     *
     * Excludes fields from `has`/`doesnthave` conditions (those are relation names, not columns).
     * Used to guard morph type callbacks against missing columns.
     *
     * @return array<int, string>
     */
    private function extractDirectFields(mixed $filter): array
    {
        if (! is_array($filter)) {
            return [];
        }

        $fields = [];

        foreach ($filter as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            if (isset($condition['field'])) {
                $primaryOp = explode('|', strtolower($condition['op'] ?? '='))[0];
                if (! in_array($primaryOp, ['has', 'doesnthave'], true)) {
                    $fields[] = $condition['field'];
                }
            } else {
                // AND / OR group — recurse
                $fields = array_merge($fields, $this->extractDirectFields($condition));
            }
        }

        return array_unique($fields);
    }

    private function where(string $type = ''): string
    {
        return $this->conjunction === 'OR' ? "orWhere$type" : "where$type";
    }

    private function whereRaw(): string
    {
        return $this->conjunction === 'OR' ? 'orWhereRaw' : 'whereRaw';
    }

    private function whereColumn(): string
    {
        return $this->conjunction === 'OR' ? 'orWhereColumn' : 'whereColumn';
    }
}