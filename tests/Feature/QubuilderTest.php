<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Kalimulhaq\Qubuilder\Qubuilder;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder as QubuilderFacade;
use Kalimulhaq\Qubuilder\Support\Filters\Group;
use Kalimulhaq\Qubuilder\Support\Filters\Includes;
use Kalimulhaq\Qubuilder\Support\Filters\Select;
use Kalimulhaq\Qubuilder\Support\Filters\Sorts;
use Kalimulhaq\Qubuilder\Support\Filters\Where;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class QubuilderTest extends TestCase
{
    public function test_make_and_make_from_array_are_equivalent(): void
    {
        $filters = ['select' => ['id'], 'limit' => 10];

        $a = Qubuilder::make($filters, User::class);
        $b = Qubuilder::makeFromArray($filters, User::class);

        $this->assertSame($a->limit(), $b->limit());
        $this->assertSame(
            $a->query()->toSql(),
            $b->query()->toSql()
        );
    }

    public function test_facade_resolves_to_the_builder(): void
    {
        $builder = QubuilderFacade::make(['select' => ['id', 'name']], User::class)->query();

        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_make_from_request_parses_the_request(): void
    {
        $this->get('/?filter='.urlencode('{"field":"status","op":"=","value":"active"}').'&limit=5');

        $instance = Qubuilder::makeFromRequest(request(), User::class);

        $this->assertSame(5, $instance->limit());
        $this->assertStringContainsString('"status" = ?', $instance->query()->toSql());
    }

    public function test_model_accepts_a_class_string(): void
    {
        $builder = Qubuilder::make([], User::class)->query();
        $this->assertInstanceOf(User::class, $builder->getModel());
    }

    public function test_model_accepts_an_existing_builder(): void
    {
        $base = User::where('status', 'active');

        $builder = Qubuilder::make(['filter' => [['field' => 'age', 'op' => '>=', 'value' => 18]]], $base)->query();

        // Both the pre-existing and the applied condition are present.
        $this->assertStringContainsString('"status" = ?', $builder->toSql());
        $this->assertStringContainsString('"age" >= ?', $builder->toSql());
    }

    public function test_model_accepts_a_relation_instance(): void
    {
        $user = User::create(['name' => 'A']);

        $builder = Qubuilder::make(['filter' => [['field' => 'status', 'op' => '=', 'value' => 'done']]], $user->orders())->query();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertStringContainsString('"status" = ?', $builder->toSql());
    }

    public function test_query_throws_when_no_model_is_set(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Qubuilder::make([])->query();
    }

    public function test_page_and_limit_accessors_use_defaults(): void
    {
        $instance = Qubuilder::make([], User::class);

        $this->assertSame(1, $instance->page());
        $this->assertSame(15, $instance->limit()); // config('qubuilder.limit.default')
    }

    public function test_page_and_limit_accessors_read_from_filters(): void
    {
        $instance = Qubuilder::make(['page' => 4, 'limit' => 30], User::class);

        $this->assertSame(4, $instance->page());
        $this->assertSame(30, $instance->limit());
    }

    public function test_query_returns_unpaginated_builder(): void
    {
        // limit in filters is an accessor only; it must not be applied to the SQL.
        $builder = Qubuilder::make(['limit' => 5], User::class)->query();

        $this->assertStringNotContainsString('limit', strtolower($builder->toSql()));
    }

    public function test_filter_objects_are_populated_after_query(): void
    {
        $instance = Qubuilder::make([
            'select' => ['id'],
            'filter' => [['field' => 'status', 'op' => '=', 'value' => 'active']],
            'include' => [['name' => 'orders']],
            'sort' => ['name' => 'asc'],
            'group' => ['status'],
        ], User::class);

        $instance->query();

        $this->assertInstanceOf(Select::class, $instance->select());
        $this->assertInstanceOf(Where::class, $instance->where());
        $this->assertInstanceOf(Includes::class, $instance->include());
        $this->assertInstanceOf(Sorts::class, $instance->sort());
        $this->assertInstanceOf(Group::class, $instance->group());
    }
}
