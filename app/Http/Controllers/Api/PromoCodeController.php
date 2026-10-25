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

        $promos = PromoCode::where('active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->orderByDesc('created_at')
            ->get(['id', 'code', 'description', 'description_en', 'description_kh', 'description_zh', 'type', 'value', 'min_order', 'max_discount', 'expires_at', 'service_type']);

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

    /**
     * POST /v1/promo/apply — order_amount is in riel. Guests (no bearer token)
     * may check product purchase/rent coupons; pass guest_phone so the
     * per-person limit is checked the same way checkout will.
     */
    public function check(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user && $request->bearerToken()) return $this->unauthorized();

        $data = $request->validate([
            'code'         => 'required|string',
            'service_type' => 'required|in:' . implode(',', PromoCode::contextNames()),
            'order_amount' => 'required|integer|min:0',
            'guest_phone'  => 'nullable|string|max:20',
        ]);

        $context = PromoCode::normalizeContext($data['service_type']);
        if (! $user && ! in_array($context, PromoCode::GUEST_CONTEXTS, true)) return $this->unauthorized();

        $promo = PromoCode::where('code', strtoupper(trim($data['code'])))->first();

        if (! $promo || ! $promo->isValid($context, $data['order_amount'], $user?->id, $data['guest_phone'] ?? null)) {
            return response()->json(['data' => null, 'message' => 'Invalid or expired promo code.'], 422);
        }

        $discount = $promo->calculateDiscount($data['order_amount']);

        return $this->success([
            'valid'            => true,
            'promo_code_id'    => $promo->id,
            'code'             => $promo->code,
            'description'      => $promo->description,
            'type'             => $promo->type,
            'value'            => $promo->value,
            'discount_percent' => $promo->type === 'percent' ? $promo->value : null,
            'discount_amount'  => $discount,
            'final_amount'     => max(0, $data['order_amount'] - $discount),
            'share_url'        => url('/promo/' . $promo->code),
        ]);
    }
}
