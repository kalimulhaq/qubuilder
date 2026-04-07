<?php

namespace Kalimulhaq\Qubuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that the given value is either a valid JSON string or an array.
 *
 * Rejects plain strings that are not valid JSON, and rejects any value
 * that is neither a string nor an array.
 */
class ValidateJson implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            $output = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('The :attribute field must be valid JSON object/array.');
            }

            return;
        }

        if (! is_array($value)) {
            $fail('The :attribute must be a JSON object/array.');
        }
    }
}
