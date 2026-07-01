<?php

namespace Kalimulhaq\Qubuilder\Tests\Unit\Rules;

use Kalimulhaq\Qubuilder\Rules\ValidateFilter;
use Kalimulhaq\Qubuilder\Tests\Concerns\InvokesRules;
use PHPUnit\Framework\TestCase;

class ValidateFilterTest extends TestCase
{
    use InvokesRules;

    private function rule(): ValidateFilter
    {
        return new ValidateFilter;
    }

    // ── Node shapes ──────────────────────────────────────────────────────────

    public function test_accepts_a_bare_condition(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'status', 'op' => '=', 'value' => 'active']);
    }

    public function test_accepts_an_and_group(): void
    {
        $this->assertRulePasses($this->rule(), [
            'AND' => [
                ['field' => 'status', 'op' => '=', 'value' => 'active'],
                ['field' => 'age', 'op' => '>=', 'value' => 18],
            ],
        ]);
    }

    public function test_accepts_a_nested_or_inside_and(): void
    {
        $this->assertRulePasses($this->rule(), [
            'AND' => [
                ['field' => 'status', 'op' => '=', 'value' => 'active'],
                ['OR' => [
                    ['field' => 'role', 'op' => '=', 'value' => 'admin'],
                    ['field' => 'role', 'op' => '=', 'value' => 'mod'],
                ]],
            ],
        ]);
    }

    public function test_accepts_a_flat_indexed_list(): void
    {
        $this->assertRulePasses($this->rule(), [
            ['field' => 'status', 'op' => '=', 'value' => 'active'],
            ['field' => 'age', 'op' => '>', 'value' => 0],
        ]);
    }

    public function test_accepts_a_json_string(): void
    {
        $this->assertRulePasses($this->rule(), '{"field":"status","op":"=","value":"active"}');
    }

    public function test_rejects_invalid_json_string(): void
    {
        $errors = $this->assertRuleFails($this->rule(), '{not json}');
        $this->assertStringContainsString('valid JSON', $errors[0]);
    }

    public function test_rejects_a_node_that_is_neither_condition_group_nor_list(): void
    {
        $this->assertRuleFails($this->rule(), ['foo' => 'bar']);
    }

    public function test_omitted_op_defaults_to_equals_and_passes(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'status', 'value' => 'active']);
    }

    // ── Operator validation ──────────────────────────────────────────────────

    public function test_rejects_unknown_operator(): void
    {
        $errors = $this->assertRuleFails($this->rule(), ['field' => 'status', 'op' => 'bogus', 'value' => 'x']);
        $this->assertStringContainsString('not a recognised operator', $errors[0]);
    }

    public function test_field_requires_non_empty_string(): void
    {
        $this->assertRuleFails($this->rule(), ['field' => '', 'op' => '=', 'value' => 'x']);
    }

    // ── Sub-operator rules ───────────────────────────────────────────────────

    public function test_has_accepts_comparison_sub_operator(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'orders', 'op' => 'has|>=', 'value' => 3]);
    }

    public function test_has_rejects_non_comparison_sub_operator(): void
    {
        $this->assertRuleFails($this->rule(), ['field' => 'orders', 'op' => 'has|like', 'value' => 3]);
    }

    public function test_field_op_requires_comparison_sub_operator(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'updated_at', 'op' => 'field|>', 'value' => 'created_at']);
        $this->assertRuleFails($this->rule(), ['field' => 'updated_at', 'op' => 'field|bogus', 'value' => 'created_at']);
    }

    public function test_any_sub_operator_must_be_known(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => ['a', 'b'], 'op' => 'any|_like_', 'value' => 'x']);
        $this->assertRuleFails($this->rule(), ['field' => ['a', 'b'], 'op' => 'any|nope', 'value' => 'x']);
    }

    public function test_operator_without_sub_op_support_rejects_one(): void
    {
        $errors = $this->assertRuleFails($this->rule(), ['field' => 'status', 'op' => '=|>', 'value' => 'x']);
        $this->assertStringContainsString('does not support a sub-operator', $errors[0]);
    }

    // ── Value rules per operator ─────────────────────────────────────────────

    public function test_null_and_not_null_need_no_value(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'verified_at', 'op' => 'not_null']);
        $this->assertRulePasses($this->rule(), ['field' => 'deleted_at', 'op' => 'null']);
    }

    public function test_in_requires_non_empty_array(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'role', 'op' => 'in', 'value' => ['a', 'b']]);
        $this->assertRuleFails($this->rule(), ['field' => 'role', 'op' => 'in', 'value' => []]);
        $this->assertRuleFails($this->rule(), ['field' => 'role', 'op' => 'in', 'value' => 'scalar']);
    }

    public function test_between_requires_exactly_two_elements(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'age', 'op' => 'between', 'value' => [1, 5]]);
        $this->assertRuleFails($this->rule(), ['field' => 'age', 'op' => 'between', 'value' => [1, 2, 3]]);
        $this->assertRuleFails($this->rule(), ['field' => 'age', 'op' => 'between', 'value' => [1]]);
    }

    public function test_raw_requires_array_bindings(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'YEAR(created_at) = ?', 'op' => 'raw', 'value' => [2024]]);
        $this->assertRuleFails($this->rule(), ['field' => 'YEAR(created_at) = ?', 'op' => 'raw', 'value' => 2024]);
    }

    public function test_field_op_requires_non_empty_string_value(): void
    {
        $this->assertRuleFails($this->rule(), ['field' => 'updated_at', 'op' => 'field|>', 'value' => '']);
    }

    public function test_has_value_may_be_scalar_or_filter_object(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'orders', 'op' => 'has', 'value' => 3]);
        $this->assertRulePasses($this->rule(), [
            'field' => 'orders',
            'op' => 'has',
            'value' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'done']]],
        ]);
        // No value at all is allowed for has
        $this->assertRulePasses($this->rule(), ['field' => 'orders', 'op' => 'has']);
    }

    public function test_doesnthave_value_when_present_must_be_filter_object(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => 'orders', 'op' => 'doesnthave']);
        $this->assertRuleFails($this->rule(), ['field' => 'orders', 'op' => 'doesnthave', 'value' => 'scalar']);
    }

    public function test_multi_column_field_must_be_non_empty_string_list(): void
    {
        $this->assertRulePasses($this->rule(), ['field' => ['a', 'b'], 'op' => 'any|=', 'value' => 'x']);
        $this->assertRuleFails($this->rule(), ['field' => 'a', 'op' => 'any|=', 'value' => 'x']);
        $this->assertRuleFails($this->rule(), ['field' => ['a', 123], 'op' => 'any|=', 'value' => 'x']);
    }

    public function test_any_value_must_be_scalar(): void
    {
        $this->assertRuleFails($this->rule(), ['field' => ['a', 'b'], 'op' => 'any|=', 'value' => ['x']]);
        $this->assertRuleFails($this->rule(), ['field' => ['a', 'b'], 'op' => 'any|=']);
    }

    public function test_comparison_value_must_be_scalar_not_array(): void
    {
        $this->assertRuleFails($this->rule(), ['field' => 'status', 'op' => '=', 'value' => ['a']]);
        $this->assertRuleFails($this->rule(), ['field' => 'status', 'op' => '=']);
    }

    public function test_collects_errors_from_nested_conditions(): void
    {
        $errors = $this->assertRuleFails($this->rule(), [
            'AND' => [
                ['field' => 'status', 'op' => 'bogus', 'value' => 'x'],
                ['field' => '', 'op' => '=', 'value' => 'y'],
            ],
        ]);
        $this->assertGreaterThanOrEqual(2, count($errors));
    }
}
