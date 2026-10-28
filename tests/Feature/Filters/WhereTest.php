<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Illuminate\Database\Eloquent\Builder;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Order;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Role;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class WhereTest extends TestCase
{
    private function builder(array $filter): Builder
    {
        return Qubuilder::make(['filter' => $filter], User::class)->query();
    }

    private function sql(array $filter): string
    {
        return $this->builder($filter)->toSql();
    }

    private function bindings(array $filter): array
    {
        return $this->builder($filter)->getBindings();
    }

    // ── Comparison ─────────────────────────────────────────────────────────────

    public function test_equals_operator(): void
    {
        $this->assertStringContainsString('"status" = ?', $this->sql([['field' => 'status', 'op' => '=', 'value' => 'active']]));
    }

    public function test_omitted_op_defaults_to_equals(): void
    {
        $this->assertStringContainsString('"status" = ?', $this->sql([['field' => 'status', 'value' => 'active']]));
    }

    public function test_all_comparison_operators(): void
    {
        foreach (['!=', '<>', '>', '<', '>=', '<='] as $op) {
            $this->assertStringContainsString("\"age\" {$op} ?", $this->sql([['field' => 'age', 'op' => $op, 'value' => 18]]));
        }
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function test_in_operator(): void
    {
        $sql = $this->sql([['field' => 'role', 'op' => 'in', 'value' => ['admin', 'editor']]]);
        $this->assertStringContainsString('"role" in (?, ?)', $sql);
        $this->assertSame(['admin', 'editor'], $this->bindings([['field' => 'role', 'op' => 'in', 'value' => ['admin', 'editor']]]));
    }

    public function test_not_in_operator(): void
    {
        $this->assertStringContainsString('"role" not in (?, ?)', $this->sql([['field' => 'role', 'op' => 'not_in', 'value' => ['a', 'b']]]));
    }

    public function test_scalar_value_is_wrapped_for_in(): void
    {
        // Arr::wrap turns a scalar into a single-element list.
        $this->assertStringContainsString('"role" in (?)', $this->sql([['field' => 'role', 'op' => 'in', 'value' => 'admin']]));
    }

    public function test_between_operator(): void
    {
        $sql = $this->sql([['field' => 'age', 'op' => 'between', 'value' => [18, 65]]]);
        $this->assertStringContainsString('"age" between ? and ?', $sql);
    }

    public function test_not_between_operator(): void
    {
        $this->assertStringContainsString('"age" not between ? and ?', $this->sql([['field' => 'age', 'op' => 'not_between', 'value' => [0, 10]]]));
    }

    // ── Null ───────────────────────────────────────────────────────────────────

    public function test_null_operator(): void
    {
        $this->assertStringContainsString('"verified_at" is null', $this->sql([['field' => 'verified_at', 'op' => 'null']]));
    }

    public function test_not_null_operator(): void
    {
        $this->assertStringContainsString('"verified_at" is not null', $this->sql([['field' => 'verified_at', 'op' => 'not_null']]));
    }

    // ── LIKE ─────────────────────────────────────────────────────────────────

    public function test_like_patterns(): void
    {
        $cases = [
            '_like' => '%john',
            'like_' => 'john%',
            '_like_' => '%john%',
        ];

        foreach ($cases as $op => $expectedBinding) {
            $filter = [['field' => 'name', 'op' => $op, 'value' => 'john']];
            $this->assertStringContainsString('"name" like ?', $this->sql($filter), "op {$op}");
            $this->assertContains($expectedBinding, $this->bindings($filter), "op {$op} binding");
        }
    }

    // ── Date / time ────────────────────────────────────────────────────────────

    public function test_date_operators_emit_strftime(): void
    {
        $map = [
            'date' => '%Y-%m-%d',
            'year' => '%Y',
            'month' => '%m',
            'day' => '%d',
            'time' => '%H:%M:%S',
        ];

        foreach ($map as $op => $token) {
            $sql = $this->sql([['field' => 'created_at', 'op' => $op, 'value' => 1]]);
            $this->assertStringContainsString($token, $sql, "op {$op}");
        }
    }

    // ── JSON ─────────────────────────────────────────────────────────────────

    public function test_json_contains_builds_sql(): void
    {
        $sql = $this->sql([['field' => 'settings->notifications', 'op' => 'json_contains', 'value' => 'email']]);
        $this->assertStringContainsString('json_each', strtolower($sql));
    }

    public function test_json_not_contains_builds_sql(): void
    {
        $sql = $this->sql([['field' => 'settings->flags', 'op' => 'json_not_contains', 'value' => 'beta']]);
        $this->assertStringContainsString('not exists', strtolower($sql));
    }

    // ── Column comparison / raw ──────────────────────────────────────────────

    public function test_field_column_comparison(): void
    {
        $this->assertStringContainsString('"updated_at" > "created_at"', $this->sql([['field' => 'updated_at', 'op' => 'field|>', 'value' => 'created_at']]));
    }

    public function test_raw_expression_with_bindings(): void
    {
        $filter = [['field' => 'YEAR(created_at) = ?', 'op' => 'raw', 'value' => [2024]]];
        $this->assertStringContainsString('YEAR(created_at) = ?', $this->sql($filter));
        $this->assertSame([2024], $this->bindings($filter));
    }

    // ── Multi-column any/all/none ────────────────────────────────────────────

    public function test_any_operator_with_comparison_subop(): void
    {
        $sql = $this->sql([['field' => ['first_name', 'last_name'], 'op' => 'any|=', 'value' => 'john']]);
        $this->assertStringContainsString('("first_name" = ? or "last_name" = ?)', $sql);
    }

    public function test_all_operator_with_comparison_subop(): void
    {
        $sql = $this->sql([['field' => ['first_name', 'last_name'], 'op' => 'all|=', 'value' => 'john']]);
        $this->assertStringContainsString('("first_name" = ? and "last_name" = ?)', $sql);
    }

    public function test_none_operator_builds_negated_group(): void
    {
        $sql = $this->sql([['field' => ['first_name', 'last_name'], 'op' => 'none|=', 'value' => 'john']]);
        $this->assertStringContainsString('not (', $sql);
    }

    public function test_none_operator_filters_real_rows(): void
    {
        // `none` is polyfilled as NOT(whereAny) so it works on every Laravel 11.x
        // (whereNone() only exists from ~11.15). This proves the runtime behaviour.
        User::create(['first_name' => 'John', 'last_name' => 'Doe']);
        User::create(['first_name' => 'Jane', 'last_name' => 'Smith']);
        User::create(['first_name' => 'Bob', 'last_name' => 'John']);

        $names = Qubuilder::make([
            'filter' => [['field' => ['first_name', 'last_name'], 'op' => 'none|=', 'value' => 'John']],
        ], User::class)->query()->pluck('first_name')->all();

        // Only Jane matches: neither of her columns equals 'John'.
        $this->assertSame(['Jane'], $names);
    }

    public function test_any_with_like_style_subop_maps_to_like(): void
    {
        $filter = [['field' => ['first_name', 'last_name'], 'op' => 'any|_like_', 'value' => 'john']];
        $sql = $this->sql($filter);
        $bindings = $this->bindings($filter);

        $this->assertStringContainsString('("first_name" like ? or "last_name" like ?)', $sql);
        $this->assertContains('%john%', $bindings);
    }

    public function test_all_with_starts_with_like_subop(): void
    {
        $filter = [['field' => ['first_name', 'last_name'], 'op' => 'all|like_', 'value' => 'jo']];
        $sql = $this->sql($filter);

        $this->assertStringContainsString('("first_name" like ? and "last_name" like ?)', $sql);
        $this->assertContains('jo%', $this->bindings($filter));
    }

    public function test_any_with_like_subop_filters_real_rows(): void
    {
        User::create(['first_name' => 'Johnny', 'last_name' => 'Doe']);
        User::create(['first_name' => 'Jane', 'last_name' => 'Johnson']);
        User::create(['first_name' => 'Bob', 'last_name' => 'Smith']);

        $names = Qubuilder::make([
            'filter' => [['field' => ['first_name', 'last_name'], 'op' => 'any|_like_', 'value' => 'john']],
        ], User::class)->query()->pluck('first_name')->all();
        sort($names);

        // Matches Johnny (first_name) and Jane (last_name Johnson), not Bob.
        $this->assertSame(['Jane', 'Johnny'], $names);
    }

    // ── Relationship existence ──────────────────────────────────────────────

    public function test_has_with_count_comparison(): void
    {
        $sql = strtolower($this->sql([['field' => 'orders', 'op' => 'has|>=', 'value' => 3]]));
        $this->assertStringContainsString('count(*)', $sql);
        $this->assertStringContainsString('>= 3', $sql);
    }

    public function test_has_with_subfilter_uses_where_has(): void
    {
        $sql = strtolower($this->sql([[
            'field' => 'orders',
            'op' => 'has',
            'value' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']]],
        ]]));
        $this->assertStringContainsString('exists', $sql);
        $this->assertStringContainsString('"status" = ?', $sql);
    }

    public function test_doesnthave(): void
    {
        $sql = strtolower($this->sql([['field' => 'invoices', 'op' => 'doesnthave']]));
        $this->assertStringContainsString('not exists', $sql);
    }

    public function test_doesnthave_with_subfilter(): void
    {
        $sql = strtolower($this->sql([[
            'field' => 'invoices',
            'op' => 'doesnthave',
            'value' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'cancelled']]],
        ]]));
        $this->assertStringContainsString('not exists', $sql);
        $this->assertStringContainsString('"status" = ?', $sql);
    }

    public function test_has_dotted_path_builds_nested_exists(): void
    {
        // `orders.items` is unrolled as `orders has { items has {...} }`: one EXISTS per segment,
        // the sub-filter on the last one.
        $sql = strtolower($this->sql([[
            'field' => 'orders.items',
            'op' => 'has',
            'value' => ['AND' => [['field' => 'qty', 'op' => '>', 'value' => 5]]],
        ]]));

        $orders = strpos($sql, 'exists (select * from "qubuilder_orders"');
        $items = strpos($sql, 'exists (select * from "qubuilder_items"');

        $this->assertNotFalse($orders);
        $this->assertNotFalse($items);
        $this->assertGreaterThan($orders, $items);
        $this->assertStringContainsString('"qty" > ?', $sql);
    }

    public function test_doesnthave_dotted_path_negates_the_outer_segment_only(): void
    {
        $sql = strtolower($this->sql([['field' => 'orders.items', 'op' => 'doesnthave']]));

        $this->assertStringContainsString('not exists (select * from "qubuilder_orders"', $sql);
        $this->assertStringContainsString('and ((exists (select * from "qubuilder_items"', $sql);
    }

    // ── Structure: AND / OR / nesting ────────────────────────────────────────

    public function test_and_group_with_equals_and_in_conditions_keeps_its_sql_without_a_morph_relation(): void
    {
        // The `=` / `in` conditions of an AND group are passed down as context for a morph
        // `has`; without a MorphTo they must not change the query at all.
        $query = $this->builder(['and' => [
            ['field' => 'status', 'value' => 'active'],
            ['field' => 'role', 'op' => 'in', 'value' => ['a', 'b']],
            ['or' => [['field' => 'type', 'value' => 'x'], ['field' => 'type', 'value' => 'y']]],
            ['field' => 'orders', 'op' => 'has', 'value' => ['and' => [['field' => 'status', 'value' => 'paid']]]],
        ]]);

        $this->assertSame(
            'select * from "qubuilder_users" where (("status" = ? and "role" in (?, ?) and (("type" = ? or "type" = ?)) '
            .'and exists (select * from "qubuilder_orders" where "qubuilder_users"."id" = "qubuilder_orders"."user_id" '
            .'and (("status" = ?))))) and "qubuilder_users"."deleted_at" is null',
            $query->toSql()
        );
        $this->assertSame(['active', 'a', 'b', 'x', 'y', 'paid'], $query->getBindings());
    }

    public function test_flat_list_is_implicit_and(): void
    {
        $sql = $this->sql([
            ['field' => 'status', 'op' => '=', 'value' => 'active'],
            ['field' => 'age', 'op' => '>=', 'value' => 18],
        ]);
        $this->assertStringContainsString('"status" = ? and "age" >= ?', $sql);
    }

    public function test_bare_condition_is_normalised(): void
    {
        $sql = $this->sql(['field' => 'status', 'op' => '=', 'value' => 'active']);
        $this->assertStringContainsString('"status" = ?', $sql);
    }

    public function test_or_group_uses_or(): void
    {
        $sql = $this->sql([
            'OR' => [
                ['field' => 'country', 'op' => '=', 'value' => 'US'],
                ['field' => 'country', 'op' => '=', 'value' => 'CA'],
            ],
        ]);
        $this->assertStringContainsString('"country" = ? or "country" = ?', $sql);
    }

    public function test_nested_or_inside_and_is_parenthesised(): void
    {
        $sql = $this->sql([
            'AND' => [
                ['field' => 'status', 'op' => '=', 'value' => 'active'],
                ['OR' => [
                    ['field' => 'role', 'op' => '=', 'value' => 'admin'],
                    ['field' => 'role', 'op' => '=', 'value' => 'mod'],
                ]],
            ],
        ]);
        // The OR group is wrapped in its own parentheses inside the outer AND.
        $this->assertStringContainsString('("role" = ? or "role" = ?)', $sql);
        $this->assertStringContainsString('"status" = ? and', $sql);
    }

    // ── Execution checks (seeded) ────────────────────────────────────────────

    public function test_execution_filters_real_rows(): void
    {
        User::create(['name' => 'Active', 'status' => 'active', 'age' => 30]);
        User::create(['name' => 'Inactive', 'status' => 'inactive', 'age' => 30]);
        User::create(['name' => 'Young', 'status' => 'active', 'age' => 15]);

        $results = Qubuilder::make([
            'filter' => ['AND' => [
                ['field' => 'status', 'op' => '=', 'value' => 'active'],
                ['field' => 'age', 'op' => '>=', 'value' => 18],
            ]],
        ], User::class)->query()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Active', $results->first()->name);
    }

    public function test_execution_in_and_like(): void
    {
        User::create(['name' => 'Johnny', 'role' => 'admin']);
        User::create(['name' => 'Jane', 'role' => 'editor']);
        User::create(['name' => 'Bob', 'role' => 'guest']);

        $byRole = Qubuilder::make([
            'filter' => [['field' => 'role', 'op' => 'in', 'value' => ['admin', 'editor']]],
        ], User::class)->query()->pluck('name')->all();
        sort($byRole);
        $this->assertSame(['Jane', 'Johnny'], $byRole);

        $byName = Qubuilder::make([
            'filter' => [['field' => 'name', 'op' => 'like_', 'value' => 'J']],
        ], User::class)->query()->pluck('name')->all();
        sort($byName);
        $this->assertSame(['Jane', 'Johnny'], $byName);
    }

    public function test_execution_has_with_subfilter(): void
    {
        $withCompleted = User::create(['name' => 'Buyer']);
        $withCompleted->orders()->create(['status' => 'completed']);

        $withPending = User::create(['name' => 'Browser']);
        $withPending->orders()->create(['status' => 'pending']);

        $names = Qubuilder::make([
            'filter' => [[
                'field' => 'orders',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']]],
            ]],
        ], User::class)->query()->pluck('name')->all();

        $this->assertSame(['Buyer'], $names);
    }

    public function test_execution_has_dotted_path(): void
    {
        $bulk = User::create(['name' => 'Bulk']);
        $bulk->orders()->create([])->items()->create(['qty' => 10]);

        $single = User::create(['name' => 'Single']);
        $single->orders()->create([])->items()->create(['qty' => 1]);

        User::create(['name' => 'Browser']);

        $names = Qubuilder::make([
            'filter' => [[
                'field' => 'orders.items',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'qty', 'op' => '>', 'value' => 5]]],
            ]],
        ], User::class)->query()->pluck('name')->all();

        $this->assertSame(['Bulk'], $names);
    }

    public function test_execution_doesnthave_dotted_path(): void
    {
        // Same meaning as Laravel's doesntHave('orders.items'): no order that has an item.
        // A user whose only order is empty qualifies, as does one with no order at all.
        $filled = User::create(['name' => 'Filled']);
        $filled->orders()->create([])->items()->create(['qty' => 1]);

        $empty = User::create(['name' => 'Empty']);
        $empty->orders()->create([]);

        User::create(['name' => 'None']);

        $names = Qubuilder::make([
            'filter' => [['field' => 'orders.items', 'op' => 'doesnthave']],
        ], User::class)->query()->pluck('name')->all();

        $this->assertSame(['Empty', 'None'], $names);
    }

    // ── Sub-filters on joined relations: columns are qualified ──────────────

    public function test_has_on_a_belongs_to_many_qualifies_an_id_shared_with_the_pivot(): void
    {
        // `role_user` has its own `id`, so a bare `id` in the sub-filter was ambiguous.
        [$member] = $this->usersWithRoles();

        $query = $this->builder([['field' => 'roles', 'op' => 'has', 'value' => ['and' => [['field' => 'id', 'value' => 3]]]]]);

        $this->assertStringContainsString('"qubuilder_roles"."id" = ?', $query->toSql());
        $this->assertSame([$member->id], $query->pluck('id')->all());
    }

    public function test_doesnthave_on_a_belongs_to_many_qualifies_created_at(): void
    {
        [$member, $guest] = $this->usersWithRoles();

        $query = $this->builder([['field' => 'roles', 'op' => 'doesnthave', 'value' => ['and' => [['field' => 'created_at', 'op' => 'null']]]]]);

        $this->assertStringContainsString('"qubuilder_roles"."created_at" is null', $query->toSql());
        $this->assertSame([$member->id, $guest->id], $query->orderBy('id')->pluck('id')->all());
    }

    public function test_has_on_a_through_relation_qualifies_id_and_keeps_intermediate_columns(): void
    {
        // `items` goes through `orders`: `id` is on both tables, `status` only on orders.
        $buyer = User::create(['name' => 'Buyer']);
        Order::create(['user_id' => $buyer->id, 'status' => 'open']);
        Order::create(['user_id' => $buyer->id, 'status' => 'paid'])->items()->create(['qty' => 1]);
        User::create(['name' => 'Browser']);

        $query = $this->builder([['field' => 'items', 'op' => 'has', 'value' => ['and' => [
            ['field' => 'id', 'op' => '>=', 'value' => 1],
            ['field' => 'status', 'value' => 'paid'],
        ]]]]);

        $this->assertStringContainsString('"qubuilder_items"."id" >= ?', $query->toSql());
        $this->assertStringContainsString('"qubuilder_orders"."status" = ?', $query->toSql());
        $this->assertSame([$buyer->id], $query->pluck('id')->all());
    }

    public function test_has_nested_inside_a_has_qualifies_the_inner_sub_filter(): void
    {
        // roles has { users has { id = ... } }: the inner relation (Role::users) joins role_user too.
        [$member] = $this->usersWithRoles();

        $query = $this->builder([['field' => 'roles', 'op' => 'has', 'value' => ['and' => [[
            'field' => 'users',
            'op' => 'has',
            'value' => ['and' => [['field' => 'id', 'value' => $member->id]]],
        ]]]]]);

        $this->assertStringContainsString('"qubuilder_users"."id" = ?', $query->toSql());
        $this->assertSame([$member->id], $query->pluck('id')->all());
    }

    public function test_has_on_a_belongs_to_many_reads_pivot_columns_from_the_pivot(): void
    {
        // `level` is declared with withPivot(), `granted_by` is not; both live on role_user.
        [$member] = $this->usersWithRoles();
        $member->roles()->updateExistingPivot(3, ['granted_by' => 'admin']);

        $query = $this->builder([['field' => 'roles', 'op' => 'has', 'value' => ['and' => [
            ['field' => 'level', 'value' => 2],
            ['field' => 'granted_by', 'value' => 'admin'],
        ]]]]);

        $this->assertStringContainsString('"qubuilder_role_user"."level" = ?', $query->toSql());
        $this->assertStringContainsString('"qubuilder_role_user"."granted_by" = ?', $query->toSql());
        $this->assertSame([$member->id], $query->pluck('id')->all());
    }

    public function test_has_on_a_relation_without_joins_keeps_its_sql(): void
    {
        $sql = $this->sql([['field' => 'orders', 'op' => 'has', 'value' => ['and' => [['field' => 'id', 'value' => 1]]]]]);

        $this->assertStringContainsString('"qubuilder_users"."id" = "qubuilder_orders"."user_id" and (("id" = ?))', $sql);
    }

    /**
     * A member whose only role is the third one (pivot id 1, role id 3, level 2), and a guest
     * with no role.
     *
     * @return array{0: User, 1: User}
     */
    private function usersWithRoles(): array
    {
        Role::create(['name' => 'r1']);
        Role::create(['name' => 'r2']);
        $third = Role::create(['name' => 'r3']);

        $member = User::create(['name' => 'Member']);
        $member->roles()->attach($third->id, ['level' => 2]);

        return [$member, User::create(['name' => 'Guest'])];
    }
}
