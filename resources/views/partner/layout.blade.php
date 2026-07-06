<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Partner Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <style>
        .brand-link { background: linear-gradient(135deg, #1c1c2e, #2d1b3d) !important; }
        .brand-text  { color: #fff !important; font-weight: 700 !important; }
        .brand-accent { color: #e63946; }
        .main-sidebar { background: #1c1c2e !important; }
        .nav-sidebar .nav-link { color: #94a3b8 !important; border-radius: 8px; margin: 2px 8px; }
        .nav-sidebar .nav-link:hover { background: rgba(255,255,255,0.07) !important; color: #fff !important; }
        .nav-sidebar .nav-link.active { background: linear-gradient(135deg, #e63946, #c1121f) !important; color: #fff !important; }
        .nav-sidebar .nav-icon { color: inherit !important; }
        .content-wrapper { background: #f8fafc; }
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 8px rgba(0,0,0,0.06); }
        .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; border-radius: 12px 12px 0 0 !important; }
        .stat-card { border-radius: 12px; padding: 20px; color: #fff; }
        .nav-label { font-size: .65rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #475569; padding: 12px 16px 4px; }
        @stack('styles-inline')
    </style>
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

{{-- Navbar --}}
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <span class="nav-link font-weight-bold text-muted">@yield('page-title', 'Dashboard')</span>
        </li>
    </ul>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item">
            <span class="nav-link">
                <i class="fas fa-wallet mr-1 text-success"></i>
                <strong>{{ number_format(Auth::guard('partner')->user()->wallet_balance) }} ៛</strong>
            </span>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="fas fa-user-circle mr-1"></i>
                {{ Auth::guard('partner')->user()->name }}
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <form method="POST" action="{{ route('partner.logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger"><i class="fas fa-sign-out-alt mr-2"></i>Logout</button>
                </form>
            </div>
        </li>
    </ul>
</nav>

{{-- Sidebar --}}
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="{{ route('partner.dashboard') }}" class="brand-link px-3">
        <span class="brand-text font-weight-bold">
            <span class="brand-accent">Auto</span>Ride
            <small class="d-block" style="font-size:.65rem; font-weight:400; opacity:.6; letter-spacing:.05em;">Partner Portal</small>
        </span>
    </a>
    <div class="sidebar">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block">{{ Auth::guard('partner')->user()->name }}</a>
                <small style="color:#64748b; font-size:.75rem;">{{ Auth::guard('partner')->user()->phone }}</small>
            </div>
        </div>
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                <li class="nav-label">Main</li>
                <li class="nav-item">
                    <a href="{{ route('partner.dashboard') }}"
                       class="nav-link {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-label mt-2">Orders</li>
                <li class="nav-item">
                    <a href="{{ route('partner.orders') }}"
                       class="nav-link {{ request()->routeIs('partner.orders*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-box"></i><p>All Orders</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('partner.orders.create') }}"
                       class="nav-link {{ request()->routeIs('partner.orders.create') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-plus-circle"></i><p>New Order</p>
                    </a>
                </li>

                <li class="nav-label mt-2">Finance</li>
                <li class="nav-item">
                    <a href="{{ route('partner.wallet') }}"
                       class="nav-link {{ request()->routeIs('partner.wallet') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-wallet"></i><p>Wallet & COD</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('partner.reports') }}"
                       class="nav-link {{ request()->routeIs('partner.reports') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-bar"></i><p>Reports</p>
                    </a>
                </li>

                <li class="nav-label mt-2">Support</li>
                <li class="nav-item">
                    <a href="{{ route('partner.issues') }}"
                       class="nav-link {{ request()->routeIs('partner.issues') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-exclamation-triangle"></i><p>Issues</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

{{-- Content --}}
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">@yield('page-title')</h1>
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
            @yield('content')
        </div>
    </section>
</div>

<footer class="main-footer text-center text-muted py-2">
    <small>AutoRide Partner Portal &copy; {{ date('Y') }}</small>
</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
@stack('scripts')
</body>
</html>
