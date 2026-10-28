<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleTopic extends Model
{
    /**
     * `subject_id` and `weight` are real columns left out of `$fillable` on purpose, like the
     * system columns many apps never mass-assign. The morph type check must still see them.
     */
    protected $fillable = ['article_id'];

    public $timestamps = false;
}
