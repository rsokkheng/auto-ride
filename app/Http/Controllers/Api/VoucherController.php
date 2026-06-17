<?php

namespace App\Http\Controllers\Api;

use App\Models\Voucher;
use App\Models\UserVoucher;
use Illuminate\Http\Request;

class VoucherController extends ApiController
{
    /** GET /v1/vouchers — list available vouchers */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $vouchers = Voucher::where('active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($v) => $v->isAvailableTo($user->id))
            ->values();

        return $this->success($vouchers);
    }

    /** POST /v1/vouchers/{voucher}/claim */
    public function claim(Request $request, Voucher $voucher)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        if (! $voucher->isAvailableTo($user->id)) {
            return response()->json(['data' => null, 'message' => 'Voucher is not available.'], 422);
        }

        if ($voucher->points_required > 0) {
            if (($user->loyalty_points ?? 0) < $voucher->points_required) {
                return response()->json(['data' => null, 'message' => 'Insufficient loyalty points.'], 422);
            }
            $user->decrement('loyalty_points', $voucher->points_required);
        }

        $uv = UserVoucher::create([
            'user_id'    => $user->id,
            'voucher_id' => $voucher->id,
            'status'     => 'active',
            'claimed_at' => now(),
        ]);

        return $this->success($uv->load('voucher'), 'Voucher claimed successfully.');
    }

    /** GET /v1/vouchers/mine */
    public function mine(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        // Auto-expire vouchers past valid_until
        UserVoucher::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('voucher', fn ($q) => $q->whereNotNull('valid_until')->where('valid_until', '<', now()))
            ->update(['status' => 'expired']);

        $vouchers = UserVoucher::with('voucher')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return $this->success($vouchers);
    }

    /** POST /v1/vouchers/apply — validate at checkout, don't consume */
    public function apply(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'user_voucher_id' => 'required|integer',
            'fare'            => 'required|integer|min:0',
            'category'        => 'required|in:rides,deliveries',
        ]);

        $uv = UserVoucher::with('voucher')
            ->where('id', $data['user_voucher_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $uv || $uv->status !== 'active') {
            return response()->json(['data' => null, 'message' => 'Voucher not found or already used.'], 422);
        }

        $voucher = $uv->voucher;
        if (! in_array($voucher->category, [$data['category'], 'all'], true)) {
            return response()->json(['data' => null, 'message' => 'Voucher not valid for this service.'], 422);
        }

        $discount = $voucher->calculateDiscount($data['fare']);

        return $this->success([
            'user_voucher_id' => $uv->id,
            'discount'        => $discount,
            'final_fare'      => max(0, $data['fare'] - $discount),
        ]);
    }
}
