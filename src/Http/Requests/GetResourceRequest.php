<?php

namespace Kalimulhaq\Qubuilder\Http\Requests;

use Illuminate\Support\Arr;
use Kalimulhaq\Qubuilder\Support\Helper;

/**
 * Form request for single-resource endpoints.
 *
 * Extends {@see GetCollectionRequest} but restricts validation to only the
 * `select` and `include` parameters — pagination, filtering, and sorting are
 * not applicable to single-record responses.
 *
 * ## Request Parameters
 *
 * @property-read string|array $select
 *   JSON array of column names to return.
 *   Example: `["id","name","email"]`
 *
 * @property-read string|array $include
 *   JSON array of relationship definitions to eager-load.
 *   Each entry requires at minimum a `name` key (the relation method name).
 *   Example: `[{"name":"profile"},{"name":"roles","select":["id","name"]}]`
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
            // JSON array of column names — e.g. ["id","name","email"]
            $select  => ['sometimes', 'json'],

            // JSON array of relationship definitions to eager-load
            $include => ['sometimes', 'json'],
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
