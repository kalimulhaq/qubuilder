<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * Holds the column list and applies a SELECT clause to the builder.
 *
 * Defaults to `['*']` when no columns are provided or all values are empty.
 *
 * @implements Arrayable<int, string>
 */
class Select implements Arrayable
{
    /**
     * The list of columns to retrieve.
     *
     * @var array<int, string>
     */
    protected array $columns;

    /**
     * @param  array<int, string>|null  $columns  Column names to select. Defaults to `['*']`.
     */
    public function __construct(?array $columns = ['*'])
    {
        $this->columns = ! empty($columns) ? $columns : ['*'];
        $this->columns = collect($this->columns)->filter()->values()->toArray();
    }

    /**
     * Get a column by index.
     *
     * @param  string  $field
     * @return string|null
     */
    public function __get(string $field): ?string
    {
        return $this->columns[$field] ?? null;
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
     * Apply the column selection to the builder.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function build(Builder $builder): Builder
    {
        return $builder->select($this->columns);
    }
}
