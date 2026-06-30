<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Video extends Model
{
    protected $guarded = [];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'channel_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
