<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * Holds the GROUP BY column list and applies it to the builder.
 *
 * Passes through to `Builder::groupBy()`. No-ops when the column list is empty.
 *
 * @implements Arrayable<int, string>
 */
class Group implements Arrayable
{
    /**
     * The list of columns to group by.
     *
     * @var array<int, string>
     */
    protected array $columns;

    /**
     * @param  array<int, string>|null  $columns  Column names to group by.
     */
    public function __construct(?array $columns = [])
    {
        $this->columns = ! empty($columns) ? $columns : [];
        $this->columns = collect($this->columns)->filter()->values()->toArray();
    }

    /**
     * Return all column names as a comma-separated string.
     */
    public function __toString(): string
    {
        return Arr::join($this->columns, ',');
    }

    /**
     * Get the column list as an array.
     *
     * @return array<int, string>
     */
    public function toArray(): array
    {
        return $this->columns;
    }

    /**
     * Apply the GROUP BY clause to the builder.
     *
     * No-ops when the column list is empty.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function build(Builder $builder): Builder
    {
        if (! empty($this->columns)) {
            $builder->groupBy($this->columns);
        }

        return $builder;
    }
}
