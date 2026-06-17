<?php

namespace App\Http\Controllers\Api;

use App\Models\Banner;
use App\Models\Delivery;
use App\Models\DriverDocument;
use App\Models\MarketplaceItem;
use App\Models\PricingSetting;
use App\Models\RidePricing;
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
use App\Models\Ride;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminApiController extends ApiController
{
    public function __construct(private WalletService $wallet) {}

    // ─── Auth guard ───────────────────────────────────────────────────────────

    private function adminUser(Request $request): ?User
    {
        $user = $this->authUser($request);
        return ($user && $user->role === 'admin') ? $user : null;
    }

    private function ok(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], $status);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['data' => null, 'message' => $message], $status);
    }

    // ─── Admin Login ──────────────────────────────────────────────────────────

    /**
     * POST /api/v1/admin/login
     * Body: { email, password }
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->where('role', 'admin')->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('Invalid admin credentials.', 401);
        }

        $token = bin2hex(random_bytes(40));
        $user->update([
            'api_token'                => $token,
            'refresh_token'            => bin2hex(random_bytes(40)),
            'token_expires_at'         => now()->addHours(12),
            'refresh_token_expires_at' => now()->addDays(7),
        ]);

        return $this->ok([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => 43200,
            'admin'        => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'avatar_url' => $user->avatar_url,
            ],
        ], 'Login successful.');
    }

    /** POST /api/v1/admin/logout */
    public function logout(Request $request): JsonResponse
    {
        $user = $this->adminUser($request);
        if (! $user) return $this->unauthorized();

        $user->update(['api_token' => null, 'token_expires_at' => now()]);

        return $this->ok(null, 'Logged out.');
    }

    // ─── Stats / Dashboard ────────────────────────────────────────────────────

    /** GET /api/v1/admin/stats */
    public function stats(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $today = now()->startOfDay();
        $week  = now()->subDays(6)->startOfDay();

        // Revenue last 7 days for chart
        $revenueChart = [];
        $ridesChart   = [];
        for ($i = 6; $i >= 0; $i--) {
            $day   = now()->subDays($i)->toDateString();
            $start = now()->subDays($i)->startOfDay();
            $end   = now()->subDays($i)->endOfDay();

            $revenueChart[] = [
                'date'    => $day,
                'revenue' => (int) Ride::where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end])
                    ->sum('fare'),
                'rides'   => Ride::where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end])
                    ->count(),
            ];
            $ridesChart[] = [
                'date'  => $day,
                'count' => Ride::where('created_at', '>=', $start)
                    ->where('created_at', '<=', $end)
                    ->count(),
            ];
        }

        $todayRevenue     = (int) Ride::where('status', 'completed')->where('completed_at', '>=', $today)->sum('fare');
        $yesterdayRevenue = (int) Ride::where('status', 'completed')
            ->whereBetween('completed_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
            ->sum('fare');
        $weekRevenue = (int) Ride::where('status', 'completed')->where('completed_at', '>=', $week)->sum('fare');

        return $this->ok([
            'overview' => [
                'passengers'          => User::where('role', 'passenger')->count(),
                'drivers_total'       => User::where('role', 'driver')->count(),
                'drivers_online'      => User::where('role', 'driver')->where('available', true)->count(),
                'drivers_pending'     => User::where('role', 'driver')->where('approval_status', 'pending')->count(),
                'vehicles'            => Vehicle::count(),
                'rides_total'         => Ride::count(),
                'rides_today'         => Ride::where('created_at', '>=', $today)->count(),
                'rides_active'        => Ride::whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])->count(),
                'deliveries_total'    => Delivery::count(),
                'deliveries_today'    => Delivery::where('created_at', '>=', $today)->count(),
                'revenue_today'       => $todayRevenue,
                'revenue_week'        => $weekRevenue,
                'revenue_growth_pct'  => $yesterdayRevenue > 0
                    ? round(($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue * 100, 1)
                    : null,
                'support_open'        => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
                'withdrawals_pending' => WithdrawalRequest::where('status', 'pending')->count(),
                'topups_pending'      => TopUpRequest::where('status', 'pending')->count(),
                'marketplace_items'   => MarketplaceItem::count(),
                'safety_incidents'    => SafetyIncident::count(),
            ],
            'revenue_chart' => $revenueChart,
            'pending_alerts' => [
                'driver_approvals'    => User::where('role', 'driver')->where('approval_status', 'pending')->count(),
                'withdrawal_requests' => WithdrawalRequest::where('status', 'pending')->count(),
                'open_tickets'        => SupportTicket::where('status', 'open')->count(),
                'topup_requests'      => TopUpRequest::where('status', 'pending')->count(),
            ],
        ]);
    }

    // ─── Users ────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/users
     * Query: role?, search?, per_page?
     */
    public function users(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = User::query()->orderByDesc('id');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $s)
                ->orWhere('email', 'like', $s)
                ->orWhere('phone', 'like', $s));
        }

        $users = $query->paginate((int) $request->input('per_page', 20));

        return $this->ok($users);
    }

    /** GET /api/v1/admin/users/{user} */
    public function showUser(Request $request, User $user): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        return $this->ok($user->load('vehicles', 'driverDocuments'));
    }

    /**
     * PUT /api/v1/admin/users/{user}
     * Body: { name?, email?, phone?, role?, approval_status?, wallet_balance? }
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'email'           => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'           => 'sometimes|nullable|string|max:20',
            'role'            => 'sometimes|in:admin,driver,passenger',
            'approval_status' => 'sometimes|in:pending,approved,rejected',
            'status_note'     => 'sometimes|nullable|string|max:500',
            'salary'          => 'sometimes|integer|min:0',
            'commission_rate' => 'sometimes|numeric|min:0|max:100',
        ]);

        $user->update($data);

        return $this->ok($user->fresh(), 'User updated.');
    }

    /** DELETE /api/v1/admin/users/{user} */
    public function deleteUser(Request $request, User $user): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        if ($user->id === $admin->id) {
            return $this->fail('Cannot delete yourself.');
        }

        $user->delete();

        return $this->ok(null, 'User deleted.');
    }

    /**
     * POST /api/v1/admin/users/{user}/credit
     * Body: { amount_khr, note? }
     */
    public function creditUser(Request $request, User $user): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'amount_khr' => 'required|integer|min:1000',
            'note'       => 'nullable|string|max:255',
        ]);

        $this->wallet->credit($user, $data['amount_khr'], 'admin_credit', $data['note'] ?? 'Admin credit', null, $admin->id);

        return $this->ok(['new_balance' => $user->fresh()->wallet_balance], 'Wallet credited.');
    }

    // ─── Drivers ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/drivers
     * Query: approval_status?, available?, search?, per_page?
     */
    public function drivers(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = User::where('role', 'driver')
            ->withCount('driverDocuments')
            ->orderByDesc('id');

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }
        if ($request->filled('available')) {
            $query->where('available', (bool) $request->available);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $s)->orWhere('phone', 'like', $s));
        }

        return $this->ok($query->paginate((int) $request->input('per_page', 20)));
    }

    /** GET /api/v1/admin/drivers/{driver} */
    public function showDriver(Request $request, User $driver): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        if ($driver->role !== 'driver') return $this->fail('User is not a driver.', 404);

        return $this->ok($driver->load('vehicles', 'driverDocuments'));
    }

    /**
     * POST /api/v1/admin/drivers/{driver}/approve
     * Body: { status: "approved"|"rejected", note? }
     */
    public function approveDriver(Request $request, User $driver): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'note'   => 'nullable|string|max:500',
        ]);

        $driver->update([
            'approval_status' => $data['status'],
            'status_note'     => $data['note'] ?? null,
            'approved_at'     => $data['status'] === 'approved' ? now() : null,
        ]);

        return $this->ok($driver->fresh(), 'Driver ' . $data['status'] . '.');
    }

    /**
     * POST /api/v1/admin/drivers/{driver}/documents/{document}/review
     * Body: { status: "approved"|"rejected", note? }
     */
    public function reviewDocument(Request $request, User $driver, DriverDocument $document): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'note'   => 'nullable|string|max:500',
        ]);

        $document->update([
            'status'      => $data['status'],
            'reject_note' => $data['note'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
        ]);

        return $this->ok($document->fresh(), 'Document ' . $data['status'] . '.');
    }

    // ─── Rides ────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/rides
     * Query: status?, driver_id?, passenger_id?, date?, per_page?
     */
    public function rides(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = Ride::with('passenger:id,name,phone', 'driver:id,name,phone')
            ->orderByDesc('id');

        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('driver_id'))    $query->where('driver_id', $request->driver_id);
        if ($request->filled('passenger_id')) $query->where('passenger_id', $request->passenger_id);
        if ($request->filled('date'))         $query->whereDate('created_at', $request->date);

        return $this->ok($query->paginate((int) $request->input('per_page', 20)));
    }

    /** GET /api/v1/admin/rides/{ride} */
    public function showRide(Request $request, Ride $ride): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        return $this->ok($ride->load('passenger', 'driver', 'vehicle'));
    }

    /**
     * POST /api/v1/admin/rides/{ride}/cancel
     * Body: { reason? }
     */
    public function cancelRide(Request $request, Ride $ride): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        if (in_array($ride->status, ['completed', 'cancelled'], true)) {
            return $this->fail('Ride is already ' . $ride->status . '.');
        }

        $ride->update([
            'status'      => 'cancelled',
            'cancel_note' => $request->input('reason', 'Cancelled by admin'),
        ]);

        return $this->ok($ride->fresh(), 'Ride cancelled.');
    }

    // ─── Deliveries ───────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/deliveries
     * Query: status?, service_type?, date?, per_page?
     */
    public function deliveries(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = Delivery::with('sender:id,name,phone', 'driver:id,name,phone')
            ->orderByDesc('id');

        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('service_type')) $query->where('service_type', $request->service_type);
        if ($request->filled('date'))         $query->whereDate('created_at', $request->date);

        return $this->ok($query->paginate((int) $request->input('per_page', 20)));
    }

    /** GET /api/v1/admin/deliveries/{delivery} */
    public function showDelivery(Request $request, Delivery $delivery): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        return $this->ok($delivery->load('sender', 'driver', 'vehicle'));
    }

    // ─── Withdrawals ─────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/withdrawals
     * Query: status?, per_page?
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = WithdrawalRequest::with('driver:id,name,phone,wallet_balance')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->ok($query->paginate((int) $request->input('per_page', 20)));
    }

    /**
     * POST /api/v1/admin/withdrawals/{withdrawal}/approve
     * Body: { note? }
     */
    public function approveWithdrawal(Request $request, WithdrawalRequest $withdrawal): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        if ($withdrawal->status !== 'pending') {
            return $this->fail('Withdrawal is already ' . $withdrawal->status . '.');
        }

        $withdrawal->update([
            'status'       => 'approved',
            'admin_note'   => $request->input('note'),
            'processed_at' => now(),
            'processed_by' => $admin->id,
        ]);

        return $this->ok($withdrawal->fresh(), 'Withdrawal approved.');
    }

    /**
     * POST /api/v1/admin/withdrawals/{withdrawal}/reject
     * Body: { reason }
     */
    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate(['reason' => 'required|string|max:500']);

        if ($withdrawal->status !== 'pending') {
            return $this->fail('Withdrawal is already ' . $withdrawal->status . '.');
        }

        // Return funds to driver wallet
        $this->wallet->credit(
            $withdrawal->driver,
            $withdrawal->amount_khr,
            'withdrawal_refund',
            'Withdrawal rejected: ' . $data['reason'],
            $withdrawal,
            $admin->id,
        );

        $withdrawal->update([
            'status'       => 'rejected',
            'admin_note'   => $data['reason'],
            'processed_at' => now(),
            'processed_by' => $admin->id,
        ]);

        return $this->ok($withdrawal->fresh(), 'Withdrawal rejected and funds returned.');
    }

    // ─── Top-up Requests ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/topups
     * Query: status?, per_page?
     */
    public function topups(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = TopUpRequest::with('user:id,name,phone,wallet_balance')
            ->orderByDesc('id');

        if ($request->filled('status')) $query->where('status', $request->status);

        return $this->ok($query->paginate((int) $request->input('per_page', 20)));
    }

    /**
     * POST /api/v1/admin/topups/{topup}/approve
     * Body: { note? }
     */
    public function approveTopUp(Request $request, TopUpRequest $topup): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        if ($topup->status !== 'pending') return $this->fail('Already ' . $topup->status . '.');

        $this->wallet->credit(
            $topup->user,
            $topup->amount,
            'topup',
            'Top-up approved by admin',
            $topup,
            $admin->id,
        );

        $topup->update(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);

        return $this->ok($topup->fresh(), 'Top-up approved and wallet credited.');
    }

    /**
     * POST /api/v1/admin/topups/{topup}/reject
     * Body: { reason }
     */
    public function rejectTopUp(Request $request, TopUpRequest $topup): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate(['reason' => 'required|string|max:500']);
        if ($topup->status !== 'pending') return $this->fail('Already ' . $topup->status . '.');

        $topup->update(['status' => 'rejected', 'reject_note' => $data['reason']]);

        return $this->ok(null, 'Top-up rejected.');
    }

    // ─── Support ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/support
     * Query: status?, priority?, per_page?
     */
    public function support(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = SupportTicket::with('user:id,name,email')->orderByDesc('id');

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);

        return $this->ok($query->paginate((int) $request->input('per_page', 20)));
    }

    /** GET /api/v1/admin/support/{ticket} */
    public function showTicket(Request $request, SupportTicket $ticket): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        return $this->ok($ticket->load('user', 'messages.sender'));
    }

    /**
     * POST /api/v1/admin/support/{ticket}/reply
     * Body: { message }
     */
    public function replyTicket(Request $request, SupportTicket $ticket): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate(['message' => 'required|string|max:2000']);

        $msg = SupportMessage::create([
            'ticket_id'  => $ticket->id,
            'sender_id'  => $admin->id,
            'message'    => $data['message'],
            'is_staff'   => true,
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return $this->ok($msg->load('sender:id,name'), 'Reply sent.');
    }

    /**
     * PUT /api/v1/admin/support/{ticket}/status
     * Body: { status: "open"|"in_progress"|"resolved"|"closed" }
     */
    public function updateTicketStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket->update(['status' => $data['status']]);

        return $this->ok($ticket->fresh(), 'Ticket status updated.');
    }

    // ─── Transactions ─────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/transactions
     * Query: type?, user_id?, date?, per_page?
     */
    public function transactions(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = WalletTransaction::with('user:id,name,role')->orderByDesc('id');

        if ($request->filled('type'))    $query->where('type', $request->type);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('date'))    $query->whereDate('created_at', $request->date);

        return $this->ok($query->paginate((int) $request->input('per_page', 30)));
    }

    // ─── Banners ─────────────────────────────────────────────────────────────

    /** GET /api/v1/admin/banners */
    public function banners(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        return $this->ok(Banner::orderBy('sort_order')->get());
    }

    /**
     * POST /api/v1/admin/banners
     * Body: { title, deeplink?, target_role?, sort_order?, active?, valid_from?, valid_until? }
     * File: image (optional)
     */
    public function storeBanner(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'deeplink'    => 'nullable|string|max:500',
            'target_role' => 'nullable|in:all,passenger,driver',
            'sort_order'  => 'nullable|integer',
            'active'      => 'nullable|boolean',
            'valid_from'  => 'nullable|date',
            'valid_until' => 'nullable|date',
            'image'       => 'nullable|file|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner = Banner::create($data);

        return $this->ok($banner->fresh(), 'Banner created.');
    }

    /**
     * PUT /api/v1/admin/banners/{banner}
     * Body: same as store (all optional)
     */
    public function updateBanner(Request $request, Banner $banner): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'deeplink'    => 'sometimes|nullable|string|max:500',
            'target_role' => 'sometimes|nullable|in:all,passenger,driver',
            'sort_order'  => 'sometimes|nullable|integer',
            'active'      => 'sometimes|boolean',
            'valid_from'  => 'sometimes|nullable|date',
            'valid_until' => 'sometimes|nullable|date',
            'image'       => 'sometimes|file|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($data);

        return $this->ok($banner->fresh(), 'Banner updated.');
    }

    /** DELETE /api/v1/admin/banners/{banner} */
    public function destroyBanner(Request $request, Banner $banner): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $banner->delete();

        return $this->ok(null, 'Banner deleted.');
    }

    // ─── Surge Zones ─────────────────────────────────────────────────────────

    /** GET /api/v1/admin/surge-zones */
    public function surgeZones(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        return $this->ok(SurgeZone::orderByDesc('id')->get());
    }

    /**
     * POST /api/v1/admin/surge-zones
     * Body: { name, type, multiplier, center_lat, center_lng, radius_km, active? }
     */
    public function storeSurgeZone(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:rides,deliveries,delivery,moving,both',
            'multiplier'  => 'required|numeric|min:1|max:10',
            'center_lat'  => 'required|numeric',
            'center_lng'  => 'required|numeric',
            'radius_km'   => 'required|numeric|min:0.1',
            'description' => 'nullable|string',
            'active'      => 'nullable|boolean',
        ]);

        return $this->ok(SurgeZone::create($data), 'Surge zone created.');
    }

    /**
     * PUT /api/v1/admin/surge-zones/{zone}
     * Body: same as store (all optional)
     */
    public function updateSurgeZone(Request $request, SurgeZone $zone): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'type'       => 'sometimes|in:rides,deliveries,delivery,moving,both',
            'multiplier' => 'sometimes|numeric|min:1|max:10',
            'center_lat' => 'sometimes|numeric',
            'center_lng' => 'sometimes|numeric',
            'radius_km'  => 'sometimes|numeric|min:0.1',
            'active'     => 'sometimes|boolean',
        ]);

        $zone->update($data);

        return $this->ok($zone->fresh(), 'Surge zone updated.');
    }

    /** DELETE /api/v1/admin/surge-zones/{zone} */
    public function destroySurgeZone(Request $request, SurgeZone $zone): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $zone->delete();

        return $this->ok(null, 'Surge zone deleted.');
    }

    /** POST /api/v1/admin/surge-zones/{zone}/toggle */
    public function toggleSurgeZone(Request $request, SurgeZone $zone): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $zone->update(['active' => ! $zone->active]);

        return $this->ok(['active' => $zone->fresh()->active], 'Surge zone toggled.');
    }

    // ─── Pricing Settings ─────────────────────────────────────────────────────

    /** GET /api/v1/admin/pricing */
    public function pricing(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $settings = PricingSetting::all()->keyBy('key');
        $pricing  = RidePricing::orderBy('service_type')->get();

        return $this->ok([
            'settings' => $settings,
            'pricing'  => $pricing,
        ]);
    }

    /**
     * PUT /api/v1/admin/pricing/settings
     * Body: { key: value, ... }  — any PricingSetting keys
     */
    public function updatePricing(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'settings'   => 'required|array',
            'settings.*' => 'required|string|max:255',
        ]);

        foreach ($data['settings'] as $key => $value) {
            PricingSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return $this->ok(PricingSetting::all()->keyBy('key'), 'Pricing settings updated.');
    }

    // ─── Safety Incidents ────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/safety
     * Query: type?, per_page?
     */
    public function safety(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $query = SafetyIncident::with('user:id,name,phone')->orderByDesc('id');

        if ($request->filled('type')) $query->where('type', $request->type);

        return $this->ok($query->paginate((int) $request->input('per_page', 20)));
    }
}
