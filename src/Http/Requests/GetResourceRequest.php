<?php

namespace Kalimulhaq\Qubuilder\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Kalimulhaq\Qubuilder\Rules\ValidateInclude;
use Kalimulhaq\Qubuilder\Rules\ValidateStringArray;
use Kalimulhaq\Qubuilder\Support\Helper;

/**
 * Form request for single-resource endpoints.
 *
 * A specialised subset of {@see GetCollectionRequest} that accepts only
 * `select` and `include` — pagination, filtering, and sorting are not
 * applicable to single-record responses.
 *
 * Parameter names are configurable via `qubuilder.params`; the names below reflect
 * the defaults.
 */
class GetResourceRequest extends GetCollectionRequest
{
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
     * Only `select` and `include` are validated for single-resource endpoints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $select = Helper::param('select');
        $include = Helper::param('include');

        $selectRules = Helper::allowSelectAll()
            ? ['sometimes', new ValidateStringArray]
            : ['required', new ValidateStringArray(forbidWildcard: true)];

        $rules = [
            /**
             * @queryParam select string
             *   Indexed JSON array of column names to include in the response.
             *   Omit to return all columns. When `qubuilder.allow_select_all` is
             *   disabled, this parameter is required and "*" is rejected.
             *   Example: ["id","name","email"]
             */
            $select => $selectRules,
        ];

        /**
         * @queryParam include string
         *   JSON array of eager-load definitions. Each object must have a `name` key
         *   (the Eloquent relation method name) and may include:
         *   - select    — column subset (array of strings)
         *   - group     — GROUP BY columns (array of strings)
         *   - filter    — sub-filter applied to the relation
         *   - sort      — sort the relation results
         *   - aggregate — one of: count avg sum min max
         *   - field     — required when aggregate is avg, sum, min, or max
         *   - page / limit — paginate the relation
         *   - include   — nested includes (recursive)
         *
         *   Omitted entirely when `qubuilder.allow_include` is disabled.
         *   Example: [{"name":"profile"},{"name":"roles","select":["id","name"]}]
         */
        if (Helper::allowInclude()) {
            $rules[$include] = ['sometimes', new ValidateInclude];
        }

        return $rules;
    }

    /**
     * Parse and store only `select` and `include` filters after validation.
     */
    protected function passedValidation(): void
    {
        $this->filters = Arr::only(Helper::input($this), ['select', 'include']);
    }
}
