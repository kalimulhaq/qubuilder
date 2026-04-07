<?php

namespace Kalimulhaq\Qubuilder\Support\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Kalimulhaq\Qubuilder\Support\Filters\Includes;
use Kalimulhaq\Qubuilder\Support\Filters\Select;
use Kalimulhaq\Qubuilder\Support\Filters\Sorts;
use Kalimulhaq\Qubuilder\Support\Filters\Where;

/**
 * @method static \Kalimulhaq\Qubuilder\Qubuilder make(array $filters = [], mixed $model = null) Create an instance from a filters array and a model class string or existing Builder.
 * @method static \Kalimulhaq\Qubuilder\Qubuilder makeFromArray(array $array, mixed $model = null) Alias for make().
 * @method static \Kalimulhaq\Qubuilder\Qubuilder makeFromRequest(Request|null $req = null, mixed $model = null) Parse filters from the HTTP request and create an instance.
 * @method static \Kalimulhaq\Qubuilder\Qubuilder filters(array $filters = []) Set the filters array (also resolves page and limit).
 * @method static \Kalimulhaq\Qubuilder\Qubuilder model(mixed $model) Set the model class string or an existing Builder / Relation instance.
 * @method static Builder query() Apply all filters and return the Eloquent Builder instance.
 * @method static Select select() Get the resolved Select filter object.
 * @method static Where where() Get the resolved Where filter object.
 * @method static Includes include() Get the resolved Includes filter object.
 * @method static Sorts sort() Get the resolved Sorts filter object.
 * @method static int page() Get the resolved page number.
 * @method static int limit() Get the resolved per-page limit.
 *
 * @see \Kalimulhaq\Qubuilder\Qubuilder
 */
class Qubuilder extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'qubuilder';
    }
}
