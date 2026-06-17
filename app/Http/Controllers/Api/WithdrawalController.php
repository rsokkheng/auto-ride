<?php

namespace App\Http\Controllers\Api;

use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WithdrawalController extends ApiController
{
    private const MIN_WITHDRAWAL = 50000;  // 50,000 KHR minimum

    public function __construct(private WalletService $wallet) {}

    /**
     * POST /v1/driver/withdraw
     * Driver requests a wallet withdrawal.
     */
    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $data = $request->validate([
            'amount_khr'     => 'required|integer|min:' . self::MIN_WITHDRAWAL,
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

        // Hold the amount (deduct from wallet immediately, return if rejected)
        $this->wallet->debit($user, $data['amount_khr'], 'withdrawal_hold', 'Withdrawal request hold');

        $withdrawal = WithdrawalRequest::create([
            'driver_id'      => $user->id,
            'amount_khr'     => $data['amount_khr'],
            'status'         => 'pending',
            'payment_method' => $data['payment_method'],
            'account_number' => $data['account_number'],
            'account_name'   => $data['account_name'],
            'bank_name'      => $data['bank_name'] ?? null,
        ]);

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
            ->paginate(20);

        return $this->success([
            'withdrawals'    => $withdrawals,
            'wallet_balance' => $user->wallet_balance,
        ]);
    }
}
