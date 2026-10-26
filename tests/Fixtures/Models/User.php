<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'verified_at' => 'datetime',
        'published_at' => 'datetime',
        'age' => 'integer',
        'score' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * A joined relation: the `role_user` pivot has its own `id` and timestamps, so a
     * bare `id` or `created_at` in the relation query is ambiguous.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('level')->withTimestamps();
    }

    /**
     * A through relation: the query joins `orders`, whose `id` clashes with `items.id`.
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(Item::class, Order::class, 'user_id', 'order_id');
    }
}
