<?php

namespace App\Http\Controllers\Api;

use App\Models\PricingSetting;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WithdrawalController extends ApiController
{
    public function __construct(private WalletService $wallet) {}

    /**
     * POST /v1/driver/withdraw
     * Driver requests a wallet withdrawal.
     */
    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $minBalance = (int) PricingSetting::get('driver_min_balance_khr', 50000);

        $data = $request->validate([
            'amount_khr'     => "required|integer|min:{$minBalance}",
            'payment_method' => 'required|in:bank_transfer,aba,wing,acleda',
            'account_number' => 'required|string|max:100',
            'account_name'   => 'required|string|max:100',
            'bank_name'      => 'nullable|string|max:100',
        ]);

        if ($user->wallet_balance < $data['amount_khr']) {
            return response()->json([
                'message' => 'Insufficient wallet balance. Available: ' . number_format($user->wallet_balance) . ' KHR.',
            ], 422);
        }

        // Block if a pending request already exists
        $hasPending = WithdrawalRequest::where('driver_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'You already have a pending withdrawal request.',
            ], 422);
        }

        $withdrawal = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data) {
            $withdrawal = WithdrawalRequest::create([
                'driver_id'      => $user->id,
                'amount_khr'     => $data['amount_khr'],
                'status'         => 'pending',
                'payment_method' => $data['payment_method'],
                'account_number' => $data['account_number'],
                'account_name'   => $data['account_name'],
                'bank_name'      => $data['bank_name'] ?? null,
            ]);

            // Hold the amount — status 'pending' so driver sees it as pending until admin approves
            $this->wallet->debit($user, $data['amount_khr'], 'withdrawal_hold', 'Withdrawal request hold', $withdrawal, null, 'pending');

            return $withdrawal;
        });

        return $this->success([
            'withdrawal'     => $withdrawal,
            'wallet_balance' => $user->fresh()->wallet_balance,
            'message'        => number_format($data['amount_khr']) . ' KHR held. Withdrawal will be processed within 1–2 business days.',
        ]);
    }

    /**
     * GET /v1/driver/withdrawals
     * Driver views their withdrawal history.
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $withdrawals = WithdrawalRequest::where('driver_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return $this->success([
            'withdrawals'    => $withdrawals,
            'wallet_balance' => $user->wallet_balance,
        ]);
    }
}
