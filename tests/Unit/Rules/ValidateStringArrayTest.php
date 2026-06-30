<?php

namespace Kalimulhaq\Qubuilder\Tests\Unit\Rules;

use Kalimulhaq\Qubuilder\Rules\ValidateStringArray;
use Kalimulhaq\Qubuilder\Tests\Concerns\InvokesRules;
use PHPUnit\Framework\TestCase;

class ValidateStringArrayTest extends TestCase
{
    use InvokesRules;

    private function rule(): ValidateStringArray
    {
        return new ValidateStringArray;
    }

    public function test_accepts_indexed_string_array(): void
    {
        $this->assertRulePasses($this->rule(), ['id', 'name', 'email']);
    }

    public function test_accepts_json_string(): void
    {
        $this->assertRulePasses($this->rule(), '["id","name"]');
    }

    public function test_accepts_empty_array(): void
    {
        $this->assertRulePasses($this->rule(), []);
    }

    public function test_rejects_non_string_element(): void
    {
        $errors = $this->assertRuleFails($this->rule(), ['id', 123]);
        $this->assertStringContainsString('must be a non-empty string', $errors[0]);
    }

    public function test_rejects_empty_string_element(): void
    {
        $this->assertRuleFails($this->rule(), ['id', '']);
    }

    public function test_rejects_associative_array(): void
    {
        $errors = $this->assertRuleFails($this->rule(), ['0' => 'id', 'name' => 'x']);
        $this->assertStringContainsString('indexed array of strings', $errors[0]);
    }

    public function test_rejects_invalid_json(): void
    {
        $this->assertRuleFails($this->rule(), 'not json');
    }
}
