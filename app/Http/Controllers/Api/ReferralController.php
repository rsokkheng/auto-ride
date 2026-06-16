<?php

namespace App\Http\Controllers\Api;

use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReferralController extends ApiController
{
    // KHR bonus awarded to referrer when referee completes their first trip
    const REFERRAL_BONUS_KHR = 5000;

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

        $referrals = Referral::where('referrer_id', $user->id)
            ->with('referee:id,name,avatar,created_at')
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'name'         => $r->referee?->name,
                'avatar_url'   => $r->referee?->avatar_url,
                'status'       => $r->status,
                'bonus_khr'    => $r->bonus_khr,
                'joined_at'    => $r->referee?->created_at?->toDateString(),
                'completed_at' => $r->completed_at?->toDateString(),
            ]);

        $totalBonus    = Referral::where('referrer_id', $user->id)->where('status', 'completed')->sum('bonus_khr');
        $completedCount = Referral::where('referrer_id', $user->id)->where('status', 'completed')->count();
        $pendingCount   = Referral::where('referrer_id', $user->id)->where('status', 'pending')->count();

        return $this->success([
            'referral_code'   => $user->referral_code,
            'share_message'   => "Join Auto-Ride with my code {$user->referral_code} and get a discount on your first trip!",
            'bonus_per_referral_khr' => self::REFERRAL_BONUS_KHR,
            'stats' => [
                'total_referrals'   => $referrals->count(),
                'completed'         => $completedCount,
                'pending'           => $pendingCount,
                'total_earned_khr'  => (int) $totalBonus,
            ],
            'referrals' => $referrals,
        ]);
    }
}
