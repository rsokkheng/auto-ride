<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'platform_commission_rate',
        'company_commission_rate',
        'rental_daily_rate',
        'active',
    ];

    protected $casts = [
        'platform_commission_rate' => 'float',
        'company_commission_rate'  => 'float',
        'rental_daily_rate'        => 'integer',
        'active'                   => 'boolean',
    ];

    public function drivers(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
