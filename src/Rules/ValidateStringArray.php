<?php

namespace Kalimulhaq\Qubuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kalimulhaq\Qubuilder\Rules\Concerns\DecodesJsonInput;

/**
 * Validates that the value is an indexed array of non-empty strings.
 *
 * Used for the `select` and `group` request parameters.
 * Accepts either a JSON-encoded string or a pre-decoded PHP array.
 *
 * Valid input examples:
 * - JSON string:  `["id","name","email"]`
 * - Decoded array: `['id', 'name', 'email']`
 * - Empty array:  `[]` (accepted — means "no restriction")
 *
 * Invalid input examples:
 * - `["id", 123]`           — non-string element
 * - `{"0":"id","1":"name"}` — associative (non-indexed) object
 */
class ValidateStringArray implements ValidationRule
{
    use DecodesJsonInput;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = $this->decode($value);

        if ($decoded === null) {
            $fail('The :attribute must be a valid JSON array.');

            return;
        }

        foreach (static::validateItems($decoded, ':attribute') as $error) {
            $fail($error);
        }
    }

    /**
     * Validate an already-decoded array as an indexed list of non-empty strings.
     *
     * Returns an array of error message strings (empty when valid).
     */
    public static function validateItems(array $items, string $path): array
    {
        $errors = [];

        if (! array_is_list($items)) {
            return ["The {$path} must be an indexed array of strings."];
        }

        foreach ($items as $i => $item) {
            if (! is_string($item) || $item === '') {
                $errors[] = "The {$path}[{$i}] must be a non-empty string.";
            }
        }

        return $errors;
    }
}
