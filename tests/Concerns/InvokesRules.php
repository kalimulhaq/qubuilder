<?php

namespace Kalimulhaq\Qubuilder\Tests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Helpers for exercising a ValidationRule directly and collecting the
 * failure messages it produces (with the `:attribute` placeholder resolved).
 */
trait InvokesRules
{
    /**
     * Run a rule and return the list of failure messages (empty when it passes).
     *
     * @return string[]
     */
    protected function ruleErrors(ValidationRule $rule, mixed $value, string $attribute = 'field'): array
    {
        $errors = [];

        $rule->validate($attribute, $value, function (string $message) use (&$errors, $attribute) {
            $errors[] = str_replace(':attribute', $attribute, $message);
        });

        return $errors;
    }

    protected function assertRulePasses(ValidationRule $rule, mixed $value): void
    {
        $errors = $this->ruleErrors($rule, $value);

        $this->assertSame([], $errors, 'Expected the rule to pass but got: ' . implode(' | ', $errors));
    }

    protected function assertRuleFails(ValidationRule $rule, mixed $value): array
    {
        $errors = $this->ruleErrors($rule, $value);

        $this->assertNotEmpty($errors, 'Expected the rule to fail but it passed.');

        return $errors;
    }
}
