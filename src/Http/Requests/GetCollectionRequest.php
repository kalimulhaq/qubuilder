<?php

namespace Kalimulhaq\Qubuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Kalimulhaq\Qubuilder\Rules\ValidateFilter;
use Kalimulhaq\Qubuilder\Rules\ValidateInclude;
use Kalimulhaq\Qubuilder\Rules\ValidateSort;
use Kalimulhaq\Qubuilder\Rules\ValidateStringArray;
use Kalimulhaq\Qubuilder\Support\Helper;

/**
 * Form request for paginated collection endpoints.
 *
 * Validates and normalises all Qubuilder query parameters from the HTTP request.
 * All parameters are optional. JSON parameters accept either a JSON-encoded string
 * or, when called programmatically (e.g. tests), a pre-decoded PHP array.
 *
 * Parameter names are configurable via `qubuilder.params`; the names below reflect
 * the defaults.
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
     * All parameters are optional (`sometimes`). Each JSON parameter is validated
     * against the exact structure the package expects. The `limit` is capped at
     * the value configured in `qubuilder.limit.max`.
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
            /**
             * @queryParam select string
             *   Indexed JSON array of column names to include in the response.
             *   Omit to return all columns.
             *   Example: ["id","name","email"]
             */
            $select  => ['sometimes', new ValidateStringArray],

            /**
             * @queryParam filter string
             *   JSON filter definition. Supports three node shapes:
             *   - Condition object:  {"field":"name","op":"=","value":"Alice"}
             *   - AND / OR group:    {"AND":[...conditions/groups...]}
             *   - Flat list (AND):   [{"field":"status","op":"=","value":"active"},...]
             *
             *   Operators: = != <> > < >= <= in not_in between not_between null not_null
             *   _like like_ _like_ date year month day time json_contains json_not_contains
             *   raw field has doesnthave any all none
             *   Piped sub-operators: has|>= field|!= any|_like_ etc.
             *
             *   Example: {"AND":[{"field":"status","op":"=","value":"active"},{"field":"age","op":">=","value":18}]}
             */
            $filter  => ['sometimes', new ValidateFilter],

            /**
             * @queryParam include string
             *   JSON array of eager-load definitions. Each object must have a `name` key
             *   (the Eloquent relation method name) and may include:
             *   - select    — column subset (array of strings)
             *   - filter    — sub-filter applied to the relation
             *   - sort      — sort the relation results
             *   - aggregate — one of: count avg sum min max
             *   - field     — required when aggregate is avg, sum, min, or max
             *   - page / limit — paginate the relation
             *   - include   — nested includes (recursive)
             *
             *   Example: [{"name":"roles","select":["id","name"]},{"name":"orders","aggregate":"count"}]
             */
            $include => ['sometimes', new ValidateInclude],

            /**
             * @queryParam sort string
             *   JSON object of column => direction pairs. Direction must be asc or desc
             *   (case-insensitive). Prefix a key with raw: for raw SQL expressions.
             *   Example: {"created_at":"desc","name":"asc"}
             */
            $sort    => ['sometimes', new ValidateSort],

            /**
             * @queryParam group string
             *   Indexed JSON array of column names to add to the GROUP BY clause.
             *   Example: ["status","type"]
             */
            $group   => ['sometimes', new ValidateStringArray],

            /**
             * @queryParam page integer
             *   1-based page number for pagination. Defaults to 1.
             *   Example: 1
             */
            $page    => ['sometimes', 'integer', 'min:1'],

            /**
             * @queryParam limit integer
             *   Number of records per page. Must be between 1 and the configured maximum
             *   (default 50, set via qubuilder.limit.max). Defaults to 15.
             *   Example: 15
             */
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
