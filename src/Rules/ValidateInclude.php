<?php

namespace Kalimulhaq\Qubuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kalimulhaq\Qubuilder\Rules\Concerns\DecodesJsonInput;

/**
 * Validates that the value is a well-formed Qubuilder `include` array.
 *
 * Used for the `include` request parameter.
 * Accepts either a JSON-encoded string or a pre-decoded PHP array.
 *
 * Must be an **indexed array** of include-item objects. Each item may contain:
 *
 * | Key         | Required | Type                                   | Notes                                      |
 * |-------------|----------|----------------------------------------|--------------------------------------------|
 * | `name`      | Yes      | Non-empty string                       | Eloquent relation method name              |
 * | `aggregate` | No       | `count` `avg` `sum` `min` `max`        | Aggregation function to apply              |
 * | `field`     | Cond.    | Non-empty string                       | Required when `aggregate` is avg/sum/min/max |
 * | `select`    | No       | Indexed string array                   | Columns to return from the relation        |
 * | `filter`    | No       | Filter object (see {@see ValidateFilter}) | Sub-filter applied to the relation      |
 * | `sort`      | No       | `{column: direction}` object           | Sort the relation results                  |
 * | `page`      | No       | Integer ≥ 1                            | Paginate the relation                      |
 * | `limit`     | No       | Integer ≥ 1                            | Limit relation results                     |
 * | `include`   | No       | Array of include-item objects          | Nested eager-loads (recursive)             |
 *
 * Nested `filter`, `sort`, `select`, and `include` are recursively validated
 * against their respective rules.
 *
 * Valid input example:
 * ```json
 * [
 *   {"name": "roles", "select": ["id", "name"]},
 *   {"name": "orders", "aggregate": "count"},
 *   {"name": "latestOrder", "include": [{"name": "items"}]}
 * ]
 * ```
 *
 * Invalid input examples:
 * - `[{"aggregate":"count"}]`         — missing required `name`
 * - `[{"name":"orders","aggregate":"avg"}]` — `field` required for avg
 */
class ValidateInclude implements ValidationRule
{
    use DecodesJsonInput;

    private const VALID_AGGREGATES = ['count', 'avg', 'sum', 'min', 'max'];

    private const FIELD_REQUIRED_AGGREGATES = ['avg', 'sum', 'min', 'max'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = $this->decode($value);

        if ($decoded === null) {
            $fail('The :attribute must be a valid JSON array of include objects.');

            return;
        }

        foreach (static::validateItems($decoded, ':attribute') as $error) {
            $fail($error);
        }
    }

    /**
     * Validate an already-decoded array as a list of include item objects.
     *
     * @return string[]  Error messages; empty when the list is valid.
     */
    public static function validateItems(array $items, string $path): array
    {
        $errors = [];

        if (! array_is_list($items)) {
            return ["The {$path} must be an indexed array of include objects."];
        }

        foreach ($items as $i => $item) {
            $itemPath = "{$path}[{$i}]";

            if (! is_array($item)) {
                $errors[] = "The {$itemPath} must be an object.";
                continue;
            }

            $errors = array_merge($errors, static::validateItem($item, $itemPath));
        }

        return $errors;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function validateItem(array $item, string $path): array
    {
        $errors = [];

        // name: required, non-empty string
        if (! isset($item['name']) || ! is_string($item['name']) || $item['name'] === '') {
            $errors[] = "The {$path} is missing required key 'name' (must be a non-empty string).";
        }

        // aggregate: optional enum
        $aggregate = $item['aggregate'] ?? null;

        if ($aggregate !== null) {
            if (! in_array($aggregate, self::VALID_AGGREGATES, true)) {
                $errors[] = "The {$path}.aggregate must be one of: "
                    . implode(', ', self::VALID_AGGREGATES) . '.';
            } elseif (in_array($aggregate, self::FIELD_REQUIRED_AGGREGATES, true)) {
                // field is required for avg / sum / min / max
                if (! isset($item['field']) || ! is_string($item['field']) || $item['field'] === '') {
                    $errors[] = "The {$path}.field is required and must be a non-empty string when aggregate is '{$aggregate}'.";
                }
            }
        }

        // select: optional indexed array of strings
        if (array_key_exists('select', $item)) {
            if (! is_array($item['select'])) {
                $errors[] = "The {$path}.select must be an array of strings.";
            } else {
                $errors = array_merge($errors, ValidateStringArray::validateItems($item['select'], "{$path}.select"));
            }
        }

        // filter: optional, validated as a filter node
        if (array_key_exists('filter', $item)) {
            if (! is_array($item['filter'])) {
                $errors[] = "The {$path}.filter must be a valid filter object or array.";
            } else {
                $errors = array_merge($errors, ValidateFilter::validateNode($item['filter'], "{$path}.filter"));
            }
        }

        // sort: optional, validated as column => direction pairs
        if (array_key_exists('sort', $item)) {
            if (! is_array($item['sort'])) {
                $errors[] = "The {$path}.sort must be a JSON object of column => direction pairs.";
            } else {
                $errors = array_merge($errors, ValidateSort::validateEntries($item['sort'], "{$path}.sort"));
            }
        }

        // include: optional nested includes (recursive)
        if (array_key_exists('include', $item)) {
            if (! is_array($item['include'])) {
                $errors[] = "The {$path}.include must be an array of include objects.";
            } else {
                $errors = array_merge($errors, static::validateItems($item['include'], "{$path}.include"));
            }
        }

        // page: optional integer >= 1
        if (array_key_exists('page', $item)) {
            if (filter_var($item['page'], FILTER_VALIDATE_INT) === false || (int) $item['page'] < 1) {
                $errors[] = "The {$path}.page must be an integer >= 1.";
            }
        }

        // limit: optional integer >= 1
        if (array_key_exists('limit', $item)) {
            if (filter_var($item['limit'], FILTER_VALIDATE_INT) === false || (int) $item['limit'] < 1) {
                $errors[] = "The {$path}.limit must be an integer >= 1.";
            }
        }

        return $errors;
    }
}
