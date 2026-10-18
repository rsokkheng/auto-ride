<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

/**
 * Admin bell: dropdown feed, full list, read state, and the session-based
 * Reverb auth endpoint the panel uses for private-admin.{userId}.
 */
class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, $next) {
            if (! Auth::check() || Auth::user()->role !== 'admin') {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Unauthenticated.'], 401)
                    : redirect()->route('admin.login');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $category = in_array($request->category, AdminNotification::CATEGORIES, true) ? $request->category : null;

        $notifications = AdminNotification::forUser(Auth::id())
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.notifications', [
            'notifications' => $notifications,
            'category'      => $category,
            'counts'        => AdminNotification::unreadCounts(Auth::id()),
        ]);
    }

    /** JSON for the bell dropdown. */
    public function feed(Request $request)
    {
        $category = in_array($request->category, AdminNotification::CATEGORIES, true) ? $request->category : null;

        $items = AdminNotification::forUser(Auth::id())
            ->when($category, fn ($q) => $q->where('category', $category))
            ->latest('id')
            ->limit(15)
            ->get();

        return response()->json([
            'items'  => $items->map->toPayload(),
            'counts' => AdminNotification::unreadCounts(Auth::id()),
        ]);
    }

    /** Clicking a notification: mark it read and go to the order. */
    public function open(AdminNotification $notification)
    {
        abort_unless($notification->user_id === Auth::id(), 404);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->to($notification->orderUrl());
    }

    public function markAllRead(Request $request)
    {
        AdminNotification::forUser(Auth::id())
            ->unread()
            ->when(in_array($request->category, AdminNotification::CATEGORIES, true), fn ($q) => $q->where('category', $request->category))
            ->update(['read_at' => now()]);

        return $request->expectsJson()
            ? response()->json(['counts' => AdminNotification::unreadCounts(Auth::id())])
            : back()->with('success', 'All notifications marked as read.');
    }

    public function broadcastAuth(Request $request)
    {
        return Broadcast::auth($request);
    }
}
