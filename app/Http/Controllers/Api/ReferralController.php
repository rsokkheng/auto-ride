<?php

namespace App\Http\Controllers\Api;

use App\Models\PricingSetting;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReferralController extends ApiController
{
    /**
     * GET /v1/referrals
     * Returns the user's referral code, stats, and list of referred users.
     * Auto-generates a referral code if the user doesn't have one.
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        // Auto-generate referral code on first visit
        if (! $user->referral_code) {
            $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($user->name)), 0, 4))
                  . strtoupper(Str::random(4));
            // Ensure uniqueness
            while (\App\Models\User::where('referral_code', $code)->exists()) {
                $code = strtoupper(Str::random(8));
            }
            $user->update(['referral_code' => $code]);
        }

        $referralBonusKhr = (int) PricingSetting::get('referral_bonus_khr', 4000);

        $referrals = Referral::where('referrer_id', $user->id)
            ->with('referee:id,name,avatar,created_at')
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'             => $r->id,
                'name'           => $r->referee?->name,
                'avatar_url'     => $r->referee?->avatar_url,
                'status'         => $r->status,
                'bonus_khr'      => $r->bonus_khr,
                // Kept for older app builds still reading this key; same value as bonus_khr.
                'points_awarded' => $r->bonus_khr,
                'joined_at'      => $r->referee?->created_at?->toDateString(),
                'created_at'     => $r->referee?->created_at?->toDateString(),
                'completed_at'   => $r->completed_at?->toDateString(),
            ]);

        $totalBonus     = Referral::where('referrer_id', $user->id)->where('status', 'completed')->sum('bonus_khr');
        $completedCount = Referral::where('referrer_id', $user->id)->where('status', 'completed')->count();
        $pendingCount   = Referral::where('referrer_id', $user->id)->where('status', 'pending')->count();

        return $this->success([
            'referral_code'   => $user->referral_code,
            // Aliases for the current app build, which reads these top-level names.
            'code'            => $user->referral_code,
            'referred_count'  => $referrals->count(),
            'points_earned'   => (int) $totalBonus,
            'share_message'   => "Join Auto-Ride with my code {$user->referral_code} and get a discount on your first trip!",
            'bonus_per_referral_khr' => $referralBonusKhr,
            'stats' => [
                'total_referrals'  => $referrals->count(),
                'completed'        => $completedCount,
                'pending'          => $pendingCount,
                'total_earned_khr' => (int) $totalBonus,
            ],
            'referrals' => $referrals,
        ]);
    }
}
