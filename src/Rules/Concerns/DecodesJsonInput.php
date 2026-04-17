<?php

namespace Kalimulhaq\Qubuilder\Rules\Concerns;

/**
 * Shared JSON-decode helper used by all Qubuilder validation rules.
 *
 * Allows rule inputs to arrive either as a JSON-encoded string (normal HTTP
 * request) or as a pre-decoded PHP array (programmatic use / tests).
 */
trait DecodesJsonInput
{
    /**
     * Decode a JSON string or pass through a pre-decoded array.
     *
     * Returns `null` when the value is neither a valid JSON string nor an array,
     * which the calling rule should treat as a validation failure.
     */
    protected function decode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
