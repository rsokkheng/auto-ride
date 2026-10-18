<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_from_unguarded_admin_controllers(): void
    {
        foreach (['admin.settlements.index', 'admin.settlements.export', 'admin.export.orders', 'admin.notifications'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
        $this->post(route('admin.settlements.bulk-approve'))->assertRedirect(route('admin.login'));
    }

    public function test_non_admins_are_rejected(): void
    {
        config(['app.admin_test_mode' => false]);
        $this->actingAs(User::factory()->create(['role' => 'passenger']));

        $this->get(route('admin.settlements.index'))->assertRedirect(route('admin.login'));
    }

    public function test_login_page_stays_public_and_admins_get_in(): void
    {
        $this->get(route('admin.login'))->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.settlements.index'))->assertOk();
    }
}
