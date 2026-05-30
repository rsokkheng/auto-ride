<?php

namespace Database\Seeders;

use App\Models\ChargingStation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PrototypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin Demo',
            'role' => 'admin',
            'phone' => '555-0000',
            'password' => Hash::make('password'),
            'api_token' => bin2hex(random_bytes(40)),
        ]);

        $passenger = User::factory()->create([
            'name' => 'Passenger Demo',
            'email' => 'passenger@example.com',
            'role' => 'passenger',
            'phone' => '555-0100',
            'api_token' => bin2hex(random_bytes(40)),
        ]);

        $driver = User::factory()->create([
            'name' => 'Driver Demo',
            'email' => 'driver@example.com',
            'role' => 'driver',
            'phone' => '555-0101',
            'api_token' => bin2hex(random_bytes(40)),
        ]);

        Vehicle::create([
            'user_id' => $driver->id,
            'license_plate' => 'RIDE-100',
            'make' => 'Tesla',
            'model' => 'Model Y',
            'year' => 2025,
            'type' => 'electric',
            'status' => 'active',
            'capacity' => 4,
            'details' => 'Comfort EV for passenger and ride-hailing services.',
        ]);

        Vehicle::create([
            'user_id' => $driver->id,
            'license_plate' => 'DEL-200',
            'make' => 'Ford',
            'model' => 'Transit',
            'year' => 2023,
            'type' => 'van',
            'status' => 'active',
            'capacity' => 2,
            'details' => 'Delivery van for local package and cargo services.',
        ]);

        ChargingStation::insert([
            [
                'name' => 'City Center Charge Hub',
                'address' => '100 Main Street',
                'latitude' => 37.7749,
                'longitude' => -122.4194,
                'available_ports' => 12,
                'operator' => 'SuperCharge',
                'rating' => 4.8,
                'details' => 'Fast charging station near downtown with covered parking.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Airport Express Charging',
                'address' => '400 Airport Drive',
                'latitude' => 37.6213,
                'longitude' => -122.3790,
                'available_ports' => 8,
                'operator' => 'PowerGo',
                'rating' => 4.5,
                'details' => '24/7 station for pickup and delivery fleets.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        \App\Models\MarketplaceItem::create([
            'seller_id' => $driver->id,
            'vehicle_id' => 2,
            'title' => 'Ford Transit Delivery Van',
            'description' => 'Available for rent or purchase with flexible leasing.',
            'type' => 'rent',
            'price' => 45000,
            'rent_rate' => 150.00,
            'available' => true,
            'condition' => 'excellent',
        ]);

        \App\Models\PushNotification::create([
            'user_id' => $passenger->id,
            'title' => 'Welcome to AutoRide',
            'body' => 'Your passenger demo account is ready to use for ride booking and delivery.',
            'type' => 'welcome',
            'payload' => ['welcome' => true],
            'status' => 'sent',
        ]);
    }
}
