<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * `topics` on a course is a different relation under the same name: `topics` through the
     * `course_topic` pivot, and neither table has a `subject_id` column.
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'course_topic')->withPivot('level');
    }
}
