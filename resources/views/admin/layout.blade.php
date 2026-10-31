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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Kantumruy+Pro:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}?v={{ @filemtime(public_path('css/admin-theme.css')) }}">
    {{-- Tailwind utilities (tw: prefix, no preflight) — built by `npm run build` / served by `npm run dev`. --}}
    @if(file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite('resources/css/admin.css')
    @endif

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
                <span class="nav-link topbar-status">
                    <i class="far fa-calendar" style="font-size:.75rem;"></i>
                    {{ now(config('app.timezone'))->format('l, d M Y') }}
                </span>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            @php
                $anEnabled = Auth::check() && Auth::user()->role === 'admin';
                $anCounts  = $anEnabled
                    ? rescue(fn () => \App\Models\AdminNotification::unreadCounts(Auth::id()), null, false)
                    : null;
            @endphp
            @if($anCounts)
                @include('admin.partials.notification-bell')
            @endif
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    @php $adminInitials = collect(preg_split('/\s+/', trim(Auth::user()->name ?? 'Admin')))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join(''); @endphp
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-chip">{{ $adminInitials ?: 'A' }}</span>
                        <span class="d-none d-md-inline text-left" style="line-height:1.2;">
                            <span class="d-block" style="font-size:.84rem;font-weight:600;color:var(--ink-900);">{{ Auth::user()->name ?? 'Admin' }}</span>
                            <span class="d-block" style="font-size:.72rem;color:var(--ink-500);">{{ Auth::user()?->getRoleNames()->first() ?? ucfirst(Auth::user()->role ?? 'admin') }}</span>
                        </span>
                        <i class="fas fa-chevron-down" style="font-size:.6rem;color:var(--ink-400);"></i>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right" style="min-width:220px;">
                    <div class="px-3 py-2 mb-1 border-bottom">
                        <div style="font-weight:600;font-size:.84rem;color:var(--ink-900);">{{ Auth::user()->name ?? 'Admin' }}</div>
                        <div style="font-size:.74rem;color:var(--ink-500);">{{ Auth::user()->email ?? '' }}</div>
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
                <div class="brand-mark"><i class="fas fa-car-side text-white" style="font-size:.8rem;"></i></div>
                <div>
                    <span class="brand-text">ROTEH</span>
                    <span class="brand-sub">Ride Platform</span>
                </div>
            </div>
        </a>

        <div class="sidebar">

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
                    ['header' => null, 'items' => [
                        ['Dashboard', 'fa-house', 'admin.dashboard', ['admin.dashboard']],
                    ]],
                    ['header' => 'Operations', 'items' => [
                        ['Rides',            'fa-route',            'admin.rides',            ['admin.rides']],
                        ['Deliveries',       'fa-box',              'admin.deliveries',       ['admin.deliveries']],
                        ['Rental',      'fa-car-side',         'admin.car-rentals',      ['admin.car-rentals*']],
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

            <nav class="mt-3">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    @foreach($menu as $section)
                        @if($section['header'])<li class="nav-header">{{ $section['header'] }}</li>@endif

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
        @hasSection('page-header')
            <div class="content-header"><div class="container-fluid">@yield('page-header')</div></div>
        @else
        <div class="content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h1>@yield('page-title', \Illuminate\Support\Str::before(\View::yieldContent('title', 'Dashboard'), ' |'))</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right" style="background:transparent;padding:0;margin:0;font-size:.8rem;">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="fas fa-house" style="font-size:.7rem;"></i></a></li>
                            <li class="breadcrumb-item active">@yield('page-title', \Illuminate\Support\Str::before(\View::yieldContent('title', 'Dashboard'), ' |'))</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        @endif

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
        &copy; {{ date('Y') }} <strong style="color:var(--ink-600);">ROTEH</strong> &middot; Admin Console
        <div class="float-right d-none d-sm-inline-block">v1.0</div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
@if($anCounts)
    @include('admin.partials.notification-script')
@endif

<script>
    // Tooltips for icon-only buttons (row actions etc.).
    $(function () { $('[data-toggle="tooltip"]').tooltip({ boundary: 'window', trigger: 'hover' }); });
</script>
@stack('scripts')
</body>
</html>
