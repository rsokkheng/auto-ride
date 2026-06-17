<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessAccount extends Model
{
    protected $fillable = [
        'name', 'code', 'tax_id', 'industry', 'contact_name', 'contact_phone',
        'billing_email', 'billing_cycle', 'monthly_credit_limit_khr',
        'used_credit_khr', 'logo_url', 'address', 'active', 'owner_user_id',
    ];

    protected $casts = [
        'monthly_credit_limit_khr' => 'integer',
        'used_credit_khr'          => 'integer',
        'active'                   => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function remainingCreditKhr(): int
    {
        return max(0, $this->monthly_credit_limit_khr - $this->used_credit_khr);
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
