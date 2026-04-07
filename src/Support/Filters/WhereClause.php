<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
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
            'any' => $builder->{$this->where('Any')}($fieldsArr, $this->subOp, $this->value),
            'all' => $builder->{$this->where('All')}($fieldsArr, $this->subOp, $this->value),
            'none' => $builder->{$this->where('None')}($fieldsArr, $this->subOp, $this->value),
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

    private function whereHas($builder)
    {
        $whereHas = 'whereHas';
        $has = 'has';
        if ($this->conjunction === 'OR') {
            $whereHas = 'orWhereHas';
            $has = 'orHas';
        }

        if (is_iterable($this->value)) {
            return $builder->{$whereHas}($this->field, fn (Builder $subBuilder) => Qubuilder::make(['filter' => $this->value], $subBuilder)->query());
        } else {
            return $builder->{$has}($this->field, $this->subOp, (int) $this->value);
        }

    }

    private function whereDoesntHave($builder)
    {
        $whereHas = 'whereDoesntHave';
        $has = 'doesntHave';
        if ($this->conjunction === 'OR') {
            $whereHas = 'orWhereDoesntHave';
            $has = 'orDoesntHave';
        }

        if (is_iterable($this->value)) {
            return $builder->{$whereHas}($this->field, fn (Builder $subBuilder) => Qubuilder::make(['filter' => $this->value], $subBuilder)->query());
        } else {
            return $builder->{$has}($this->field);
        }

    }

    private function where(string $type = ''): string
    {
        return $this->conjunction === 'OR' ? "orWhere$type" : "where$type";
    }

    private function whereRaw(): string
    {
        return $this->conjunction === 'OR' ? "orWhereRaw" : "whereRaw";
    }

    private function whereColumn(): string
    {
        return $this->conjunction === 'OR' ? "orWhereColumn" : "whereColumn";
    }
}
