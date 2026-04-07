<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies a single ORDER BY clause to the builder.
 *
 * Prefix the column name with `raw:` to emit an `orderByRaw()` expression.
 * Sort direction is validated and defaults to `asc` for any unrecognised value.
 */
class SingleSort
{
    /**
     * The name of field to sort
     *
     * @var string
     */
    protected $name;

    /**
     * The order of sort
     *
     * @var string
     */
    protected $order;

    /**
     * @param  string  $name   Column name or a `raw:` prefixed SQL expression.
     * @param  string  $order  Sort direction (`asc` or `desc`). Invalid values default to `asc`.
     */
    public function __construct(string $name, string $order)
    {
        $this->name = $name;
        $this->order = $order;
    }

    /**
     * Apply this sort clause to the builder.
     *
     * If the name is prefixed with `raw:`, `orderByRaw()` is used with the
     * resolved direction appended to the expression.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function build(Builder $builder): Builder
    {
        $direction = in_array(strtolower($this->order), ['asc', 'desc']) ? strtolower($this->order) : 'asc';

        if (str_starts_with($this->name, 'raw:')) {
            return $builder->orderByRaw(substr($this->name, 4).' '.$direction);
        }

        return $builder->orderBy($this->name, $direction);
    }
}
