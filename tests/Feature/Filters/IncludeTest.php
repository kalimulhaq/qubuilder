<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Order;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Role;
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
                'name' => 'orders',
                'filter' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']]],
            ]],
        ], User::class)->query()->first();

        $this->assertCount(1, $loaded->orders);
        $this->assertSame('completed', $loaded->orders->first()->status);
    }

    public function test_nested_include(): void
    {
        $user = User::create(['name' => 'Buyer']);
        $order = $user->orders()->create(['status' => 'completed']);
        $order->items()->create(['product_id' => 'P1', 'qty' => 2]);

        $loaded = Qubuilder::make([
            'include' => [[
                'name' => 'orders',
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

    public function test_aggregate_filter_scopes_the_aggregation(): void
    {
        $user = User::create(['name' => 'Buyer']);
        $user->orders()->create(['status' => 'completed', 'total' => 100]);
        $user->orders()->create(['status' => 'pending', 'total' => 50]);

        $loaded = Qubuilder::make([
            'include' => [[
                'name' => 'orders',
                'aggregate' => 'sum',
                'field' => 'total',
                'filter' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']]],
            ]],
        ], User::class)->query()->first();

        // Only the completed order is summed.
        $this->assertSame(100.0, (float) $loaded->orders_sum_total);
    }

    public function test_aggregate_count_with_filter(): void
    {
        $user = User::create(['name' => 'Buyer']);
        $user->orders()->create(['status' => 'completed']);
        $user->orders()->create(['status' => 'completed']);
        $user->orders()->create(['status' => 'pending']);

        $loaded = Qubuilder::make([
            'include' => [[
                'name' => 'orders',
                'aggregate' => 'count',
                'filter' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']]],
            ]],
        ], User::class)->query()->first();

        $this->assertSame(2, (int) $loaded->orders_count);
    }

    public function test_include_without_name_is_skipped(): void
    {
        $builder = Qubuilder::make(['include' => [['aggregate' => 'count']]], User::class)->query();

        $this->assertSame([], $builder->getEagerLoads());
    }

    // ── Includes on joined relations: columns are qualified ──────────────────

    public function test_select_inside_a_belongs_to_many_include_is_qualified(): void
    {
        // Both `roles` and the `role_user` pivot have an `id`: a bare `id` in the
        // relation query fails with "column is ambiguous".
        $user = $this->userWithThirdRole();

        $loaded = Qubuilder::make([
            'include' => [['name' => 'roles', 'select' => ['id', 'name']]],
        ], User::class)->query()->first();

        $role = $loaded->roles->first();

        $this->assertSame(3, $role->id);
        $this->assertSame('r3', $role->name);
        $this->assertSame($user->id, $role->pivot->user_id);
    }

    public function test_pivot_column_in_include_select_is_read_from_the_pivot_table(): void
    {
        $this->userWithThirdRole();

        $loaded = Qubuilder::make([
            'include' => [['name' => 'roles', 'select' => ['id', 'level']]],
        ], User::class)->query()->first();

        $this->assertSame(7, (int) $loaded->roles->first()->level);
    }

    public function test_include_with_sort_and_no_select_keeps_the_related_keys(): void
    {
        // An explicit `SELECT *` over the join returns duplicate `id`, `created_at` and
        // `updated_at` columns; the pivot row's values won before this was fixed.
        Carbon::setTestNow('2026-01-01 10:00:00');
        $user = $this->userWithThirdRole();
        Carbon::setTestNow('2026-02-02 12:00:00');
        $user->roles()->updateExistingPivot(3, ['level' => 8]);
        Carbon::setTestNow();

        $sql = $this->eagerLoadSql(['include' => [['name' => 'roles', 'sort' => ['name' => 'asc']]]]);
        $role = Qubuilder::make([
            'include' => [['name' => 'roles', 'sort' => ['name' => 'asc']]],
        ], User::class)->query()->first()->roles->first();

        $this->assertStringContainsString('select "qubuilder_roles".*', $sql);
        $this->assertSame(3, $role->id);
        $this->assertSame('2026-01-01 10:00:00', $role->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-02 12:00:00', $role->pivot->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_through_include_with_filter_and_no_select_keeps_the_related_keys(): void
    {
        // `items` goes through `orders`: the item's `id` must not be the order's.
        $user = User::create(['name' => 'Buyer']);
        Order::create(['user_id' => $user->id]);
        Order::create(['user_id' => $user->id]);
        Order::create(['user_id' => $user->id])->items()->create(['qty' => 1]);

        $loaded = Qubuilder::make([
            'include' => [[
                'name' => 'items',
                'filter' => ['AND' => [['field' => 'qty', 'op' => '>=', 'value' => 1]]],
            ]],
        ], User::class)->query()->first();

        $this->assertSame(1, $loaded->items->first()->id);
    }

    public function test_sort_group_and_filter_inside_a_joined_include_are_qualified(): void
    {
        $this->userWithThirdRole();

        $filters = [
            'include' => [[
                'name' => 'roles',
                'select' => ['id'],
                'filter' => ['AND' => [
                    ['field' => 'id', 'op' => '>=', 'value' => 1],
                    ['field' => 'created_at', 'op' => 'field|<=', 'value' => 'updated_at'],
                    ['field' => ['name', 'id'], 'op' => 'any|_like_', 'value' => 'r'],
                ]],
                'group' => ['id'],
                'sort' => ['id' => 'desc', 'raw:name' => 'asc'],
            ]],
        ];

        $sql = $this->eagerLoadSql($filters);

        $this->assertStringContainsString('"qubuilder_roles"."id" >= ?', $sql);
        $this->assertStringContainsString('"qubuilder_roles"."created_at" <= "qubuilder_roles"."updated_at"', $sql);
        $this->assertStringContainsString('"qubuilder_roles"."name" like ?', $sql);
        $this->assertStringContainsString('group by "qubuilder_roles"."id"', $sql);
        $this->assertStringContainsString('order by "qubuilder_roles"."id" desc, name asc', $sql);
        $this->assertSame([3], Qubuilder::make($filters, User::class)->query()->first()->roles->pluck('id')->all());
    }

    public function test_qualified_entries_inside_a_joined_include_stay_as_sent(): void
    {
        $this->userWithThirdRole();

        $sql = $this->eagerLoadSql([
            'include' => [['name' => 'roles', 'select' => ['roles.id', 'role_user.level as lvl']]],
        ]);

        // The grammar adds the table prefix; the qualifier left both entries as sent.
        $this->assertStringContainsString('select "qubuilder_roles"."id", "qubuilder_role_user"."level" as "lvl"', $sql);
    }

    public function test_include_on_a_relation_without_joins_is_not_qualified(): void
    {
        $user = User::create(['name' => 'Buyer']);
        $user->orders()->create(['status' => 'completed']);

        $sql = $this->eagerLoadSql([
            'include' => [['name' => 'orders', 'select' => ['id', 'status'], 'sort' => ['id' => 'asc']]],
        ]);

        $this->assertStringContainsString('select "id", "status"', $sql);
        $this->assertStringContainsString('order by "id" asc', $sql);
    }

    /**
     * A user whose only role is the third one, so the pivot row id (1) differs from the role id (3).
     */
    private function userWithThirdRole(): User
    {
        Role::create(['name' => 'r1']);
        Role::create(['name' => 'r2']);
        $third = Role::create(['name' => 'r3']);

        $user = User::create(['name' => 'Member']);
        $user->roles()->attach($third->id, ['level' => 7]);

        return $user;
    }

    /**
     * The SQL of the relation query that eager-loads the first include.
     *
     * @param  array<string, mixed>  $filters
     */
    private function eagerLoadSql(array $filters): string
    {
        DB::enableQueryLog();
        Qubuilder::make($filters, User::class)->query()->first();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $queries[1]['query'];
    }
}
