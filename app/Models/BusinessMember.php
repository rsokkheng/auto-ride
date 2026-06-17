<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMember extends Model
{
    protected $fillable = [
        'business_account_id', 'user_id', 'role', 'department',
        'cost_center', 'employee_id', 'monthly_limit_khr', 'active', 'joined_at',
    ];

    protected $casts = [
        'monthly_limit_khr' => 'integer',
        'active'            => 'boolean',
        'joined_at'         => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(BusinessAccount::class, 'business_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
