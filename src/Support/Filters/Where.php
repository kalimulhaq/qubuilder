<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Recursively builds nested WHERE conditions from a structured filter array.
 *
 * Each array entry is either a condition (has a `field` key) or a logical group
 * keyed by `AND` or `OR`. Groups are wrapped in a closure to produce properly
 * nested parentheses in the generated SQL.
 */
class Where
{
    /**
     * The raw filter array for this group.
     *
     * @var array
     */
    private $where;

    /**
     * The logical conjunction applied to this group ('AND' or 'OR').
     *
     * @var string
     */
    private $conjunction;

    /**
     * @param  array|null   $where        Filter conditions / nested groups.
     * @param  string|null  $conjunction  'AND' or 'OR' (default: 'AND').
     */
    public function __construct(?array $where = [], ?string $conjunction = 'AND')
    {
        $this->where = ! empty($where) ? $where : [];
        $this->conjunction = ! empty($conjunction) ? $conjunction : 'AND';
    }

    /**
     * Apply all conditions in this group to the given builder.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function build(Builder $builder): Builder
    {
        if (empty($this->where)) {
            return $builder;
        }

        $group = function (Builder $subBuilder) {
            foreach ($this->where as $key => $condition) {

                // $conjunction = Str::upper($key) === 'OR' ? 'OR' : 'AND';
                $conj = Str::upper($key);
                $conjunction = in_array($conj, ['AND', 'OR']) ? $conj : $this->conjunction;

                if (! empty($condition['field'])) {
                    $subBuilder = (new WhereClause($condition, $conjunction))->build($subBuilder);
                } elseif (in_array($conjunction, ['AND', 'OR'])) {
                    $subBuilder = (new Where($condition, $conjunction))->build($subBuilder);
                }

            }
        };

        if ($this->conjunction === 'OR') {
            $builder->orWhere($group);
        } else {
            $builder->where($group);
        }

        return $builder;
    }
}
