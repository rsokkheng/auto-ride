<?php

namespace App\Http\Controllers\Api;

use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends ApiController
{
    /**
     * GET /v1/banners?role=passenger|driver
     * Returns active banners valid right now for the given role.
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $role = $user->role === 'driver' ? 'driver' : 'passenger';

        $banners = Banner::where('active', true)
            ->whereIn('target_role', [$role, 'all'])
            ->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'image', 'deeplink', 'valid_until'])
            ->map(fn($b) => [
                'id'          => $b->id,
                'title'       => $b->title,
                'image_url'   => asset('storage/' . $b->image),
                'deeplink'    => $b->deeplink,
                'valid_until' => $b->valid_until?->toIso8601String(),
            ]);

        return $this->success(['banners' => $banners]);
    }
}
