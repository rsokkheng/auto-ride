<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Close between the accountant and a Partner — the day's statement settled. */
class PartnerClosing extends Model
{
    public const METHODS = ['cash', 'bank', 'cash_bank', 'wallet', 'carry_over'];

    public const METHOD_LABELS = [
        'cash' => 'Cash', 'bank' => 'Bank transfer', 'cash_bank' => 'Cash + Bank',
        'wallet' => 'ROTEH Pay wallet', 'carry_over' => 'Carry over to next statement',
    ];

    protected $fillable = [
        'partner_id', 'business_date', 'successful_count', 'unsuccessful_count', 'cod_collected',
        'delivery_fees', 'roteh_pays', 'partner_pays', 'net_to_partner',
        'settle_method', 'settle_cash_khr', 'settle_bank_khr', 'settle_bank_name', 'settle_ref', 'note', 'closed_by', 'closed_at',
    ];

    protected $casts = ['business_date' => 'date:Y-m-d', 'closed_at' => 'datetime'];

    /** e.g. "Cash + Bank (50,000 ៛ cash · 35,000 ៛ ABA)". */
    public function methodLabel(): string
    {
        $label = self::METHOD_LABELS[$this->settle_method] ?? ucfirst(str_replace('_', ' ', (string) $this->settle_method));
        return match ($this->settle_method) {
            'cash_bank' => $label . ' (' . number_format($this->settle_cash_khr) . ' ៛ cash · ' . number_format($this->settle_bank_khr) . ' ៛ ' . ($this->settle_bank_name ?: 'bank') . ')',
            'bank'      => $label . ($this->settle_bank_name ? " · {$this->settle_bank_name}" : ''),
            default     => $label,
        };
    }

    public function partner(): BelongsTo { return $this->belongsTo(User::class, 'partner_id'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
}
