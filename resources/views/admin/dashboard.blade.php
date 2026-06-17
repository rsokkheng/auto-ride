@extends('admin.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<style>
.kpi-card { border-radius:12px; border:none; overflow:hidden; }
.kpi-inner { padding:18px 20px; display:flex; align-items:center; gap:16px; }
.kpi-icon { width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem; flex-shrink:0; }
.kpi-label { font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;opacity:.75;margin-bottom:2px; }
.kpi-value { font-size:1.6rem;font-weight:800;line-height:1; }
.kpi-sub   { font-size:.75rem;margin-top:4px; }
.kpi-positive { color:#22c55e; }
.kpi-negative { color:#ef4444; }
.section-label { font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#64748b;margin:24px 0 10px; }
.pending-badge { display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;font-size:.7rem;font-weight:700; }
.chart-card { border-radius:12px;border:1px solid #e2e8f0;background:#fff; }
.chart-card .card-header { background:transparent;border-bottom:1px solid #f1f5f9;padding:14px 20px; }
.chart-card .card-header h6 { font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:0; }
.activity-row { display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f8fafc; }
.activity-row:last-child { border-bottom:none; }
.status-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
</style>
@endpush

@section('content')

{{-- ── Top KPI row ──────────────────────────────────────────────────────── --}}
<div class="row g-3">
    {{-- Today's Revenue --}}
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card" style="background:linear-gradient(135deg,#1a73e8,#1557b0);">
            <div class="kpi-inner text-white">
                <div class="kpi-icon" style="background:rgba(255,255,255,.2);">
                    <i class="fas fa-coins"></i>
                </div>
                <div>
                    <div class="kpi-label" style="color:rgba(255,255,255,.8)">Today's Revenue</div>
                    <div class="kpi-value">{{ number_format($metrics['revenue_today']) }} ៛</div>
                    @if($metrics['revenue_growth'] !== null)
                    <div class="kpi-sub">
                        @if($metrics['revenue_growth'] >= 0)
                            <i class="fas fa-arrow-up mr-1"></i>{{ $metrics['revenue_growth'] }}% vs yesterday
                        @else
                            <i class="fas fa-arrow-down mr-1"></i>{{ abs($metrics['revenue_growth']) }}% vs yesterday
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Active Rides --}}
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card" style="background:linear-gradient(135deg,#10b981,#059669);">
            <div class="kpi-inner text-white">
                <div class="kpi-icon" style="background:rgba(255,255,255,.2);">
                    <i class="fas fa-route"></i>
                </div>
                <div>
                    <div class="kpi-label" style="color:rgba(255,255,255,.8)">Live Rides</div>
                    <div class="kpi-value">{{ $metrics['rides_active'] }}</div>
                    <div class="kpi-sub" style="opacity:.85">{{ $metrics['rides_today'] }} started today</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Online Drivers --}}
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            <div class="kpi-inner text-white">
                <div class="kpi-icon" style="background:rgba(255,255,255,.2);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div>
                    <div class="kpi-label" style="color:rgba(255,255,255,.8)">Online Drivers</div>
                    <div class="kpi-value">{{ $metrics['drivers_online'] }}</div>
                    <div class="kpi-sub" style="opacity:.85">of {{ $metrics['drivers'] }} total</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Week Revenue --}}
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
            <div class="kpi-inner text-white">
                <div class="kpi-icon" style="background:rgba(255,255,255,.2);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <div class="kpi-label" style="color:rgba(255,255,255,.8)">7-Day Revenue</div>
                    <div class="kpi-value">{{ number_format($metrics['revenue_week']) }} ៛</div>
                    <div class="kpi-sub" style="opacity:.85">Last 7 days</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Secondary KPIs ───────────────────────────────────────────────────── --}}
<div class="row mt-3">
    @foreach([
        ['label'=>'Passengers',        'value'=> $metrics['users'],              'icon'=>'fas fa-users',          'color'=>'#0ea5e9'],
        ['label'=>'Today Deliveries',  'value'=> $metrics['deliveries_today'],   'icon'=>'fas fa-box',            'color'=>'#8b5cf6'],
        ['label'=>'Open Tickets',      'value'=> $metrics['support_open'],       'icon'=>'fas fa-headset',        'color'=>'#ef4444'],
        ['label'=>'Pending Payouts',   'value'=> $metrics['withdrawals_pending'],'icon'=>'fas fa-money-check-alt','color'=>'#f59e0b'],
        ['label'=>'Pending Drivers',   'value'=> $metrics['drivers_pending'],    'icon'=>'fas fa-user-clock',     'color'=>'#10b981'],
        ['label'=>'Marketplace Items', 'value'=> $metrics['marketplace'],        'icon'=>'fas fa-store',          'color'=>'#64748b'],
    ] as $k)
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card h-100" style="border-radius:10px;border:1px solid #f1f5f9;">
            <div class="card-body p-3 text-center">
                <div style="font-size:1.6rem;color:{{ $k['color'] }};margin-bottom:4px;">
                    <i class="{{ $k['icon'] }}"></i>
                </div>
                <div style="font-size:1.3rem;font-weight:800;color:#1e293b;">{{ $k['value'] }}</div>
                <div style="font-size:.72rem;color:#94a3b8;font-weight:600;">{{ $k['label'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── Charts ───────────────────────────────────────────────────────────── --}}
<div class="row mt-2">
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="fas fa-chart-bar mr-2 text-primary"></i> Revenue (Last 7 Days)</h6>
                <small class="text-muted">KHR ៛</small>
            </div>
            <div class="card-body p-3">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header">
                <h6><i class="fas fa-route mr-2 text-success"></i> Completed Rides / Day</h6>
            </div>
            <div class="card-body p-3">
                <canvas id="ridesChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Pending Actions + Recent Activity ──────────────────────────────── --}}
<div class="row mt-3">

    {{-- Pending driver approvals --}}
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between">
                <h6><i class="fas fa-user-clock mr-2 text-warning"></i> Drivers Awaiting Approval</h6>
                <a href="{{ route('admin.drivers') }}" class="btn btn-xs btn-outline-warning" style="font-size:.72rem;padding:2px 8px;">View All</a>
            </div>
            <div class="card-body p-0">
                @forelse($pendingDrivers as $d)
                <div class="activity-row px-3">
                    @if($d->avatar_url)
                        <img src="{{ $d->avatar_url }}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                    @else
                        <div style="width:32px;height:32px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:700;color:#64748b;font-size:.85rem;">{{ strtoupper(substr($d->name,0,1)) }}</div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div style="font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $d->name }}</div>
                        <div style="font-size:.72rem;color:#94a3b8;">{{ $d->created_at->diffForHumans() }}</div>
                    </div>
                    <a href="{{ route('admin.drivers.show', $d) }}" class="btn btn-xs btn-primary" style="font-size:.7rem;padding:3px 8px;">Review</a>
                </div>
                @empty
                <div class="text-center py-4 text-muted" style="font-size:.82rem;">
                    <i class="fas fa-check-circle text-success d-block mb-1" style="font-size:1.4rem;"></i>
                    No pending approvals
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Open support tickets --}}
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between">
                <h6><i class="fas fa-headset mr-2 text-danger"></i> Open Support Tickets</h6>
                <a href="{{ route('admin.support') }}" class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 8px;">View All</a>
            </div>
            <div class="card-body p-0">
                @forelse($openTickets as $t)
                <div class="activity-row px-3">
                    <div class="status-dot" style="background:{{ $t->status==='open'?'#ef4444':'#f59e0b' }};"></div>
                    <div class="flex-1 min-w-0">
                        <div style="font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $t->subject }}</div>
                        <div style="font-size:.72rem;color:#94a3b8;">{{ ucfirst($t->status) }} · {{ $t->created_at->diffForHumans() }}</div>
                    </div>
                    <span class="badge badge-{{ $t->priority==='high'?'danger':($t->priority==='medium'?'warning':'secondary') }}" style="font-size:.68rem;">
                        {{ ucfirst($t->priority ?? 'low') }}
                    </span>
                </div>
                @empty
                <div class="text-center py-4 text-muted" style="font-size:.82rem;">
                    <i class="fas fa-smile text-success d-block mb-1" style="font-size:1.4rem;"></i>
                    No open tickets
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent rides --}}
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between">
                <h6><i class="fas fa-route mr-2 text-info"></i> Recent Rides</h6>
                <a href="{{ route('admin.rides') }}" class="btn btn-xs btn-outline-info" style="font-size:.72rem;padding:2px 8px;">View All</a>
            </div>
            <div class="card-body p-0">
                @foreach($latestRides as $ride)
                @php
                    $sdot = ['requested'=>'#94a3b8','accepted'=>'#3b82f6','in_progress'=>'#10b981','completed'=>'#22c55e','cancelled'=>'#ef4444'];
                @endphp
                <div class="activity-row px-3">
                    <div class="status-dot" style="background:{{ $sdot[$ride->status] ?? '#94a3b8' }};"></div>
                    <div class="flex-1 min-w-0">
                        <div style="font-size:.8rem;font-weight:600;">#{{ $ride->id }} · {{ $ride->passenger?->name ?? '—' }}</div>
                        <div style="font-size:.7rem;color:#94a3b8;">{{ \Illuminate\Support\Str::limit($ride->pickup_address,28) }}</div>
                    </div>
                    <div style="font-size:.75rem;color:#64748b;white-space:nowrap;">{{ number_format($ride->fare) }} ៛</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>

{{-- ── Quick Links ──────────────────────────────────────────────────────── --}}
<div class="card mt-3 chart-card">
    <div class="card-header"><h6><i class="fas fa-bolt mr-2 text-warning"></i> Quick Actions</h6></div>
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2">
            @foreach([
                ['route'=>'admin.users',           'label'=>'Users',          'icon'=>'fas fa-users',           'color'=>'info'],
                ['route'=>'admin.drivers',          'label'=>'Drivers',        'icon'=>'fas fa-user-tie',        'color'=>'success'],
                ['route'=>'admin.rides',            'label'=>'Rides',          'icon'=>'fas fa-route',           'color'=>'primary'],
                ['route'=>'admin.deliveries',       'label'=>'Deliveries',     'icon'=>'fas fa-box',             'color'=>'purple'],
                ['route'=>'admin.fare-management',  'label'=>'Fare Settings',  'icon'=>'fas fa-sliders-h',       'color'=>'warning'],
                ['route'=>'admin.surge-zones',      'label'=>'Surge Zones',    'icon'=>'fas fa-bolt',            'color'=>'danger'],
                ['route'=>'admin.banners',          'label'=>'Banners',        'icon'=>'fas fa-images',          'color'=>'secondary'],
                ['route'=>'admin.withdrawals',      'label'=>'Payouts',        'icon'=>'fas fa-money-check-alt', 'color'=>'dark'],
                ['route'=>'admin.support',          'label'=>'Support',        'icon'=>'fas fa-headset',         'color'=>'danger'],
                ['route'=>'admin.transactions',     'label'=>'Transactions',   'icon'=>'fas fa-exchange-alt',    'color'=>'info'],
            ] as $ql)
            <a href="{{ route($ql['route']) }}" class="btn btn-sm btn-outline-{{ $ql['color'] }}" style="border-radius:8px;">
                <i class="{{ $ql['icon'] }} mr-1"></i> {{ $ql['label'] }}
            </a>
            @endforeach
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const labels  = @json(array_keys($revenueChart));
const revenue = @json(array_values($revenueChart));
const rides   = @json(array_values($ridesChart));

// Revenue bar chart
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Revenue (KHR)',
            data: revenue,
            backgroundColor: 'rgba(26,115,232,.75)',
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                ticks: { callback: v => (v/1000).toFixed(0)+'K ៛', font:{size:10} },
                grid: { color:'#f1f5f9' },
            },
            x: { grid: { display: false }, ticks:{ font:{size:10} } }
        }
    }
});

// Rides doughnut/line
new Chart(document.getElementById('ridesChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Completed rides',
            data: rides,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#10b981',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid:{ color:'#f1f5f9' }, ticks:{ font:{size:10} } },
            x: { grid:{ display:false }, ticks:{ font:{size:9} } }
        }
    }
});
</script>
@endpush
