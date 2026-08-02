<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargingStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'available_ports',
        'operator',
        'rating',
        'details',
    ];

    public function favorites(): HasMany
    {
        return $this->hasMany(ChargingStationFavorite::class);
    }
}
