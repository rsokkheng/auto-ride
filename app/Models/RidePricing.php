<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RidePricing extends Model
{
    protected $table = 'ride_pricing';

    protected $fillable = [
        'service_type',
        'label',
        'icon',
        'base',
        'per_km',
        'per_min',
        'booking_fee',
        'minimum',
        'capacity',
        'active',
    ];

    protected $casts = [
        'base'        => 'integer',
        'per_km'      => 'integer',
        'per_min'     => 'integer',
        'booking_fee' => 'integer',
        'minimum'     => 'integer',
        'capacity'    => 'integer',
        'active'      => 'boolean',
    ];

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'label'    => $this->label,
            'capacity' => $this->capacity,
        ]);
    }
}
