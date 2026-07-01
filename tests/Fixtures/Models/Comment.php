<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Selective sub-includes per morph type, consumed by Includes via morphWith().
     * Keys are morph aliases (resolved to class names when a morph map is registered).
     */
    public function commentableMap(): array
    {
        return [
            'post' => ['author'],
            'video' => ['channel'],
        ];
    }
}
