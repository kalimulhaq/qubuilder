<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A morph owner with no `{relation}Map()`: its types come from the global morph map.
 */
class Bookmark extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }
}
