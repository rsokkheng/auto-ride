<?php

namespace App\Http\Controllers\Api;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shop-order alerts for admins on the mobile app — same rows the web panel's
 * bell shows (App\Support\AdminNotifications), so reading one clears both.
 */
class AdminNotificationController extends ApiController
{
    private function adminUser(Request $request): ?User
    {
        $user = $this->authUser($request);
        return ($user && $user->role === 'admin') ? $user : null;
    }

    /**
     * GET /v1/admin/notifications?category=purchase|rental|system&unread=1
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        $data = $request->validate([
            'category' => 'nullable|in:' . implode(',', AdminNotification::CATEGORIES),
            'unread'   => 'nullable|boolean',
        ]);

        $page = AdminNotification::forUser($admin->id)
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
            ->latest()
            ->paginate(20);

        return $this->success([
            'notifications' => collect($page->items())->map(fn (AdminNotification $n) => $this->payload($n))->values(),
            'counts'        => AdminNotification::unreadCounts($admin->id),
            'current_page'  => $page->currentPage(),
            'last_page'     => $page->lastPage(),
            'total'         => $page->total(),
        ]);
    }

    /** GET /v1/admin/notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        return $this->success(['counts' => AdminNotification::unreadCounts($admin->id)]);
    }

    /** POST /v1/admin/notifications/{notification}/read */
    public function markRead(Request $request, AdminNotification $notification): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin || $notification->user_id !== $admin->id) return $this->unauthorized();

        if (! $notification->read_at) $notification->update(['read_at' => now()]);

        return $this->success([
            'notification' => $this->payload($notification),
            'counts'       => AdminNotification::unreadCounts($admin->id),
        ]);
    }

    /** POST /v1/admin/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $admin = $this->adminUser($request);
        if (! $admin) return $this->unauthorized();

        AdminNotification::forUser($admin->id)->unread()->update(['read_at' => now()]);

        return $this->success(['counts' => AdminNotification::unreadCounts($admin->id)]);
    }

    private function payload(AdminNotification $n): array
    {
        return $n->toPayload() + [
            'order_kind' => $n->order_kind,
            'order_id'   => $n->order_id,
            'customer'   => $n->data['customer'] ?? null,
        ];
    }
}
