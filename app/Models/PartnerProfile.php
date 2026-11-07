<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A passenger account's Partner (package sender) registration. */
class PartnerProfile extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    protected $fillable = [
        'user_id', 'business_name', 'business_type', 'contact_name', 'phone', 'address',
        'latitude', 'longitude', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
