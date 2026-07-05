<?php

namespace App\Http\Controllers\Api;

use App\Models\TopUpRequest;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // ── Balance only (Flutter-friendly) ─────────────────────────────────────

    public function balance(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $khr = (int) $user->wallet_balance;
        $usd = round($khr / 4000, 2);

        return $this->success([
            'balance_khr' => $khr,
            'balance_usd' => $usd,
            'currency'    => 'KHR',
        ]);
    }

    // ── Full transaction history (paginated) ─────────────────────────────────

    public function transactions(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $perPage = min((int) $request->query('per_page', 10), 100);
        $date    = $request->query('date');   // YYYY-MM-DD  or  "today"
        $type    = $request->query('type');

        $query = $user->walletTransactions();

        if ($date) {
            $day = $date === 'today' ? now()->toDateString() : $date;
            $query->whereDate('created_at', $day);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $paginator = $query->paginate($perPage);

        return $this->success([
            'wallet_balance' => $user->wallet_balance,
            'transactions'   => $paginator->items(),
            'pagination'     => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
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
            'amount'         => 'required|integer|min:1000',
            'method'         => 'nullable|in:cash,online,company_credit,card,qr',
            'payment_method' => 'nullable|in:cash,online,company_credit,card,qr',
            'note'           => 'nullable|string|max:255',
        ]);

        $topup = TopUpRequest::create([
            'user_id' => $user->id,
            'amount'  => $data['amount'],
            'method'  => $data['method'] ?? $data['payment_method'] ?? 'cash',
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

    // ── Transfer / Send to another user ─────────────────────────────────────

    public function transfer(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'phone'  => 'required|string',
            'amount' => 'required|integer|min:1000',
            'note'   => 'nullable|string|max:255',
        ]);

        if ($user->wallet_balance < $data['amount']) {
            return response()->json(['message' => 'Insufficient wallet balance.'], 422);
        }

        $recipient = User::where('phone', $data['phone'])->first();
        if (! $recipient) {
            return response()->json(['message' => 'Recipient phone number not found.'], 422);
        }

        if ($recipient->id === $user->id) {
            return response()->json(['message' => 'Cannot transfer to yourself.'], 422);
        }

        $note = $data['note'] ?? '';

        $this->wallet->debit($user, $data['amount'], 'transfer_out', "To {$recipient->name} ({$recipient->phone})" . ($note ? " — {$note}" : ''));
        $this->wallet->credit($recipient, $data['amount'], 'transfer_in', "From {$user->name} ({$user->phone})" . ($note ? " — {$note}" : ''));

        return $this->success([
            'message'   => "Transferred {$data['amount']} KHR to {$recipient->name}.",
            'balance'   => $user->fresh()->wallet_balance,
            'recipient' => ['name' => $recipient->name, 'phone' => $recipient->phone],
        ]);
    }

    // ── Request withdrawal ───────────────────────────────────────────────────

    /**
     * POST /v1/wallet/withdraw
     * Accepts same body as /v1/driver/withdraw — creates a WithdrawalRequest
     * so it appears in the admin approval queue.
     *
     * Body: amount_khr (or amount), payment_method?, account_number?,
     *       account_name?, bank_name?, note?
     */
    public function requestWithdrawal(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        // Accept both `amount_khr` (driver endpoint) and `amount` (wallet endpoint)
        $amountKhr = (int) ($request->input('amount_khr') ?? $request->input('amount') ?? 0);

        $data = $request->validate([
            'amount_khr'     => 'nullable|integer|min:50000',
            'amount'         => 'nullable|integer|min:50000',
            'payment_method' => 'nullable|in:bank_transfer,aba,wing,acleda',
            'account_number' => 'nullable|string|max:100',
            'account_name'   => 'nullable|string|max:100',
            'bank_name'      => 'nullable|string|max:100',
            'note'           => 'nullable|string|max:255',
        ]);

        if ($amountKhr < 50000) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum withdrawal is 50,000 ៛.',
            ], 422);
        }

        if ($user->wallet_balance < $amountKhr) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance. Available: ' . number_format($user->wallet_balance) . ' ៛.',
            ], 422);
        }

        $hasPending = \App\Models\WithdrawalRequest::where('driver_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending withdrawal request.',
            ], 422);
        }

        $withdrawal = DB::transaction(function () use ($user, $amountKhr, $data) {
            $withdrawal = \App\Models\WithdrawalRequest::create([
                'driver_id'      => $user->id,
                'amount_khr'     => $amountKhr,
                'status'         => 'pending',
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'account_number' => $data['account_number'] ?? null,
                'account_name'   => $data['account_name'] ?? null,
                'bank_name'      => $data['bank_name'] ?? null,
            ]);

            // Hold the amount — status 'pending' so driver sees it as pending until admin approves
            $this->wallet->debit($user, $amountKhr, 'withdrawal_hold', 'Withdrawal request hold', $withdrawal, null, 'pending');

            return $withdrawal;
        });

        return $this->success([
            'withdrawal'     => $withdrawal,
            'wallet_balance' => $user->fresh()->wallet_balance,
            'message'        => number_format($amountKhr) . ' ៛ held. Your withdrawal request has been submitted for admin approval.',
        ]);
    }
}
