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

    /**
     * Cambodian provinces and the place names in addresses that belong to
     * them (addresses rarely say "Preah Sihanouk", they say "Sihanoukville").
     */
    public const PROVINCES = [
        'Phnom Penh'       => ['Phnom Penh', 'Daun Penh', 'Chamkarmon', 'Chamkar Mon', 'Sen Sok', 'Chbar Ampov', 'Toul Kork', 'Tuol Kouk', 'Russey Keo', 'Por Senchey', '7 Makara', 'Boeng Keng Kang'],
        'Siem Reap'        => ['Siem Reap', 'Angkor'],
        'Preah Sihanouk'   => ['Preah Sihanouk', 'Sihanoukville', 'Mittapheap'],
        'Kampot'           => ['Kampot'],
        'Kep'              => ['Kep'],
        'Battambang'       => ['Battambang'],
        'Kandal'           => ['Kandal', 'Ta Khmau'],
        'Kampong Cham'     => ['Kampong Cham'],
        'Kampong Chhnang'  => ['Kampong Chhnang'],
        'Kampong Speu'     => ['Kampong Speu'],
        'Kampong Thom'     => ['Kampong Thom'],
        'Takeo'            => ['Takeo'],
        'Koh Kong'         => ['Koh Kong'],
        'Banteay Meanchey' => ['Banteay Meanchey', 'Poipet', 'Serei Saophoan'],
        'Pursat'           => ['Pursat'],
        'Kratie'           => ['Kratie'],
        'Mondulkiri'       => ['Mondulkiri'],
        'Ratanakiri'       => ['Ratanakiri'],
        'Stung Treng'      => ['Stung Treng'],
        'Preah Vihear'     => ['Preah Vihear'],
        'Oddar Meanchey'   => ['Oddar Meanchey'],
        'Pailin'           => ['Pailin'],
        'Prey Veng'        => ['Prey Veng'],
        'Svay Rieng'       => ['Svay Rieng', 'Bavet'],
        'Tboung Khmum'     => ['Tboung Khmum'],
    ];

    /** Province this station is in, read from its address. */
    public function province(): ?string
    {
        $address = (string) ($this->getAttributes()['address'] ?? '');
        foreach (self::PROVINCES as $province => $names) {
            foreach ($names as $name) {
                if (stripos($address, $name) !== false) return $province;
            }
        }

        return null;
    }

    /** Stations whose address names the given province (or one of its towns/districts). */
    public function scopeInProvince($query, string $province)
    {
        return $query->where(function ($q) use ($province) {
            foreach (self::PROVINCES[$province] ?? [$province] as $name) {
                $q->orWhere('address', 'like', "%{$name}%");
            }
        });
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(ChargingStationFavorite::class);
    }
}
