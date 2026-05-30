<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopUpRequest extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'method',
        'status',
        'note',
        'admin_note',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount'      => 'integer',
        'approved_at' => 'datetime',
    ];

    public static array $methodLabels = [
        'cash'           => 'Cash',
        'online'         => 'Online Payment',
        'company_credit' => 'Company Credit',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
