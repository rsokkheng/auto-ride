<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_every_period_and_falls_back_to_today(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('period', 'today')
            ->assertSee('Revenue (Last 7 Days)')
            ->assertSee('Ride Status (Today)');

        $this->get(route('admin.dashboard', ['period' => '7d']))->assertOk()
            ->assertSee('Ride Status (Last 7 Days)');

        $this->get(route('admin.dashboard', ['period' => '30d']))->assertOk()
            ->assertViewHas('revenueChart', fn ($c) => count($c) === 30)
            ->assertSee('Revenue (Last 30 Days)');

        $this->get(route('admin.dashboard', ['period' => 'bogus']))->assertOk()
            ->assertViewHas('period', 'today');
    }

    public function test_metrics_come_from_real_rides(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $ride = fn (array $a) => Ride::forceCreate($a + [
            'passenger_id' => $passenger->id, 'pickup_address' => 'A', 'pickup_lat' => 11.55, 'service_type' => 'motorcycle',
            'pickup_lng' => 104.92, 'fare' => 0,
        ]);
        $ride(['status' => 'completed', 'fare' => 12000, 'completed_at' => now()]);
        $ride(['status' => 'completed', 'fare' => 8000,  'completed_at' => now()]);
        $ride(['status' => 'completed', 'fare' => 5000,  'completed_at' => now()->subDays(3)]);
        $ride(['status' => 'cancelled']);
        $ride(['status' => 'in_progress']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard', ['period' => 'today']))
            ->assertOk()
            ->assertViewHas('metrics', fn ($m) => $m['revenue_today'] === 20000 && $m['revenue_week'] === 25000)
            ->assertViewHas('revenueByService', fn ($s) => $s['ride'] === 20000)
            // The ride completed 3 days ago was also created today, so it counts in today's status totals.
            ->assertViewHas('rideStatus', fn ($s) => $s === ['completed' => 3, 'in_progress' => 1, 'pending' => 0, 'cancelled' => 1]);
    }
}
