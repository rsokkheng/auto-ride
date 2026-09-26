<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | ROTEH Admin</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=swap">

    <style>
        .brand-link { background: linear-gradient(135deg, #1c1c2e, #2d1b3d) !important; }
        .brand-link:hover { background: linear-gradient(135deg, #252540, #3d2555) !important; }
        .brand-text { color: #fff !important; font-weight: 700 !important; }
        .brand-accent { color: #e63946; }
        .main-sidebar { background: #1c1c2e !important; }
        .sidebar { background: transparent; }
        .nav-sidebar .nav-link { color: #94a3b8 !important; border-radius: 8px; margin: 2px 8px; }
        .nav-sidebar .nav-link:hover { background: rgba(255,255,255,0.07) !important; color: #fff !important; }
        .nav-sidebar .nav-link.active { background: linear-gradient(135deg, #e63946, #c1121f) !important; color: #fff !important; }
        .nav-sidebar .nav-icon { color: inherit !important; }
        .user-panel .info a { color: #e2e8f0 !important; }
        .main-header.navbar { border-bottom: 1px solid #f1f5f9; box-shadow: 0 1px 8px rgba(0,0,0,0.06); }
        .content-wrapper { background: #f8fafc; }
        .content-header h1 { font-size: 1.4rem; font-weight: 700; color: #1e293b; }
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 8px rgba(0,0,0,0.06); }
        .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; border-radius: 12px 12px 0 0 !important; }
        .card-title { font-weight: 600; color: #1e293b; }
        .small-box { border-radius: 12px; }
        .small-box:hover { transform: translateY(-2px); transition: transform .2s; }
        .main-footer { background: #fff; border-top: 1px solid #f1f5f9; color: #64748b; font-size: .85rem; }
        .table thead th { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; border-top: none; }
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active { background: linear-gradient(135deg,#e63946,#c1121f); }
    </style>

    @stack('styles')
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    {{-- ── Top Navbar ── --}}
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link text-muted" style="font-size:.8rem;">
                    <i class="fas fa-circle" style="color:#4ade80;font-size:.5rem;vertical-align:middle;"></i>
                    &nbsp;ROTEH Admin
                </span>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#e63946,#c1121f);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-user-shield text-white" style="font-size:.75rem;"></i>
                        </div>
                        <span class="d-none d-md-inline" style="font-size:.85rem;font-weight:600;color:#1e293b;">
                            {{ Auth::user()->name ?? 'Admin' }}
                        </span>
                        <i class="fas fa-chevron-down" style="font-size:.65rem;color:#94a3b8;"></i>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right" style="border:none;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.12);min-width:200px;">
                    <div class="px-4 py-3 border-bottom">
                        <div style="font-weight:600;font-size:.85rem;color:#1e293b;">{{ Auth::user()->name ?? 'Admin' }}</div>
                        <div style="font-size:.75rem;color:#94a3b8;">{{ Auth::user()->email ?? '' }}</div>
                    </div>
                    <a href="{{ route('admin.dashboard') }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                        <i class="fas fa-gauge-high" style="width:16px;color:#64748b;"></i>
                        <span style="font-size:.85rem;">Dashboard</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger border-0 bg-transparent w-100 text-left">
                            <i class="fas fa-arrow-right-from-bracket" style="width:16px;"></i>
                            <span style="font-size:.85rem;">Sign Out</span>
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>

    {{-- ── Sidebar ── --}}
    <aside class="main-sidebar sidebar-dark-primary elevation-0">
        <a href="{{ route('admin.dashboard') }}" class="brand-link px-4">
            <div class="d-flex align-items-center gap-2">
                <div style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#e63946,#c1121f);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-car-side text-white" style="font-size:.8rem;"></i>
                </div>
                <span class="brand-text">ROTEH</span>
            </div>
        </a>

        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center px-3">
                <div style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-user-tie" style="color:#94a3b8;font-size:.85rem;"></i>
                </div>
                <div class="info ml-2">
                    <a href="#" class="d-block" style="font-size:.85rem;">{{ Auth::user()->name ?? 'Administrator' }}</a>
                    <span style="font-size:.7rem;color:#64748b;">Super Admin</span>
                </div>
            </div>

            @php
                $pendingDrivers    = rescue(fn() => \App\Models\User::where('role','driver')->where('approval_status','pending')->count(), 0, false);
                $pendingTx         = rescue(fn() => \App\Models\TransactionRecord::where('status','pending')->count(), 0, false);
                $pendingTopup      = rescue(fn() => \App\Models\TopUpRequest::where('status','pending')->count(), 0, false);
                $pendingWithdraw   = rescue(fn() => \App\Models\WithdrawalRequest::where('status','pending')->count(), 0, false);

                $needsReplyCount = rescue(function () {
                    return \App\Models\SupportTicket::whereIn('status', ['open', 'in_progress'])
                        ->with(['messages' => fn($q) => $q->latest('id')->limit(1)->with('sender:id,role')])
                        ->get()
                        ->filter(function ($t) {
                            $last = $t->messages->first();
                            return ! $last || ! $last->sender || $last->sender->role !== 'admin';
                        })
                        ->count();
                }, 0, false);
            @endphp

            @php
                $pendingSettlements = rescue(fn() => \App\Models\Settlement::where('status','pending')->count(), 0, false);

                // [label, icon, route, active-patterns, badge count, badge class, gate]
                $menu = [
                    ['header' => 'Overview', 'items' => [
                        ['Dashboard', 'fa-gauge-high', 'admin.dashboard', ['admin.dashboard']],
                    ]],
                    ['header' => 'Operations', 'items' => [
                        ['Rides',            'fa-route',            'admin.rides',            ['admin.rides']],
                        ['Deliveries',       'fa-box',              'admin.deliveries',       ['admin.deliveries']],
                        ['Car Rentals',      'fa-car-side',         'admin.car-rentals',      ['admin.car-rentals*']],
                        ['Marketplace',      'fa-store',            'admin.marketplace',      ['admin.marketplace']],
                        ['Marketplace Orders','fa-shopping-bag',    'admin.marketplace-orders',['admin.marketplace-orders*']],
                        ['Charging Stations','fa-charging-station', 'admin.charging-stations',['admin.charging-stations']],
                    ]],
                    ['header' => 'People', 'items' => [
                        ['Users',             'fa-users',        'admin.users',             ['admin.users'], 0, '', 'manage-users'],
                        ['Drivers',           'fa-id-card',      'admin.drivers',           ['admin.drivers*'], $pendingDrivers, 'badge-danger'],
                        ['Vehicles',          'fa-car',          'admin.vehicles',          ['admin.vehicles']],
                        ['Companies',         'fa-building',     'admin.companies',         ['admin.companies']],
                        ['Business Accounts', 'fa-briefcase',    'admin.business-accounts', ['admin.business-accounts*']],
                        ['Partner Contracts', 'fa-file-contract','admin.partner-contracts', ['admin.partner-contracts*']],
                    ]],
                    ['header' => 'Finance', 'items' => [
                        ['Transactions',    'fa-receipt',              'admin.transactions',       ['admin.transactions'], $pendingTx, 'badge-danger'],
                        ['Top-up Requests', 'fa-money-bill-transfer',  'admin.topups',             ['admin.topups'], $pendingTopup, 'badge-warning'],
                        ['Driver Payouts',  'fa-money-check-alt',      'admin.withdrawals',        ['admin.withdrawals'], $pendingWithdraw, 'badge-danger'],
                        ['Wallet',          'fa-wallet',               'admin.wallet',             ['admin.wallet']],
                        ['Settlements',     'fa-file-invoice-dollar',  'admin.settlements.index',  ['admin.settlements.*'], $pendingSettlements, 'badge-warning'],
                    ]],
                    ['header' => 'Pricing', 'items' => [
                        ['Ride Pricing',        'fa-tags',          'admin.ride-pricing',       ['admin.ride-pricing']],
                        ['Delivery Fare',       'fa-box-open',      'admin.delivery-fare',      ['admin.delivery-fare']],
                        ['Moving Fare',         'fa-truck-moving',  'admin.moving-fare',        ['admin.moving-fare']],
                        ['Fare Management',     'fa-sliders-h',     'admin.fare-management',    ['admin.fare-management']],
                        ['Surge Zones',         'fa-bolt',          'admin.surge-zones',        ['admin.surge-zones']],
                        ['Airport Zones',       'fa-plane-departure','admin.airport-zones',     ['admin.airport-zones*']],
                        ['Subscription Plans',  'fa-layer-group',   'admin.subscription-plans', ['admin.subscription-plans*']],
                    ]],
                    ['header' => 'Marketing', 'items' => [
                        ['Banners', 'fa-images',    'admin.banners',       ['admin.banners']],
                        ['Events',  'fa-bullhorn',  'admin.promo-events',  ['admin.promo-events']],
                        ['Coupons', 'fa-ticket-alt','admin.promo-coupons', ['admin.promo-coupons']],
                    ]],
                    ['header' => 'Reports', 'items' => [
                        ['label' => 'Business', 'icon' => 'fa-chart-line', 'children' => [
                            ['Operations',  '', 'admin.operations-report',  ['admin.operations-report']],
                            ['Orders',      '', 'admin.report.orders',      ['admin.report.orders']],
                            ['Performance', '', 'admin.report.performance', ['admin.report.performance']],
                            ['Analytics',   '', 'admin.report.analytics',   ['admin.report.analytics']],
                        ]],
                        ['label' => 'Financial', 'icon' => 'fa-coins', 'children' => [
                            ['Financial',   '', 'admin.report.financial',   ['admin.report.financial']],
                            ['Commission',  '', 'admin.report.commission',  ['admin.report.commission']],
                            ['Wallet',      '', 'admin.report.wallet',      ['admin.report.wallet']],
                            ['Withdrawals', '', 'admin.report.withdrawals', ['admin.report.withdrawals']],
                        ]],
                        ['label' => 'People', 'icon' => 'fa-user-friends', 'children' => [
                            ['Drivers',        '', 'admin.report.drivers',        ['admin.report.drivers']],
                            ['Driver Ranking', '', 'admin.report.driver-ranking', ['admin.report.driver-ranking']],
                            ['Customers',      '', 'admin.report.customers',      ['admin.report.customers']],
                            ['Partners',       '', 'admin.report.partners',       ['admin.report.partners']],
                        ]],
                    ]],
                    ['header' => 'Support', 'items' => [
                        ['Support Tickets', 'fa-headset',       'admin.support', ['admin.support*'], $needsReplyCount, 'badge-danger'],
                        ['Safety',          'fa-shield-halved', 'admin.safety',  ['admin.safety']],
                    ]],
                    ['header' => 'System', 'items' => [
                        ['Roles & Permissions', 'fa-user-shield', 'admin.roles', ['admin.roles*'], 0, '', 'manage-roles'],
                        ['Chat Testing',        'fa-comments',    'admin.chat',  ['admin.chat']],
                    ]],
                ];
            @endphp

            <nav class="mt-1">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    @foreach($menu as $section)
                        <li class="nav-header" style="font-size:.65rem;color:#475569;letter-spacing:.1em;padding:12px 16px 4px;text-transform:uppercase;">{{ $section['header'] }}</li>

                        @foreach($section['items'] as $item)
                            @if(isset($item['children']))
                                @php $open = collect($item['children'])->contains(fn($c) => request()->routeIs(...$c[3])); @endphp
                                <li class="nav-item {{ $open ? 'menu-open' : '' }}">
                                    <a href="#" class="nav-link {{ $open ? 'active' : '' }}">
                                        <i class="nav-icon fas {{ $item['icon'] }}"></i>
                                        <p>{{ $item['label'] }} <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview">
                                        @foreach($item['children'] as $c)
                                            <li class="nav-item">
                                                <a href="{{ route($c[2]) }}" class="nav-link {{ request()->routeIs(...$c[3]) ? 'active' : '' }}">
                                                    <i class="far fa-circle nav-icon"></i><p>{{ $c[0] }}</p>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @else
                                @php [$label, $icon, $route, $match] = $item; $count = $item[4] ?? 0; $badge = $item[5] ?? ''; $gate = $item[6] ?? null; @endphp
                                @if(! $gate || Gate::allows($gate))
                                    <li class="nav-item">
                                        <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs(...$match) ? 'active' : '' }}">
                                            <i class="nav-icon fas {{ $icon }}"></i>
                                            <p>
                                                {{ $label }}
                                                @if($count)<span class="right badge {{ $badge }}">{{ $count }}</span>@endif
                                            </p>
                                        </a>
                                    </li>
                                @endif
                            @endif
                        @endforeach
                    @endforeach
                </ul>
            </nav>
        </div>
    </aside>

    {{-- ── Page Content ── --}}
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h1>@yield('page-title', 'Dashboard')</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right" style="background:transparent;padding:0;margin:0;font-size:.8rem;">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" style="color:#e63946;">Home</a></li>
                            <li class="breadcrumb-item active" style="color:#94a3b8;">@yield('page-title', 'Dashboard')</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                {{-- Test-mode banner shown to non-admin users --}}
                @if(config('app.admin_test_mode') && Auth::check() && Auth::user()->role !== 'admin')
                @php $roleColor = Auth::user()->role === 'driver' ? '#d97706' : '#7c3aed'; $roleIcon = Auth::user()->role === 'driver' ? 'fa-car' : 'fa-user'; @endphp
                <div class="alert mb-3 d-flex align-items-center justify-content-between"
                     style="background:{{ $roleColor }}15;border:1.5px solid {{ $roleColor }};border-radius:10px;padding:10px 16px;">
                    <div>
                        <i class="fas {{ $roleIcon }} mr-2" style="color:{{ $roleColor }};"></i>
                        <strong style="color:{{ $roleColor }};">TEST MODE</strong>
                        <span class="ml-2" style="font-size:.875rem;">
                            Logged in as <strong>{{ Auth::user()->name }}</strong>
                            (<span style="color:{{ $roleColor }};">{{ ucfirst(Auth::user()->role) }}</span>)
                            — Admin panel access is for testing only.
                        </span>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}" class="mb-0">
                        @csrf
                        <button class="btn btn-sm" style="border:1px solid {{ $roleColor }};color:{{ $roleColor }};background:transparent;">
                            <i class="fas fa-sign-out-alt mr-1"></i> Exit
                        </button>
                    </form>
                </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show">
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </section>
    </div>

    {{-- ── Footer ── --}}
    <footer class="main-footer">
        <strong style="color:#1e293b;">ROTEH</strong> &mdash; Admin Panel
        <div class="float-right d-none d-sm-inline-block" style="font-size:.75rem;">
            v1.0 &nbsp;&bull;&nbsp; {{ date('Y') }}
        </div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>

@stack('scripts')
</body>
</html>
