@extends('admin.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@php
    $khr          = fn ($v) => number_format((int) $v) . ' ៛';
    $chartDays    = count($revenueChart);
    $periodLabels = ['today' => 'Today', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days'];
    $periodLabel  = $periodLabels[$period];
    $trend = fn (?float $g, string $suffix) => $g === null
        ? null
        : ['up' => $g >= 0, 'text' => ($g >= 0 ? '+' : '−') . abs($g) . '% ' . $suffix];
@endphp

@push('styles')
<style>
.dash-hero { position: relative; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; min-height: 72px; }
.dash-hero h1 { font-size: 1.75rem !important; font-weight: 700; letter-spacing: -.03em; }
.dash-hero .hero-art { position: absolute; left: 50%; top: 50%; transform: translate(-58%, -54%); pointer-events: none; opacity: .95; }
.period-toggle { display: inline-flex; background: #fff; border: 1px solid var(--ink-200); border-radius: 12px; padding: 4px; box-shadow: var(--shadow-sm); position: relative; z-index: 1; }
.period-toggle a { padding: .42rem .95rem; border-radius: 9px; font-size: .82rem; font-weight: 500; color: var(--ink-600); text-decoration: none; white-space: nowrap; }
.period-toggle a:hover { color: var(--ink-900); background: var(--ink-50); }
.period-toggle a.active { background: linear-gradient(135deg, #4f46e5, #6d28d9); color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, .35); }

.kpi-grad { position: relative; overflow: hidden; border-radius: 14px; color: #fff; padding: 1.25rem 1.35rem; display: flex; align-items: center; gap: 1.1rem; min-height: 118px; box-shadow: 0 10px 24px -12px var(--kpi-shadow); }
.kpi-grad::before, .kpi-grad::after { content: ''; position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .09); pointer-events: none; }
.kpi-grad::before { width: 220px; height: 220px; right: -70px; top: -110px; }
.kpi-grad::after  { width: 160px; height: 160px; right: 30px; bottom: -110px; background: rgba(255, 255, 255, .07); }
.kpi-grad .kpi-ico { width: 56px; height: 56px; border-radius: 14px; background: rgba(255, 255, 255, .18); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; backdrop-filter: blur(2px); }
.kpi-grad .kpi-lbl { font-size: .86rem; font-weight: 500; opacity: .92; }
.kpi-grad .kpi-val { white-space: nowrap; font-size: 1.65rem; font-weight: 700; line-height: 1.15; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
.kpi-grad .kpi-sub { font-size: .78rem; opacity: .92; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kpi-blue   { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 55%, #7c3aed 100%); --kpi-shadow: rgba(79, 70, 229, .7); }
.kpi-green  { background: linear-gradient(135deg, #10b981, #059669); --kpi-shadow: rgba(5, 150, 105, .7); }
.kpi-orange { background: linear-gradient(135deg, #f59e0b, #ea580c); --kpi-shadow: rgba(234, 88, 12, .7); }
.kpi-indigo { background: linear-gradient(135deg, #2563eb, #4338ca); --kpi-shadow: rgba(67, 56, 202, .7); }

.mini-tile { background: linear-gradient(180deg, #fff 40%, var(--tile-tint) 160%); border: 1px solid var(--ink-200); border-radius: 14px; padding: 1.1rem .75rem; text-align: center; box-shadow: var(--shadow-sm); transition: transform .15s, box-shadow .15s; display: block; color: inherit; text-decoration: none !important; }
a.mini-tile:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); color: inherit; }
.mini-tile .tile-ico { width: 46px; height: 46px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem; margin-bottom: .5rem; }
.mini-tile .tile-val { font-size: 1.5rem; font-weight: 700; color: var(--ink-900); line-height: 1.1; font-variant-numeric: tabular-nums; }
.mini-tile .tile-lbl { font-size: .82rem; color: var(--ink-600); margin-top: 2px; }

.panel { background: #fff; border: 1px solid var(--ink-200); border-radius: 14px; box-shadow: var(--shadow-sm); height: 100%; display: flex; flex-direction: column; }
.panel-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: 1rem 1.25rem .5rem; }
.panel-head h6 { margin: 0; font-size: .98rem; font-weight: 600; color: var(--ink-900); display: flex; align-items: center; gap: .55rem; }
.panel-head .chip { font-size: .74rem; font-weight: 500; color: var(--ink-600); border: 1px solid var(--ink-200); border-radius: 8px; padding: .2rem .6rem; background: #fff; }
.panel-body { padding: .5rem 1.25rem 1.1rem; flex: 1; }

.legend-row { display: grid; grid-template-columns: 10px 1fr auto 48px; align-items: center; gap: .55rem; white-space: nowrap; padding: .42rem 0; font-size: .82rem; border-bottom: 1px solid var(--ink-100); }
.legend-row:last-child { border-bottom: none; }
.legend-row .dot { width: 10px; height: 10px; border-radius: 50%; }
.legend-row .num { font-weight: 600; color: var(--ink-900); text-align: right; font-variant-numeric: tabular-nums; }
.legend-row .pct { color: var(--ink-500); text-align: right; font-variant-numeric: tabular-nums; }
.donut-wrap { position: relative; width: 136px; height: 136px; flex-shrink: 0; }
.donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
.donut-center b { font-size: 1.6rem; color: var(--ink-900); line-height: 1; }
.donut-center span { font-size: .72rem; color: var(--ink-500); margin-top: 3px; }

.svc-row { display: grid; grid-template-columns: 34px 1fr; gap: .75rem; align-items: center; padding: .55rem 0; }
.svc-ico { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: .9rem; }
.svc-bar { height: 6px; border-radius: 999px; background: var(--ink-100); overflow: hidden; margin-top: 6px; }
.svc-bar > span { display: block; height: 100%; border-radius: 999px; }

.ride-row { display: flex; align-items: center; gap: .8rem; padding: .6rem 0; border-bottom: 1px solid var(--ink-100); }
.ride-row:last-child { border-bottom: none; }
.ride-ico { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex-shrink: 0; }

.queue-row { display: flex; align-items: center; gap: .75rem; padding: .6rem 1.25rem; border-top: 1px solid var(--ink-100); }
.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
@media (max-width: 991.98px) { .dash-hero .hero-art { display: none; } }
</style>
@endpush

{{-- ── Hero header ─────────────────────────────────────────────────────── --}}
@section('page-header')
<div class="dash-hero">
    <div style="position:relative;z-index:1;">
        <h1 class="m-0">Dashboard</h1>
        <p class="m-0 text-muted" style="font-size:.9rem;">Overview of your ride platform</p>
    </div>

    {{-- Skyline + car illustration --}}
    <svg class="hero-art" width="330" height="96" viewBox="0 0 330 96" aria-hidden="true">
        <defs>
            <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#c7d2fe" stop-opacity=".75"/>
                <stop offset="1" stop-color="#e0e7ff" stop-opacity=".15"/>
            </linearGradient>
            <linearGradient id="carBody" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#e0e7ff"/>
            </linearGradient>
        </defs>
        <g fill="url(#sky)">
            <rect x="8" y="40" width="18" height="56" rx="2"/><rect x="30" y="26" width="14" height="70" rx="2"/>
            <rect x="48" y="50" width="22" height="46" rx="2"/><rect x="196" y="22" width="16" height="74" rx="2"/>
            <rect x="216" y="38" width="22" height="58" rx="2"/><rect x="242" y="12" width="14" height="84" rx="2"/>
            <rect x="260" y="44" width="20" height="52" rx="2"/><rect x="284" y="30" width="16" height="66" rx="2"/>
            <rect x="304" y="52" width="20" height="44" rx="2"/>
        </g>
        <path d="M0 84 C 60 62, 120 62, 170 74 S 280 92, 330 70 L 330 96 L 0 96 Z" fill="#e0e7ff" opacity=".85"/>
        <g transform="translate(118 50)">
            <ellipse cx="54" cy="38" rx="56" ry="4" fill="#6366f1" opacity=".18"/>
            <path d="M6 30 C 6 22, 12 19, 22 18 L 36 8 C 40 5, 46 4, 54 4 L 76 4 C 84 4, 90 7, 95 12 L 104 19 C 110 21, 112 25, 112 30 L 112 32 L 6 32 Z" fill="url(#carBody)" stroke="#a5b4fc" stroke-width="1.2"/>
            <path d="M40 10 L 52 7.5 L 64 7.5 L 64 18 L 30 18 Z" fill="#818cf8" opacity=".55"/>
            <path d="M68 7.5 L 80 7.5 C 86 8, 90 11, 93 18 L 68 18 Z" fill="#818cf8" opacity=".55"/>
            <rect x="104" y="22" width="7" height="3" rx="1.5" fill="#f87171"/>
            <circle cx="28" cy="32" r="7.5" fill="#312e81"/><circle cx="28" cy="32" r="3" fill="#c7d2fe"/>
            <circle cx="90" cy="32" r="7.5" fill="#312e81"/><circle cx="90" cy="32" r="3" fill="#c7d2fe"/>
        </g>
    </svg>

    <nav class="period-toggle" aria-label="Period">
        @foreach($periodLabels as $key => $label)
            <a href="{{ route('admin.dashboard', ['period' => $key]) }}" class="{{ $period === $key ? 'active' : '' }}"
               @if($period === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</div>
@endsection

@section('content')

@include('admin.partials.order-alert-banner')

{{-- ── KPI cards ───────────────────────────────────────────────────────── --}}
@php
    $kpis = [
        ['Today\'s Revenue', $khr($metrics['revenue_today']), 'fa-coins', 'kpi-blue',
            $trend($metrics['revenue_growth'], 'vs yesterday') ?? ['up' => null, 'text' => 'No data for yesterday']],
        ['Live Rides', number_format($metrics['rides_active']), 'fa-route', 'kpi-green',
            ['up' => null, 'text' => number_format($metrics['rides_today']) . ' started today']],
        ['Online Drivers', number_format($metrics['drivers_online']), 'fa-user-tie', 'kpi-orange',
            ['up' => null, 'text' => 'of ' . number_format($metrics['drivers']) . ' total']],
        ['7-Day Revenue', $khr($metrics['revenue_week']), 'fa-chart-line', 'kpi-indigo',
            $trend($metrics['revenue_week_growth'], 'vs previous 7 days') ?? ['up' => null, 'text' => 'Last 7 days']],
    ];
@endphp
<div class="tw:grid tw:grid-cols-1 tw:gap-4 tw:sm:grid-cols-2 tw:min-[87.5rem]:grid-cols-4">
    @foreach($kpis as [$label, $value, $icon, $class, $sub])
        <div class="kpi-grad {{ $class }}">
            <span class="kpi-ico"><i class="fas {{ $icon }}"></i></span>
            <div class="tw:min-w-0" style="position:relative;z-index:1;">
                <div class="kpi-lbl">{{ $label }}</div>
                <div class="kpi-val">{{ $value }}</div>
                <div class="kpi-sub">
                    @if($sub['up'] !== null)<i class="fas fa-arrow-{{ $sub['up'] ? 'up' : 'down' }} mr-1" style="font-size:.7rem;"></i>@endif{{ $sub['text'] }}
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- ── Secondary tiles ─────────────────────────────────────────────────── --}}
<div class="tw:mt-4 tw:grid tw:grid-cols-2 tw:gap-4 tw:md:grid-cols-3 tw:min-[87.5rem]:grid-cols-6">
    @foreach([
        ['Passengers',        $metrics['users'],               'fa-users',          '#0ea5e9', '#e0f2fe', 'admin.users'],
        ['Today Deliveries',  $metrics['deliveries_today'],    'fa-box',            '#8b5cf6', '#ede9fe', 'admin.deliveries'],
        ['Open Tickets',      $metrics['support_open'],        'fa-headset',        '#ef4444', '#fee2e2', 'admin.support'],
        ['Pending Payouts',   $metrics['withdrawals_pending'], 'fa-money-check-alt','#f59e0b', '#fef3c7', 'admin.withdrawals'],
        ['Pending Drivers',   $metrics['drivers_pending'],     'fa-user-clock',     '#10b981', '#d1fae5', 'admin.drivers'],
        ['Marketplace Items', $metrics['marketplace'],         'fa-store',          '#6366f1', '#e0e7ff', 'admin.marketplace'],
    ] as [$label, $value, $icon, $color, $tint, $route])
        <a href="{{ route($route) }}" class="mini-tile" style="--tile-tint: {{ $tint }};">
            <span class="tile-ico" style="background: {{ $tint }}; color: {{ $color }};"><i class="fas {{ $icon }}"></i></span>
            <div class="tile-val">{{ number_format($value) }}</div>
            <div class="tile-lbl">{{ $label }}</div>
        </a>
    @endforeach
</div>

{{-- ── Charts ──────────────────────────────────────────────────────────── --}}
<div class="tw:mt-4 tw:grid tw:grid-cols-1 tw:gap-4 tw:lg:grid-cols-12">
    <div class="panel tw:lg:col-span-7">
        <div class="panel-head">
            <h6><i class="fas fa-chart-column" style="color:var(--primary);"></i> Revenue (Last {{ $chartDays }} Days)</h6>
            <span class="chip">KHR ៛</span>
        </div>
        <div class="panel-body"><div style="height:230px;"><canvas id="revenueChart"></canvas></div></div>
    </div>
    <div class="panel tw:lg:col-span-5">
        <div class="panel-head">
            <h6><i class="fas fa-route" style="color:var(--success);"></i> Completed Rides / Day</h6>
        </div>
        <div class="panel-body"><div style="height:230px;"><canvas id="ridesChart"></canvas></div></div>
    </div>
</div>

{{-- ── Ride status · Revenue by service · Recent rides ─────────────────── --}}
@php
    $statusMeta = [
        'completed'   => ['Completed',   '#22c55e'],
        'in_progress' => ['In Progress', '#3b82f6'],
        'pending'     => ['Pending',     '#f59e0b'],
        'cancelled'   => ['Cancelled',   '#ef4444'],
    ];
    $statusTotal = array_sum($rideStatus);
    $svcMeta = [
        'ride'     => ['Ride',     'fa-car-side', '#22c55e', '#dcfce7', 'linear-gradient(90deg,#6366f1,#7c3aed)'],
        'delivery' => ['Delivery', 'fa-box',      '#f59e0b', '#fef3c7', 'linear-gradient(90deg,#3b82f6,#6366f1)'],
        'rental'   => ['Rental',   'fa-key',      '#8b5cf6', '#ede9fe', 'linear-gradient(90deg,#8b5cf6,#a855f7)'],
    ];
    $svcTotal  = array_sum($revenueByService);
    $rideTones = [
        'completed' => ['#16a34a', '#dcfce7'], 'cancelled' => ['#dc2626', '#fee2e2'],
        'requested' => ['#d97706', '#fef3c7'], 'pending'   => ['#d97706', '#fef3c7'],
    ];
@endphp
<div class="tw:mt-4 tw:grid tw:grid-cols-1 tw:gap-4 tw:lg:grid-cols-2 tw:min-[87.5rem]:grid-cols-3">
    <div class="panel">
        <div class="panel-head"><h6><i class="fas fa-circle-half-stroke" style="color:var(--primary);"></i> Ride Status ({{ $periodLabel }})</h6></div>
        <div class="panel-body tw:flex tw:flex-wrap tw:items-center tw:content-start tw:gap-5 tw:pt-3" style="flex:0 0 auto;">
            <div class="donut-wrap">
                <canvas id="statusChart" width="136" height="136"></canvas>
                <div class="donut-center"><b>{{ number_format($statusTotal) }}</b><span>Total Rides</span></div>
            </div>
            <div class="tw:min-w-[200px] tw:flex-1">
                @foreach($statusMeta as $key => [$label, $color])
                    <div class="legend-row">
                        <span class="dot" style="background:{{ $color }};"></span>
                        <span>{{ $label }}</span>
                        <span class="num">{{ number_format($rideStatus[$key]) }}</span>
                        <span class="pct">{{ $statusTotal ? number_format($rideStatus[$key] / $statusTotal * 100, 1) : '0.0' }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h6><i class="fas fa-sack-dollar" style="color:var(--violet);"></i> Revenue by Service</h6><span class="chip">{{ $periodLabel }}</span></div>
        <div class="panel-body">
            @foreach($svcMeta as $key => [$label, $icon, $color, $tint, $bar])
                @php $share = $svcTotal ? $revenueByService[$key] / $svcTotal * 100 : 0; @endphp
                <div class="svc-row">
                    <span class="svc-ico" style="background:{{ $tint }};color:{{ $color }};"><i class="fas {{ $icon }}"></i></span>
                    <div>
                        <div class="tw:flex tw:items-baseline tw:justify-between tw:gap-2">
                            <span style="font-size:.84rem;color:var(--ink-700);">{{ $label }}</span>
                            <span>
                                <b style="color:var(--ink-900);font-variant-numeric:tabular-nums;">{{ $khr($revenueByService[$key]) }}</b>
                                <span class="text-muted ml-2" style="font-size:.76rem;">{{ number_format($share, 1) }}%</span>
                            </span>
                        </div>
                        <div class="svc-bar"><span style="width:{{ max($share, $revenueByService[$key] ? 2 : 0) }}%;background:{{ $bar }};"></span></div>
                    </div>
                </div>
            @endforeach
            <div class="tw:mt-2 tw:flex tw:justify-between tw:border-t tw:border-ink-100 tw:pt-3" style="font-size:.82rem;">
                <span class="text-muted">Total</span><b style="color:var(--ink-900);">{{ $khr($svcTotal) }}</b>
            </div>
        </div>
    </div>

    <div class="panel tw:lg:col-span-2 tw:min-[87.5rem]:col-span-1">
        <div class="panel-head">
            <h6><i class="fas fa-receipt" style="color:var(--info);"></i> Recent Rides</h6>
            <a href="{{ route('admin.rides') }}" style="font-size:.8rem;font-weight:500;">View all <i class="fas fa-arrow-right ml-1" style="font-size:.7rem;"></i></a>
        </div>
        <div class="panel-body tw:pt-0">
            @forelse($latestRides as $ride)
                @php [$fg, $bg] = $rideTones[$ride->status] ?? ['#2563eb', '#dbeafe']; @endphp
                <div class="ride-row">
                    <span class="ride-ico" style="background:{{ $bg }};color:{{ $fg }};" title="{{ ucfirst(str_replace('_', ' ', $ride->status)) }}"><i class="fas fa-car-side"></i></span>
                    <div class="tw:min-w-0 tw:flex-1">
                        <div style="font-weight:600;color:var(--ink-900);font-size:.84rem;">#{{ $ride->id }}
                            <span class="text-muted" style="font-weight:400;">· {{ $ride->passenger?->name ?? '—' }}</span>
                        </div>
                        <div class="text-muted" style="font-size:.76rem;">
                            {{ $ride->distance_km ? number_format($ride->distance_km, 1) . ' km · ' : '' }}{{ $khr($ride->fare) }}
                        </div>
                    </div>
                    <span class="text-muted" style="font-size:.78rem;">{{ $ride->created_at->isToday() ? $ride->created_at->format('H:i') : $ride->created_at->format('d M') }}</span>
                </div>
            @empty
                <div class="empty-state py-4"><div class="empty-icon"><i class="fas fa-car-side"></i></div><h6>No rides yet</h6></div>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Work queues ─────────────────────────────────────────────────────── --}}
<div class="tw:mt-4 tw:grid tw:grid-cols-1 tw:gap-4 tw:lg:grid-cols-2">
    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-user-clock" style="color:var(--warning);"></i> Drivers Awaiting Approval</h6>
            <a href="{{ route('admin.drivers') }}" style="font-size:.8rem;font-weight:500;">View all <i class="fas fa-arrow-right ml-1" style="font-size:.7rem;"></i></a>
        </div>
        <div class="tw:pb-2">
            @forelse($pendingDrivers as $d)
                <div class="queue-row">
                    @if($d->avatar_url)
                        <img src="{{ $d->avatar_url }}" class="avatar-chip" style="object-fit:cover;" alt="">
                    @else
                        <span class="avatar-chip">{{ mb_strtoupper(mb_substr($d->name, 0, 1)) }}</span>
                    @endif
                    <div class="tw:min-w-0 tw:flex-1">
                        <div class="text-truncate" style="font-size:.84rem;font-weight:600;color:var(--ink-900);">{{ $d->name }}</div>
                        <div class="text-muted" style="font-size:.74rem;">Applied {{ $d->created_at->diffForHumans() }}</div>
                    </div>
                    <a href="{{ route('admin.drivers.show', $d) }}" class="btn btn-sm btn-primary">Review</a>
                </div>
            @empty
                <div class="empty-state py-4"><div class="empty-icon"><i class="fas fa-check"></i></div><h6>No pending approvals</h6></div>
            @endforelse
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-headset" style="color:var(--danger);"></i> Open Support Tickets</h6>
            <a href="{{ route('admin.support') }}" style="font-size:.8rem;font-weight:500;">View all <i class="fas fa-arrow-right ml-1" style="font-size:.7rem;"></i></a>
        </div>
        <div class="tw:pb-2">
            @forelse($openTickets as $t)
                <div class="queue-row">
                    <span class="status-dot" style="background:{{ $t->status === 'open' ? '#ef4444' : '#f59e0b' }};"></span>
                    <div class="tw:min-w-0 tw:flex-1">
                        <div class="text-truncate" style="font-size:.84rem;font-weight:600;color:var(--ink-900);">{{ $t->subject }}</div>
                        <div class="text-muted" style="font-size:.74rem;">{{ ucfirst(str_replace('_', ' ', $t->status)) }} · {{ $t->created_at->diffForHumans() }}</div>
                    </div>
                    <span class="badge badge-{{ $t->priority === 'high' ? 'danger' : ($t->priority === 'medium' ? 'warning' : 'secondary') }}">{{ ucfirst($t->priority ?? 'low') }}</span>
                </div>
            @empty
                <div class="empty-state py-4"><div class="empty-icon"><i class="fas fa-face-smile"></i></div><h6>No open tickets</h6></div>
            @endforelse
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const days    = @json(array_keys($revenueChart));
    const revenue = @json(array_values($revenueChart));
    const rides   = @json(array_values($ridesChart));
    const status  = @json(array_values($rideStatus));

    const fmtDay  = d => new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    const fmtKhr  = v => Math.round(v).toLocaleString('en-US') + ' ៛';
    const short   = v => v >= 1e6 ? (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M' : v >= 1e3 ? (v / 1e3).toFixed(0) + 'K' : v;
    const labels  = days.map(fmtDay);
    const many    = days.length > 10;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';
    const tooltip = {
        backgroundColor: '#fff', titleColor: '#64748b', bodyColor: '#0f172a', borderColor: '#e2e8f0', borderWidth: 1,
        padding: 10, displayColors: false, titleFont: { size: 11, weight: 500 }, bodyFont: { size: 13, weight: 700 },
        callbacks: { title: items => new Date(days[items[0].dataIndex] + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) },
    };
    const grid = { color: '#f1f5f9', drawTicks: false };

    // Revenue — violet bars, today highlighted.
    const rc = document.getElementById('revenueChart');
    const g = rc.getContext('2d').createLinearGradient(0, 0, 0, 230);
    g.addColorStop(0, '#6366f1'); g.addColorStop(1, '#8b5cf6');
    new Chart(rc, {
        type: 'bar',
        data: { labels, datasets: [{
            data: revenue, borderRadius: 7, borderSkipped: false, maxBarThickness: 48,
            backgroundColor: revenue.map((_, i) => i === revenue.length - 1 ? g : 'rgba(139, 92, 246, .28)'),
            hoverBackgroundColor: g,
        }] },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { ...tooltip.callbacks, label: c => fmtKhr(c.parsed.y) } } },
            scales: {
                y: { beginAtZero: true, grid, border: { display: false }, ticks: { padding: 8, font: { size: 11 }, callback: short } },
                x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 }, autoSkip: true, maxTicksLimit: many ? 10 : 7, maxRotation: 0 } },
            },
        },
    });

    // Completed rides — green area line, last value labelled.
    const lc = document.getElementById('ridesChart');
    const lg = lc.getContext('2d').createLinearGradient(0, 0, 0, 230);
    lg.addColorStop(0, 'rgba(34, 197, 94, .28)'); lg.addColorStop(1, 'rgba(34, 197, 94, 0)');
    const lastBadge = {
        id: 'lastBadge',
        afterDatasetsDraw(chart) {
            const pt = chart.getDatasetMeta(0).data.at(-1); if (!pt) return;
            const ctx = chart.ctx, text = String(rides.at(-1)), w = ctx.measureText(text).width + 14;
            ctx.save(); ctx.fillStyle = '#16a34a';
            ctx.beginPath(); ctx.roundRect(pt.x - w / 2, pt.y - 30, w, 20, 6); ctx.fill();
            ctx.fillStyle = '#fff'; ctx.font = '600 11px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(text, pt.x, pt.y - 20); ctx.restore();
        },
    };
    new Chart(lc, {
        type: 'line',
        data: { labels, datasets: [{
            data: rides, borderColor: '#22c55e', backgroundColor: lg, fill: true, cubicInterpolationMode: 'monotone', borderWidth: 2.5,
            pointRadius: many ? 0 : 4, pointHoverRadius: 6, pointBackgroundColor: '#fff', pointBorderColor: '#22c55e', pointBorderWidth: 2,
        }] },
        options: {
            maintainAspectRatio: false, layout: { padding: { top: 26, right: 8 } },
            plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { ...tooltip.callbacks, label: c => c.parsed.y + ' rides' } } },
            scales: {
                y: { beginAtZero: true, grid, border: { display: false }, ticks: { padding: 8, precision: 0, font: { size: 11 } } },
                x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 }, autoSkip: true, maxTicksLimit: many ? 8 : 7, maxRotation: 0 } },
            },
        },
        plugins: [lastBadge],
    });

    // Ride status donut.
    const total = status.reduce((a, b) => a + b, 0);
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'In Progress', 'Pending', 'Cancelled'],
            datasets: [{
                data: total ? status : [1], borderWidth: 0, spacing: total ? 2 : 0, borderRadius: total ? 4 : 0,
                backgroundColor: total ? ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444'] : ['#e2e8f0'],
            }],
        },
        options: {
            responsive: false, cutout: '72%',
            plugins: { legend: { display: false }, tooltip: { enabled: !!total, ...tooltip, callbacks: { label: c => c.label + ': ' + c.parsed } } },
        },
    });
})();
</script>
@endpush
