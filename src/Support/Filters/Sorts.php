<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies one or more ORDER BY clauses to the builder.
 *
 * Accepts a key-value array of `column => direction` pairs. Prefix a column
 * name with `raw:` to emit an `orderByRaw()` expression instead.
 */
class Sorts
{
    /**
     * The sort definitions keyed by column name (or `raw:` expression).
     *
     * @var array<string, string>
     */
    private $sorts = [];

    /**
     * @param  array<string, string>|null  $sorts  Column => direction pairs.
     */
    public function __construct(?array $sorts = [])
    {
        $this->sorts = ! empty($sorts) ? $sorts : [];
    }

    /**
     * Apply all sort clauses to the builder.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function build(Builder $builder): Builder
    {

        foreach ($this->sorts as $field => $order) {
            $builder = (new SingleSort($field, $order))->build($builder);
        }

        return $builder;
    }
}
