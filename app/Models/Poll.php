<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poll extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'question',
        'description',
        'options',
        'closes_at',
    ];

    protected $casts = [
        'options' => 'array',
        'closes_at' => 'datetime',
    ];

    /**
     * Get the votes for the poll.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
