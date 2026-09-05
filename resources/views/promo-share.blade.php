<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $promo ? $promo['code'] . ' — Promo Code' : 'Promo Code' }} — ROTEH APP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5; color: #222;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px 16px;
        }
        .card {
            background: white;
            width: 100%; max-width: 380px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .header {
            background: #2e7d32;
            color: white;
            padding: 28px 24px 24px;
            text-align: center;
        }
        .header .app-name { font-size: 13px; opacity: 0.85; letter-spacing: 0.5px; margin-bottom: 6px; }
        .header .discount { font-size: 34px; font-weight: 800; }
        .header .discount small { font-size: 16px; font-weight: 600; opacity: 0.9; }
        .body { padding: 24px; }
        .desc { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.5; }
        .code-box {
            background: #f0faf1;
            border: 2px dashed #2e7d32;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 18px;
        }
        .code-box .code { font-size: 20px; font-weight: 800; letter-spacing: 2px; color: #1b5e20; }
        .copy-btn {
            background: #2e7d32; color: white; border: none;
            padding: 8px 14px; border-radius: 8px;
            font-size: 13px; font-weight: 700; cursor: pointer;
        }
        .copy-btn:active { opacity: 0.8; }
        .meta { font-size: 12px; color: #888; line-height: 1.8; margin-bottom: 20px; }
        .meta strong { color: #444; }
        .hint {
            background: #fff8e1; color: #7a5c00;
            border-radius: 10px; padding: 12px 14px;
            font-size: 13px; line-height: 1.5;
        }
        .status-msg {
            text-align: center; padding: 40px 24px; color: #888; font-size: 15px;
        }
        .status-msg .icon { font-size: 40px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="card">
        @if($promo && $status === 'active')
            <div class="header">
                <div class="app-name">ROTEH APP · Promo Code</div>
                <div class="discount">
                    @if($promo['type'] === 'percent')
                        {{ $promo['value'] }}%<small> OFF</small>
                    @else
                        ៛{{ number_format($promo['value']) }}<small> OFF</small>
                    @endif
                </div>
            </div>
            <div class="body">
                @if($promo['description'])
                    <div class="desc">{{ $promo['description'] }}</div>
                @endif
                <div class="code-box">
                    <span class="code" id="promo-code">{{ $promo['code'] }}</span>
                    <button class="copy-btn" onclick="copyCode()">Copy</button>
                </div>
                <div class="meta">
                    @if($promo['min_order'] > 0)
                        <div><strong>Minimum order:</strong> ៛{{ number_format($promo['min_order']) }}</div>
                    @endif
                    @if($promo['type'] === 'percent' && $promo['max_discount'])
                        <div><strong>Max discount:</strong> ៛{{ number_format($promo['max_discount']) }}</div>
                    @endif
                    <div><strong>Applies to:</strong> {{ $promo['service_type'] === 'all' ? 'All services' : ucfirst($promo['service_type']) }}</div>
                    @if($promo['expires_at'])
                        <div><strong>Expires:</strong> {{ $promo['expires_at']->format('d M Y') }}</div>
                    @endif
                </div>
                <div class="hint">
                    Open the ROTEH app, enter this code at checkout, and enjoy your discount.
                </div>
            </div>
        @else
            <div class="status-msg">
                <div class="icon">🎟️</div>
                @if($status === 'not_found')
                    This promo code doesn't exist.
                @elseif($status === 'expired')
                    This promo code has expired.
                @elseif($status === 'not_started')
                    This promo code isn't active yet.
                @elseif($status === 'exhausted')
                    This promo code has reached its usage limit.
                @else
                    This promo code is no longer active.
                @endif
            </div>
        @endif
    </div>

    <script>
        function copyCode() {
            const code = document.getElementById('promo-code').innerText;
            const btn = event.target;
            navigator.clipboard.writeText(code).then(() => {
                const original = btn.innerText;
                btn.innerText = 'Copied!';
                setTimeout(() => { btn.innerText = original; }, 1500);
            });
        }
    </script>
</body>
</html>
