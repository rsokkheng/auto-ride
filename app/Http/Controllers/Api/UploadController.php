<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends ApiController
{
    private const ALLOWED_MIME  = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    private const MAX_SIZE_KB   = 3072;   // 3 MB
    private const MAX_VEHICLE_IMAGES = 5;

    // ── Profile Avatar ────────────────────────────────────────────────────────

    /**
     * POST /v1/upload/avatar
     *
     * Form-data:
     *   avatar  file  required  (jpeg|png|webp, max 3 MB)
     *
     * Replaces the existing avatar if one is already set.
     * Returns the full public URL of the new avatar.
     */
    public function avatar(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $request->validate([
            'avatar' => 'required|file|mimes:jpeg,jpg,png,webp|max:' . self::MAX_SIZE_KB,
        ]);

        // Delete old avatar if exists.
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $file = $request->file('avatar');
        $path = $file->storeAs(
            'avatars',
            $user->id . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $user->update(['avatar' => $path]);

        return $this->success([
            'avatar_url' => asset('storage/' . $path),
            'path'       => $path,
        ]);
    }

    /**
     * DELETE /v1/upload/avatar
     *
     * Removes the current profile avatar.
     */
    public function deleteAvatar(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $this->success(['message' => 'Avatar removed.']);
    }

    // ── Vehicle Images ────────────────────────────────────────────────────────

    /**
     * POST /v1/upload/vehicle/{vehicle}/images
     *
     * Form-data:
     *   image  file  required  (jpeg|png|webp, max 3 MB)
     *
     * Adds one photo to the vehicle's image gallery (max 5 total).
     * Only the vehicle owner (driver) may upload.
     */
    public function addVehicleImage(Request $request, Vehicle $vehicle)
    {
        $user = $this->authUser($request);
        if (! $user || $vehicle->user_id !== $user->id) {
            return $this->unauthorized();
        }

        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,webp|max:' . self::MAX_SIZE_KB,
        ]);

        $current = $vehicle->images ?? [];

        if (count($current) >= self::MAX_VEHICLE_IMAGES) {
            return response()->json([
                'message' => 'Maximum ' . self::MAX_VEHICLE_IMAGES . ' images allowed per vehicle.',
            ], 422);
        }

        $file = $request->file('image');
        $path = $file->storeAs(
            'vehicles/' . $vehicle->id,
            Str::random(12) . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $current[] = $path;
        $vehicle->update(['images' => $current]);

        return $this->success([
            'image_url'   => asset('storage/' . $path),
            'path'        => $path,
            'image_urls'  => $vehicle->fresh()->image_urls,
            'total'       => count($current),
        ], 201);
    }

    /**
     * DELETE /v1/upload/vehicle/{vehicle}/images
     *
     * Body (JSON):
     *   path  string  required  (the path returned from upload, e.g. "vehicles/3/abc.jpg")
     *
     * Removes one specific image from the vehicle gallery.
     * Only the vehicle owner may delete.
     */
    public function deleteVehicleImage(Request $request, Vehicle $vehicle)
    {
        $user = $this->authUser($request);
        if (! $user || $vehicle->user_id !== $user->id) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'path' => 'required|string',
        ]);

        $current = $vehicle->images ?? [];

        if (! in_array($data['path'], $current, true)) {
            return response()->json(['message' => 'Image not found on this vehicle.'], 404);
        }

        Storage::disk('public')->delete($data['path']);

        $updated = array_values(array_filter($current, fn($p) => $p !== $data['path']));
        $vehicle->update(['images' => $updated]);

        return $this->success([
            'message'    => 'Image deleted.',
            'image_urls' => $vehicle->fresh()->image_urls,
            'total'      => count($updated),
        ]);
    }

    /**
     * GET /v1/upload/vehicle/{vehicle}/images
     *
     * Returns all image URLs for a vehicle.
     */
    public function vehicleImages(Request $request, Vehicle $vehicle)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        return $this->success([
            'vehicle_id'  => $vehicle->id,
            'image_urls'  => $vehicle->image_urls,
            'total'       => count($vehicle->images ?? []),
        ]);
    }
}
