<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\PricingSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Daily Partner statements after closing the books — the same numbers for
 * ROTEH (admin prints and hands them to the Partner) and the Partner (app /
 * Partner website) so both sides reconcile:
 *
 *   • Successful  — packages completed that day (by completed_at)
 *   • Unsuccessful — not delivered / returned / cancelled that day
 *
 * Money per package:
 *   COD collected   = item price the driver collected from the receiver
 *   Service fee     = ROTEH delivery fee
 *   Fee from COD    = fee the Partner owes that was not paid up front
 *                     (Partner pays + not prepaid by wallet/ABA)
 *   Net to Partner  = COD collected − fee from COD   (negative = Partner owes ROTEH)
 * Unsuccessful packages collect no COD; a fee is charged only if admin set
 * `partner_failed_fee_percent` (default 0 = free).
 */
class PartnerStatementService
{
    public const REASONS = [
        'cannot_contact'     => ['Cannot contact receiver', 'មិនអាចទាក់ទងអ្នកទទួល'],
        'receiver_refused'   => ['Receiver refused', 'អ្នកទទួលបដិសេធ'],
        'wrong_address'      => ['Wrong address', 'អាសយដ្ឋានខុស'],
        'receiver_postponed' => ['Receiver asked to deliver later', 'អ្នកទទួលស្នើឲ្យដឹកពេលក្រោយ'],
        'other'              => ['Other', 'ផ្សេងៗ'],
    ];

    public const UNSUCCESSFUL_STATUS = [
        'failed'    => ['Not delivered — returning', 'មិនបានដឹកដល់ — កំពុងយកត្រឡប់'],
        'returned'  => ['Returned to shop', 'បានប្រគល់ត្រឡប់មកហាង'],
        'cancelled' => ['Cancelled', 'បានបោះបង់'],
    ];

    public static function tz(): string
    {
        return config('ride.local_timezone', 'Asia/Phnom_Penh');
    }

    /** Day (Phnom Penh) → [from, to] in local time. */
    public static function dayRange(?string $date): array
    {
        $d = $date ? Carbon::parse($date, self::tz()) : now(self::tz());
        return [$d->copy()->startOfDay(), $d->copy()->endOfDay()];
    }

    public function build(User $partner, Carbon $from, Carbon $to): array
    {
        $appTz = config('app.timezone');
        $a = $from->copy()->setTimezone($appTz);
        $b = $to->copy()->setTimezone($appTz);
        $failedPct = (float) PricingSetting::get('partner_failed_fee_percent', 0);

        $successful = Delivery::where('partner_id', $partner->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereBetween('completed_at', [$a, $b])
            ->with('driver:id,name,phone')
            ->orderBy('completed_at')
            ->get()
            ->map(fn (Delivery $d) => $this->successRow($d))
            ->values();

        $unsuccessful = Delivery::where('partner_id', $partner->id)
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('status', 'delivery_failed')->whereBetween('failed_at', [$a, $b]))
                ->orWhere(fn ($w) => $w->where('status', 'returned')->whereBetween('returned_at', [$a, $b]))
                ->orWhere(fn ($w) => $w->where('status', 'cancelled')
                    ->whereBetween(\DB::raw('COALESCE(cancelled_at, updated_at)'), [$a, $b])))
            ->with('driver:id,name,phone')
            ->orderBy('id')
            ->get()
            ->map(fn (Delivery $d) => $this->failRow($d, $failedPct))
            ->values();

        $sum = fn (Collection $c, string $k) => (int) $c->sum($k);
        $profile = $partner->partnerProfile;

        return [
            'statement_no' => 'ST-' . $partner->id . '-' . $from->format('Ymd') . ($from->isSameDay($to) ? '' : '-' . $to->format('Ymd')),
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'generated_at' => now(self::tz())->toIso8601String(),
            'partner'      => [
                'id'            => $partner->id,
                'business_name' => $profile?->business_name ?: ($partner->company_name ?: $partner->name),
                'phone'         => $profile?->phone ?: $partner->phone,
                'address'       => $profile?->address,
            ],
            'successful'   => $successful->all(),
            'unsuccessful' => $unsuccessful->all(),
            'summary'      => [
                'successful_count'    => $successful->count(),
                'unsuccessful_count'  => $unsuccessful->count(),
                'returned_count'      => $unsuccessful->where('status', 'returned')->count(),
                'returning_count'     => $unsuccessful->where('status', 'failed')->count(),
                'cancelled_count'     => $unsuccessful->where('status', 'cancelled')->count(),
                'cod_collected'       => $sum($successful, 'cod_collected'),
                'service_fees'        => $sum($successful, 'fee'),
                'fees_paid_by_receiver' => $sum($successful->where('fee_paid_by', 'receiver'), 'fee'),
                'fees_prepaid'        => $sum($successful->where('fee_paid_by', 'partner_prepaid'), 'fee'),
                'fees_from_cod'       => $sum($successful, 'fee_from_cod') + $sum($unsuccessful, 'fee_charged'),
                'cod_not_collected'   => $sum($unsuccessful, 'cod_amount'),
                'failed_fees'         => $sum($unsuccessful, 'fee_charged'),
                'net_to_partner'      => $sum($successful, 'net_to_partner') + $sum($unsuccessful, 'net_to_partner'),
                'failed_fee_percent'  => $failedPct,
            ],
        ];
    }

    private function base(Delivery $d): array
    {
        $tz = self::tz();
        return [
            'id'              => $d->id,
            'code'            => 'PKG-' . str_pad((string) $d->id, 6, '0', STR_PAD_LEFT),
            'reference'       => $d->partner_reference,
            'created_at'      => $d->created_at?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
            'receiver_name'   => $d->recipient_name,
            'receiver_phone'  => $d->recipient_phone,
            'dropoff_address' => $d->dropoff_address,
            'package_type'    => $d->package_type,
            'package_size'    => $d->package_size,
            'express'         => $d->service_option === 'express',
            'driver'          => $d->driver?->name,
            'fee'             => (int) $d->fee,
            'cod_amount'      => (int) $d->package_amount,
        ];
    }

    private function successRow(Delivery $d): array
    {
        $fee = (int) $d->fee;
        $cod = (int) $d->package_amount;
        $prepaid = in_array($d->payment_status, ['paid', 'prepaid'], true);
        [$paidBy, $fromCod] = match (true) {
            $d->payment_by === 'recipient' => ['receiver', 0],
            $prepaid                       => ['partner_prepaid', 0],
            default                        => ['partner_from_cod', $fee],
        };

        return $this->base($d) + [
            'status'         => 'completed',
            'completed_at'   => $d->completed_at?->copy()->setTimezone(self::tz())->format('Y-m-d H:i'),
            'cod_collected'  => $cod,
            'fee_paid_by'    => $paidBy,        // receiver | partner_prepaid | partner_from_cod
            'fee_from_cod'   => $fromCod,
            'net_to_partner' => $cod - $fromCod,
        ];
    }

    private function failRow(Delivery $d, float $failedPct): array
    {
        $status = $d->packageStatus(); // failed | returned | cancelled
        $at = match ($status) {
            'failed'   => $d->failed_at,
            'returned' => $d->returned_at,
            default    => $d->cancelled_at ?? $d->updated_at,
        };
        // Only real delivery attempts can carry a fee; cancellations never do.
        $charged = $status === 'cancelled' ? 0 : (int) round((int) $d->fee * $failedPct / 100);

        return $this->base($d) + [
            'status'      => $status,
            'closed_at'   => $at?->copy()->setTimezone(self::tz())->format('Y-m-d H:i'),
            'reason'      => $status === 'cancelled' ? null : ($d->failure_reason ?? 'other'),
            'note'        => $status === 'cancelled' ? $d->cancellation_reason : $d->failure_note,
            'fee_charged' => $charged,
            'net_to_partner' => -$charged,
        ];
    }
}
