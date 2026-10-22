<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * A MorphTo with no {relation}Map() method.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
