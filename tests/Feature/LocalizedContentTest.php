<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\PushNotification;
use App\Models\SurgeZone;
use App\Models\User;
use App\Services\FcmService;
use App\Support\LocalizedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalizedContentTest extends TestCase
{
    use RefreshDatabase;

    private function apiUser(array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'role'             => 'passenger',
            'api_token'        => Str::random(60),
            'token_expires_at' => now()->addDay(),
        ]);
    }

    public function test_admin_saves_three_languages_and_api_returns_the_requested_one(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post(route('admin.surge-zones.store'), [
            'name' => 'Airport Rush', 'name_kh' => 'ពេលមមាញឹកព្រលានយន្តហោះ', 'name_zh' => '机场高峰',
            'center_lat' => 11.55, 'center_lng' => 104.92, 'radius_km' => 2, 'multiplier' => 1.5, 'type' => 'rides',
        ])->assertRedirect();

        $zone = SurgeZone::firstOrFail();
        $this->assertSame('Airport Rush', $zone->getAttributes()['name_en']);
        $this->assertSame('机场高峰', $zone->getAttributes()['name_zh']);

        // Renaming in English keeps name_en in step (it used to go stale).
        $this->put(route('admin.surge-zones.update', $zone), [
            'name' => 'Airport Peak', 'name_kh' => 'ពេលមមាញឹកព្រលានយន្តហោះ',
            'center_lat' => 11.55, 'center_lng' => 104.92, 'radius_km' => 2, 'multiplier' => 1.5, 'type' => 'rides',
        ])->assertRedirect();
        $this->assertSame('Airport Peak', $zone->fresh()->getAttributes()['name_en']);

        Banner::create([
            'title' => 'Big sale', 'title_kh' => 'បញ្ចុះតម្លៃធំ', 'title_zh' => '大促销',
            'image' => 'b.png', 'target_role' => 'all', 'active' => true,
        ]);
        $user = $this->apiUser();

        foreach (['en' => 'Big sale', 'kh' => 'បញ្ចុះតម្លៃធំ', 'zh' => '大促销'] as $lang => $expected) {
            $this->withToken($user->api_token)->getJson("/api/v1/banners?lang={$lang}")
                ->assertOk()->assertJsonPath('data.banners.0.title', $expected);
        }

        // Empty translation falls back to English.
        Banner::query()->update(['title_zh' => null]);
        $this->withToken($user->api_token)->getJson('/api/v1/banners', ['X-Locale' => 'zh'])
            ->assertJsonPath('data.banners.0.title', 'Big sale');
    }

    public function test_admin_pages_with_language_fields_render(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['admin.banners', 'admin.promo-events', 'admin.promo-coupons', 'admin.surge-zones',
                  'admin.charging-stations', 'admin.marketplace', 'admin.ride-pricing',
                  'admin.airport-zones', 'admin.subscription-plans'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('ខ្មែរ', false);
        }
    }

    public function test_push_is_sent_in_the_users_language_and_inbox_follows_the_app_language(): void
    {
        $user = $this->apiUser(['accessibility_settings' => ['language' => 'kh']]);

        app(FcmService::class)->notifyUser($user, ...FcmService::message('ride_started'), data: ['type' => 'ride_started']);

        $row = PushNotification::firstOrFail();
        $this->assertSame('🚀 ដំណើរបានចាប់ផ្តើម', $row->getAttributes()['title']);   // stored in the user's language
        $this->assertSame('🚀 Your ride has started', $row->getAttributes()['title_en']);
        $this->assertSame('🚀 行程已开始', $row->getAttributes()['title_zh']);

        $this->withToken($user->api_token)->getJson('/api/v1/notifications?lang=zh')
            ->assertOk()->assertJsonPath('data.notifications.0.title', '🚀 行程已开始');
    }

    public function test_the_apps_language_is_remembered_for_later_pushes(): void
    {
        $user = $this->apiUser();
        $this->assertNull($user->preferredLocale());

        $this->withToken($user->api_token)->getJson('/api/v1/banners', ['X-Locale' => 'zh'])->assertOk();

        $this->assertSame('zh', $user->fresh()->preferredLocale());
    }

    public function test_product_titles_are_translated_inside_messages(): void
    {
        $product = new \App\Models\MarketplaceProduct([
            'title' => 'Helmet', 'title_kh' => 'មួកសុវត្ថិភាព', 'title_zh' => '头盔',
        ]);

        [, $body] = FcmService::message('shop.order_placed', ['title' => LocalizedText::fromModel($product, 'title')]);

        $this->assertStringContainsString('Helmet', $body->in('en'));
        $this->assertStringContainsString('មួកសុវត្ថិភាព', $body->in('kh'));
        $this->assertStringContainsString('头盔', $body->in('zh'));
    }
}
