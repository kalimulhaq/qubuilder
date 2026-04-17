<?php

namespace Kalimulhaq\Qubuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kalimulhaq\Qubuilder\Rules\Concerns\DecodesJsonInput;

/**
 * Validates that the value is a JSON object of column => direction pairs.
 *
 * Used for the `sort` request parameter.
 * Accepts either a JSON-encoded string or a pre-decoded PHP array.
 *
 * Rules:
 * - Must be a JSON **object** (associative), not an indexed array.
 * - Keys are column names; prefix with `raw:` to pass a raw SQL expression.
 * - Values must be `"asc"` or `"desc"` (case-insensitive).
 * - Empty object `{}` is accepted (no sorting applied).
 *
 * Valid input examples:
 * - `{"created_at":"desc","name":"asc"}`
 * - `{"raw:FIELD(status,'active','inactive')":"asc"}`
 *
 * Invalid input examples:
 * - `{"created_at":"sideways"}` — unrecognised direction
 * - `["created_at","desc"]`     — indexed array instead of object
 */
class ValidateSort implements ValidationRule
{
    use DecodesJsonInput;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = $this->decode($value);

        if ($decoded === null) {
            $fail('The :attribute must be a valid JSON object of column => direction pairs.');

            return;
        }

        foreach (static::validateEntries($decoded, ':attribute') as $error) {
            $fail($error);
        }
    }

    /**
     * Validate an already-decoded array as an associative column => direction map.
     *
     * Returns an array of error message strings (empty when valid).
     */
    public static function validateEntries(array $sort, string $path): array
    {
        $errors = [];

        if (! empty($sort) && array_is_list($sort)) {
            return ["The {$path} must be a JSON object (column => direction pairs), not an indexed array."];
        }

        foreach ($sort as $column => $direction) {
            if (! is_string($column) || $column === '') {
                $errors[] = "The {$path} sort key must be a non-empty string column name.";
                continue;
            }

            if (! in_array(strtolower((string) $direction), ['asc', 'desc'], true)) {
                $errors[] = "The {$path} direction for '{$column}' must be 'asc' or 'desc'.";
            }
        }

        return $errors;
    }
}
