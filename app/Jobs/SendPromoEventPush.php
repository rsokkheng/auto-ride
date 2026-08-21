<?php

namespace App\Jobs;

use App\Models\PromoEvent;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a promo event to every matching user. Queued (rather than sent
 * synchronously from the admin request) since "all users" can be a large,
 * slow-to-iterate audience.
 */
class SendPromoEventPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $eventId) {}

    public function handle(FcmService $fcm): void
    {
        $event = PromoEvent::find($this->eventId);
        if (! $event || ! $event->active) {
            return;
        }

        $query = User::query();
        if ($event->target_role !== 'all') {
            $query->where('role', $event->target_role);
        } else {
            $query->whereIn('role', ['passenger', 'driver']);
        }

        $query->chunkById(200, function ($users) use ($event, $fcm) {
            foreach ($users as $user) {
                $fcm->promoEvent($user, $event->id, $event->title, $event->body, $event->image_url);
            }
        });

        $event->update(['sent_at' => now()]);
    }
}
