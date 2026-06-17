<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DriverDocumentController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\SurgeZoneController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ChargingStationController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DeliveryFeaturesController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverFeaturesController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\RideFeaturesController;
use App\Http\Controllers\Api\RideTrackingController;
use App\Http\Controllers\Api\SafetyController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TripHistoryController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\AccessibilityController;
use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BiometricController;
use App\Http\Controllers\Api\HelmetDetectionController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\MultiAccountController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\QrPaymentController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\WithdrawalController;
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
    Route::post('auth/fcm-token', [AuthController::class, 'saveFcmToken']);

    Route::get('vehicles', [VehicleController::class, 'index']);
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show']);
    Route::post('vehicles', [VehicleController::class, 'store']);

    // Marketplace — categories
    Route::get('marketplace/categories', [MarketplaceController::class, 'categories']);

    // Marketplace — my listings & orders (static, before {product} wildcard)
    Route::get('marketplace/my-products', [MarketplaceController::class, 'myProducts']);
    Route::get('marketplace/my-orders',   [MarketplaceController::class, 'myOrders']);

    // Marketplace — products CRUD
    Route::get('marketplace',                            [MarketplaceController::class, 'index']);
    Route::post('marketplace',                           [MarketplaceController::class, 'store']);
    Route::get('marketplace/{product}',                  [MarketplaceController::class, 'show']);
    Route::put('marketplace/{product}',                  [MarketplaceController::class, 'update']);
    Route::patch('marketplace/{product}',                [MarketplaceController::class, 'update']);
    Route::delete('marketplace/{product}',               [MarketplaceController::class, 'destroy']);

    // Marketplace — product images
    Route::post('marketplace/{product}/images',                    [MarketplaceController::class, 'addImage']);
    Route::delete('marketplace/{product}/images/{image}',          [MarketplaceController::class, 'deleteImage']);

    // Marketplace — orders
    Route::post('marketplace/{product}/order',           [MarketplaceController::class, 'placeOrder']);
    Route::post('marketplace/orders/{order}/confirm',    [MarketplaceController::class, 'confirmOrder']);
    Route::post('marketplace/orders/{order}/complete',   [MarketplaceController::class, 'completeOrder']);
    Route::post('marketplace/orders/{order}/cancel',     [MarketplaceController::class, 'cancelOrder']);

    // Legacy marketplace_items (backward compat)
    Route::post('marketplace/items/{item}/purchase', [MarketplaceController::class, 'purchase']);

    // ── Unified trip history ──────────────────────────────────────────────────
    Route::get('trips', [TripHistoryController::class, 'index']);
    Route::get('trips/months', [TripHistoryController::class, 'months']);

    // Static ride routes — must come before {ride} wildcard.
    Route::get('rides', [RideController::class, 'index']);
    Route::get('rides/available', [RideController::class, 'available']);
    Route::get('rides/active', [RideController::class, 'active']);
    Route::get('rides/scheduled', [RideFeaturesController::class, 'scheduled']);
    Route::get('rides/reorder-last', [RideFeaturesController::class, 'reorderLast']);
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
    Route::post('rides/{ride}/tip', [RideController::class, 'tip']);

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
    Route::post('deliveries/{delivery}/start', [DeliveryController::class, 'start']);
    Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel']);
    Route::post('deliveries/{delivery}/track', [DeliveryController::class, 'track']);
    Route::post('deliveries/{delivery}/complete', [DeliveryController::class, 'complete']);
    Route::post('deliveries/{delivery}/rate', [DeliveryController::class, 'rate']);

    // Aliases for Moving service using the same delivery controller logic.
    Route::post('movings/estimate', [DeliveryController::class, 'estimateMoving']);
    Route::get('movings', [DeliveryController::class, 'indexMoving']);
    Route::post('movings', [DeliveryController::class, 'storeMoving']);
    Route::get('movings/{delivery}', [DeliveryController::class, 'show']);
    Route::put('movings/{delivery}', [DeliveryController::class, 'update']);
    Route::patch('movings/{delivery}', [DeliveryController::class, 'update']);
    Route::delete('movings/{delivery}', [DeliveryController::class, 'destroy']);
    Route::post('movings/{delivery}/accept', [DeliveryController::class, 'accept']);
    Route::post('movings/{delivery}/start', [DeliveryController::class, 'start']);
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
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

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
    Route::get('wallet/balance', [WalletController::class, 'balance']);
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('wallet/topup', [WalletController::class, 'requestTopUp']);
    Route::get('wallet/topup/{topup}', [WalletController::class, 'topUpStatus']);
    Route::post('wallet/withdraw', [WalletController::class, 'requestWithdrawal']);
    Route::post('wallet/transfer', [WalletController::class, 'transfer']);

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

    // ── Saved places ──────────────────────────────────────────────────────────
    Route::get('saved-places', [UserProfileController::class, 'savedPlaces']);
    Route::post('saved-places', [UserProfileController::class, 'storeSavedPlace']);
    Route::put('saved-places/{place}', [UserProfileController::class, 'updateSavedPlace']);
    Route::patch('saved-places/{place}', [UserProfileController::class, 'updateSavedPlace']);
    Route::delete('saved-places/{place}', [UserProfileController::class, 'destroySavedPlace']);

    // ── Emergency contacts ────────────────────────────────────────────────────
    Route::get('emergency-contacts', [UserProfileController::class, 'emergencyContacts']);
    Route::post('emergency-contacts', [UserProfileController::class, 'storeEmergencyContact']);
    Route::put('emergency-contacts/{contact}', [UserProfileController::class, 'updateEmergencyContact']);
    Route::patch('emergency-contacts/{contact}', [UserProfileController::class, 'updateEmergencyContact']);
    Route::delete('emergency-contacts/{contact}', [UserProfileController::class, 'destroyEmergencyContact']);

    // ── Ride features (stops, share, reorder, promo, safety) ─────────────────
    Route::get('rides/{ride}/eta', [RideFeaturesController::class, 'eta']);
    Route::get('rides/{ride}/stops', [RideFeaturesController::class, 'stops']);
    Route::post('rides/{ride}/stops', [RideFeaturesController::class, 'addStops']);
    Route::post('rides/{ride}/stops/{stop}/arrive', [RideFeaturesController::class, 'markStopArrived']);
    Route::post('rides/{ride}/share', [RideFeaturesController::class, 'shareToken']);
    Route::delete('rides/{ride}/share', [RideFeaturesController::class, 'deactivateShare']);
    Route::post('rides/{ride}/sos', [RideFeaturesController::class, 'sos']);
    Route::post('rides/{ride}/timeout', [RideFeaturesController::class, 'setPickupTimeout']);

    // ── Delivery features (stops, proof, live location) ───────────────────────
    Route::get('deliveries/{delivery}/stops', [DeliveryFeaturesController::class, 'stops']);
    Route::post('deliveries/{delivery}/stops', [DeliveryFeaturesController::class, 'addStops']);
    Route::post('deliveries/{delivery}/proof', [DeliveryFeaturesController::class, 'uploadProof']);
    Route::post('deliveries/{delivery}/stops/{stop}/proof', [DeliveryFeaturesController::class, 'uploadStopProof']);
    Route::get('deliveries/{delivery}/driver-location', [DeliveryFeaturesController::class, 'driverLocation']);

    // ── Promo codes ───────────────────────────────────────────────────────────
    Route::post('promo-codes/validate', [PromoCodeController::class, 'check']);
    Route::get('promos/active', [PromoCodeController::class, 'active']);

    // ── Rewards ───────────────────────────────────────────────────────────────
    Route::get('rewards/balance', [PromoCodeController::class, 'rewardsBalance']);

    // ── Driver features ───────────────────────────────────────────────────────
    Route::get('driver/dashboard', [DriverFeaturesController::class, 'dashboard']);
    Route::get('driver/earnings', [DriverFeaturesController::class, 'earnings']);
    Route::get('driver/earnings/summary', [DriverFeaturesController::class, 'earningsSummary']);
    Route::get('driver/earnings/history', [DriverFeaturesController::class, 'earningsHistory']);
    Route::get('driver/incentives', [DriverFeaturesController::class, 'incentives']);
    Route::get('driver/cancellation-status', [DriverFeaturesController::class, 'cancellationStatus']);
    Route::get('driver/approval-status', [DriverFeaturesController::class, 'approvalStatus']);
    Route::get('driver/heatmap', [DriverFeaturesController::class, 'heatmap']);

    // ── Admin: driver approval & documents ────────────────────────────────────
    Route::get('admin/drivers/pending', [DriverFeaturesController::class, 'pendingDrivers']);
    Route::post('admin/drivers/{driver}/approve', [DriverFeaturesController::class, 'approveDriver']);
    Route::get('admin/drivers/{driver}/documents', [DriverDocumentController::class, 'adminView']);
    Route::post('admin/drivers/{driver}/documents/{document}/review', [DriverDocumentController::class, 'adminReview']);

    // ── Driver documents ──────────────────────────────────────────────────────
    Route::get('driver/documents', [DriverDocumentController::class, 'index']);
    Route::post('driver/documents', [DriverDocumentController::class, 'upload']);

    // ── Safety features & contact aliases ────────────────────────────────────
    Route::post('safety/fake-call', [RideFeaturesController::class, 'fakeCall']);
    Route::get('safety/contacts', [UserProfileController::class, 'emergencyContacts']);
    Route::post('safety/contacts', [UserProfileController::class, 'storeEmergencyContact']);
    Route::delete('safety/contacts/{contact}', [UserProfileController::class, 'destroyEmergencyContact']);
    Route::get('rides/{ride}/masked-phone', [DriverFeaturesController::class, 'maskedPhone']);


    // ── Surge alias ───────────────────────────────────────────────────────────
    Route::get('surge', [SurgeZoneController::class, 'check']);

    // ── Driver trip history & rate-passenger ─────────────────────────────────
    Route::get('driver/trips', [DriverFeaturesController::class, 'trips']);
    Route::post('rides/{ride}/rate-passenger', [RideController::class, 'ratePassenger']);

    // ── Loyalty / rewards ─────────────────────────────────────────────────────
    Route::get('loyalty', [LoyaltyController::class, 'index']);
    Route::post('loyalty/redeem', [LoyaltyController::class, 'redeem']);

    // ── Referrals ─────────────────────────────────────────────────────────────
    Route::get('referrals', [ReferralController::class, 'index']);

    // ── Car rentals ───────────────────────────────────────────────────────────
    Route::get('rentals', [RentalController::class, 'index']);
    Route::post('rentals', [RentalController::class, 'store']);

    // ── Promo apply (alias to validate — same logic, booking-time friendly) ──
    Route::post('promo/apply', [PromoCodeController::class, 'check']);

    // ── Public trip tracking (no auth required) ───────────────────────────────
    Route::get('track/{token}', [RideFeaturesController::class, 'trackByToken']);

    // ── Social login ──────────────────────────────────────────────────────────
    Route::post('auth/social', [AuthController::class, 'socialLogin']);

    // ── In-app call token (Agora RTC) ─────────────────────────────────────────
    Route::post('rides/{ride}/call-token', [RideFeaturesController::class, 'callToken']);

    // ── Helmet detection ──────────────────────────────────────────────────────
    Route::post('driver/helmet-check', [HelmetDetectionController::class, 'check']);

    // ── Voucher store ─────────────────────────────────────────────────────────
    Route::get('vouchers', [VoucherController::class, 'index']);
    Route::post('vouchers/apply', [VoucherController::class, 'apply']);
    Route::get('vouchers/mine', [VoucherController::class, 'mine']);
    Route::post('vouchers/{voucher}/claim', [VoucherController::class, 'claim']);

    // ── Promotional banners ───────────────────────────────────────────────────
    Route::get('banners', [BannerController::class, 'index']);

    // ── Driver withdrawals ────────────────────────────────────────────────────
    Route::post('driver/withdraw', [WithdrawalController::class, 'store']);
    Route::get('driver/withdrawals', [WithdrawalController::class, 'index']);

    // ── QR Payment ────────────────────────────────────────────────────────────
    Route::post('payments/qr/generate', [QrPaymentController::class, 'generate']);
    Route::get('payments/qr', [QrPaymentController::class, 'index']);
    Route::get('payments/qr/{reference}/status', [QrPaymentController::class, 'status']);
    Route::post('payments/qr/webhook', [QrPaymentController::class, 'webhook']);

    // ── Biometric / Face ID ───────────────────────────────────────────────────
    Route::post('auth/biometric/register', [BiometricController::class, 'register']);
    Route::post('auth/biometric/challenge', [BiometricController::class, 'challenge']);
    Route::post('auth/biometric/verify', [BiometricController::class, 'verify']);
    Route::get('auth/biometric/devices', [BiometricController::class, 'devices']);
    Route::delete('auth/biometric/devices/{device}', [BiometricController::class, 'revoke']);

    // ── Multi-account ─────────────────────────────────────────────────────────
    Route::get('accounts', [MultiAccountController::class, 'index']);
    Route::post('accounts/link', [MultiAccountController::class, 'link']);
    Route::post('accounts/switch/{linkId}', [MultiAccountController::class, 'switchAccount']);
    Route::delete('accounts/{linkId}', [MultiAccountController::class, 'unlink']);

    // ── Membership tiers ──────────────────────────────────────────────────────
    Route::get('membership', [MembershipController::class, 'index']);
    Route::get('membership/tiers', [MembershipController::class, 'tiers']);

    // ── Onboarding tour ───────────────────────────────────────────────────────
    Route::get('onboarding', [OnboardingController::class, 'show']);
    Route::post('onboarding/step', [OnboardingController::class, 'completeStep']);
    Route::post('onboarding/skip', [OnboardingController::class, 'skip']);

    // ── Accessibility settings ────────────────────────────────────────────────
    Route::get('accessibility', [AccessibilityController::class, 'show']);
    Route::put('accessibility', [AccessibilityController::class, 'update']);

    // ── Mobile Admin API ──────────────────────────────────────────────────────
    // Auth (no bearer token required)
    Route::post('admin/login', [AdminApiController::class, 'login']);

    // All routes below require Bearer token with role=admin (enforced inside controller)
    Route::post('admin/logout',  [AdminApiController::class, 'logout']);

    // Dashboard
    Route::get('admin/stats',    [AdminApiController::class, 'stats']);

    // Users
    Route::get('admin/users',                       [AdminApiController::class, 'users']);
    Route::get('admin/users/{user}',                [AdminApiController::class, 'showUser']);
    Route::put('admin/users/{user}',                [AdminApiController::class, 'updateUser']);
    Route::delete('admin/users/{user}',             [AdminApiController::class, 'deleteUser']);
    Route::post('admin/users/{user}/credit',        [AdminApiController::class, 'creditUser']);

    // Drivers
    Route::get('admin/drivers',                                              [AdminApiController::class, 'drivers']);
    Route::get('admin/drivers/{driver}',                                     [AdminApiController::class, 'showDriver']);
    Route::post('admin/drivers/{driver}/approve',                            [AdminApiController::class, 'approveDriver']);
    Route::post('admin/drivers/{driver}/documents/{document}/review',        [AdminApiController::class, 'reviewDocument']);

    // Rides
    Route::get('admin/rides',              [AdminApiController::class, 'rides']);
    Route::get('admin/rides/{ride}',       [AdminApiController::class, 'showRide']);
    Route::post('admin/rides/{ride}/cancel', [AdminApiController::class, 'cancelRide']);

    // Deliveries
    Route::get('admin/deliveries',              [AdminApiController::class, 'deliveries']);
    Route::get('admin/deliveries/{delivery}',   [AdminApiController::class, 'showDelivery']);

    // Withdrawals
    Route::get('admin/withdrawals',                              [AdminApiController::class, 'withdrawals']);
    Route::post('admin/withdrawals/{withdrawal}/approve',        [AdminApiController::class, 'approveWithdrawal']);
    Route::post('admin/withdrawals/{withdrawal}/reject',         [AdminApiController::class, 'rejectWithdrawal']);

    // Top-ups
    Route::get('admin/topups',                       [AdminApiController::class, 'topups']);
    Route::post('admin/topups/{topup}/approve',      [AdminApiController::class, 'approveTopUp']);
    Route::post('admin/topups/{topup}/reject',       [AdminApiController::class, 'rejectTopUp']);

    // Support tickets
    Route::get('admin/support',                           [AdminApiController::class, 'support']);
    Route::get('admin/support/{ticket}',                  [AdminApiController::class, 'showTicket']);
    Route::post('admin/support/{ticket}/reply',           [AdminApiController::class, 'replyTicket']);
    Route::put('admin/support/{ticket}/status',           [AdminApiController::class, 'updateTicketStatus']);

    // Transactions
    Route::get('admin/transactions', [AdminApiController::class, 'transactions']);

    // Banners
    Route::get('admin/banners',              [AdminApiController::class, 'banners']);
    Route::post('admin/banners',             [AdminApiController::class, 'storeBanner']);
    Route::put('admin/banners/{banner}',     [AdminApiController::class, 'updateBanner']);
    Route::delete('admin/banners/{banner}',  [AdminApiController::class, 'destroyBanner']);

    // Surge zones
    Route::get('admin/surge-zones',                    [AdminApiController::class, 'surgeZones']);
    Route::post('admin/surge-zones',                   [AdminApiController::class, 'storeSurgeZone']);
    Route::put('admin/surge-zones/{zone}',             [AdminApiController::class, 'updateSurgeZone']);
    Route::delete('admin/surge-zones/{zone}',          [AdminApiController::class, 'destroySurgeZone']);
    Route::post('admin/surge-zones/{zone}/toggle',     [AdminApiController::class, 'toggleSurgeZone']);

    // Pricing settings
    Route::get('admin/pricing',         [AdminApiController::class, 'pricing']);
    Route::put('admin/pricing/settings', [AdminApiController::class, 'updatePricing']);

    // Safety incidents
    Route::get('admin/safety', [AdminApiController::class, 'safety']);
});
