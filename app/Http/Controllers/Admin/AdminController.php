<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChargingStation;
use App\Models\PricingSetting;
use App\Models\RidePricing;
use App\Services\FareService;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\MarketplaceItem;
use App\Models\Ride;
use App\Models\SafetyIncident;
use App\Models\SupportTicket;
use App\Models\SurgeZone;
use App\Models\TopUpRequest;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Models\AirportZone;
use App\Models\Banner;
use App\Models\BusinessAccount;
use App\Models\MembershipTier;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /** Roles allowed to access the admin panel. */
    private static function allowedRoles(): array
    {
        $roles = ['admin'];

        if (config('app.admin_test_mode')) {
            $roles[] = 'driver';
            $roles[] = 'passenger';
        }

        return $roles;
    }

    public function __construct()
    {
        $this->middleware(function (Request $request, $next) {
            if (! Auth::check()) {
                return redirect()->route('admin.login');
            }
            if (! in_array(Auth::user()->role, self::allowedRoles(), true)) {
                return redirect()->route('admin.login');
            }
            return $next($request);
        })->except(['showLogin', 'login']);
    }

    // ─── Auth ────────────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (Auth::check() && in_array(Auth::user()->role, self::allowedRoles(), true)) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login', [
            'testMode' => config('app.admin_test_mode', false),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        if (! in_array($user->role, self::allowedRoles(), true)) {
            $hint = config('app.admin_test_mode')
                ? 'Only admin, driver, and passenger accounts are allowed.'
                : 'Only admin accounts can access this panel.';
            return back()->withErrors(['email' => $hint])->withInput();
        }

        Auth::login($user, $request->boolean('remember'));

        return redirect()->route('admin.dashboard');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('admin.login');
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function dashboard()
    {
        $today = now()->startOfDay();
        $week  = now()->subDays(6)->startOfDay();

        // Revenue last 7 days for chart — safe even if completed_at column is missing
        $revenueChart = [];
        $ridesChart   = [];
        for ($i = 6; $i >= 0; $i--) {
            $day   = now()->subDays($i)->toDateString();
            $start = now()->subDays($i)->startOfDay();
            $end   = now()->subDays($i)->endOfDay();
            try {
                $revenueChart[$day] = (int) Ride::where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end])
                    ->sum('fare');
                $ridesChart[$day]   = Ride::where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end])
                    ->count();
            } catch (\Throwable) {
                $revenueChart[$day] = 0;
                $ridesChart[$day]   = 0;
            }
        }

        $todayRevenue     = $revenueChart[now()->toDateString()] ?? 0;
        $yesterdayRevenue = $revenueChart[now()->subDay()->toDateString()] ?? 0;
        $revenueGrowth    = $yesterdayRevenue > 0
            ? round(($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue * 100, 1)
            : null;

        // Helper: safely count a query even if the table doesn't exist yet
        $safe = fn (\Closure $cb, int $default = 0) => rescue($cb, $default, false);

        return view('admin.dashboard', [
            'metrics' => [
                'users'               => User::where('role', 'passenger')->count(),
                'drivers'             => User::where('role', 'driver')->count(),
                'drivers_online'      => User::where('role', 'driver')->where('available', true)->count(),
                'drivers_pending'     => User::where('role', 'driver')->where('approval_status', 'pending')->count(),
                'vehicles'            => Vehicle::count(),
                'rides_total'         => Ride::count(),
                'rides_today'         => Ride::where('created_at', '>=', $today)->count(),
                'rides_active'        => Ride::whereIn('status', ['accepted','driver_arrived','in_progress'])->count(),
                'deliveries_total'    => Delivery::count(),
                'deliveries_today'    => Delivery::where('created_at', '>=', $today)->count(),
                'revenue_today'       => $todayRevenue,
                'revenue_week'        => $safe(fn () => (int) Ride::where('status','completed')->where('completed_at','>=',$week)->sum('fare')),
                'revenue_growth'      => $revenueGrowth,
                'marketplace'         => MarketplaceItem::count(),
                'support_open'        => SupportTicket::whereIn('status', ['open','in_progress'])->count(),
                'withdrawals_pending' => $safe(fn () => WithdrawalRequest::where('status','pending')->count()),
                'safety_incidents'    => SafetyIncident::count(),
            ],
            'revenueChart'   => $revenueChart,
            'ridesChart'     => $ridesChart,
            'latestUsers'    => User::latest()->take(8)->get(),
            'latestRides'    => Ride::with('passenger:id,name','driver:id,name')->latest()->take(8)->get(),
            'pendingDrivers' => User::where('role','driver')->where('approval_status','pending')->latest()->take(5)->get(),
            'openTickets'    => SupportTicket::whereIn('status',['open','in_progress'])->latest()->take(5)->get(),
        ]);
    }

    public function fareManagement()
    {
        $settings = PricingSetting::all()->keyBy('key');
        $tiers    = rescue(fn () => MembershipTier::orderBy('sort_order')->get(), collect(), false);

        return view('admin.fare-management', compact('settings', 'tiers'));
    }

    public function updateFareManagement(Request $request)
    {
        $data = $request->validate([
            'cancel_fee_after_arrival'        => 'required|integer|min:0',
            'cancel_fee_after_accepted'        => 'required|integer|min:0',
            'cancel_free_minutes'              => 'required|integer|min:0',
            'waiting_free_minutes'             => 'required|integer|min:0',
            'waiting_rate_khr_per_min'         => 'required|integer|min:0',
            'night_surcharge_rate'             => 'required|numeric|min:0|max:2',
            'delivery_night_surcharge_rate'    => 'required|numeric|min:0|max:2',
            'delivery_express_multiplier'      => 'required|numeric|min:1|max:10',
            'avg_city_speed_kmh'               => 'required|integer|min:5|max:200',
            'traffic_speed_threshold_kmh'      => 'required|integer|min:5|max:100',
            'loyalty_points_per_ride'          => 'required|integer|min:0',
            'loyalty_points_per_delivery'      => 'required|integer|min:0',
            'loyalty_min_redeem_points'        => 'required|integer|min:0',
            'loyalty_redeem_rate_khr'          => 'required|integer|min:0',
        ]);

        foreach ($data as $key => $value) {
            PricingSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Fee settings saved successfully.');
    }

    // ─── Users ───────────────────────────────────────────────────────────────

    public function users()
    {
        return view('admin.users', [
            'users'     => User::with('company')->orderBy('created_at')->paginate(20),
            'companies' => Company::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:6',
            'phone'           => 'nullable|string|max:20',
            'role'            => 'required|in:admin,driver,passenger',
            'driver_type'     => 'nullable|in:employee,owner,rental',
            'company_id'      => 'nullable|exists:companies,id',
            'salary'          => 'nullable|integer|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $data['api_token'] = bin2hex(random_bytes(40));

        if ($data['role'] !== 'driver') {
            $data['driver_type']     = null;
            $data['company_id']      = null;
            $data['salary']          = 0;
            $data['commission_rate'] = null;
        }

        // Handle optional avatar upload on create.
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'file|mimes:jpeg,jpg,png,webp|max:3072']);
            $file = $request->file('avatar');
            $data['avatar'] = $file->storeAs(
                'avatars',
                'tmp_' . Str::random(12) . '.' . $file->getClientOriginalExtension(),
                'public'
            );
        }

        $user = User::create($data);

        // Rename avatar to use real user ID now that we have it.
        if (! empty($data['avatar']) && str_starts_with($data['avatar'], 'avatars/tmp_')) {
            $ext     = pathinfo($data['avatar'], PATHINFO_EXTENSION);
            $newPath = 'avatars/' . $user->id . '_' . Str::random(8) . '.' . $ext;
            Storage::disk('public')->move($data['avatar'], $newPath);
            $user->update(['avatar' => $newPath]);
        }

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $user->id,
            'password'        => 'nullable|string|min:6',
            'phone'           => 'nullable|string|max:20',
            'role'            => 'required|in:admin,driver,passenger',
            'wallet_balance'  => 'nullable|integer|min:0',
            'driver_type'     => 'nullable|in:employee,owner,rental',
            'company_id'      => 'nullable|exists:companies,id',
            'salary'          => 'nullable|integer|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($data['role'] !== 'driver') {
            $data['driver_type']     = null;
            $data['company_id']      = null;
            $data['salary']          = 0;
            $data['commission_rate'] = null;
        }

        // Handle avatar upload if a file was attached.
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'file|mimes:jpeg,jpg,png,webp|max:3072']);
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $file = $request->file('avatar');
            $data['avatar'] = $file->storeAs(
                'avatars',
                $user->id . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension(),
                'public'
            );
        }

        $user->update($data);

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function destroyUser(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users')->with('success', 'User deleted.');
    }

    // ─── Vehicles ────────────────────────────────────────────────────────────

    public function vehicles()
    {
        return view('admin.vehicles', [
            'vehicles' => Vehicle::with('driver')->orderBy('created_at')->paginate(20),
            'drivers'  => User::where('role', 'driver')->orderBy('name')->get(),
        ]);
    }

    public function storeVehicle(Request $request)
    {
        $data = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'license_plate' => 'required|string|max:20',
            'make'          => 'required|string|max:100',
            'model'         => 'required|string|max:100',
            'year'          => 'required|integer|min:1990|max:' . (date('Y') + 1),
            'type'          => 'required|in:electric,sedan,suv,van,motorcycle,truck,tuk_tuk',
            'status'        => 'required|in:active,inactive,maintenance',
            'capacity'      => 'required|integer|min:1|max:50',
            'details'       => 'nullable|string',
            'images.*'      => 'nullable|file|mimes:jpeg,jpg,png,webp|max:3072',
        ]);

        unset($data['images']);
        $vehicle = Vehicle::create($data);

        if ($request->hasFile('images')) {
            $paths = [];
            foreach (array_slice($request->file('images'), 0, 5) as $file) {
                $paths[] = $file->storeAs(
                    'vehicles/' . $vehicle->id,
                    Str::random(12) . '.' . $file->getClientOriginalExtension(),
                    'public'
                );
            }
            $vehicle->update(['images' => $paths]);
        }

        return redirect()->route('admin.vehicles')->with('success', 'Vehicle created successfully.');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'license_plate' => 'required|string|max:20',
            'make'          => 'required|string|max:100',
            'model'         => 'required|string|max:100',
            'year'          => 'required|integer|min:1990|max:' . (date('Y') + 1),
            'type'          => 'required|in:electric,sedan,suv,van,motorcycle,truck,tuk_tuk',
            'status'        => 'required|in:active,inactive,maintenance',
            'capacity'      => 'required|integer|min:1|max:50',
            'details'       => 'nullable|string',
            'images.*'      => 'nullable|file|mimes:jpeg,jpg,png,webp|max:3072',
        ]);

        unset($data['images']);
        $vehicle->update($data);

        if ($request->hasFile('images')) {
            $existing = $vehicle->images ?? [];
            $slots = max(0, 5 - count($existing));
            foreach (array_slice($request->file('images'), 0, $slots) as $file) {
                $existing[] = $file->storeAs(
                    'vehicles/' . $vehicle->id,
                    Str::random(12) . '.' . $file->getClientOriginalExtension(),
                    'public'
                );
            }
            $vehicle->update(['images' => $existing]);
        }

        return redirect()->route('admin.vehicles')->with('success', 'Vehicle updated successfully.');
    }

    public function destroyVehicle(Vehicle $vehicle)
    {
        $vehicle->delete();
        return redirect()->route('admin.vehicles')->with('success', 'Vehicle deleted.');
    }

    public function storeVehicleImage(Request $request, Vehicle $vehicle)
    {
        $request->validate(['image' => 'required|file|mimes:jpeg,jpg,png,webp|max:3072']);

        $images = $vehicle->images ?? [];

        if (count($images) >= 5) {
            return back()->with('error', 'Maximum 5 images allowed per vehicle.');
        }

        $file = $request->file('image');
        $path = $file->storeAs(
            'vehicles/' . $vehicle->id,
            Str::random(12) . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $images[] = $path;
        $vehicle->update(['images' => $images]);

        return back()->with('success', 'Image uploaded.');
    }

    public function destroyVehicleImage(Request $request, Vehicle $vehicle)
    {
        $data   = $request->validate(['path' => 'required|string']);
        $images = $vehicle->images ?? [];

        if (! in_array($data['path'], $images, true)) {
            return back()->with('error', 'Image not found.');
        }

        Storage::disk('public')->delete($data['path']);
        $vehicle->update(['images' => array_values(array_filter($images, fn($p) => $p !== $data['path']))]);

        return back()->with('success', 'Image deleted.');
    }

    // ─── Rides ───────────────────────────────────────────────────────────────

    public function rides()
    {
        return view('admin.rides', [
            'rides'      => Ride::with(['passenger', 'driver'])->orderBy('created_at')->paginate(20),
            'passengers' => User::where('role', 'passenger')->orderBy('name')->get(),
            'drivers'    => User::where('role', 'driver')->orderBy('name')->get(),
        ]);
    }

    public function storeRide(Request $request)
    {
        $data = $request->validate([
            'passenger_id'    => 'required|exists:users,id',
            'driver_id'       => 'nullable|exists:users,id',
            'pickup_address'  => 'required|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'status'          => 'required|in:requested,pending,accepted,in_progress,completed,cancelled',
            'fare'            => 'nullable|numeric|min:0',
            'service_type'    => 'nullable|string|max:50',
            'notes'           => 'nullable|string',
        ]);

        Ride::create($data);

        return redirect()->route('admin.rides')->with('success', 'Ride created successfully.');
    }

    public function updateRide(Request $request, Ride $ride)
    {
        $data = $request->validate([
            'passenger_id'    => 'required|exists:users,id',
            'driver_id'       => 'nullable|exists:users,id',
            'pickup_address'  => 'required|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'status'          => 'required|in:requested,pending,accepted,in_progress,completed,cancelled',
            'fare'            => 'nullable|numeric|min:0',
            'service_type'    => 'nullable|string|max:50',
            'notes'           => 'nullable|string',
        ]);

        $ride->update($data);

        return redirect()->route('admin.rides')->with('success', 'Ride updated successfully.');
    }

    public function destroyRide(Ride $ride)
    {
        $ride->delete();
        return redirect()->route('admin.rides')->with('success', 'Ride deleted.');
    }

    // ─── Deliveries ──────────────────────────────────────────────────────────

    public function deliveries(\Illuminate\Http\Request $request)
    {
        $type  = $request->input('type', 'all');
        $query = Delivery::with(['sender', 'driver'])->orderBy('created_at', 'desc');

        if ($type !== 'all') {
            $query->where('service_type', $type);
        }

        return view('admin.deliveries', [
            'deliveries' => $query->paginate(20)->appends(['type' => $type]),
            'senders'    => User::where('role', 'passenger')->orderBy('name')->get(),
            'drivers'    => User::where('role', 'driver')->orderBy('name')->get(),
            'activeType' => $type,
            'counts'     => [
                'all'      => Delivery::count(),
                'delivery' => Delivery::where('service_type', 'delivery')->count(),
                'moving'   => Delivery::where('service_type', 'moving')->count(),
            ],
        ]);
    }

    public function storeDelivery(Request $request)
    {
        $data = $request->validate([
            'service_type'       => 'required|in:delivery,moving',
            'sender_id'          => 'required|exists:users,id',
            'sender_name'        => 'required|string|max:255',
            'recipient_name'     => 'required|string|max:255',
            'recipient_phone'    => 'required|string|max:24',
            'package_size'       => 'nullable|in:small,medium,large',
            'driver_id'          => 'nullable|exists:users,id',
            'pickup_address'     => 'required|string|max:255',
            'dropoff_address'    => 'required|string|max:255',
            'status'             => 'required|in:requested,pending,accepted,in_progress,completed,cancelled',
            'fee'                => 'nullable|numeric|min:0',
            'payment_by'         => 'nullable|in:sender,recipient',
            'payment_method'     => 'nullable|in:cash,wallet,aba,wing,other_online',
            'scheduled_at'       => 'nullable|date',
            'notes'              => 'nullable|string',
            'package_details'    => 'nullable|string|max:500',
            // Moving fields
            'floor_pickup'        => 'nullable|integer|min:0|max:50',
            'floor_dropoff'       => 'nullable|integer|min:0|max:50',
            'has_elevator'        => 'nullable|boolean',
            'needs_stairs_carry'  => 'nullable|boolean',
            'heavy_items'         => 'nullable|boolean',
            'requires_helpers'    => 'nullable|integer|min:0|max:4',
            'helper_type'         => 'nullable|in:normal_carry,heavy_carry',
            'helper_fee'          => 'nullable|numeric|min:0',
            'floor_fee'           => 'nullable|numeric|min:0',
            // Payment model
            'payment_model'       => 'nullable|in:customer_pays,partner_pays,split_payment,sponsored',
            'split_pct_customer'  => 'nullable|integer|min:0|max:100',
            'partner_reference'   => 'nullable|string|max:150',
        ]);

        $data['package_details']    = $data['package_details'] ?? '';
        $data['payment_by']         = $data['payment_by'] ?? 'sender';
        $data['payment_method']     = $data['payment_method'] ?? 'cash';
        $data['payment_status']     = 'unpaid';
        $data['payment_model']      = $data['payment_model'] ?? 'customer_pays';
        $data['assigned_at']        = ! empty($data['driver_id']) ? now() : null;
        $data['has_elevator']       = (bool) ($data['has_elevator'] ?? false);
        $data['needs_stairs_carry'] = (bool) ($data['needs_stairs_carry'] ?? false);
        $data['heavy_items']        = (bool) ($data['heavy_items'] ?? false);

        Delivery::create($data);

        return redirect()->route('admin.deliveries', ['type' => $data['service_type']])->with('success', ucfirst($data['service_type']) . ' order created successfully.');
    }

    public function updateDelivery(Request $request, Delivery $delivery)
    {
        $data = $request->validate([
            'service_type'       => 'required|in:delivery,moving',
            'sender_id'          => 'required|exists:users,id',
            'sender_name'        => 'required|string|max:255',
            'recipient_name'     => 'required|string|max:255',
            'recipient_phone'    => 'required|string|max:24',
            'package_size'       => 'nullable|in:small,medium,large',
            'driver_id'          => 'nullable|exists:users,id',
            'pickup_address'     => 'required|string|max:255',
            'dropoff_address'    => 'required|string|max:255',
            'status'             => 'required|in:requested,pending,accepted,in_progress,completed,cancelled',
            'fee'                => 'nullable|numeric|min:0',
            'payment_by'         => 'nullable|in:sender,recipient',
            'payment_method'     => 'nullable|in:cash,wallet,aba,wing,other_online',
            'scheduled_at'       => 'nullable|date',
            'notes'              => 'nullable|string',
            // Moving fields
            'floor_pickup'        => 'nullable|integer|min:0|max:50',
            'floor_dropoff'       => 'nullable|integer|min:0|max:50',
            'has_elevator'        => 'nullable|boolean',
            'needs_stairs_carry'  => 'nullable|boolean',
            'heavy_items'         => 'nullable|boolean',
            'requires_helpers'    => 'nullable|integer|min:0|max:4',
            'helper_type'         => 'nullable|in:normal_carry,heavy_carry',
            'helper_fee'          => 'nullable|numeric|min:0',
            'floor_fee'           => 'nullable|numeric|min:0',
            // Payment model
            'payment_model'       => 'nullable|in:customer_pays,partner_pays,split_payment,sponsored',
            'split_pct_customer'  => 'nullable|integer|min:0|max:100',
            'partner_reference'   => 'nullable|string|max:150',
        ]);

        if (! empty($data['driver_id']) && ! $delivery->assigned_at) {
            $data['assigned_at'] = now();
        }

        $data['has_elevator']       = (bool) ($data['has_elevator'] ?? false);
        $data['needs_stairs_carry'] = (bool) ($data['needs_stairs_carry'] ?? false);
        $data['heavy_items']        = (bool) ($data['heavy_items'] ?? false);
        $data['payment_model']      = $data['payment_model'] ?? 'customer_pays';

        $delivery->update($data);

        return redirect()->route('admin.deliveries', ['type' => $data['service_type']])->with('success', 'Order updated successfully.');
    }

    public function assignDelivery(Request $request, Delivery $delivery)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:users,id',
        ]);

        $delivery->update([
            'driver_id'   => $data['driver_id'],
            'status'      => in_array($delivery->status, ['requested', 'pending']) ? 'accepted' : $delivery->status,
            'assigned_at' => $delivery->assigned_at ?? now(),
        ]);

        $driver = User::find($data['driver_id']);

        return redirect()->route('admin.deliveries')
            ->with('success', "Delivery #{$delivery->id} assigned to {$driver->name}.");
    }

    public function destroyDelivery(Delivery $delivery)
    {
        $delivery->delete();
        return redirect()->route('admin.deliveries')->with('success', 'Delivery deleted.');
    }

    // ─── Marketplace ─────────────────────────────────────────────────────────

    public function marketplace()
    {
        return view('admin.marketplace', [
            'items'    => MarketplaceItem::with(['seller', 'images'])->orderByDesc('created_at')->paginate(20),
            'sellers'  => User::orderBy('name')->get(),
            'vehicles' => Vehicle::orderBy('make')->get(),
        ]);
    }

    public function storeMarketplace(Request $request)
    {
        $isSale    = $request->boolean('is_sale');
        $isRent    = $request->boolean('is_rent');
        $isGuest   = $request->input('entry_type') === 'guest';

        if (!$isSale && !$isRent) {
            return back()->withErrors(['type' => 'Please select at least one listing type (Sale or Rent).'])->withInput();
        }
        $type = ($isSale && $isRent) ? 'both' : ($isSale ? 'sale' : 'rent');

        $data = $request->validate([
            'entry_type'  => 'required|in:user,guest',
            'seller_id'   => $isGuest ? 'nullable' : 'required|exists:users,id',
            'vehicle_id'  => 'nullable|exists:vehicles,id',
            'guest_name'  => $isGuest ? 'required|string|max:100' : 'nullable',
            'guest_phone' => $isGuest ? 'required|string|max:20'  : 'nullable',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => $isSale ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'rent_rate'   => $isRent ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'available'   => 'boolean',
            'condition'   => 'required|in:excellent,good,fair,poor',
            'images'      => 'nullable|array|max:10',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $data['type']      = $type;
        $data['available'] = $request->boolean('available');
        if ($isGuest) {
            $data['seller_id'] = null;
        }
        unset($data['images']);

        $item = MarketplaceItem::create($data);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('marketplace/items', 'public');
                \App\Models\MarketplaceItemImage::create([
                    'marketplace_item_id' => $item->id,
                    'path'                => $path,
                    'disk'                => 'public',
                    'sort_order'          => $i,
                ]);
            }
        }

        return redirect()->route('admin.marketplace')->with('success', 'Item created successfully.');
    }

    public function updateMarketplace(Request $request, MarketplaceItem $item)
    {
        $isSale  = $request->boolean('is_sale');
        $isRent  = $request->boolean('is_rent');
        $isGuest = $request->input('entry_type') === 'guest';

        if (!$isSale && !$isRent) {
            return back()->withErrors(['type' => 'Please select at least one listing type (Sale or Rent).'])->withInput();
        }
        $type = ($isSale && $isRent) ? 'both' : ($isSale ? 'sale' : 'rent');

        $data = $request->validate([
            'entry_type'  => 'required|in:user,guest',
            'seller_id'   => $isGuest ? 'nullable' : 'required|exists:users,id',
            'vehicle_id'  => 'nullable|exists:vehicles,id',
            'guest_name'  => $isGuest ? 'required|string|max:100' : 'nullable',
            'guest_phone' => $isGuest ? 'required|string|max:20'  : 'nullable',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => $isSale ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'rent_rate'   => $isRent ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'available'   => 'boolean',
            'condition'   => 'required|in:excellent,good,fair,poor',
            'images'      => 'nullable|array|max:10',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $data['type']      = $type;
        $data['available'] = $request->boolean('available');
        if ($isGuest) {
            $data['seller_id'] = null;
        }
        unset($data['images']);

        $item->update($data);

        if ($request->hasFile('images')) {
            $next = ($item->images()->max('sort_order') ?? -1) + 1;
            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('marketplace/items', 'public');
                \App\Models\MarketplaceItemImage::create([
                    'marketplace_item_id' => $item->id,
                    'path'                => $path,
                    'disk'                => 'public',
                    'sort_order'          => $next + $i,
                ]);
            }
        }

        return redirect()->route('admin.marketplace')->with('success', 'Item updated successfully.');
    }

    public function destroyMarketplace(MarketplaceItem $item)
    {
        foreach ($item->images as $img) {
            \Illuminate\Support\Facades\Storage::disk($img->disk)->delete($img->path);
        }
        $item->delete();
        return redirect()->route('admin.marketplace')->with('success', 'Item deleted.');
    }

    public function destroyMarketplaceImage(\App\Models\MarketplaceItemImage $image)
    {
        \Illuminate\Support\Facades\Storage::disk($image->disk)->delete($image->path);
        $image->delete();
        return response()->json(['message' => 'Image deleted.']);
    }

    // ─── Ride Pricing ────────────────────────────────────────────────────────

    public function ridePricing()
    {
        return view('admin.ride-pricing', [
            'pricing'  => RidePricing::orderBy('id')->get(),
            'settings' => PricingSetting::orderBy('key')->get()->keyBy('key'),
        ]);
    }

    public function updateRidePricing(Request $request, RidePricing $pricing)
    {
        $data = $request->validate([
            'label'       => 'required|string|max:100',
            'icon'        => 'required|string|max:50',
            'base'        => 'required|integer|min:0',
            'per_km'      => 'required|integer|min:0',
            'per_min'     => 'required|integer|min:0',
            'booking_fee' => 'required|integer|min:0',
            'minimum'     => 'required|integer|min:0',
            'capacity'    => 'required|integer|min:1|max:20',
            'active'      => 'boolean',
        ]);

        $data['active'] = $request->boolean('active');
        $pricing->update($data);

        FareService::clearCache();

        return redirect()->route('admin.ride-pricing')
            ->with('success', "Pricing for \"{$pricing->label}\" updated.");
    }

    public function updatePricingSettings(Request $request)
    {
        $data = $request->validate([
            'night_surcharge_rate'           => 'required|numeric|min:0|max:1',
            'delivery_night_surcharge_rate'  => 'required|numeric|min:0|max:1',
            'delivery_express_multiplier'    => 'required|numeric|min:1|max:10',
            'avg_city_speed_kmh'             => 'required|integer|min:5|max:120',
            'traffic_speed_threshold_kmh'    => 'required|integer|min:5|max:60',
        ]);

        foreach ($data as $key => $value) {
            PricingSetting::set($key, $value);
        }

        FareService::clearCache();

        return redirect()->route('admin.ride-pricing')
            ->with('success', 'Global pricing settings saved.');
    }

    // ─── Admin Chat ──────────────────────────────────────────────────────────

    public function adminChat()
    {
        $admin = Auth::user();

        $conversations = ChatConversation::with(['passenger', 'driver', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->where('passenger_id', $admin->id)
            ->orWhere('driver_id', $admin->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.chat', [
            'conversations' => $conversations,
            'users'         => User::whereIn('role', ['driver', 'passenger'])->orderBy('name')->get(),
            'admin'         => $admin,
        ]);
    }

    public function adminChatMessages(ChatConversation $conversation)
    {
        $admin = Auth::user();

        if (! in_array($admin->id, [$conversation->passenger_id, $conversation->driver_id])) {
            abort(403);
        }

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'message'    => $m->message,
                'sender_id'  => $m->sender_id,
                'sender'     => $m->sender?->name,
                'is_admin'   => $m->sender_id === $admin->id,
                'time'       => $m->created_at->format('H:i'),
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        // Mark messages from the other party as read.
        $conversation->messages()
            ->where('sender_id', '!=', $admin->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    public function adminChatStart(Request $request)
    {
        $admin = Auth::user();

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string|max:2000',
        ]);

        $target = User::findOrFail($data['user_id']);

        // Admin always occupies the passenger slot; target occupies driver slot.
        // For passengers with no driver slot, we still use this convention.
        $existing = ChatConversation::where('passenger_id', $admin->id)
            ->where('driver_id', $target->id)
            ->first();

        if (! $existing) {
            $existing = ChatConversation::create([
                'passenger_id' => $admin->id,
                'driver_id'    => $target->id,
                'topic'        => 'admin_support',
                'status'       => 'open',
            ]);
        }

        ChatMessage::create([
            'conversation_id' => $existing->id,
            'sender_id'       => $admin->id,
            'message'         => $data['message'],
        ]);

        $existing->touch();

        return redirect()->route('admin.chat', ['open' => $existing->id]);
    }

    public function adminChatSend(Request $request, ChatConversation $conversation)
    {
        $admin = Auth::user();

        if (! in_array($admin->id, [$conversation->passenger_id, $conversation->driver_id])) {
            abort(403);
        }

        $data = $request->validate(['message' => 'required|string|max:2000']);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $admin->id,
            'message'         => $data['message'],
        ]);

        $conversation->touch();

        return response()->json([
            'id'        => $message->id,
            'message'   => $message->message,
            'sender_id' => $message->sender_id,
            'is_admin'  => true,
            'time'      => $message->created_at->format('H:i'),
        ]);
    }

    // ─── Surge Zones ─────────────────────────────────────────────────────────

    public function surgeZones()
    {
        return view('admin.surge-zones', [
            'zones' => SurgeZone::orderBy('active')->orderBy('multiplier')->paginate(20),
        ]);
    }

    public function storeSurgeZone(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'center_lat'  => 'required|numeric|between:-90,90',
            'center_lng'  => 'required|numeric|between:-180,180',
            'radius_km'   => 'required|numeric|min:0.1|max:100',
            'multiplier'  => 'required|numeric|min:1.1|max:5.0',
            'type'        => 'required|in:rides,deliveries,delivery,moving,both',
            'active'               => 'boolean',
            'starts_at'            => 'nullable|date',
            'ends_at'              => 'nullable|date|after_or_equal:starts_at',
            'schedule_days'        => 'nullable|array',
            'schedule_days.*'      => 'integer|between:0,6',
            'schedule_start_time'  => 'nullable|date_format:H:i',
            'schedule_end_time'    => 'nullable|date_format:H:i|after:schedule_start_time',
        ]);

        $data['active']        = $request->boolean('active', true);
        $data['schedule_days'] = ! empty($data['schedule_days']) ? array_map('intval', $data['schedule_days']) : null;
        SurgeZone::create($data);

        return redirect()->route('admin.surge-zones')->with('success', 'Surge zone created.');
    }

    public function updateSurgeZone(Request $request, SurgeZone $surgeZone)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string',
            'center_lat'           => 'required|numeric|between:-90,90',
            'center_lng'           => 'required|numeric|between:-180,180',
            'radius_km'            => 'required|numeric|min:0.1|max:100',
            'multiplier'           => 'required|numeric|min:1.1|max:5.0',
            'type'                 => 'required|in:rides,deliveries,both',
            'active'               => 'boolean',
            'starts_at'            => 'nullable|date',
            'ends_at'              => 'nullable|date|after_or_equal:starts_at',
            'schedule_days'        => 'nullable|array',
            'schedule_days.*'      => 'integer|between:0,6',
            'schedule_start_time'  => 'nullable|date_format:H:i',
            'schedule_end_time'    => 'nullable|date_format:H:i',
        ]);

        $data['active']        = $request->boolean('active');
        $data['schedule_days'] = ! empty($data['schedule_days']) ? array_map('intval', $data['schedule_days']) : null;
        $surgeZone->update($data);

        return redirect()->route('admin.surge-zones')->with('success', 'Surge zone updated.');
    }

    public function toggleSurgeZone(SurgeZone $surgeZone)
    {
        $surgeZone->update(['active' => ! $surgeZone->active]);

        return redirect()->route('admin.surge-zones')
            ->with('success', "Surge zone \"{$surgeZone->name}\" " . ($surgeZone->active ? 'deactivated' : 'activated') . '.');
    }

    public function destroySurgeZone(SurgeZone $surgeZone)
    {
        $surgeZone->delete();
        return redirect()->route('admin.surge-zones')->with('success', 'Surge zone deleted.');
    }

    // ─── Charging Stations ───────────────────────────────────────────────────

    public function chargingStations()
    {
        return view('admin.charging-stations', [
            'stations' => ChargingStation::orderBy('created_at')->paginate(20),
        ]);
    }

    public function storeChargingStation(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'address'         => 'required|string|max:255',
            'latitude'        => 'required|numeric|between:-90,90',
            'longitude'       => 'required|numeric|between:-180,180',
            'available_ports' => 'required|integer|min:0',
            'operator'        => 'nullable|string|max:100',
            'rating'          => 'nullable|numeric|between:0,5',
            'details'         => 'nullable|string',
        ]);

        ChargingStation::create($data);

        return redirect()->route('admin.charging-stations')->with('success', 'Charging station created successfully.');
    }

    public function updateChargingStation(Request $request, ChargingStation $station)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'address'         => 'required|string|max:255',
            'latitude'        => 'required|numeric|between:-90,90',
            'longitude'       => 'required|numeric|between:-180,180',
            'available_ports' => 'required|integer|min:0',
            'operator'        => 'nullable|string|max:100',
            'rating'          => 'nullable|numeric|between:0,5',
            'details'         => 'nullable|string',
        ]);

        $station->update($data);

        return redirect()->route('admin.charging-stations')->with('success', 'Charging station updated successfully.');
    }

    public function destroyChargingStation(ChargingStation $station)
    {
        $station->delete();
        return redirect()->route('admin.charging-stations')->with('success', 'Charging station deleted.');
    }

    // ─── Support ─────────────────────────────────────────────────────────────

    public function support()
    {
        return view('admin.support', [
            'tickets' => SupportTicket::with('user')->orderBy('created_at')->paginate(20),
            'users'   => User::orderBy('name')->get(),
            'admins'  => User::where('role', 'admin')->orderBy('name')->get(),
        ]);
    }

    public function storeSupport(Request $request)
    {
        $data = $request->validate([
            'user_id'     => 'required|exists:users,id',
            'subject'     => 'required|string|max:255',
            'status'      => 'required|in:open,in_progress,resolved,closed',
            'priority'    => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        SupportTicket::create($data);

        return redirect()->route('admin.support')->with('success', 'Support ticket created successfully.');
    }

    public function updateSupport(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'user_id'     => 'required|exists:users,id',
            'subject'     => 'required|string|max:255',
            'status'      => 'required|in:open,in_progress,resolved,closed',
            'priority'    => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $ticket->update($data);

        return redirect()->route('admin.support')->with('success', 'Support ticket updated successfully.');
    }

    public function destroySupport(SupportTicket $ticket)
    {
        $ticket->delete();
        return redirect()->route('admin.support')->with('success', 'Support ticket deleted.');
    }

    // ─── Safety ──────────────────────────────────────────────────────────────

    public function safety()
    {
        return view('admin.safety', [
            'incidents' => SafetyIncident::with('user')->orderBy('created_at')->paginate(20),
            'users'     => User::orderBy('name')->get(),
        ]);
    }

    public function storeSafety(Request $request)
    {
        $data = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'incident_type' => 'required|in:accident,harassment,theft,other',
            'description'   => 'required|string',
            'status'        => 'required|in:reported,investigating,resolved,closed',
        ]);

        SafetyIncident::create($data);

        return redirect()->route('admin.safety')->with('success', 'Safety incident created successfully.');
    }

    public function updateSafety(Request $request, SafetyIncident $incident)
    {
        $data = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'incident_type' => 'required|in:accident,harassment,theft,other',
            'description'   => 'required|string',
            'status'        => 'required|in:reported,investigating,resolved,closed',
        ]);

        $incident->update($data);

        return redirect()->route('admin.safety')->with('success', 'Safety incident updated successfully.');
    }

    public function destroySafety(SafetyIncident $incident)
    {
        $incident->delete();
        return redirect()->route('admin.safety')->with('success', 'Safety incident deleted.');
    }

    // ─── Transaction Records ─────────────────────────────────────────────────

    public function transactions(Request $request)
    {
        $query = TransactionRecord::with(['payer', 'payee', 'processedBy'])
            ->orderBy('created_at');

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return view('admin.transactions', [
            'transactions' => $query->paginate(30)->withQueryString(),
            'pending_cash' => TransactionRecord::where('status', 'pending')
                ->where('payment_method', 'cash')->count(),
            'pending_online' => TransactionRecord::where('status', 'pending')
                ->whereIn('payment_method', ['aba', 'wing', 'other_online'])->count(),
        ]);
    }

    public function confirmTransaction(TransactionRecord $transaction)
    {
        if (! $transaction->isPending()) {
            return redirect()->route('admin.transactions')->with('error', 'Transaction already processed.');
        }

        app(PaymentService::class)->confirm($transaction, Auth::user());

        return redirect()->route('admin.transactions')
            ->with('success', "Transaction #{$transaction->id} confirmed — " . number_format($transaction->gross_amount, 0) . " ៛ credited to driver.");
    }

    public function cancelTransaction(Request $request, TransactionRecord $transaction)
    {
        if (! $transaction->isPending()) {
            return redirect()->route('admin.transactions')->with('error', 'Transaction is not pending.');
        }

        $data = $request->validate(['note' => 'nullable|string|max:500']);
        app(PaymentService::class)->cancel($transaction, Auth::user(), $data['note'] ?? '');

        return redirect()->route('admin.transactions')->with('success', "Transaction #{$transaction->id} cancelled.");
    }

    // ─── Companies ────────────────────────────────────────────────────────────

    public function companies()
    {
        return view('admin.companies', [
            'companies' => Company::withCount('drivers')->orderBy('created_at')->paginate(20),
        ]);
    }

    public function storeCompany(Request $request)
    {
        $data = $request->validate([
            'name'                     => 'required|string|max:255',
            'phone'                    => 'nullable|string|max:24',
            'email'                    => 'nullable|email|max:255',
            'address'                  => 'nullable|string|max:255',
            'platform_commission_rate' => 'nullable|numeric|min:0|max:100',
            'company_commission_rate'  => 'nullable|numeric|min:0|max:100',
            'rental_daily_rate'        => 'nullable|integer|min:0',
            'active'                   => 'boolean',
        ]);

        $data['active'] = $request->boolean('active', true);
        Company::create($data);

        return redirect()->route('admin.companies')->with('success', 'Company created.');
    }

    public function updateCompany(Request $request, Company $company)
    {
        $data = $request->validate([
            'name'                     => 'required|string|max:255',
            'phone'                    => 'nullable|string|max:24',
            'email'                    => 'nullable|email|max:255',
            'address'                  => 'nullable|string|max:255',
            'platform_commission_rate' => 'nullable|numeric|min:0|max:100',
            'company_commission_rate'  => 'nullable|numeric|min:0|max:100',
            'rental_daily_rate'        => 'nullable|integer|min:0',
            'active'                   => 'boolean',
        ]);

        $data['active'] = $request->boolean('active');
        $company->update($data);

        return redirect()->route('admin.companies')->with('success', 'Company updated.');
    }

    public function destroyCompany(Company $company)
    {
        $company->delete();
        return redirect()->route('admin.companies')->with('success', 'Company deleted.');
    }

    // ─── Wallet / Transactions ────────────────────────────────────────────────

    public function walletTransactions()
    {
        return view('admin.wallet', [
            'transactions' => WalletTransaction::with('user')
                ->orderBy('created_at')
                ->paginate(30),
        ]);
    }

    public function paySalary(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount'  => 'required|integer|min:1000',
            'note'    => 'nullable|string|max:255',
        ]);

        $user = User::findOrFail($data['user_id']);
        app(WalletService::class)->paySalary($user, $data['amount'], Auth::user(), $data['note'] ?? '');

        return redirect()->route('admin.wallet')->with('success', "Salary of " . number_format($data['amount'], 0) . " ៛ paid to {$user->name}.");
    }

    public function adminCredit(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount'  => 'required|integer|min:100',
            'type'    => 'required|in:bonus,adjustment,top_up',
            'note'    => 'nullable|string|max:255',
        ]);

        $user = User::findOrFail($data['user_id']);
        app(WalletService::class)->credit($user, $data['amount'], $data['type'], $data['note'] ?? 'Admin credit', null, Auth::id());

        return redirect()->route('admin.wallet')->with('success', "Credit of " . number_format($data['amount'], 0) . " ៛ added to {$user->name}.");
    }

    // ─── Top-up Requests ─────────────────────────────────────────────────────

    public function topups()
    {
        return view('admin.topups', [
            'pending'  => TopUpRequest::with('user')->where('status', 'pending')->orderBy('created_at')->get(),
            'history'  => TopUpRequest::with(['user', 'approvedBy'])->whereIn('status', ['approved', 'rejected'])->orderBy('updated_at')->paginate(20),
        ]);
    }

    public function approveTopUp(TopUpRequest $topup)
    {
        if ($topup->status !== 'pending') {
            return redirect()->route('admin.topups')->with('error', 'Request already processed.');
        }

        app(WalletService::class)->approveTopUp($topup, Auth::user());

        return redirect()->route('admin.topups')->with('success', "Top-up of " . number_format($topup->amount, 0) . " ៛ approved for {$topup->user->name}.");
    }

    public function rejectTopUp(Request $request, TopUpRequest $topup)
    {
        if ($topup->status !== 'pending') {
            return redirect()->route('admin.topups')->with('error', 'Request already processed.');
        }

        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        $topup->update([
            'status'      => 'rejected',
            'admin_note'  => $data['admin_note'] ?? null,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return redirect()->route('admin.topups')->with('success', "Top-up request rejected.");
    }

    // ─── Moving Fare Pricing ─────────────────────────────────────────────────

    public function movingFare()
    {
        $keys = [
            'moving_base_fee', 'moving_truck_fee', 'moving_distance_rate',
            'moving_helper_rate_normal', 'moving_helper_rate_heavy',
            'moving_no_elevator_mult',
            'moving_floor_fee_tier_1', 'moving_floor_fee_tier_3',
            'moving_floor_fee_tier_6', 'moving_floor_fee_tier_7plus',
        ];

        $settings = PricingSetting::whereIn('key', $keys)->get()->keyBy('key');

        return view('admin.moving-fare', compact('settings'));
    }

    public function updateMovingFare(Request $request)
    {
        $data = $request->validate([
            'moving_base_fee'             => 'required|integer|min:0',
            'moving_truck_fee'            => 'required|integer|min:0',
            'moving_distance_rate'        => 'required|integer|min:0',
            'moving_helper_rate_normal'   => 'required|integer|min:0',
            'moving_helper_rate_heavy'    => 'required|integer|min:0',
            'moving_no_elevator_mult'     => 'required|numeric|min:1|max:5',
            'moving_floor_fee_tier_1'     => 'required|integer|min:0',
            'moving_floor_fee_tier_3'     => 'required|integer|min:0',
            'moving_floor_fee_tier_6'     => 'required|integer|min:0',
            'moving_floor_fee_tier_7plus' => 'required|integer|min:0',
        ]);

        foreach ($data as $key => $value) {
            PricingSetting::where('key', $key)->update(['value' => $value]);
        }

        return redirect()->route('admin.moving-fare')->with('success', 'Moving fare rates saved.');
    }

    // ─── Driver Approvals ─────────────────────────────────────────────────────

    public function drivers(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = User::where('role', 'driver')
            ->withCount('driverDocuments')
            ->orderBy('created_at', 'desc');

        if (in_array($status, ['pending','approved','rejected'])) {
            $query->where('approval_status', $status);
        }

        return view('admin.drivers', [
            'drivers' => $query->paginate(20)->appends(['status' => $status]),
            'status' => $status,
            'counts' => [
                'pending'  => User::where('role','driver')->where('approval_status','pending')->count(),
                'approved' => User::where('role','driver')->where('approval_status','approved')->count(),
                'rejected' => User::where('role','driver')->where('approval_status','rejected')->count(),
            ],
        ]);
    }

    public function showDriver(User $driver)
    {
        $documents = $driver->driverDocuments()->orderBy('type')->get();

        return view('admin.driver-detail', [
            'driver'    => $driver,
            'documents' => $documents,
            'vehicle'   => $driver->vehicles()->latest()->first(),
        ]);
    }

    public function approveDriver(Request $request, User $driver)
    {
        $data = $request->validate([
            'action'       => 'required|in:approve,reject',
            'service_zone' => 'nullable|string|max:100',
            'reason'       => 'nullable|string|max:500',
        ]);

        $update = [
            'approval_status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'approved_at'     => $data['action'] === 'approve' ? now() : null,
            'status_note'     => $data['reason'] ?? null,
        ];

        if (! empty($data['service_zone'])) {
            $update['service_zone'] = $data['service_zone'];
        }

        $driver->update($update);

        $label = $data['action'] === 'approve' ? 'approved' : 'rejected';

        return redirect()->route('admin.drivers.show', $driver)
            ->with('success', "Driver {$driver->name} has been {$label}.");
    }

    public function reviewDocument(Request $request, User $driver, \App\Models\DriverDocument $document)
    {
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'note'   => 'nullable|string|max:500',
        ]);

        $document->update([
            'status'      => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'admin_note'  => $data['note'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Document ' . $data['action'] . 'd.');
    }

    // ─── Withdrawal Requests ─────────────────────────────────────────────────

    public function withdrawals(Request $request)
    {
        $status = $request->input('status', 'pending');

        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        return view('admin.withdrawals', [
            'withdrawals' => rescue(
                fn () => WithdrawalRequest::with('driver')
                    ->where('status', $status)
                    ->orderBy('created_at')
                    ->paginate(20),
                $emptyPage,
                false
            ),
            'status' => $status,
            'counts' => [
                'pending'  => rescue(fn () => WithdrawalRequest::where('status', 'pending')->count(), 0, false),
                'approved' => rescue(fn () => WithdrawalRequest::where('status', 'approved')->count(), 0, false),
                'rejected' => rescue(fn () => WithdrawalRequest::where('status', 'rejected')->count(), 0, false),
            ],
        ]);
    }

    public function approveWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return redirect()->route('admin.withdrawals')->with('error', 'Already processed.');
        }

        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        $withdrawal->update([
            'status'       => 'approved',
            'admin_note'   => $data['admin_note'] ?? null,
            'processed_at' => now(),
            'processed_by' => Auth::id(),
        ]);

        return redirect()->route('admin.withdrawals')
            ->with('success', number_format($withdrawal->amount_khr) . ' ៛ withdrawal approved for ' . $withdrawal->driver->name . '.');
    }

    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return redirect()->route('admin.withdrawals')->with('error', 'Already processed.');
        }

        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        // Return funds to driver wallet
        app(\App\Services\WalletService::class)->credit(
            $withdrawal->driver,
            $withdrawal->amount_khr,
            'withdrawal_rejected',
            'Withdrawal request rejected — funds returned'
        );

        $withdrawal->update([
            'status'       => 'rejected',
            'admin_note'   => $data['admin_note'] ?? null,
            'processed_at' => now(),
            'processed_by' => Auth::id(),
        ]);

        return redirect()->route('admin.withdrawals')
            ->with('success', 'Withdrawal rejected and funds returned to driver wallet.');
    }

    // ─── Banners ─────────────────────────────────────────────────────────────

    public function banners()
    {
        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        return view('admin.banners', [
            'banners' => rescue(
                fn () => Banner::orderBy('sort_order')->orderByDesc('created_at')->paginate(20),
                $emptyPage,
                false
            ),
        ]);
    }

    public function storeBanner(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'deeplink'    => 'nullable|string|max:255',
            'target_role' => 'required|in:all,passenger,driver',
            'sort_order'  => 'nullable|integer|min:0',
            'valid_from'  => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'active'      => 'boolean',
            'image'       => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $data['image']      = $request->file('image')->store('banners', 'public');
        $data['active']     = $request->boolean('active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        Banner::create($data);

        return redirect()->route('admin.banners')->with('success', 'Banner created.');
    }

    public function updateBanner(Request $request, Banner $banner)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'deeplink'    => 'nullable|string|max:255',
            'target_role' => 'required|in:all,passenger,driver',
            'sort_order'  => 'nullable|integer|min:0',
            'valid_from'  => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'active'      => 'boolean',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        } else {
            unset($data['image']);
        }

        $data['active']     = $request->boolean('active');
        $data['sort_order'] = $data['sort_order'] ?? $banner->sort_order;

        $banner->update($data);

        return redirect()->route('admin.banners')->with('success', 'Banner updated.');
    }

    public function destroyBanner(Banner $banner)
    {
        $banner->delete();
        return redirect()->route('admin.banners')->with('success', 'Banner deleted.');
    }

    // ─── Airport Zones ────────────────────────────────────────────────────────

    public function airportZones()
    {
        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        return view('admin.airport-zones', [
            'zones' => rescue(fn () => AirportZone::orderBy('sort_order')->orderBy('name')->paginate(20), $emptyPage, false),
        ]);
    }

    public function storeAirportZone(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'iata_code'       => 'required|string|max:4|unique:airport_zones',
            'latitude'        => 'required|numeric|between:-90,90',
            'longitude'       => 'required|numeric|between:-180,180',
            'radius_meters'   => 'required|integer|min:100|max:10000',
            'surcharge_khr'   => 'required|integer|min:0',
            'luggage_fee_khr' => 'required|integer|min:0',
            'active'          => 'boolean',
        ]);
        $data['iata_code'] = strtoupper($data['iata_code']);
        $data['active']    = $request->boolean('active', true);
        AirportZone::create($data);
        return redirect()->route('admin.airport-zones')->with('success', 'Airport zone created.');
    }

    public function updateAirportZone(Request $request, AirportZone $zone)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'latitude'        => 'required|numeric|between:-90,90',
            'longitude'       => 'required|numeric|between:-180,180',
            'radius_meters'   => 'required|integer|min:100|max:10000',
            'surcharge_khr'   => 'required|integer|min:0',
            'luggage_fee_khr' => 'required|integer|min:0',
            'active'          => 'boolean',
        ]);
        $data['active'] = $request->boolean('active');
        $zone->update($data);
        return redirect()->route('admin.airport-zones')->with('success', 'Airport zone updated.');
    }

    public function destroyAirportZone(AirportZone $zone)
    {
        $zone->delete();
        return redirect()->route('admin.airport-zones')->with('success', 'Airport zone deleted.');
    }

    // ─── Business Accounts ────────────────────────────────────────────────────

    public function businessAccounts(Request $request)
    {
        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        return view('admin.business-accounts', [
            'accounts' => rescue(fn () => BusinessAccount::with('owner:id,name,email')
                ->withCount('members')
                ->orderByDesc('created_at')
                ->paginate(20), $emptyPage, false),
        ]);
    }

    public function showBusinessAccount(BusinessAccount $account)
    {
        $account->load(['owner:id,name,email', 'members.user:id,name,email,phone']);
        $recentRides = rescue(fn () => \App\Models\Ride::where('business_account_id', $account->id)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get(), collect(), false);

        return view('admin.business-account-detail', compact('account', 'recentRides'));
    }

    public function updateBusinessAccount(Request $request, BusinessAccount $account)
    {
        $data = $request->validate([
            'monthly_credit_limit_khr' => 'required|integer|min:0',
            'active'                   => 'boolean',
        ]);
        $data['active'] = $request->boolean('active');
        $account->update($data);
        return redirect()->route('admin.business-accounts')->with('success', 'Business account updated.');
    }

    // ─── Subscription Plans ───────────────────────────────────────────────────

    public function subscriptionPlans()
    {
        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        $plans = rescue(fn () => SubscriptionPlan::orderBy('sort_order')->paginate(20), $emptyPage, false);

        $stats = [
            'total_active'      => rescue(fn () => UserSubscription::where('status', 'active')->count(), 0, false),
            'revenue_month'     => rescue(fn () => \App\Models\SubscriptionTransaction::where('status', 'paid')
                ->where('created_at', '>=', now()->startOfMonth())->sum('amount_khr'), 0, false),
            'cancelled_month'   => rescue(fn () => UserSubscription::where('status', 'cancelled')
                ->where('cancelled_at', '>=', now()->startOfMonth())->count(), 0, false),
        ];

        return view('admin.subscription-plans', compact('plans', 'stats'));
    }

    public function storeSubscriptionPlan(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:80',
            'slug'                  => 'required|string|max:40|unique:subscription_plans',
            'description'           => 'nullable|string',
            'price_khr'             => 'required|integer|min:0',
            'billing_cycle'         => 'required|in:weekly,monthly,yearly',
            'ride_credit_khr'       => 'nullable|integer|min:0',
            'ride_discount_pct'     => 'nullable|integer|min:0|max:100',
            'delivery_discount_pct' => 'nullable|integer|min:0|max:100',
            'free_cancellations'    => 'nullable|integer|min:0',
            'surge_waived'          => 'boolean',
            'priority_matching'     => 'boolean',
            'bonus_points_pct'      => 'nullable|integer|min:0|max:200',
            'badge_color'           => 'nullable|string|max:20',
            'sort_order'            => 'nullable|integer|min:0',
            'active'                => 'boolean',
        ]);

        $data['slug']               = \Illuminate\Support\Str::slug($data['slug']);
        $data['surge_waived']       = $request->boolean('surge_waived');
        $data['priority_matching']  = $request->boolean('priority_matching');
        $data['active']             = $request->boolean('active', true);
        $data['ride_credit_khr']    = $data['ride_credit_khr'] ?? 0;
        $data['ride_discount_pct']  = $data['ride_discount_pct'] ?? 0;
        $data['delivery_discount_pct'] = $data['delivery_discount_pct'] ?? 0;
        $data['free_cancellations'] = $data['free_cancellations'] ?? 0;
        $data['bonus_points_pct']   = $data['bonus_points_pct'] ?? 0;
        $data['sort_order']         = $data['sort_order'] ?? 0;

        SubscriptionPlan::create($data);

        return redirect()->route('admin.subscription-plans')->with('success', 'Plan created.');
    }

    public function updateSubscriptionPlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:80',
            'description'           => 'nullable|string',
            'price_khr'             => 'required|integer|min:0',
            'billing_cycle'         => 'required|in:weekly,monthly,yearly',
            'ride_credit_khr'       => 'nullable|integer|min:0',
            'ride_discount_pct'     => 'nullable|integer|min:0|max:100',
            'delivery_discount_pct' => 'nullable|integer|min:0|max:100',
            'free_cancellations'    => 'nullable|integer|min:0',
            'surge_waived'          => 'boolean',
            'priority_matching'     => 'boolean',
            'bonus_points_pct'      => 'nullable|integer|min:0|max:200',
            'badge_color'           => 'nullable|string|max:20',
            'sort_order'            => 'nullable|integer|min:0',
            'active'                => 'boolean',
        ]);

        $data['surge_waived']          = $request->boolean('surge_waived');
        $data['priority_matching']     = $request->boolean('priority_matching');
        $data['active']                = $request->boolean('active');
        $data['ride_credit_khr']       = $data['ride_credit_khr'] ?? 0;
        $data['ride_discount_pct']     = $data['ride_discount_pct'] ?? 0;
        $data['delivery_discount_pct'] = $data['delivery_discount_pct'] ?? 0;
        $data['free_cancellations']    = $data['free_cancellations'] ?? 0;
        $data['bonus_points_pct']      = $data['bonus_points_pct'] ?? 0;

        $plan->update($data);

        return redirect()->route('admin.subscription-plans')->with('success', 'Plan updated.');
    }

    public function destroySubscriptionPlan(SubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            return redirect()->route('admin.subscription-plans')
                ->with('error', 'Cannot delete a plan with active subscribers.');
        }

        $plan->update(['active' => false]);

        return redirect()->route('admin.subscription-plans')->with('success', 'Plan deactivated.');
    }

    public function subscriptionSubscribers(SubscriptionPlan $plan)
    {
        $subscribers = rescue(
            fn () => UserSubscription::with('user:id,name,email,phone')
                ->where('subscription_plan_id', $plan->id)
                ->orderByDesc('created_at')
                ->paginate(30),
            new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30),
            false
        );

        return view('admin.subscription-subscribers', compact('plan', 'subscribers'));
    }
}
