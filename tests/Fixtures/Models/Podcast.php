<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Podcast extends Model
{
    protected $guarded = [];

    /**
     * Same relation name as Post::author(), but it points at a table without a `name` column.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'author_id');
    }
}
