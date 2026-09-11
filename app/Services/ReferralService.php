<?php

namespace App\Services;

use App\Models\PricingSetting;
use App\Models\Referral;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Referral reward flow:
 *
 *   User A shares referral code -> User B registers with it (Referral row
 *   created with status=pending in AuthController::register) -> User B
 *   completes their first ride -> this service validates the referral and
 *   credits BOTH wallets as real wallet_transactions rows, via WalletService
 *   so balances and transaction history stay in perfect sync.
 */
class ReferralService
{
    public function __construct(private WalletService $wallet) {}

    public function creditFirstRideReward(Ride $ride): void
    {
        $referee = $ride->passenger;
        if (! $referee || ! $referee->referred_by) {
            return;
        }

        // status=pending is the guard against double-crediting — this flips
        // to 'completed' below, so a referee's *next* ride is a no-op here.
        $referral = Referral::where('referee_id', $referee->id)
            ->where('status', 'pending')
            ->first();

        if (! $referral) {
            return;
        }

        $referrer = User::find($referral->referrer_id);
        if (! $referrer) {
            return;
        }

        $referralBonusKhr = (int) PricingSetting::get('referral_bonus_khr', 4000);
        $welcomeBonusKhr  = (int) PricingSetting::get('welcome_bonus_khr', 4000);

        DB::transaction(function () use ($referral, $referrer, $referee, $referralBonusKhr, $welcomeBonusKhr) {
            $this->wallet->credit(
                $referrer,
                $referralBonusKhr,
                'referral_reward',
                "Referral reward — {$referee->name} completed their first ride",
                $referral,
            );

            $this->wallet->credit(
                $referee,
                $welcomeBonusKhr,
                'welcome_bonus',
                'Welcome bonus for joining via referral',
                $referral,
            );

            $referral->update([
                'status'       => 'completed',
                'bonus_khr'    => $referralBonusKhr,
                'completed_at' => now(),
            ]);
        });
    }
}
