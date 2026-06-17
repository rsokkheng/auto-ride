<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirportZone extends Model
{
    protected $fillable = [
        'name', 'iata_code', 'latitude', 'longitude',
        'radius_meters', 'surcharge_khr', 'luggage_fee_khr', 'active',
    ];

    protected $casts = [
        'latitude'        => 'float',
        'longitude'       => 'float',
        'surcharge_khr'   => 'integer',
        'luggage_fee_khr' => 'integer',
        'active'          => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function detectFromCoords(float $lat, float $lng): ?self
    {
        return self::active()->get()->first(function (AirportZone $zone) use ($lat, $lng) {
            return $this->haversineMeters($lat, $lng, $zone->latitude, $zone->longitude) <= $zone->radius_meters;
        });
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlambda = deg2rad($lng2 - $lng1);
        $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlambda / 2) ** 2;

        return 2 * $R * asin(sqrt($a));
    }
}
