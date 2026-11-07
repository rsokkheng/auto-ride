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
 * Money per package — ROTEH always charges the delivery fee to the Partner;
 * the receiver pays only the item price (COD):
 *   COD collected      = item price the driver collected from the receiver
 *   Delivery fee       = ROTEH fee, charged to the Partner
 *   ROTEH pays partner = COD − delivery fee            (when ≥ 0)
 *   Partner pays ROTEH = delivery fee − COD            (when > 0, e.g. no COD)
 *   Balance            = Σ ROTEH pays − Σ Partner pays (negative = Partner owes)
 * A fee the Partner prepaid (ROTEH Pay / ABA) is not deducted again.
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

    /** Status groups used by the overview / "All packages" report. */
    public const OPEN_GROUPS = [
        'pending'    => ['created'],
        'confirmed'  => ['requested', 'pending'],
        'on_the_way' => ['assigned', 'accepted', 'picked_up', 'in_progress', 'in_transit'],
    ];

    public const STATUS_LABELS = [
        'pending'    => ['Pending', 'កំពុងរង់ចាំ'],
        'confirmed'  => ['Confirmed — finding driver', 'បានបញ្ជាក់ — កំពុងរកអ្នកបើកបរ'],
        'on_the_way' => ['On the way', 'កំពុងដឹក'],
        'completed'  => ['Completed', 'បានបញ្ចប់'],
        'failed'     => ['Not delivered — returning', 'មិនបានដឹកដល់ — កំពុងយកត្រឡប់'],
        'returned'   => ['Returned to shop', 'បានប្រគល់ត្រឡប់មកហាង'],
        'cancelled'  => ['Cancelled', 'បានបោះបង់'],
    ];

    /**
     * SQL pieces for "packages of the day": created that day and still open
     * (pending / confirmed / on the way), or closed that day (completed,
     * not delivered, returned, cancelled — by their own timestamp).
     * @return array{0: array<string,string>, 1: array} conditions per group, bindings per group
     */
    private function dayConditions(Carbon $from, Carbon $to): array
    {
        $appTz = config('app.timezone');
        $a = $from->copy()->setTimezone($appTz)->toDateTimeString();
        $b = $to->copy()->setTimezone($appTz)->toDateTimeString();
        $in = fn (array $v) => "'" . implode("','", $v) . "'";

        $cond = [];
        foreach (self::OPEN_GROUPS as $g => $statuses) {
            $cond[$g] = "(status IN ({$in($statuses)}) AND created_at BETWEEN ? AND ?)";
        }
        $cond['completed'] = "(status IN ('delivered','completed') AND completed_at BETWEEN ? AND ?)";
        $cond['failed']    = "(status = 'delivery_failed' AND failed_at BETWEEN ? AND ?)";
        $cond['returned']  = "(status = 'returned' AND returned_at BETWEEN ? AND ?)";
        $cond['cancelled'] = "(status = 'cancelled' AND COALESCE(cancelled_at, updated_at) BETWEEN ? AND ?)";

        return [$cond, array_fill_keys(array_keys($cond), [$a, $b])];
    }

    /**
     * One row per partner with packages that day: counts for every status,
     * COD, service fees and net — one grouped query, for the admin overview.
     * @return Collection<int, array> keyed by partner_id
     */
    public function overview(Carbon $from, Carbon $to): Collection
    {
        [$cond, $bind] = $this->dayConditions($from, $to);
        $pct = (float) PricingSetting::get('partner_failed_fee_percent', 0);

        $select = ['partner_id'];
        $bindings = [];
        foreach ($cond as $g => $c) {
            $select[] = "SUM(CASE WHEN {$c} THEN 1 ELSE 0 END) AS c_{$g}";
            array_push($bindings, ...$bind[$g]);
        }
        $done = $cond['completed'];
        $select[] = "SUM(CASE WHEN {$done} THEN package_amount ELSE 0 END) AS cod_collected";
        $select[] = "SUM(CASE WHEN {$done} THEN fee ELSE 0 END) AS service_fees";
        $owed = "(CASE WHEN COALESCE(payment_method,'cash') IN ('wallet','aba','acleda') AND COALESCE(payment_status,'') IN ('paid','prepaid') THEN 0 ELSE fee END)";
        $select[] = "SUM(CASE WHEN {$done} THEN {$owed} ELSE 0 END) AS fees_from_cod";
        $select[] = "SUM(CASE WHEN {$done} THEN package_amount ELSE 0 END) AS total_collected";
        $select[] = "SUM(CASE WHEN {$done} AND package_amount - {$owed} > 0 THEN package_amount - {$owed} ELSE 0 END) AS roteh_pays";
        $select[] = "SUM(CASE WHEN {$done} AND {$owed} - package_amount > 0 THEN {$owed} - package_amount ELSE 0 END) AS partner_pays";
        $select[] = "SUM(CASE WHEN {$cond['failed']} OR {$cond['returned']} THEN fee ELSE 0 END) AS attempted_fees";
        $select[] = "SUM(CASE WHEN {$cond['failed']} OR {$cond['returned']} THEN package_amount ELSE 0 END) AS cod_not_collected";
        array_push($bindings, ...$bind['completed'], ...$bind['completed'], ...$bind['completed'],
            ...$bind['completed'], ...$bind['completed'], ...$bind['completed'],
            ...$bind['failed'], ...$bind['returned'], ...$bind['failed'], ...$bind['returned']);

        $where = '(' . implode(' OR ', $cond) . ')';
        $whereBindings = array_merge(...array_values($bind));

        return Delivery::query()
            ->whereNotNull('partner_id')
            ->whereRaw($where, $whereBindings)
            ->selectRaw(implode(', ', $select), $bindings)
            ->groupBy('partner_id')
            ->get()
            ->mapWithKeys(function ($r) use ($pct) {
                $counts = [];
                foreach (array_keys(self::STATUS_LABELS) as $g) $counts[$g] = (int) $r->{"c_{$g}"};
                $failedFees = (int) round((int) $r->attempted_fees * $pct / 100);
                return [(int) $r->partner_id => [
                    'counts'            => $counts,
                    'total'             => array_sum($counts),
                    'successful'        => $counts['completed'],
                    'unsuccessful'      => $counts['failed'] + $counts['returned'] + $counts['cancelled'],
                    'open'              => $counts['pending'] + $counts['confirmed'] + $counts['on_the_way'],
                    'cod_collected'     => (int) $r->cod_collected,
                    'cod_not_collected' => (int) $r->cod_not_collected,
                    'service_fees'      => (int) $r->service_fees,
                    'fees_from_cod'     => (int) $r->fees_from_cod,
                    'failed_fees'       => $failedFees,
                    'total_collected'   => (int) $r->total_collected,
                    'roteh_pays_partner' => (int) $r->roteh_pays,
                    'partner_pays_roteh' => (int) $r->partner_pays + $failedFees,
                    'net_to_partner'    => (int) $r->roteh_pays - (int) $r->partner_pays - $failedFees,
                ]];
            });
    }

    /**
     * Staff driver cash hand-over for a closing day. Staff drivers are on a
     * salary: everything they collect for Partner packages belongs to ROTEH.
     *   cash to hand over = COD collected + delivery fees the receiver paid in cash
     * Plus the packages they must bring back to shops (not delivered).
     * @return Collection<int, array>
     */
    public function driverCash(Carbon $from, Carbon $to): Collection
    {
        $appTz = config('app.timezone');
        [$a, $b] = [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)];

        $rows = Delivery::whereNotNull('partner_id')->whereNotNull('driver_id')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereIn('status', ['delivered', 'completed'])->whereBetween('completed_at', [$a, $b]))
                ->orWhere(fn ($w) => $w->where('status', 'delivery_failed')->whereBetween('failed_at', [$a, $b]))
                ->orWhere(fn ($w) => $w->where('status', 'returned')->whereBetween('returned_at', [$a, $b])))
            ->with(['driver:id,name,phone,driver_type', 'partner:id,name,company_name', 'partner.partnerProfile:id,user_id,business_name', 'collection'])
            ->orderBy('completed_at')
            ->get();

        return $rows->groupBy('driver_id')->map(function (Collection $list) {
            $driver = $list->first()->driver;
            $done   = $list->filter(fn ($d) => in_array($d->status, ['delivered', 'completed'], true));
            // The receiver pays only the COD — the fee is charged to the Partner.
            $cashFee = fn ($d) => 0;
            $cod = (int) $done->sum('package_amount');
            $fees = (int) $done->sum($cashFee);
            $col = $done->pluck('collection')->filter();
            return [
                'driver'          => $driver,
                'cash_khr'        => (int) $col->sum('cash_khr') + (int) $done->whereNull('collection')->sum('package_amount'),
                'cash_usd'        => round((float) $col->sum('cash_usd'), 2),
                'bank_khr'        => (int) $col->sum('bank_khr'),
                'bank_usd'        => round((float) $col->sum('bank_usd'), 2),
                'delivered'       => $done->count(),
                'cod_collected'   => $cod,
                'cash_fees'       => $fees,
                'cash_to_hand_over' => $cod + $fees,
                'returning'       => $list->where('status', 'delivery_failed')->count(),
                'returned'        => $list->where('status', 'returned')->count(),
                'packages'        => $list->map(fn (Delivery $d) => [
                    'code'      => 'PKG-' . str_pad((string) $d->id, 6, '0', STR_PAD_LEFT),
                    'partner'   => $d->partner?->partnerProfile?->business_name ?: ($d->partner?->company_name ?: $d->partner?->name),
                    'status'    => $d->packageStatus(),
                    'reason'    => $d->failure_reason,
                    'receiver'  => $d->recipient_name,
                    'cod'       => in_array($d->status, ['delivered', 'completed'], true) ? (int) $d->package_amount : 0,
                    'cash_fee'  => in_array($d->status, ['delivered', 'completed'], true) ? $cashFee($d) : 0,
                    'time'      => ($d->completed_at ?? $d->returned_at ?? $d->failed_at)?->copy()->setTimezone(self::tz())->format('H:i'),
                    'paid_via'  => $d->collection?->methodLabel(),
                    'cash_usd'  => $d->collection?->cash_usd ?? 0,
                    'bank'      => $d->collection ? ($d->collection->bank_khr + (int) round($d->collection->bank_usd * $d->collection->khr_per_usd)) : 0,
                ])->values()->all(),
            ];
        })->sortByDesc('cash_to_hand_over')->values();
    }

    /** Every package of the day with its status group — the "All packages" report. */
    public function allPackages(User $partner, Carbon $from, Carbon $to): array
    {
        [$cond, $bind] = $this->dayConditions($from, $to);
        $rows = Delivery::where('partner_id', $partner->id)
            ->whereRaw('(' . implode(' OR ', $cond) . ')', array_merge(...array_values($bind)))
            ->with('driver:id,name,phone')
            ->orderBy('id')
            ->get();

        return $rows->map(function (Delivery $d) {
            $ps = $d->packageStatus();
            $group = match ($ps) {
                'pending'                                    => 'pending',
                'confirmed'                                  => 'confirmed',
                'driver_assigned', 'picked_up', 'in_transit' => 'on_the_way',
                default                                      => $ps, // completed | failed | returned | cancelled
            };
            $tz = self::tz();
            $closed = match ($group) {
                'completed' => $d->completed_at,
                'failed'    => $d->failed_at,
                'returned'  => $d->returned_at,
                'cancelled' => $d->cancelled_at ?? $d->updated_at,
                default     => null,
            };
            return $this->base($d) + [
                'group'     => $group,
                'closed_at' => $closed?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                'reason'    => in_array($group, ['failed', 'returned'], true) ? ($d->failure_reason ?? 'other') : null,
                'payment_by' => $d->payment_by,
            ];
        })->values()->all();
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
            ->with(['driver:id,name,phone', 'collection'])
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
            'all'          => $all = $this->allPackages($partner, $from, $to),
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
                'total_collected'     => $sum($successful, 'total_collected'),
                'roteh_pays_partner'  => $sum($successful, 'roteh_pays_partner'),
                'partner_pays_roteh'  => $sum($successful, 'partner_pays_roteh') + $sum($unsuccessful, 'partner_pays_roteh'),
                'cod_packages'        => $successful->where('cod_collected', '>', 0)->count(),
                'non_cod_packages'    => $successful->where('cod_collected', 0)->count(),
                'failed_fee_percent'  => $failedPct,
                'status_counts'       => collect(array_keys(self::STATUS_LABELS))
                    ->mapWithKeys(fn ($g) => [$g => collect($all)->where('group', $g)->count()])->all(),
                'total_count'         => count($all),
            ],
            // Accountant's close with this partner for the day (null = still open).
            'closing'      => $from->isSameDay($to)
                ? \App\Models\PartnerClosing::with('closer:id,name')->where('partner_id', $partner->id)
                    ->whereDate('business_date', $from->toDateString())->first()?->only([
                        'settle_method', 'settle_ref', 'note', 'closed_at', 'net_to_partner',
                    ])
                : null,
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
        // Prepaid only when the Partner really paid up front (wallet / ABA) —
        // a cash fee is settled from the COD or on this statement.
        $prepaid = in_array($d->payment_method, ['wallet', 'aba', 'acleda'], true)
            && in_array($d->payment_status, ['paid', 'prepaid'], true);
        // The fee is always the Partner's: prepaid, taken from the COD, or owed.
        [$paidBy, $fromCod] = match (true) {
            $prepaid  => ['partner_prepaid', 0],
            $cod > 0  => ['partner_from_cod', $fee],
            default   => ['partner_pays', $fee],
        };

        return $this->base($d) + [
            'status'         => 'completed',
            'completed_at'   => $d->completed_at?->copy()->setTimezone(self::tz())->format('Y-m-d H:i'),
            'cod_collected'  => $cod,
            'fee_paid_by'    => $paidBy,        // receiver | partner_prepaid | partner_from_cod
            'fee_from_cod'   => $fromCod,
            'total_collected' => $cod,   // the receiver pays only the item price
            'paid_via'       => $d->collection?->methodLabel(),
            'collected'      => $d->collection ? [
                'cash_khr' => $d->collection->cash_khr, 'cash_usd' => $d->collection->cash_usd,
                'bank_khr' => $d->collection->bank_khr, 'bank_usd' => $d->collection->bank_usd,
                'bank_name' => $d->collection->bank_name, 'bank_ref' => $d->collection->bank_ref,
                'difference_khr' => $d->collection->difference_khr, 'note' => $d->collection->note,
            ] : null,
            'roteh_pays_partner' => max(0, $cod - $fromCod),
            'partner_pays_roteh' => max(0, $fromCod - $cod),
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
            'roteh_pays_partner' => 0,
            'partner_pays_roteh' => $charged,
            'net_to_partner' => -$charged,
        ];
    }
}
