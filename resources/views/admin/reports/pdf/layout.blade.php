<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size:10px; color:#1f2937; background:#fff; }
.page-header { background:#1e3a5f; color:#fff; padding:12px 16px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; }
.page-header h1 { font-size:16px; font-weight:700; }
.page-header .meta { font-size:9px; text-align:right; opacity:.85; }
.section-title { font-size:12px; font-weight:700; color:#1e3a5f; border-left:4px solid #1e3a5f; padding-left:8px; margin:14px 0 8px; }
table { width:100%; border-collapse:collapse; margin-bottom:14px; }
thead tr { background:#374151; color:#fff; }
thead th { padding:6px 8px; text-align:left; font-size:9px; font-weight:600; }
tbody tr:nth-child(even) { background:#f9fafb; }
tbody td { padding:5px 8px; border-bottom:1px solid #e5e7eb; font-size:9px; }
.text-right { text-align:right; }
.text-center { text-align:center; }
.badge { display:inline-block; padding:2px 6px; border-radius:3px; font-size:8px; font-weight:600; }
.badge-success { background:#d1fae5; color:#065f46; }
.badge-danger  { background:#fee2e2; color:#991b1b; }
.badge-warning { background:#fef3c7; color:#92400e; }
.badge-info    { background:#dbeafe; color:#1e40af; }
.badge-secondary { background:#f3f4f6; color:#374151; }
.summary-grid { display:table; width:100%; margin-bottom:14px; }
.summary-card { display:table-cell; width:25%; border:1px solid #e5e7eb; padding:10px 12px; text-align:center; }
.summary-card .val { font-size:18px; font-weight:700; color:#1e3a5f; }
.summary-card .lbl { font-size:9px; color:#6b7280; margin-top:3px; }
.footer { margin-top:20px; border-top:1px solid #e5e7eb; padding-top:8px; font-size:8px; color:#9ca3af; display:flex; justify-content:space-between; }
</style>
</head>
<body>
<div class="page-header">
    <h1>{{ $reportTitle }}</h1>
    <div class="meta">
        Period: Last {{ $days }} days ({{ $start->format('d M Y') }} – {{ now()->format('d M Y') }})<br>
        Generated: {{ now()->format('d M Y H:i:s') }}<br>
        Auto-Ride Admin System
    </div>
</div>

@yield('content')

<div class="footer">
    <span>Auto-Ride Operations Report</span>
    <span>Generated {{ now()->format('d M Y H:i') }}</span>
</div>
</body>
</html>
