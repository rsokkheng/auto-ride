<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricDevice extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'device_name', 'platform',
        'public_key', 'challenge', 'challenge_expires_at',
        'last_used_at', 'active',
    ];

    protected $casts = [
        'active'               => 'boolean',
        'challenge_expires_at' => 'datetime',
        'last_used_at'         => 'datetime',
    ];

    protected $hidden = ['public_key', 'challenge'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
