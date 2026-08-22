<?php

namespace Tests\Feature;

use App\Jobs\SendPromoEventPush;
use App\Models\PromoEvent;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendPromoEventPushTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $overrides = []): PromoEvent
    {
        return PromoEvent::create(array_merge([
            'title'       => 'Friday Party Discount',
            'body'        => '20% off all rides this Friday!',
            'target_role' => 'all',
            'active'      => true,
        ], $overrides));
    }

    public function test_pushes_to_all_passengers_and_drivers_when_target_role_is_all(): void
    {
        $driver    = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $event     = $this->makeEvent(['target_role' => 'all']);

        $this->mock(FcmService::class, function ($mock) use ($driver, $passenger, $event) {
            $mock->shouldReceive('promoEvent')->once()
                ->with(\Mockery::on(fn (User $u) => $u->id === $driver->id), $event->id, $event->title, $event->body, null);
            $mock->shouldReceive('promoEvent')->once()
                ->with(\Mockery::on(fn (User $u) => $u->id === $passenger->id), $event->id, $event->title, $event->body, null);
        });

        (new SendPromoEventPush($event->id))->handle(app(FcmService::class));

        $event->refresh();
        $this->assertNotNull($event->sent_at);
    }

    public function test_only_pushes_to_matching_role_when_target_role_is_specific(): void
    {
        $driver    = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $event     = $this->makeEvent(['target_role' => 'driver']);

        $this->mock(FcmService::class, function ($mock) use ($driver, $event) {
            $mock->shouldReceive('promoEvent')->once()
                ->with(\Mockery::on(fn (User $u) => $u->id === $driver->id), $event->id, $event->title, $event->body, null);
        });

        (new SendPromoEventPush($event->id))->handle(app(FcmService::class));

        $event->refresh();
        $this->assertNotNull($event->sent_at);
    }

    public function test_does_not_push_or_mark_sent_when_event_is_inactive(): void
    {
        User::factory()->create(['role' => 'driver']);
        $event = $this->makeEvent(['active' => false]);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldNotReceive('promoEvent');
        });

        (new SendPromoEventPush($event->id))->handle(app(FcmService::class));

        $event->refresh();
        $this->assertNull($event->sent_at);
    }

    public function test_does_nothing_if_event_was_deleted_before_job_ran(): void
    {
        $event   = $this->makeEvent();
        $eventId = $event->id;
        $event->delete();

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldNotReceive('promoEvent');
        });

        // Should not throw despite the event no longer existing.
        (new SendPromoEventPush($eventId))->handle(app(FcmService::class));

        $this->assertTrue(true);
    }
}
