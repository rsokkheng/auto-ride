<?php

namespace App\Http\Controllers\Api;

use App\Models\PushNotification;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $notifications = PushNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success(['notifications' => $notifications]);
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
