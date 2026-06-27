<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Seller\SellerController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

// Public live trip tracking page (no auth required)
Route::get('/track/{token}', [TrackController::class, 'show'])->name('track.show');

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('admin/login', [AdminController::class, 'showLogin'])->name('admin.login');
Route::post('admin/login', [AdminController::class, 'login'])->name('admin.login.post');
Route::post('admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

Route::prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    // Driver Approvals
    Route::get('drivers', [AdminController::class, 'drivers'])->name('admin.drivers');
    Route::get('drivers/{driver}', [AdminController::class, 'showDriver'])->name('admin.drivers.show');
    Route::post('drivers/{driver}/approve', [AdminController::class, 'approveDriver'])->name('admin.drivers.approve');
    Route::post('drivers/{driver}/documents/{document}/review', [AdminController::class, 'reviewDocument'])->name('admin.drivers.documents.review');

    // Users
    Route::get('users', [AdminController::class, 'users'])->name('admin.users');
    Route::post('users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::put('users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');

    // Vehicles
    Route::get('vehicles', [AdminController::class, 'vehicles'])->name('admin.vehicles');
    Route::post('vehicles', [AdminController::class, 'storeVehicle'])->name('admin.vehicles.store');
    Route::put('vehicles/{vehicle}', [AdminController::class, 'updateVehicle'])->name('admin.vehicles.update');
    Route::delete('vehicles/{vehicle}', [AdminController::class, 'destroyVehicle'])->name('admin.vehicles.destroy');
    Route::post('vehicles/{vehicle}/images', [AdminController::class, 'storeVehicleImage'])->name('admin.vehicles.images.store');
    Route::delete('vehicles/{vehicle}/images', [AdminController::class, 'destroyVehicleImage'])->name('admin.vehicles.images.destroy');

    // Rides
    Route::get('rides', [AdminController::class, 'rides'])->name('admin.rides');
    Route::post('rides', [AdminController::class, 'storeRide'])->name('admin.rides.store');
    Route::put('rides/{ride}', [AdminController::class, 'updateRide'])->name('admin.rides.update');
    Route::delete('rides/{ride}', [AdminController::class, 'destroyRide'])->name('admin.rides.destroy');

    // Deliveries
    Route::get('deliveries', [AdminController::class, 'deliveries'])->name('admin.deliveries');
    Route::post('deliveries', [AdminController::class, 'storeDelivery'])->name('admin.deliveries.store');
    Route::put('deliveries/{delivery}', [AdminController::class, 'updateDelivery'])->name('admin.deliveries.update');
    Route::delete('deliveries/{delivery}', [AdminController::class, 'destroyDelivery'])->name('admin.deliveries.destroy');
    Route::post('deliveries/{delivery}/assign', [AdminController::class, 'assignDelivery'])->name('admin.deliveries.assign');

    // Marketplace
    Route::get('marketplace', [AdminController::class, 'marketplace'])->name('admin.marketplace');
    Route::post('marketplace', [AdminController::class, 'storeMarketplace'])->name('admin.marketplace.store');
    Route::put('marketplace/{item}', [AdminController::class, 'updateMarketplace'])->name('admin.marketplace.update');
    Route::delete('marketplace/{item}', [AdminController::class, 'destroyMarketplace'])->name('admin.marketplace.destroy');
    Route::delete('marketplace-images/{image}', [AdminController::class, 'destroyMarketplaceImage'])->name('admin.marketplace.images.destroy');

    // Ride Pricing
    Route::get('ride-pricing', [AdminController::class, 'ridePricing'])->name('admin.ride-pricing');
    Route::put('ride-pricing/{pricing}', [AdminController::class, 'updateRidePricing'])->name('admin.ride-pricing.update');
    Route::post('ride-pricing/settings', [AdminController::class, 'updatePricingSettings'])->name('admin.ride-pricing.settings');

    // Moving Fare Pricing
    Route::get('moving-fare', [AdminController::class, 'movingFare'])->name('admin.moving-fare');
    Route::post('moving-fare', [AdminController::class, 'updateMovingFare'])->name('admin.moving-fare.update');

    // Admin Chat
    Route::get('chat', [AdminController::class, 'adminChat'])->name('admin.chat');
    Route::get('chat/{conversation}/messages', [AdminController::class, 'adminChatMessages'])->name('admin.chat.messages');
    Route::post('chat/start', [AdminController::class, 'adminChatStart'])->name('admin.chat.start');
    Route::post('chat/{conversation}/send', [AdminController::class, 'adminChatSend'])->name('admin.chat.send');

    // Surge Zones
    Route::get('surge-zones', [AdminController::class, 'surgeZones'])->name('admin.surge-zones');
    Route::post('surge-zones', [AdminController::class, 'storeSurgeZone'])->name('admin.surge-zones.store');
    Route::put('surge-zones/{surgeZone}', [AdminController::class, 'updateSurgeZone'])->name('admin.surge-zones.update');
    Route::post('surge-zones/{surgeZone}/toggle', [AdminController::class, 'toggleSurgeZone'])->name('admin.surge-zones.toggle');
    Route::delete('surge-zones/{surgeZone}', [AdminController::class, 'destroySurgeZone'])->name('admin.surge-zones.destroy');

    // Charging Stations
    Route::get('charging-stations', [AdminController::class, 'chargingStations'])->name('admin.charging-stations');
    Route::post('charging-stations', [AdminController::class, 'storeChargingStation'])->name('admin.charging-stations.store');
    Route::put('charging-stations/{station}', [AdminController::class, 'updateChargingStation'])->name('admin.charging-stations.update');
    Route::delete('charging-stations/{station}', [AdminController::class, 'destroyChargingStation'])->name('admin.charging-stations.destroy');

    // Support
    Route::get('support', [AdminController::class, 'support'])->name('admin.support');
    Route::post('support', [AdminController::class, 'storeSupport'])->name('admin.support.store');
    Route::put('support/{ticket}', [AdminController::class, 'updateSupport'])->name('admin.support.update');
    Route::delete('support/{ticket}', [AdminController::class, 'destroySupport'])->name('admin.support.destroy');

    // Safety
    Route::get('safety', [AdminController::class, 'safety'])->name('admin.safety');
    Route::post('safety', [AdminController::class, 'storeSafety'])->name('admin.safety.store');
    Route::put('safety/{incident}', [AdminController::class, 'updateSafety'])->name('admin.safety.update');
    Route::delete('safety/{incident}', [AdminController::class, 'destroySafety'])->name('admin.safety.destroy');

    // Transactions
    Route::get('transactions', [AdminController::class, 'transactions'])->name('admin.transactions');
    Route::post('transactions/{transaction}/confirm', [AdminController::class, 'confirmTransaction'])->name('admin.transactions.confirm');
    Route::post('transactions/{transaction}/cancel', [AdminController::class, 'cancelTransaction'])->name('admin.transactions.cancel');

    // Companies
    Route::get('companies', [AdminController::class, 'companies'])->name('admin.companies');
    Route::post('companies', [AdminController::class, 'storeCompany'])->name('admin.companies.store');
    Route::put('companies/{company}', [AdminController::class, 'updateCompany'])->name('admin.companies.update');
    Route::delete('companies/{company}', [AdminController::class, 'destroyCompany'])->name('admin.companies.destroy');

    // Wallet & Transactions
    Route::get('wallet', [AdminController::class, 'walletTransactions'])->name('admin.wallet');
    Route::post('wallet/salary', [AdminController::class, 'paySalary'])->name('admin.wallet.salary');
    Route::post('wallet/credit', [AdminController::class, 'adminCredit'])->name('admin.wallet.credit');

    // Top-up Requests
    Route::get('topups', [AdminController::class, 'topups'])->name('admin.topups');
    Route::post('topups/{topup}/approve', [AdminController::class, 'approveTopUp'])->name('admin.topups.approve');
    Route::post('topups/{topup}/reject', [AdminController::class, 'rejectTopUp'])->name('admin.topups.reject');

    // Fare Management
    Route::get('fare-management', [AdminController::class, 'fareManagement'])->name('admin.fare-management');
    Route::post('fare-management', [AdminController::class, 'updateFareManagement'])->name('admin.fare-management.update');

    // Driver Withdrawal Payouts
    Route::get('withdrawals', [AdminController::class, 'withdrawals'])->name('admin.withdrawals');
    Route::post('withdrawals/{withdrawal}/approve', [AdminController::class, 'approveWithdrawal'])->name('admin.withdrawals.approve');
    Route::post('withdrawals/{withdrawal}/reject', [AdminController::class, 'rejectWithdrawal'])->name('admin.withdrawals.reject');

    // Promotional Banners
    Route::get('banners', [AdminController::class, 'banners'])->name('admin.banners');
    Route::post('banners', [AdminController::class, 'storeBanner'])->name('admin.banners.store');
    Route::put('banners/{banner}', [AdminController::class, 'updateBanner'])->name('admin.banners.update');
    Route::delete('banners/{banner}', [AdminController::class, 'destroyBanner'])->name('admin.banners.destroy');

    // Airport Zones
    Route::get('airport-zones', [AdminController::class, 'airportZones'])->name('admin.airport-zones');
    Route::post('airport-zones', [AdminController::class, 'storeAirportZone'])->name('admin.airport-zones.store');
    Route::put('airport-zones/{zone}', [AdminController::class, 'updateAirportZone'])->name('admin.airport-zones.update');
    Route::delete('airport-zones/{zone}', [AdminController::class, 'destroyAirportZone'])->name('admin.airport-zones.destroy');

    // Business Accounts
    Route::get('business-accounts', [AdminController::class, 'businessAccounts'])->name('admin.business-accounts');
    Route::get('business-accounts/{account}', [AdminController::class, 'showBusinessAccount'])->name('admin.business-accounts.show');
    Route::put('business-accounts/{account}', [AdminController::class, 'updateBusinessAccount'])->name('admin.business-accounts.update');

    // Subscription Plans
    Route::get('subscription-plans', [AdminController::class, 'subscriptionPlans'])->name('admin.subscription-plans');
    Route::post('subscription-plans', [AdminController::class, 'storeSubscriptionPlan'])->name('admin.subscription-plans.store');
    Route::put('subscription-plans/{plan}', [AdminController::class, 'updateSubscriptionPlan'])->name('admin.subscription-plans.update');
    Route::delete('subscription-plans/{plan}', [AdminController::class, 'destroySubscriptionPlan'])->name('admin.subscription-plans.destroy');
    Route::get('subscription-plans/{plan}/subscribers', [AdminController::class, 'subscriptionSubscribers'])->name('admin.subscription-plans.subscribers');
});

// ── Seller Portal ─────────────────────────────────────────────────────────────

Route::get('seller/login',  [SellerController::class, 'showLogin'])->name('seller.login');
Route::post('seller/login', [SellerController::class, 'login'])->name('seller.login.post');

Route::prefix('seller')->middleware(\App\Http\Middleware\SellerAuth::class)->group(function () {
    Route::post('logout', [SellerController::class, 'logout'])->name('seller.logout');

    Route::get('/',        [SellerController::class, 'dashboard'])->name('seller.dashboard');

    // Products
    Route::get('products',                                   [SellerController::class, 'products'])->name('seller.products');
    Route::get('products/create',                            [SellerController::class, 'createProduct'])->name('seller.products.create');
    Route::post('products',                                  [SellerController::class, 'storeProduct'])->name('seller.products.store');
    Route::get('products/{product}/edit',                    [SellerController::class, 'editProduct'])->name('seller.products.edit');
    Route::put('products/{product}',                         [SellerController::class, 'updateProduct'])->name('seller.products.update');
    Route::delete('products/{product}',                      [SellerController::class, 'deleteProduct'])->name('seller.products.destroy');
    Route::delete('products/{product}/images/{image}',       [SellerController::class, 'deleteProductImage'])->name('seller.products.images.destroy');

    // Orders
    Route::get('orders',                                     [SellerController::class, 'orders'])->name('seller.orders');
    Route::post('orders/{order}/confirm',                    [SellerController::class, 'confirmOrder'])->name('seller.orders.confirm');
    Route::post('orders/{order}/complete',                   [SellerController::class, 'completeOrder'])->name('seller.orders.complete');
    Route::post('orders/{order}/cancel',                     [SellerController::class, 'cancelOrder'])->name('seller.orders.cancel');
});
