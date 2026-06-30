<?php

namespace Kalimulhaq\Qubuilder\Tests\Unit\Rules;

use Kalimulhaq\Qubuilder\Rules\ValidateSort;
use Kalimulhaq\Qubuilder\Tests\Concerns\InvokesRules;
use PHPUnit\Framework\TestCase;

class ValidateSortTest extends TestCase
{
    use InvokesRules;

    private function rule(): ValidateSort
    {
        return new ValidateSort;
    }

    public function test_accepts_column_direction_pairs(): void
    {
        $this->assertRulePasses($this->rule(), ['created_at' => 'desc', 'name' => 'asc']);
    }

    public function test_accepts_json_string(): void
    {
        $this->assertRulePasses($this->rule(), '{"created_at":"desc"}');
    }

    public function test_direction_is_case_insensitive(): void
    {
        $this->assertRulePasses($this->rule(), ['name' => 'ASC', 'created_at' => 'Desc']);
    }

    public function test_accepts_empty_object(): void
    {
        $this->assertRulePasses($this->rule(), []);
    }

    public function test_accepts_raw_prefixed_key(): void
    {
        $this->assertRulePasses($this->rule(), ["raw:FIELD(status,'active')" => 'asc']);
    }

    public function test_rejects_indexed_array(): void
    {
        $errors = $this->assertRuleFails($this->rule(), ['created_at', 'desc']);
        $this->assertStringContainsString('not an indexed array', $errors[0]);
    }

    public function test_rejects_invalid_direction(): void
    {
        $errors = $this->assertRuleFails($this->rule(), ['created_at' => 'sideways']);
        $this->assertStringContainsString("must be 'asc' or 'desc'", $errors[0]);
    }

    public function test_rejects_invalid_json(): void
    {
        $this->assertRuleFails($this->rule(), 'not json');
    }

    public function test_rejects_integer_key_in_mixed_object(): void
    {
        // Mixed keys -> not a list, so it loops; the integer key trips the string check.
        $errors = $this->assertRuleFails($this->rule(), [0 => 'asc', 'name' => 'desc']);
        $this->assertStringContainsString('non-empty string column name', $errors[0]);
    }
}
