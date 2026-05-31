<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChargingStation;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\MarketplaceItem;
use App\Models\Ride;
use App\Models\SafetyIncident;
use App\Models\SupportTicket;
use App\Models\TopUpRequest;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, $next) {
            if (! Auth::check()) {
                return redirect()->route('admin.login');
            }
            if (Auth::user()->role !== 'admin') {
                return redirect()->route('admin.login');
            }
            return $next($request);
        })->except(['showLogin', 'login']);
    }

    // ─── Auth ────────────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || $user->role !== 'admin') {
            return back()->withErrors(['email' => 'Invalid credentials or not an admin'])->withInput();
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
        return view('admin.dashboard', [
            'metrics' => [
                'users'            => User::count(),
                'drivers'          => User::where('role', 'driver')->count(),
                'vehicles'         => Vehicle::count(),
                'rides'            => Ride::count(),
                'deliveries'       => Delivery::count(),
                'marketplace'      => MarketplaceItem::count(),
                'charging_stations'=> ChargingStation::count(),
                'support_tickets'  => SupportTicket::count(),
                'safety_incidents' => SafetyIncident::count(),
            ],
            'latestUsers' => User::latest()->take(5)->get(),
            'latestRides' => Ride::latest()->take(5)->get(),
        ]);
    }

    // ─── Users ───────────────────────────────────────────────────────────────

    public function users()
    {
        return view('admin.users', [
            'users'     => User::with('company')->orderByDesc('created_at')->paginate(20),
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

        $data['password']  = Hash::make($data['password']);
        $data['api_token'] = bin2hex(random_bytes(40));

        if ($data['role'] !== 'driver') {
            $data['driver_type'] = $data['company_id'] = $data['salary'] = $data['commission_rate'] = null;
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
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        if ($data['role'] !== 'driver') {
            $data['driver_type'] = $data['company_id'] = $data['salary'] = $data['commission_rate'] = null;
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
            'vehicles' => Vehicle::with('driver')->orderByDesc('created_at')->paginate(20),
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
            'rides'      => Ride::with(['passenger', 'driver'])->orderByDesc('created_at')->paginate(20),
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

    public function deliveries()
    {
        return view('admin.deliveries', [
            'deliveries' => Delivery::with(['sender', 'driver'])->orderByDesc('created_at')->paginate(20),
            'senders'    => User::where('role', 'passenger')->orderBy('name')->get(),
            'drivers'    => User::where('role', 'driver')->orderBy('name')->get(),
        ]);
    }

    public function storeDelivery(Request $request)
    {
        $data = $request->validate([
            'sender_id'       => 'required|exists:users,id',
            'sender_name'     => 'required|string|max:255',
            'recipient_name'  => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:24',
            'package_size'    => 'required|in:small,medium,large',
            'driver_id'       => 'nullable|exists:users,id',
            'pickup_address'  => 'required|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'status'          => 'required|in:requested,pending,accepted,in_progress,completed,cancelled',
            'fee'             => 'nullable|numeric|min:0',
            'payment_by'      => 'nullable|in:sender,recipient',
            'payment_method'  => 'nullable|in:cash,wallet,aba,wing,other_online',
            'scheduled_at'    => 'nullable|date',
            'notes'           => 'nullable|string',
            'package_details' => 'nullable|string|max:500',
        ]);

        $data['package_details'] = $data['package_details'] ?? '';
        $data['payment_by']      = $data['payment_by'] ?? 'sender';
        $data['payment_method']  = $data['payment_method'] ?? 'cash';
        $data['payment_status']  = 'unpaid';
        $data['assigned_at']     = ! empty($data['driver_id']) ? now() : null;

        Delivery::create($data);

        return redirect()->route('admin.deliveries')->with('success', 'Delivery created successfully.');
    }

    public function updateDelivery(Request $request, Delivery $delivery)
    {
        $data = $request->validate([
            'sender_id'       => 'required|exists:users,id',
            'sender_name'     => 'required|string|max:255',
            'recipient_name'  => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:24',
            'package_size'    => 'required|in:small,medium,large',
            'driver_id'       => 'nullable|exists:users,id',
            'pickup_address'  => 'required|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'status'          => 'required|in:requested,pending,accepted,in_progress,completed,cancelled',
            'fee'             => 'nullable|numeric|min:0',
            'payment_by'      => 'nullable|in:sender,recipient',
            'payment_method'  => 'nullable|in:cash,wallet,aba,wing,other_online',
            'scheduled_at'    => 'nullable|date',
            'notes'           => 'nullable|string',
        ]);

        // Stamp assigned_at when a driver is set for the first time.
        if (! empty($data['driver_id']) && ! $delivery->assigned_at) {
            $data['assigned_at'] = now();
        }

        $delivery->update($data);

        return redirect()->route('admin.deliveries')->with('success', 'Delivery updated successfully.');
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
            'items'    => MarketplaceItem::with('seller')->orderByDesc('created_at')->paginate(20),
            'sellers'  => User::orderBy('name')->get(),
            'vehicles' => Vehicle::orderBy('make')->get(),
        ]);
    }

    public function storeMarketplace(Request $request)
    {
        $data = $request->validate([
            'seller_id'   => 'required|exists:users,id',
            'vehicle_id'  => 'nullable|exists:vehicles,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'type'        => 'required|in:rent,sale',
            'price'       => 'nullable|numeric|min:0',
            'rent_rate'   => 'nullable|numeric|min:0',
            'available'   => 'boolean',
            'condition'   => 'required|in:excellent,good,fair,poor',
        ]);

        $data['available'] = $request->boolean('available');

        MarketplaceItem::create($data);

        return redirect()->route('admin.marketplace')->with('success', 'Item created successfully.');
    }

    public function updateMarketplace(Request $request, MarketplaceItem $item)
    {
        $data = $request->validate([
            'seller_id'   => 'required|exists:users,id',
            'vehicle_id'  => 'nullable|exists:vehicles,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'type'        => 'required|in:rent,sale',
            'price'       => 'nullable|numeric|min:0',
            'rent_rate'   => 'nullable|numeric|min:0',
            'available'   => 'boolean',
            'condition'   => 'required|in:excellent,good,fair,poor',
        ]);

        $data['available'] = $request->boolean('available');

        $item->update($data);

        return redirect()->route('admin.marketplace')->with('success', 'Item updated successfully.');
    }

    public function destroyMarketplace(MarketplaceItem $item)
    {
        $item->delete();
        return redirect()->route('admin.marketplace')->with('success', 'Item deleted.');
    }

    // ─── Charging Stations ───────────────────────────────────────────────────

    public function chargingStations()
    {
        return view('admin.charging-stations', [
            'stations' => ChargingStation::orderByDesc('created_at')->paginate(20),
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
            'tickets' => SupportTicket::with('user')->orderByDesc('created_at')->paginate(20),
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
            'incidents' => SafetyIncident::with('user')->orderByDesc('created_at')->paginate(20),
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
            ->orderByDesc('created_at');

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
            'companies' => Company::withCount('drivers')->orderByDesc('created_at')->paginate(20),
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
                ->orderByDesc('created_at')
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
            'pending'  => TopUpRequest::with('user')->where('status', 'pending')->orderByDesc('created_at')->get(),
            'history'  => TopUpRequest::with(['user', 'approvedBy'])->whereIn('status', ['approved', 'rejected'])->orderByDesc('updated_at')->paginate(20),
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
}
