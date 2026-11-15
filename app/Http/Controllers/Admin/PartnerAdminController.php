<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\PartnerPackageController;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\PartnerProfile;
use App\Services\FcmService;
use App\Services\PartnerPackageWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** ROTEH admin: Partner registrations and Partner package management. */
class PartnerAdminController extends Controller
{
    // ── Registrations ────────────────────────────────────────────────────────

    public function partners(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $profiles = PartnerProfile::with('user:id,name,phone,email,role')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('business_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->select('partner_profiles.*')
            ->addSelect(['packages_count' => Delivery::selectRaw('COUNT(*)')->whereColumn('deliveries.partner_id', 'partner_profiles.user_id')])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $stats = PartnerProfile::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        return view('admin.partners', compact('profiles', 'stats'));
    }

    public function approve(PartnerProfile $profile)
    {
        DB::transaction(function () use ($profile) {
            $profile->update([
                'status' => 'approved', 'rejection_reason' => null,
                'reviewed_by' => $this->adminId(), 'reviewed_at' => now(),
            ]);
            $user = $profile->user;
            // Passenger accounts become Partners; they keep every passenger feature.
            if ($user && $user->role === 'passenger') {
                $user->forceFill(['role' => 'partner', 'company_name' => $user->company_name ?: $profile->business_name])->save();
            }
        });

        $this->push($profile, 'partner_approved', ['name' => $profile->business_name]);

        return back()->with('success', "{$profile->business_name} is now an approved Partner.");
    }

    public function reject(Request $request, PartnerProfile $profile)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);

        $profile->update([
            'status' => 'rejected', 'rejection_reason' => $data['reason'],
            'reviewed_by' => $this->adminId(), 'reviewed_at' => now(),
        ]);
        if ($profile->user?->role === 'partner') {
            $profile->user->forceFill(['role' => 'passenger'])->save();
        }

        $this->push($profile, 'partner_rejected', ['reason' => $data['reason']]);

        return back()->with('success', "Registration for {$profile->business_name} rejected.");
    }

    public function suspend(PartnerProfile $profile)
    {
        $profile->update(['status' => 'suspended', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', "{$profile->business_name} suspended — they can no longer create packages.");
    }

    // ── Partner Deliveries (workflow board) ─────────────────────────────────

    /** Board stages, in workflow order: [label, icon, tone]. See Delivery::workflowStage(). */
    public const STAGES = [
        'new'       => ['New',              'fa-inbox',        'warning'],
        'pickup'    => ['Pickup',           'fa-motorcycle',   'info'],
        'at_office' => ['At office',        'fa-warehouse',    'violet'],
        'out'       => ['Out for delivery', 'fa-truck-fast',   'primary'],
        'done'      => ['Delivered',        'fa-circle-check', 'success'],
        'problem'   => ['Problems',         'fa-triangle-exclamation', 'danger'],
    ];

    /**
     * Partner package flow: partner books → admin assigns a pickup driver (shop → office)
     * → received at the office → admin assigns a delivery driver (office → customer)
     * → completed by the driver's QR scan + payment, or by admin.
     */
    public function deliveries(Request $request)
    {
        $stage   = array_key_exists($request->query('stage'), self::STAGES) ? $request->query('stage') : null;
        $search  = trim((string) $request->query('search', ''));
        $partner = $request->integer('partner_id') ?: null;

        $packages = Delivery::with(['partner.partnerProfile:id,user_id,business_name', 'driver:id,name,phone', 'pickupDriver:id,name', 'collection'])
            ->whereNotNull('partner_id')
            ->when($stage, fn ($q) => $q->inStage($stage))
            ->when($partner, fn ($q) => $q->where('partner_id', $partner))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('recipient_name', 'like', "%{$search}%")
                ->orWhere('recipient_phone', 'like', "%{$search}%")
                ->orWhere('partner_reference', 'like', "%{$search}%")
                ->orWhere('id', ltrim(preg_replace('/^PKG-/i', '', $search), '#0') ?: 0)
                ->orWhereHas('partner', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
            ->orderByRaw("CASE WHEN status = 'created' THEN 0 WHEN status = 'at_office' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $counts = collect(self::STAGES)->map(fn ($_, $key) => Delivery::whereNotNull('partner_id')->inStage($key)->count());

        $staff = \App\Models\User::salariedStaff()->orderBy('name')->get(['id', 'name', 'phone']);
        $staffLoad = Delivery::whereIn('driver_id', $staff->pluck('id'))->whereNotNull('partner_id')
            ->whereIn('status', ['accepted', 'assigned', 'picked_up', 'in_progress', 'in_transit', 'delivery_failed'])
            ->selectRaw('driver_id, COUNT(*) n')->groupBy('driver_id')->pluck('n', 'driver_id');

        $partners = PartnerProfile::whereIn('user_id', Delivery::whereNotNull('partner_id')->select('partner_id'))
            ->orderBy('business_name')->pluck('business_name', 'user_id');

        return view('admin.partner-deliveries', [
            'packages'  => $packages,
            'stage'     => $stage,
            'counts'    => $counts,
            'atRoteh'   => Delivery::whereNotNull('partner_id')->where('status', 'at_roteh')->count(),
            'staff'     => $staff,
            'staffLoad' => $staffLoad,
            'partners'  => $partners,
            'office'    => (string) config('ride.office.address'),
        ]);
    }

    /** Old Partner Packages URL → the board. */
    public function packages(Request $request)
    {
        return redirect()->route('admin.partner-deliveries', $request->query());
    }

    /** End of the pickup leg: admin marks the package received at the office. */
    public function receiveAtOffice(Delivery $delivery)
    {
        if (! app(PartnerPackageWorkflow::class)->receiveAtOffice($delivery, Auth::user())) {
            return back()->with('error', "PKG-{$delivery->id} is not on its way to the office.");
        }

        return back()->with('success', "PKG-{$delivery->id} received at the office — now assign a delivery driver.");
    }

    /** Admin completes the drop-off for the driver, recording the money received. */
    public function completeDelivery(Request $request, Delivery $delivery)
    {
        if (! $delivery->partner_id || $delivery->onPickupLeg() || $delivery->status === 'at_office'
            || ! in_array($delivery->status, PartnerPackageWorkflow::HANDOVER_STATUSES, true)) {
            return back()->with('error', "PKG-{$delivery->id} is not out for delivery.");
        }
        if (! $delivery->driver_id) {
            return back()->with('error', "Assign a delivery driver to PKG-{$delivery->id} first — the money is recorded against them.");
        }

        $data   = $request->validate(\App\Http\Controllers\Api\DeliveryCollectionController::rules());
        $flow   = app(PartnerPackageWorkflow::class);
        $result = $flow->collect($delivery, $delivery->driver_id, $data);
        if (isset($result['error'])) {
            return back()->withInput()->with('error', $result['error']['message'])->with('completeId', $delivery->id);
        }
        $flow->finish($delivery);

        return back()->with('success', "PKG-{$delivery->id} delivered and completed.");
    }

    /** Confirm a pending package and offer it to nearby drivers. */
    public function confirmPackage(Delivery $delivery)
    {
        if (! $delivery->partner_id || $delivery->status !== 'created') {
            return back()->with('error', "Package #{$delivery->id} is not waiting for confirmation.");
        }

        PartnerPackageController::confirm($delivery);

        try {
            if ($partner = $delivery->partner) {
                app(FcmService::class)->sendToUser($partner,
                    ...FcmService::message('partner_package_confirmed', ['code' => 'PKG-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT)]),
                    data: ['type' => 'partner_package', 'delivery_id' => (string) $delivery->id]
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Package #{$delivery->id} confirmed — now assign a pickup driver.");
    }

    /** Packages brought back to ROTEH → handed back to the shop. */
    public function returnPackagesToShop(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:deliveries,id']);
        $n = app(\App\Services\DailyClosingService::class)
            ->returnToShop(Delivery::whereIn('id', $data['ids'])->whereNotNull('partner_id')->get());

        return back()->with($n ? 'success' : 'error', $n
            ? "{$n} package(s) marked as returned to the shop."
            : 'Only packages that are at ROTEH can be returned to the shop.');
    }

    /** Assign one or many packages to a ROTEH staff driver. */
    public function assignPackages(Request $request)
    {
        $data = $request->validate([
            'ids'       => 'required|array|min:1',
            'ids.*'     => 'integer|exists:deliveries,id',
            'driver_id' => 'required|integer|exists:users,id',
        ]);
        $staff = \App\Models\User::findOrFail($data['driver_id']);
        $svc = app(\App\Services\StaffAssignmentService::class);

        $done = 0; $errors = [];
        foreach (Delivery::whereIn('id', $data['ids'])->get() as $d) {
            try { $svc->assign($d, $staff); $done++; }
            catch (\Illuminate\Validation\ValidationException $e) { $errors[] = collect($e->errors())->flatten()->first(); }
        }

        $msg = "{$done} package(s) assigned to {$staff->name}.";
        return back()->with($errors ? 'error' : 'success', $errors ? $msg . ' Skipped: ' . implode(' ', $errors) : $msg);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function adminId(): ?int
    {
        return Auth::id();
    }

    private function push(PartnerProfile $profile, string $key, array $replace): void
    {
        try {
            if ($profile->user) {
                [$title, $body] = FcmService::message($key, $replace);
                app(FcmService::class)->notifyUser($profile->user, $title, $body, ['type' => $key]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
