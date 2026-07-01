<?php

namespace Kalimulhaq\Qubuilder\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Kalimulhaq\Qubuilder\Qubuilder;
use Kalimulhaq\Qubuilder\Support\Helper;

/**
 * Applies eager-loading of relationships to the builder.
 *
 * Each include entry may carry its own `select`, `filter`, `sort`, `include`,
 * `page`, and `limit` keys to scope the loaded relationship. Aggregates
 * (`count`, `avg`, `sum`, `min`, `max`) are also supported. Polymorphic
 * `MorphTo` relations are handled via `morphWith()` when the model exposes a
 * `{relation}Map()` method.
 */
class Includes
{
    /**
     * The list of include definitions.
     *
     * @var array
     */
    private $includes = [];

    /**
     * @param  array|null  $includes  Array of include definition arrays.
     */
    public function __construct(?array $includes = [])
    {
        $this->includes = ! empty($includes) ? $includes : [];
    }

    /**
     * Apply all includes to the builder.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function build(Builder $builder): Builder
    {
        foreach ($this->includes as $include) {
            $name = $include['name'] ?? '';
            $model = $builder->getModel();

            if (! empty($name)) {
                $commonFilters = Arr::only($include, ['select', 'filter', 'include', 'sort', 'group', 'page', 'limit']);
                $aggregateFilter = $include['filter'] ?? null;
                $aggregate = $include['aggregate'] ?? null;
                $field = $include['field'] ?? null;

                // Scope the aggregate sub-query by its `filter` only — applying the full
                // pipeline (select/sort/group) would clobber the aggregate's own SELECT.
                $scopeAggregate = fn ($subBuilder) => (new Where($aggregateFilter))->build($subBuilder);

                switch ($aggregate) {
                    case 'count':
                        $builder->withCount([$name => $scopeAggregate]);
                        break;
                    case 'avg':
                        $builder->withAvg([$name => $scopeAggregate], $field);
                        break;
                    case 'sum':
                        $builder->withSum([$name => $scopeAggregate], $field);
                        break;
                    case 'min':
                        $builder->withMin([$name => $scopeAggregate], $field);
                        break;
                    case 'max':
                        $builder->withMax([$name => $scopeAggregate], $field);
                        break;
                    default:
                        if (Helper::getReturnTypes(get_class($model), $name) === MorphTo::class && method_exists($model, $name.'Map')) {
                            $builder->with([$name => function ($morphBuilder) use ($commonFilters, $model, $name) {
                                $morphWith = [];
                                $morphMaping = $model->{$name.'Map'}();

                                foreach ($morphMaping as $morpto => $relations) {
                                    // morphWith() resolves morphable eager-loads by class name,
                                    // so map a morph alias (e.g. 'post') to its FQCN when one is
                                    // registered; otherwise the key is assumed to be a class name.
                                    $morphClass = Relation::getMorphedModel($morpto) ?? $morpto;

                                    foreach (Helper::include($commonFilters) as $inputInclude) {
                                        if (in_array($inputInclude['name'], $relations)) {
                                            Arr::set(
                                                $morphWith, $morphClass.'.'.$inputInclude['name'],
                                                fn ($subBuilder) => Qubuilder::make($inputInclude, $subBuilder)->query()
                                            );
                                        }
                                    }
                                }

                                $morphBuilder->morphWith($morphWith);
                            }]);
                        } else {
                            if (! empty($commonFilters)) {
                                $builder->with([$name => fn ($subBuilder) => Qubuilder::make($commonFilters, $subBuilder)->query()]);
                            } else {
                                $builder->with([$name]);
                            }
                        }

                        break;
                }
            }
        }

        return $builder;
    }
}
