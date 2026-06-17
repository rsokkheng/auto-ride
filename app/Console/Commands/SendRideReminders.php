<?php

namespace App\Console\Commands;

use App\Models\Ride;
use App\Services\FcmService;
use Illuminate\Console\Command;

class SendRideReminders extends Command
{
    protected $signature   = 'rides:remind';
    protected $description = 'Send push notifications for rides scheduled in the next 30 minutes';

    public function handle(FcmService $fcm): void
    {
        $now    = now();
        $window = $now->copy()->addMinutes(30);

        // Find rides scheduled in [now+14min, now+30min] to avoid duplicate sends
        // (command runs every 15 min, so we check a 16-min window with 1-min overlap)
        $rides = Ride::with('passenger')
            ->where('status', Ride::STATUS_REQUESTED)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$now->copy()->addMinutes(14), $window])
            ->whereNull('reminder_sent_at')
            ->get();

        foreach ($rides as $ride) {
            if (! $ride->passenger || ! $ride->passenger->fcm_token) {
                continue;
            }

            $minutesAway = (int) $now->diffInMinutes($ride->scheduled_at);

            $fcm->sendToUser(
                $ride->passenger,
                '🚗 Ride Reminder',
                "Your scheduled ride is in ~{$minutesAway} minutes. Get ready!",
                ['type' => 'ride_reminder', 'ride_id' => (string) $ride->id]
            );

            $ride->update(['reminder_sent_at' => now()]);
        }

        $this->info("Sent reminders for {$rides->count()} ride(s).");
    }
}
