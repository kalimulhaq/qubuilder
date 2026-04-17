<?php

namespace Kalimulhaq\Qubuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kalimulhaq\Qubuilder\Rules\Concerns\DecodesJsonInput;

/**
 * Validates that the value matches the Qubuilder filter structure.
 *
 * Used for the `filter` request parameter.
 * Accepts either a JSON-encoded string or a pre-decoded PHP array.
 *
 * A filter is a recursive structure with three valid node shapes:
 *
 * **Condition** — an object with a `field` key:
 * ```json
 * {"field": "status", "op": "=", "value": "active"}
 * ```
 * - `field` — required; column name string, or array of strings for `any`/`all`/`none`.
 * - `op`    — optional (defaults to `=`); any of the 25+ known operators, optionally
 *             piped with a sub-operator (e.g. `has|>=`, `field|!=`, `any|_like_`).
 * - `value` — depends on the operator (see operator table below).
 *
 * **AND / OR group** — an object with an `AND` or `OR` key whose value is an array
 * of nested conditions or groups:
 * ```json
 * {"AND": [{"field": "age", "op": ">=", "value": 18}, {"OR": [...]}]}
 * ```
 *
 * **Flat list** — an indexed array treated as an implicit AND:
 * ```json
 * [{"field": "status", "op": "=", "value": "active"}, {"field": "age", "op": ">", "value": 0}]
 * ```
 *
 * **Operator value requirements:**
 *
 * | Operator(s)                        | `value` required | Type                          |
 * |------------------------------------|-----------------|-------------------------------|
 * | `null`, `not_null`                 | No              | —                             |
 * | `in`, `not_in`                     | Yes             | Non-empty array               |
 * | `between`, `not_between`           | Yes             | Array with exactly 2 elements |
 * | `raw`                              | Yes             | Array (SQL bindings)          |
 * | `has`, `has\|op`                   | Optional        | Scalar count or filter object |
 * | `doesnthave`                       | Optional        | Filter object (AND/OR group)  |
 * | `field\|op`                        | Yes             | Non-empty string (column name)|
 * | `any\|op`, `all\|op`, `none\|op`   | Yes             | Scalar                        |
 * | All other operators                | Yes             | Scalar                        |
 *
 * Known primary operators:
 * `=` `!=` `<>` `>` `<` `>=` `<=`
 * `in` `not_in` `between` `not_between`
 * `null` `not_null`
 * `_like` `like_` `_like_`
 * `date` `year` `month` `day` `time`
 * `json_contains` `json_not_contains`
 * `raw` `field` `has` `doesnthave` `any` `all` `none`
 */
class ValidateFilter implements ValidationRule
{
    use DecodesJsonInput;

    /** Operators that accept a scalar comparison sub-operator via `|` */
    private const COMPARISON_OPS = ['=', '!=', '<>', '>', '<', '>=', '<='];

    /** Every valid primary operator recognised by WhereClause */
    private const KNOWN_OPS = [
        '=', '!=', '<>', '>', '<', '>=', '<=',
        'in', 'not_in',
        'between', 'not_between',
        'null', 'not_null',
        '_like', 'like_', '_like_',
        'date', 'year', 'month', 'day', 'time',
        'json_contains', 'json_not_contains',
        'raw', 'field',
        'has', 'doesnthave',
        'any', 'all', 'none',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = $this->decode($value);

        if ($decoded === null) {
            $fail('The :attribute must be a valid JSON array or object.');

            return;
        }

        foreach (static::validateNode($decoded, ':attribute') as $error) {
            $fail($error);
        }
    }

    /**
     * Recursively validate a filter node (condition, group, or list).
     *
     * @return string[]  Error messages; empty when the node is valid.
     */
    public static function validateNode(array $node, string $path): array
    {
        // Bare condition: has a `field` key at the top level
        if (array_key_exists('field', $node)) {
            return static::validateCondition($node, $path);
        }

        // Flat indexed list of conditions/groups
        if (array_is_list($node)) {
            $errors = [];
            foreach ($node as $i => $item) {
                if (! is_array($item)) {
                    $errors[] = "The {$path}[{$i}] must be a condition object or a group (AND/OR).";
                    continue;
                }
                $errors = array_merge($errors, static::validateNode($item, "{$path}[{$i}]"));
            }

            return $errors;
        }

        // AND / OR group(s)
        $errors   = [];
        $hasGroup = false;

        foreach (['AND', 'OR'] as $conjunction) {
            if (! array_key_exists($conjunction, $node)) {
                continue;
            }

            $hasGroup = true;
            $children = $node[$conjunction];

            if (! is_array($children)) {
                $errors[] = "The {$path}.{$conjunction} must be an array of conditions or groups.";
                continue;
            }

            foreach ($children as $i => $child) {
                if (! is_array($child)) {
                    $errors[] = "The {$path}.{$conjunction}[{$i}] must be a condition object or a group.";
                    continue;
                }
                $errors = array_merge($errors, static::validateNode($child, "{$path}.{$conjunction}[{$i}]"));
            }
        }

        if (! $hasGroup) {
            $errors[] = "The {$path} must be a condition object (with 'field' key), "
                . "a group (with 'AND' or 'OR' key), or an indexed array of conditions/groups.";
        }

        return $errors;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function validateCondition(array $condition, string $path): array
    {
        $errors = [];

        // Parse and validate `op`
        $rawOp     = isset($condition['op']) ? strtolower(trim((string) $condition['op'])) : '=';
        $parts     = explode('|', $rawOp, 2);
        $primaryOp = $parts[0];
        $subOp     = $parts[1] ?? null;

        if (! in_array($primaryOp, self::KNOWN_OPS, true)) {
            $errors[] = "The {$path}.op '{$primaryOp}' is not a recognised operator.";

            return $errors; // can't validate value or field further without a known op
        }

        if ($subOp !== null) {
            $errors = array_merge($errors, static::validateSubOp($primaryOp, $subOp, $path));
        }

        // Validate `field`
        $field = $condition['field'] ?? null;
        $isMultiColumn = in_array($primaryOp, ['any', 'all', 'none'], true);

        if ($isMultiColumn) {
            if (! is_array($field) || empty($field) || ! array_is_list($field)) {
                $errors[] = "The {$path}.field must be a non-empty indexed array of strings for operator '{$primaryOp}'.";
            } elseif (array_filter($field, fn ($f) => ! is_string($f) || $f === '')) {
                $errors[] = "The {$path}.field must contain only non-empty strings for operator '{$primaryOp}'.";
            }
        } else {
            if (! is_string($field) || $field === '') {
                $errors[] = "The {$path}.field must be a non-empty string.";
            }
        }

        // Validate `value`
        $hasValue = array_key_exists('value', $condition);
        $value    = $condition['value'] ?? null;

        $errors = array_merge($errors, static::validateValue($primaryOp, $hasValue, $value, $path));

        return $errors;
    }

    private static function validateSubOp(string $primaryOp, string $subOp, string $path): array
    {
        $errors = [];

        if (in_array($primaryOp, ['has', 'field'], true)) {
            if (! in_array($subOp, self::COMPARISON_OPS, true)) {
                $errors[] = "The {$path}.op sub-operator '{$subOp}' for '{$primaryOp}' must be one of: "
                    . implode(', ', self::COMPARISON_OPS) . '.';
            }
        } elseif (in_array($primaryOp, ['any', 'all', 'none'], true)) {
            $subPrimary = explode('|', $subOp, 2)[0];
            if (! in_array($subPrimary, self::KNOWN_OPS, true)) {
                $errors[] = "The {$path}.op sub-operator '{$subOp}' for '{$primaryOp}' is not a recognised operator.";
            }
        } else {
            $errors[] = "The {$path}.op '{$primaryOp}' does not support a sub-operator ('{$subOp}' given).";
        }

        return $errors;
    }

    private static function validateValue(string $primaryOp, bool $hasValue, mixed $value, string $path): array
    {
        $errors = [];

        switch ($primaryOp) {
            case 'null':
            case 'not_null':
                break;

            case 'doesnthave':
                if ($hasValue && $value !== null) {
                    if (! is_array($value)) {
                        $errors[] = "The {$path}.value for 'doesnthave' must be a filter object (AND/OR group) when provided.";
                    } else {
                        $errors = array_merge($errors, static::validateNode($value, "{$path}.value"));
                    }
                }
                break;

            case 'in':
            case 'not_in':
                if (! $hasValue || ! is_array($value) || empty($value)) {
                    $errors[] = "The {$path}.value for '{$primaryOp}' must be a non-empty array.";
                }
                break;

            case 'between':
            case 'not_between':
                if (! $hasValue || ! is_array($value)) {
                    $errors[] = "The {$path}.value for '{$primaryOp}' must be an array.";
                } elseif (count($value) !== 2) {
                    $errors[] = "The {$path}.value for '{$primaryOp}' must contain exactly 2 elements [min, max].";
                }
                break;

            case 'raw':
                if (! $hasValue || ! is_array($value)) {
                    $errors[] = "The {$path}.value for 'raw' must be an array of SQL bindings.";
                }
                break;

            case 'has':
                if ($hasValue && $value !== null) {
                    if (is_array($value)) {
                        $errors = array_merge($errors, static::validateNode($value, "{$path}.value"));
                    } elseif (! is_scalar($value)) {
                        $errors[] = "The {$path}.value for 'has' must be a scalar count or a filter object.";
                    }
                }
                break;

            case 'field':
                if (! $hasValue || ! is_string($value) || $value === '') {
                    $errors[] = "The {$path}.value for 'field' must be a non-empty string (target column name).";
                }
                break;

            case 'any':
            case 'all':
            case 'none':
                if (! $hasValue) {
                    $errors[] = "The {$path}.value for '{$primaryOp}' is required.";
                } elseif (is_array($value)) {
                    $errors[] = "The {$path}.value for '{$primaryOp}' must be a scalar.";
                }
                break;

            default:
                // All remaining ops require a scalar value
                if (! $hasValue) {
                    $errors[] = "The {$path}.value for '{$primaryOp}' is required.";
                } elseif (is_array($value)) {
                    $errors[] = "The {$path}.value for '{$primaryOp}' must be a scalar, not an array.";
                }
                break;
        }

        return $errors;
    }
}
