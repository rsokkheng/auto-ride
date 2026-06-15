<?php

namespace App\Http\Controllers\Api;

use App\Models\PushNotification;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $notifications = PushNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->success([
            'notifications' => $notifications->items(),
            'unread_count'  => PushNotification::where('user_id', $user->id)->where('is_read', false)->count(),
            'total'         => $notifications->total(),
        ]);
    }

    public function markRead(Request $request, PushNotification $notification)
    {
        $user = $this->authUser($request);
        if (! $user || $notification->user_id !== $user->id) return $this->unauthorized();

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return $this->success(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        PushNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return $this->success(['message' => 'All notifications marked as read.']);
    }

    public function send(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'type' => 'nullable|string|max:64',
            'payload' => 'nullable|array',
        ]);

        $notification = PushNotification::create(array_merge($data, [
            'user_id' => $user->id,
            'status' => 'sent',
        ]));

        return $this->success(['notification' => $notification], 201);
    }
}
