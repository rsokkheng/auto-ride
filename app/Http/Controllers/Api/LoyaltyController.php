<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\LoyaltyRedemption;
use App\Models\Ride;
use App\Services\WalletService;
use Illuminate\Http\Request;

class LoyaltyController extends ApiController
{
    // Points earned per completed trip
    const POINTS_PER_TRIP = 10;

    // Redemption rate: points → KHR
    const KHR_PER_POINT = 100; // 100 pts = 10,000 KHR

    // Minimum points to redeem at once
    const MIN_REDEEM_POINTS = 100;

    private WalletService $wallet;

    public function __construct(WalletService $wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * GET /v1/loyalty
     * Returns full loyalty screen data: points, tier, progress, benefits, history.
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $earnedPoints  = $this->calcEarnedPoints($user->id);
        $redeemedPoints = LoyaltyRedemption::where('user_id', $user->id)->sum('points_redeemed');
        $balance        = max(0, $earnedPoints - $redeemedPoints);

        [$tier, $nextTier, $tierMin, $nextMin] = $this->resolveTier($earnedPoints);

        $progress = $nextMin > 0
            ? min(100, (int) round(($earnedPoints - $tierMin) / ($nextMin - $tierMin) * 100))
            : 100;

        $history = LoyaltyRedemption::where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get(['id', 'points_redeemed', 'credit_amount_khr', 'description', 'created_at']);

        return $this->success([
            'points_balance'  => $balance,
            'points_earned'   => $earnedPoints,
            'points_redeemed' => (int) $redeemedPoints,
            'tier'            => $tier,
            'next_tier'       => $nextTier,
            'tier_progress'   => $progress,
            'points_to_next'  => $nextMin > 0 ? max(0, $nextMin - $earnedPoints) : 0,
            'redeem_rate'     => ['points' => 100, 'khr' => self::KHR_PER_POINT * 100],
            'min_redeem'      => self::MIN_REDEEM_POINTS,
            'benefits'        => $this->tierBenefits($tier),
            'redemption_history' => $history,
        ]);
    }

    /**
     * POST /v1/loyalty/redeem
     * Redeem loyalty points for wallet credit.
     * Body: points (multiple of 100, min 100)
     */
    public function redeem(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'points' => 'required|integer|min:' . self::MIN_REDEEM_POINTS,
        ]);

        $points = (int) $data['points'];

        if ($points % self::MIN_REDEEM_POINTS !== 0) {
            return response()->json([
                'data'    => null,
                'message' => 'Points must be a multiple of ' . self::MIN_REDEEM_POINTS . '.',
            ], 422);
        }

        $earnedPoints   = $this->calcEarnedPoints($user->id);
        $redeemedPoints = (int) LoyaltyRedemption::where('user_id', $user->id)->sum('points_redeemed');
        $balance        = max(0, $earnedPoints - $redeemedPoints);

        if ($points > $balance) {
            return response()->json([
                'data'    => null,
                'message' => "Insufficient points. You have {$balance} points available.",
            ], 422);
        }

        $creditKhr = $points * self::KHR_PER_POINT;

        $redemption = LoyaltyRedemption::create([
            'user_id'           => $user->id,
            'points_redeemed'   => $points,
            'credit_amount_khr' => $creditKhr,
            'description'       => "{$points} points redeemed for " . number_format($creditKhr) . " KHR wallet credit",
        ]);

        $this->wallet->credit($user, $creditKhr, 'loyalty_redemption', "Loyalty: {$points} pts redeemed");

        return $this->success([
            'points_redeemed'   => $points,
            'credit_amount_khr' => $creditKhr,
            'new_balance_khr'   => $user->fresh()->wallet_balance,
            'new_points_balance'=> $balance - $points,
            'message'           => number_format($creditKhr) . " KHR added to your wallet.",
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function calcEarnedPoints(int $userId): int
    {
        $rideTrips = Ride::where('passenger_id', $userId)->where('status', 'completed')->count();
        $delivTrips = Delivery::where('sender_id', $userId)->where('status', 'completed')->count();
        return ($rideTrips + $delivTrips) * self::POINTS_PER_TRIP;
    }

    private function resolveTier(int $points): array
    {
        // [tier, nextTier, tierMin, nextMin]
        if ($points >= 5000) return ['Platinum', null,   5000, 0];
        if ($points >= 2000) return ['Gold',     'Platinum', 2000, 5000];
        if ($points >= 500)  return ['Silver',   'Gold',      500, 2000];
        return ['Bronze', 'Silver', 0, 500];
    }

    private function tierBenefits(string $tier): array
    {
        return match ($tier) {
            'Platinum' => [
                'Priority booking',
                'Free cancellation (up to 10 min)',
                '20% bonus on points earned',
                'Dedicated support line',
            ],
            'Gold' => [
                'Priority booking',
                'Free cancellation (up to 5 min)',
                '10% bonus on points earned',
            ],
            'Silver' => [
                'Early access to promotions',
                '5% bonus on points earned',
            ],
            default => [
                'Earn 10 points per completed trip',
                'Redeem 100 points for 10,000 KHR',
            ],
        };
    }
}
