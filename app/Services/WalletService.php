<?php

namespace App\Services;

use App\Models\TopUpRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Credit money into a user's wallet and record the transaction.
     */
    public function credit(
        User   $user,
        int    $amount,
        string $type,
        string $note = '',
        ?Model $reference = null,
        ?int   $actorId = null,
    ): WalletTransaction {
        return $this->record($user, 'credit', $amount, $type, $note, $reference, $actorId);
    }

    /**
     * Debit money from a user's wallet and record the transaction.
     * Throws if balance would go below zero.
     */
    public function debit(
        User   $user,
        int    $amount,
        string $type,
        string $note = '',
        ?Model $reference = null,
        ?int   $actorId = null,
    ): WalletTransaction {
        if ($user->wallet_balance < $amount) {
            throw new \RuntimeException("Insufficient wallet balance.");
        }

        return $this->record($user, 'debit', $amount, $type, $note, $reference, $actorId);
    }

    /**
     * Process a trip payment: credit driver earning and record platform fee.
     * All in one DB transaction.
     */
    public function processTripPayment(User $driver, array $split, Model $reference): void
    {
        DB::transaction(function () use ($driver, $split, $reference) {
            // Credit driver earning (skip for employee drivers — salary-based).
            if ($split['driver_earning'] > 0) {
                $this->credit(
                    $driver,
                    $split['driver_earning'],
                    'trip_earning',
                    "Trip fare: {$split['fare']} ៛ — earned {$split['driver_earning']} ៛",
                    $reference,
                );
            }

            // Record platform fee deduction (informational — platform keeps it).
            if ($split['platform_fee'] > 0) {
                $this->record(
                    $driver,
                    'debit',
                    $split['platform_fee'],
                    'platform_commission',
                    "Platform fee {$split['platform_rate']}% on {$split['fare']} ៛",
                    $reference,
                    null,
                    'completed',
                );
            }

            // Record company share (rental/employee).
            if ($split['company_share'] > 0) {
                $this->record(
                    $driver,
                    'debit',
                    $split['company_share'],
                    'company_commission',
                    "Company share on {$split['fare']} ៛",
                    $reference,
                    null,
                    'completed',
                );
            }
        });
    }

    /**
     * Approve a top-up request: credit wallet + mark request approved.
     */
    public function approveTopUp(TopUpRequest $request, User $admin): WalletTransaction
    {
        return DB::transaction(function () use ($request, $admin) {
            $tx = $this->credit(
                $request->user,
                $request->amount,
                'top_up',
                "Top-up via {$request->method}",
                null,
                $admin->id,
            );

            $request->update([
                'status'      => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            return $tx;
        });
    }

    /**
     * Credit salary to an employee driver's wallet.
     */
    public function paySalary(User $driver, int $amount, User $admin, string $note = ''): WalletTransaction
    {
        return $this->credit($driver, $amount, 'salary', $note ?: "Monthly salary", null, $admin->id);
    }

    /**
     * Request a withdrawal (status=pending until admin processes payout).
     */
    public function requestWithdrawal(User $user, int $amount, string $note = ''): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $note) {
            return $this->debit($user, $amount, 'withdrawal', $note ?: "Withdrawal request", null, null, 'pending');
        });
    }

    // ── Internal ────────────────────────────────────────────────────────────

    private function record(
        User    $user,
        string  $direction,
        int     $amount,
        string  $type,
        string  $note,
        ?Model  $reference,
        ?int    $actorId,
        string  $status = 'completed',
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $direction, $amount, $type, $note, $reference, $actorId, $status) {
            // Lock the user row to prevent race conditions.
            $user = User::lockForUpdate()->find($user->id);

            $balanceBefore = $user->wallet_balance;
            $balanceAfter  = $direction === 'credit'
                ? $balanceBefore + $amount
                : $balanceBefore - $amount;

            $user->update(['wallet_balance' => $balanceAfter]);

            return WalletTransaction::create([
                'user_id'        => $user->id,
                'direction'      => $direction,
                'type'           => $type,
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'reference_id'   => $reference?->getKey(),
                'reference_type' => $reference ? get_class($reference) : null,
                'status'         => $status,
                'note'           => $note,
                'created_by'     => $actorId,
            ]);
        });
    }
}
