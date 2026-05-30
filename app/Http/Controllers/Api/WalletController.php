<?php

namespace App\Http\Controllers\Api;

use App\Models\TopUpRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function __construct(private WalletService $wallet) {}

    // ── Balance + recent transactions ────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $transactions = $user->walletTransactions()->limit(20)->get();

        return $this->success([
            'balance'      => $user->wallet_balance,
            'currency'     => 'KHR',
            'transactions' => $transactions,
        ]);
    }

    // ── Full transaction history (paginated) ─────────────────────────────────

    public function transactions(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $transactions = $user->walletTransactions()->paginate(30);

        return $this->success(['transactions' => $transactions]);
    }

    // ── Request top-up ───────────────────────────────────────────────────────

    /**
     * POST /v1/wallet/topup
     * Body: amount (KHR), method (cash|online|company_credit), note?
     */
    public function requestTopUp(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'amount' => 'required|integer|min:1000',
            'method' => 'required|in:cash,online,company_credit',
            'note'   => 'nullable|string|max:255',
        ]);

        $topup = TopUpRequest::create([
            'user_id' => $user->id,
            'amount'  => $data['amount'],
            'method'  => $data['method'],
            'note'    => $data['note'] ?? null,
            'status'  => 'pending',
        ]);

        return $this->success(['top_up_request' => $topup], 201);
    }

    // ── Top-up status ────────────────────────────────────────────────────────

    public function topUpStatus(Request $request, TopUpRequest $topup)
    {
        $user = $this->authUser($request);
        if (! $user || $topup->user_id !== $user->id) return $this->unauthorized();

        return $this->success(['top_up_request' => $topup]);
    }

    // ── Request withdrawal ───────────────────────────────────────────────────

    /**
     * POST /v1/wallet/withdraw
     * Body: amount (KHR), note?
     */
    public function requestWithdrawal(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'amount' => 'required|integer|min:1000',
            'note'   => 'nullable|string|max:255',
        ]);

        if ($user->wallet_balance < $data['amount']) {
            return response()->json(['message' => 'Insufficient wallet balance'], 422);
        }

        $tx = $this->wallet->requestWithdrawal($user, $data['amount'], $data['note'] ?? '');

        return $this->success([
            'transaction'   => $tx,
            'balance'       => $user->fresh()->wallet_balance,
        ]);
    }
}
