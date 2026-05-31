<?php

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('admin/login', [AdminController::class, 'showLogin'])->name('admin.login');
Route::post('admin/login', [AdminController::class, 'login'])->name('admin.login.post');
Route::post('admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

Route::prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');

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
});
