<?php

use App\Models\ChatConversation;
use App\Models\Delivery;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Authorized via POST /api/v1/broadcasting/auth using the app's bearer
| api_token (see AuthenticateApiToken). Admins may join any ride/delivery/
| conversation channel for the dispatch console.
*/

Broadcast::channel('user.{userId}', fn (User $user, int $userId) => $user->id === $userId);

Broadcast::channel('driver.{driverId}', fn (User $user, int $driverId) =>
    $user->id === $driverId && $user->role === 'driver'
);

Broadcast::channel('ride.{rideId}', function (User $user, int $rideId) {
    if ($user->role === 'admin') return true;

    return Ride::whereKey($rideId)
        ->where(fn ($q) => $q->where('passenger_id', $user->id)->orWhere('driver_id', $user->id))
        ->exists();
});

Broadcast::channel('delivery.{deliveryId}', function (User $user, int $deliveryId) {
    if ($user->role === 'admin') return true;

    return Delivery::whereKey($deliveryId)
        ->where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('driver_id', $user->id))
        ->exists();
});

Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    if ($user->role === 'admin') return true;

    return ChatConversation::whereKey($conversationId)
        ->where(fn ($q) => $q->where('passenger_id', $user->id)->orWhere('driver_id', $user->id))
        ->exists();
});
