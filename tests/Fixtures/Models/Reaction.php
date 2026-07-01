<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Morph map keyed by fully-qualified class name (mirrors the real-world
     * usage where values are produced dynamically, e.g. Helper::getRelationships()).
     */
    public function reactableMap(): array
    {
        return [
            Post::class => ['author'],
            Video::class => ['channel'],
        ];
    }
}
