<?php

namespace App\Http\Controllers\Api;

use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoCodeController extends ApiController
{
    /**
     * GET /v1/promos/active
     * Returns currently active & public promo codes the user can apply.
     */
    public function active(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $promos = PromoCode::where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'))
            ->orderByDesc('created_at')
            ->get(['id', 'code', 'description', 'type', 'value', 'min_order_amount', 'max_discount', 'expires_at', 'service_type']);

        return $this->success(['promos' => $promos]);
    }

    /**
     * GET /v1/rewards/balance
     * Placeholder — returns points based on completed trip count (10 pts per trip).
     */
    public function rewardsBalance(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $tripCount = \App\Models\Ride::where('passenger_id', $user->id)->where('status', 'completed')->count()
            + \App\Models\Delivery::where('sender_id', $user->id)->where('status', 'completed')->count();

        $points = $tripCount * 10;

        $tier = match (true) {
            $points >= 5000 => 'Platinum',
            $points >= 2000 => 'Gold',
            $points >= 500  => 'Silver',
            default         => 'Bronze',
        };

        return $this->success([
            'points' => $points,
            'tier'   => $tier,
        ]);
    }

    public function check(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'code'         => 'required|string',
            'service_type' => 'required|in:rides,deliveries,moving',
            'order_amount' => 'required|integer|min:0',
        ]);

        $promo = PromoCode::where('code', strtoupper(trim($data['code'])))->first();

        if (! $promo || ! $promo->isValid($data['service_type'], $data['order_amount'], $user->id)) {
            return response()->json(['data' => null, 'message' => 'Invalid or expired promo code.'], 422);
        }

        $discount = $promo->calculateDiscount($data['order_amount']);

        return $this->success([
            'promo_code_id'   => $promo->id,
            'code'            => $promo->code,
            'description'     => $promo->description,
            'type'            => $promo->type,
            'value'           => $promo->value,
            'discount_amount' => $discount,
            'final_amount'    => max(0, $data['order_amount'] - $discount),
        ]);
    }
}
