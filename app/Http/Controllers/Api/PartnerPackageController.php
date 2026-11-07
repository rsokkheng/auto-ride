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
 * driver_assigned → picked_up → in_transit → completed
 *   | failed (cannot contact receiver, refused…) → returned (back at the shop)
 *   | cancelled.
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
            // The fee is always charged to the Partner: cash = taken from the
            // COD / paid on the daily statement; or prepaid by ROTEH Pay / ABA.
            'payment_method'    => 'nullable|in:cash,wallet,aba,acleda',
            'partner_reference' => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:500',
            'photo'             => 'nullable|image|max:8192',
        ]);

        $option    = $data['service_option'] ?? 'normal';
        $paymentBy = 'sender';
        $method    = $data['payment_method'] ?? 'cash';
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

        $finished = ['delivered', 'completed', 'cancelled', 'returned'];
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
            'active'      => $counts['pending'] + $counts['confirmed'] + $counts['driver_assigned'] + $counts['picked_up'] + $counts['in_transit'] + $counts['failed'],
            'today'       => $base()->whereDate('created_at', today())->count(),
            'today_completed' => $base()->whereIn('status', ['delivered', 'completed'])->whereDate('completed_at', today())->count(),
            'today_returned'  => $base()->whereIn('status', ['delivery_failed', 'returned'])->whereDate('failed_at', today())->count(),
            'month'       => $base()->where('created_at', '>=', now()->startOfMonth())->count(),
            'completed_month' => $base()->whereIn('status', ['delivered', 'completed'])
                ->where('created_at', '>=', now()->startOfMonth())->count(),
            'cod_pending' => (int) $base()->where('package_amount', '>', 0)
                ->whereNotIn('status', ['delivered', 'completed', 'cancelled', 'returned', 'delivery_failed'])->sum('package_amount'),
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

    /**
     * GET /v1/partner/packages/report?period=day|week|month&date=YYYY-MM-DD
     * Packages created in that day / week (Mon–Sun) / month, Phnom Penh time.
     */
    public function report(Request $request)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;

        $data = $request->validate([
            'period' => 'nullable|in:day,week,month',
            'date'   => 'nullable|date_format:Y-m-d',
        ]);
        [$payload] = $this->buildReport($user, $data['period'] ?? 'day', $data['date'] ?? null);

        return $this->success($payload);
    }

    /**
     * GET /v1/partner/packages/report/export?period=&date=&format=pdf|xlsx
     * The same report as a file the Partner can save or share.
     */
    public function exportReport(Request $request)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;

        $data = $request->validate([
            'period' => 'nullable|in:day,week,month',
            'date'   => 'nullable|date_format:Y-m-d',
            'format' => 'required|in:pdf,xlsx',
        ]);
        [$report, $rows] = $this->buildReport($user, $data['period'] ?? 'day', $data['date'] ?? null, withPackages: true);

        $business = $user->partnerProfile?->business_name ?: ($user->company_name ?: $user->name);
        $name = 'ROTEH_report_' . $report['period'] . '_' . $report['from'] . ($report['from'] !== $report['to'] ? '_' . $report['to'] : '');

        if ($data['format'] === 'xlsx') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\PartnerReportExport($report, $rows, $business), $name . '.xlsx');
        }

        // dompdf is memory hungry: give this request room, and keep the PDF's
        // package table to a printable size (Excel always has every package).
        @ini_set('memory_limit', '512M');
        $pdfLimit = 500;

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('partner.report-pdf', [
                'report' => $report, 'rows' => $rows->take($pdfLimit), 'business' => $business,
                'hiddenRows' => max(0, $rows->count() - $pdfLimit),
                'generatedAt' => now(config('ride.local_timezone', 'Asia/Phnom_Penh')),
            ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false])
            ->download($name . '.pdf');
    }

    /**
     * GET /v1/partner/statements?date=YYYY-MM-DD — daily statement after
     * closing: successful and unsuccessful packages with service fees and COD.
     */
    public function statement(Request $request)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        [$from, $to] = \App\Services\PartnerStatementService::dayRange($data['date'] ?? null);

        return $this->success(app(\App\Services\PartnerStatementService::class)->build($user, $from, $to));
    }

    /** GET /v1/partner/statements/link?date=&type=successful|unsuccessful|both — 30-min browser links (print/PDF, Excel). */
    public function statementLink(Request $request)
    {
        if ($deny = $this->denyUnlessPartner($user = $this->authUser($request))) return $deny;
        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'type' => 'nullable|in:successful,unsuccessful,both,all',
        ]);

        return $this->success(\App\Http\Controllers\PartnerStatementController::signedLink(
            $user, $data['date'] ?? now(\App\Services\PartnerStatementService::tz())->toDateString(), $data['type'] ?? 'both'));
    }

    /** @return array{0: array, 1: \Illuminate\Support\Collection} report payload, package rows */
    private function buildReport(User $user, string $period, ?string $date, bool $withPackages = false): array
    {
        $tz     = config('ride.local_timezone', 'Asia/Phnom_Penh');
        $anchor = $date ? \Carbon\Carbon::parse($date, $tz) : now($tz);

        [$from, $to] = match ($period) {
            'week'  => [$anchor->copy()->startOfWeek(\Carbon\Carbon::MONDAY), $anchor->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
        };

        $appTz = config('app.timezone');
        $rows = Delivery::where('partner_id', $user->id)
            ->whereBetween('created_at', [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)])
            ->orderBy('created_at')
            ->get($withPackages ? ['*'] : ['id', 'status', 'fee', 'package_amount', 'failure_reason', 'created_at', 'completed_at']);

        // Buckets: hours of the day, or days of the week / month.
        $buckets = [];
        if ($period === 'day') {
            for ($h = 0; $h < 24; $h++) $buckets[sprintf('%02d', $h)] = null;
        } else {
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) $buckets[$d->toDateString()] = null;
        }
        $empty = ['total' => 0, 'completed' => 0, 'not_delivered' => 0, 'cancelled' => 0];
        $buckets = array_map(fn () => $empty, $buckets);

        $totals = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'failed' => 0, 'returned' => 0, 'cancelled' => 0];
        $money  = ['delivery_fees' => 0, 'cod_collected' => 0, 'cod_pending' => 0];
        $reasons = array_fill_keys(Delivery::FAILURE_REASONS, 0);
        $minutes = [];

        foreach ($rows as $d) {
            $ps    = $d->packageStatus();
            $local = $d->created_at->copy()->setTimezone($tz);
            $key   = $period === 'day' ? $local->format('H') : $local->toDateString();

            $totals['total']++;
            $bucketKey = match ($ps) {
                'completed'           => 'completed',
                'failed', 'returned'  => 'not_delivered',
                'cancelled'           => 'cancelled',
                default               => null,
            };
            match ($ps) {
                'completed' => $totals['completed']++,
                'failed'    => $totals['failed']++,
                'returned'  => $totals['returned']++,
                'cancelled' => $totals['cancelled']++,
                default     => $totals['in_progress']++,
            };
            if (isset($buckets[$key])) {
                $buckets[$key]['total']++;
                if ($bucketKey) $buckets[$key][$bucketKey]++;
            }

            if ($ps === 'completed') {
                $money['delivery_fees'] += (int) $d->fee;
                $money['cod_collected'] += (int) $d->package_amount;
                if ($d->completed_at) $minutes[] = $d->created_at->diffInMinutes($d->completed_at);
            } elseif (! in_array($ps, ['cancelled', 'returned', 'failed'], true)) {
                $money['cod_pending'] += (int) $d->package_amount;
            }
            if (in_array($ps, ['failed', 'returned'], true) && isset($reasons[$d->failure_reason ?? 'other'])) {
                $reasons[$d->failure_reason ?? 'other']++;
            }
        }

        $handled = $totals['completed'] + $totals['failed'] + $totals['returned'];

        return [[
            'period'       => $period,
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'totals'       => $totals,
            'money'        => $money,
            'success_rate' => $handled > 0 ? round($totals['completed'] / $handled * 100, 1) : null,
            'avg_delivery_minutes' => $minutes ? (int) round(array_sum($minutes) / count($minutes)) : null,
            'failure_reasons' => $reasons,
            'series'       => collect($buckets)->map(fn ($v, $k) => ['key' => (string) $k] + $v)->values()->all(),
        ], $rows];
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
            'completed'       => ['delivered', 'completed'],
            'failed'          => ['delivery_failed'],
            'returned'        => ['returned'],
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
            'failure_reason'    => $d->failure_reason,
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
            'failure_reason'  => $d->failure_reason,
            'failure_note'    => $d->failure_note,
            'failed_at'       => $d->failed_at?->toIso8601String(),
            'returned_at'     => $d->returned_at?->toIso8601String(),
            'completed_at'    => $d->completed_at?->toIso8601String(),
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
