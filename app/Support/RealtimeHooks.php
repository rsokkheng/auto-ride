<?php

namespace App\Support;

use App\Models\WithdrawalRequest;
use App\Services\FcmService;
use App\Services\RideDispatchService;
use App\Events\RealtimeUpdate;
use App\Http\Controllers\Api\DeliveryController;
use App\Models\ChatMessage;
use App\Models\Delivery;
use App\Models\QrPayment;
use App\Models\Ride;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\TopUpRequest;
use Illuminate\Support\Facades\Cache;

/**
 * Every "push a realtime signal to the app" trigger, in one place.
 *
 * Hooked at the model level so every write path (API controllers, admin
 * panel, queued jobs, console commands) notifies the app — the same reason
 * User::booted() keeps the Redis GEO index in sync there.
 */
class RealtimeHooks
{
    public static function register(): void
    {
        Ride::updated(function (Ride $ride) {
            if (! $ride->wasChanged(['status', 'driver_id'])) return;

            RealtimeUpdate::dispatch("ride.{$ride->id}", 'ride.updated', [
                'ride_id'   => $ride->id,
                'status'    => $ride->status,
                'driver_id' => $ride->driver_id,
            ]);

            // The ride left the "requested" state (claimed by someone else,
            // cancelled by the passenger, auto-cancelled) — tell whichever
            // driver is currently looking at the offer so it disappears now,
            // not on their next poll.
            if ($ride->getOriginal('status') === Ride::STATUS_REQUESTED
                && $ride->status !== Ride::STATUS_REQUESTED) {
                $current   = ($ride->dispatch_queue ?? [])[$ride->dispatch_position ?? -1] ?? null;
                $offeredTo = array_unique(array_filter(array_merge(
                    Cache::pull(RideDispatchService::offeredCacheKey($ride->id), []),
                    [$current],
                )));
                // 'cancelled' → the driver app tells the driver; 'taken' just hides it.
                $reason = $ride->status === Ride::STATUS_CANCELLED ? 'cancelled' : 'taken';
                foreach ($offeredTo as $driverId) {
                    if ((int) $driverId !== (int) $ride->driver_id) {
                        RealtimeUpdate::toDriver((int) $driverId, 'ride.offer_withdrawn', [
                            'ride_id' => $ride->id, 'reason' => $reason,
                        ]);
                    }
                }
            }
        });

        Delivery::updated(function (Delivery $delivery) {
            if (! $delivery->wasChanged(['status', 'driver_id', 'payment_status'])) return;

            RealtimeUpdate::dispatch("delivery.{$delivery->id}", 'delivery.updated', [
                'delivery_id' => $delivery->id,
                'status'      => $delivery->status,
                'driver_id'   => $delivery->driver_id,
            ]);

            // No longer open (taken by someone, cancelled) — make it vanish from
            // every other driver it was offered to, instead of on their next poll.
            $open = ['requested', 'pending'];
            if (in_array($delivery->getOriginal('status'), $open, true)
                && ! in_array($delivery->status, $open, true)) {
                $key = DeliveryController::offeredCacheKey($delivery->id);
                $reason = $delivery->status === 'cancelled' ? 'cancelled' : 'taken';
                foreach (Cache::pull($key, []) as $driverId) {
                    if ((int) $driverId !== (int) $delivery->driver_id) {
                        RealtimeUpdate::toDriver((int) $driverId, 'delivery.offer_withdrawn', [
                            'delivery_id' => $delivery->id, 'reason' => $reason,
                        ]);
                    }
                }
            }
        });

        TopUpRequest::updated(function (TopUpRequest $topup) {
            if (! $topup->wasChanged('status') || ! in_array($topup->status, ['approved', 'rejected'], true)) return;
            $user = $topup->user;
            if (! $user) return;

            RealtimeUpdate::toUser($user->id, 'wallet.updated', [
                'topup_id' => $topup->id, 'status' => $topup->status,
            ]);
            try {
                app(FcmService::class)->sendToUser($user,
                    ...FcmService::message($topup->status === 'approved' ? 'topup_approved' : 'topup_rejected', [
                        'amount' => number_format((int) $topup->amount),
                    ]),
                    data: ['type' => 'topup_' . $topup->status, 'topup_id' => (string) $topup->id]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        });

        // Withdrawal decided (web admin or API) — tell the driver. An approval
        // that leaves the wallet under the minimum is announced by the
        // low-balance push instead (enforceMinBalance), not twice.
        WithdrawalRequest::updated(function (WithdrawalRequest $w) {
            if (! $w->wasChanged('status') || ! in_array($w->status, ['approved', 'rejected'], true)) return;
            $driver = $w->driver?->fresh();
            if (! $driver) return;
            if ($w->status === 'approved' && ! $driver->hasMinBalance()) return;

            RealtimeUpdate::toDriver($driver->id, 'wallet.updated', [
                'withdrawal_id' => $w->id, 'status' => $w->status,
            ]);
            try {
                app(FcmService::class)->sendToDriver($driver,
                    ...FcmService::message('withdrawal_' . $w->status, [
                        'amount' => number_format((int) $w->amount_khr),
                    ]),
                    data: ['type' => 'withdrawal_' . $w->status, 'withdrawal_id' => (string) $w->id]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        });

        ChatMessage::created(function (ChatMessage $message) {
            RealtimeUpdate::dispatch("conversation.{$message->conversation_id}", 'message.created', [
                'conversation_id' => $message->conversation_id,
                'message_id'      => $message->id,
                'sender_id'       => $message->sender_id,
            ]);
        });

        QrPayment::updated(function (QrPayment $payment) {
            if (! $payment->wasChanged('status') || ! $payment->user_id) return;

            RealtimeUpdate::toUser($payment->user_id, 'payment.updated', [
                'reference' => $payment->reference,
                'status'    => $payment->status,
            ]);
        });

        TopUpRequest::updated(function (TopUpRequest $topUp) {
            if (! $topUp->wasChanged('status')) return;

            RealtimeUpdate::toUser($topUp->user_id, 'topup.updated', [
                'topup_id' => $topUp->id,
                'status'   => $topUp->status,
            ]);
        });

        SupportMessage::created(function (SupportMessage $message) {
            $ownerId = SupportTicket::whereKey($message->ticket_id)->value('user_id');
            if ($ownerId) {
                RealtimeUpdate::toUser($ownerId, 'support.updated', ['ticket_id' => $message->ticket_id]);
            }
        });

        SupportTicket::updated(function (SupportTicket $ticket) {
            if (! $ticket->wasChanged('status')) return;

            RealtimeUpdate::toUser($ticket->user_id, 'support.updated', ['ticket_id' => $ticket->id]);
        });
    }
}
