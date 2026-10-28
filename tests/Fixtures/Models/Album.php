<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * `topics` on an album reaches `album_topics`, which has a `label` but no `subject_id`.
     */
    public function topics(): HasMany
    {
        return $this->hasMany(AlbumTopic::class);
    }
}
