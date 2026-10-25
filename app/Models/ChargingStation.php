<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargingStation extends Model
{
    use HasFactory, HasLocalizedName;

    /** Columns with _en / _kh / _zh variants (see HasLocalizedName). */
    protected array $localized = ['name', 'address'];

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
