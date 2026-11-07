<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\PartnerPackageController;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\PartnerProfile;
use App\Services\FcmService;
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

    // ── Packages ─────────────────────────────────────────────────────────────

    public function packages(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $raw = [
            'pending' => ['created'], 'confirmed' => ['requested', 'pending'], 'driver_assigned' => ['assigned', 'accepted'],
            'picked_up' => ['picked_up'], 'in_transit' => ['in_progress', 'in_transit'],
            'completed' => ['delivered', 'completed'], 'failed' => ['delivery_failed'], 'returned' => ['returned'],
            'cancelled' => ['cancelled'],
        ];

        $packages = Delivery::with(['partner:id,name,phone,company_name', 'driver:id,name,phone'])
            ->whereNotNull('partner_id')
            ->when($status && isset($raw[$status]), fn ($q) => $q->whereIn('status', $raw[$status]))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('recipient_name', 'like', "%{$search}%")
                ->orWhere('recipient_phone', 'like', "%{$search}%")
                ->orWhere('partner_reference', 'like', "%{$search}%")
                ->orWhere('id', ltrim(preg_replace('/^PKG-/i', '', $search), '#0') ?: 0)
                ->orWhereHas('partner', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
            ->orderByRaw("CASE WHEN status = 'created' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $counts = Delivery::whereNotNull('partner_id')->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $stats = array_fill_keys(array_keys($raw), 0);
        foreach ($counts as $s => $n) {
            $stats[Delivery::packageStatusFor((string) $s)] = ($stats[Delivery::packageStatusFor((string) $s)] ?? 0) + (int) $n;
        }

        return view('admin.partner-packages', compact('packages', 'stats'));
    }

    /** Confirm a pending package and offer it to nearby drivers. */
    public function confirmPackage(Delivery $delivery)
    {
        if (! $delivery->partner_id || $delivery->status !== 'created') {
            return back()->with('error', "Package #{$delivery->id} is not waiting for confirmation.");
        }

        PartnerPackageController::confirmAndOffer($delivery);

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

        return back()->with('success', "Package #{$delivery->id} confirmed and sent to nearby drivers.");
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
