// Load test for auto-ride's driver/passenger hot paths at 10,000+ concurrent
// users. Exercises exactly the paths flagged in the scaling review:
//   - POST /v1/driver/location  (every driver, every few seconds)
//   - POST /v1/rides            (ride creation -> Kafka -> dispatch)
//   - GET  /v1/rides/available + POST /v1/rides/{id}/accept (concurrent-accept race)
//
// Setup (once):
//   php artisan loadtest:seed --drivers=10000 --passengers=2000
//   # writes storage/app/loadtest/tokens.json, which this script reads
//
// Run:
//   brew install k6   # or see https://k6.io/docs/get-started/installation/
//   ulimit -n 65536    # 10k VUs each holding a connection needs headroom
//   k6 run -e BASE_URL=http://auto-ride.test deploy/loadtest/k6-10k.js
//
// Start smaller first — DRIVERS/PASSENGERS below cap how much of the seeded
// pool is actually used, independent of how many you seeded:
//   k6 run -e BASE_URL=http://auto-ride.test -e DRIVERS=500 -e PASSENGERS=100 deploy/loadtest/k6-10k.js
//
// IMPORTANT: point BASE_URL at a staging/local environment, never production —
// this intentionally creates load and writes real rows (rides, ride_locations).

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const tokens = JSON.parse(open('../../storage/app/loadtest/tokens.json'));

const BASE_URL    = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const DRIVERS      = Math.min(parseInt(__ENV.DRIVERS || tokens.drivers.length), tokens.drivers.length);
const PASSENGERS   = Math.min(parseInt(__ENV.PASSENGERS || tokens.passengers.length), tokens.passengers.length);
const RAMP_TIME    = __ENV.RAMP_TIME || '2m';
const HOLD_TIME    = __ENV.HOLD_TIME || '5m';

const ridesCreated   = new Counter('rides_created');
const ridesAccepted  = new Counter('rides_accepted');
const acceptConflicts = new Counter('accept_conflicts'); // expected under contention — see check below
const locationPingDuration = new Trend('location_ping_duration');

export const options = {
    scenarios: {
        // Every driver in the pool pings its location every 3-6s for the
        // whole test — this is the throughput-critical path from the review.
        driver_location_pings: {
            executor: 'ramping-vus',
            exec: 'driverLocationPing',
            startVUs: 0,
            stages: [
                { duration: RAMP_TIME, target: DRIVERS },
                { duration: HOLD_TIME, target: DRIVERS },
                { duration: '30s', target: 0 },
            ],
            gracefulRampDown: '30s',
        },
        // Passengers requesting rides at a steady rate throughout the hold window.
        passenger_ride_requests: {
            executor: 'ramping-arrival-rate',
            exec: 'passengerRequestRide',
            startRate: 0,
            timeUnit: '1s',
            preAllocatedVUs: Math.min(PASSENGERS, 500),
            maxVUs: Math.min(PASSENGERS * 2, 2000),
            stages: [
                { duration: RAMP_TIME, target: Math.max(1, Math.round(PASSENGERS / 60)) }, // ~1 request/passenger/min
                { duration: HOLD_TIME, target: Math.max(1, Math.round(PASSENGERS / 60)) },
                { duration: '30s', target: 0 },
            ],
        },
        // A subset of drivers self-serve poll /rides/available and race to
        // accept — exercises the atomic accept() guard under contention.
        driver_self_serve_accept: {
            executor: 'ramping-vus',
            exec: 'driverSelfServeAccept',
            startVUs: 0,
            stages: [
                { duration: RAMP_TIME, target: Math.min(200, DRIVERS) },
                { duration: HOLD_TIME, target: Math.min(200, DRIVERS) },
                { duration: '30s', target: 0 },
            ],
            gracefulRampDown: '30s',
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.02'],
        location_ping_duration: ['p(95)<500'],
    },
};

function authHeaders(token) {
    return { headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' } };
}

function jitter(value, maxDegrees) {
    return value + (Math.random() * 2 - 1) * maxDegrees;
}

// ── Scenario: driver location ping ─────────────────────────────────────────
export function driverLocationPing() {
    const driver = tokens.drivers[(__VU - 1) % DRIVERS];
    const lat = jitter(parseFloat(driver.lat), 0.002);
    const lng = jitter(parseFloat(driver.lng), 0.002);

    const res = http.post(
        `${BASE_URL}/api/v1/driver/location`,
        JSON.stringify({ latitude: lat, longitude: lng, speed: Math.random() * 40, heading: Math.random() * 360 }),
        authHeaders(driver.api_token),
    );

    locationPingDuration.add(res.timings.duration);
    check(res, { 'location ping 200': (r) => r.status === 200 });

    sleep(3 + Math.random() * 3); // 3-6s between pings, like a real GPS tick interval
}

// ── Scenario: passenger requests a ride ─────────────────────────────────────
export function passengerRequestRide() {
    const passenger = tokens.passengers[Math.floor(Math.random() * PASSENGERS)];
    const pickupLat = jitter(11.5564, 0.08);
    const pickupLng = jitter(104.9282, 0.08);
    const dropLat   = jitter(11.5564, 0.08);
    const dropLng   = jitter(104.9282, 0.08);

    const res = http.post(
        `${BASE_URL}/api/v1/rides`,
        JSON.stringify({
            pickup_address: 'Load Test Pickup',
            dropoff_address: 'Load Test Dropoff',
            pickup_lat: pickupLat,
            pickup_lng: pickupLng,
            dropoff_lat: dropLat,
            dropoff_lng: dropLng,
            service_type: 'standard',
            payment_method: 'cash',
        }),
        authHeaders(passenger.api_token),
    );

    const ok = check(res, { 'ride created (200/201/422 surge)': (r) => [200, 201, 422].includes(r.status) });
    if (ok && res.status !== 422) {
        ridesCreated.add(1);
    }

    sleep(1);
}

// ── Scenario: driver polls self-serve queue and races to accept ────────────
export function driverSelfServeAccept() {
    const driver = tokens.drivers[(__VU - 1) % DRIVERS];

    const available = http.get(`${BASE_URL}/api/v1/rides/available`, authHeaders(driver.api_token));
    check(available, { 'available list 200': (r) => r.status === 200 });

    let rideId = null;
    try {
        const body = JSON.parse(available.body);
        // GET /v1/rides/available returns {"data": {"rides": <paginator>}} —
        // the paginator's own `data` key holds the actual ride array.
        const list = body.data?.rides?.data || [];
        if (Array.isArray(list) && list.length > 0) {
            rideId = list[0].id;
        }
    } catch (e) {
        // non-JSON or empty — nothing to accept this iteration
    }

    if (rideId) {
        const res = http.post(`${BASE_URL}/api/v1/rides/${rideId}/accept`, null, authHeaders(driver.api_token));
        if (res.status === 200) {
            ridesAccepted.add(1);
        } else if (res.status === 422) {
            // Another driver won the race — expected and correct under
            // contention, not a failure of the system.
            acceptConflicts.add(1);
        }
    }

    sleep(2 + Math.random() * 2);
}
