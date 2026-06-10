<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserEmergencyContact extends Model
{
    protected $fillable = [
        'user_id', 'name', 'phone', 'relationship', 'notify_on_sos', 'notify_on_trip_share',
    ];

    protected $casts = [
        'notify_on_sos'       => 'boolean',
        'notify_on_trip_share' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
