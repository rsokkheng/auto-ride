<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Ride;
use App\Models\TransactionRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Unified payment processor for deliveries and rides.
 *
 * Payment method behaviour:
 *  cash        – creates a PENDING transaction; wallet credit happens when
 *                admin/driver confirms receipt (confirmCash).
 *  wallet      – deducts sender/passenger wallet immediately; driver wallet
 *                credited on completion.
 *  aba / wing / other_online – creates a PENDING transaction (waiting for
 *                gateway callback); admin confirms via confirmOnline.
 */
class PaymentService
{
    public function __construct(
        private CommissionService $commission,
        private WalletService     $wallet,
    ) {}

    // ── Delivery ─────────────────────────────────────────────────────────────

    public function processDelivery(Delivery $delivery): TransactionRecord
    {
        $driver = $delivery->driver?->load('company');
        $fare   = $delivery->fee;
        $split  = $driver
            ? $this->commission->split($fare, $driver)
            : ['gross_amount' => $fare, 'platform_fee' => 0, 'company_share' => 0, 'driver_earning' => $fare, 'platform_rate' => 0, 'driver_type' => 'owner'];

        $method = $delivery->payment_method ?? 'cash';
        $isCash = $method === 'cash';
        $isOnline = in_array($method, ['aba', 'wing', 'other_online']);

        return DB::transaction(function () use ($delivery, $driver, $split, $method, $isCash, $isOnline, $fare) {
            $status = ($isCash || $isOnline) ? 'pending' : 'completed';

            $record = TransactionRecord::create([
                'reference_type' => Delivery::class,
                'reference_id'   => $delivery->id,
                'payer_id'       => $delivery->sender_id,
                'payee_id'       => $driver?->id,
                'type'           => 'delivery_payment',
                'payment_method' => $method,
                'payment_by'     => $delivery->payment_by ?? 'sender',
                'gross_amount'   => $fare,
                'platform_fee'   => $split['platform_fee'],
                'company_share'  => $split['company_share'],
                'driver_earning' => $split['driver_earning'],
                'status'         => $status,
                'note'           => "Delivery #{$delivery->id}",
            ]);

            // Wallet payment: deduct from sender & update delivery immediately.
            if ($method === 'wallet') {
                $sender = $delivery->sender;
                if ($sender && $sender->wallet_balance >= $fare) {
                    $this->wallet->debit($sender, $fare, 'delivery_payment', "Delivery #{$delivery->id} fee", $delivery);
                }
                if ($driver && $split['driver_earning'] > 0) {
                    $this->wallet->processTripPayment($driver, $split, $delivery);
                }
                $delivery->update(['payment_status' => 'paid']);
            } else {
                $delivery->update(['payment_status' => 'pending']);
            }

            return $record;
        });
    }

    // ── Ride ─────────────────────────────────────────────────────────────────

    public function processRide(Ride $ride): TransactionRecord
    {
        $driver = $ride->driver?->load('company');
        $fare   = $ride->fare;
        $split  = $driver
            ? $this->commission->split($fare, $driver)
            : ['gross_amount' => $fare, 'platform_fee' => 0, 'company_share' => 0, 'driver_earning' => $fare, 'platform_rate' => 0, 'driver_type' => 'owner'];

        $method   = $ride->payment_method ?? 'cash';
        $isCash   = $method === 'cash';
        $isOnline = in_array($method, ['aba', 'wing', 'other_online']);

        return DB::transaction(function () use ($ride, $driver, $split, $method, $isCash, $isOnline, $fare) {
            $status = ($isCash || $isOnline) ? 'pending' : 'completed';

            $record = TransactionRecord::create([
                'reference_type' => Ride::class,
                'reference_id'   => $ride->id,
                'payer_id'       => $ride->passenger_id,
                'payee_id'       => $driver?->id,
                'type'           => 'ride_payment',
                'payment_method' => $method,
                'payment_by'     => 'sender',
                'gross_amount'   => $fare,
                'platform_fee'   => $split['platform_fee'],
                'company_share'  => $split['company_share'],
                'driver_earning' => $split['driver_earning'],
                'status'         => $status,
                'note'           => "Ride #{$ride->id}",
            ]);

            if ($method === 'wallet') {
                $passenger = $ride->passenger;
                if ($passenger && $passenger->wallet_balance >= $fare) {
                    $this->wallet->debit($passenger, $fare, 'ride_payment', "Ride #{$ride->id} fare", $ride);
                }
                if ($driver && $split['driver_earning'] > 0) {
                    $this->wallet->processTripPayment($driver, $split, $ride);
                }
                $ride->update(['payment_status' => 'paid']);
            } else {
                $ride->update(['payment_status' => 'pending']);
            }

            return $record;
        });
    }

    // ── Confirm cash / online payment (admin or driver) ───────────────────────

    public function confirm(TransactionRecord $record, User $confirmedBy): void
    {
        if (! $record->isPending()) {
            throw new \RuntimeException('Transaction is not pending.');
        }

        DB::transaction(function () use ($record, $confirmedBy) {
            $record->update([
                'status'       => 'completed',
                'processed_by' => $confirmedBy->id,
                'processed_at' => now(),
            ]);

            // Credit driver wallet now that payment is confirmed.
            $driver = $record->payee;
            if ($driver && $record->driver_earning > 0) {
                $split = [
                    'fare'           => $record->gross_amount,
                    'platform_fee'   => $record->platform_fee,
                    'company_share'  => $record->company_share,
                    'driver_earning' => $record->driver_earning,
                    'platform_rate'  => 0,
                    'driver_type'    => $driver->driver_type ?? 'owner',
                ];
                $this->wallet->processTripPayment($driver, $split, $record->reference);
            }

            // Mark the delivery/ride as paid.
            $record->reference?->update(['payment_status' => 'paid']);
        });
    }

    // ── Cancel / Refund ───────────────────────────────────────────────────────

    public function cancel(TransactionRecord $record, User $cancelledBy, string $note = ''): void
    {
        if ($record->status === 'completed') {
            throw new \RuntimeException('Completed transactions must be refunded, not cancelled.');
        }

        $record->update([
            'status'       => 'cancelled',
            'processed_by' => $cancelledBy->id,
            'processed_at' => now(),
            'note'         => $note ?: $record->note,
        ]);

        $record->reference?->update(['payment_status' => 'unpaid']);
    }
}
