<?php

namespace Kalimulhaq\Qubuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Kalimulhaq\Qubuilder\Support\Filters\Includes;
use Kalimulhaq\Qubuilder\Support\Filters\Select;
use Kalimulhaq\Qubuilder\Support\Filters\Sorts;
use Kalimulhaq\Qubuilder\Support\Filters\Where;
use Kalimulhaq\Qubuilder\Support\Helper;

/**
 * Class Qubuilder
 *
 * A query builder utility class that helps in constructing complex queries
 * by providing methods to handle selection, filtering, including related resources,
 * sorting, pagination, and more.
 */
class Qubuilder
{
    /**
     * The raw filters provided for building the query.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * The list of columns to retrieve.
     *
     * @var Select
     */
    protected $select;

    /**
     * The conditions to apply in the query.
     *
     * @var Where
     */
    protected $where;

    /**
     * The related resources to load.
     *
     * @var Includes
     */
    protected $include;

    /**
     * The column and order to sort the records.
     *
     * @var Sorts
     */
    protected $sort;

    /**
     * The page index to retrieve for pagination.
     *
     * @var int
     */
    protected $page;

    /**
     * The number of records to retrieve per page.
     *
     * @var int
     */
    protected $limit;

    /**
     * The model class for which the query is being built.
     *
     * @var string
     */
    protected $model = Model::class;

    /**
     * The query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $builder;

    /**
     * Get the list of columns to retrieve in the query.
     */
    public function select(): Select
    {
        return $this->select;
    }

    /**
     * Get the conditions to apply in the query.
     */
    public function where(): Where
    {
        return $this->where;
    }

    /**
     * Get the related resources to load in the query.
     */
    public function include(): Includes
    {
        return $this->include;
    }

    /**
     * Get the column and order to sort the records in the query.
     */
    public function sort(): Sorts
    {
        return $this->sort;
    }

    /**
     * Get the page index to retrieve for pagination.
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Get the number of records to retrieve per page.
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Create an instance of Qubuilder with the provided filters and model.
     *
     * @param  array  $filters  The filters to apply.
     * @param  mixed  $model  The model class or query builder instance.
     * @return static
     */
    public static function make($filters = [], mixed $model = null)
    {
        $instance = new static;

        $instance->filters($filters);
        $instance->model($model);

        return $instance;
    }

    /**
     * Create a Qubuilder instance from an array of filters.
     *
     * @param  array  $array  The array of filters.
     * @param  mixed  $model  The model class or query builder instance.
     */
    public static function makeFromArray(array $array, mixed $model = null): self
    {
        return static::make($array, $model);
    }

    /**
     * Create a Qubuilder instance from a request's input.
     *
     * @param  Request|null  $req  The HTTP request instance.
     * @param  mixed  $model  The model class or query builder instance.
     */
    public static function makeFromRequest(?Request $req = null, mixed $model = null): self
    {
        return static::make(Helper::input($req), $model);
    }

    /**
     * Set the filters for the query builder.
     *
     * @param  array  $filters  The filters to apply.
     * @return $this
     */
    public function filters(array $filters = []): self
    {
        $this->filters = $filters;

        $pageName = Helper::param('page');
        $limitName = Helper::param('limit');
        $this->page = Arr::get($this->filters, $pageName, 1);
        $this->limit = Arr::get($this->filters, $limitName, config('qubuilder.limit.default', 15));

        return $this;
    }

    /**
     * Set the model for the query builder.
     *
     * @param  mixed  $model  The model class or query builder instance.
     * @return $this
     */
    public function model(mixed $model): self
    {
        if (is_string($model)) {
            $this->model = $model;
            $this->builder = $this->model::query();
        } elseif ($model instanceof Builder) {
            $this->builder = $model;
        } elseif ($model instanceof Relation) {
            $this->builder = $model->getQuery();
        }

        return $this;
    }

    /**
     * Build and return the query builder instance with the applied
     * select, where, include, and sort conditions.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(): Builder
    {
        if ($this->builder === null) {
            throw new \InvalidArgumentException(
                'No model has been set. Pass a model class or Builder to make() or call model() before query().'
            );
        }

        $this->buildSelect();
        $this->buildWhere();
        $this->buildInclude();
        $this->buildSort();
        $this->buildGroup();

        return $this->builder;
    }

    /**
     * Build the select part of the query.
     *
     * @return void
     */
    private function buildSelect()
    {
        $selectArray = Arr::get($this->filters, 'select');
        $this->select = new Select($selectArray);
        $this->builder = $this->select->build($this->builder);
    }

    /**
     * Build the where conditions for the query.
     *
     * @return void
     */
    private function buildWhere()
    {
        $whereArray = Arr::get($this->filters, 'filter');
        $this->where = new Where($whereArray);
        $this->builder = $this->where->build($this->builder);

        if ($this->includeTrashed()) {
            $this->builder->withTrashed();
        }
    }

    /**
     * Build the includes for related resources in the query.
     *
     * @return void
     */
    private function buildInclude()
    {
        $includeArray = Arr::get($this->filters, 'include');
        $this->include = new Includes($includeArray);
        $this->builder = $this->include->build($this->builder);
    }

    /**
     * Build the sorting order for the query.
     *
     * @return void
     */
    private function buildSort()
    {
        $sortArray = Arr::get($this->filters, 'sort');
        $this->sort = new Sorts($sortArray);
        $this->builder = $this->sort->build($this->builder);
    }

    private function buildGroup()
    {
        $groupArray = Arr::get($this->filters, 'group');
        if (! empty($groupArray)) {
            $this->builder->groupBy((array) $groupArray);
        }
    }

    private function includeTrashed(): bool
    {
        $whereArray = Arr::get($this->filters, 'filter', []);

        $flattened = Arr::dot($whereArray);

        return collect($flattened)
            ->filter(fn ($value, $key) => str_ends_with($key, 'field') && $value === 'deleted_at')
            ->isNotEmpty();
    }
}
