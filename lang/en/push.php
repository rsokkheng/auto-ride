<?php

// Push / in-app notification text. Keep keys in sync with lang/km and lang/zh.
return [
    'your_driver' => 'Your driver',

    // ── Rides ────────────────────────────────────────────────────────────────
    'ride_requested'        => ['title' => '🚕 New ride request',        'body' => 'A passenger is requesting a ride'],
    'ride_accepted'         => ['title' => '🚕 Driver accepted your ride', 'body' => ':driver is on the way to you'],
    'ride_rejected'         => ['title' => 'No driver available',        'body' => 'Your ride request could not be accepted. Please try again.'],
    'driver_arrived'        => ['title' => '📍 Your driver has arrived',  'body' => ':driver has arrived at your pickup point'],
    'ride_started'          => ['title' => '🚀 Your ride has started',    'body' => 'Your trip is underway. Have a safe ride!'],
    'ride_completed'        => ['title' => '🏁 Ride completed',           'body' => 'You have arrived. Fare: :fare KHR.'],
    'ride_cancelled_driver' => ['title' => '❌ Ride cancelled',           'body' => 'Your driver cancelled the ride. Please book again.'],
    'ride_cancelled_passenger' => ['title' => '❌ Ride cancelled',        'body' => 'The passenger cancelled the booking.'],
    'ride_reminder'         => ['title' => '🚗 Ride reminder',            'body' => 'Your scheduled ride is in ~:minutes minutes. Get ready!'],
    'tip_received'          => ['title' => 'You received a tip!',        'body' => 'Your passenger added a :amount KHR tip.'],

    // ── Deliveries ───────────────────────────────────────────────────────────
    'delivery_requested'    => ['title' => '📦 New delivery request',     'body' => ':pickup → :dropoff'],
    'delivery_accepted'     => ['title' => 'Driver assigned',            'body' => ':driver has accepted your delivery.'],
    'delivery_picked_up'    => ['title' => 'Package picked up',          'body' => ':driver has picked up your package and is on the way.'],
    'delivery_started'      => ['title' => 'Delivery started',           'body' => 'Your driver has picked up and is on the way.'],
    'moving_started'        => ['title' => 'Moving job started',         'body' => 'Your driver has picked up and is on the way.'],
    'delivery_delivered'    => ['title' => 'Package delivered',          'body' => 'Your package has been delivered successfully.'],
    'delivery_completed'    => ['title' => 'Delivery completed',         'body' => 'Your package has been delivered successfully.'],
    'delivery_cancelled'    => [
        'title'          => 'Delivery cancelled',
        'body_by_driver' => 'Your driver cancelled the delivery. Please book again.',
        'body'           => 'The delivery has been cancelled.',
    ],

    // ── Shop: purchases & rentals of listed products ────────────────────────
    'shop' => [
        'your_item'    => 'your item',
        'order_number' => 'order #:id',
        'your_vehicle' => 'your vehicle',

        'order_placed'   => [
            'title'      => 'Order placed 🛍️',
            'body'       => "Your order for :title was sent to the seller. We'll let you know when it's confirmed.",
            'body_batch' => "Your order was sent to the sellers. We'll let you know when it's confirmed.",
        ],
        'rent_requested' => [
            'title' => 'Rental request sent',
            'body'  => "Your request to rent :title (:start → :end) was sent. We'll let you know when it's confirmed.",
        ],
        'seller_new_order' => [
            'title'         => '🛒 New order',
            'body_delivery' => ':buyer ordered :qty× :title — :price · Deliver to :address',
            'body_pickup'   => ':buyer ordered :qty× :title — :price · Pick up',
        ],
        'seller_new_rent' => [
            'title' => '🔑 New rental request',
            'body'  => ':buyer wants to rent :title from :start to :end — :price',
        ],

        // Status changes; "rent_order" is the rental flavour of a shop order.
        'order' => [
            'confirmed' => ['title' => 'Order confirmed ✅', 'body' => 'The seller confirmed your order for :title.'],
            'completed' => [
                'title'       => 'Order completed',
                'body'        => 'Your order for :title is complete. Thanks for shopping with us!',
                'seller_body' => ":buyer's order for :title is complete — :price",
            ],
            'cancelled' => [
                'title'          => 'Order cancelled',
                'body_by_seller' => 'The seller cancelled your order for :title.',
                'body'           => 'Your order for :title was cancelled.',
                'seller_body'    => ':buyer cancelled their order for :title.',
            ],
        ],
        'rent_order' => [
            'confirmed' => ['title' => 'Rental confirmed ✅', 'body' => 'The seller confirmed your rental for :title.'],
            'completed' => [
                'title'       => 'Rental completed',
                'body'        => 'Your rental for :title is complete. Thanks for shopping with us!',
                'seller_body' => ":buyer's rental for :title is complete — :price",
            ],
            'cancelled' => [
                'title'          => 'Rental cancelled',
                'body_by_seller' => 'The seller cancelled your rental for :title.',
                'body'           => 'Your rental for :title was cancelled.',
                'seller_body'    => ':buyer cancelled their rental for :title.',
            ],
        ],

        // Car rentals (CarRental bookings)
        'rental_booked' => [
            'title' => 'Rental booked 🔑',
            'body'  => "Your rental of :title (:dates) is pending confirmation. We'll notify you when it's confirmed.",
        ],
        'rental_new' => [
            'title'    => '🔑 New rental request',
            'body'     => ':renter wants to rent :title (:dates, :days days).',
            'body_one' => ':renter wants to rent :title (:dates, 1 day).',
        ],
        'rental' => [
            'confirmed' => ['title' => 'Rental confirmed ✅', 'body' => 'Your rental of :title from :start is confirmed.'],
            'active'    => ['title' => 'Rental started',     'body' => 'Your rental of :title has started. Drive safe!'],
            'completed' => ['title' => 'Rental completed',   'body' => 'Your rental of :title is complete. Thanks for renting with us!'],
            'cancelled' => [
                'title'       => 'Rental cancelled',
                'body'        => 'Your rental of :title was cancelled.',
                'seller_body' => ':renter cancelled their rental of :title.',
            ],
        ],
    ],
];
