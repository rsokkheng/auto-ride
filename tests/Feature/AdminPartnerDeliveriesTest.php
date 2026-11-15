<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPartnerDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    private function delivery(string $recipient, ?int $partnerId = null): Delivery
    {
        $sender = User::factory()->create(['role' => 'passenger']);
        return Delivery::create([
            'partner_id' => $partnerId, 'sender_id' => $partnerId ?? $sender->id, 'service_type' => 'delivery',
            'sender_name' => 'Sender', 'recipient_name' => $recipient, 'recipient_phone' => '0961234567',
            'pickup_address' => 'TK', 'dropoff_address' => 'BKK1', 'fee' => 5000,
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'created',
            'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);
    }

    public function test_partner_orders_have_their_own_list_and_are_left_out_of_deliveries(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $partner = User::factory()->create(['role' => 'passenger', 'name' => 'Dara']);
        PartnerProfile::create(['user_id' => $partner->id, 'business_name' => 'Dara Shop', 'phone' => '012345678', 'status' => 'approved']);

        $this->delivery('Customer Order');
        $this->delivery('Partner Order', $partner->id);

        $this->get(route('admin.deliveries'))->assertOk()
            ->assertSee('Customer Order')->assertDontSee('Partner Order')
            ->assertViewHas('counts', fn ($c) => $c['all'] === 1);

        $this->get(route('admin.partner-deliveries'))->assertOk()
            ->assertSee('Partner Deliveries')
            ->assertSee('Partner Order')->assertDontSee('Customer Order')
            ->assertSee('Dara Shop')
            ->assertViewHas('counts', fn ($c) => $c['all'] === 1)
            ->assertViewHas('partners', fn ($p) => $p->all() === [$partner->id => 'Dara Shop']);
    }

    public function test_editing_a_partner_order_keeps_it_a_delivery_with_its_workflow_status_and_driver(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $partner = User::factory()->create(['role' => 'passenger']);
        $staff   = User::factory()->create(['role' => 'driver']);
        $order   = $this->delivery('Old Name', $partner->id);
        $order->update(['status' => 'assigned', 'driver_id' => $staff->id]);

        $this->get(route('admin.partner-deliveries'))->assertOk()
            ->assertSee('Partner Account')
            ->assertSee('Change it from Partner Packages.');

        $this->put(route('admin.deliveries.update', $order), [
            'service_type' => 'moving', 'status' => 'completed', 'driver_id' => '',
            'sender_id' => $partner->id, 'sender_name' => 'Shop', 'recipient_name' => 'New Name',
            'recipient_phone' => '0961234567', 'pickup_address' => 'TK', 'dropoff_address' => 'BKK1', 'fee' => 5000,
        ])->assertRedirect(route('admin.partner-deliveries'));

        $order->refresh();
        $this->assertSame('New Name', $order->recipient_name);
        $this->assertSame('delivery', $order->service_type);
        $this->assertSame('assigned', $order->status);
        $this->assertSame($staff->id, $order->driver_id);
    }
}
