<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AutoRide Receipt</title>
<style>
  body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:0; color:#333; }
  .wrap { max-width:560px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:#1a73e8; color:#fff; padding:28px 32px; text-align:center; }
  .header h1 { margin:0; font-size:22px; letter-spacing:.5px; }
  .header p  { margin:4px 0 0; font-size:13px; opacity:.85; }
  .ref-box { background:#f0f4ff; border:1px solid #c7d7f5; border-radius:6px; padding:12px 20px; margin:24px 32px 0; text-align:center; }
  .ref-box .ref { font-size:20px; font-weight:700; color:#1a73e8; letter-spacing:1px; }
  .ref-box .date { font-size:12px; color:#666; margin-top:2px; }
  .section { padding:20px 32px 0; }
  .section h3 { font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:#888; margin:0 0 10px; }
  .route { display:flex; align-items:flex-start; gap:12px; background:#f9fafb; border-radius:6px; padding:14px 16px; }
  .route-dots { display:flex; flex-direction:column; align-items:center; padding-top:4px; }
  .dot-green { width:10px; height:10px; border-radius:50%; background:#34a853; }
  .dot-line  { width:2px; flex:1; background:#ddd; margin:3px 0; min-height:18px; }
  .dot-red   { width:10px; height:10px; border-radius:50%; background:#ea4335; }
  .route-text { flex:1; }
  .route-text .addr { font-size:14px; line-height:1.4; }
  .route-text .addr + .addr { margin-top:12px; }
  .row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0; font-size:14px; }
  .row:last-child { border-bottom:none; }
  .row .label { color:#666; }
  .row .val   { font-weight:600; }
  .total-row  { display:flex; justify-content:space-between; padding:14px 0 0; font-size:16px; font-weight:700; border-top:2px solid #1a73e8; margin-top:4px; color:#1a73e8; }
  .badge { display:inline-block; background:#e8f0fe; color:#1a73e8; border-radius:4px; padding:2px 8px; font-size:12px; font-weight:600; }
  .footer { padding:20px 32px 28px; text-align:center; color:#999; font-size:12px; }
  .footer a { color:#1a73e8; text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <h1>🚗 AutoRide</h1>
    <p>{{ $tripType === 'delivery' ? 'Delivery Receipt' : 'Trip Receipt' }}</p>
  </div>

  <div class="ref-box">
    <div class="ref">{{ $details['ref'] }}</div>
    <div class="date">{{ $details['date'] }}</div>
  </div>

  <div class="section" style="margin-top:20px;">
    <h3>Route</h3>
    <div class="route">
      <div class="route-dots">
        <div class="dot-green"></div>
        <div class="dot-line"></div>
        <div class="dot-red"></div>
      </div>
      <div class="route-text">
        <div class="addr">{{ $details['from'] }}</div>
        <div class="addr">{{ $details['to'] }}</div>
      </div>
    </div>
  </div>

  <div class="section" style="margin-top:20px;">
    <h3>Trip Details</h3>
    <div class="row">
      <span class="label">Service</span>
      <span class="val">{{ $details['service_type'] }}</span>
    </div>
    @if(!empty($details['package_size']))
    <div class="row">
      <span class="label">Package Size</span>
      <span class="val">{{ ucfirst($details['package_size']) }}</span>
    </div>
    @endif
    <div class="row">
      <span class="label">Distance</span>
      <span class="val">{{ $details['distance_km'] }} km</span>
    </div>
    @if(!empty($details['duration_min']) && $details['duration_min'] !== '—')
    <div class="row">
      <span class="label">Duration</span>
      <span class="val">{{ $details['duration_min'] }} min</span>
    </div>
    @endif
    <div class="row">
      <span class="label">Driver</span>
      <span class="val">{{ $details['driver_name'] }}</span>
    </div>
    @if(!empty($details['vehicle_plate']))
    <div class="row">
      <span class="label">Vehicle</span>
      <span class="val">{{ $details['vehicle_plate'] }}</span>
    </div>
    @endif
    <div class="row">
      <span class="label">Payment</span>
      <span class="val"><span class="badge">{{ $details['payment_method'] }}</span></span>
    </div>
  </div>

  <div class="section" style="margin-top:20px; padding-bottom:24px;">
    <h3>Fare Breakdown</h3>
    <div class="row">
      <span class="label">Base Fare</span>
      <span class="val">{{ number_format($details['base_fare'] ?? $details['total']) }} ៛</span>
    </div>
    @if(!empty($details['waiting_fee']) && $details['waiting_fee'] > 0)
    <div class="row">
      <span class="label">Waiting Fee</span>
      <span class="val">+ {{ number_format($details['waiting_fee']) }} ៛</span>
    </div>
    @endif
    @if(!empty($details['surge_multiplier']) && $details['surge_multiplier'] > 1)
    <div class="row">
      <span class="label">Surge (×{{ $details['surge_multiplier'] }})</span>
      <span class="val" style="color:#e67700;">Applied</span>
    </div>
    @endif
    @if(!empty($details['discount']) && $details['discount'] > 0)
    <div class="row">
      <span class="label">Promo Discount</span>
      <span class="val" style="color:#34a853;">− {{ number_format($details['discount']) }} ៛</span>
    </div>
    @endif
    <div class="total-row">
      <span>Total Charged</span>
      <span>{{ number_format($details['total']) }} ៛</span>
    </div>
  </div>

  <div class="footer">
    Thank you for riding with AutoRide!<br>
    Questions? <a href="mailto:support@auto-supperapp.com">support@auto-supperapp.com</a>
  </div>
</div>
</body>
</html>
