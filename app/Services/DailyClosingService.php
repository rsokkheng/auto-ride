<?php

namespace App\Services;

use App\Models\DailyClosing;
use App\Models\Delivery;
use App\Models\DeliveryCollection;
use App\Models\DriverClosing;
use App\Models\PartnerClosing;
use App\Models\User;
use App\Services\PartnerStatementService as PS;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Daily process for Partner packages (accountant):
 *   1. close with each staff driver   — the cash they collected handed over (in cash, by bank, or both), bank checked
 *   2. close with each partner        — the day's statement settled (cash, bank, cash + bank, wallet, carry over)
 *   3. finish the day                 — locked and logged
 */
class DailyClosingService
{
    public function __construct(private PS $statements) {}

    public static function rate(): int
    {
        return max(1, (int) config('ride.khr_per_usd', 4100));
    }

    // ── Drivers ──────────────────────────────────────────────────────────────

    /** Expected money for one driver on one day, from their verified collections. */
    public function driverExpected(int $driverId, string $date): array
    {
        $rows = DeliveryCollection::where('driver_id', $driverId)->whereDate('business_date', $date)->get();
        [$from, $to] = PS::dayRange($date);
        $appTz = config('app.timezone');

        [$a, $b] = [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)];
        // Handed back to ROTEH today, and still with the driver (failed today).
        $returned = Delivery::where('driver_id', $driverId)->whereNotNull('partner_id')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereIn('status', ['at_roteh', 'returned'])->whereBetween('hub_returned_at', [$a, $b]))
                ->orWhere(fn ($w) => $w->where('status', 'delivery_failed')->whereBetween('failed_at', [$a, $b])))
            ->get(['id', 'status']);

        return [
            'packages'      => $rows->count(),
            'cash_khr'      => (int) $rows->sum('cash_khr'),
            'cash_usd'      => round((float) $rows->sum('cash_usd'), 2),
            'bank_khr'      => (int) $rows->sum('bank_khr'),
            'bank_usd'      => round((float) $rows->sum('bank_usd'), 2),
            'expected_khr'  => (int) $rows->sum('expected_khr'),
            'total_khr'     => (int) $rows->sum('total_khr'),
            'difference_khr' => (int) $rows->sum('difference_khr'),
            'returned'      => $returned->whereIn('status', ['at_roteh', 'returned'])->count(),  // brought back to ROTEH
            'returning'     => $returned->where('status', 'delivery_failed')->count(),
            'collections'   => $rows,
        ];
    }

    /** Staff drivers with activity that day (or already closed), with expected amounts and closing. */
    public function driversForDay(string $date): Collection
    {
        [$from, $to] = PS::dayRange($date);
        $appTz = config('app.timezone');
        $ids = DeliveryCollection::whereDate('business_date', $date)->distinct()->pluck('driver_id')
            ->merge(Delivery::whereNotNull('partner_id')->whereNotNull('driver_id')
                ->where(fn ($q) => $q
                    ->whereBetween('hub_returned_at', [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)])
                    ->orWhereBetween('failed_at', [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)]))
                ->distinct()->pluck('driver_id'))
            ->merge(DriverClosing::whereDate('business_date', $date)->pluck('driver_id'))
            ->unique();

        $closings = DriverClosing::with('closer:id,name')->whereDate('business_date', $date)->get()->keyBy('driver_id');

        return User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'phone', 'driver_type'])
            ->map(fn (User $d) => [
                'driver'   => $d,
                'expected' => $this->driverExpected($d->id, $date),
                'closing'  => $closings[$d->id] ?? null,
            ]);
    }

    public function closeDriver(User $driver, string $date, array $in, User $by): DriverClosing
    {
        $this->assertDayOpen($date);
        $e = $this->driverExpected($driver->id, $date);
        $rate = self::rate();

        // The driver hands over the cash they collected — in cash, by bank transfer to ROTEH, or both.
        $method  = in_array($in['handover_method'] ?? 'cash', array_keys(DriverClosing::HANDOVER_METHODS), true) ? ($in['handover_method'] ?? 'cash') : 'cash';
        $byCash  = $method !== 'bank';
        $byBank  = $method !== 'cash';
        $receivedKhr = $byCash ? (int) ($in['received_cash_khr'] ?? 0) : 0;
        $receivedUsd = $byCash ? round((float) ($in['received_cash_usd'] ?? 0), 2) : 0.0;
        $bankKhr     = $byBank ? (int) ($in['received_bank_khr'] ?? 0) : 0;
        $bankUsd     = $byBank ? round((float) ($in['received_bank_usd'] ?? 0), 2) : 0.0;
        $handedOver  = $receivedKhr + $bankKhr + (int) round(($receivedUsd + $bankUsd) * $rate);
        $diff = $handedOver - ($e['cash_khr'] + (int) round($e['cash_usd'] * $rate));
        $bankExpected = $e['bank_khr'] > 0 || $e['bank_usd'] > 0;

        if ($byBank && ($bankKhr > 0 || $bankUsd > 0) && empty($in['handover_bank_name'])) {
            throw ValidationException::withMessages(['handover_bank_name' => 'Choose the bank the driver transferred to.']);
        }
        if ($diff !== 0 && trim((string) ($in['note'] ?? '')) === '') {
            throw ValidationException::withMessages(['note' => 'Money handed over differs from the cash expected — add a note.']);
        }
        if ($bankExpected && empty($in['bank_verified'])) {
            throw ValidationException::withMessages(['bank_verified' => 'Check the bank transfers against the bank statement first.']);
        }
        if ($e['returned'] > 0 && empty($in['returns_received'])) {
            throw ValidationException::withMessages(['returns_received' => "Confirm you received the {$e['returned']} returned package(s) from the driver."]);
        }

        return DB::transaction(function () use ($driver, $date, $e, $method, $receivedKhr, $receivedUsd, $bankKhr, $bankUsd, $diff, $rate, $in, $by) {
            $closing = DriverClosing::updateOrCreate(
                ['driver_id' => $driver->id, 'business_date' => $date],
                [
                    'packages_count'    => $e['packages'],
                    'returned_count'    => $e['returned'] + $e['returning'],
                    'expected_cash_khr' => $e['cash_khr'],
                    'expected_cash_usd' => $e['cash_usd'],
                    'expected_bank_khr' => $e['bank_khr'],
                    'expected_bank_usd' => $e['bank_usd'],
                    'received_cash_khr' => $receivedKhr,
                    'received_cash_usd' => $receivedUsd,
                    'handover_method'   => $method,
                    'received_bank_khr' => $bankKhr,
                    'received_bank_usd' => $bankUsd,
                    'handover_bank_name' => $bankKhr > 0 || $bankUsd > 0 ? ($in['handover_bank_name'] ?? null) : null,
                    'handover_bank_ref'  => $bankKhr > 0 || $bankUsd > 0 ? ($in['handover_bank_ref'] ?? null) : null,
                    'bank_verified'     => (bool) ($in['bank_verified'] ?? false),
                    'returns_received'  => (bool) ($in['returns_received'] ?? false),
                    'khr_per_usd'       => $rate,
                    'difference_khr'    => $diff,
                    'note'              => $in['note'] ?? null,
                    'closed_by'         => $by->id,
                    'closed_at'         => now(),
                ]
            );
            DeliveryCollection::where('driver_id', $driver->id)->whereDate('business_date', $date)
                ->update(['driver_closing_id' => $closing->id]);
            return $closing;
        });
    }

    public function reopenDriver(User $driver, string $date): void
    {
        $this->assertDayOpen($date);
        DB::transaction(function () use ($driver, $date) {
            DeliveryCollection::where('driver_id', $driver->id)->whereDate('business_date', $date)->update(['driver_closing_id' => null]);
            DriverClosing::where('driver_id', $driver->id)->whereDate('business_date', $date)->delete();
        });
    }

    // ── Partners ─────────────────────────────────────────────────────────────

    public function partnersForDay(string $date): Collection
    {
        [$from, $to] = PS::dayRange($date);
        $ov = $this->statements->overview($from, $to);
        $closings = PartnerClosing::with('closer:id,name')->whereDate('business_date', $date)->get()->keyBy('partner_id');
        $ids = $ov->keys()->merge($closings->keys())->unique();

        $atRoteh = Delivery::whereIn('partner_id', $ids)->where('status', 'at_roteh')
            ->selectRaw('partner_id, COUNT(*) n')->groupBy('partner_id')->pluck('n', 'partner_id');

        return User::whereIn('id', $ids->merge($atRoteh->keys())->unique())->with('partnerProfile')->orderBy('name')->get()
            ->map(fn (User $p) => [
                'partner'   => $p,
                'at_roteh'  => (int) ($atRoteh[$p->id] ?? 0),   // packages to hand back to the shop
                'name'    => $p->partnerProfile?->business_name ?: ($p->company_name ?: $p->name),
                'summary' => $ov[$p->id] ?? null,
                'closing' => $closings[$p->id] ?? null,
            ]);
    }

    public function closePartner(User $partner, string $date, array $in, User $by): PartnerClosing
    {
        $this->assertDayOpen($date);

        // Closing with the partner = handing their returned packages back to the shop.
        $this->returnToShop(Delivery::where('partner_id', $partner->id)->where('status', 'at_roteh')->get());

        [$from, $to] = PS::dayRange($date);
        $s = $this->statements->build($partner, $from, $to)['summary'];

        // How the balance was settled; cash + bank must add up to it (or explain why not).
        $method  = $in['settle_method'] ?? null;
        $balance = abs((int) $s['net_to_partner']);
        [$cash, $bank] = match ($method) {
            'cash'      => [$balance, 0],
            'bank'      => [0, $balance],
            'cash_bank' => [(int) ($in['settle_cash_khr'] ?? 0), (int) ($in['settle_bank_khr'] ?? 0)],
            default     => [0, 0],
        };
        if ($method === 'cash_bank' && ($cash <= 0 || $bank <= 0)) {
            throw ValidationException::withMessages(['settle_cash_khr' => 'Enter both the cash and the bank amount (or choose Cash or Bank transfer).']);
        }
        if ($method === 'cash_bank' && $cash + $bank !== $balance && trim((string) ($in['note'] ?? '')) === '') {
            throw ValidationException::withMessages(['note' => 'Cash + bank (' . number_format($cash + $bank) . ' ៛) differs from the balance (' . number_format($balance) . ' ៛) — add a note.']);
        }
        if (in_array($method, ['bank', 'cash_bank'], true) && empty($in['settle_bank_name'])) {
            throw ValidationException::withMessages(['settle_bank_name' => 'Choose the bank used for the transfer.']);
        }

        return PartnerClosing::updateOrCreate(
            ['partner_id' => $partner->id, 'business_date' => $date],
            [
                'successful_count'   => $s['successful_count'],
                'unsuccessful_count' => $s['unsuccessful_count'],
                'cod_collected'      => $s['cod_collected'],
                'delivery_fees'      => $s['service_fees'],
                'roteh_pays'         => $s['roteh_pays_partner'],
                'partner_pays'       => $s['partner_pays_roteh'],
                'net_to_partner'     => $s['net_to_partner'],
                'settle_method'      => $method,
                'settle_cash_khr'    => $cash,
                'settle_bank_khr'    => $bank,
                'settle_bank_name'   => $bank > 0 ? ($in['settle_bank_name'] ?? null) : null,
                'settle_ref'         => $in['settle_ref'] ?? null,
                'note'               => $in['note'] ?? null,
                'closed_by'          => $by->id,
                'closed_at'          => now(),
            ]
        );
    }

    public function reopenPartner(User $partner, string $date): void
    {
        $this->assertDayOpen($date);
        PartnerClosing::where('partner_id', $partner->id)->whereDate('business_date', $date)->delete();
    }

    /**
     * Packages at ROTEH (driver brought them back) → handed back to the shop.
     * @param iterable<Delivery> $packages
     */
    public function returnToShop(iterable $packages): int
    {
        $n = 0;
        foreach ($packages as $d) {
            if ($d->status !== 'at_roteh') continue;
            $d->update(['status' => 'returned', 'returned_at' => now()]);
            $n++;
            try {
                if ($partner = $d->partner) {
                    [$title, $body] = FcmService::message('delivery_returned_sender', [
                        'code' => 'PKG-' . str_pad((string) $d->id, 6, '0', STR_PAD_LEFT),
                    ]);
                    app(FcmService::class)->notifyUser($partner, $title, $body, ['type' => 'partner_package', 'delivery_id' => (string) $d->id]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return $n;
    }

    // ── Day ──────────────────────────────────────────────────────────────────

    public function finishDay(string $date, User $by, ?string $note = null): DailyClosing
    {
        $this->assertDayOpen($date);
        $drivers  = $this->driversForDay($date);
        $partners = $this->partnersForDay($date);

        $openDrivers  = $drivers->whereNull('closing')->count();
        $openPartners = $partners->whereNull('closing')->count();
        if ($openDrivers || $openPartners) {
            throw ValidationException::withMessages([
                'day' => "Close every driver and partner first ({$openDrivers} driver(s), {$openPartners} partner(s) still open).",
            ]);
        }

        $dc = $drivers->pluck('closing');
        $pc = $partners->pluck('closing');

        return DailyClosing::create([
            'business_date'   => $date,
            'drivers_closed'  => $dc->count(),
            'partners_closed' => $pc->count(),
            'totals'          => [
                'cash_khr_received' => (int) $dc->sum('received_cash_khr'),
                'cash_usd_received' => round((float) $dc->sum('received_cash_usd'), 2),
                'handover_bank_khr' => (int) $dc->sum('received_bank_khr'),     // drivers who transferred instead of cash
                'handover_bank_usd' => round((float) $dc->sum('received_bank_usd'), 2),
                'partner_settled_cash_khr' => (int) $pc->sum('settle_cash_khr'),
                'partner_settled_bank_khr' => (int) $pc->sum('settle_bank_khr'),
                'bank_khr'          => (int) $dc->sum('expected_bank_khr'),
                'bank_usd'          => round((float) $dc->sum('expected_bank_usd'), 2),
                'driver_difference_khr' => (int) $dc->sum('difference_khr'),
                'cod_collected'     => (int) $pc->sum('cod_collected'),
                'delivery_fees'     => (int) $pc->sum('delivery_fees'),
                'roteh_pays'        => (int) $pc->sum('roteh_pays'),
                'partner_pays'      => (int) $pc->sum('partner_pays'),
                'khr_per_usd'       => self::rate(),
            ],
            'note'      => $note,
            'closed_by' => $by->id,
            'closed_at' => now(),
        ]);
    }

    private function assertDayOpen(string $date): void
    {
        if (DailyClosing::isClosed($date)) {
            throw ValidationException::withMessages(['day' => 'This day is already finished and locked.']);
        }
    }
}
