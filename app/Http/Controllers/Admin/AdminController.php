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
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceVehicleColor;
use App\Models\MarketplaceVehicleSize;
use App\Models\MarketplaceVehicleType;
use App\Models\MovingFloorFeeTier;
use App\Models\PromoEvent;
use App\Models\MarketplaceProductImage;
use App\Models\Ride;
use App\Models\SafetyIncident;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SurgeZone;
use App\Models\TopUpRequest;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Models\AirportZone;
use App\Jobs\SendPromoEventPush;
use App\Models\Banner;
use App\Models\BusinessAccount;
use App\Models\MembershipTier;
use App\Models\PartnerContract;
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
                'marketplace'         => MarketplaceProduct::count(),
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

    public function users(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $role   = $request->input('role', '');

        $query = User::with('company')->orderBy('created_at');

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('admin.users', [
            'users'     => $query->paginate(10)->appends(['search' => $search, 'role' => $role]),
            'companies' => Company::where('active', true)->orderBy('name')->get(),
            'search'    => $search,
            'role'      => $role,
            'roleCounts' => User::selectRaw('role, count(*) as c')->groupBy('role')->pluck('c', 'role'),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:6',
            'phone'           => 'nullable|string|max:20',
            'role'            => 'required|in:admin,driver,passenger,partner',
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
            'role'            => 'required|in:admin,driver,passenger,partner',
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

    // ─── Roles & Permissions (Spatie) ─────────────────────────────────────────

    public function roles()
    {
        return view('admin.roles', [
            'roles'       => \Spatie\Permission\Models\Role::with('permissions')->orderBy('name')->get(),
            'permissions' => \Spatie\Permission\Models\Permission::orderBy('name')->get(),
            'admins'      => User::where('role', 'admin')->with('roles')->orderBy('name')->get(),
        ]);
    }

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = \Spatie\Permission\Models\Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles')->with('success', "Role \"{$role->name}\" created.");
    }

    public function updateRole(Request $request, \Spatie\Permission\Models\Role $role)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name,' . $role->id,
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles')->with('success', "Role \"{$role->name}\" updated.");
    }

    public function destroyRole(\Spatie\Permission\Models\Role $role)
    {
        if ($role->name === 'Super Admin') {
            return redirect()->route('admin.roles')->with('error', 'The Super Admin role cannot be deleted.');
        }

        $role->delete();
        return redirect()->route('admin.roles')->with('success', 'Role deleted.');
    }

    public function assignRole(Request $request)
    {
        $data = $request->validate([
            'user_id'  => 'required|exists:users,id',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $user  = User::findOrFail($data['user_id']);
        $roles = \Spatie\Permission\Models\Role::whereIn('id', $data['role_ids'] ?? [])->get();
        $user->syncRoles($roles);

        return redirect()->route('admin.roles')->with('success', "Roles updated for {$user->name}.");
    }

    // ─── Vehicles ────────────────────────────────────────────────────────────

    public function vehicles(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Vehicle::with('driver')->orderBy('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('license_plate', 'like', "%{$search}%")
                  ->orWhereHas('driver', function ($dq) use ($search) {
                      $dq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        return view('admin.vehicles', [
            'vehicles' => $query->paginate(10)->appends(['search' => $search]),
            'drivers'  => User::where('role', 'driver')->orderBy('name')->get(),
            'search'   => $search,
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

    public function rides(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $tab    = $request->input('tab', 'all');

        $pendingStatuses = ['requested', 'pending', 'accepted', 'driver_arrived', 'in_progress'];

        $query = Ride::with(['passenger', 'driver', 'stops'])->orderByDesc('created_at');

        if ($tab === 'completed') {
            $query->where('status', 'completed');
        } elseif ($tab === 'cancelled') {
            $query->where('status', 'cancelled');
        } elseif ($tab === 'pending') {
            $query->whereIn('status', $pendingStatuses);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', $search);
                }
                $q->orWhereHas('passenger', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('driver', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
                });
            });
        }

        return view('admin.rides', [
            'rides'         => $query->paginate(15)->appends(['search' => $search, 'tab' => $tab]),
            'passengers'    => User::where('role', 'passenger')->orderBy('name')->get(),
            'drivers'       => User::where('role', 'driver')->orderBy('name')->get(),
            'commissionPct' => (float) \App\Models\PricingSetting::get('driver_commission_pct', 20),
            'search'        => $search,
            'tab'           => $tab,
            'counts'        => [
                'all'       => Ride::count(),
                'completed' => Ride::where('status', 'completed')->count(),
                'pending'   => Ride::whereIn('status', $pendingStatuses)->count(),
                'cancelled' => Ride::where('status', 'cancelled')->count(),
            ],
        ]);
    }

    public function showRide(\App\Models\Ride $ride)
    {
        $ride->load(['passenger', 'driver', 'vehicle', 'stops']);
        return view('admin.ride-detail', compact('ride'));
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
        $type      = $request->input('type', 'all');
        $status    = $request->input('status');
        $search    = $request->input('search');
        $partnerId = $request->input('partner_id');

        $query = Delivery::with(['sender', 'driver', 'partner'])->orderBy('created_at', 'desc');

        if ($type !== 'all') {
            $query->where('service_type', $type);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('recipient_name', 'like', "%{$search}%")
                  ->orWhere('recipient_phone', 'like', "%{$search}%")
                  ->orWhere('sender_name', 'like', "%{$search}%")
                  ->orWhere('partner_reference', 'like', "%{$search}%");
            });
        }
        if ($partnerId) {
            $query->where('partner_id', $partnerId);
        }

        $deliveries = $query->paginate(10)->withQueryString();
        foreach ($deliveries as $delivery) {
            $delivery->ensureShareToken();
        }

        return view('admin.deliveries', [
            'deliveries'    => $deliveries,
            'senders'       => User::where('role', 'passenger')->orderBy('name')->get(),
            'drivers'       => User::where('role', 'driver')->orderBy('name')->get(),
            'partners'      => User::where('role', 'partner')->orderBy('name')->get(),
            'activeType'    => $type,
            'activeStatus'  => $status,
            'search'        => $search,
            'activePartner' => $partnerId,
            'commissionPct' => (float) \App\Models\PricingSetting::get('delivery_commission_pct', \App\Models\PricingSetting::get('driver_commission_pct', config('commission.platform_rate.owner', 25))),
            'counts'        => [
                'all'      => Delivery::count(),
                'delivery' => Delivery::where('service_type', 'delivery')->count(),
                'moving'   => Delivery::where('service_type', 'moving')->count(),
            ],
        ]);
    }

    public function showDelivery(Delivery $delivery)
    {
        // Older orders predate the tracking link — mint one so the page can always share it.
        $delivery->ensureShareToken();

        $delivery->load(['sender', 'driver.company', 'vehicle', 'partner', 'stops', 'promoCode', 'transactions']);
        $commissionPct = (float) \App\Models\PricingSetting::get('delivery_commission_pct', \App\Models\PricingSetting::get('driver_commission_pct', config('commission.platform_rate.owner', 25)));
        $driverCommRate = $delivery->driver?->commission_rate
            ?? $delivery->driver?->company?->platform_commission_rate
            ?? $commissionPct;
        $platformFee = (int) floor((($delivery->fee ?? 0) * $driverCommRate) / 100);
        $netDriver = max(0, ($delivery->fee ?? 0) - $platformFee);

        return view('admin.delivery-detail', compact('delivery', 'commissionPct', 'driverCommRate', 'platformFee', 'netDriver'));
    }

    public function completeDelivery(Delivery $delivery)
    {
        if ($delivery->status === 'cancelled' || $delivery->status === 'completed') {
            return back()->with('error', "Order #{$delivery->id} is already {$delivery->status}.");
        }

        $delivery->update([
            'status'       => 'completed',
            'completed_at' => $delivery->completed_at ?? now(),
        ]);

        // Settle payment / credit the driver. No-op if a QR scan already paid it out.
        try {
            app(\App\Services\PaymentService::class)->settleDelivery($delivery->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Order #{$delivery->id} marked as completed.");
    }

    public function storeDelivery(Request $request)
    {
        $data = $request->validate([
            'service_type'       => 'required|in:delivery,moving',
            'sender_id'          => 'required|exists:users,id',
            'sender_name'        => 'required|string|max:255',
            'recipient_name'     => 'required|string|max:255',
            'recipient_phone'    => 'required|string|max:24',
            'package_size'       => 'nullable|in:small,medium,large,extra_large',
            'driver_id'          => 'nullable|exists:users,id',
            'pickup_address'     => 'required|string|max:255',
            'dropoff_address'    => 'required|string|max:255',
            'status'             => 'required|in:requested,pending,accepted,in_progress,completed,cancelled',
            'fee'                => 'nullable|numeric|min:0',
            'package_amount'     => 'nullable|integer|min:0',
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
        $data['package_amount']     = (int) ($data['package_amount'] ?? 0);
        $data['payment_by']         = $data['payment_by'] ?? 'sender';
        $data['payment_method']     = $data['payment_method'] ?? 'cash';
        $data['payment_status']     = 'unpaid';
        $data['payment_model']      = $data['payment_model'] ?? 'customer_pays';
        $data['assigned_at']        = ! empty($data['driver_id']) ? now() : null;
        $data['has_elevator']       = (bool) ($data['has_elevator'] ?? false);
        $data['needs_stairs_carry'] = (bool) ($data['needs_stairs_carry'] ?? false);
        $data['heavy_items']        = (bool) ($data['heavy_items'] ?? false);

        // Auto-calculate delivery fee if not manually specified
        if (empty($data['fee']) || (int)$data['fee'] === 0) {
            if (($data['service_type'] ?? 'delivery') === 'delivery') {
                $pLat = !empty($data['pickup_lat']) ? (float)$data['pickup_lat'] : 11.5564;
                $pLng = !empty($data['pickup_lng']) ? (float)$data['pickup_lng'] : 104.9282;
                $dLat = !empty($data['dropoff_lat']) ? (float)$data['dropoff_lat'] : 11.5700;
                $dLng = !empty($data['dropoff_lng']) ? (float)$data['dropoff_lng'] : 104.9350;

                $fareService = app(\App\Services\FareService::class);
                $route = $fareService->getRoute($pLat, $pLng, $dLat, $dLng);
                $fareResult = $fareService->calculateDeliveryFare(
                    $data['package_size'] ?? 'small',
                    $route,
                    $pLat,
                    $pLng,
                    'delivery'
                );
                $data['fee'] = $fareResult['total'];
            }
        }

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
            'package_size'       => 'nullable|in:small,medium,large,extra_large',
            'driver_id'          => 'nullable|exists:users,id',
            'pickup_address'     => 'required|string|max:255',
            'dropoff_address'    => 'required|string|max:255',
            'status'             => 'required|in:requested,pending,accepted,in_progress,completed,cancelled',
            'fee'                => 'nullable|numeric|min:0',
            'package_amount'     => 'nullable|integer|min:0',
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

        if (array_key_exists('package_amount', $data)) {
            $data['package_amount'] = (int) ($data['package_amount'] ?? 0);
        }

        if (! empty($data['driver_id']) && ! $delivery->assigned_at) {
            $data['assigned_at'] = now();
        }

        // Auto-recalculate delivery fee if fee is empty/zero
        if (empty($data['fee']) || (int)$data['fee'] === 0) {
            if (($data['service_type'] ?? $delivery->service_type ?? 'delivery') === 'delivery') {
                $pLat = !empty($data['pickup_lat']) ? (float)$data['pickup_lat'] : ((float)$delivery->pickup_lat ?: 11.5564);
                $pLng = !empty($data['pickup_lng']) ? (float)$data['pickup_lng'] : ((float)$delivery->pickup_lng ?: 104.9282);
                $dLat = !empty($data['dropoff_lat']) ? (float)$data['dropoff_lat'] : ((float)$delivery->dropoff_lat ?: 11.5700);
                $dLng = !empty($data['dropoff_lng']) ? (float)$data['dropoff_lng'] : ((float)$delivery->dropoff_lng ?: 104.9350);

                $fareService = app(\App\Services\FareService::class);
                $route = $fareService->getRoute($pLat, $pLng, $dLat, $dLng);
                $fareResult = $fareService->calculateDeliveryFare(
                    $data['package_size'] ?? $delivery->package_size ?? 'small',
                    $route,
                    $pLat,
                    $pLng,
                    'delivery'
                );
                $data['fee'] = $fareResult['total'];
            }
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
            'driver_id'       => $data['driver_id'],
            'status'          => in_array($delivery->status, ['requested', 'pending']) ? 'accepted'
                                : (in_array($delivery->status, ['created']) ? 'assigned' : $delivery->status),
            'assigned_at'     => $delivery->assigned_at ?? now(),
            'assignment_type' => $delivery->assignment_type ?? 'manual',
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
            'items'      => MarketplaceProduct::with(['seller', 'images', 'category', 'marketplaceVehicleType', 'marketplaceVehicleColor', 'marketplaceVehicleSize'])->orderByDesc('created_at')->paginate(10),
            'categories' => MarketplaceCategory::where('active', true)->orderBy('sort_order')->get(),
            'vehicleTypes'  => MarketplaceVehicleType::active()->with(['sizes', 'categories', 'colors'])->orderBy('sort_order')->get(),
            'vehicleColors' => MarketplaceVehicleColor::active()->orderBy('sort_order')->get(),
            'vehicleSizes'  => MarketplaceVehicleSize::active()->orderBy('sort_order')->get(),
        ]);
    }

    public function storeMarketplace(Request $request)
    {
        $isSale  = $request->boolean('is_sale');
        $isRent  = $request->boolean('is_rent');

        if (!$isSale && !$isRent) {
            return back()->withErrors(['listing_type' => 'Please select at least one listing type.'])->withInput();
        }
        $listingType = ($isSale && $isRent) ? 'both' : ($isSale ? 'sale' : 'rent');

        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'price'              => $isSale ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'rent_price_per_day' => $isRent ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'category_id'                  => 'nullable|exists:marketplace_categories,id',
            'marketplace_vehicle_type_id'  => 'nullable|exists:marketplace_vehicle_types,id',
            'marketplace_vehicle_color_id' => 'nullable|exists:marketplace_vehicle_colors,id',
            'marketplace_vehicle_size_id'  => 'nullable|exists:marketplace_vehicle_sizes,id',
            'condition'          => 'required|in:new,used,refurbished',
            'status'             => 'required|in:active,paused,draft',
            'images'             => 'nullable|array|max:10',
            'images.*'           => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($error = $this->marketplaceVehicleTypeMismatchError($data)) {
            return back()->withErrors(['marketplace_vehicle_size_id' => $error])->withInput();
        }

        $data['listing_type'] = $listingType;
        $data['seller_id']    = Auth::id();
        $data['entry_type']   = 'user';
        unset($data['images']);

        try {
            $product = MarketplaceProduct::create($data);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $i => $file) {
                    $url = $file->store('marketplace/products', 'public');
                    MarketplaceProductImage::create([
                        'product_id' => $product->id,
                        'url'        => $url,
                        'disk'       => 'public',
                        'sort_order' => $i,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Save failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.marketplace')->with('success', 'Item created successfully.');
    }

    public function updateMarketplace(Request $request, MarketplaceProduct $item)
    {
        $isSale  = $request->boolean('is_sale');
        $isRent  = $request->boolean('is_rent');
        if (!$isSale && !$isRent) {
            return back()->withErrors(['listing_type' => 'Please select at least one listing type.'])->withInput();
        }
        $listingType = ($isSale && $isRent) ? 'both' : ($isSale ? 'sale' : 'rent');

        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'price'              => $isSale ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'rent_price_per_day' => $isRent ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'category_id'                  => 'nullable|exists:marketplace_categories,id',
            'marketplace_vehicle_type_id'  => 'nullable|exists:marketplace_vehicle_types,id',
            'marketplace_vehicle_color_id' => 'nullable|exists:marketplace_vehicle_colors,id',
            'marketplace_vehicle_size_id'  => 'nullable|exists:marketplace_vehicle_sizes,id',
            'condition'          => 'required|in:new,used,refurbished',
            'status'             => 'required|in:active,paused,draft',
            'images'             => 'nullable|array|max:10',
            'images.*'           => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($error = $this->marketplaceVehicleTypeMismatchError($data)) {
            return back()->withErrors(['marketplace_vehicle_size_id' => $error])->withInput();
        }

        $data['listing_type'] = $listingType;
        unset($data['images']);

        try {
            $item->update($data);

            if ($request->hasFile('images')) {
                $next = ($item->images()->max('sort_order') ?? -1) + 1;
                foreach ($request->file('images') as $i => $file) {
                    $url = $file->store('marketplace/products', 'public');
                    MarketplaceProductImage::create([
                        'product_id' => $item->id,
                        'url'        => $url,
                        'disk'       => 'public',
                        'sort_order' => $next + $i,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Save failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.marketplace')->with('success', 'Item updated successfully.');
    }

    /**
     * Ensures a submitted size/category actually belongs to the selected vehicle
     * type — e.g. rejects Passenger Three-Wheeler + 2.2M, which is only valid for Cargo.
     */
    private function marketplaceVehicleTypeMismatchError(array $data): ?string
    {
        $typeId = $data['marketplace_vehicle_type_id'] ?? null;
        if (! $typeId) {
            return null;
        }

        $type = MarketplaceVehicleType::with(['sizes', 'categories', 'colors'])->find($typeId);
        if (! $type) {
            return null;
        }

        if (! empty($data['marketplace_vehicle_size_id']) && ! $type->sizes->contains('id', $data['marketplace_vehicle_size_id'])) {
            return 'The selected size is not valid for this vehicle type.';
        }

        if (! empty($data['category_id']) && ! $type->categories->contains('id', $data['category_id'])) {
            return 'The selected category is not valid for this vehicle type.';
        }

        if (! empty($data['marketplace_vehicle_color_id']) && ! $type->colors->contains('id', $data['marketplace_vehicle_color_id'])) {
            return 'The selected color is not valid for this vehicle type.';
        }

        return null;
    }

    public function destroyMarketplace(MarketplaceProduct $item)
    {
        foreach ($item->images as $img) {
            \Illuminate\Support\Facades\Storage::disk($img->disk ?? 'public')->delete($img->url);
        }
        $item->delete();
        return redirect()->route('admin.marketplace')->with('success', 'Item deleted.');
    }

    public function destroyMarketplaceImage(MarketplaceProductImage $image)
    {
        \Illuminate\Support\Facades\Storage::disk($image->disk ?? 'public')->delete($image->url);
        $image->delete();
        return response()->json(['message' => 'Image deleted.']);
    }

    // ─── Marketplace Orders ──────────────────────────────────────────────────

    public function marketplaceOrders(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = \App\Models\MarketplaceOrder::with(['product', 'buyer', 'seller'])
            ->latest();

        if ($request->filled('order_type')) {
            $query->where('order_type', $request->order_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', $search);
                }
                $q->orWhereHas('buyer', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('seller', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        return view('admin.marketplace-orders', [
            'orders' => $query->paginate(10)->appends($request->query()),
            'search' => $search,
        ]);
    }

    public function confirmMarketplaceOrder(\App\Models\MarketplaceOrder $order)
    {
        if ($order->status === 'pending') {
            $order->update(['status' => 'confirmed']);
        }
        return back()->with('success', 'Order #' . $order->id . ' confirmed.');
    }

    public function completeMarketplaceOrder(\App\Models\MarketplaceOrder $order)
    {
        if ($order->status === 'confirmed') {
            $order->update(['status' => 'completed', 'payment_status' => 'paid']);
            if ($order->order_type === 'purchase') {
                $product   = $order->product;
                $remaining = $product->quantity - $order->quantity;
                $product->update([
                    'quantity' => max(0, $remaining),
                    'status'   => $remaining <= 0 ? 'sold' : $product->status,
                ]);
            }
        }
        return back()->with('success', 'Order #' . $order->id . ' completed.');
    }

    public function cancelMarketplaceOrder(\App\Models\MarketplaceOrder $order)
    {
        if (! in_array($order->status, ['completed', 'cancelled'])) {
            $order->update(['status' => 'cancelled']);
        }
        return back()->with('success', 'Order #' . $order->id . ' cancelled.');
    }

    // ─── Car Rentals ─────────────────────────────────────────────────────────

    public function carRentals(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = \App\Models\CarRental::with(['user', 'marketplaceProduct.images'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('vehicle_type')) {
            $query->where('vehicle_type', $request->vehicle_type);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', $search);
                }
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        return view('admin.car-rentals', [
            'rentals' => $query->paginate(10)->appends($request->query()),
            'search'  => $search,
        ]);
    }

    public function confirmCarRental(\App\Models\CarRental $rental)
    {
        if ($rental->status === 'pending') {
            $rental->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        }
        return back()->with('success', 'Rental #' . $rental->id . ' confirmed.');
    }

    public function completeCarRental(\App\Models\CarRental $rental)
    {
        if ($rental->status === 'confirmed') {
            $rental->update(['status' => 'completed']);
        }
        return back()->with('success', 'Rental #' . $rental->id . ' completed.');
    }

    public function cancelCarRental(\App\Models\CarRental $rental)
    {
        if (! in_array($rental->status, ['completed', 'cancelled'])) {
            $rental->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }
        return back()->with('success', 'Rental #' . $rental->id . ' cancelled.');
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
            'night_surcharge_rate'              => 'sometimes|numeric|min:0|max:1',
            'delivery_night_surcharge_rate'     => 'sometimes|numeric|min:0|max:1',
            'delivery_express_multiplier'       => 'sometimes|numeric|min:1|max:10',
            'avg_city_speed_kmh'                => 'sometimes|integer|min:5|max:120',
            'traffic_speed_threshold_kmh'       => 'sometimes|integer|min:5|max:60',
            'driver_min_balance_khr'            => 'sometimes|integer|min:0',
            'partner_normal_fee'                => 'sometimes|integer|min:0',
            'partner_express_fee'               => 'sometimes|integer|min:0',
            'partner_surcharge_large'           => 'sometimes|integer|min:0',
            'partner_surcharge_extra_large'     => 'sometimes|integer|min:0',
            'ride_radius_tiers_km'              => ['sometimes', 'regex:/^(\d+(\.\d+)?)(,\d+(\.\d+)?)*$/'],
            'ride_dispatch_limit'               => 'sometimes|integer|min:1|max:50',
            'ride_offer_timeout_seconds'        => 'sometimes|integer|min:5|max:120',
            'ride_self_serve_window_seconds'    => 'sometimes|integer|min:5|max:600',
            'delivery_match_radius_km'          => 'sometimes|numeric|min:1|max:100',
            'driver_match_distance_weight'      => 'sometimes|numeric|min:0|max:50',
            'driver_match_eta_weight'           => 'sometimes|numeric|min:0|max:50',
            'driver_match_rating_weight'        => 'sometimes|numeric|min:0|max:50',
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
            'zones' => SurgeZone::orderBy('active')->orderBy('multiplier')->paginate(10),
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
            'stations' => ChargingStation::orderBy('created_at')->paginate(10),
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

    public function support(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = SupportTicket::with(['user', 'messages' => fn ($q) => $q->latest('id')->limit(1)->with('sender:id,role')])
            ->orderBy('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', $search);
                }
                $q->orWhere('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $tickets = $query->paginate(10)->appends(['search' => $search]);

        $tickets->getCollection()->each(function ($t) {
            $last = $t->messages->first();
            $t->needs_reply = in_array($t->status, ['open', 'in_progress'], true)
                && (! $last || ! $last->sender || $last->sender->role !== 'admin');
        });

        return view('admin.support', [
            'tickets' => $tickets,
            'users'   => User::orderBy('name')->get(),
            'admins'  => User::where('role', 'admin')->orderBy('name')->get(),
            'search'  => $search,
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

    /**
     * The conversation thread for one support ticket — where staff actually
     * read what the passenger/driver wrote and reply to them.
     */
    public function showSupport(SupportTicket $ticket)
    {
        return view('admin.support-detail', [
            'ticket'   => $ticket->load(['user', 'messages.sender']),
            'admins'   => User::where('role', 'admin')->orderBy('name')->get(),
        ]);
    }

    public function replySupport(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => Auth::id(),
            'message'   => $data['message'],
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return redirect()->route('admin.support.show', $ticket)->with('success', 'Reply sent.');
    }

    // ─── Safety ──────────────────────────────────────────────────────────────

    public function safety(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = SafetyIncident::with('user')->orderBy('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', $search);
                }
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        return view('admin.safety', [
            'incidents' => $query->paginate(10)->appends(['search' => $search]),
            'users'     => User::orderBy('name')->get(),
            'search'    => $search,
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
        $search = trim((string) $request->input('search', ''));

        $query = TransactionRecord::with(['payer', 'payee', 'processedBy', 'reference'])
            ->orderByDesc('id');

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', $search);
                }
                $q->orWhereHas('payer', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('payee', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        return view('admin.transactions', [
            'transactions' => $query->paginate(10)->withQueryString(),
            'pending_cash' => TransactionRecord::where('status', 'pending')
                ->where('payment_method', 'cash')->count(),
            'pending_online' => TransactionRecord::where('status', 'pending')
                ->whereIn('payment_method', ['aba', 'wing', 'other_online'])->count(),
            'search' => $search,
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

    public function confirmTransactionBulk(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));

        if (empty($ids)) {
            return redirect()->route('admin.transactions')->with('error', 'No transactions selected.');
        }

        $transactions = TransactionRecord::whereIn('id', $ids)->where('status', 'pending')->get();

        if ($transactions->isEmpty()) {
            return redirect()->route('admin.transactions')->with('error', 'No pending transactions found for selected IDs.');
        }

        $count = 0;
        foreach ($transactions as $transaction) {
            app(PaymentService::class)->confirm($transaction, Auth::user());
            $count++;
        }

        return redirect()->route('admin.transactions')
            ->with('success', "{$count} transaction(s) confirmed successfully.");
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

    public function companies(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Company::withCount('drivers')->orderBy('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('admin.companies', [
            'companies' => $query->paginate(10)->appends(['search' => $search]),
            'search'    => $search,
        ]);
    }

    public function showCompany(Company $company)
    {
        $company->loadCount('drivers');
        $drivers = $company->drivers()
            ->select('id', 'name', 'phone', 'email', 'status_note', 'approval_status', 'available', 'rating', 'created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.company-show', compact('company', 'drivers'));
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
                ->paginate(10),
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

    public function topups(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $matchesSearch = function ($query) use ($search) {
            if ($search === '') return;
            $query->where(function ($q) use ($search) {
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                });
            });
        };

        $pendingQuery = TopUpRequest::with('user')->where('status', 'pending')->orderBy('created_at');
        $matchesSearch($pendingQuery);

        $historyQuery = TopUpRequest::with(['user', 'approvedBy'])->whereIn('status', ['approved', 'rejected'])->orderBy('updated_at');
        $matchesSearch($historyQuery);

        return view('admin.topups', [
            'pending' => $pendingQuery->get(),
            'history' => $historyQuery->paginate(10)->appends(['search' => $search]),
            'search'  => $search,
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
        ];

        $settings = PricingSetting::whereIn('key', $keys)->get()->keyBy('key');
        $floorTiers = MovingFloorFeeTier::ordered();

        return view('admin.moving-fare', compact('settings', 'floorTiers'));
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
        ]);

        foreach ($data as $key => $value) {
            PricingSetting::where('key', $key)->update(['value' => $value]);
        }

        return redirect()->route('admin.moving-fare')->with('success', 'Moving fare rates saved.');
    }

    /**
     * POST /admin/moving-fare/floor-tiers
     * Add a new floor carry fee tier. Leave max_floor empty for the open-ended
     * top tier (only one such tier should exist — a new one replaces the old).
     */
    public function storeMovingFloorFeeTier(Request $request)
    {
        $data = $request->validate([
            'max_floor' => 'nullable|integer|min:1|max:200',
            'fee'       => 'required|integer|min:0',
        ]);

        if (empty($data['max_floor'])) {
            // Only one open-ended ("N+") tier makes sense — replace it if present.
            MovingFloorFeeTier::whereNull('max_floor')->delete();
            $data['max_floor'] = null;
        } elseif (MovingFloorFeeTier::where('max_floor', $data['max_floor'])->exists()) {
            return back()->withErrors(['max_floor' => 'A tier for this floor already exists.']);
        }

        MovingFloorFeeTier::create($data);

        return redirect()->route('admin.moving-fare')->with('success', 'Floor fee tier added.');
    }

    /**
     * DELETE /admin/moving-fare/floor-tiers/{tier}
     */
    public function destroyMovingFloorFeeTier(MovingFloorFeeTier $tier)
    {
        $tier->delete();

        return redirect()->route('admin.moving-fare')->with('success', 'Floor fee tier removed.');
    }

    // ─── Package Delivery Fare Pricing ──────────────────────────────────────

    public function deliveryFare()
    {
        $keys = [
            'delivery_fee_base', 'delivery_fee_per_km',
            'delivery_fee_surcharge_small', 'delivery_fee_surcharge_medium', 'delivery_fee_surcharge_large', 'delivery_fee_surcharge_extra_large',
            'delivery_night_surcharge_rate', 'delivery_express_multiplier',
            'delivery_commission_pct',
        ];

        $settings = PricingSetting::whereIn('key', $keys)->get()->keyBy('key');

        return view('admin.delivery-fare', compact('settings'));
    }

    public function updateDeliveryFare(Request $request)
    {
        $data = $request->validate([
            'delivery_fee_base'                => 'required|integer|min:0',
            'delivery_fee_per_km'              => 'required|integer|min:0',
            'delivery_fee_surcharge_small'     => 'required|integer|min:0',
            'delivery_fee_surcharge_medium'    => 'required|integer|min:0',
            'delivery_fee_surcharge_large'     => 'required|integer|min:0',
            'delivery_fee_surcharge_extra_large'=> 'required|integer|min:0',
            'delivery_night_surcharge_rate'    => 'required|numeric|min:0|max:1',
            'delivery_express_multiplier'      => 'required|numeric|min:1|max:10',
            'delivery_commission_pct'          => 'nullable|numeric|min:0|max:100',
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                PricingSetting::set($key, $value);
            }
        }

        FareService::clearCache();

        return redirect()->route('admin.delivery-fare')->with('success', 'Delivery fare rates saved.');
    }

    // ─── Driver Approvals ─────────────────────────────────────────────────────

    public function drivers(Request $request)
    {
        $status = $request->input('status', 'pending');
        $search = trim((string) $request->input('search', ''));

        $query = User::where('role', 'driver')
            ->withCount('driverDocuments')
            ->orderBy('created_at', 'desc');

        if (in_array($status, ['pending','approved','rejected'])) {
            $query->where('approval_status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('admin.drivers', [
            'drivers' => $query->paginate(10)->appends(['status' => $status, 'search' => $search]),
            'status' => $status,
            'search' => $search,
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

    /**
     * Suspend a driver from receiving ride requests for a fixed window —
     * e.g. after a customer complaint. Enforced in DriverMatchingService
     * (excluded from ride offers) and DriverController::goOnline (blocked
     * from going online while penalized).
     */
    public function penalizeDriver(Request $request, User $driver)
    {
        if ($driver->role !== 'driver') {
            return back()->with('error', 'Only drivers can be penalized.');
        }

        $data = $request->validate([
            'hours'  => 'required|numeric|min:0.5|max:8760', // up to 1 year
            'reason' => 'nullable|string|max:255',
        ]);

        $driver->update([
            'penalty_until'  => now()->addMinutes((int) round($data['hours'] * 60)),
            'penalty_reason' => $data['reason'] ?? null,
            'available'      => false,
        ]);

        return back()->with('success', "Driver {$driver->name} penalized until {$driver->fresh()->penalty_until->format('d M Y, g:i A')}.");
    }

    public function clearDriverPenalty(User $driver)
    {
        $driver->update(['penalty_until' => null, 'penalty_reason' => null]);
        return back()->with('success', "Penalty cleared for {$driver->name}.");
    }

    public function reviewDocument(Request $request, User $_driver, \App\Models\DriverDocument $document)
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
        $search = $request->input('search');
        $method = $request->input('method');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = WithdrawalRequest::with(['driver', 'processor'])
            ->where('status', $status)
            ->orderByDesc('id');

        if ($search) {
            $query->whereHas('driver', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }
        if ($method) {
            $query->where('payment_method', $method);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);

        return view('admin.withdrawals', [
            'withdrawals' => rescue(fn () => $query->paginate(10)->withQueryString(), $emptyPage, false),
            'status'  => $status,
            'search'  => $search,
            'method'  => $method,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'counts'  => [
                'pending'  => rescue(fn () => WithdrawalRequest::where('status', 'pending')->count(), 0, false),
                'approved' => rescue(fn () => WithdrawalRequest::where('status', 'approved')->count(), 0, false),
                'rejected' => rescue(fn () => WithdrawalRequest::where('status', 'rejected')->count(), 0, false),
            ],
        ]);
    }

    public function exportWithdrawals(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = WithdrawalRequest::with('driver')
            ->where('status', $status)
            ->orderByDesc('id');

        if ($s = $request->input('search')) {
            $query->whereHas('driver', fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        }
        if ($m = $request->input('method')) {
            $query->where('payment_method', $m);
        }
        if ($df = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $df);
        }
        if ($dt = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dt);
        }

        $withdrawals = $query->get();

        $filename = 'withdrawals_' . $status . '_' . now()->format('Ymd_His') . '.xlsx';

        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ];

        $callback = function () use ($withdrawals) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel reads Khmer names correctly
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Driver Name', 'Phone', 'Amount (KHR)', 'Amount (USD)', 'Method', 'Bank', 'Account Name', 'Account Number', 'Requested At', 'Status']);
            foreach ($withdrawals as $w) {
                fputcsv($out, [
                    $w->id,
                    $w->driver->name ?? '',
                    $w->driver->phone ?? '',
                    $w->amount_khr,
                    round($w->amount_khr / 4000, 2),
                    strtoupper(str_replace('_', ' ', $w->payment_method)),
                    $w->bank_name ?? '',
                    $w->account_name ?? '',
                    $w->account_number ?? '',
                    $w->created_at->format('Y-m-d H:i:s'),
                    ucfirst($w->status),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkApproveWithdrawals(Request $request)
    {
        $ids  = $request->input('ids', []);
        $note = $request->input('admin_note');

        if (empty($ids)) {
            return redirect()->route('admin.withdrawals')->with('error', 'No withdrawals selected.');
        }

        $withdrawals = WithdrawalRequest::whereIn('id', $ids)->where('status', 'pending')->get();
        $count = 0;

        foreach ($withdrawals as $withdrawal) {
            $withdrawal->update([
                'status'       => 'approved',
                'admin_note'   => $note,
                'processed_at' => now(),
                'processed_by' => Auth::id(),
            ]);

            WalletTransaction::where('reference_type', WithdrawalRequest::class)
                ->where('reference_id', $withdrawal->id)
                ->where('type', 'withdrawal_hold')
                ->update(['status' => 'completed']);

            $count++;
        }

        return redirect()->route('admin.withdrawals', ['status' => 'pending'])
            ->with('success', "{$count} withdrawal(s) approved successfully.");
    }

    public function bulkRejectWithdrawals(Request $request)
    {
        $ids  = $request->input('ids', []);
        $note = $request->input('admin_note');

        if (empty($ids)) {
            return redirect()->route('admin.withdrawals')->with('error', 'No withdrawals selected.');
        }
        if (empty($note)) {
            return redirect()->back()->with('error', 'Rejection reason is required for bulk reject.');
        }

        $withdrawals = WithdrawalRequest::whereIn('id', $ids)->where('status', 'pending')->get();
        $count = 0;

        foreach ($withdrawals as $withdrawal) {
            app(\App\Services\WalletService::class)->credit(
                $withdrawal->driver,
                $withdrawal->amount_khr,
                'withdrawal_rejected',
                'Withdrawal request rejected — funds returned'
            );

            WalletTransaction::where('reference_type', WithdrawalRequest::class)
                ->where('reference_id', $withdrawal->id)
                ->where('type', 'withdrawal_hold')
                ->update(['status' => 'cancelled']);

            $withdrawal->update([
                'status'       => 'rejected',
                'admin_note'   => $note,
                'processed_at' => now(),
                'processed_by' => Auth::id(),
            ]);

            $count++;
        }

        return redirect()->route('admin.withdrawals', ['status' => 'pending'])
            ->with('success', "{$count} withdrawal(s) rejected and funds returned.");
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

        // Mark the hold transaction as completed so driver sees it as approved
        WalletTransaction::where('reference_type', WithdrawalRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'withdrawal_hold')
            ->update(['status' => 'completed']);

        return redirect()->route('admin.withdrawals')
            ->with('success', number_format($withdrawal->amount_khr) . ' ៛ withdrawal approved for ' . $withdrawal->driver->name . '.');
    }

    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return redirect()->route('admin.withdrawals')->with('error', 'Already processed.');
        }

        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        // Return funds to driver wallet and mark hold as cancelled
        app(\App\Services\WalletService::class)->credit(
            $withdrawal->driver,
            $withdrawal->amount_khr,
            'withdrawal_rejected',
            'Withdrawal request rejected — funds returned'
        );

        WalletTransaction::where('reference_type', WithdrawalRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'withdrawal_hold')
            ->update(['status' => 'cancelled']);

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
                fn () => Banner::orderBy('sort_order')->orderByDesc('created_at')->paginate(10),
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

    // ─── Promo Events (push notification announcements) ─────────────────────

    public function promoEvents()
    {
        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        return view('admin.promo-events', [
            'events' => rescue(
                fn () => PromoEvent::orderByDesc('created_at')->paginate(10),
                $emptyPage,
                false
            ),
        ]);
    }

    public function storePromoEvent(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'body'        => 'required|string|max:1000',
            'target_role' => 'required|in:all,passenger,driver',
            'active'      => 'boolean',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('promo-events', 'public');
        }

        $data['active']     = $request->boolean('active', true);
        $data['created_by'] = Auth::id();

        $event = PromoEvent::create($data);

        if ($event->active) {
            SendPromoEventPush::dispatch($event->id);
        }

        return redirect()->route('admin.promo-events')
            ->with('success', $event->active ? 'Event created — pushing notification to users now.' : 'Event created (inactive, no push sent).');
    }

    public function updatePromoEvent(Request $request, PromoEvent $event)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'body'        => 'required|string|max:1000',
            'target_role' => 'required|in:all,passenger,driver',
            'active'      => 'boolean',
            'resend'      => 'boolean',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $resend = $request->boolean('resend');
        unset($data['resend']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('promo-events', 'public');
        } else {
            unset($data['image']);
        }

        $data['active'] = $request->boolean('active');
        $event->update($data);

        if ($resend && $event->active) {
            SendPromoEventPush::dispatch($event->id);
        }

        return redirect()->route('admin.promo-events')->with('success', 'Event updated.' . ($resend ? ' Re-sending push now.' : ''));
    }

    public function destroyPromoEvent(PromoEvent $event)
    {
        $event->delete();
        return redirect()->route('admin.promo-events')->with('success', 'Event deleted.');
    }

    // ─── Promo Coupons (discount codes) ───────────────────────────────────────

    public function promoCoupons()
    {
        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        return view('admin.promo-coupons', [
            'coupons' => rescue(
                fn () => \App\Models\PromoCode::orderByDesc('created_at')->paginate(10),
                $emptyPage,
                false
            ),
        ]);
    }

    protected function promoCouponRules(): array
    {
        return [
            'code'           => 'required|string|max:32',
            'description'    => 'nullable|string|max:255',
            'type'           => 'required|in:percent,fixed',
            'value'          => 'required|integer|min:1',
            'min_order'      => 'nullable|integer|min:0',
            'max_discount'   => 'nullable|integer|min:0',
            'usage_limit'    => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1',
            'service_type'   => 'required|in:rides,deliveries,moving,all',
            'active'         => 'boolean',
            'starts_at'      => 'nullable|date',
            'expires_at'     => 'nullable|date|after_or_equal:starts_at',
        ];
    }

    public function storePromoCoupon(Request $request)
    {
        $data = $request->validate(array_merge($this->promoCouponRules(), [
            'code' => $this->promoCouponRules()['code'] . '|unique:promo_codes,code',
        ]));

        $data['code']   = strtoupper(trim($data['code']));
        $data['active'] = $request->boolean('active', true);

        \App\Models\PromoCode::create($data);

        return redirect()->route('admin.promo-coupons')->with('success', "Coupon \"{$data['code']}\" created.");
    }

    public function updatePromoCoupon(Request $request, \App\Models\PromoCode $coupon)
    {
        $data = $request->validate(array_merge($this->promoCouponRules(), [
            'code' => $this->promoCouponRules()['code'] . '|unique:promo_codes,code,' . $coupon->id,
        ]));

        $data['code']   = strtoupper(trim($data['code']));
        $data['active'] = $request->boolean('active');

        $coupon->update($data);

        return redirect()->route('admin.promo-coupons')->with('success', "Coupon \"{$data['code']}\" updated.");
    }

    public function destroyPromoCoupon(\App\Models\PromoCode $coupon)
    {
        $coupon->delete();
        return redirect()->route('admin.promo-coupons')->with('success', 'Coupon deleted.');
    }

    // ─── Airport Zones ────────────────────────────────────────────────────────

    public function airportZones()
    {
        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        return view('admin.airport-zones', [
            'zones' => rescue(fn () => AirportZone::orderBy('sort_order')->orderBy('name')->paginate(10), $emptyPage, false),
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
        $search = trim((string) $request->input('search', ''));
        $emptyPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        return view('admin.business-accounts', [
            'accounts' => rescue(function () use ($search) {
                $query = BusinessAccount::with('owner:id,name,email,phone')
                    ->withCount('members')
                    ->orderByDesc('created_at');

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('contact_name', 'like', "%{$search}%")
                          ->orWhere('contact_phone', 'like', "%{$search}%")
                          ->orWhere('billing_email', 'like', "%{$search}%")
                          ->orWhereHas('owner', function ($uq) use ($search) {
                              $uq->where('name', 'like', "%{$search}%")
                                 ->orWhere('phone', 'like', "%{$search}%")
                                 ->orWhere('email', 'like', "%{$search}%");
                          });
                    });
                }

                return $query->paginate(10)->appends(['search' => $search]);
            }, $emptyPage, false),
            'search' => $search,
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

        $plans = rescue(fn () => SubscriptionPlan::orderBy('sort_order')->paginate(10), $emptyPage, false);

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

    public function subscriptionSubscribers(Request $request, SubscriptionPlan $plan)
    {
        $search = trim((string) $request->input('search', ''));

        $subscribers = rescue(
            function () use ($plan, $search) {
                $query = UserSubscription::with('user:id,name,email,phone')
                    ->where('subscription_plan_id', $plan->id)
                    ->orderByDesc('created_at');

                if ($search !== '') {
                    $query->whereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                           ->orWhere('phone', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                    });
                }

                return $query->paginate(10)->appends(['search' => $search]);
            },
            new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30),
            false
        );

        return view('admin.subscription-subscribers', compact('plan', 'subscribers', 'search'));
    }

    // ── Partner Contracts ─────────────────────────────────────────────────────

    public function partnerContracts(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = PartnerContract::with('partner:id,name,phone,email')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->whereHas('partner', function ($uq) use ($search) {
                $uq->where('name', 'like', "%{$search}%")
                   ->orWhere('phone', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $contracts = $query->paginate(20)->appends(['search' => $search]);

        $partners = User::where('role', 'partner')->orderBy('name')->get(['id', 'name', 'phone', 'email']);

        $defaults = [
            'normal_fee'            => (int) PricingSetting::get('partner_normal_fee', 5000),
            'express_fee'           => (int) PricingSetting::get('partner_express_fee', 10000),
            'surcharge_large'       => (int) PricingSetting::get('partner_surcharge_large', 5000),
            'surcharge_extra_large' => (int) PricingSetting::get('partner_surcharge_extra_large', 5000),
        ];

        return view('admin.partner-contracts', compact('contracts', 'partners', 'defaults', 'search'));
    }

    public function storePartnerContract(Request $request)
    {
        $data = $request->validate([
            'partner_id'       => 'required|exists:users,id',
            'base_fee'         => 'required|integer|min:0',
            'per_km_rate'      => 'required|integer|min:0',
            'surcharge_small'  => 'required|integer|min:0',
            'surcharge_medium' => 'required|integer|min:0',
            'surcharge_large'  => 'required|integer|min:0',
            'min_fee'          => 'required|integer|min:0',
            'is_active'        => 'nullable|boolean',
            'notes'            => 'nullable|string|max:500',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // Deactivate any existing active contract for this partner
        PartnerContract::where('partner_id', $data['partner_id'])
            ->where('is_active', true)
            ->update(['is_active' => false]);

        PartnerContract::create($data);

        return redirect()->route('admin.partner-contracts')
            ->with('success', 'Contract created successfully.');
    }

    public function updatePartnerContract(Request $request, PartnerContract $contract)
    {
        $data = $request->validate([
            'base_fee'         => 'required|integer|min:0',
            'per_km_rate'      => 'required|integer|min:0',
            'surcharge_small'  => 'required|integer|min:0',
            'surcharge_medium' => 'required|integer|min:0',
            'surcharge_large'  => 'required|integer|min:0',
            'min_fee'          => 'required|integer|min:0',
            'is_active'        => 'nullable|boolean',
            'notes'            => 'nullable|string|max:500',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $contract->update($data);

        return redirect()->route('admin.partner-contracts')
            ->with('success', 'Contract updated.');
    }

    public function destroyPartnerContract(PartnerContract $contract)
    {
        $contract->delete();
        return redirect()->route('admin.partner-contracts')->with('success', 'Contract deleted.');
    }

    // ── Report Helpers ────────────────────────────────────────────────────────

    private function reportPeriod(\Illuminate\Http\Request $request): array
    {
        $period = (int) $request->input('period', 30);
        $start  = now()->subDays($period)->startOfDay();
        $prev   = now()->subDays($period * 2)->startOfDay();
        return [$period, $start, $prev];
    }

    private function trendDates(int $days = 30): \Illuminate\Support\Collection
    {
        $dates = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates->push(now()->subDays($i)->format('Y-m-d'));
        }
        return $dates;
    }

    private function extractScope(\Illuminate\Http\Request $request): array
    {
        $scope    = in_array($request->input('scope', 'company'), ['company', 'partner', 'driver'])
                    ? $request->input('scope', 'company') : 'company';
        $entityId = (int) $request->input('entity_id', 0);
        $partners = \DB::table('users')->where('role', 'partner')->orderBy('name')->get(['id', 'name']);
        $drivers  = \DB::table('users')->where('role', 'driver')->orderBy('name')->get(['id', 'name']);
        return [$scope, $entityId, $partners, $drivers];
    }

    // ── 1. Order Report ───────────────────────────────────────────────────────
    public function reportOrders(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        $applyScope = function ($q) use ($scope, $entityId) {
            if ($scope === 'partner') {
                $q->whereNotNull('partner_id');
                if ($entityId) $q->where('partner_id', $entityId);
            } elseif ($scope === 'driver') {
                $q->whereNotNull('driver_id');
                if ($entityId) $q->where('driver_id', $entityId);
            }
        };

        $totals = \DB::table('deliveries')->where('created_at', '>=', $start);
        $applyScope($totals);
        $totals = $totals->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN status IN("delivered","completed") THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN status="cancelled" THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN partner_id IS NOT NULL THEN 1 ELSE 0 END) as partner_orders,
                SUM(CASE WHEN partner_id IS NULL THEN 1 ELSE 0 END) as regular_orders,
                SUM(CASE WHEN service_option="express" THEN 1 ELSE 0 END) as express_orders,
                SUM(fee) as total_fee,
                SUM(package_amount) as total_cod,
                AVG(CASE WHEN status IN("delivered","completed") AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,completed_at) END) as avg_minutes')
            ->first();

        $byStatusQ = \DB::table('deliveries')->where('created_at', '>=', $start);
        $applyScope($byStatusQ);
        $byStatus = $byStatusQ->selectRaw('status, COUNT(*) as c, SUM(fee) as rev')->groupBy('status')->orderByRaw('c DESC')->get();

        $bySizeQ = \DB::table('deliveries')->where('created_at', '>=', $start);
        $applyScope($bySizeQ);
        $bySize = $bySizeQ->selectRaw('package_size, COUNT(*) as c')->groupBy('package_size')->orderByRaw('c DESC')->get();

        $byPaymentQ = \DB::table('deliveries')->where('created_at', '>=', $start);
        $applyScope($byPaymentQ);
        $byPayment = $byPaymentQ->selectRaw('payment_by, COUNT(*) as c, SUM(package_amount) as cod')->groupBy('payment_by')->get();

        $dailyQ = \DB::table('deliveries')->where('created_at', '>=', $start);
        $applyScope($dailyQ);
        $daily = $dailyQ->selectRaw('DATE(created_at) as date, COUNT(*) as c, SUM(fee) as rev,
                SUM(CASE WHEN status IN("delivered","completed") THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN status="cancelled" THEN 1 ELSE 0 END) as cancelled')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $trendDates = $this->trendDates($period);

        $recentQ = \DB::table('deliveries as d')
            ->leftJoin('users as dr', 'dr.id', '=', 'd.driver_id')
            ->leftJoin('users as p', 'p.id', '=', 'd.partner_id')
            ->where('d.created_at', '>=', $start);
        if ($scope === 'partner') {
            $recentQ->whereNotNull('d.partner_id');
            if ($entityId) $recentQ->where('d.partner_id', $entityId);
        } elseif ($scope === 'driver') {
            $recentQ->whereNotNull('d.driver_id');
            if ($entityId) $recentQ->where('d.driver_id', $entityId);
        }
        $recent = $recentQ->selectRaw('d.id, d.recipient_name, d.recipient_phone, d.status, d.service_option,
                d.package_size, d.fee, d.created_at, dr.name as driver_name, p.name as partner_name')
            ->orderByDesc('d.id')->limit(20)->get();

        return view('admin.reports.orders', compact('period','start','scope','entityId','partners','drivers','totals','byStatus','bySize','byPayment','daily','trendDates','recent'));
    }

    // ── 2. Driver Report ──────────────────────────────────────────────────────
    public function reportDrivers(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        $driverBaseQ = \DB::table('users')->where('role', 'driver');
        if ($scope === 'driver' && $entityId) $driverBaseQ->where('id', $entityId);

        $totals = (clone $driverBaseQ)->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN available=1 THEN 1 ELSE 0 END) as online,
                SUM(CASE WHEN approval_status="approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN approval_status="pending" THEN 1 ELSE 0 END) as pending,
                AVG(rating) as avg_rating')->first();

        $newDrivers = (clone $driverBaseQ)->where('created_at', '>=', $start)->count();

        $driverActivityQ = \DB::table('users as u')->where('u.role', 'driver');
        if ($scope === 'driver' && $entityId) $driverActivityQ->where('u.id', $entityId);

        $driverActivityQ->leftJoin('rides as r', function($j) use ($start, $scope, $entityId) {
            $j->on('r.driver_id','=','u.id')->where('r.status','completed')->where('r.created_at','>=',$start);
        })->leftJoin('deliveries as d', function($j) use ($start, $scope, $entityId) {
            $cond = $j->on('d.driver_id','=','u.id')->whereIn('d.status',['delivered','completed'])->where('d.created_at','>=',$start);
            if ($scope === 'partner' && $entityId) $cond->where('d.partner_id', $entityId);
            elseif ($scope === 'partner')           $cond->whereNotNull('d.partner_id');
        });

        $driverActivity = $driverActivityQ->selectRaw('u.id, u.name, u.phone, u.rating, u.available, u.wallet_balance, u.approval_status,
                COUNT(DISTINCT r.id) as rides, COUNT(DISTINCT d.id) as deliveries,
                COALESCE(SUM(DISTINCT r.fare),0) + COALESCE(SUM(DISTINCT d.fee),0) as gross_revenue')
            ->groupBy('u.id','u.name','u.phone','u.rating','u.available','u.wallet_balance','u.approval_status')
            ->orderByRaw('(COUNT(DISTINCT r.id)+COUNT(DISTINCT d.id)) DESC')
            ->get();

        $dailyQ = \DB::table('rides')->where('created_at', '>=', $start)->where('status','completed');
        if ($scope === 'driver' && $entityId) $dailyQ->where('driver_id', $entityId);
        $daily = $dailyQ->selectRaw('DATE(created_at) as date, COUNT(*) as rides')->groupBy('date')
            ->orderBy('date')->get()->keyBy('date');

        $trendDates = $this->trendDates($period);

        $cancellationsQ = \DB::table('users as u')->where('u.role','driver');
        if ($scope === 'driver' && $entityId) $cancellationsQ->where('u.id', $entityId);
        $cancellations = $cancellationsQ->join('rides as r', function($j) use ($start) {
                $j->on('r.driver_id','=','u.id')->where('r.status','cancelled')->where('r.created_at','>=',$start);
            })
            ->selectRaw('u.id, u.name, COUNT(r.id) as cancelled')
            ->groupBy('u.id','u.name')->orderByRaw('cancelled DESC')->limit(10)->get();

        return view('admin.reports.drivers', compact('period','start','scope','entityId','partners','drivers','totals','newDrivers','driverActivity','daily','trendDates','cancellations'));
    }

    // ── 3. Partner Report ─────────────────────────────────────────────────────
    public function reportPartners(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        $partnerListQ = \DB::table('users as u')->where('u.role','partner');
        if ($scope === 'partner' && $entityId) $partnerListQ->where('u.id', $entityId);

        $partnerList = $partnerListQ
            ->leftJoin('deliveries as d', function($j) use ($start, $scope, $entityId) {
                $cond = $j->on('d.partner_id','=','u.id')->where('d.created_at','>=',$start);
                if ($scope === 'driver' && $entityId) $cond->where('d.driver_id', $entityId);
                elseif ($scope === 'driver')           $cond->whereNotNull('d.driver_id');
            })
            ->leftJoin('partner_contracts as pc', function($j) {
                $j->on('pc.partner_id','=','u.id')->where('pc.is_active',1);
            })
            ->selectRaw('u.id, u.name, u.phone, u.wallet_balance, u.created_at as joined,
                COUNT(DISTINCT d.id) as orders,
                SUM(CASE WHEN d.status IN("delivered","completed") THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN d.status="cancelled" THEN 1 ELSE 0 END) as cancelled,
                SUM(d.fee) as revenue,
                SUM(CASE WHEN d.service_option="express" THEN 1 ELSE 0 END) as express,
                MAX(pc.normal_fee) as contract_normal_fee')
            ->groupBy('u.id','u.name','u.phone','u.wallet_balance','u.created_at')
            ->orderByRaw('orders DESC')->get();

        $totalPartners = \DB::table('users')->where('role','partner')->count();

        $totalOrdersQ = \DB::table('deliveries')->where('created_at','>=',$start)->whereNotNull('partner_id');
        if ($scope === 'partner' && $entityId) $totalOrdersQ->where('partner_id', $entityId);
        if ($scope === 'driver' && $entityId)  $totalOrdersQ->where('driver_id', $entityId);
        $totalOrders = $totalOrdersQ->count();

        $totalRevenueQ = \DB::table('deliveries')->where('created_at','>=',$start)->whereNotNull('partner_id')
                            ->whereIn('status',['delivered','completed']);
        if ($scope === 'partner' && $entityId) $totalRevenueQ->where('partner_id', $entityId);
        if ($scope === 'driver' && $entityId)  $totalRevenueQ->where('driver_id', $entityId);
        $totalRevenue = $totalRevenueQ->sum('fee');

        $dailyQ = \DB::table('deliveries')->where('created_at','>=',$start)->whereNotNull('partner_id');
        if ($scope === 'partner' && $entityId) $dailyQ->where('partner_id', $entityId);
        if ($scope === 'driver' && $entityId)  $dailyQ->where('driver_id', $entityId);
        $daily = $dailyQ->selectRaw('DATE(created_at) as date, COUNT(*) as c, SUM(fee) as rev')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $trendDates = $this->trendDates($period);

        return view('admin.reports.partners', compact('period','start','scope','entityId','partners','drivers','partnerList','totalPartners','totalOrders','totalRevenue','daily','trendDates'));
    }

    // ── 4. Customer Report ────────────────────────────────────────────────────
    public function reportCustomers(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        $totals = \DB::table('users')->where('role','passenger')
            ->selectRaw('COUNT(*) as total')->first();
        $newCustomers = \DB::table('users')->where('role','passenger')
            ->where('created_at','>=',$start)->count();
        $activeCustomers = \DB::table('users as u')
            ->where('u.role','passenger')
            ->where(function($q) use ($start) {
                $q->whereExists(function($s) use ($start) {
                    $s->from('rides')->whereColumn('passenger_id','u.id')->where('created_at','>=',$start);
                })->orWhereExists(function($s) use ($start) {
                    $s->from('deliveries')->whereColumn('sender_id','u.id')->where('created_at','>=',$start)->whereNull('partner_id');
                });
            })->count();

        $topCustomersQ = \DB::table('users as u')->where('u.role','passenger');
        $topCustomersQ->leftJoin('rides as r', function($j) use ($start, $scope, $entityId) {
            $cond = $j->on('r.passenger_id','=','u.id')->where('r.created_at','>=',$start);
            if ($scope === 'driver' && $entityId) $cond->where('r.driver_id', $entityId);
            elseif ($scope === 'partner')          $cond->whereRaw('1=0'); // rides not partner-scoped
        });

        $topCustomers = $topCustomersQ->selectRaw('u.id, u.name, u.phone, u.created_at as joined,
                COUNT(DISTINCT r.id) as rides,
                SUM(CASE WHEN r.status="completed" THEN r.fare ELSE 0 END) as spent,
                AVG(r.passenger_rating) as avg_rating_given')
            ->groupBy('u.id','u.name','u.phone','u.created_at')
            ->orderByRaw('rides DESC')->limit(20)->get();

        $registrations = \DB::table('users')->where('role','passenger')
            ->where('created_at','>=',$start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as c')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $trendDates = $this->trendDates($period);

        return view('admin.reports.customers', compact('period','start','scope','entityId','partners','drivers','totals','newCustomers','activeCustomers','topCustomers','registrations','trendDates'));
    }

    // ── 5. Financial Report ───────────────────────────────────────────────────
    public function reportFinancial(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        // Rides revenue — partners don't have rides, driver scope filters by driver_id
        $rideQ = \DB::table('rides')->where('created_at','>=',$start)->where('status','completed');
        if ($scope === 'partner')                   $rideQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $rideQ->where('driver_id', $entityId);
        $rideRev = (float) (clone $rideQ)->sum('fare');

        // Delivery revenue
        $delQ = \DB::table('deliveries')->where('created_at','>=',$start)->whereIn('status',['delivered','completed']);
        if ($scope === 'partner') {
            $delQ->whereNotNull('partner_id');
            if ($entityId) $delQ->where('partner_id', $entityId);
        } elseif ($scope === 'driver') {
            if ($entityId) $delQ->where('driver_id', $entityId);
        }
        $deliveryRev = (float) (clone $delQ)->sum('fee');

        // Commission
        $commQ = \DB::table('wallet_transactions')->where('created_at','>=',$start)->where('type','platform_commission');
        if ($scope === 'partner') $commQ->whereRaw('1=0'); // commission is from drivers
        elseif ($scope === 'driver' && $entityId) $commQ->where('user_id', $entityId);
        $commission = (float) $commQ->sum('amount');

        // Tips
        $tipsQ = \DB::table('wallet_transactions')->where('created_at','>=',$start)->where('type','tip_in');
        if ($scope === 'partner')                   $tipsQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $tipsQ->where('user_id', $entityId);
        $tips = (float) $tipsQ->sum('amount');

        // COD outstanding
        $codQ = \DB::table('deliveries')->where('payment_by','recipient')->whereIn('status',['delivered','completed'])->where('payment_status','unpaid');
        if ($scope === 'partner' && $entityId) $codQ->where('partner_id', $entityId);
        elseif ($scope === 'driver' && $entityId) $codQ->where('driver_id', $entityId);
        $cod = (float) $codQ->sum('package_amount');

        $topups      = (float)\DB::table('top_up_requests')->where('status','approved')->where('created_at','>=',$start)->sum('amount');
        $withdrawals = (float)\DB::table('withdrawal_requests')->where('status','approved')->where('created_at','>=',$start)->sum('amount_khr');

        $dailyRideQ = \DB::table('rides as r')->where('r.status','completed')->where('r.created_at','>=',$start);
        if ($scope === 'partner')                   $dailyRideQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $dailyRideQ->where('r.driver_id', $entityId);
        $dailyRevenue = $dailyRideQ->selectRaw('DATE(r.created_at) as date, SUM(r.fare) as ride_rev')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $dailyDelQ = \DB::table('deliveries')->whereIn('status',['delivered','completed'])->where('created_at','>=',$start);
        if ($scope === 'partner') { $dailyDelQ->whereNotNull('partner_id'); if ($entityId) $dailyDelQ->where('partner_id', $entityId); }
        elseif ($scope === 'driver' && $entityId) $dailyDelQ->where('driver_id', $entityId);
        $dailyDelivery = $dailyDelQ->selectRaw('DATE(created_at) as date, SUM(fee) as del_rev')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $trendDates = $this->trendDates($period);

        $pmQ = \DB::table('rides')->where('created_at','>=',$start)->where('status','completed');
        if ($scope === 'partner')                   $pmQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $pmQ->where('driver_id', $entityId);
        $paymentMethods = $pmQ->selectRaw('payment_method, COUNT(*) as c, SUM(fare) as rev')
            ->groupBy('payment_method')->orderByRaw('rev DESC')->get();

        return view('admin.reports.financial', compact(
            'period','start','scope','entityId','partners','drivers',
            'rideRev','deliveryRev','commission','tips','cod','topups','withdrawals',
            'dailyRevenue','dailyDelivery','trendDates','paymentMethods'
        ));
    }

    // ── 6. Wallet Report ──────────────────────────────────────────────────────
    public function reportWallet(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        $walletUserIds = null;
        if ($scope === 'driver') {
            $walletUserIds = $entityId
                ? collect([$entityId])
                : \DB::table('users')->where('role','driver')->pluck('id');
        } elseif ($scope === 'partner') {
            $walletUserIds = $entityId
                ? collect([$entityId])
                : \DB::table('users')->where('role','partner')->pluck('id');
        }

        $applyWalletScope = function($q) use ($walletUserIds) {
            if ($walletUserIds) $q->whereIn('user_id', $walletUserIds);
        };

        $byTypeQ = \DB::table('wallet_transactions')->where('created_at','>=',$start);
        $applyWalletScope($byTypeQ);
        $byType = $byTypeQ->selectRaw('type, direction, COUNT(*) as c, SUM(amount) as total')
            ->groupBy('type','direction')->orderByRaw('total DESC')->get();

        $topBalancesQ = \DB::table('users')->where('wallet_balance','>',0);
        if ($scope === 'driver')  $topBalancesQ->where('role','driver');
        if ($scope === 'partner') $topBalancesQ->where('role','partner');
        if ($walletUserIds && $entityId) $topBalancesQ->whereIn('id', $walletUserIds);
        $topBalances = $topBalancesQ->selectRaw('id, name, phone, role, wallet_balance')
            ->orderByDesc('wallet_balance')->limit(15)->get();

        $recentQ = \DB::table('wallet_transactions as wt')->join('users as u','u.id','=','wt.user_id')
            ->where('wt.created_at','>=',$start);
        $applyWalletScope($recentQ);
        $recent = $recentQ->selectRaw('wt.id, u.name, u.role, wt.type, wt.direction, wt.amount, wt.balance_after, wt.note, wt.created_at')
            ->orderByDesc('wt.id')->limit(30)->get();

        $topups = \DB::table('top_up_requests as t')
            ->join('users as u','u.id','=','t.user_id')
            ->where('t.created_at','>=',$start)
            ->selectRaw('t.id, u.name, u.phone, t.amount, t.method, t.status, t.created_at')
            ->orderByDesc('t.id')->limit(20)->get();

        $totalInQ  = \DB::table('wallet_transactions')->where('direction','credit')->where('created_at','>=',$start);
        $totalOutQ = \DB::table('wallet_transactions')->where('direction','debit')->where('created_at','>=',$start);
        $applyWalletScope($totalInQ); $applyWalletScope($totalOutQ);
        $totalIn  = (float)$totalInQ->sum('amount');
        $totalOut = (float)$totalOutQ->sum('amount');

        return view('admin.reports.wallet', compact('period','start','scope','entityId','partners','drivers','byType','topBalances','recent','topups','totalIn','totalOut'));
    }

    // ── 7. Withdrawal Report ──────────────────────────────────────────────────
    public function reportWithdrawals(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        // Withdrawals are always driver-scoped (partners don't withdraw the same way)
        $applyScope = function($q) use ($scope, $entityId) {
            if ($scope === 'driver' && $entityId) $q->where('driver_id', $entityId);
            elseif ($scope === 'partner')          $q->whereRaw('1=0'); // no partner withdrawals
        };

        $totalsQ = \DB::table('withdrawal_requests')->where('created_at','>=',$start);
        $applyScope($totalsQ);
        $totals = $totalsQ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN status="pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status="approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status="rejected" THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status="approved" THEN amount_khr ELSE 0 END) as total_paid,
                SUM(CASE WHEN status="pending" THEN amount_khr ELSE 0 END) as total_pending')
            ->first();

        $byMethodQ = \DB::table('withdrawal_requests')->where('created_at','>=',$start);
        $applyScope($byMethodQ);
        $byMethod = $byMethodQ->selectRaw('payment_method, COUNT(*) as c, SUM(amount_khr) as total')
            ->groupBy('payment_method')->orderByRaw('total DESC')->get();

        $withdrawalsQ = \DB::table('withdrawal_requests as w')
            ->join('users as u','u.id','=','w.driver_id')
            ->where('w.created_at','>=',$start);
        if ($scope === 'driver' && $entityId) $withdrawalsQ->where('w.driver_id', $entityId);
        elseif ($scope === 'partner')          $withdrawalsQ->whereRaw('1=0');
        $withdrawals = $withdrawalsQ->selectRaw('w.id, u.name, u.phone, w.amount_khr, w.payment_method, w.account_number,
                w.bank_name, w.status, w.admin_note, w.processed_at, w.created_at')
            ->orderByDesc('w.id')->paginate(25);

        $dailyQ = \DB::table('withdrawal_requests')->where('created_at','>=',$start);
        $applyScope($dailyQ);
        $daily = $dailyQ->selectRaw('DATE(created_at) as date, COUNT(*) as c, SUM(amount_khr) as total')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $trendDates = $this->trendDates($period);

        return view('admin.reports.withdrawals', compact('period','start','scope','entityId','partners','drivers','totals','byMethod','withdrawals','daily','trendDates'));
    }

    // ── 8. Commission Report ──────────────────────────────────────────────────
    public function reportCommission(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        // Commission is always between drivers and the platform
        $applyScope = function($q) use ($scope, $entityId) {
            if ($scope === 'driver' && $entityId) $q->where('user_id', $entityId);
            elseif ($scope === 'partner')          $q->whereRaw('1=0');
        };

        $commBaseQ = \DB::table('wallet_transactions')->where('type','platform_commission')->where('created_at','>=',$start);
        $applyScope($commBaseQ);
        $totalCommission = (float)(clone $commBaseQ)->sum('amount');
        $totalTrips = (clone $commBaseQ)->count();
        $avgCommission = $totalTrips > 0 ? round($totalCommission / $totalTrips) : 0;

        $byDriverQ = \DB::table('wallet_transactions as wt')
            ->join('users as u','u.id','=','wt.user_id')
            ->where('wt.type','platform_commission')->where('wt.created_at','>=',$start);
        if ($scope === 'driver' && $entityId) $byDriverQ->where('wt.user_id', $entityId);
        elseif ($scope === 'partner')          $byDriverQ->whereRaw('1=0');
        $byDriver = $byDriverQ->selectRaw('u.id, u.name, u.phone, COUNT(wt.id) as trips, SUM(wt.amount) as commission')
            ->groupBy('u.id','u.name','u.phone')->orderByRaw('commission DESC')->get();

        $dailyQ = \DB::table('wallet_transactions')->where('type','platform_commission')->where('created_at','>=',$start);
        $applyScope($dailyQ);
        $daily = $dailyQ->selectRaw('DATE(created_at) as date, COUNT(*) as c, SUM(amount) as total')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $trendDates = $this->trendDates($period);

        $recentQ = \DB::table('wallet_transactions as wt')
            ->join('users as u','u.id','=','wt.user_id')
            ->where('wt.type','platform_commission')->where('wt.created_at','>=',$start);
        if ($scope === 'driver' && $entityId) $recentQ->where('wt.user_id', $entityId);
        elseif ($scope === 'partner')          $recentQ->whereRaw('1=0');
        $recent = $recentQ->selectRaw('wt.id, u.name, wt.amount, wt.balance_before, wt.balance_after, wt.note, wt.created_at')
            ->orderByDesc('wt.id')->limit(30)->get();

        return view('admin.reports.commission', compact('period','start','scope','entityId','partners','drivers','totalCommission','totalTrips','avgCommission','byDriver','daily','trendDates','recent'));
    }

    // ── 9. Performance Report ─────────────────────────────────────────────────
    public function reportPerformance(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        $rideQ = \DB::table('rides')->where('created_at','>=',$start);
        if ($scope === 'partner')                   $rideQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $rideQ->where('driver_id', $entityId);

        $rideKpi = (clone $rideQ)->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN status="completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status="cancelled" THEN 1 ELSE 0 END) as cancelled,
                AVG(CASE WHEN status="completed" AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,completed_at) END) as avg_duration,
                AVG(CASE WHEN rating IS NOT NULL THEN rating END) as avg_rating,
                AVG(CASE WHEN accepted_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND,created_at,accepted_at) END) as avg_accept_seconds')
            ->first();

        $delQ = \DB::table('deliveries')->where('created_at','>=',$start);
        if ($scope === 'partner') { $delQ->whereNotNull('partner_id'); if ($entityId) $delQ->where('partner_id', $entityId); }
        elseif ($scope === 'driver' && $entityId) $delQ->where('driver_id', $entityId);

        $deliveryKpi = (clone $delQ)->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN status IN("delivered","completed") THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status="cancelled" THEN 1 ELSE 0 END) as cancelled,
                AVG(CASE WHEN status IN("delivered","completed") AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,completed_at) END) as avg_duration,
                AVG(CASE WHEN rating IS NOT NULL THEN rating END) as avg_rating,
                SUM(CASE WHEN driver_id IS NULL AND status NOT IN("cancelled","completed","delivered") THEN 1 ELSE 0 END) as unassigned')
            ->first();

        $hourlyQ = \DB::table('rides')->where('created_at','>=',$start)->where('status','completed');
        if ($scope === 'partner')                   $hourlyQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $hourlyQ->where('driver_id', $entityId);
        $hourly = $hourlyQ->selectRaw('HOUR(created_at) as hour, COUNT(*) as c')->groupBy('hour')->orderBy('hour')->get()->keyBy('hour');

        $cancelRideQ = (clone $rideQ)->where('status','cancelled')->whereNotNull('cancellation_reason');
        $cancellationReasons = $cancelRideQ->selectRaw('cancellation_reason, COUNT(*) as c')->groupBy('cancellation_reason')->orderByRaw('c DESC')->limit(10)->get();

        $cancelDelQ = (clone $delQ)->where('status','cancelled')->whereNotNull('cancellation_reason');
        $deliCancelReasons = $cancelDelQ->selectRaw('cancellation_reason, COUNT(*) as c')->groupBy('cancellation_reason')->orderByRaw('c DESC')->limit(10)->get();

        $ratingDistQ = \DB::table('rides')->where('created_at','>=',$start)->whereNotNull('rating');
        if ($scope === 'partner')                   $ratingDistQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $ratingDistQ->where('driver_id', $entityId);
        $ratingDist = $ratingDistQ->selectRaw('FLOOR(rating) as star, COUNT(*) as c')->groupBy('star')->orderBy('star')->get()->keyBy('star');

        return view('admin.reports.performance', compact(
            'period','start','scope','entityId','partners','drivers',
            'rideKpi','deliveryKpi','hourly','cancellationReasons','deliCancelReasons','ratingDist'
        ));
    }

    // ── 10. Driver Ranking Report ─────────────────────────────────────────────
    public function reportDriverRanking(\Illuminate\Http\Request $request)
    {
        [$period, $start] = $this->reportPeriod($request);
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);
        $sortBy = $request->input('sort', 'total');

        $rankQ = \DB::table('users as u')->where('u.role','driver');
        if ($scope === 'driver' && $entityId) $rankQ->where('u.id', $entityId);

        $rankQ->leftJoin('rides as r', function($j) use ($start, $scope) {
            $cond = $j->on('r.driver_id','=','u.id')->where('r.status','completed')->where('r.created_at','>=',$start);
            if ($scope === 'partner') $cond->whereRaw('1=0'); // partners don't do rides
        })->leftJoin('deliveries as d', function($j) use ($start, $scope, $entityId) {
            $cond = $j->on('d.driver_id','=','u.id')->whereIn('d.status',['delivered','completed'])->where('d.created_at','>=',$start);
            if ($scope === 'partner' && $entityId) $cond->where('d.partner_id', $entityId);
            elseif ($scope === 'partner')           $cond->whereNotNull('d.partner_id');
        });

        $rankQ->selectRaw('u.id, u.name, u.phone, u.rating, u.available, u.wallet_balance,
                COUNT(DISTINCT r.id) as rides,
                COUNT(DISTINCT d.id) as deliveries,
                COALESCE(SUM(DISTINCT r.fare),0) as ride_revenue,
                COALESCE(SUM(DISTINCT d.fee),0) as delivery_revenue,
                AVG(DISTINCT r.rating) as avg_ride_rating')
            ->groupBy('u.id','u.name','u.phone','u.rating','u.available','u.wallet_balance');

        $rankQ = match($sortBy) {
            'rides'    => $rankQ->orderByRaw('rides DESC'),
            'delivery' => $rankQ->orderByRaw('deliveries DESC'),
            'revenue'  => $rankQ->orderByRaw('(COALESCE(SUM(DISTINCT r.fare),0)+COALESCE(SUM(DISTINCT d.fee),0)) DESC'),
            'rating'   => $rankQ->orderByRaw('u.rating DESC'),
            default    => $rankQ->orderByRaw('(COUNT(DISTINCT r.id)+COUNT(DISTINCT d.id)) DESC'),
        };

        $driverRanking = $rankQ->get();

        return view('admin.reports.driver-ranking', compact('period','start','scope','entityId','partners','drivers','driverRanking','sortBy'));
    }

    // ── 11. Analytics Report ──────────────────────────────────────────────────
    public function reportAnalytics(\Illuminate\Http\Request $request)
    {
        $view = $request->input('view', 'daily');
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        $groupFmt = match($view) {
            'weekly'  => "YEARWEEK(created_at,1)",
            'monthly' => "DATE_FORMAT(created_at,'%Y-%m')",
            'yearly'  => "YEAR(created_at)",
            default   => "DATE(created_at)",
        };
        $labelFmt = match($view) {
            'weekly'  => "CONCAT('W',WEEK(created_at,1),' ',YEAR(created_at))",
            'monthly' => "DATE_FORMAT(created_at,'%b %Y')",
            'yearly'  => "YEAR(created_at)",
            default   => "DATE(created_at)",
        };

        $start = match($view) {
            'weekly'  => now()->subWeeks(16)->startOfWeek(),
            'monthly' => now()->subMonths(12)->startOfMonth(),
            'yearly'  => now()->subYears(3)->startOfYear(),
            default   => now()->subDays(60)->startOfDay(),
        };

        $rideQ = \DB::table('rides')->where('created_at','>=',$start);
        if ($scope === 'partner')                   $rideQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $rideQ->where('driver_id', $entityId);

        $rideTrend = $rideQ->selectRaw("{$groupFmt} as grp, {$labelFmt} as label,
                COUNT(*) as total,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status='completed' THEN fare ELSE 0 END) as revenue")
            ->groupBy('grp','label')->orderBy('grp')->get();

        $delQ = \DB::table('deliveries')->where('created_at','>=',$start);
        if ($scope === 'partner') { $delQ->whereNotNull('partner_id'); if ($entityId) $delQ->where('partner_id', $entityId); }
        elseif ($scope === 'driver' && $entityId) $delQ->where('driver_id', $entityId);

        $deliveryTrend = $delQ->selectRaw("{$groupFmt} as grp, {$labelFmt} as label,
                COUNT(*) as total,
                SUM(CASE WHEN status IN('delivered','completed') THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status IN('delivered','completed') THEN fee ELSE 0 END) as revenue")
            ->groupBy('grp','label')->orderBy('grp')->get();

        $userGrowth = \DB::table('users')->where('created_at','>=',$start)
            ->selectRaw("{$groupFmt} as grp, {$labelFmt} as label, role, COUNT(*) as c")
            ->groupBy('grp','label','role')->orderBy('grp')->get();

        $commQ = \DB::table('wallet_transactions')->where('type','platform_commission')->where('created_at','>=',$start);
        if ($scope === 'partner')                   $commQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $commQ->where('user_id', $entityId);
        $commissionTrend = (clone $commQ)->selectRaw("{$groupFmt} as grp, {$labelFmt} as label, SUM(amount) as total")
            ->groupBy('grp','label')->orderBy('grp')->get();

        $allTimeRideQ = \DB::table('rides');
        $allTimeDelQ  = \DB::table('deliveries');
        if ($scope === 'partner') { $allTimeRideQ->whereRaw('1=0'); $allTimeDelQ->whereNotNull('partner_id'); if ($entityId) $allTimeDelQ->where('partner_id', $entityId); }
        elseif ($scope === 'driver' && $entityId) { $allTimeRideQ->where('driver_id', $entityId); $allTimeDelQ->where('driver_id', $entityId); }

        $allTimeCommQ = \DB::table('wallet_transactions')->where('type','platform_commission');
        if ($scope === 'partner')                   $allTimeCommQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId)   $allTimeCommQ->where('user_id', $entityId);

        $allTime = [
            'rides'      => (clone $allTimeRideQ)->count(),
            'deliveries' => (clone $allTimeDelQ)->count(),
            'users'      => \DB::table('users')->count(),
            'drivers'    => \DB::table('users')->where('role','driver')->count(),
            'partners'   => \DB::table('users')->where('role','partner')->count(),
            'revenue'    => (float)(clone $allTimeRideQ)->where('status','completed')->sum('fare')
                          + (float)(clone $allTimeDelQ)->whereIn('status',['delivered','completed'])->sum('fee'),
            'commission' => (float)$allTimeCommQ->sum('amount'),
        ];

        return view('admin.reports.analytics', compact('view','start','scope','entityId','partners','drivers','rideTrend','deliveryTrend','userGrowth','commissionTrend','allTime'));
    }

    // ── Operations Report ─────────────────────────────────────────────────────

    public function operationsReport(\Illuminate\Http\Request $request)
    {
        $period = (int) $request->input('period', 30);
        $start  = now()->subDays($period)->startOfDay();
        [$scope, $entityId, $partners, $drivers] = $this->extractScope($request);

        $applyDelScope = function($q) use ($scope, $entityId) {
            if ($scope === 'partner') { $q->whereNotNull('partner_id'); if ($entityId) $q->where('partner_id', $entityId); }
            elseif ($scope === 'driver' && $entityId) $q->where('driver_id', $entityId);
        };
        $applyRideScope = function($q) use ($scope, $entityId) {
            if ($scope === 'partner') $q->whereRaw('1=0');
            elseif ($scope === 'driver' && $entityId) $q->where('driver_id', $entityId);
        };

        $delBase = \DB::table('deliveries')->where('created_at', '>=', $start);
        $applyDelScope($delBase);

        $totalDeliveries  = (clone $delBase)->count();
        $doneDeliveries   = (clone $delBase)->whereIn('status', ['delivered', 'completed'])->count();
        $cancelDeliveries = (clone $delBase)->where('status', 'cancelled')->count();

        $rideBase = \DB::table('rides')->where('created_at', '>=', $start);
        $applyRideScope($rideBase);

        $totalRides  = (clone $rideBase)->count();
        $doneRides   = (clone $rideBase)->where('status', 'completed')->count();
        $cancelRides = (clone $rideBase)->where('status', 'cancelled')->count();

        $deliveryRevenue = (float)(clone $delBase)->whereIn('status', ['delivered', 'completed'])->sum('fee');
        $rideRevenue     = (float)(clone $rideBase)->where('status', 'completed')->sum('fare');

        $commQ = \DB::table('wallet_transactions')->where('created_at', '>=', $start)->where('type', 'platform_commission');
        if ($scope === 'partner') $commQ->whereRaw('1=0');
        elseif ($scope === 'driver' && $entityId) $commQ->where('user_id', $entityId);
        $commission = (float)$commQ->sum('amount');

        $codQ = \DB::table('deliveries')->where('payment_by', 'recipient')->whereIn('status', ['delivered', 'completed'])->where('payment_status', 'unpaid');
        $applyDelScope($codQ);
        $codPending = (float)$codQ->sum('package_amount');

        $activeDrivers = \DB::table('users')->where('role', 'driver')->where('available', 1)->count();
        $totalDrivers  = \DB::table('users')->where('role', 'driver')->count();
        $totalPartners = \DB::table('users')->where('role', 'partner')->count();
        $unassigned    = (clone $delBase)->whereNull('driver_id')->whereNotIn('status', ['cancelled', 'completed', 'delivered'])->count();

        $deliveryStatuses = (clone $delBase)->selectRaw('status, COUNT(*) as c')->groupBy('status')->orderByRaw('c DESC')->get()->keyBy('status');

        $partnerStatsQ = \DB::table('deliveries as d')->join('users as u', 'u.id', '=', 'd.partner_id')->where('d.created_at', '>=', $start);
        if ($scope === 'partner' && $entityId) $partnerStatsQ->where('d.partner_id', $entityId);
        $partnerStats = $partnerStatsQ->selectRaw('u.id, u.name,
                COUNT(d.id) as orders,
                SUM(CASE WHEN d.status IN ("delivered","completed") THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN d.status = "cancelled" THEN 1 ELSE 0 END) as cancelled,
                SUM(d.fee) as revenue')
            ->groupBy('u.id', 'u.name')->orderByRaw('orders DESC')->get();

        $leaderQ = \DB::table('users as u')->where('u.role', 'driver');
        if ($scope === 'driver' && $entityId) $leaderQ->where('u.id', $entityId);
        $leaderQ->leftJoin('deliveries as d', function ($j) use ($start, $scope, $entityId) {
            $cond = $j->on('d.driver_id', '=', 'u.id')->whereIn('d.status', ['delivered', 'completed'])->where('d.created_at', '>=', $start);
            if ($scope === 'partner' && $entityId) $cond->where('d.partner_id', $entityId);
            elseif ($scope === 'partner')           $cond->whereNotNull('d.partner_id');
        })->leftJoin('rides as r', function ($j) use ($start, $scope) {
            $cond = $j->on('r.driver_id', '=', 'u.id')->where('r.status', 'completed')->where('r.created_at', '>=', $start);
            if ($scope === 'partner') $cond->whereRaw('1=0');
        });
        $driverLeaderboard = $leaderQ->selectRaw('u.id, u.name, u.phone, u.rating,
                COUNT(DISTINCT d.id) as deliveries, COUNT(DISTINCT r.id) as rides')
            ->groupBy('u.id', 'u.name', 'u.phone', 'u.rating')
            ->orderByRaw('(COUNT(DISTINCT d.id) + COUNT(DISTINCT r.id)) DESC')->limit(10)->get();

        $trendBase30 = now()->subDays(29)->startOfDay();
        $delTrendQ = \DB::table('deliveries')->where('created_at', '>=', $trendBase30);
        $applyDelScope($delTrendQ);
        $deliveryTrend = $delTrendQ->selectRaw('DATE(created_at) as date, COUNT(*) as c, SUM(fee) as rev')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $rideTrendQ = \DB::table('rides')->where('created_at', '>=', $trendBase30);
        $applyRideScope($rideTrendQ);
        $rideTrend = $rideTrendQ->selectRaw('DATE(created_at) as date, COUNT(*) as c, SUM(fare) as rev')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $trendDates = collect();
        for ($i = 29; $i >= 0; $i--) { $trendDates->push(now()->subDays($i)->format('Y-m-d')); }

        $avgDelQ = (clone $delBase)->whereIn('status', ['delivered', 'completed'])->whereNotNull('completed_at');
        $avgDeliveryMin = $avgDelQ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as avg')->value('avg');

        $cancelQ = (clone $delBase)->where('status', 'cancelled');
        $recentCancelled = \DB::table('deliveries as d')
            ->leftJoin('users as dr', 'dr.id', '=', 'd.driver_id')
            ->where('d.status', 'cancelled')->where('d.created_at', '>=', $start);
        $applyDelScope($recentCancelled);
        $recentCancelled = $recentCancelled->selectRaw('d.id, d.recipient_name, d.cancellation_reason, d.created_at, dr.name as driver_name')
            ->orderByDesc('d.id')->limit(10)->get();

        return view('admin.operations-report', compact(
            'period', 'start', 'scope', 'entityId', 'partners', 'drivers',
            'totalDeliveries', 'doneDeliveries', 'cancelDeliveries',
            'totalRides', 'doneRides', 'cancelRides',
            'deliveryRevenue', 'rideRevenue', 'commission',
            'codPending', 'activeDrivers', 'totalDrivers', 'totalPartners', 'unassigned',
            'deliveryStatuses', 'partnerStats', 'driverLeaderboard',
            'trendDates', 'deliveryTrend', 'rideTrend',
            'avgDeliveryMin', 'recentCancelled'
        ));
    }
}
