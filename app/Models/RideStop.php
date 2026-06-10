<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideStop extends Model
{
    protected $fillable = [
        'ride_id', 'address', 'lat', 'lng', 'sort_order', 'arrived_at',
    ];

    protected $casts = [
        'lat'        => 'float',
        'lng'        => 'float',
        'sort_order' => 'integer',
        'arrived_at' => 'datetime',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
