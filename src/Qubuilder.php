<?php

namespace Kalimulhaq\Qubuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Kalimulhaq\Qubuilder\Support\Filters\Group;
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
 *
 * @template TModel of Model
 *
 * @phpstan-consistent-constructor
 */
class Qubuilder
{
    /**
     * The raw filters provided for building the query.
     *
     * @var array<int|string, mixed>
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
     * The GROUP BY column list.
     *
     * @var Group
     */
    protected $group;

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
     * @var class-string<TModel>|null
     */
    protected $model = Model::class;

    /**
     * The query builder instance.
     *
     * @var Builder<TModel>|null
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
     * Get the GROUP BY column list.
     */
    public function group(): Group
    {
        return $this->group;
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
     * @template TMakeModel of Model
     *
     * @param  array<int|string, mixed>  $filters  The filters to apply.
     * @param  class-string<TMakeModel>|Builder<TMakeModel>|Relation<TMakeModel, Model, mixed>|null  $model  The model class or query builder instance.
     * @return self<TMakeModel>
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
     * @template TMakeModel of Model
     *
     * @param  array<int|string, mixed>  $array  The array of filters.
     * @param  class-string<TMakeModel>|Builder<TMakeModel>|Relation<TMakeModel, Model, mixed>|null  $model  The model class or query builder instance.
     * @return self<TMakeModel>
     */
    public static function makeFromArray(array $array, mixed $model = null): self
    {
        return static::make($array, $model);
    }

    /**
     * Create a Qubuilder instance from a request's input.
     *
     * @template TMakeModel of Model
     *
     * @param  Request|null  $req  The HTTP request instance.
     * @param  class-string<TMakeModel>|Builder<TMakeModel>|Relation<TMakeModel, Model, mixed>|null  $model  The model class or query builder instance.
     * @return self<TMakeModel>
     */
    public static function makeFromRequest(?Request $req = null, mixed $model = null): self
    {
        return static::make(Helper::input($req), $model);
    }

    /**
     * Set the filters for the query builder.
     *
     * @param  array<int|string, mixed>  $filters  The filters to apply.
     * @return $this
     */
    public function filters(array $filters = []): self
    {
        $this->filters = $filters;

        $this->page = Arr::get($this->filters, 'page', 1);
        $this->limit = Arr::get($this->filters, 'limit', config('qubuilder.limit.default', 15));

        return $this;
    }

    /**
     * Set the model for the query builder.
     *
     * @template TSetModel of Model
     *
     * @param  class-string<TSetModel>|Builder<TSetModel>|Relation<TSetModel, Model, mixed>|null  $model  The model class or query builder instance.
     * @return self<TSetModel>
     */
    public function model(mixed $model): self
    {
        if (is_string($model)) {
            $this->model = $model;
            // @phpstan-ignore assign.propertyType (this method reassigns TModel for the current instance; PHPStan can't express that for a nested-generic property — see phpstan/phpstan#12592)
            $this->builder = $this->model::query();
        } elseif ($model instanceof Builder) {
            // @phpstan-ignore assign.propertyType (this method reassigns TModel for the current instance; PHPStan can't express that for a nested-generic property — see phpstan/phpstan#12592)
            $this->builder = $model;
        } elseif ($model instanceof Relation) {
            // @phpstan-ignore assign.propertyType (this method reassigns TModel for the current instance; PHPStan can't express that for a nested-generic property — see phpstan/phpstan#12592)
            $this->builder = $model->getQuery();
        }

        return $this;
    }

    /**
     * Build and return the query builder instance with the applied
     * select, where, include, and sort conditions.
     *
     * @return Builder<TModel>
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
            // @phpstan-ignore method.notFound (withTrashed() is a macro added at runtime by SoftDeletingScope for models using the SoftDeletes trait; PHPStan can't see macro registrations)
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
        $includeArray = Helper::allowInclude() ? Arr::get($this->filters, 'include') : [];
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

    private function buildGroup(): void
    {
        $groupArray = Arr::get($this->filters, 'group');
        $this->group = new Group($groupArray);
        $this->builder = $this->group->build($this->builder);
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
