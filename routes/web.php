<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\SettlementController;
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
    Route::post('deliveries/{delivery}/complete', [AdminController::class, 'completeDelivery'])->name('admin.deliveries.complete');

    // Marketplace
    Route::get('marketplace', [AdminController::class, 'marketplace'])->name('admin.marketplace');
    Route::post('marketplace', [AdminController::class, 'storeMarketplace'])->name('admin.marketplace.store');
    Route::put('marketplace/{item}', [AdminController::class, 'updateMarketplace'])->name('admin.marketplace.update');
    Route::delete('marketplace/{item}', [AdminController::class, 'destroyMarketplace'])->name('admin.marketplace.destroy');
    Route::delete('marketplace-images/{image}', [AdminController::class, 'destroyMarketplaceImage'])->name('admin.marketplace.images.destroy');

    // Marketplace Orders
    Route::get('marketplace-orders', [AdminController::class, 'marketplaceOrders'])->name('admin.marketplace-orders');
    Route::post('marketplace-orders/{order}/confirm', [AdminController::class, 'confirmMarketplaceOrder'])->name('admin.marketplace-orders.confirm');
    Route::post('marketplace-orders/{order}/complete', [AdminController::class, 'completeMarketplaceOrder'])->name('admin.marketplace-orders.complete');
    Route::post('marketplace-orders/{order}/cancel', [AdminController::class, 'cancelMarketplaceOrder'])->name('admin.marketplace-orders.cancel');

    // Car Rentals
    Route::get('car-rentals', [AdminController::class, 'carRentals'])->name('admin.car-rentals');
    Route::post('car-rentals/{rental}/confirm', [AdminController::class, 'confirmCarRental'])->name('admin.car-rentals.confirm');
    Route::post('car-rentals/{rental}/complete', [AdminController::class, 'completeCarRental'])->name('admin.car-rentals.complete');
    Route::post('car-rentals/{rental}/cancel', [AdminController::class, 'cancelCarRental'])->name('admin.car-rentals.cancel');

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
    Route::post('transactions/confirm-bulk', [AdminController::class, 'confirmTransactionBulk'])->name('admin.transactions.confirm-bulk');
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
    Route::get('withdrawals/export', [AdminController::class, 'exportWithdrawals'])->name('admin.withdrawals.export');
    Route::post('withdrawals/bulk-approve', [AdminController::class, 'bulkApproveWithdrawals'])->name('admin.withdrawals.bulk-approve');
    Route::post('withdrawals/bulk-reject', [AdminController::class, 'bulkRejectWithdrawals'])->name('admin.withdrawals.bulk-reject');
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

    // Reports
    Route::get('operations-report',  [AdminController::class, 'operationsReport'])->name('admin.operations-report');
    Route::get('report/orders',       [AdminController::class, 'reportOrders'])->name('admin.report.orders');
    Route::get('report/drivers',      [AdminController::class, 'reportDrivers'])->name('admin.report.drivers');
    Route::get('report/partners',     [AdminController::class, 'reportPartners'])->name('admin.report.partners');
    Route::get('report/customers',    [AdminController::class, 'reportCustomers'])->name('admin.report.customers');
    Route::get('report/financial',    [AdminController::class, 'reportFinancial'])->name('admin.report.financial');
    Route::get('report/wallet',       [AdminController::class, 'reportWallet'])->name('admin.report.wallet');
    Route::get('report/withdrawals',  [AdminController::class, 'reportWithdrawals'])->name('admin.report.withdrawals');
    Route::get('report/commission',   [AdminController::class, 'reportCommission'])->name('admin.report.commission');
    Route::get('report/performance',  [AdminController::class, 'reportPerformance'])->name('admin.report.performance');
    Route::get('report/driver-ranking', [AdminController::class, 'reportDriverRanking'])->name('admin.report.driver-ranking');
    Route::get('report/analytics',    [AdminController::class, 'reportAnalytics'])->name('admin.report.analytics');

    // Report Exports
    Route::get('export/operations',     [ReportExportController::class, 'operations'])->name('admin.export.operations');
    Route::get('export/orders',         [ReportExportController::class, 'orders'])->name('admin.export.orders');
    Route::get('export/drivers',        [ReportExportController::class, 'drivers'])->name('admin.export.drivers');
    Route::get('export/partners',       [ReportExportController::class, 'partners'])->name('admin.export.partners');
    Route::get('export/customers',      [ReportExportController::class, 'customers'])->name('admin.export.customers');
    Route::get('export/financial',      [ReportExportController::class, 'financial'])->name('admin.export.financial');
    Route::get('export/wallet',         [ReportExportController::class, 'wallet'])->name('admin.export.wallet');
    Route::get('export/withdrawals',    [ReportExportController::class, 'withdrawals'])->name('admin.export.withdrawals');
    Route::get('export/commission',     [ReportExportController::class, 'commission'])->name('admin.export.commission');
    Route::get('export/performance',    [ReportExportController::class, 'performance'])->name('admin.export.performance');
    Route::get('export/driver-ranking', [ReportExportController::class, 'driverRanking'])->name('admin.export.driver-ranking');
    Route::get('export/analytics',      [ReportExportController::class, 'analytics'])->name('admin.export.analytics');

    // Payment Settlements
    Route::get('settlements',                                    [SettlementController::class, 'index'])->name('admin.settlements.index');
    Route::get('settlements/create',                             [SettlementController::class, 'create'])->name('admin.settlements.create');
    Route::get('settlements/preview',                            [SettlementController::class, 'preview'])->name('admin.settlements.preview');
    Route::post('settlements',                                   [SettlementController::class, 'store'])->name('admin.settlements.store');
    Route::get('settlements/{settlement}',                       [SettlementController::class, 'show'])->name('admin.settlements.show');
    Route::patch('settlements/{settlement}/approve',             [SettlementController::class, 'approve'])->name('admin.settlements.approve');
    Route::patch('settlements/{settlement}/reject',              [SettlementController::class, 'reject'])->name('admin.settlements.reject');
    Route::patch('settlements/{settlement}/processing',          [SettlementController::class, 'markProcessing'])->name('admin.settlements.processing');
    Route::patch('settlements/{settlement}/paid',                [SettlementController::class, 'markPaid'])->name('admin.settlements.paid');
    Route::patch('settlements/{settlement}/failed',              [SettlementController::class, 'markFailed'])->name('admin.settlements.failed');
    Route::patch('settlements/{settlement}/cancel',              [SettlementController::class, 'cancel'])->name('admin.settlements.cancel');
    Route::delete('settlements/{settlement}',                    [SettlementController::class, 'destroy'])->name('admin.settlements.destroy');
    Route::post('settlements/bulk-approve',                      [SettlementController::class, 'bulkApprove'])->name('admin.settlements.bulk-approve');
    Route::post('settlements/bulk-process',                      [SettlementController::class, 'bulkProcess'])->name('admin.settlements.bulk-process');

    // Partner Contracts
    Route::get('partner-contracts',                    [AdminController::class, 'partnerContracts'])->name('admin.partner-contracts');
    Route::post('partner-contracts',                   [AdminController::class, 'storePartnerContract'])->name('admin.partner-contracts.store');
    Route::put('partner-contracts/{contract}',         [AdminController::class, 'updatePartnerContract'])->name('admin.partner-contracts.update');
    Route::delete('partner-contracts/{contract}',      [AdminController::class, 'destroyPartnerContract'])->name('admin.partner-contracts.destroy');

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

// ── Partner Panel ─────────────────────────────────────────────────────────────
Route::get('partner/login',  [\App\Http\Controllers\Partner\PartnerController::class, 'loginPage'])->name('partner.login');
Route::post('partner/login', [\App\Http\Controllers\Partner\PartnerController::class, 'login'])->name('partner.login.post');

Route::prefix('partner')->middleware(\App\Http\Middleware\PartnerAuth::class)->group(function () {
    Route::post('logout',                [\App\Http\Controllers\Partner\PartnerController::class, 'logout'])->name('partner.logout');
    Route::get('/',                      [\App\Http\Controllers\Partner\PartnerController::class, 'dashboard'])->name('partner.dashboard');

    // Orders
    Route::get('orders',                 [\App\Http\Controllers\Partner\PartnerController::class, 'orders'])->name('partner.orders');
    Route::get('orders/create',          [\App\Http\Controllers\Partner\PartnerController::class, 'createOrder'])->name('partner.orders.create');
    Route::post('orders',                [\App\Http\Controllers\Partner\PartnerController::class, 'storeOrder'])->name('partner.orders.store');
    Route::get('orders/{delivery}',      [\App\Http\Controllers\Partner\PartnerController::class, 'showOrder'])->name('partner.orders.show');
    Route::post('orders/{delivery}/assign', [\App\Http\Controllers\Partner\PartnerController::class, 'assignDriver'])->name('partner.orders.assign');
    Route::post('orders/{delivery}/cancel', [\App\Http\Controllers\Partner\PartnerController::class, 'cancelOrder'])->name('partner.orders.cancel');

    // Wallet
    Route::get('wallet',                 [\App\Http\Controllers\Partner\PartnerController::class, 'wallet'])->name('partner.wallet');

    // Reports
    Route::get('reports',                [\App\Http\Controllers\Partner\PartnerController::class, 'reports'])->name('partner.reports');

    // Issues
    Route::get('issues',                 [\App\Http\Controllers\Partner\PartnerController::class, 'issues'])->name('partner.issues');
});

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
