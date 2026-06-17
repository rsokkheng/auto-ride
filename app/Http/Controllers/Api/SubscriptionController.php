<?php

namespace App\Http\Controllers\Api;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\UserSubscription;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends ApiController
{
    // ── List all available plans ──────────────────────────────────────────────

    public function plans(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::active()->get()->map(fn ($p) => $this->formatPlan($p));

        return response()->json(['data' => $plans]);
    }

    // ── My current subscription ───────────────────────────────────────────────

    public function mySubscription(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $sub = UserSubscription::with('plan')
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'cancelled'])
            ->latest()
            ->first();

        if (! $sub) {
            return response()->json(['data' => null, 'message' => 'No active subscription.']);
        }

        return response()->json(['data' => $this->formatSubscription($sub)]);
    }

    // ── Subscribe to a plan ───────────────────────────────────────────────────

    public function subscribe(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'plan_slug'      => 'required|string|exists:subscription_plans,slug',
            'payment_method' => 'required|in:wallet,card,qr',
            'auto_renew'     => 'boolean',
        ]);

        // Check no active subscription already
        $existing = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have an active subscription. Cancel it first or wait until it expires.',
                'current_plan' => $existing->plan->name,
            ], 422);
        }

        $plan = SubscriptionPlan::where('slug', $data['plan_slug'])->where('active', true)->first();
        if (! $plan) {
            return response()->json(['message' => 'Plan not found or inactive.'], 404);
        }

        // Deduct from wallet
        if ($data['payment_method'] === 'wallet') {
            if (($user->wallet_balance ?? 0) < $plan->price_khr) {
                return response()->json([
                    'message'       => 'Insufficient wallet balance.',
                    'required_khr'  => $plan->price_khr,
                    'balance_khr'   => $user->wallet_balance ?? 0,
                ], 422);
            }

            app(WalletService::class)->debit(
                $user,
                $plan->price_khr,
                'subscription',
                "Subscribed to {$plan->name} plan"
            );
        }

        $expiresAt = match ($plan->billing_cycle) {
            'weekly'  => now()->addWeek(),
            'yearly'  => now()->addYear(),
            default   => now()->addMonth(),
        };

        $sub = UserSubscription::create([
            'user_id'              => $user->id,
            'subscription_plan_id' => $plan->id,
            'status'               => 'active',
            'payment_method'       => $data['payment_method'],
            'started_at'           => now(),
            'expires_at'           => $expiresAt,
            'auto_renew'           => $data['auto_renew'] ?? true,
        ]);

        SubscriptionTransaction::create([
            'user_subscription_id' => $sub->id,
            'user_id'              => $user->id,
            'subscription_plan_id' => $plan->id,
            'amount_khr'           => $plan->price_khr,
            'type'                 => 'subscribe',
            'status'               => 'paid',
            'payment_method'       => $data['payment_method'],
            'reference'            => 'SUB-' . strtoupper(uniqid()),
            'paid_at'              => now(),
        ]);

        return response()->json([
            'data'    => $this->formatSubscription($sub->load('plan')),
            'message' => "Subscribed to {$plan->name} plan. Enjoy your benefits!",
        ], 201);
    }

    // ── Cancel subscription ───────────────────────────────────────────────────

    public function cancel(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $sub = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $sub) {
            return response()->json(['message' => 'No active subscription to cancel.'], 404);
        }

        $sub->update([
            'status'       => 'cancelled',
            'auto_renew'   => false,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message'    => 'Subscription cancelled. Benefits continue until ' . $sub->expires_at?->format('d M Y') . '.',
            'expires_at' => $sub->expires_at,
        ]);
    }

    // ── Toggle auto-renew ─────────────────────────────────────────────────────

    public function toggleAutoRenew(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate(['auto_renew' => 'required|boolean']);

        $sub = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $sub) {
            return response()->json(['message' => 'No active subscription.'], 404);
        }

        $sub->update(['auto_renew' => $data['auto_renew']]);

        return response()->json([
            'message'    => 'Auto-renew ' . ($data['auto_renew'] ? 'enabled' : 'disabled') . '.',
            'auto_renew' => $sub->auto_renew,
        ]);
    }

    // ── Upgrade / switch plan ─────────────────────────────────────────────────

    public function upgrade(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'plan_slug'      => 'required|string|exists:subscription_plans,slug',
            'payment_method' => 'required|in:wallet,card,qr',
        ]);

        $current = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $newPlan = SubscriptionPlan::where('slug', $data['plan_slug'])->where('active', true)->firstOrFail();

        if ($current && $current->plan->slug === $newPlan->slug) {
            return response()->json(['message' => 'Already on this plan.'], 422);
        }

        // Cancel current if exists
        if ($current) {
            $current->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);
        }

        // Charge for new plan
        if ($data['payment_method'] === 'wallet') {
            if (($user->wallet_balance ?? 0) < $newPlan->price_khr) {
                return response()->json([
                    'message'      => 'Insufficient wallet balance.',
                    'required_khr' => $newPlan->price_khr,
                    'balance_khr'  => $user->wallet_balance ?? 0,
                ], 422);
            }

            app(WalletService::class)->debit(
                $user,
                $newPlan->price_khr,
                'subscription',
                "Upgraded to {$newPlan->name} plan"
            );
        }

        $expiresAt = match ($newPlan->billing_cycle) {
            'weekly' => now()->addWeek(),
            'yearly' => now()->addYear(),
            default  => now()->addMonth(),
        };

        $sub = UserSubscription::create([
            'user_id'              => $user->id,
            'subscription_plan_id' => $newPlan->id,
            'status'               => 'active',
            'payment_method'       => $data['payment_method'],
            'started_at'           => now(),
            'expires_at'           => $expiresAt,
            'auto_renew'           => true,
        ]);

        SubscriptionTransaction::create([
            'user_subscription_id' => $sub->id,
            'user_id'              => $user->id,
            'subscription_plan_id' => $newPlan->id,
            'amount_khr'           => $newPlan->price_khr,
            'type'                 => 'subscribe',
            'status'               => 'paid',
            'payment_method'       => $data['payment_method'],
            'reference'            => 'SUB-' . strtoupper(uniqid()),
            'paid_at'              => now(),
        ]);

        return response()->json([
            'data'    => $this->formatSubscription($sub->load('plan')),
            'message' => "Switched to {$newPlan->name} plan.",
        ]);
    }

    // ── Payment / billing history ─────────────────────────────────────────────

    public function history(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $transactions = SubscriptionTransaction::with('plan:id,name,slug,badge_color,icon')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $transactions]);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatPlan(SubscriptionPlan $p): array
    {
        return [
            'id'                    => $p->id,
            'name'                  => $p->name,
            'slug'                  => $p->slug,
            'description'           => $p->description,
            'price_khr'             => $p->price_khr,
            'billing_cycle'         => $p->billing_cycle,
            'ride_credit_khr'       => $p->ride_credit_khr,
            'ride_discount_pct'     => $p->ride_discount_pct,
            'delivery_discount_pct' => $p->delivery_discount_pct,
            'free_cancellations'    => $p->free_cancellations === 0 ? 'Unlimited' : $p->free_cancellations,
            'surge_waived'          => $p->surge_waived,
            'priority_matching'     => $p->priority_matching,
            'bonus_points_pct'      => $p->bonus_points_pct,
            'features'              => $p->features ?? [],
            'badge_color'           => $p->badge_color,
            'icon'                  => $p->icon,
        ];
    }

    private function formatSubscription(UserSubscription $sub): array
    {
        return [
            'id'                     => $sub->id,
            'plan'                   => $this->formatPlan($sub->plan),
            'status'                 => $sub->status,
            'is_active'              => $sub->isActive(),
            'payment_method'         => $sub->payment_method,
            'auto_renew'             => $sub->auto_renew,
            'started_at'             => $sub->started_at,
            'expires_at'             => $sub->expires_at,
            'expires_in_days'        => $sub->expiresInDays(),
            'cancelled_at'           => $sub->cancelled_at,
            'used_ride_credit_khr'   => $sub->used_ride_credit_khr,
            'remaining_credit_khr'   => $sub->remainingCreditKhr(),
            'used_cancellations'     => $sub->used_cancellations,
            'remaining_cancellations'=> $sub->remainingCancellations(),
            'renewal_count'          => $sub->renewal_count,
        ];
    }
}
