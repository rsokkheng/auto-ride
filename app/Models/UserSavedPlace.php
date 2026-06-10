<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSavedPlace extends Model
{
    protected $fillable = [
        'user_id', 'label', 'address', 'lat', 'lng', 'icon', 'is_default',
    ];

    protected $casts = [
        'lat'        => 'float',
        'lng'        => 'float',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
