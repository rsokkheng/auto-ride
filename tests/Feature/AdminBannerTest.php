<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function banner(string $role, array $attrs = []): Banner
    {
        return Banner::create($attrs + [
            'title' => ucfirst($role) . ' banner', 'image' => "banners/{$role}.jpg", 'target_role' => $role, 'active' => true,
        ]);
    }

    public function test_admin_uploads_a_banner_for_each_app_and_each_app_only_gets_its_own(): void
    {
        foreach (['passenger' => 'Ride promo', 'driver' => 'Driver bonus', 'all' => 'Holiday'] as $role => $title) {
            $this->post(route('admin.banners.store'), [
                'title' => $title, 'target_role' => $role, 'active' => 1,
                'image' => UploadedFile::fake()->image("{$role}.jpg", 1200, 400),
            ])->assertRedirect(route('admin.banners'));
        }
        $this->assertSame(3, Banner::count());
        Storage::disk('public')->assertExists(Banner::where('title', 'Ride promo')->value('image'));

        $titles = function (string $role) {
            $user = User::factory()->create(['role' => $role, 'api_token' => Str::random(60), 'token_expires_at' => now()->addDay()]);
            return collect($this->withToken($user->api_token)->getJson('/api/v1/banners')->assertOk()->json('data.banners'))
                ->pluck('title')->sort()->values()->all();
        };
        $this->assertSame(['Holiday', 'Ride promo'], $titles('passenger'));
        $this->assertSame(['Driver bonus', 'Holiday'], $titles('driver'));
    }

    public function test_app_tabs_include_banners_shown_in_both_apps(): void
    {
        $this->banner('passenger');
        $this->banner('driver');
        $this->banner('all');

        $this->get(route('admin.banners'))->assertOk()
            ->assertViewHas('banners', fn ($p) => $p->total() === 3)
            ->assertViewHas('stats', fn ($s) => $s['live_passenger'] === 2 && $s['live_driver'] === 2);

        $this->get(route('admin.banners', ['app' => 'driver']))->assertOk()
            ->assertViewHas('banners', fn ($p) => $p->pluck('target_role')->sort()->values()->all() === ['all', 'driver'])
            ->assertDontSee('>Clear</a>', false);
    }

    public function test_replacing_or_deleting_a_banner_removes_the_old_image(): void
    {
        Storage::disk('public')->put('banners/old.jpg', 'x');
        $banner = $this->banner('all', ['image' => 'banners/old.jpg']);

        $this->put(route('admin.banners.update', $banner), [
            'title' => 'New', 'target_role' => 'driver', 'active' => 1,
            'image' => UploadedFile::fake()->image('new.png', 1200, 400),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing('banners/old.jpg');
        $new = $banner->fresh()->image;
        Storage::disk('public')->assertExists($new);
        $this->assertSame('driver', $banner->fresh()->target_role);

        $this->delete(route('admin.banners.destroy', $banner))->assertRedirect();
        Storage::disk('public')->assertMissing($new);
        $this->assertSame(0, Banner::count());
    }

    public function test_sort_order_is_capped_to_the_column_size(): void
    {
        $this->post(route('admin.banners.store'), [
            'title' => 'X', 'target_role' => 'all', 'sort_order' => 300,
            'image' => UploadedFile::fake()->image('x.jpg'),
        ])->assertSessionHasErrors('sort_order');
    }
}
