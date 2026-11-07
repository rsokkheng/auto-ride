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
 *   1. close with each staff driver   — cash ៛ / cash $ received vs verified, bank checked
 *   2. close with each partner        — the day's statement settled
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

        $returned = Delivery::where('driver_id', $driverId)->whereNotNull('partner_id')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('status', 'returned')->whereBetween('returned_at', [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)]))
                ->orWhere(fn ($w) => $w->where('status', 'delivery_failed')->whereBetween('failed_at', [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)])))
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
            'returned'      => $returned->where('status', 'returned')->count(),
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
                    ->whereBetween('returned_at', [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)])
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

        $receivedKhr = (int) ($in['received_cash_khr'] ?? 0);
        $receivedUsd = round((float) ($in['received_cash_usd'] ?? 0), 2);
        $diff = ($receivedKhr + (int) round($receivedUsd * $rate)) - ($e['cash_khr'] + (int) round($e['cash_usd'] * $rate));
        $bankExpected = $e['bank_khr'] > 0 || $e['bank_usd'] > 0;

        if ($diff !== 0 && trim((string) ($in['note'] ?? '')) === '') {
            throw ValidationException::withMessages(['note' => 'Cash received differs from expected — add a note.']);
        }
        if ($bankExpected && empty($in['bank_verified'])) {
            throw ValidationException::withMessages(['bank_verified' => 'Check the bank transfers against the bank statement first.']);
        }

        return DB::transaction(function () use ($driver, $date, $e, $receivedKhr, $receivedUsd, $diff, $rate, $in, $by) {
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
                    'bank_verified'     => (bool) ($in['bank_verified'] ?? false),
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

        return User::whereIn('id', $ids)->with('partnerProfile')->orderBy('name')->get()
            ->map(fn (User $p) => [
                'partner' => $p,
                'name'    => $p->partnerProfile?->business_name ?: ($p->company_name ?: $p->name),
                'summary' => $ov[$p->id] ?? null,
                'closing' => $closings[$p->id] ?? null,
            ]);
    }

    public function closePartner(User $partner, string $date, array $in, User $by): PartnerClosing
    {
        $this->assertDayOpen($date);
        [$from, $to] = PS::dayRange($date);
        $s = $this->statements->build($partner, $from, $to)['summary'];

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
                'settle_method'      => $in['settle_method'] ?? null,
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
