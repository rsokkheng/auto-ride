<?php

namespace App\Http\Controllers\Api;

use App\Models\PromoEvent;
use Illuminate\Http\Request;

class PromoEventController extends ApiController
{
    /**
     * GET /v1/events
     * In-app feed of active promo events for the authenticated user's role
     * (mirrors the push notification content, for users who missed it or
     * want to browse recent announcements).
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $role = $user->role === 'driver' ? 'driver' : 'passenger';

        $events = PromoEvent::where('active', true)
            ->whereIn('target_role', [$role, 'all'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn(PromoEvent $e) => [
                'id'         => $e->id,
                'title'      => $e->title,
                'body'       => $e->body,
                'image_url'  => $e->image_url,
                'created_at' => $e->created_at->toIso8601String(),
            ]);

        return $this->success(['events' => $events]);
    }
}
