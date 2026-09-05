<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;

class PromoShareController extends Controller
{
    /**
     * GET /promo/{code}
     *
     * Public landing page for a shared promo code link (no auth). Shows the
     * coupon's public-safe details and a "Copy Code" button so the recipient
     * can paste it into the app at checkout.
     */
    public function show(string $code)
    {
        $promo = PromoCode::where('code', strtoupper(trim($code)))->first();

        if (! $promo) {
            return view('promo-share', ['promo' => null, 'status' => 'not_found']);
        }

        $now = now();
        $status = match (true) {
            ! $promo->active                                   => 'inactive',
            $promo->starts_at && $now->lt($promo->starts_at)    => 'not_started',
            $promo->expires_at && $now->gt($promo->expires_at)  => 'expired',
            $promo->usage_limit && $promo->used_count >= $promo->usage_limit => 'exhausted',
            default                                             => 'active',
        };

        return view('promo-share', [
            'promo' => [
                'code'          => $promo->code,
                'description'   => $promo->description,
                'type'          => $promo->type,
                'value'         => $promo->value,
                'min_order'     => $promo->min_order,
                'max_discount'  => $promo->max_discount,
                'service_type'  => $promo->service_type,
                'expires_at'    => $promo->expires_at,
            ],
            'status' => $status,
        ]);
    }
}
