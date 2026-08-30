<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\PartnerContract;
use App\Models\PricingSetting;
use App\Models\TopUpRequest;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\DriverMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PartnerController extends Controller
{
    // ── Auth ──────────────────────────────────────────────────────────────────

    public function loginPage()
    {
        if (Auth::guard('partner')->check()) {
            return redirect()->route('partner.dashboard');
        }
        return view('partner.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login'    => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        $login = trim($data['login']);

        $user = User::where('role', 'partner')
            ->where(function ($q) use ($login) {
                $q->where('phone', $login)->orWhere('email', $login);
            })->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['login' => 'Invalid phone/email or password.'])->withInput();
        }

        Auth::guard('partner')->login($user, $request->boolean('remember'));

        return redirect()->route('partner.dashboard');
    }

    public function logout()
    {
        Auth::guard('partner')->logout();
        return redirect()->route('partner.login');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $partner = Auth::guard('partner')->user();
        $week    = now()->startOfWeek();
        $month   = now()->startOfMonth();

        $base = fn() => Delivery::where('partner_id', $partner->id);

        $stats = [
            'today'      => (clone $base())->whereDate('created_at', today())->count(),
            'week'       => (clone $base())->where('created_at', '>=', $week)->count(),
            'month'      => (clone $base())->where('created_at', '>=', $month)->count(),
            'total'      => (clone $base())->count(),
            'created'    => (clone $base())->where('status', 'created')->count(),
            'assigned'   => (clone $base())->where('status', 'assigned')->count(),
            'in_transit' => (clone $base())->whereIn('status', ['accepted','picked_up','in_transit'])->count(),
            'delivered'  => (clone $base())->whereIn('status', ['delivered','completed'])->count(),
            'cancelled'  => (clone $base())->where('status', 'cancelled')->count(),
        ];

        $active = (clone $base())
            ->with(['driver:id,name,phone,rating,available', 'vehicle'])
            ->whereIn('status', ['assigned','accepted','picked_up','in_transit'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $recent = (clone $base())
            ->with(['driver:id,name,phone'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $successRate = $stats['total'] > 0
            ? round(($stats['delivered'] / $stats['total']) * 100, 1)
            : 0;

        return view('partner.dashboard', compact('partner', 'stats', 'active', 'recent', 'successRate'));
    }

    // ── Orders ────────────────────────────────────────────────────────────────

    public function orders(Request $request)
    {
        $partner = Auth::guard('partner')->user();

        $status  = $request->input('status');
        $search  = $request->input('search');
        $from    = $request->input('date_from');
        $to      = $request->input('date_to');

        $query = Delivery::with(['driver:id,name,phone,rating', 'vehicle'])
            ->where('partner_id', $partner->id)
            ->orderByDesc('id');

        if ($status) $query->where('status', $status);
        if ($from)   $query->whereDate('created_at', '>=', $from);
        if ($to)     $query->whereDate('created_at', '<=', $to);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('recipient_phone', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%")
                  ->orWhere('partner_reference', 'like', "%{$search}%")
                  ->orWhere('qr_token', $search);
            });
        }

        $orders = $query->paginate(10)->withQueryString();

        return view('partner.orders', compact('partner', 'orders', 'status', 'search', 'from', 'to'));
    }

    public function createOrder()
    {
        $partner = Auth::guard('partner')->user();
        $contract = PartnerContract::where('partner_id', $partner->id)
            ->where('is_active', true)
            ->latest()
            ->first();
        return view('partner.order-create', compact('partner', 'contract'));
    }

    public function storeOrder(Request $request)
    {
        $partner = Auth::guard('partner')->user();

        $data = $request->validate([
            'recipient_name'    => 'required|string|max:255',
            'recipient_phone'   => 'required|string|max:24',
            'pickup_address'    => 'required|string|max:500',
            'dropoff_address'   => 'required|string|max:500',
            'pickup_lat'        => 'nullable|numeric',
            'pickup_lng'        => 'nullable|numeric',
            'dropoff_lat'       => 'nullable|numeric',
            'dropoff_lng'       => 'nullable|numeric',
            'package_size'      => 'nullable|in:small,medium,large,extra_large',
            'service_option'    => 'nullable|in:normal,express',
            'package_amount'    => 'nullable|integer|min:0',
            'payment_by'        => 'nullable|in:sender,recipient',
            'notes'             => 'nullable|string|max:500',
            'partner_reference' => 'nullable|string|max:100',
        ]);

        $serviceOption = $data['service_option'] ?? 'normal';
        $packageSize   = $data['package_size']   ?? 'small';

        // Use partner contract (flat rate); fall back to system default partner rates
        $contract = PartnerContract::where('partner_id', $partner->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if ($contract) {
            $fee = $contract->calculateFee($serviceOption, $packageSize);
        } else {
            $normalFee  = (int) PricingSetting::get('partner_normal_fee', 5000);
            $expressFee = (int) PricingSetting::get('partner_express_fee', 10000);
            $base       = $serviceOption === 'express' ? $expressFee : $normalFee;
            $surcharge  = match ($packageSize) {
                'large'       => (int) PricingSetting::get('partner_surcharge_large', 5000),
                'extra_large' => (int) PricingSetting::get('partner_surcharge_extra_large', 5000),
                default       => 0,
            };
            $fee = $base + $surcharge;
        }

        $delivery = Delivery::create([
            'partner_id'        => $partner->id,
            'sender_id'         => $partner->id,
            'sender_name'       => $partner->name,
            'sender_phone'      => $partner->phone,
            'recipient_name'    => $data['recipient_name'],
            'recipient_phone'   => $data['recipient_phone'],
            'pickup_address'    => $data['pickup_address'],
            'dropoff_address'   => $data['dropoff_address'],
            'pickup_lat'        => $data['pickup_lat'] ?? 0,
            'pickup_lng'        => $data['pickup_lng'] ?? 0,
            'dropoff_lat'       => $data['dropoff_lat'] ?? 0,
            'dropoff_lng'       => $data['dropoff_lng'] ?? 0,
            'package_size'      => $packageSize,
            'fee'               => $fee,
            'package_amount'    => $data['package_amount'] ?? 0,
            'payment_method'    => 'cash',
            'payment_by'        => $data['payment_by'] ?? 'recipient',
            'payment_status'    => 'unpaid',
            'notes'             => $data['notes'] ?? null,
            'partner_reference' => $data['partner_reference'] ?? null,
            'service_type'      => 'delivery',
            'service_option'    => $serviceOption,
            'status'            => 'created',
            'qr_token'          => Str::random(32),
        ]);

        return redirect()->route('partner.orders.show', $delivery)
            ->with('success', 'Order #' . $delivery->id . ' created. Delivery fee: ' . number_format($fee) . ' ៛. QR code is ready.');
    }

    public function showOrder(Delivery $delivery)
    {
        $partner = Auth::guard('partner')->user();
        if ($delivery->partner_id !== $partner->id) abort(403);

        $delivery->load(['driver:id,name,phone,rating,current_latitude,current_longitude', 'vehicle']);

        $nearbyDrivers = [];
        if (in_array($delivery->status, ['created', 'assigned'])) {
            $nearbyDrivers = app(DriverMatchingService::class)
                ->findDrivers((float) $delivery->pickup_lat, (float) $delivery->pickup_lng, 10)
                ->map(fn($d) => [
                    'id'          => $d->id,
                    'name'        => $d->name,
                    'phone'       => $d->phone,
                    'rating'      => $d->rating,
                    'distance_km' => $d->distance_km,
                    'eta_minutes' => $d->eta_minutes,
                ])->toArray();
        }

        return view('partner.order-show', compact('partner', 'delivery', 'nearbyDrivers'));
    }

    public function assignDriver(Request $request, Delivery $delivery)
    {
        $partner = Auth::guard('partner')->user();
        if ($delivery->partner_id !== $partner->id) abort(403);

        $data = $request->validate(['driver_id' => 'required|integer|exists:users,id']);

        $driver = User::where('id', $data['driver_id'])->where('role', 'driver')->firstOrFail();

        $delivery->update([
            'driver_id'       => $driver->id,
            'status'          => 'assigned',
            'assigned_at'     => now(),
            'assignment_type' => 'manual',
        ]);

        return back()->with('success', "Order assigned to {$driver->name}.");
    }

    public function cancelOrder(Request $request, Delivery $delivery)
    {
        $partner = Auth::guard('partner')->user();
        if ($delivery->partner_id !== $partner->id) abort(403);

        if (in_array($delivery->status, ['picked_up', 'in_transit', 'delivered', 'completed'])) {
            return back()->with('error', 'Cannot cancel — package is already in transit.');
        }

        $delivery->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->input('reason'),
        ]);

        return redirect()->route('partner.orders')->with('success', "Order #$delivery->id cancelled.");
    }

    // ── Wallet ────────────────────────────────────────────────────────────────

    public function wallet()
    {
        $partner = Auth::guard('partner')->user();

        $codTotal     = Delivery::where('partner_id', $partner->id)
            ->where('payment_by', 'recipient')
            ->whereIn('status', ['delivered', 'completed'])
            ->where('payment_status', 'unpaid')
            ->sum('fee');

        $codCollected = Delivery::where('partner_id', $partner->id)
            ->where('payment_by', 'recipient')
            ->where('payment_status', 'paid')
            ->sum('fee');

        $topups      = TopUpRequest::where('user_id', $partner->id)->orderByDesc('id')->paginate(10, ['*'], 'topup_page');
        $withdrawals = WithdrawalRequest::where('driver_id', $partner->id)->orderByDesc('id')->paginate(10, ['*'], 'wd_page');

        return view('partner.wallet', compact('partner', 'codTotal', 'codCollected', 'topups', 'withdrawals'));
    }

    // ── Reports ───────────────────────────────────────────────────────────────

    public function reports(Request $request)
    {
        $partner = Auth::guard('partner')->user();
        $days    = (int) $request->input('days', 30);
        $start   = now()->subDays($days)->startOfDay();

        $base = Delivery::where('partner_id', $partner->id)->where('created_at', '>=', $start);

        $total     = (clone $base)->count();
        $delivered = (clone $base)->whereIn('status', ['delivered', 'completed'])->count();
        $cancelled = (clone $base)->where('status', 'cancelled')->count();

        $avgTime = Delivery::where('partner_id', $partner->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->where('created_at', '>=', $start)
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as avg_minutes')
            ->value('avg_minutes');

        $daily = Delivery::where('partner_id', $partner->id)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total,
                SUM(CASE WHEN status IN ("delivered","completed") THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $successRate = $total > 0 ? round(($delivered / $total) * 100, 1) : 0;
        $avgMinutes  = $avgTime ? round($avgTime) : null;

        return view('partner.reports', compact('partner', 'days', 'total', 'delivered', 'cancelled', 'successRate', 'avgMinutes', 'daily'));
    }

    // ── Issues ────────────────────────────────────────────────────────────────

    public function issues()
    {
        $partner = Auth::guard('partner')->user();

        $failed  = Delivery::where('partner_id', $partner->id)
            ->where('status', 'cancelled')
            ->with(['driver:id,name,phone'])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'failed_page');

        return view('partner.issues', compact('partner', 'failed'));
    }
}
