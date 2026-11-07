<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Close between the accountant and a Partner — the day's statement settled. */
class PartnerClosing extends Model
{
    public const METHODS = ['cash', 'bank', 'wallet', 'carry_over'];

    protected $fillable = [
        'partner_id', 'business_date', 'successful_count', 'unsuccessful_count', 'cod_collected',
        'delivery_fees', 'roteh_pays', 'partner_pays', 'net_to_partner',
        'settle_method', 'settle_ref', 'note', 'closed_by', 'closed_at',
    ];

    protected $casts = ['business_date' => 'date:Y-m-d', 'closed_at' => 'datetime'];

    public function partner(): BelongsTo { return $this->belongsTo(User::class, 'partner_id'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
}
