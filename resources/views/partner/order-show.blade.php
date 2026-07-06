@extends('partner.layout')
@section('title', 'Order #' . $delivery->id)
@section('page-title', 'Order #' . $delivery->id)

@push('styles')
<style>
#qr-canvas { border-radius: 8px; }
.timeline-step { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
.timeline-dot { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .65rem; font-weight: 700; color: #fff; margin-top: 2px; }
.timeline-line { border-left: 2px dashed #e2e8f0; margin-left: 11px; padding-left: 23px; margin-top: -8px; padding-top: 8px; margin-bottom: -8px; }
.driver-card { border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; cursor: pointer; transition: border-color .15s; }
.driver-card:hover, .driver-card.selected { border-color: #e63946; background: #fff5f5; }
</style>
@endpush

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <a href="{{ route('partner.orders') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i>Back to Orders
        </a>
    </div>
    <div>
        @php
            $sc = ['created'=>'light','assigned'=>'warning','accepted'=>'info','picked_up'=>'primary',
                   'in_transit'=>'secondary','delivered'=>'success','completed'=>'success','cancelled'=>'danger'];
        @endphp
        @php $statusTextDark = $delivery->status === 'created' ? 'color:#333' : ''; @endphp
        <span class="badge badge-{{ $sc[$delivery->status] ?? 'secondary' }} p-2"
              style="font-size:.85rem; {{ $statusTextDark }}">
            {{ ucfirst(str_replace('_',' ',$delivery->status)) }}
        </span>
    </div>
</div>

<div class="row">
    {{-- Left: Order Detail + Timeline --}}
    <div class="col-md-7">

        {{-- Order Info --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0"><i class="fas fa-box text-primary mr-2"></i>Order Details</h5>
                @if($delivery->partner_reference)
                    <span class="text-muted small">Ref: {{ $delivery->partner_reference }}</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-muted small">Recipient</div>
                        <div class="font-weight-bold">{{ $delivery->recipient_name }}</div>
                        <div class="small">{{ $delivery->recipient_phone }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Package Size</div>
                        <div class="font-weight-bold">{{ ucfirst($delivery->package_size ?? 'Medium') }}</div>
                    </div>
                </div>
                <hr class="my-3">
                <div class="mb-2">
                    <div class="text-muted small"><i class="fas fa-map-marker-alt text-success mr-1"></i>Pickup</div>
                    <div>{{ $delivery->pickup_address }}</div>
                </div>
                <div>
                    <div class="text-muted small"><i class="fas fa-map-marker-alt text-danger mr-1"></i>Dropoff</div>
                    <div>{{ $delivery->dropoff_address }}</div>
                </div>
                @if($delivery->notes)
                <hr class="my-2">
                <div class="text-muted small">Notes: {{ $delivery->notes }}</div>
                @endif
                <hr class="my-3">
                <div class="row text-center">
                    <div class="col-3">
                        <div class="text-muted small">Delivery Fee</div>
                        <div class="font-weight-bold text-success">{{ number_format($delivery->fee) }} ៛</div>
                        <div class="text-muted" style="font-size:.65rem">Auto-calculated</div>
                    </div>
                    <div class="col-3">
                        <div class="text-muted small">Package Value</div>
                        <div class="font-weight-bold">{{ number_format($delivery->package_amount ?? 0) }} ៛</div>
                    </div>
                    <div class="col-3">
                        <div class="text-muted small">Paid By</div>
                        <div class="font-weight-bold">{{ ucfirst($delivery->payment_by ?? 'recipient') }}</div>
                    </div>
                    <div class="col-3">
                        <div class="text-muted small">Payment</div>
                        <div class="font-weight-bold">
                            @php $ps = ['unpaid'=>'danger','paid'=>'success','pending'=>'warning']; @endphp
                            <span class="badge badge-{{ $ps[$delivery->payment_status] ?? 'secondary' }}">
                                {{ ucfirst($delivery->payment_status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Status Timeline --}}
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-route text-warning mr-2"></i>Delivery Timeline</h5></div>
            <div class="card-body">
                @php
                    $steps = [
                        'created'    => ['Created', 'fas fa-plus', '#6b7280', $delivery->created_at],
                        'assigned'   => ['Assigned to Driver', 'fas fa-user-check', '#f59e0b', $delivery->assigned_at ?? null],
                        'accepted'   => ['Driver Accepted', 'fas fa-thumbs-up', '#3b82f6', null],
                        'picked_up'  => ['Picked Up (QR Scanned)', 'fas fa-qrcode', '#8b5cf6', $delivery->pickup_scanned_at ?? null],
                        'in_transit' => ['In Transit (QR Scanned)', 'fas fa-truck', '#0ea5e9', $delivery->delivery_scanned_at ?? null],
                        'delivered'  => ['Delivered', 'fas fa-check', '#10b981', $delivery->completed_at ?? null],
                    ];
                    $order_statuses = ['created','assigned','accepted','picked_up','in_transit','delivered','completed'];
                    $current_idx   = array_search($delivery->status, $order_statuses);
                @endphp
                @foreach($steps as $key => [$label, $icon, $color, $ts])
                    @php
                        $stepIdx  = array_search($key, $order_statuses);
                        $done     = $current_idx !== false && $current_idx >= $stepIdx;
                        $dotBg    = $done ? $color : '#e2e8f0';
                        $iconStyle = $done ? '' : 'color:#9ca3af';
                    @endphp
                <div class="timeline-step">
                    <div class="timeline-dot" style="background: {{ $dotBg }}">
                        <i class="{{ $icon }}" style="{{ $iconStyle }}"></i>
                    </div>
                    <div>
                        <div class="{{ $done ? 'font-weight-bold' : 'text-muted' }}">{{ $label }}</div>
                        @if($ts)<small class="text-muted">{{ \Carbon\Carbon::parse($ts)->format('d M Y H:i') }}</small>@endif
                    </div>
                </div>
                @endforeach
                @if($delivery->status === 'cancelled')
                <div class="timeline-step">
                    <div class="timeline-dot" style="background:#ef4444"><i class="fas fa-times"></i></div>
                    <div>
                        <div class="font-weight-bold text-danger">Cancelled</div>
                        @if($delivery->cancellation_reason)<small class="text-muted">{{ $delivery->cancellation_reason }}</small>@endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Driver Info --}}
        @if($delivery->driver)
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-motorcycle text-info mr-2"></i>Assigned Driver</h5></div>
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-light"
                     style="width:50px;height:50px;font-size:1.4rem;flex-shrink:0">
                    <i class="fas fa-user text-muted"></i>
                </div>
                <div>
                    <div class="font-weight-bold">{{ $delivery->driver->name }}</div>
                    <div class="small text-muted">{{ $delivery->driver->phone }}</div>
                    @if($delivery->driver->rating)
                    <div class="small">
                        <i class="fas fa-star text-warning"></i> {{ number_format($delivery->driver->rating,1) }}
                    </div>
                    @endif
                    @if($delivery->assignment_type)
                    <span class="badge badge-{{ $delivery->assignment_type==='auto'?'info':'secondary' }} mt-1">
                        {{ $delivery->assignment_type === 'auto' ? 'Auto-assigned' : 'Manually assigned' }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Cancel Button --}}
        @if(in_array($delivery->status, ['created','assigned','accepted']))
        <div class="card border-danger">
            <div class="card-body">
                <h6 class="text-danger"><i class="fas fa-times-circle mr-2"></i>Cancel Order</h6>
                <form method="POST" action="{{ route('partner.orders.cancel', $delivery) }}"
                      onsubmit="return confirm('Cancel this order?')">
                    @csrf
                    <div class="form-group">
                        <input type="text" name="reason" class="form-control form-control-sm"
                               placeholder="Reason for cancellation (optional)">
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Order</button>
                </form>
            </div>
        </div>
        @endif
    </div>

    {{-- Right: QR Code + Assign Driver --}}
    <div class="col-md-5">

        {{-- QR Code --}}
        <div class="card mb-3 text-center">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-qrcode mr-2"></i>QR Code</h5></div>
            <div class="card-body">
                <canvas id="qr-canvas" style="max-width:200px;width:100%"></canvas>
                <div class="mt-2">
                    <code class="small text-muted d-block">AUTORIDE:DELIVERY:{{ $delivery->qr_token }}</code>
                </div>
                @if($delivery->pickup_scanned_at || $delivery->delivery_scanned_at)
                <div class="mt-2">
                    @if($delivery->pickup_scanned_at)
                        <div class="badge badge-primary py-1 px-2">
                            <i class="fas fa-check mr-1"></i>Pickup scanned {{ $delivery->pickup_scanned_at->format('d M H:i') }}
                        </div>
                    @endif
                    @if($delivery->delivery_scanned_at)
                        <div class="badge badge-success py-1 px-2 mt-1">
                            <i class="fas fa-check-double mr-1"></i>Delivery scanned {{ $delivery->delivery_scanned_at->format('d M H:i') }}
                        </div>
                    @endif
                </div>
                @endif
                <button class="btn btn-sm btn-outline-secondary mt-3" onclick="downloadQR()">
                    <i class="fas fa-download mr-1"></i>Download QR
                </button>
            </div>
        </div>

        {{-- Assign Driver --}}
        @if(in_array($delivery->status, ['created','assigned']) && count($nearbyDrivers) > 0)
        @php $assignUrl = route('partner.orders.assign', $delivery->id); @endphp
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-plus text-success mr-2"></i>Assign Driver</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ $assignUrl }}" id="assign-form">
                    @csrf
                    <input type="hidden" name="driver_id" id="selected-driver-id">
                    <div style="max-height:320px;overflow-y:auto;" class="pr-1">
                        @foreach($nearbyDrivers as $d)
                        <div class="driver-card mb-2" onclick="selectDriver({{ $d['id'] }}, this)">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="font-weight-bold">{{ $d['name'] }}</div>
                                    <div class="small text-muted">{{ $d['phone'] }}</div>
                                    @if($d['rating'])
                                    <div class="small"><i class="fas fa-star text-warning"></i> {{ number_format($d['rating'],1) }}</div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="small font-weight-bold text-primary">{{ number_format($d['distance_km'],1) }} km</div>
                                    @if($d['eta_minutes'])
                                    <div class="small text-muted">~{{ $d['eta_minutes'] }} min ETA</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-success btn-block mt-3" id="assign-btn" disabled>
                        <i class="fas fa-check mr-1"></i>Assign Selected Driver
                    </button>
                </form>
            </div>
        </div>
        @elseif(in_array($delivery->status, ['created','assigned']))
        <div class="card">
            <div class="card-body text-center text-muted py-4">
                <i class="fas fa-search fa-2x mb-2 d-block"></i>
                No nearby drivers found at the moment.
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
// Minimal QR code generator (no external CDN)
(function() {
    const QR_PAYLOAD = "AUTORIDE:DELIVERY:{{ $delivery->qr_token }}";

    // Use a simple canvas-based placeholder if qrcode.js unavailable
    // We'll use a data URI approach via the qrcode-generator lib inlined approach
    // Since no CDN allowed, we generate a simple visual placeholder and show the code
    function generateQR(text, canvas) {
        // Simple QR visual via a grid pattern derived from text hash
        // In production, use a proper offline QR lib
        const ctx = canvas.getContext('2d');
        const size = 200;
        canvas.width = size;
        canvas.height = size;

        // Draw white background
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, size, size);

        // Draw border
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 4;
        ctx.strokeRect(2, 2, size - 4, size - 4);

        // Draw finder patterns (corners)
        function finder(x, y) {
            ctx.fillStyle = '#000';
            ctx.fillRect(x, y, 49, 49);
            ctx.fillStyle = '#fff';
            ctx.fillRect(x+7, y+7, 35, 35);
            ctx.fillStyle = '#000';
            ctx.fillRect(x+14, y+14, 21, 21);
        }
        finder(10, 10); finder(size-59, 10); finder(10, size-59);

        // Hash text into pseudorandom cells
        let hash = 0;
        for (let i = 0; i < text.length; i++) hash = ((hash << 5) - hash) + text.charCodeAt(i);

        ctx.fillStyle = '#000';
        const modules = 21;
        const mSize = Math.floor((size - 40) / modules);
        for (let r = 0; r < modules; r++) {
            for (let c = 0; c < modules; c++) {
                // Skip finder pattern areas
                if ((r < 7 && c < 7) || (r < 7 && c > modules-8) || (r > modules-8 && c < 7)) continue;
                const bit = (hash >> ((r * modules + c) % 32)) & 1;
                if (bit) ctx.fillRect(20 + c * mSize, 20 + r * mSize, mSize - 1, mSize - 1);
            }
        }

        // Center label
        ctx.fillStyle = 'rgba(0,0,0,0.6)';
        ctx.font = '9px monospace';
        ctx.textAlign = 'center';
        ctx.fillText('#{{ $delivery->id }}', size/2, size - 14);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('qr-canvas');
        if (canvas) generateQR(QR_PAYLOAD, canvas);
    });

    window.downloadQR = function() {
        const canvas = document.getElementById('qr-canvas');
        const a = document.createElement('a');
        a.download = 'delivery-qr-{{ $delivery->id }}.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
    };
})();

function selectDriver(id, el) {
    document.querySelectorAll('.driver-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selected-driver-id').value = id;
    document.getElementById('assign-btn').disabled = false;
}
</script>
@endpush
