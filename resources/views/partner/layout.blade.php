<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Partner Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Tailwind preflight resets certain Bootstrap styles — restore table borders */
        .table { --bs-table-bg: transparent; }
        .form-control:focus, .form-select:focus { border-color: #e63946; box-shadow: 0 0 0 .2rem rgba(230,57,70,.2); }
        .btn-brand { background: linear-gradient(135deg,#e63946,#c1121f); color:#fff; border:none; }
        .btn-brand:hover { background: linear-gradient(135deg,#c1121f,#a00f19); color:#fff; }
        /* Sidebar nav active */
        #sidebar .nav-active { background: linear-gradient(135deg,#e63946,#c1121f); color: #fff !important; }
        #sidebar .nav-active i { color: #fff !important; }
        /* Sidebar transition */
        #sidebar { transition: transform .25s ease; }
        @stack('styles-inline')
    </style>
    @stack('styles')
</head>
<body class="h-full bg-slate-50">

{{-- Mobile overlay --}}
<div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>

{{-- ── Sidebar ──────────────────────────────────────────────────── --}}
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col"
       style="background:#0f172a; transform:translateX(-100%)"
       aria-label="Sidebar">

    {{-- Brand --}}
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background:linear-gradient(135deg,#e63946,#c1121f)">
            <i class="fas fa-truck text-white text-sm"></i>
        </div>
        <div>
            <div class="text-white font-bold text-base leading-tight">
                <span style="color:#e63946">Auto</span>Ride
            </div>
            <div class="text-slate-400 text-xs">Partner Portal</div>
        </div>
        <button onclick="closeSidebar()" class="ml-auto text-slate-400 hover:text-white lg:hidden">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- User chip --}}
    <div class="px-4 py-4 border-b border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-600 to-slate-700 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user text-slate-300 text-sm"></i>
            </div>
            <div class="min-w-0">
                <div class="text-white text-sm font-semibold truncate">{{ Auth::guard('partner')->user()->name }}</div>
                <div class="text-slate-400 text-xs truncate">{{ Auth::guard('partner')->user()->phone }}</div>
            </div>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <p class="px-3 text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1">Main</p>
        <a href="{{ route('partner.dashboard') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors
                  {{ request()->routeIs('partner.dashboard') ? 'nav-active' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <i class="fas fa-tachometer-alt w-4 text-center"></i><span>Dashboard</span>
        </a>

        <p class="px-3 text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1 mt-4">Orders</p>
        <a href="{{ route('partner.orders') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors
                  {{ request()->routeIs('partner.orders') ? 'nav-active' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <i class="fas fa-box w-4 text-center"></i><span>All Orders</span>
        </a>
        <a href="{{ route('partner.orders.create') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors
                  {{ request()->routeIs('partner.orders.create') ? 'nav-active' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <i class="fas fa-plus-circle w-4 text-center"></i><span>New Order</span>
        </a>

        <p class="px-3 text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1 mt-4">Finance</p>
        <a href="{{ route('partner.wallet') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors
                  {{ request()->routeIs('partner.wallet') ? 'nav-active' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <i class="fas fa-wallet w-4 text-center"></i><span>Wallet &amp; COD</span>
        </a>
        <a href="{{ route('partner.reports') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors
                  {{ request()->routeIs('partner.reports') ? 'nav-active' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <i class="fas fa-chart-bar w-4 text-center"></i><span>Reports</span>
        </a>

        <p class="px-3 text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1 mt-4">Support</p>
        <a href="{{ route('partner.issues') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors
                  {{ request()->routeIs('partner.issues') ? 'nav-active' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <i class="fas fa-exclamation-triangle w-4 text-center"></i><span>Issues</span>
        </a>
    </nav>

    {{-- Logout --}}
    <div class="px-4 py-4 border-t border-white/10">
        <form method="POST" action="{{ route('partner.logout') }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors">
                <i class="fas fa-sign-out-alt w-4 text-center"></i><span>Logout</span>
            </button>
        </form>
    </div>
</aside>

{{-- ── Main wrapper (shifts right on lg) ──────────────────────────── --}}
<div id="main-wrap" class="flex flex-col min-h-full transition-all duration-250">

    {{-- ── Topbar ─────────────────────────────────────────────────── --}}
    <header class="sticky top-0 z-30 bg-white border-b border-slate-200 h-16 flex items-center px-4 sm:px-6 gap-4">
        {{-- Hamburger --}}
        <button onclick="openSidebar()" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors" aria-label="Open menu">
            <i class="fas fa-bars"></i>
        </button>

        {{-- Page title --}}
        <h1 class="text-slate-700 font-semibold text-base truncate hidden sm:block">@yield('page-title', 'Dashboard')</h1>

        <div class="ml-auto flex items-center gap-3">
            {{-- Wallet balance --}}
            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-emerald-50 rounded-lg border border-emerald-200">
                <i class="fas fa-wallet text-emerald-600 text-sm"></i>
                <span class="text-emerald-700 font-semibold text-sm">{{ number_format(Auth::guard('partner')->user()->wallet_balance) }} ៛</span>
            </div>

            {{-- User dropdown --}}
            <div class="relative" id="user-menu-wrap">
                <button onclick="toggleUserMenu()" class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors text-sm text-slate-700">
                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center">
                        <i class="fas fa-user text-white" style="font-size:.6rem"></i>
                    </div>
                    <span class="hidden sm:inline font-medium">{{ Auth::guard('partner')->user()->name }}</span>
                    <i class="fas fa-chevron-down text-xs text-slate-400"></i>
                </button>
                <div id="user-menu" class="hidden absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-xs text-slate-500">Signed in as</p>
                        <p class="text-sm font-semibold text-slate-700 truncate">{{ Auth::guard('partner')->user()->name }}</p>
                    </div>
                    <form method="POST" action="{{ route('partner.logout') }}" class="px-2 py-1">
                        @csrf
                        <button class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- ── Content ─────────────────────────────────────────────────── --}}
    <main class="flex-1 px-4 py-6 sm:px-6">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="text-center py-4 text-xs text-slate-400 border-t border-slate-200 bg-white">
        AutoRide Partner Portal &copy; {{ date('Y') }}
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var sidebarOpen = false;

function openSidebar() {
    sidebarOpen = true;
    document.getElementById('sidebar').style.transform = 'translateX(0)';
    document.getElementById('overlay').classList.remove('hidden');
    document.getElementById('main-wrap').style.paddingLeft = '';
}
function closeSidebar() {
    sidebarOpen = false;
    var w = window.innerWidth;
    if (w < 1024) {
        document.getElementById('sidebar').style.transform = 'translateX(-100%)';
        document.getElementById('overlay').classList.add('hidden');
    }
}
function toggleUserMenu() {
    document.getElementById('user-menu').classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('user-menu-wrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('user-menu').classList.add('hidden');
    }
});

// On desktop: sidebar always visible
function handleResize() {
    var sidebar = document.getElementById('sidebar');
    var wrap    = document.getElementById('main-wrap');
    if (window.innerWidth >= 1024) {
        sidebar.style.transform = 'translateX(0)';
        wrap.style.paddingLeft  = '256px';
        document.getElementById('overlay').classList.add('hidden');
    } else {
        if (!sidebarOpen) sidebar.style.transform = 'translateX(-100%)';
        wrap.style.paddingLeft = '';
    }
}
window.addEventListener('resize', handleResize);
handleResize();
</script>
@stack('scripts')
</body>
</html>
