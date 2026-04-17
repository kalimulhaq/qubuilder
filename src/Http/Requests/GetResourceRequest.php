<?php

namespace Kalimulhaq\Qubuilder\Http\Requests;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $select  = Helper::param('select');
        $include = Helper::param('include');

        return [
            /**
             * @queryParam select string
             *   Indexed JSON array of column names to include in the response.
             *   Omit to return all columns.
             *   Example: ["id","name","email"]
             */
            $select  => ['sometimes', new ValidateStringArray],

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
             *   Example: [{"name":"profile"},{"name":"roles","select":["id","name"]}]
             */
            $include => ['sometimes', new ValidateInclude],
        ];
    }

    /**
     * Parse and store only `select` and `include` filters after validation.
     */
    protected function passedValidation(): void
    {
        $this->filters = Arr::only(Helper::input($this), ['select', 'include']);
    }
}
