<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SurgeZoneController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ChargingStationController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\RideTrackingController;
use App\Http\Controllers\Api\SafetyController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refreshToken']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::get('auth/avatar', [AuthController::class, 'getAvatar']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('auth/otp/send', [AuthController::class, 'sendOTP']);
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOTP']);

    Route::get('vehicles', [VehicleController::class, 'index']);
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show']);
    Route::post('vehicles', [VehicleController::class, 'store']);

    Route::get('marketplace', [MarketplaceController::class, 'index']);
    Route::get('marketplace/{item}', [MarketplaceController::class, 'show']);
    Route::post('marketplace', [MarketplaceController::class, 'store']);
    Route::post('marketplace/{item}/purchase', [MarketplaceController::class, 'purchase']);

    // Static ride routes — must come before {ride} wildcard.
    Route::get('rides', [RideController::class, 'index']);
    Route::get('rides/available', [RideController::class, 'available']);
    Route::get('rides/active', [RideController::class, 'active']);
    Route::post('rides/estimate', [RideController::class, 'estimate']);
    Route::post('rides', [RideController::class, 'store']);

    // Parameterised ride routes.
    Route::get('rides/{ride}', [RideController::class, 'show']);
    Route::post('rides/{ride}/accept', [RideController::class, 'accept']);
    Route::post('rides/{ride}/arrive', [RideController::class, 'arrive']);
    Route::post('rides/{ride}/start', [RideController::class, 'start']);
    Route::post('rides/{ride}/complete', [RideController::class, 'complete']);
    Route::post('rides/{ride}/cancel', [RideController::class, 'cancel']);
    Route::post('rides/{ride}/rate', [RideController::class, 'rate']);
    Route::post('rides/{ride}/dispute', [RideController::class, 'dispute']);

    // Static delivery routes must come before {delivery} wildcard routes.
    Route::get('deliveries/available', [DeliveryController::class, 'available']);
    Route::get('deliveries/nearby-drivers', [DeliveryController::class, 'nearbyDrivers']);
    Route::get('deliveries/history', [DeliveryController::class, 'history']);
    Route::post('deliveries/estimate', [DeliveryController::class, 'estimate']);

    Route::get('deliveries', [DeliveryController::class, 'index']);
    Route::post('deliveries', [DeliveryController::class, 'store']);
    Route::get('deliveries/{delivery}', [DeliveryController::class, 'show']);
    Route::put('deliveries/{delivery}', [DeliveryController::class, 'update']);
    Route::patch('deliveries/{delivery}', [DeliveryController::class, 'update']);
    Route::delete('deliveries/{delivery}', [DeliveryController::class, 'destroy']);
    Route::post('deliveries/{delivery}/accept', [DeliveryController::class, 'accept']);
    Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel']);
    Route::post('deliveries/{delivery}/track', [DeliveryController::class, 'track']);
    Route::post('deliveries/{delivery}/complete', [DeliveryController::class, 'complete']);
    Route::post('deliveries/{delivery}/rate', [DeliveryController::class, 'rate']);

    // Aliases for Moving service using the same delivery controller logic.
    Route::post('movings/estimate', [DeliveryController::class, 'estimate']);
    Route::post('movings', [DeliveryController::class, 'store']);
    Route::get('movings/{delivery}', [DeliveryController::class, 'show']);
    Route::put('movings/{delivery}', [DeliveryController::class, 'update']);
    Route::patch('movings/{delivery}', [DeliveryController::class, 'update']);
    Route::delete('movings/{delivery}', [DeliveryController::class, 'destroy']);
    Route::post('movings/{delivery}/accept', [DeliveryController::class, 'accept']);
    Route::post('movings/{delivery}/cancel', [DeliveryController::class, 'cancel']);
    Route::post('movings/{delivery}/track', [DeliveryController::class, 'track']);
    Route::post('movings/{delivery}/complete', [DeliveryController::class, 'complete']);
    Route::post('movings/{delivery}/rate', [DeliveryController::class, 'rate']);

    Route::get('charging-stations', [ChargingStationController::class, 'index']);

    Route::get('tracking/rides/{ride}', [RideTrackingController::class, 'show']);
    Route::post('tracking/rides/{ride}', [RideTrackingController::class, 'update']);

    Route::get('chats', [ChatController::class, 'index']);
    Route::post('chats', [ChatController::class, 'create']);
    Route::get('chats/{conversation}', [ChatController::class, 'show']);
    Route::post('chats/{conversation}/messages', [ChatController::class, 'store']);

    Route::get('support/tickets', [SupportController::class, 'index']);
    Route::post('support/tickets', [SupportController::class, 'store']);
    Route::post('support/tickets/{ticket}/reply', [SupportController::class, 'reply']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/send', [NotificationController::class, 'send']);

    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store']);

    Route::get('safety-incidents', [SafetyController::class, 'index']);
    Route::post('safety-incidents', [SafetyController::class, 'store']);
    Route::post('sos/alert', [SafetyController::class, 'sos']);

    // Surge Zones
    Route::get('surge/zones', [SurgeZoneController::class, 'index']);
    Route::get('surge/check', [SurgeZoneController::class, 'check']);

    // Upload — profile avatar & vehicle images
    Route::post('upload/avatar',                              [UploadController::class, 'avatar']);
    Route::delete('upload/avatar',                            [UploadController::class, 'deleteAvatar']);
    Route::post('upload/vehicle/{vehicle}/images',            [UploadController::class, 'addVehicleImage']);
    Route::delete('upload/vehicle/{vehicle}/images',          [UploadController::class, 'deleteVehicleImage']);
    Route::get('upload/vehicle/{vehicle}/images',             [UploadController::class, 'vehicleImages']);

    // Wallet
    Route::get('wallet', [WalletController::class, 'index']);
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('wallet/topup', [WalletController::class, 'requestTopUp']);
    Route::get('wallet/topup/{topup}', [WalletController::class, 'topUpStatus']);
    Route::post('wallet/withdraw', [WalletController::class, 'requestWithdrawal']);

    Route::get('drivers/nearby', [DriverController::class, 'nearby']);
    Route::get('drivers/{driver}', [DriverController::class, 'profile']);

    Route::get('driver/status', [DriverController::class, 'status']);
    Route::post('driver/availability', [DriverController::class, 'setAvailability']);
    Route::post('driver/go-online', [DriverController::class, 'goOnline']);
    Route::post('driver/go-offline', [DriverController::class, 'goOffline']);
    Route::post('driver/location', [DriverController::class, 'updateLocation']);
    Route::post('driver/rides/{ride}/decline', [DriverController::class, 'declineRide']);
    Route::get('driver/tasks', [DriverController::class, 'tasks']);
    Route::get('driver/stats', [DriverController::class, 'getDriverStats']);
    Route::post('driver/vehicles', [DriverController::class, 'registerVehicle']);
    Route::put('driver/vehicles/{vehicle}', [DriverController::class, 'updateVehicle']);
});
