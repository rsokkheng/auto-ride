<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericReportExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    // ── Shared helpers ────────────────────────────────────────────────────────

    private function period(Request $request): array
    {
        $days  = (int) $request->input('period', 30);
        $start = now()->subDays($days)->startOfDay();
        return [$days, $start];
    }

    private function excel(string $title, array $headings, array $rows, array $meta = []): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $export   = new GenericReportExport($title, $headings, $rows, $meta);
        $filename = str_replace(' ', '_', strtolower($title)) . '_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }

    private function pdf(string $view, array $data, string $filename): \Illuminate\Http\Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false, 'defaultFont' => 'sans-serif']);
        return $pdf->download($filename . '_' . now()->format('Ymd_His') . '.pdf');
    }

    private function metaBlock(int $days, \Carbon\Carbon $start): array
    {
        return [
            'Period'       => "Last {$days} days ({$start->format('d M Y')} – " . now()->format('d M Y') . ')',
            'Generated At' => now()->format('d M Y H:i:s'),
        ];
    }

    // ── 1. Order Report ───────────────────────────────────────────────────────

    public function orders(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rows = DB::table('deliveries as d')
            ->leftJoin('users as dr', 'dr.id', '=', 'd.driver_id')
            ->leftJoin('users as p',  'p.id',  '=', 'd.partner_id')
            ->where('d.created_at', '>=', $start)
            ->selectRaw('d.id, d.recipient_name, d.recipient_phone, d.status,
                d.service_option, d.package_size, d.fee, d.package_amount,
                d.payment_by, d.payment_status, dr.name as driver, p.name as partner,
                d.created_at')
            ->orderByDesc('d.id')
            ->get();

        $headings = ['#', 'Recipient', 'Phone', 'Status', 'Service', 'Size', 'Fee (KHR)', 'COD (KHR)', 'Pay By', 'Pay Status', 'Driver', 'Partner', 'Date'];
        $data = $rows->map(fn($r) => [
            $r->id, $r->recipient_name, $r->recipient_phone, $r->status,
            $r->service_option, $r->package_size, $r->fee, $r->package_amount,
            $r->payment_by, $r->payment_status, $r->driver ?? '—', $r->partner ?? '—',
            \Carbon\Carbon::parse($r->created_at)->format('d M Y H:i'),
        ])->toArray();

        if ($format === 'pdf') {
            $totals = (object)[
                'total'    => $rows->count(),
                'done'     => $rows->whereIn('status', ['delivered','completed'])->count(),
                'cancelled'=> $rows->where('status','cancelled')->count(),
                'fee'      => $rows->sum('fee'),
                'cod'      => $rows->sum('package_amount'),
            ];
            return $this->pdf('admin.reports.pdf.orders', compact('rows','totals','days','start'), 'order_report');
        }

        return $this->excel('Order Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 2. Driver Report ──────────────────────────────────────────────────────

    public function drivers(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rows = DB::table('users as u')
            ->where('u.role', 'driver')
            ->leftJoin('rides as r', function ($j) use ($start) {
                $j->on('r.driver_id','=','u.id')->where('r.status','completed')->where('r.created_at','>=',$start);
            })
            ->leftJoin('deliveries as d', function ($j) use ($start) {
                $j->on('d.driver_id','=','u.id')->whereIn('d.status',['delivered','completed'])->where('d.created_at','>=',$start);
            })
            ->selectRaw('u.id, u.name, u.phone, u.rating, u.available, u.approval_status, u.wallet_balance,
                COUNT(DISTINCT r.id) as rides, COUNT(DISTINCT d.id) as deliveries,
                COALESCE(SUM(DISTINCT r.fare),0)+COALESCE(SUM(DISTINCT d.fee),0) as revenue')
            ->groupBy('u.id','u.name','u.phone','u.rating','u.available','u.approval_status','u.wallet_balance')
            ->orderByRaw('(COUNT(DISTINCT r.id)+COUNT(DISTINCT d.id)) DESC')
            ->get();

        $headings = ['#', 'Name', 'Phone', 'Status', 'Approval', 'Rides', 'Deliveries', 'Total Jobs', 'Revenue (KHR)', 'Rating', 'Wallet (KHR)'];
        $data = $rows->values()->map(fn($r, $i) => [
            $i+1, $r->name, $r->phone,
            $r->available ? 'Online' : 'Offline',
            $r->approval_status,
            $r->rides, $r->deliveries, $r->rides + $r->deliveries,
            $r->revenue, $r->rating ?? '—', $r->wallet_balance,
        ])->toArray();

        if ($format === 'pdf') {
            return $this->pdf('admin.reports.pdf.drivers', compact('rows','days','start'), 'driver_report');
        }

        return $this->excel('Driver Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 3. Partner Report ─────────────────────────────────────────────────────

    public function partners(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rows = DB::table('users as u')
            ->where('u.role','partner')
            ->leftJoin('deliveries as d', function ($j) use ($start) {
                $j->on('d.partner_id','=','u.id')->where('d.created_at','>=',$start);
            })
            ->selectRaw('u.id, u.name, u.phone, u.wallet_balance,
                COUNT(DISTINCT d.id) as orders,
                SUM(CASE WHEN d.status IN("delivered","completed") THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN d.status="cancelled" THEN 1 ELSE 0 END) as cancelled,
                SUM(d.fee) as revenue,
                SUM(CASE WHEN d.service_option="express" THEN 1 ELSE 0 END) as express')
            ->groupBy('u.id','u.name','u.phone','u.wallet_balance')
            ->orderByRaw('orders DESC')
            ->get();

        $headings = ['#', 'Partner', 'Phone', 'Orders', 'Completed', 'Cancelled', 'Express', 'Success %', 'Revenue (KHR)', 'Wallet (KHR)'];
        $data = $rows->values()->map(fn($r, $i) => [
            $i+1, $r->name, $r->phone,
            $r->orders, $r->done, $r->cancelled, $r->express,
            $r->orders > 0 ? round(($r->done / $r->orders) * 100, 1) . '%' : '0%',
            $r->revenue ?? 0, $r->wallet_balance,
        ])->toArray();

        if ($format === 'pdf') {
            return $this->pdf('admin.reports.pdf.partners', compact('rows','days','start'), 'partner_report');
        }

        return $this->excel('Partner Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 4. Customer Report ────────────────────────────────────────────────────

    public function customers(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rows = DB::table('users as u')
            ->where('u.role','passenger')
            ->leftJoin('rides as r', function ($j) use ($start) {
                $j->on('r.passenger_id','=','u.id')->where('r.created_at','>=',$start);
            })
            ->selectRaw('u.id, u.name, u.phone, u.created_at as joined,
                COUNT(DISTINCT r.id) as rides,
                SUM(CASE WHEN r.status="completed" THEN r.fare ELSE 0 END) as spent,
                AVG(r.passenger_rating) as avg_rating')
            ->groupBy('u.id','u.name','u.phone','u.created_at')
            ->orderByRaw('rides DESC')
            ->get();

        $headings = ['#', 'Name', 'Phone', 'Joined', 'Rides', 'Total Spent (KHR)', 'Avg Rating Given'];
        $data = $rows->values()->map(fn($r, $i) => [
            $i+1, $r->name, $r->phone,
            \Carbon\Carbon::parse($r->joined)->format('d M Y'),
            $r->rides, $r->spent ?? 0,
            $r->avg_rating ? round($r->avg_rating, 1) : '—',
        ])->toArray();

        if ($format === 'pdf') {
            return $this->pdf('admin.reports.pdf.customers', compact('rows','days','start'), 'customer_report');
        }

        return $this->excel('Customer Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 5. Financial Report ───────────────────────────────────────────────────

    public function financial(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $daily = DB::table('rides')
            ->where('status','completed')->where('created_at','>=',$start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as rides, SUM(fare) as ride_rev')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $dailyDel = DB::table('deliveries')
            ->whereIn('status',['delivered','completed'])->where('created_at','>=',$start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as deliveries, SUM(fee) as del_rev')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $allDates = collect($daily->keys()->merge($dailyDel->keys())->unique()->sort()->values());

        $headings = ['Date', 'Rides', 'Ride Revenue (KHR)', 'Deliveries', 'Delivery Revenue (KHR)', 'Total Revenue (KHR)'];
        $data = $allDates->map(fn($date) => [
            $date,
            $daily[$date]->rides ?? 0,
            $daily[$date]->ride_rev ?? 0,
            $dailyDel[$date]->deliveries ?? 0,
            $dailyDel[$date]->del_rev ?? 0,
            ($daily[$date]->ride_rev ?? 0) + ($dailyDel[$date]->del_rev ?? 0),
        ])->toArray();

        // Summary rows
        $data[] = [''];
        $data[] = ['SUMMARY', '', '', '', '', ''];
        $data[] = ['Total Ride Revenue',    '', (float) DB::table('rides')->where('status','completed')->where('created_at','>=',$start)->sum('fare'), '', '', ''];
        $data[] = ['Total Delivery Revenue','', '','',(float) DB::table('deliveries')->whereIn('status',['delivered','completed'])->where('created_at','>=',$start)->sum('fee'),''];
        $data[] = ['Platform Commission',   '', (float) DB::table('wallet_transactions')->where('type','platform_commission')->where('created_at','>=',$start)->sum('amount'), '', '', ''];

        if ($format === 'pdf') {
            $summary = [
                'rideRev'     => (float) DB::table('rides')->where('status','completed')->where('created_at','>=',$start)->sum('fare'),
                'deliveryRev' => (float) DB::table('deliveries')->whereIn('status',['delivered','completed'])->where('created_at','>=',$start)->sum('fee'),
                'commission'  => (float) DB::table('wallet_transactions')->where('type','platform_commission')->where('created_at','>=',$start)->sum('amount'),
                'topups'      => (float) DB::table('top_up_requests')->where('status','approved')->where('created_at','>=',$start)->sum('amount'),
                'withdrawals' => (float) DB::table('withdrawal_requests')->where('status','approved')->where('created_at','>=',$start)->sum('amount_khr'),
            ];
            return $this->pdf('admin.reports.pdf.financial', compact('allDates','daily','dailyDel','summary','days','start'), 'financial_report');
        }

        return $this->excel('Financial Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 6. Wallet Report ──────────────────────────────────────────────────────

    public function wallet(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rows = DB::table('wallet_transactions as wt')
            ->join('users as u','u.id','=','wt.user_id')
            ->where('wt.created_at','>=',$start)
            ->selectRaw('wt.id, u.name, u.role, wt.type, wt.direction, wt.amount,
                wt.balance_before, wt.balance_after, wt.note, wt.created_at')
            ->orderByDesc('wt.id')
            ->get();

        $headings = ['#', 'User', 'Role', 'Type', 'Direction', 'Amount (KHR)', 'Balance Before', 'Balance After', 'Note', 'Date'];
        $data = $rows->map(fn($r) => [
            $r->id, $r->name, $r->role, str_replace('_',' ',$r->type),
            $r->direction, $r->amount, $r->balance_before, $r->balance_after,
            $r->note,
            \Carbon\Carbon::parse($r->created_at)->format('d M Y H:i'),
        ])->toArray();

        if ($format === 'pdf') {
            $byType = DB::select('SELECT type, direction, COUNT(*) as c, SUM(amount) as total FROM wallet_transactions WHERE created_at >= ? GROUP BY type, direction ORDER BY total DESC', [$start]);
            return $this->pdf('admin.reports.pdf.wallet', compact('rows','byType','days','start'), 'wallet_report');
        }

        return $this->excel('Wallet Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 7. Withdrawal Report ──────────────────────────────────────────────────

    public function withdrawals(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rows = DB::table('withdrawal_requests as w')
            ->join('users as u','u.id','=','w.driver_id')
            ->where('w.created_at','>=',$start)
            ->selectRaw('w.id, u.name, u.phone, w.amount_khr, w.payment_method,
                w.account_number, w.bank_name, w.status, w.admin_note, w.processed_at, w.created_at')
            ->orderByDesc('w.id')
            ->get();

        $headings = ['#', 'Driver', 'Phone', 'Amount (KHR)', 'Method', 'Account', 'Bank', 'Status', 'Admin Note', 'Processed At', 'Requested At'];
        $data = $rows->map(fn($r) => [
            $r->id, $r->name, $r->phone, $r->amount_khr, $r->payment_method,
            $r->account_number, $r->bank_name, $r->status, $r->admin_note,
            $r->processed_at ? \Carbon\Carbon::parse($r->processed_at)->format('d M Y H:i') : '—',
            \Carbon\Carbon::parse($r->created_at)->format('d M Y H:i'),
        ])->toArray();

        if ($format === 'pdf') {
            $totals = (object)[
                'total'   => $rows->count(),
                'pending' => $rows->where('status','pending')->count(),
                'approved'=> $rows->where('status','approved')->count(),
                'rejected'=> $rows->where('status','rejected')->count(),
                'paid'    => $rows->where('status','approved')->sum('amount_khr'),
            ];
            return $this->pdf('admin.reports.pdf.withdrawals', compact('rows','totals','days','start'), 'withdrawal_report');
        }

        return $this->excel('Withdrawal Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 8. Commission Report ──────────────────────────────────────────────────

    public function commission(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rows = DB::table('wallet_transactions as wt')
            ->join('users as u','u.id','=','wt.user_id')
            ->where('wt.type','platform_commission')
            ->where('wt.created_at','>=',$start)
            ->selectRaw('wt.id, u.name, u.phone, wt.amount, wt.balance_before, wt.balance_after, wt.note, wt.created_at')
            ->orderByDesc('wt.id')
            ->get();

        $headings = ['#', 'Driver', 'Phone', 'Commission (KHR)', 'Balance Before', 'Balance After', 'Note', 'Date'];
        $data = $rows->map(fn($r) => [
            $r->id, $r->name, $r->phone, $r->amount,
            $r->balance_before, $r->balance_after, $r->note,
            \Carbon\Carbon::parse($r->created_at)->format('d M Y H:i'),
        ])->toArray();

        if ($format === 'pdf') {
            $byDriver = DB::table('wallet_transactions as wt')
                ->join('users as u','u.id','=','wt.user_id')
                ->where('wt.type','platform_commission')->where('wt.created_at','>=',$start)
                ->selectRaw('u.name, COUNT(wt.id) as trips, SUM(wt.amount) as commission')
                ->groupBy('u.name')->orderByRaw('commission DESC')->get();
            $total = $rows->sum('amount');
            return $this->pdf('admin.reports.pdf.commission', compact('rows','byDriver','total','days','start'), 'commission_report');
        }

        return $this->excel('Commission Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 9. Performance Report ─────────────────────────────────────────────────

    public function performance(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rideKpi = DB::table('rides')->where('created_at','>=',$start)
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN status="completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status="cancelled" THEN 1 ELSE 0 END) as cancelled,
                AVG(CASE WHEN status="completed" AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,completed_at) END) as avg_min,
                AVG(CASE WHEN rating IS NOT NULL THEN rating END) as avg_rating')->first();

        $delivKpi = DB::table('deliveries')->where('created_at','>=',$start)
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN status IN("delivered","completed") THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status="cancelled" THEN 1 ELSE 0 END) as cancelled,
                AVG(CASE WHEN status IN("delivered","completed") AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,completed_at) END) as avg_min,
                AVG(CASE WHEN rating IS NOT NULL THEN rating END) as avg_rating')->first();

        $headings = ['Metric', 'Rides', 'Deliveries'];
        $data = [
            ['Total',            $rideKpi->total  ?? 0, $delivKpi->total  ?? 0],
            ['Completed',        $rideKpi->completed ?? 0, $delivKpi->completed ?? 0],
            ['Cancelled',        $rideKpi->cancelled ?? 0, $delivKpi->cancelled ?? 0],
            ['Completion Rate',  $rideKpi->total  > 0 ? round($rideKpi->completed/$rideKpi->total*100,1).'%' : '0%',
                                 $delivKpi->total > 0 ? round($delivKpi->completed/$delivKpi->total*100,1).'%' : '0%'],
            ['Cancellation Rate',$rideKpi->total  > 0 ? round($rideKpi->cancelled/$rideKpi->total*100,1).'%' : '0%',
                                 $delivKpi->total > 0 ? round($delivKpi->cancelled/$delivKpi->total*100,1).'%' : '0%'],
            ['Avg Duration (min)',$rideKpi->avg_min  ? round($rideKpi->avg_min) : '—', $delivKpi->avg_min ? round($delivKpi->avg_min) : '—'],
            ['Avg Rating',       $rideKpi->avg_rating  ? round($rideKpi->avg_rating,2) : '—', $delivKpi->avg_rating ? round($delivKpi->avg_rating,2) : '—'],
        ];

        if ($format === 'pdf') {
            $cancelReasons = DB::table('rides')->where('created_at','>=',$start)->where('status','cancelled')
                ->whereNotNull('cancellation_reason')
                ->selectRaw('cancellation_reason, COUNT(*) as c')->groupBy('cancellation_reason')->orderByRaw('c DESC')->limit(10)->get();
            return $this->pdf('admin.reports.pdf.performance', compact('rideKpi','delivKpi','cancelReasons','days','start'), 'performance_report');
        }

        return $this->excel('Performance Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 10. Driver Ranking ────────────────────────────────────────────────────

    public function driverRanking(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $rows = DB::table('users as u')
            ->where('u.role','driver')
            ->leftJoin('rides as r', function ($j) use ($start) {
                $j->on('r.driver_id','=','u.id')->where('r.status','completed')->where('r.created_at','>=',$start);
            })
            ->leftJoin('deliveries as d', function ($j) use ($start) {
                $j->on('d.driver_id','=','u.id')->whereIn('d.status',['delivered','completed'])->where('d.created_at','>=',$start);
            })
            ->selectRaw('u.id, u.name, u.phone, u.rating, u.available,
                COUNT(DISTINCT r.id) as rides, COUNT(DISTINCT d.id) as deliveries,
                COALESCE(SUM(DISTINCT r.fare),0) as ride_rev,
                COALESCE(SUM(DISTINCT d.fee),0) as del_rev')
            ->groupBy('u.id','u.name','u.phone','u.rating','u.available')
            ->orderByRaw('(COUNT(DISTINCT r.id)+COUNT(DISTINCT d.id)) DESC')
            ->get();

        $headings = ['Rank', 'Driver', 'Phone', 'Status', 'Rides', 'Deliveries', 'Total Jobs', 'Ride Revenue (KHR)', 'Delivery Revenue (KHR)', 'Total Revenue (KHR)', 'Rating'];
        $data = $rows->values()->map(fn($r, $i) => [
            $i+1, $r->name, $r->phone,
            $r->available ? 'Online' : 'Offline',
            $r->rides, $r->deliveries, $r->rides + $r->deliveries,
            $r->ride_rev, $r->del_rev, $r->ride_rev + $r->del_rev,
            $r->rating ?? '—',
        ])->toArray();

        if ($format === 'pdf') {
            return $this->pdf('admin.reports.pdf.driver-ranking', compact('rows','days','start'), 'driver_ranking_report');
        }

        return $this->excel('Driver Ranking Report', $headings, $data, $this->metaBlock($days, $start));
    }

    // ── 11. Analytics Report ──────────────────────────────────────────────────

    public function analytics(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');
        $view   = $request->input('view', 'daily');

        $groupFmt = match($view) {
            'weekly'  => "YEARWEEK(created_at,1)",
            'monthly' => "DATE_FORMAT(created_at,'%Y-%m')",
            'yearly'  => "YEAR(created_at)",
            default   => "DATE(created_at)",
        };
        $labelFmt = match($view) {
            'weekly'  => "CONCAT('W',WEEK(created_at,1),' ',YEAR(created_at))",
            'monthly' => "DATE_FORMAT(created_at,'%b %Y')",
            'yearly'  => "YEAR(created_at)",
            default   => "DATE(created_at)",
        };

        $rides = DB::table('rides')->where('created_at','>=',$start)
            ->selectRaw("{$groupFmt} as grp, {$labelFmt} as label,
                COUNT(*) as total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status='completed' THEN fare ELSE 0 END) as revenue")
            ->groupBy('grp','label')->orderBy('grp')->get()->keyBy('label');

        $deliveries = DB::table('deliveries')->where('created_at','>=',$start)
            ->selectRaw("{$groupFmt} as grp, {$labelFmt} as label,
                COUNT(*) as total, SUM(CASE WHEN status IN('delivered','completed') THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status IN('delivered','completed') THEN fee ELSE 0 END) as revenue")
            ->groupBy('grp','label')->orderBy('grp')->get()->keyBy('label');

        $labels = collect($rides->keys()->merge($deliveries->keys())->unique()->sort()->values());

        $headings = ['Period', 'Rides Total', 'Rides Done', 'Rides Cancelled', 'Ride Revenue', 'Deliveries Total', 'Deliveries Done', 'Deliveries Cancelled', 'Delivery Revenue', 'Combined Revenue'];
        $data = $labels->map(fn($label) => [
            $label,
            $rides[$label]->total      ?? 0,
            $rides[$label]->completed  ?? 0,
            $rides[$label]->cancelled  ?? 0,
            $rides[$label]->revenue    ?? 0,
            $deliveries[$label]->total     ?? 0,
            $deliveries[$label]->completed ?? 0,
            $deliveries[$label]->cancelled ?? 0,
            $deliveries[$label]->revenue   ?? 0,
            ($rides[$label]->revenue ?? 0) + ($deliveries[$label]->revenue ?? 0),
        ])->toArray();

        if ($format === 'pdf') {
            return $this->pdf('admin.reports.pdf.analytics', compact('labels','rides','deliveries','view','days','start'), 'analytics_report');
        }

        return $this->excel("Analytics Report ({$view})", $headings, $data, $this->metaBlock($days, $start));
    }

    // ── Operations Report ─────────────────────────────────────────────────────

    public function operations(Request $request)
    {
        [$days, $start] = $this->period($request);
        $format = $request->input('format', 'excel');

        $deliveries = DB::table('deliveries')->where('created_at','>=',$start)
            ->selectRaw('status, COUNT(*) as c, SUM(fee) as rev')
            ->groupBy('status')->orderByRaw('c DESC')->get();

        $rides = DB::table('rides')->where('created_at','>=',$start)
            ->selectRaw('status, COUNT(*) as c, SUM(fare) as rev')
            ->groupBy('status')->orderByRaw('c DESC')->get();

        if ($format === 'pdf') {
            $summary = [
                'totalDeliveries' => DB::table('deliveries')->where('created_at','>=',$start)->count(),
                'totalRides'      => DB::table('rides')->where('created_at','>=',$start)->count(),
                'deliveryRev'     => DB::table('deliveries')->whereIn('status',['delivered','completed'])->where('created_at','>=',$start)->sum('fee'),
                'rideRev'         => DB::table('rides')->where('status','completed')->where('created_at','>=',$start)->sum('fare'),
                'commission'      => DB::table('wallet_transactions')->where('type','platform_commission')->where('created_at','>=',$start)->sum('amount'),
                'unassigned'      => DB::table('deliveries')->whereNull('driver_id')->whereNotIn('status',['cancelled','completed','delivered'])->count(),
                'activeDrivers'   => DB::table('users')->where('role','driver')->where('available',1)->count(),
            ];
            return $this->pdf('admin.reports.pdf.operations', compact('deliveries','rides','summary','days','start'), 'operations_report');
        }

        $headings = ['Type', 'Status', 'Count', 'Revenue (KHR)'];
        $data = array_merge(
            $deliveries->map(fn($r) => ['Delivery', $r->status, $r->c, $r->rev])->toArray(),
            [['', '', '', '']],
            $rides->map(fn($r) => ['Ride', $r->status, $r->c, $r->rev])->toArray()
        );

        return $this->excel('Operations Report', $headings, $data, $this->metaBlock($days, $start));
    }
}
