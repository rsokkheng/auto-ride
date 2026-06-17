<?php

namespace App\Http\Controllers\Api;

use App\Models\MembershipTier;
use Illuminate\Http\Request;

class MembershipController extends ApiController
{
    /** GET /v1/membership — current user's tier + all tiers */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $points     = $user->loyalty_points ?? 0;
        $allTiers   = MembershipTier::orderBy('sort_order')->get();
        $current    = MembershipTier::forPoints($points);
        $nextTier   = $allTiers->first(fn ($t) => $t->min_points > $points);

        // Auto-sync tier on user record
        if ($current && $user->membership_tier_id !== $current->id) {
            $user->update(['membership_tier_id' => $current->id]);
        }

        return $this->success([
            'loyalty_points'  => $points,
            'current_tier'    => $current,
            'next_tier'       => $nextTier ? [
                'name'           => $nextTier->name,
                'min_points'     => $nextTier->min_points,
                'points_needed'  => $nextTier->min_points - $points,
            ] : null,
            'tiers'           => $allTiers->map(fn ($t) => array_merge($t->toArray(), [
                'reached' => $points >= $t->min_points,
            ])),
        ]);
    }

    /** GET /v1/membership/tiers — public list of all tiers */
    public function tiers(Request $request)
    {
        $tiers = MembershipTier::orderBy('sort_order')->get();
        return $this->success($tiers);
    }
}
