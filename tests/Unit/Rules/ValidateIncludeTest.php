<?php

namespace Kalimulhaq\Qubuilder\Tests\Unit\Rules;

use Kalimulhaq\Qubuilder\Rules\ValidateInclude;
use Kalimulhaq\Qubuilder\Tests\Concerns\InvokesRules;
use PHPUnit\Framework\TestCase;

class ValidateIncludeTest extends TestCase
{
    use InvokesRules;

    private function rule(): ValidateInclude
    {
        return new ValidateInclude;
    }

    public function test_accepts_a_list_of_include_items(): void
    {
        $this->assertRulePasses($this->rule(), [
            ['name' => 'profile'],
            ['name' => 'orders', 'aggregate' => 'count'],
            ['name' => 'orders', 'select' => ['id', 'status']],
        ]);
    }

    public function test_accepts_a_json_string(): void
    {
        $this->assertRulePasses($this->rule(), '[{"name":"profile"}]');
    }

    public function test_rejects_non_indexed_array(): void
    {
        $errors = $this->assertRuleFails($this->rule(), ['name' => 'profile']);
        $this->assertStringContainsString('indexed array', $errors[0]);
    }

    public function test_rejects_invalid_json(): void
    {
        $this->assertRuleFails($this->rule(), 'not json');
    }

    public function test_item_requires_name(): void
    {
        $errors = $this->assertRuleFails($this->rule(), [['aggregate' => 'count']]);
        $this->assertStringContainsString("required key 'name'", $errors[0]);
    }

    public function test_item_must_be_object(): void
    {
        $this->assertRuleFails($this->rule(), ['orders']);
    }

    public function test_aggregate_must_be_in_enum(): void
    {
        $this->assertRulePasses($this->rule(), [['name' => 'orders', 'aggregate' => 'count']]);
        $this->assertRuleFails($this->rule(), [['name' => 'orders', 'aggregate' => 'median']]);
    }

    public function test_field_required_for_avg_sum_min_max(): void
    {
        foreach (['avg', 'sum', 'min', 'max'] as $agg) {
            $this->assertRuleFails($this->rule(), [['name' => 'orders', 'aggregate' => $agg]]);
            $this->assertRulePasses($this->rule(), [['name' => 'orders', 'aggregate' => $agg, 'field' => 'total']]);
        }
    }

    public function test_count_does_not_require_field(): void
    {
        $this->assertRulePasses($this->rule(), [['name' => 'orders', 'aggregate' => 'count']]);
    }

    public function test_select_and_group_validated_as_string_arrays(): void
    {
        $this->assertRulePasses($this->rule(), [['name' => 'orders', 'select' => ['id', 'name']]]);
        $this->assertRuleFails($this->rule(), [['name' => 'orders', 'select' => ['id', 123]]]);
        $this->assertRuleFails($this->rule(), [['name' => 'orders', 'group' => 'status']]);
    }

    public function test_filter_is_recursively_validated(): void
    {
        $this->assertRulePasses($this->rule(), [[
            'name'   => 'orders',
            'filter' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'done']]],
        ]]);
        $this->assertRuleFails($this->rule(), [[
            'name'   => 'orders',
            'filter' => ['AND' => [['field' => 'status', 'op' => 'bogus', 'value' => 'done']]],
        ]]);
    }

    public function test_sort_is_recursively_validated(): void
    {
        $this->assertRulePasses($this->rule(), [['name' => 'orders', 'sort' => ['total' => 'desc']]]);
        $this->assertRuleFails($this->rule(), [['name' => 'orders', 'sort' => ['total' => 'sideways']]]);
    }

    public function test_nested_includes_are_recursively_validated(): void
    {
        $this->assertRulePasses($this->rule(), [[
            'name'    => 'orders',
            'include' => [['name' => 'items', 'select' => ['id', 'qty']]],
        ]]);
        $this->assertRuleFails($this->rule(), [[
            'name'    => 'orders',
            'include' => [['aggregate' => 'count']], // missing name
        ]]);
    }

    public function test_select_must_be_subset_of_group(): void
    {
        $this->assertRulePasses($this->rule(), [[
            'name'   => 'orders',
            'select' => ['status'],
            'group'  => ['status', 'type'],
        ]]);
        $errors = $this->assertRuleFails($this->rule(), [[
            'name'   => 'orders',
            'select' => ['status', 'total'],
            'group'  => ['status'],
        ]]);
        $this->assertStringContainsString('not in the group clause', $errors[0]);
    }

    public function test_page_and_limit_must_be_positive_integers(): void
    {
        $this->assertRulePasses($this->rule(), [['name' => 'orders', 'page' => 2, 'limit' => 5]]);
        $this->assertRuleFails($this->rule(), [['name' => 'orders', 'page' => 0]]);
        $this->assertRuleFails($this->rule(), [['name' => 'orders', 'limit' => -1]]);
        $this->assertRuleFails($this->rule(), [['name' => 'orders', 'limit' => 'ten']]);
    }
}
