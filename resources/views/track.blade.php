<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Trip Tracking — ROTEH APP</title>
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
            height: calc(100vh - 58px - 200px);
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
        }
        .s-accepted       { background: #e3f2fd; color: #1b5e20; }
        .s-driver_arrived { background: #fff8e1; color: #e65100; }
        .s-in_progress    { background: #e8f5e9; color: #2e7d32; }
        .s-completed      { background: #f3e5f5; color: #6a1b9a; }
        .s-cancelled      { background: #fce4ec; color: #b71c1c; }

        .live-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #34a853; animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity: 1; } 50% { opacity: 0.3; }
        }

        .driver-row {
            display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
        }
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
        .route-item {
            display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: #555;
        }
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
        <h1>🚗 ROTEH APP — Trip Tracker</h1>
        <p>Shared live location</p>
    </div>
</div>

@if($ride)

    {{-- MAP --}}
    <div id="map">
        @php
            $hasLocation = isset($driver['lat']) && $driver['lat'] && isset($driver['lng']) && $driver['lng'];
            $isLiveFlag  = isset($is_live) && $is_live;
        @endphp

        @if($isLiveFlag && $hasLocation)
            {{-- Live driver pin on map --}}
            <iframe
                src="https://maps.google.com/maps?q={{ $driver['lat'] }},{{ $driver['lng'] }}&z=15&output=embed"
                allowfullscreen loading="lazy">
            </iframe>

        @elseif($ride['status'] === 'completed')
            <div class="map-placeholder">
                <div class="icon">✅</div>
                <p>Trip has ended</p>
            </div>

        @elseif($ride['status'] === 'cancelled')
            <div class="map-placeholder">
                <div class="icon">❌</div>
                <p>Trip was cancelled</p>
            </div>

        @elseif($isLiveFlag && ! $hasLocation)
            {{-- Driver assigned but no GPS yet --}}
            <div class="map-placeholder">
                <div class="icon" style="font-size:36px">🛺</div>
                <p style="font-weight:600;color:#555">Driver is on the way</p>
                <p style="font-size:12px;margin-top:4px">Waiting for GPS signal from driver...</p>
                <p style="font-size:11px;color:#bbb;margin-top:8px">Page refreshes automatically every 10s</p>
            </div>

        @else
            <div class="map-placeholder">
                <div class="icon">📍</div>
                <p>Looking for driver...</p>
            </div>
        @endif
    </div>

    {{-- CARD --}}
    <div class="card">

        {{-- Status --}}
        <div class="status-row">
            @if(isset($is_live) && $is_live)
                <div class="live-dot"></div>
            @endif
            <span class="status-badge s-{{ $ride['status'] }}">
                @switch($ride['status'])
                    @case('accepted')        Driver On The Way @break
                    @case('driver_arrived')  Driver Arrived @break
                    @case('in_progress')     Trip In Progress @break
                    @case('completed')       Trip Completed @break
                    @case('cancelled')       Trip Cancelled @break
                    @default {{ $ride['status'] }}
                @endswitch
            </span>
        </div>

        {{-- Ended banner --}}
        @if($ride['status'] === 'completed')
            <div class="ended-banner">🏁 This trip has been completed. Location tracking has ended.</div>
        @elseif($ride['status'] === 'cancelled')
            <div class="ended-banner" style="background:#fce4ec;color:#b71c1c;">
                ❌ This trip was cancelled.
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
                <span>{{ $ride['pickup_address'] }}</span>
            </div>
            <div class="route-line" style="margin-left:4px"></div>
            <div class="route-item">
                <div class="dot dot-red"></div>
                <span>{{ $ride['dropoff_address'] }}</span>
            </div>
        </div>

    </div>

    @if(isset($is_live) && $is_live)
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
        <p>This tracking link is invalid or has expired.<br>Please ask the passenger to share a new link.</p>
    </div>

@endif

</body>
</html>
