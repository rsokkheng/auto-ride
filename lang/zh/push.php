<?php

// Chinese push / in-app notification text. Keep keys in sync with lang/en.
return [
    'your_driver' => '您的司机',

    // ── Rides ────────────────────────────────────────────────────────────────
    'ride_requested'        => ['title' => '🚕 新的乘车请求',   'body' => '有乘客正在请求乘车'],
    'ride_accepted'         => ['title' => '🚕 司机已接单',     'body' => ':driver 正在赶来接您'],
    'ride_rejected'         => ['title' => '暂无可用司机',      'body' => '您的乘车请求未被接受，请重试。'],
    'driver_arrived'        => ['title' => '📍 司机已到达',     'body' => ':driver 已到达上车地点'],
    'ride_started'          => ['title' => '🚀 行程已开始',     'body' => '您的行程正在进行中，祝您一路平安！'],
    'ride_completed'        => ['title' => '🏁 行程已完成',     'body' => '您已到达目的地。车费：:fare 瑞尔。'],
    'ride_cancelled_driver' => ['title' => '❌ 行程已取消',     'body' => '司机取消了行程，请重新预订。'],
    'ride_cancelled_passenger' => ['title' => '❌ 行程已取消',  'body' => '乘客已取消预订。'],
    'ride_reminder'         => ['title' => '🚗 行程提醒',       'body' => '您预约的行程将在约 :minutes 分钟后开始，请做好准备！'],
    'tip_received'          => ['title' => '您收到了小费！',    'body' => '乘客给了您 :amount 瑞尔的小费。'],

    // ── Deliveries ───────────────────────────────────────────────────────────
    'delivery_requested'    => ['title' => '📦 新的配送请求',   'body' => ':pickup → :dropoff'],
    'delivery_accepted'     => ['title' => '已分配司机',        'body' => ':driver 已接受您的配送。'],
    'delivery_picked_up'    => ['title' => '包裹已取件',        'body' => ':driver 已取走您的包裹，正在配送途中。'],
    'delivery_started'      => ['title' => '配送已开始',        'body' => '司机已取件，正在途中。'],
    'moving_started'        => ['title' => '搬家任务已开始',    'body' => '司机已取件，正在途中。'],
    'delivery_delivered'    => ['title' => '包裹已送达',        'body' => '您的包裹已成功送达。'],
    'delivery_completed'    => ['title' => '配送已完成',        'body' => '您的包裹已成功送达。'],
    'delivery_cancelled'    => [
        'title'          => '配送已取消',
        'body_by_driver' => '司机取消了配送，请重新预订。',
        'body'           => '配送已被取消。',
    ],

    // ── Shop: purchases & rentals of listed products ────────────────────────
    'shop' => [
        'your_item'    => '您的商品',
        'order_number' => '订单 #:id',
        'your_vehicle' => '您的车辆',

        'order_placed'   => [
            'title'      => '订单已提交 🛍️',
            'body'       => '您购买 :title 的订单已发送给卖家，确认后我们会通知您。',
            'body_batch' => '您的订单已发送给卖家，确认后我们会通知您。',
        ],
        'rent_requested' => [
            'title' => '租赁请求已发送',
            'body'  => '您租赁 :title（:start → :end）的请求已发送，确认后我们会通知您。',
        ],
        'seller_new_order' => [
            'title'         => '🛒 新订单',
            'body_delivery' => ':buyer 订购了 :qty× :title — :price · 配送至 :address',
            'body_pickup'   => ':buyer 订购了 :qty× :title — :price · 自取',
        ],
        'seller_new_rent' => [
            'title' => '🔑 新的租赁请求',
            'body'  => ':buyer 想要租赁 :title，从 :start 到 :end — :price',
        ],

        'order' => [
            'confirmed' => ['title' => '订单已确认 ✅', 'body' => '卖家已确认您购买 :title 的订单。'],
            'completed' => [
                'title'       => '订单已完成',
                'body'        => '您购买 :title 的订单已完成，感谢您的惠顾！',
                'seller_body' => ':buyer 购买 :title 的订单已完成 — :price',
            ],
            'cancelled' => [
                'title'          => '订单已取消',
                'body_by_seller' => '卖家取消了您购买 :title 的订单。',
                'body'           => '您购买 :title 的订单已取消。',
                'seller_body'    => ':buyer 取消了购买 :title 的订单。',
            ],
        ],
        'rent_order' => [
            'confirmed' => ['title' => '租赁已确认 ✅', 'body' => '卖家已确认您租赁 :title 的请求。'],
            'completed' => [
                'title'       => '租赁已完成',
                'body'        => '您租赁 :title 已完成，感谢您的惠顾！',
                'seller_body' => ':buyer 租赁 :title 已完成 — :price',
            ],
            'cancelled' => [
                'title'          => '租赁已取消',
                'body_by_seller' => '卖家取消了您租赁 :title 的请求。',
                'body'           => '您租赁 :title 的请求已取消。',
                'seller_body'    => ':buyer 取消了租赁 :title 的请求。',
            ],
        ],

        'rental_booked' => [
            'title' => '租赁已预订 🔑',
            'body'  => '您租赁 :title（:dates）正在等待确认，确认后我们会通知您。',
        ],
        'rental_new' => [
            'title'    => '🔑 新的租赁请求',
            'body'     => ':renter 想要租赁 :title（:dates，:days 天）。',
            'body_one' => ':renter 想要租赁 :title（:dates，1 天）。',
        ],
        'rental' => [
            'confirmed' => ['title' => '租赁已确认 ✅', 'body' => '您从 :start 开始租赁 :title 已确认。'],
            'active'    => ['title' => '租赁已开始',    'body' => '您租赁的 :title 已开始，请安全驾驶！'],
            'completed' => ['title' => '租赁已完成',    'body' => '您租赁的 :title 已结束，感谢您的租用！'],
            'cancelled' => [
                'title'       => '租赁已取消',
                'body'        => '您租赁 :title 的预订已取消。',
                'seller_body' => ':renter 取消了 :title 的租赁。',
            ],
        ],
    ],
];
