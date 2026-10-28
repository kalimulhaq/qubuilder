<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Recursively builds nested WHERE conditions from a structured filter array.
 *
 * Each array entry is either a condition (has a `field` key) or a logical group
 * keyed by `AND` or `OR`. Groups are wrapped in a closure to produce properly
 * nested parentheses in the generated SQL.
 *
 * Each group also passes a context down to its clauses and sub-groups: the column
 * values that every row they apply to must have, taken from the `=` and `in`
 * conditions of this group and of every enclosing group whose items are joined
 * with AND. `WhereClause` uses it to narrow a `has` on a `MorphTo` to the morph
 * types the filter already names (`bookmarkable_type = Article`). A group whose items
 * are joined with OR adds nothing of its own, because its conditions are
 * alternatives; it still passes down what it inherited. A sub-group that is only an
 * OR of `=` / `in` conditions on one column counts as one `in` condition.
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
     * Column values inherited from the enclosing AND groups, keyed by column name.
     *
     * @var array<string, array<int, mixed>>
     */
    private array $context;

    /**
     * @param  array<int|string, mixed>|null  $where  Filter conditions / nested groups.
     * @param  string|null  $conjunction  'AND' or 'OR' (default: 'AND').
     * @param  array<string, array<int, mixed>>  $context  Column values every row of this group must have,
     *                                                     inherited from the enclosing AND groups (default: none).
     */
    public function __construct(?array $where = [], ?string $conjunction = 'AND', array $context = [])
    {
        $this->where = ! empty($where) ? $where : [];
        $this->conjunction = ! empty($conjunction) ? $conjunction : 'AND';
        $this->context = $context;
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

        $context = $this->contextForItems();

        /** @param  Builder<TFilterModel>  $subBuilder */
        $group = function (Builder $subBuilder) use ($context) {
            foreach ($this->where as $key => $condition) {

                $conjunction = $this->itemConjunction($key);

                if (! empty($condition['field'])) {
                    $subBuilder = (new WhereClause($condition, $conjunction, $context))->build($subBuilder);
                } elseif (in_array($conjunction, ['AND', 'OR'])) {
                    $subBuilder = (new Where($condition, $conjunction, $context))->build($subBuilder);
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

    /**
     * The conjunction that joins an item to the items before it: its `AND` / `OR` key,
     * else the group's own conjunction (for list items).
     */
    private function itemConjunction(int|string $key): string
    {
        $conj = Str::upper((string) $key);

        return in_array($conj, ['AND', 'OR'], true) ? $conj : $this->conjunction;
    }

    /**
     * The context for this group's items: the inherited one, narrowed by this group's own
     * `=` / `in` conditions when every item after the first is joined with AND.
     *
     * The first item's conjunction does not count: SQL drops the boolean of the first
     * clause in a group. Two conditions on the same column keep the values they share.
     *
     * @return array<string, array<int, mixed>>
     */
    private function contextForItems(): array
    {
        $hints = [];
        $first = true;

        foreach ($this->where as $key => $condition) {
            $conjunction = $this->itemConjunction($key);

            if (! $first && $conjunction !== 'AND') {
                return $this->context;
            }

            $first = false;

            if (! is_array($condition)) {
                continue;
            }

            $hint = ! empty($condition['field'])
                ? self::conditionHint($condition)
                : self::orSetHint($condition, $conjunction);

            if ($hint !== null) {
                $hints[] = $hint;
            }
        }

        $context = $this->context;

        foreach ($hints as [$column, $values]) {
            $context[$column] = array_key_exists($column, $context)
                ? array_values(array_intersect($context[$column], $values))
                : $values;
        }

        return $context;
    }

    /**
     * The column and values of an `=` or `in` condition on a single column, or null.
     *
     * @param  array<string, mixed>  $condition
     * @return array{0: string, 1: array<int, mixed>}|null
     */
    private static function conditionHint(array $condition): ?array
    {
        $field = $condition['field'];
        $value = $condition['value'] ?? null;
        // Parsed as WhereClause does: lower-cased, primary operator before any `|`.
        $op = empty($condition['op']) ? '=' : explode('|', Str::lower((string) $condition['op']))[0];

        if (! is_string($field)) {
            return null;
        }

        if ($op === '=' && is_scalar($value)) {
            return [$field, [$value]];
        }

        if ($op === 'in') {
            $values = Arr::wrap($value);

            if ($values !== [] && count(array_filter($values, 'is_scalar')) === count($values)) {
                return [$field, array_values($values)];
            }
        }

        return null;
    }

    /**
     * The column and values of a group that is only an OR of `=` / `in` conditions on
     * one column (`or[ type = Article, type = Course ]`), read as one `in` condition.
     *
     * The group is either a list of conditions joined by `$conjunction`, or a map with a
     * single `AND` / `OR` key around such a list. Anything else returns null.
     *
     * @param  array<int|string, mixed>  $group
     * @return array{0: string, 1: array<int, mixed>}|null
     */
    private static function orSetHint(array $group, string $conjunction): ?array
    {
        if (! array_is_list($group)) {
            $keys = array_keys($group);
            $conj = Str::upper((string) $keys[0]);

            if (count($keys) !== 1 || ! in_array($conj, ['AND', 'OR'], true) || ! is_array($group[$keys[0]])) {
                return null;
            }

            $inner = $group[$keys[0]];

            return isset($inner['field']) ? self::conditionHint($inner) : self::orSetHint($inner, $conj);
        }

        if ($group === [] || (count($group) > 1 && $conjunction !== 'OR')) {
            return null;
        }

        $column = null;
        $values = [];

        foreach ($group as $condition) {
            $hint = is_array($condition) && ! empty($condition['field']) ? self::conditionHint($condition) : null;

            if ($hint === null || ($column !== null && $hint[0] !== $column)) {
                return null;
            }

            $column = $hint[0];
            $values = array_merge($values, $hint[1]);
        }

        return [$column, array_values(array_unique($values))];
    }
}
