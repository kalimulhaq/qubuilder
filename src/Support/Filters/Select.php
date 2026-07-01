<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Kalimulhaq\Qubuilder\Support\Helper;

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
     * When `qubuilder.allow_select_all` is disabled, the "*" wildcard is stripped
     * and the selection falls back to the model's primary key only if no explicit
     * columns remain — preventing unrestricted "SELECT *" queries.
     */
    public function build(Builder $builder): Builder
    {
        $columns = $this->columns;

        if (! Helper::allowSelectAll()) {
            $columns = array_values(array_filter($columns, fn ($column) => $column !== '*'));

            if (empty($columns)) {
                $columns = [$builder->getModel()->getKeyName()];
            }
        }

        return $builder->select($columns);
    }
}
