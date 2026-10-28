<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * `topics` on an article reaches `article_topics`, which has a `subject_id` column.
     */
    public function topics(): HasMany
    {
        return $this->hasMany(ArticleTopic::class);
    }
}
