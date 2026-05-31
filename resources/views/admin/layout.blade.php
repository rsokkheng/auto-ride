<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | AutoRide Admin</title>

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
                    &nbsp;AutoRide Admin
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
                <span class="brand-text">Auto<span class="brand-accent">Ride</span></span>
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

            <nav class="mt-1">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

                    <li class="nav-header" style="font-size:.65rem;color:#475569;letter-spacing:.1em;padding:8px 16px 4px;">MAIN</li>

                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-gauge-high"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    <li class="nav-header" style="font-size:.65rem;color:#475569;letter-spacing:.1em;padding:8px 16px 4px;">MANAGEMENT</li>

                    <li class="nav-item">
                        <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Users</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.vehicles') }}" class="nav-link {{ request()->routeIs('admin.vehicles') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-car"></i>
                            <p>Vehicles</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.rides') }}" class="nav-link {{ request()->routeIs('admin.rides') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-route"></i>
                            <p>Rides</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.deliveries') }}" class="nav-link {{ request()->routeIs('admin.deliveries') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-box"></i>
                            <p>Deliveries</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.marketplace') }}" class="nav-link {{ request()->routeIs('admin.marketplace') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-store"></i>
                            <p>Marketplace</p>
                        </a>
                    </li>

                    <li class="nav-header" style="font-size:.65rem;color:#475569;letter-spacing:.1em;padding:8px 16px 4px;">DRIVER &amp; FINANCE</li>

                    <li class="nav-item">
                        <a href="{{ route('admin.transactions') }}" class="nav-link {{ request()->routeIs('admin.transactions') ? 'active' : '' }}">
                            @php $pendingTx = \App\Models\TransactionRecord::where('status','pending')->count(); @endphp
                            <i class="nav-icon fas fa-receipt"></i>
                            <p>
                                Transactions
                                @if($pendingTx)
                                    <span class="right badge badge-danger">{{ $pendingTx }}</span>
                                @endif
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.companies') }}" class="nav-link {{ request()->routeIs('admin.companies') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Companies</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.wallet') }}" class="nav-link {{ request()->routeIs('admin.wallet') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-wallet"></i>
                            <p>Wallet &amp; Transactions</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.topups') }}" class="nav-link {{ request()->routeIs('admin.topups') ? 'active' : '' }}">
                            @php $pendingCount = \App\Models\TopUpRequest::where('status','pending')->count(); @endphp
                            <i class="nav-icon fas fa-money-bill-transfer"></i>
                            <p>
                                Top-up Requests
                                @if($pendingCount)
                                    <span class="right badge badge-warning">{{ $pendingCount }}</span>
                                @endif
                            </p>
                        </a>
                    </li>

                    <li class="nav-header" style="font-size:.65rem;color:#475569;letter-spacing:.1em;padding:8px 16px 4px;">SERVICES</li>

                    <li class="nav-item">
                        <a href="{{ route('admin.charging-stations') }}" class="nav-link {{ request()->routeIs('admin.charging-stations') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-charging-station"></i>
                            <p>Charging Stations</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.surge-zones') }}" class="nav-link {{ request()->routeIs('admin.surge-zones') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-bolt"></i>
                            <p>Surge Zones</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.chat') }}" class="nav-link {{ request()->routeIs('admin.chat') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-comments"></i>
                            <p>Chat Testing</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.support') }}" class="nav-link {{ request()->routeIs('admin.support') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-headset"></i>
                            <p>Support</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.safety') }}" class="nav-link {{ request()->routeIs('admin.safety') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-shield-halved"></i>
                            <p>Safety</p>
                        </a>
                    </li>
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
        <strong style="color:#1e293b;">AutoRide</strong> &mdash; Admin Panel
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
