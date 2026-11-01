<?php

namespace App\Services;

use App\Events\RealtimeUpdate;
use App\Jobs\SyncFirestore;
use App\Models\DriverSession;
use App\Models\User;

/**
 * Takes a driver offline from the server side — e.g. a withdrawal dropped
 * their wallet under the minimum balance needed to work. Mirrors what the
 * app's own "go offline" does, and tells the driver app so it updates now.
 */
class DriverAvailabilityService
{
    public function __construct(
        private FirestoreService $firestore,
        private FcmService $fcm,
    ) {}

    /**
     * Push: "money withdrawn — balance under the minimum, top up to go online".
     * Sent whether or not the driver was online, so they know before trying.
     */
    public function notifyLowBalance(User $driver): void
    {
        try {
            $this->fcm->sendToDriver($driver,
                ...FcmService::message('driver_low_balance_offline', [
                    'balance' => number_format((int) $driver->wallet_balance),
                    'min'     => number_format(User::minDriverBalance()),
                ]),
                data: ['type' => 'driver_low_balance', 'reason' => 'low_balance']
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Wallet under the minimum to work? Online → switch offline (with push);
     * already offline → just the push, when [notifyIfOffline]. Returns true
     * when the driver was taken offline.
     */
    public function enforceMinBalance(User $driver, bool $notifyIfOffline = false): bool
    {
        if ($driver->role !== 'driver' || $driver->hasMinBalance()) return false;

        if ($driver->available) {
            $this->forceOffline($driver, 'low_balance');
            return true;
        }
        if ($notifyIfOffline) $this->notifyLowBalance($driver);
        return false;
    }

    public function forceOffline(User $driver, string $reason): void
    {
        DriverSession::where('driver_id', $driver->id)->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $driver->update(['available' => false]);

        SyncFirestore::dispatch('syncDriver', [$driver->fresh()]);
        try {
            $this->firestore->updateDriverLiveStatus($driver->id, 'offline');
        } catch (\Throwable $e) {
            report($e);
        }

        if ($reason === 'low_balance') $this->notifyLowBalance($driver);

        RealtimeUpdate::toDriver($driver->id, 'driver.forced_offline', [
            'reason'          => $reason,
            'wallet_balance'  => (int) $driver->wallet_balance,
            'min_balance_khr' => User::minDriverBalance(),
        ]);
    }
}
