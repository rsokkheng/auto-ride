@extends('partner.layout')
@section('title', 'Order #' . $delivery->id)
@section('page-title', 'Order #' . $delivery->id)

@push('styles')
<style>
.timeline-step + .timeline-step { margin-top: 1rem; }
.driver-card { cursor: pointer; transition: border-color .15s, background .15s; }
.driver-card.selected { border-color: #e63946 !important; background: #fff5f5; }
</style>
@endpush

@section('content')

{{-- ── Back + Status bar ─────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <a href="{{ route('partner.orders') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Orders
    </a>
    @php
    $sc = ['created'=>'secondary','assigned'=>'warning','accepted'=>'info','picked_up'=>'primary',
           'in_transit'=>'dark','delivered'=>'success','completed'=>'success','cancelled'=>'danger'];
    @endphp
    <span class="badge bg-{{ $sc[$delivery->status] ?? 'secondary' }} fs-6 px-3 py-2">
        {{ ucfirst(str_replace('_',' ',$delivery->status)) }}
    </span>
</div>

<div class="row g-4">

    {{-- ── LEFT: Details + Timeline ────────────────────────────── --}}
    <div class="col-lg-7">

        {{-- Order Info --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-slate-800 flex items-center gap-2 mb-0">
                    <i class="fas fa-box text-blue-500"></i> Order #{{ $delivery->id }}
                </h2>
                @if($delivery->partner_reference)
                    <span class="text-xs text-slate-400 font-medium">Ref: {{ $delivery->partner_reference }}</span>
                @endif
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6">
                    <p class="text-xs text-slate-400 mb-0.5">Recipient</p>
                    <p class="fw-semibold mb-0">{{ $delivery->recipient_name }}</p>
                    <p class="small text-muted mb-0">{{ $delivery->recipient_phone }}</p>
                </div>
                <div class="col-6">
                    <p class="text-xs text-slate-400 mb-0.5">Package Size</p>
                    <p class="fw-semibold mb-0">{{ ucfirst(str_replace('_',' ',$delivery->package_size ?? 'Medium')) }}</p>
                    @if(isset($delivery->service_option))
                    <span class="badge bg-{{ $delivery->service_option === 'express' ? 'danger' : 'secondary' }} mt-1">
                        {{ ucfirst($delivery->service_option) }}
                    </span>
                    @endif
                </div>
            </div>

            <div class="rounded-xl p-3 mb-3" style="background:#f0fdf4;border:1px solid #d1fae5">
                <p class="text-xs text-slate-500 mb-1"><i class="fas fa-map-marker-alt text-emerald-500 me-1"></i>Pickup</p>
                <p class="small fw-semibold mb-0">{{ $delivery->pickup_address }}</p>
            </div>
            <div class="rounded-xl p-3 mb-4" style="background:#fff5f5;border:1px solid #fecaca">
                <p class="text-xs text-slate-500 mb-1"><i class="fas fa-map-marker-alt text-red-500 me-1"></i>Dropoff</p>
                <p class="small fw-semibold mb-0">{{ $delivery->dropoff_address }}</p>
            </div>

            @if($delivery->notes)
            <div class="rounded-xl p-3 mb-4 bg-amber-50 border border-amber-100">
                <p class="text-xs text-slate-400 mb-0.5">Notes for driver</p>
                <p class="small mb-0">{{ $delivery->notes }}</p>
            </div>
            @endif

            <div class="grid grid-cols-4 gap-3 text-center pt-3 border-t border-slate-100">
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Delivery Fee</p>
                    <p class="fw-bold text-emerald-600 mb-0">{{ number_format($delivery->fee) }} ៛</p>
                    <p class="text-muted" style="font-size:.65rem">Auto-calculated</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Package Value</p>
                    <p class="fw-bold mb-0">{{ number_format($delivery->package_amount ?? 0) }} ៛</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Paid By</p>
                    <p class="fw-bold mb-0">{{ ucfirst($delivery->payment_by ?? 'recipient') }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Payment</p>
                    @php $ps = ['unpaid'=>'danger','paid'=>'success','pending'=>'warning']; @endphp
                    <span class="badge bg-{{ $ps[$delivery->payment_status ?? 'unpaid'] ?? 'secondary' }}">
                        {{ ucfirst($delivery->payment_status ?? 'unpaid') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-4">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2 mb-5">
                <i class="fas fa-route text-amber-500"></i> Delivery Timeline
            </h3>
            @php
            /*  [label, icon, dot-color, row-bg, text-color, timestamp] */
            $steps = [
                'created'    => ['Order Created',            'fas fa-plus-circle',  '#64748b', '#f8fafc', '#374151', $delivery->created_at],
                'assigned'   => ['Assigned to Driver',       'fas fa-user-check',   '#f59e0b', '#fffbeb', '#92400e', $delivery->assigned_at ?? null],
                'accepted'   => ['Driver Accepted',          'fas fa-thumbs-up',    '#3b82f6', '#eff6ff', '#1e40af', null],
                'picked_up'  => ['Picked Up — QR Scanned',   'fas fa-qrcode',       '#8b5cf6', '#f5f3ff', '#5b21b6', $delivery->pickup_scanned_at ?? null],
                'in_transit' => ['In Transit — QR Scanned',  'fas fa-truck',        '#0ea5e9', '#f0f9ff', '#0c4a6e', $delivery->delivery_scanned_at ?? null],
                'delivered'  => ['Delivered',                'fas fa-check-circle', '#10b981', '#f0fdf4', '#065f46', $delivery->completed_at ?? null],
            ];
            $statusOrder = ['created','assigned','accepted','picked_up','in_transit','delivered','completed'];
            $curIdx      = array_search($delivery->status, $statusOrder);
            @endphp

            <div class="relative">
                {{-- Vertical connector line --}}
                <div class="absolute bg-slate-200"
                     style="left:17px;top:18px;bottom:18px;width:2px;z-index:0"></div>

                <div class="space-y-2" style="position:relative;z-index:1">
                @foreach($steps as $key => [$label, $icon, $dotColor, $rowBg, $textColor, $ts])
                @php
                    $stepIdx = array_search($key, $statusOrder);
                    $done    = $curIdx !== false && $curIdx >= $stepIdx;
                    $current = $curIdx === $stepIdx && $delivery->status !== 'cancelled';
                @endphp
                <div class="flex items-center gap-3 rounded-xl px-3 py-2"
                     style="{{ $done ? 'background:'.$rowBg.';border:1.5px solid '.($current ? $dotColor : 'transparent') : 'background:transparent;border:1.5px solid transparent' }}">

                    <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 shadow-sm"
                         style="background:{{ $done ? $dotColor : '#e2e8f0' }};min-width:2.25rem">
                        <i class="{{ $icon }} text-white" style="font-size:.8rem"></i>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold leading-tight"
                                  style="color:{{ $done ? $textColor : '#cbd5e1' }}">{{ $label }}</span>
                            @if($current)
                                <span class="badge rounded-pill text-white px-2"
                                      style="font-size:.6rem;background:{{ $dotColor }}">Now</span>
                            @elseif($done)
                                <i class="fas fa-check" style="font-size:.6rem;color:{{ $dotColor }}"></i>
                            @endif
                        </div>
                        @if($ts)
                            <p class="mb-0 text-xs text-slate-400 mt-0.5">
                                {{ \Carbon\Carbon::parse($ts)->format('d M Y, H:i') }}
                            </p>
                        @elseif(!$done)
                            <p class="mb-0 text-xs mt-0.5" style="color:#cbd5e1">Waiting…</p>
                        @endif
                    </div>
                </div>
                @endforeach

                @if($delivery->status === 'cancelled')
                <div class="flex items-center gap-3 rounded-xl px-3 py-2"
                     style="background:#fef2f2;border:1.5px solid #ef4444">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 shadow-sm bg-red-500"
                         style="min-width:2.25rem">
                        <i class="fas fa-times text-white" style="font-size:.8rem"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-red-700">Order Cancelled</span>
                            <span class="badge rounded-pill bg-red-500 text-white px-2" style="font-size:.6rem">Now</span>
                        </div>
                        @if($delivery->cancellation_reason)
                            <p class="mb-0 text-xs text-red-400 mt-0.5">{{ $delivery->cancellation_reason }}</p>
                        @endif
                    </div>
                </div>
                @endif
                </div>
            </div>
        </div>

        {{-- Driver info --}}
        @if($delivery->driver)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-4">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2 mb-4">
                <i class="fas fa-motorcycle text-sky-500"></i> Assigned Driver
            </h3>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-slate-400 text-lg"></i>
                </div>
                <div>
                    <p class="fw-bold mb-0">{{ $delivery->driver->name }}</p>
                    <p class="small text-muted mb-0">{{ $delivery->driver->phone }}</p>
                    @if($delivery->driver->rating)
                    <p class="small mb-0"><i class="fas fa-star text-warning"></i> {{ number_format($delivery->driver->rating,1) }}</p>
                    @endif
                    @if($delivery->assignment_type)
                    <span class="badge bg-{{ $delivery->assignment_type === 'auto' ? 'info' : 'secondary' }} mt-1">
                        {{ $delivery->assignment_type === 'auto' ? 'Auto-assigned' : 'Manual' }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Cancel --}}
        @if(in_array($delivery->status, ['created','assigned','accepted']))
        <div class="bg-white rounded-2xl shadow-sm border border-red-200 p-5">
            <h3 class="font-semibold text-red-600 flex items-center gap-2 mb-3">
                <i class="fas fa-times-circle"></i> Cancel Order
            </h3>
            <form method="POST" action="{{ route('partner.orders.cancel', $delivery) }}"
                  onsubmit="return confirm('Cancel this order?')">
                @csrf
                <div class="input-group">
                    <input type="text" name="reason" class="form-control form-control-sm"
                           placeholder="Reason for cancellation (optional)">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Order</button>
                </div>
            </form>
        </div>
        @endif
    </div>

    {{-- ── RIGHT: QR + Assign Driver ────────────────────────────── --}}
    <div class="col-lg-5">

        {{-- QR Code --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 text-center mb-4">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2 justify-center mb-4">
                <i class="fas fa-qrcode text-slate-400"></i> QR Code
            </h3>
            <canvas id="qr-canvas" class="mx-auto rounded-xl" style="max-width:200px;width:100%"></canvas>
            <p class="mt-2 font-mono text-xs text-slate-400">AUTORIDE:DELIVERY:{{ $delivery->qr_token }}</p>

            @if($delivery->pickup_scanned_at || $delivery->delivery_scanned_at)
            <div class="mt-2 space-y-1">
                @if($delivery->pickup_scanned_at)
                    <span class="badge bg-primary d-block py-1 px-2">
                        <i class="fas fa-check me-1"></i>Pickup scanned {{ $delivery->pickup_scanned_at->format('d M H:i') }}
                    </span>
                @endif
                @if($delivery->delivery_scanned_at)
                    <span class="badge bg-success d-block py-1 px-2 mt-1">
                        <i class="fas fa-check-double me-1"></i>Delivery scanned {{ $delivery->delivery_scanned_at->format('d M H:i') }}
                    </span>
                @endif
            </div>
            @endif

            <button class="btn btn-sm btn-outline-secondary mt-3" onclick="downloadQR()">
                <i class="fas fa-download me-1"></i>Download QR
            </button>
        </div>

        {{-- Share tracking link --}}
        @if($delivery->tracking_url)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-4">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2 mb-3">
                <i class="fas fa-share-nodes text-emerald-500"></i> Tracking Link
            </h3>
            <p class="text-muted mb-2" style="font-size:.78rem">
                Share this with the recipient — no login needed. Shows live driver location and the dropoff point.
            </p>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control font-monospace" id="track-url"
                       value="{{ $delivery->tracking_url }}" readonly style="font-size:.72rem">
                <button class="btn btn-outline-secondary" type="button" onclick="copyTrackUrl()">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <div class="d-flex gap-2 mt-3">
                <a class="btn btn-sm btn-outline-primary flex-fill" target="_blank" rel="noopener"
                   href="{{ $delivery->tracking_url }}">
                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Open
                </a>
                @if($delivery->recipient_phone)
                <a class="btn btn-sm btn-outline-success flex-fill"
                   href="sms:{{ $delivery->recipient_phone }}?&body={{ rawurlencode('Track your delivery: ' . $delivery->tracking_url) }}">
                    <i class="fas fa-comment-sms me-1"></i>SMS
                </a>
                @endif
            </div>
            @unless($delivery->share_active)
                <p class="text-warning mb-0 mt-2" style="font-size:.72rem">
                    <i class="fas fa-eye-slash me-1"></i>Live location sharing is turned off for this order.
                </p>
            @endunless
        </div>
        @endif

        {{-- Assign Driver --}}
        @if(in_array($delivery->status, ['created','assigned']))
        @php $assignUrl = route('partner.orders.assign', $delivery->id); @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2 mb-4">
                <i class="fas fa-user-plus text-emerald-500"></i>
                @if(count($nearbyDrivers) > 0) Assign Driver @else Nearby Drivers @endif
            </h3>
            @if(count($nearbyDrivers) > 0)
            <form method="POST" action="{{ $assignUrl }}" id="assign-form">
                @csrf
                <input type="hidden" name="driver_id" id="selected-driver-id">
                <div style="max-height:320px;overflow-y:auto" class="space-y-2 mb-4">
                    @foreach($nearbyDrivers as $d)
                    <div class="driver-card rounded-xl border-2 border-slate-200 p-3"
                         onclick="selectDriver({{ $d['id'] }}, this)">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="fw-semibold mb-0 small">{{ $d['name'] }}</p>
                                <p class="text-muted mb-0" style="font-size:.75rem">{{ $d['phone'] }}</p>
                                @if($d['rating'])
                                <p class="mb-0" style="font-size:.75rem">
                                    <i class="fas fa-star text-warning"></i> {{ number_format($d['rating'],1) }}
                                </p>
                                @endif
                            </div>
                            <div class="text-end">
                                <p class="fw-bold text-primary mb-0 small">{{ number_format($d['distance_km'],1) }} km</p>
                                @if($d['eta_minutes'])
                                <p class="text-muted mb-0" style="font-size:.75rem">~{{ $d['eta_minutes'] }} min</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-success w-100" id="assign-btn" disabled>
                    <i class="fas fa-check me-1"></i>Assign Selected Driver
                </button>
            </form>
            @else
            <div class="text-center text-muted py-6">
                <i class="fas fa-search fa-2x mb-2 d-block text-slate-300"></i>
                No nearby drivers found at the moment.
            </div>
            @endif
        </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    const QR_PAYLOAD = "AUTORIDE:DELIVERY:{{ $delivery->qr_token }}";
    function generateQR(text, canvas) {
        const ctx = canvas.getContext('2d');
        const size = 200;
        canvas.width = canvas.height = size;
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, size, size);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 4;
        ctx.strokeRect(2, 2, size - 4, size - 4);
        function finder(x, y) {
            ctx.fillStyle = '#000'; ctx.fillRect(x, y, 49, 49);
            ctx.fillStyle = '#fff'; ctx.fillRect(x+7, y+7, 35, 35);
            ctx.fillStyle = '#000'; ctx.fillRect(x+14, y+14, 21, 21);
        }
        finder(10, 10); finder(size-59, 10); finder(10, size-59);
        let hash = 0;
        for (let i = 0; i < text.length; i++) hash = ((hash << 5) - hash) + text.charCodeAt(i);
        ctx.fillStyle = '#000';
        const modules = 21;
        const mSize = Math.floor((size - 40) / modules);
        for (let r = 0; r < modules; r++) {
            for (let c = 0; c < modules; c++) {
                if ((r < 7 && c < 7) || (r < 7 && c > modules-8) || (r > modules-8 && c < 7)) continue;
                if ((hash >> ((r * modules + c) % 32)) & 1) ctx.fillRect(20 + c*mSize, 20 + r*mSize, mSize-1, mSize-1);
            }
        }
        ctx.fillStyle = 'rgba(0,0,0,0.5)';
        ctx.font = '9px monospace';
        ctx.textAlign = 'center';
        ctx.fillText('#{{ $delivery->id }}', size/2, size - 14);
    }
    document.addEventListener('DOMContentLoaded', function() {
        const c = document.getElementById('qr-canvas');
        if (c) generateQR(QR_PAYLOAD, c);
    });
    window.downloadQR = function() {
        const a = document.createElement('a');
        a.download = 'delivery-qr-{{ $delivery->id }}.png';
        a.href = document.getElementById('qr-canvas').toDataURL('image/png');
        a.click();
    };
})();

function copyTrackUrl() {
    const input = document.getElementById('track-url');
    if (! input) return;
    input.select();
    const done = () => {
        const btn = input.nextElementSibling;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => btn.innerHTML = original, 1500);
    };
    // navigator.clipboard needs HTTPS or localhost — fall back to execCommand.
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(done);
    } else {
        document.execCommand('copy');
        done();
    }
}

function selectDriver(id, el) {
    document.querySelectorAll('.driver-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selected-driver-id').value = id;
    document.getElementById('assign-btn').disabled = false;
}
</script>
@endpush
