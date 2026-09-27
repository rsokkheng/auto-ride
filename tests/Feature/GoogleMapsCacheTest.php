<?php

namespace Tests\Feature;

use App\Services\FareService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleMapsCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_maps.key' => 'test-key']);
    }

    private function fakeDirections(): void
    {
        Http::fake(['maps.googleapis.com/maps/api/directions/*' => Http::response([
            'status' => 'OK',
            'routes' => [['legs' => [[
                'distance' => ['value' => 11580, 'text' => '11.6 km'],
                'duration' => ['value' => 1440, 'text' => '24 mins'],
            ]]]],
        ])]);
    }

    public function test_same_and_nearby_routes_call_google_once(): void
    {
        $this->fakeDirections();
        $fare = app(FareService::class);

        $a = $fare->getRoute(11.5564, 104.9282, 11.5462, 104.8440);
        $b = $fare->getRoute(11.5564, 104.9282, 11.5462, 104.8440);   // same trip
        $c = $fare->getRoute(11.55642, 104.92818, 11.54615, 104.84404); // pin nudged a few metres

        Http::assertSentCount(1);
        $this->assertSame('google_maps', $c['source']);
        $this->assertSame($a, $b);
        $this->assertSame(11.58, $c['distance_km']);
    }

    public function test_different_destination_is_a_new_lookup(): void
    {
        $this->fakeDirections();
        $fare = app(FareService::class);

        $fare->getRoute(11.5564, 104.9282, 11.5462, 104.8440);
        $fare->getRoute(11.5564, 104.9282, 11.5700, 104.9000);

        Http::assertSentCount(2);
    }

    public function test_google_failure_is_not_cached(): void
    {
        Http::fakeSequence('maps.googleapis.com/*')
            ->push(['status' => 'OVER_QUERY_LIMIT'])
            ->push(['status' => 'OK', 'routes' => [['legs' => [[
                'distance' => ['value' => 11580, 'text' => '11.6 km'],
                'duration' => ['value' => 1440, 'text' => '24 mins'],
            ]]]]]);
        $fare = app(FareService::class);

        $this->assertSame('haversine', $fare->getRoute(11.5564, 104.9282, 11.5462, 104.8440)['source']);
        $this->assertSame('google_maps', $fare->getRoute(11.5564, 104.9282, 11.5462, 104.8440)['source']);
    }

    public function test_plus_code_is_skipped_for_a_real_place_name(): void
    {
        Http::fake(['maps.googleapis.com/maps/api/geocode/*' => Http::response(['status' => 'OK', 'results' => [
            ['formatted_address' => 'GRWV+FH Phnom Penh, Cambodia', 'types' => ['plus_code']],
            ['formatted_address' => 'Phnom Penh International Airport, Phnom Penh', 'types' => ['airport', 'point_of_interest']],
        ]])]);

        $this->assertSame('Phnom Penh International Airport, Phnom Penh', app(FareService::class)->reverseGeocode(11.5462, 104.8440));
    }

    public function test_only_a_plus_code_is_still_better_than_coordinates(): void
    {
        Http::fake(['maps.googleapis.com/maps/api/geocode/*' => Http::response(['status' => 'OK', 'results' => [
            ['formatted_address' => 'GRWV+FH Phnom Penh, Cambodia', 'types' => ['plus_code']],
        ]])]);

        $this->assertSame('GRWV+FH Phnom Penh, Cambodia', app(FareService::class)->reverseGeocode(11.5462, 104.8440));
    }

    public function test_place_names_are_cached_and_fallback_is_not(): void
    {
        Http::fakeSequence('maps.googleapis.com/maps/api/geocode/*')
            ->push(['status' => 'ZERO_RESULTS'])
            ->push(['status' => 'OK', 'results' => [['formatted_address' => 'Phnom Penh International Airport']]]);
        $fare = app(FareService::class);

        $this->assertSame('11.5462, 104.844', $fare->reverseGeocode(11.5462, 104.8440));   // fallback, not cached
        $this->assertSame('Phnom Penh International Airport', $fare->reverseGeocode(11.5462, 104.8440));
        $this->assertSame('Phnom Penh International Airport', $fare->reverseGeocode(11.54621, 104.84401)); // cached

        Http::assertSentCount(2);
    }
}
