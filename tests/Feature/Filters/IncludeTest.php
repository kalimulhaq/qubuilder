<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class IncludeTest extends TestCase
{
    public function test_basic_include_registers_eager_load(): void
    {
        $builder = Qubuilder::make(['include' => [['name' => 'orders'], ['name' => 'profile']]], User::class)->query();

        $this->assertArrayHasKey('orders', $builder->getEagerLoads());
        $this->assertArrayHasKey('profile', $builder->getEagerLoads());
    }

    public function test_sub_filtered_include_scopes_the_relation(): void
    {
        $user = User::create(['name' => 'Buyer']);
        $user->orders()->create(['status' => 'completed', 'total' => 10]);
        $user->orders()->create(['status' => 'pending', 'total' => 5]);

        $loaded = Qubuilder::make([
            'include' => [[
                'name'   => 'orders',
                'filter' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']]],
            ]],
        ], User::class)->query()->first();

        $this->assertCount(1, $loaded->orders);
        $this->assertSame('completed', $loaded->orders->first()->status);
    }

    public function test_nested_include(): void
    {
        $user  = User::create(['name' => 'Buyer']);
        $order = $user->orders()->create(['status' => 'completed']);
        $order->items()->create(['product_id' => 'P1', 'qty' => 2]);

        $loaded = Qubuilder::make([
            'include' => [[
                'name'    => 'orders',
                'include' => [['name' => 'items']],
            ]],
        ], User::class)->query()->first();

        $this->assertTrue($loaded->orders->first()->relationLoaded('items'));
        $this->assertSame('P1', $loaded->orders->first()->items->first()->product_id);
    }

    public function test_aggregate_count(): void
    {
        $user = User::create(['name' => 'Buyer']);
        $user->orders()->create(['status' => 'completed']);
        $user->orders()->create(['status' => 'pending']);

        $loaded = Qubuilder::make([
            'include' => [['name' => 'orders', 'aggregate' => 'count']],
        ], User::class)->query()->first();

        $this->assertSame(2, (int) $loaded->orders_count);
    }

    public function test_aggregate_sum_avg_min_max(): void
    {
        $user = User::create(['name' => 'Buyer']);
        $user->orders()->create(['total' => 10]);
        $user->orders()->create(['total' => 30]);

        $loaded = Qubuilder::make([
            'include' => [
                ['name' => 'orders', 'aggregate' => 'sum', 'field' => 'total'],
                ['name' => 'orders', 'aggregate' => 'avg', 'field' => 'total'],
                ['name' => 'orders', 'aggregate' => 'min', 'field' => 'total'],
                ['name' => 'orders', 'aggregate' => 'max', 'field' => 'total'],
            ],
        ], User::class)->query()->first();

        $this->assertSame(40.0, (float) $loaded->orders_sum_total);
        $this->assertSame(20.0, (float) $loaded->orders_avg_total);
        $this->assertSame(10.0, (float) $loaded->orders_min_total);
        $this->assertSame(30.0, (float) $loaded->orders_max_total);
    }

    /**
     * KNOWN DISCREPANCY: the README states an aggregate include accepts a `filter`
     * key to scope the aggregation (e.g. sum only completed orders). In reality
     * Includes builds the aggregate sub-query with `Qubuilder::make($aggregateFilters,
     * $sub)` but never calls `->query()`, so the filter constraints are never applied
     * to the relation query — the aggregate is computed over ALL related rows.
     * (The non-aggregate include branch DOES call ->query(), so sub-filters work there.)
     * This test pins the ACTUAL behaviour: the filter is ignored and both orders are summed.
     */
    public function test_aggregate_filter_scope_is_currently_ignored(): void
    {
        $user = User::create(['name' => 'Buyer']);
        $user->orders()->create(['status' => 'completed', 'total' => 100]);
        $user->orders()->create(['status' => 'pending', 'total' => 50]);

        $loaded = Qubuilder::make([
            'include' => [[
                'name'      => 'orders',
                'aggregate' => 'sum',
                'field'     => 'total',
                'filter'    => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']]],
            ]],
        ], User::class)->query()->first();

        // Filter ignored: 100 + 50 instead of the documented 100.
        $this->assertSame(150.0, (float) $loaded->orders_sum_total);
    }

    public function test_include_without_name_is_skipped(): void
    {
        $builder = Qubuilder::make(['include' => [['aggregate' => 'count']]], User::class)->query();

        $this->assertSame([], $builder->getEagerLoads());
    }
}
