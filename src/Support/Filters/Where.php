<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
     * @var array<int|string, mixed>
     */
    private $where;

    /**
     * The logical conjunction applied to this group ('AND' or 'OR').
     *
     * @var string
     */
    private $conjunction;

    /**
     * @param  array<int|string, mixed>|null  $where  Filter conditions / nested groups.
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
     * @template TFilterModel of Model
     *
     * @param  Builder<TFilterModel>  $builder
     * @return Builder<TFilterModel>
     */
    public function build(Builder $builder): Builder
    {
        if (empty($this->where)) {
            return $builder;
        }

        // A bare single-condition object (has a top-level 'field' key) is not a
        // group — normalise it to a one-item list so the loop handles it uniformly.
        if (isset($this->where['field'])) {
            $this->where = [$this->where];
        }

        /** @param  Builder<TFilterModel>  $subBuilder */
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
