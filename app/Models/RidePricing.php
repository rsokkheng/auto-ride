<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class RidePricing extends Model
{
    use HasLocalizedName;

    protected $table = 'ride_pricing';

    protected $fillable = [
        'service_type',
        'label',
        'name_en',
        'name_kh',
        'name_zh',
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

    /** Label in the request locale (name_kh / name_zh / name_en), falling back to the raw label column. */
    public function getLabelAttribute(): ?string
    {
        return $this->resolveLocalizedName('name') ?? ($this->attributes['label'] ?? null);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'label'    => $this->label,
            'capacity' => $this->capacity,
        ]);
    }
}
