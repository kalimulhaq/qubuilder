<?php

namespace Kalimulhaq\Qubuilder\Tests\Unit\Rules;

use Kalimulhaq\Qubuilder\Rules\ValidateJson;
use Kalimulhaq\Qubuilder\Tests\Concerns\InvokesRules;
use PHPUnit\Framework\TestCase;

class ValidateJsonTest extends TestCase
{
    use InvokesRules;

    private function rule(): ValidateJson
    {
        return new ValidateJson;
    }

    public function test_accepts_valid_json_string(): void
    {
        $this->assertRulePasses($this->rule(), '{"a":1}');
        $this->assertRulePasses($this->rule(), '[1,2,3]');
    }

    public function test_accepts_array(): void
    {
        $this->assertRulePasses($this->rule(), ['a' => 1]);
    }

    public function test_rejects_invalid_json_string(): void
    {
        $errors = $this->assertRuleFails($this->rule(), '{bad json}');
        $this->assertStringContainsString('valid JSON', $errors[0]);
    }

    public function test_rejects_non_string_non_array(): void
    {
        $errors = $this->assertRuleFails($this->rule(), 42);
        $this->assertStringContainsString('JSON object/array', $errors[0]);
    }
}
