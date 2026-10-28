<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Support\MorphTypeResolver;

/**
 * Compiles a single filter condition into an Eloquent where call.
 *
 * Supports comparison, list, null, LIKE, date/time, JSON, column comparison,
 * raw expressions, relationship existence, and multi-column operators.
 * Piped operators (`op|subOp`) allow a secondary operator to be passed alongside
 * the primary one (e.g. `has|>=`, `field|>`, `any|_like_`).
 *
 * Polymorphic (`MorphTo`) relations are detected automatically. A `has` or
 * `doesnthave` filter on a MorphTo builds one sub-query per morph type, and
 * `MorphTypeResolver` chooses those types in PHP (relation map or morph map, narrowed
 * by a morph type condition from the enclosing AND groups, then checked against the
 * sub-filter). When no type is known, `whereHasMorph('*', ...)` is used and each type
 * that cannot run the sub-filter is excluded inside the callback via `WHERE 0 = 1`.
 * When no type is eligible, a `has` matches no rows and a `doesnthave` matches all.
 *
 * A dotted relation path (`listable.community`) is unrolled one segment at a time:
 * `a.b has X` is compiled as `a has { b has X }`, so every segment goes through this
 * class on its own model and a MorphTo at any depth gets the treatment above. Laravel's
 * own nested `has()` would run the inner `has()` on every stored morph type and fail on
 * a type that lacks the relation. The operator and count apply to the last segment and
 * `doesnthave` becomes an outer `doesnthave` with an inner `has`, as Laravel does.
 */
class WhereClause
{
    /**
     * The column name (or array of columns for `any`/`all`/`none`).
     *
     * @var string|array<int, string>
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
     * Column values every row this clause applies to must have, from the enclosing AND groups.
     *
     * @var array<string, array<int, mixed>>
     */
    private array $context;

    /**
     * @param  array<string, mixed>  $condition  Associative array with `field`, `op`, and optionally `value`.
     * @param  string|null  $conjunction  'AND' or 'OR' (default: 'AND').
     * @param  array<string, array<int, mixed>>  $context  Column values that hold for this clause, keyed by
     *                                                     column; used to narrow a morph `has` (default: none).
     */
    public function __construct(array $condition, ?string $conjunction = 'AND', array $context = [])
    {
        $this->field = $condition['field'];
        $this->value = $condition['value'] ?? null;
        $this->conjunction = ! empty($conjunction) ? $conjunction : 'AND';
        $this->context = $context;

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
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
     * @return Builder<TFilterModel>
     */
    public function build(Builder $builder): Builder
    {
        $valuesArr = Arr::wrap($this->value);

        return match ($this->op) {
            'any' => $this->whereMultiColumn($builder, 'Any'),
            'all' => $this->whereMultiColumn($builder, 'All'),
            'none' => $this->whereMultiColumn($builder, 'None'),
            'in' => $builder->{$this->where('In')}($this->field, $valuesArr),
            'not_in' => $builder->{$this->where('NotIn')}($this->field, $valuesArr),
            'between' => $builder->{$this->where('Between')}($this->field, $valuesArr),
            'not_between' => $builder->{$this->where('NotBetween')}($this->field, $valuesArr),
            'null' => $builder->{$this->where('Null')}($this->field),
            'not_null' => $builder->{$this->where('NotNull')}($this->field),
            'date' => $builder->{$this->where('Date')}($this->field, $this->value),
            'year' => $builder->{$this->where('Year')}($this->field, $this->value),
            'month' => $builder->{$this->where('Month')}($this->field, $this->value),
            'day' => $builder->{$this->where('Day')}($this->field, $this->value),
            'time' => $builder->{$this->where('Time')}($this->field, $this->value),
            'has' => $this->whereHas($builder),
            'doesnthave' => $this->whereDoesntHave($builder),
            'json_contains' => $builder->{$this->where('JsonContains')}($this->field, $this->value),
            'json_not_contains' => $builder->{$this->where('JsonDoesntContain')}($this->field, $this->value),
            '_like' => $builder->{$this->where()}($this->field, 'like', '%'.$this->value),
            'like_' => $builder->{$this->where()}($this->field, 'like', $this->value.'%'),
            '_like_' => $builder->{$this->where()}($this->field, 'like', '%'.$this->value.'%'),
            'raw' => $builder->{$this->whereRaw()}($this->field, $this->value),
            'field' => $builder->{$this->whereColumn()}($this->field, $this->subOp, $this->value),
            default => $builder->{$this->where()}($this->field, $this->op, $this->value),
        };
    }

    /**
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
     * @return Builder<TFilterModel>
     */
    private function whereHas(Builder $builder): Builder
    {
        if ($this->isNestedPath()) {
            return $this->unrollNestedPath($builder);
        }

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

    /**
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
     * @return Builder<TFilterModel>
     */
    private function whereDoesntHave(Builder $builder): Builder
    {
        if ($this->isNestedPath()) {
            return $this->unrollNestedPath($builder);
        }

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
     * Whether `$this->field` is a dotted relation path (`orders.items`, `listable.community`).
     */
    private function isNestedPath(): bool
    {
        return is_string($this->field) && str_contains($this->field, '.');
    }

    /**
     * Compile a dotted `has`/`doesnthave` path as nested single-segment clauses.
     *
     * `a.b.c has X` becomes `a has { b.c has X }`; the tail is compiled by a new
     * `WhereClause` on the related model, which unrolls it again until one segment is
     * left. The outer clause keeps this clause's conjunction. The rewrite mirrors
     * Laravel's `hasNested()`: the operator and count belong to the last segment, every
     * outer segment is `>= 1`, and a does-not-have path (`doesnthave`, or `has|<` with
     * a count of 1) is an outer `doesnthave` around an inner `has`.
     *
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
     * @return Builder<TFilterModel>
     */
    private function unrollNestedPath(Builder $builder): Builder
    {
        [$head, $tail] = explode('.', $this->field, 2);

        $doesntHave = $this->op === 'doesnthave'
            || (! is_iterable($this->value) && $this->subOp === '<' && (int) $this->value === 1);

        if ($doesntHave) {
            $inner = is_iterable($this->value)
                ? ['field' => $tail, 'op' => 'has', 'value' => $this->value]
                : ['field' => $tail, 'op' => 'has|>=', 'value' => 1];
        } elseif (is_iterable($this->value)) {
            $inner = ['field' => $tail, 'op' => 'has', 'value' => $this->value];
        } else {
            $inner = ['field' => $tail, 'op' => 'has|'.$this->subOp, 'value' => $this->value];
        }

        $outer = [
            'field' => $head,
            'op' => $doesntHave ? 'doesnthave' : 'has',
            'value' => ['AND' => [$inner]],
        ];

        return (new WhereClause($outer, $this->conjunction, $this->context))->build($builder);
    }

    /**
     * Check whether `$this->field` resolves to a MorphTo relation on the builder's model.
     *
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
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
     * Asks `MorphTypeResolver` for the types to search. With a known list, no database
     * query is run to choose them. With none known, falls back to `whereHasMorph('*', ...)`,
     * which runs one `SELECT DISTINCT _type` query, and guards each type in the callback.
     *
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
     * @return Builder<TFilterModel>
     */
    private function applyAutoMorphQuery(Builder $builder, bool $doesntHave): Builder
    {
        $or = $this->conjunction === 'OR';

        $method = match (true) {
            $doesntHave && $or => 'orWhereDoesntHaveMorph',
            $doesntHave => 'whereDoesntHaveMorph',
            $or => 'orWhereHasMorph',
            default => 'whereHasMorph',
        };

        $value = $this->value;
        $model = $builder->getModel();
        $types = MorphTypeResolver::resolve($model, $this->field, $model->{$this->field}(), $value, $this->context);

        if ($types === null) {
            return $builder->{$method}(
                $this->field,
                '*',
                function (Builder $sub) use ($value) {
                    if (! MorphTypeResolver::supports($sub->getModel(), $value)) {
                        $sub->whereRaw('0 = 1');

                        return;
                    }

                    Qubuilder::make(['filter' => $value], $sub)->query();
                }
            );
        }

        if ($types === []) {
            return $this->noEligibleMorphType($builder, $doesntHave);
        }

        return $builder->{$method}(
            $this->field,
            $types,
            fn (Builder $sub) => Qubuilder::make(['filter' => $value], $sub)->query()
        );
    }

    /**
     * Apply the result of a morph `has` / `doesnthave` for which no type is eligible.
     *
     * No type can satisfy the sub-filter, so `has` is false for every row and
     * `doesnthave` is true for every row. Both are written out, even the true one under
     * AND: a group left empty is dropped by Laravel, and an OR item after it would then
     * become the first clause and lose its boolean.
     *
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
     * @return Builder<TFilterModel>
     */
    private function noEligibleMorphType(Builder $builder, bool $doesntHave): Builder
    {
        return $builder->{$this->whereRaw()}($doesntHave ? '1 = 1' : '0 = 1');
    }

    /**
     * Apply a multi-column condition (`any`/`all`/`none`) using the resolved sub-operator.
     *
     * LIKE-style sub-operators (`_like`, `like_`, `_like_`) are translated to a SQL
     * `like` with the value wrapped accordingly, mirroring the single-column behaviour.
     *
     * `none` is expressed as `NOT (whereAny)` rather than calling `whereNone()` directly,
     * because `whereNone()` was only added mid-Laravel-11 (~11.15) while `whereAny()` and
     * `whereNot()` exist since 11.0 — keeping the operator working across the whole
     * supported range.
     *
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
     * @param  string  $type  `Any`, `All`, or `None`.
     * @return Builder<TFilterModel>
     */
    private function whereMultiColumn(Builder $builder, string $type): Builder
    {
        [$operator, $value] = $this->resolveSubOperator();
        $fields = Arr::wrap($this->field);

        if ($type === 'None') {
            return $builder->{$this->where('Not')}(
                fn (Builder $sub) => $sub->whereAny($fields, $operator, $value)
            );
        }

        return $builder->{$this->where($type)}($fields, $operator, $value);
    }

    /**
     * Resolve the piped sub-operator into a [SQL operator, value] pair.
     *
     * Translates the LIKE-pattern aliases; all other sub-operators pass through unchanged.
     *
     * @return array{0: string, 1: mixed}
     */
    private function resolveSubOperator(): array
    {
        return match ($this->subOp) {
            '_like' => ['like', '%'.$this->value],
            'like_' => ['like', $this->value.'%'],
            '_like_' => ['like', '%'.$this->value.'%'],
            default => [$this->subOp, $this->value],
        };
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
