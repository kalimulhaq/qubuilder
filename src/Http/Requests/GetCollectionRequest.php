<?php

namespace Kalimulhaq\Qubuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Kalimulhaq\Qubuilder\Rules\ValidateJson;
use Kalimulhaq\Qubuilder\Support\Helper;

/**
 * Form request for paginated collection endpoints.
 *
 * Validates and parses all Qubuilder query parameters from the HTTP request.
 * All parameters are optional. JSON parameters accept either a JSON-encoded
 * string or a pre-decoded array.
 *
 * ## Request Parameters
 *
 * @property-read string|array $select
 *   JSON array of column names to return.
 *   Example: `["id","name","email"]`
 *
 * @property-read string|array $filter
 *   JSON filter object. Conditions must be wrapped in `AND`/`OR` groups or
 *   passed as a flat array of condition objects.
 *   Example: `{"AND":[{"field":"status","op":"=","value":"active"}]}`
 *
 * @property-read string|array $include
 *   JSON array of relationship definitions to eager-load.
 *   Each entry requires at minimum a `name` key (the relation method name).
 *   Example: `[{"name":"orders","aggregate":"count"}]`
 *
 * @property-read string|array $sort
 *   JSON object of `column => direction` pairs. Use `raw:` prefix for raw expressions.
 *   Example: `{"created_at":"desc","name":"asc"}`
 *
 * @property-read string|array $group
 *   JSON array of column names to group results by.
 *   Example: `["status","type"]`
 *
 * @property-read int $page
 *   Page number for pagination. Must be a positive integer. Default: `1`.
 *
 * @property-read int $limit
 *   Number of records per page. Must be between `1` and the configured max (default `50`).
 *   Default: `15`.
 */
class GetCollectionRequest extends FormRequest
{
    /**
     * The parsed filters array, populated after successful validation.
     *
     * @var array{select: array, filter: array, include: array, sort: array, group: array, page: int, limit: int}
     */
    protected array $filters = [];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * All parameters are optional (`sometimes`). JSON parameters are validated
     * via {@see ValidateJson}. The `limit` is capped at the value configured
     * in `qubuilder.limit.max`.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $select  = Helper::param('select');
        $filter  = Helper::param('filter');
        $include = Helper::param('include');
        $sort    = Helper::param('sort');
        $group   = Helper::param('group');
        $page    = Helper::param('page');
        $limit   = Helper::param('limit');

        return [
            // JSON array of column names — e.g. ["id","name","email"]
            $select  => ['sometimes', new ValidateJson],

            // JSON filter structure — conditions wrapped in AND/OR groups
            $filter  => ['sometimes', new ValidateJson],

            // JSON array of relationship definitions to eager-load
            $include => ['sometimes', new ValidateJson],

            // JSON object of column => direction sort pairs
            $sort    => ['sometimes', new ValidateJson],

            // JSON array of column names to group results by
            $group   => ['sometimes', new ValidateJson],

            // Positive integer page number
            $page    => ['sometimes', 'integer', 'min:1'],

            // Records per page, capped at qubuilder.limit.max
            $limit   => ['sometimes', 'integer', 'between:1,'.Helper::maxLimit()],
        ];
    }

    /**
     * Parse and store all filter parameters after the request passes validation.
     */
    protected function passedValidation(): void
    {
        $this->filters = Helper::input($this);
    }

    /**
     * Get the parsed filters array after validation.
     *
     * Returns a normalised array ready to pass directly to `Qubuilder::make()`.
     *
     * @return array{select: array, filter: array, include: array, sort: array, group: array, page: int, limit: int}
     */
    public function filters(): array
    {
        return $this->filters;
    }
}
