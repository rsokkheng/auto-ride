<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\PartnerContract;
use App\Models\PartnerProfile;
use App\Models\PricingSetting;
use App\Models\User;
use App\Rules\CambodianPhone;
use App\Services\DeliveryOfferService;
use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Partner Package Delivery, inside the Passenger app.
 *
 * Any passenger can register as a Partner; once ROTEH approves, the same
 * account can create packages for ROTEH to pick up and deliver, track them
 * and see their history — and still book rides like everyone else.
 *
 * Workflow: pending (created) → confirmed (ROTEH; offered to drivers) →
 * driver_assigned → picked_up → in_transit → delivered | cancelled.
 */
class PartnerPackageController extends ApiController
{
    public const PACKAGE_TYPES = ['document', 'parcel', 'food', 'clothing', 'electronics', 'fragile', 'other'];

    // ── Registration ──────────────────────────────────────────────────────────

    /** GET /v1/partner/profile — registration state for the dashboard. */
    public function profile(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        return $this->success($this->profilePayload($user));
    }

    /**
     * POST /v1/partner/register — new registration, or re-submit after a
     * rejection. ROTEH reviews it in the admin panel.
     */
    public function register(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();
        if (in_array($user->role, ['driver', 'admin'], true)) {
            return response()->json(['success' => false, 'message' => 'This account cannot register as a Partner.'], 403);
        }

        $existing = $user->partnerProfile;
        if ($existing && in_array($existing->status, ['pending', 'approved'], true)) {
            return $this->success($this->profilePayload($user));
        }
        if ($existing?->status === 'suspended') {
            return response()->json(['success' => false, 'message' => 'Your Partner account is suspended. Please contact ROTEH support.'], 403);
        }

        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:50',
            'contact_name'  => 'nullable|string|max:255',
            'phone'         => ['required', 'string', 'max:24', new CambodianPhone],
            'address'       => 'required|string|max:500',
            'latitude'      => 'nullable|numeric|between:-90,90',
            'longitude'     => 'nullable|numeric|between:-180,180',
        ]);

        PartnerProfile::updateOrCreate(['user_id' => $user->id], $data + [
            'status' => 'pending', 'rejection_reason' => null, 'reviewed_by' => null, 'reviewed_at' => null,
        ]);

        return $this->success($this->profilePayload($user->fresh()), 201);
    }

    // ── Packages ──────────────────────────────────────────────────────────────

    /** GET /v1/partner/packages/quote?package_size=&service_option= */
    public function quote(Request $request)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;

        $data = $request->validate([
            'package_size'   => 'nullable|in:small,medium,large,extra_large',
            'service_option' => 'nullable|in:normal,express',
        ]);

        return $this->success([
            'fee'            => PartnerContract::feeFor($user, $data['service_option'] ?? 'normal', $data['package_size'] ?? 'small'),
            'package_size'   => $data['package_size'] ?? 'small',
            'service_option' => $data['service_option'] ?? 'normal',
        ]);
    }

    /** POST /v1/partner/packages (multipart when a photo is attached) */
    public function store(Request $request)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;

        $data = $request->validate([
            'sender_name'       => 'required|string|max:255',
            'sender_phone'      => ['required', 'string', 'max:24', new CambodianPhone],
            'recipient_name'    => 'required|string|max:255',
            'recipient_phone'   => ['required', 'string', 'max:24', new CambodianPhone],
            'pickup_address'    => 'required|string|max:255',
            'pickup_lat'        => 'required|numeric|between:-90,90',
            'pickup_lng'        => 'required|numeric|between:-180,180',
            'dropoff_address'   => 'required|string|max:255',
            'dropoff_lat'       => 'required|numeric|between:-90,90',
            'dropoff_lng'       => 'required|numeric|between:-180,180',
            'package_type'      => 'required|in:' . implode(',', self::PACKAGE_TYPES),
            'package_details'   => 'required|string|max:500',
            'package_weight'    => 'nullable|numeric|min:0|max:500',
            'package_size'      => 'required|in:small,medium,large,extra_large',
            'service_option'    => 'nullable|in:normal,express',
            'package_amount'    => 'nullable|integer|min:0|max:100000000', // COD item price collected from the recipient
            'payment_by'        => 'nullable|in:sender,recipient',
            'payment_method'    => 'nullable|in:cash,wallet,aba,acleda',
            'partner_reference' => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:500',
            'photo'             => 'nullable|image|max:8192',
        ]);

        $option    = $data['service_option'] ?? 'normal';
        $paymentBy = $data['payment_by'] ?? 'sender';
        // The recipient can only pay the driver in cash.
        $method    = $paymentBy === 'recipient' ? 'cash' : ($data['payment_method'] ?? 'cash');
        // Fee is always the server's price — never the app's.
        $fee       = PartnerContract::feeFor($user, $option, $data['package_size']);

        if ($method === 'wallet' && (int) $user->wallet_balance < $fee) {
            return response()->json([
                'success' => false, 'code' => 'insufficient_balance',
                'message' => 'Your ROTEH Pay balance is not enough for this delivery fee.',
            ], 422);
        }

        $photo = $request->hasFile('photo') ? $request->file('photo')->store('partner-packages', 'public') : null;
        $autoConfirm = (bool) PricingSetting::get('partner_packages_auto_confirm', false);

        $delivery = Delivery::create([
            'partner_id'        => $user->id,
            'sender_id'         => $user->id,
            'service_type'      => 'delivery',
            'sender_name'       => $data['sender_name'],
            'sender_phone'      => $data['sender_phone'],
            'recipient_name'    => $data['recipient_name'],
            'recipient_phone'   => $data['recipient_phone'],
            'pickup_address'    => $data['pickup_address'],
            'pickup_lat'        => $data['pickup_lat'],
            'pickup_lng'        => $data['pickup_lng'],
            'dropoff_address'   => $data['dropoff_address'],
            'dropoff_lat'       => $data['dropoff_lat'],
            'dropoff_lng'       => $data['dropoff_lng'],
            'package_type'      => $data['package_type'],
            'package_details'   => $data['package_details'],
            'package_weight'    => $data['package_weight'] ?? null,
            'package_size'      => $data['package_size'],
            'package_photo'     => $photo,
            'service_option'    => $option,
            'fee'               => $fee,
            'package_amount'    => (int) ($data['package_amount'] ?? 0),
            'payment_by'        => $paymentBy,
            'payment_method'    => $method,
            'payment_status'    => 'unpaid',
            'partner_reference' => $data['partner_reference'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'status'            => 'created',
            'qr_token'          => Str::random(32),
            'share_token'       => Str::random(32),
            'share_active'      => true,
            'surge_multiplier'  => 1.0,
        ]);

        if ($autoConfirm) $this->confirmAndOffer($delivery);

        return $this->success(['package' => $this->format($delivery->fresh(['driver', 'trackingHistories']), true)], 201);
    }

    /** GET /v1/partner/packages?group=active|history|all&status=&search= */
    public function index(Request $request)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;

        $group   = $request->query('group', 'all');
        $status  = $request->query('status');
        $search  = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $finished = ['delivered', 'completed', 'cancelled'];
        $query = Delivery::with('driver:id,name,phone,avatar,rating')
            ->where('partner_id', $user->id)
            ->when($group === 'active', fn ($q) => $q->whereNotIn('status', $finished))
            ->when($group === 'history', fn ($q) => $q->whereIn('status', $finished))
            ->when($status && in_array($status, Delivery::PACKAGE_STATUSES, true),
                fn ($q) => $q->whereIn('status', $this->rawStatusesFor($status)))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('recipient_name', 'like', "%{$search}%")
                ->orWhere('recipient_phone', 'like', "%{$search}%")
                ->orWhere('partner_reference', 'like', "%{$search}%")
                ->orWhere('id', ltrim($search, '#'))))
            ->orderByDesc('id');

        $page = $query->paginate($perPage);

        return $this->success([
            'packages'   => collect($page->items())->map(fn ($d) => $this->format($d))->all(),
            'pagination' => [
                'total'        => $page->total(),
                'per_page'     => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    /** GET /v1/partner/packages/summary — counts for the dashboard section. */
    public function summary(Request $request)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;

        $rows = Delivery::where('partner_id', $user->id)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $counts = array_fill_keys(Delivery::PACKAGE_STATUSES, 0);
        foreach ($rows as $raw => $n) {
            $key = Delivery::packageStatusFor((string) $raw);
            if (isset($counts[$key])) $counts[$key] += (int) $n;
        }

        $base = fn () => Delivery::where('partner_id', $user->id);

        return $this->success([
            'counts'      => $counts,
            'active'      => $counts['pending'] + $counts['confirmed'] + $counts['driver_assigned'] + $counts['picked_up'] + $counts['in_transit'],
            'today'       => $base()->whereDate('created_at', today())->count(),
            'month'       => $base()->where('created_at', '>=', now()->startOfMonth())->count(),
            'delivered_month' => $base()->whereIn('status', ['delivered', 'completed'])
                ->where('created_at', '>=', now()->startOfMonth())->count(),
            'cod_pending' => (int) $base()->where('package_amount', '>', 0)
                ->whereNotIn('status', ['delivered', 'completed', 'cancelled'])->sum('package_amount'),
        ]);
    }

    /** GET /v1/partner/packages/{delivery} — detail with timeline. */
    public function show(Request $request, Delivery $delivery)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;
        if ($delivery->partner_id !== $user->id) return $this->unauthorized();

        $delivery->load(['driver:id,name,phone,avatar,rating,current_latitude,current_longitude', 'trackingHistories']);

        return $this->success(['package' => $this->format($delivery, true)]);
    }

    /** POST /v1/partner/packages/{delivery}/cancel — until the driver picks it up. */
    public function cancel(Request $request, Delivery $delivery)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;
        if ($delivery->partner_id !== $user->id) return $this->unauthorized();

        if (! in_array($delivery->status, Delivery::PRE_PICKUP_STATUSES, true)) {
            return response()->json([
                'success' => false,
                'message' => $delivery->status === 'cancelled'
                    ? 'This package is already cancelled.'
                    : 'The driver has already picked up this package — it can no longer be cancelled.',
            ], 422);
        }

        $reason = trim((string) $request->input('reason', '')) ?: null;
        $delivery->update([
            'status'              => 'cancelled',
            'cancelled_by'        => 'sender',
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);

        return $this->success(['package' => $this->format($delivery->fresh(['driver', 'trackingHistories']), true)]);
    }

    // ── Shared with the admin panel ───────────────────────────────────────────

    /** ROTEH confirms a pending package and offers it to nearby drivers. */
    public static function confirmAndOffer(Delivery $delivery): void
    {
        if ($delivery->status !== 'created') return;
        $delivery->update(['status' => 'requested', 'confirmed_at' => now()]);

        try {
            app(FirestoreService::class)->syncDelivery($delivery->fresh(['sender', 'driver', 'vehicle']));
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            app(DeliveryOfferService::class)->offer($delivery);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function denyUnlessPartner(?User $user): ?JsonResponse
    {
        if (! $user) return $this->unauthorized();
        if ($user->isApprovedPartner()) return null;

        return response()->json([
            'success' => false,
            'code'    => 'not_partner',
            'message' => 'Only approved Partners can send packages.',
            'partner_status' => $user->partnerProfile?->status,
        ], 403);
    }

    /** Raw deliveries.status values behind one Partner package status. */
    private function rawStatusesFor(string $packageStatus): array
    {
        return [
            'pending'         => ['created'],
            'confirmed'       => ['requested', 'pending'],
            'driver_assigned' => ['assigned', 'accepted'],
            'picked_up'       => ['picked_up'],
            'in_transit'      => ['in_progress', 'in_transit'],
            'delivered'       => ['delivered', 'completed'],
            'cancelled'       => ['cancelled'],
        ][$packageStatus] ?? [$packageStatus];
    }

    private function profilePayload(User $user): array
    {
        $p = $user->partnerProfile;
        return [
            'is_partner' => $user->isApprovedPartner(),
            'status'     => $p?->status ?? ($user->role === 'partner' ? 'approved' : 'none'),
            'profile'    => $p ? [
                'business_name'    => $p->business_name,
                'business_type'    => $p->business_type,
                'contact_name'     => $p->contact_name,
                'phone'            => $p->phone,
                'address'          => $p->address,
                'latitude'         => $p->latitude,
                'longitude'        => $p->longitude,
                'rejection_reason' => $p->rejection_reason,
                'reviewed_at'      => $p->reviewed_at?->toIso8601String(),
                'created_at'       => $p->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function format(Delivery $d, bool $detail = false): array
    {
        $out = [
            'id'                => $d->id,
            'code'              => 'PKG-' . str_pad((string) $d->id, 6, '0', STR_PAD_LEFT),
            'status'            => $d->packageStatus(),
            'raw_status'        => $d->status,
            'partner_reference' => $d->partner_reference,
            'sender_name'       => $d->sender_name,
            'sender_phone'      => $d->sender_phone,
            'recipient_name'    => $d->recipient_name,
            'recipient_phone'   => $d->recipient_phone,
            'pickup_address'    => $d->pickup_address,
            'dropoff_address'   => $d->dropoff_address,
            'package_type'      => $d->package_type,
            'package_details'   => $d->package_details,
            'package_weight'    => $d->package_weight,
            'package_size'      => $d->package_size,
            'service_option'    => $d->service_option ?? 'normal',
            'fee'               => (int) $d->fee,
            'package_amount'    => (int) $d->package_amount,
            'payment_by'        => $d->payment_by,
            'payment_method'    => $d->payment_method,
            'payment_status'    => $d->payment_status,
            'driver'            => $d->driver ? [
                'id'     => $d->driver->id,
                'name'   => $d->driver->name,
                'phone'  => $d->driver->phone,
                'rating' => $d->driver->rating,
                'avatar_url' => $d->driver->avatar_url ?? null,
            ] : null,
            'created_at'        => $d->created_at?->toIso8601String(),
            'updated_at'        => $d->updated_at?->toIso8601String(),
        ];

        if (! $detail) return $out;

        return $out + [
            'pickup_lat'      => $d->pickup_lat,
            'pickup_lng'      => $d->pickup_lng,
            'dropoff_lat'     => $d->dropoff_lat,
            'dropoff_lng'     => $d->dropoff_lng,
            'notes'           => $d->notes,
            'photo_url'       => $d->package_photo ? Storage::disk('public')->url($d->package_photo) : null,
            'proof_photo_url' => $d->proof_photo ? Storage::disk('public')->url($d->proof_photo) : null,
            'qr_payload'      => $d->qr_token ? 'AUTORIDE:DELIVERY:' . $d->qr_token : null,
            'tracking_url'    => $d->tracking_url,
            'cancellation_reason' => $d->cancellation_reason,
            'can_cancel'      => in_array($d->status, Delivery::PRE_PICKUP_STATUSES, true),
            'is_live'         => in_array($d->status, array_merge(['assigned', 'accepted'], Delivery::IN_TRANSIT_STATUSES), true),
            'timeline'        => $d->trackingHistories->map(fn ($h) => [
                'status' => $h->status,
                'note'   => $h->note,
                'at'     => $h->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
