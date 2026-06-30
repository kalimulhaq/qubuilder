<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Illuminate\Database\Eloquent\Builder;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
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
            '_like'  => '%john',
            'like_'  => 'john%',
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
            'date'  => '%Y-%m-%d',
            'year'  => '%Y',
            'month' => '%m',
            'day'   => '%d',
            'time'  => '%H:%M:%S',
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

    /**
     * KNOWN DISCREPANCY: the README documents `any|_like_` mapping to a SQL `LIKE`
     * with `%...%` wrapping. In reality WhereClause passes the raw sub-operator
     * (`_like_`) straight to whereAny; Laravel rejects it as an invalid operator and
     * silently coerces the call to equality, binding the literal string `_like_` as
     * the value instead of `%john%`. The intended LIKE search is lost entirely.
     * This test pins the ACTUAL behaviour; update it if the source is fixed.
     */
    public function test_any_with_like_style_subop_is_coerced_to_equality(): void
    {
        $filter   = [['field' => ['first_name', 'last_name'], 'op' => 'any|_like_', 'value' => 'john']];
        $sql      = strtolower($this->sql($filter));
        $bindings = $this->bindings($filter);

        $this->assertStringNotContainsString('like', $sql);
        $this->assertStringContainsString('"first_name" = ?', $sql);
        // The pattern is lost: the operator token leaks in as the bound value.
        $this->assertContains('_like_', $bindings);
    }

    // ── Relationship existence ──────────────────────────────────────────────

    public function test_has_with_count_comparison(): void
    {
        $sql = strtolower($this->sql([['field' => 'orders', 'op' => 'has|>=', 'value' => 3]]));
        $this->assertStringContainsString('count(*)', $sql);
        $this->assertStringContainsString('>= 3', $sql);
    }

    public function test_has_with_subfilter_uses_whereHas(): void
    {
        $sql = strtolower($this->sql([[
            'field' => 'orders',
            'op'    => 'has',
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
            'op'    => 'doesnthave',
            'value' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'cancelled']]],
        ]]));
        $this->assertStringContainsString('not exists', $sql);
        $this->assertStringContainsString('"status" = ?', $sql);
    }

    // ── Structure: AND / OR / nesting ────────────────────────────────────────

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
                'op'    => 'has',
                'value' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']]],
            ]],
        ], User::class)->query()->pluck('name')->all();

        $this->assertSame(['Buyer'], $names);
    }
}
