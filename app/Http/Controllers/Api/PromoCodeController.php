<?php

namespace App\Http\Controllers\Api;

use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoCodeController extends ApiController
{
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
