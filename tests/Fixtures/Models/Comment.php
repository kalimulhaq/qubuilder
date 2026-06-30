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
     *
     * NOTE: the README documents this as `private`, but Includes invokes it
     * externally ($model->commentableMap()), so it must be public to work.
     *
     * NOTE: the README documents the keys as morph aliases ('post', 'video'), but
     * MorphTo resolves morphable eager-loads by get_class() (the FQCN), so the keys
     * must be fully-qualified class names for the nested relations to actually load.
     */
    public function commentableMap(): array
    {
        return [
            Post::class  => ['author'],
            Video::class => ['channel'],
        ];
    }
}
