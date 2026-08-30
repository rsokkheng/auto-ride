@php
    $isMoving = ($delivery['service_type'] ?? 'delivery') === 'moving';
    $label    = $isMoving ? 'Moving' : 'Delivery';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track {{ $label }} — ROTEH APP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; color: #222; }

        .header {
            background: #2e7d32;
            color: white;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .header h1 { font-size: 17px; font-weight: 600; }
        .header p  { font-size: 12px; opacity: 0.8; margin-top: 2px; }

        #map {
            width: 100%;
            height: calc(100vh - 58px - 240px);
            min-height: 220px;
            background: #dde8f0;
            position: relative;
        }
        #map iframe { width: 100%; height: 100%; border: 0; display: block; }

        .map-placeholder {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            flex-direction: column; gap: 10px; color: #888; font-size: 14px;
        }
        .map-placeholder .icon { font-size: 40px; }

        .card {
            background: white;
            padding: 16px 20px;
            border-top: 3px solid #2e7d32;
        }

        .status-row { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
        .status-badge {
            padding: 4px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            background: #eceff1; color: #37474f;
        }
        .s-requested, .s-pending, .s-created { background: #eceff1; color: #37474f; }
        .s-assigned, .s-accepted             { background: #e3f2fd; color: #1b5e20; }
        .s-in_progress, .s-picked_up,
        .s-in_transit                        { background: #e8f5e9; color: #2e7d32; }
        .s-delivered, .s-completed           { background: #f3e5f5; color: #6a1b9a; }
        .s-cancelled                         { background: #fce4ec; color: #b71c1c; }

        .live-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #34a853; animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity: 1; } 50% { opacity: 0.3; }
        }

        .driver-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .avatar {
            width: 46px; height: 46px; border-radius: 50%;
            background: #2e7d32; color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; flex-shrink: 0;
        }
        .driver-name   { font-size: 16px; font-weight: 600; }
        .driver-rating { font-size: 13px; color: #888; margin-top: 2px; }

        .divider { height: 1px; background: #f0f0f0; margin: 12px 0; }

        .route { display: flex; flex-direction: column; gap: 8px; }
        .route-item { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: #555; }
        .route-item .label { display: block; font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 0.4px; }
        .dot { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; margin-top: 3px; }
        .dot-green { background: #34a853; }
        .dot-red   { background: #ea4335; }
        .route-line {
            width: 2px; height: 14px; background: #ddd;
            margin-left: 4px; margin-top: -2px; margin-bottom: -2px;
        }

        .ended-banner {
            background: #f3e5f5; border-radius: 8px;
            padding: 12px 16px; margin-bottom: 14px;
            font-size: 13px; color: #6a1b9a;
            display: flex; align-items: center; gap: 8px;
        }

        .directions {
            display: block; margin-top: 14px; text-align: center;
            background: #2e7d32; color: white; text-decoration: none;
            padding: 11px; border-radius: 8px; font-size: 13px; font-weight: 600;
        }

        .refresh-note { text-align: center; font-size: 11px; color: #bbb; padding: 10px; }

        .error-box {
            background: white; margin: 40px 20px; padding: 32px 20px;
            border-radius: 16px; text-align: center;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
        }
        .error-box .icon { font-size: 52px; margin-bottom: 12px; }
        .error-box h2 { font-size: 18px; color: #333; margin-bottom: 8px; }
        .error-box p  { font-size: 14px; color: #888; line-height: 1.5; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1>{{ $isMoving ? '🚚' : '📦' }} ROTEH APP — {{ $label }} Tracker</h1>
        <p>Shared live location</p>
    </div>
</div>

@if($delivery)

    @php
        $hasDriverLocation = ! empty($driver['lat']) && ! empty($driver['lng']);
        $hasDropoffCoords  = ! empty($delivery['dropoff_lat']) && ! empty($delivery['dropoff_lng']);
        $isLiveFlag        = ! empty($is_live);
    @endphp

    {{-- MAP --}}
    <div id="map">
        @if($isLiveFlag && $hasDriverLocation)
            {{-- Live driver pin --}}
            <iframe
                src="https://maps.google.com/maps?q={{ $driver['lat'] }},{{ $driver['lng'] }}&z=15&output=embed"
                allowfullscreen loading="lazy">
            </iframe>

        @elseif($hasDropoffCoords)
            {{-- No driver GPS yet (or job ended) — show the dropoff location instead --}}
            <iframe
                src="https://maps.google.com/maps?q={{ $delivery['dropoff_lat'] }},{{ $delivery['dropoff_lng'] }}&z=15&output=embed"
                allowfullscreen loading="lazy">
            </iframe>

        @elseif($delivery['is_finished'])
            <div class="map-placeholder">
                <div class="icon">✅</div>
                <p>{{ $label }} completed</p>
            </div>

        @elseif($delivery['is_cancelled'])
            <div class="map-placeholder">
                <div class="icon">❌</div>
                <p>{{ $label }} was cancelled</p>
            </div>

        @else
            <div class="map-placeholder">
                <div class="icon">📍</div>
                <p>Looking for a driver...</p>
            </div>
        @endif
    </div>

    {{-- CARD --}}
    <div class="card">

        {{-- Status --}}
        <div class="status-row">
            @if($isLiveFlag)
                <div class="live-dot"></div>
            @endif
            <span class="status-badge s-{{ $delivery['status'] }}">
                @switch($delivery['status'])
                    @case('requested')
                    @case('pending')
                    @case('created')      Finding a driver @break
                    @case('assigned')     Driver assigned @break
                    @case('accepted')     Driver heading to pickup @break
                    @case('picked_up')    Package collected @break
                    @case('in_progress')
                    @case('in_transit')   On the way to dropoff @break
                    @case('delivered')
                    @case('completed')    {{ $isMoving ? 'Job completed' : 'Delivered' }} @break
                    @case('cancelled')    Cancelled @break
                    @default {{ $delivery['status'] }}
                @endswitch
            </span>
        </div>

        {{-- Ended banner --}}
        @if($delivery['is_finished'])
            <div class="ended-banner">🏁 This {{ strtolower($label) }} is complete. Location tracking has ended.</div>
        @elseif($delivery['is_cancelled'])
            <div class="ended-banner" style="background:#fce4ec;color:#b71c1c;">
                ❌ This {{ strtolower($label) }} was cancelled.
            </div>
        @endif

        {{-- Driver info --}}
        @if($driver)
            <div class="driver-row">
                <div class="avatar">{{ strtoupper(substr($driver['name'], 0, 1)) }}</div>
                <div>
                    <div class="driver-name">{{ $driver['name'] }}</div>
                    <div class="driver-rating">⭐ {{ number_format($driver['rating'], 1) }}</div>
                </div>
            </div>
            <div class="divider"></div>
        @endif

        {{-- Route --}}
        <div class="route">
            <div class="route-item">
                <div class="dot dot-green"></div>
                <span>
                    <span class="label">Pickup</span>
                    {{ $delivery['pickup_address'] }}
                </span>
            </div>
            <div class="route-line"></div>
            <div class="route-item">
                <div class="dot dot-red"></div>
                <span>
                    <span class="label">Dropoff{{ $delivery['recipient_name'] ? ' — ' . $delivery['recipient_name'] : '' }}</span>
                    {{ $delivery['dropoff_address'] ?? 'Destination to be confirmed' }}
                </span>
            </div>
        </div>

        @if($hasDropoffCoords)
            <a class="directions" target="_blank" rel="noopener"
               href="https://maps.google.com/maps?daddr={{ $delivery['dropoff_lat'] }},{{ $delivery['dropoff_lng'] }}">
                🧭 Open dropoff location in Maps
            </a>
        @endif

    </div>

    @if($isLiveFlag)
        <p class="refresh-note">📡 Auto-refreshes every 10 seconds</p>
        <script>setTimeout(() => location.reload(), 10000);</script>
    @else
        <p class="refresh-note">Tracking has ended</p>
    @endif

@else

    {{-- Token not found --}}
    <div class="error-box">
        <div class="icon">🔗</div>
        <h2>Link not found</h2>
        <p>This tracking link is invalid or has expired.<br>Please ask the sender to share a new link.</p>
    </div>

@endif

</body>
</html>
