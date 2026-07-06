<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericReportExport;
use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SettlementController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('settlements as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->select(
                's.*',
                'u.name as entity_name',
                'u.phone as entity_phone',
                'u.wallet_balance'
            );

        if ($request->filled('type')) {
            $query->where('s.settlement_type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('s.status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('u.name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('from')) {
            $query->where('s.period_start', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('s.period_end', '<=', $request->to);
        }

        $settlements = $query->orderByDesc('s.created_at')->paginate(20)->withQueryString();

        $summary = DB::table('settlements')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN status='paid' THEN net_payout ELSE 0 END) as total_paid,
                SUM(CASE WHEN status IN('pending','approved','processing') THEN net_payout ELSE 0 END) as total_pending_amount
            ")->first();

        return view('admin.settlements.index', compact('settlements', 'summary'));
    }

    public function create()
    {
        $drivers  = User::where('role', 'driver')->orderBy('name')->get(['id', 'name', 'phone', 'wallet_balance']);
        $partners = User::where('role', 'partner')->orderBy('name')->get(['id', 'name', 'phone', 'wallet_balance']);

        return view('admin.settlements.create', compact('drivers', 'partners'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'settlement_type' => 'required|in:driver,partner',
            'user_id'         => 'required|integer',
            'period_start'    => 'required|date',
            'period_end'      => 'required|date|after_or_equal:period_start',
        ]);

        $user = User::findOrFail($request->user_id);
        $data = $request->settlement_type === 'driver'
            ? $this->calcDriver($request->user_id, $request->period_start, $request->period_end)
            : $this->calcPartner($request->user_id, $request->period_start, $request->period_end);

        return response()->json(array_merge($data, [
            'user_name'      => $user->name,
            'user_phone'     => $user->phone,
            'wallet_balance' => $user->wallet_balance,
        ]));
    }

    public function store(Request $request)
    {
        $request->validate([
            'settlement_type' => 'required|in:driver,partner',
            'user_id'         => 'required|integer|exists:users,id',
            'period_start'    => 'required|date',
            'period_end'      => 'required|date|after_or_equal:period_start',
            'payment_method'  => 'nullable|string|max:50',
            'bank_name'       => 'nullable|string|max:100',
            'bank_account'    => 'nullable|string|max:100',
            'account_holder'  => 'nullable|string|max:100',
            'adjustments'     => 'nullable|integer',
            'adjustment_note' => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'submit_action'   => 'required|in:draft,pending',
        ]);

        $calc = $request->settlement_type === 'driver'
            ? $this->calcDriver($request->user_id, $request->period_start, $request->period_end)
            : $this->calcPartner($request->user_id, $request->period_start, $request->period_end);

        $adj      = (int) $request->input('adjustments', 0);
        $netPayout = $calc['net_payout'] + $adj;

        $settlement = Settlement::create(array_merge($calc, [
            'settlement_type' => $request->settlement_type,
            'user_id'         => $request->user_id,
            'period_start'    => $request->period_start,
            'period_end'      => $request->period_end,
            'status'          => $request->submit_action,
            'adjustments'     => $adj,
            'adjustment_note' => $request->adjustment_note,
            'net_payout'      => $netPayout,
            'payment_method'  => $request->payment_method,
            'bank_name'       => $request->bank_name,
            'bank_account'    => $request->bank_account,
            'account_holder'  => $request->account_holder,
            'notes'           => $request->notes,
            'created_by'      => Auth::id(),
        ]));

        return redirect()->route('admin.settlements.show', $settlement)
            ->with('success', 'Settlement #' . $settlement->id . ' created successfully.');
    }

    public function show(Settlement $settlement)
    {
        $settlement->load('user', 'creator', 'approver', 'processor');

        $lineItems = $settlement->settlement_type === 'driver'
            ? $this->driverLineItems($settlement)
            : $this->partnerLineItems($settlement);

        return view('admin.settlements.show', compact('settlement', 'lineItems'));
    }

    public function approve(Request $request, Settlement $settlement)
    {
        if (! $settlement->canApprove()) {
            return back()->with('error', 'Settlement cannot be approved in its current state.');
        }

        $settlement->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'notes'       => $settlement->notes . ($request->filled('note') ? "\n[Approved] " . $request->note : ''),
        ]);

        return back()->with('success', 'Settlement approved.');
    }

    public function reject(Request $request, Settlement $settlement)
    {
        if (! $settlement->canApprove()) {
            return back()->with('error', 'Settlement cannot be rejected in its current state.');
        }

        $settlement->update([
            'status' => 'cancelled',
            'notes'  => $settlement->notes . ($request->filled('note') ? "\n[Rejected] " . $request->note : ''),
        ]);

        return back()->with('success', 'Settlement rejected and cancelled.');
    }

    public function markProcessing(Settlement $settlement)
    {
        if (! $settlement->canProcess()) {
            return back()->with('error', 'Settlement cannot be moved to processing.');
        }

        $settlement->update([
            'status'       => 'processing',
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Settlement marked as Processing.');
    }

    public function markPaid(Request $request, Settlement $settlement)
    {
        if (! $settlement->canMarkPaid()) {
            return back()->with('error', 'Settlement cannot be marked as paid.');
        }

        $settlement->update([
            'status'            => 'paid',
            'payment_reference' => $request->input('payment_reference', $settlement->payment_reference),
            'paid_at'           => now(),
        ]);

        return back()->with('success', 'Settlement marked as Paid.');
    }

    public function markFailed(Request $request, Settlement $settlement)
    {
        if (! $settlement->canMarkFailed()) {
            return back()->with('error', 'Settlement cannot be marked as failed.');
        }

        $settlement->update([
            'status' => 'failed',
            'notes'  => $settlement->notes . ($request->filled('reason') ? "\n[Failed] " . $request->reason : ''),
        ]);

        return back()->with('success', 'Settlement marked as Failed.');
    }

    public function cancel(Settlement $settlement)
    {
        if (! $settlement->canCancel()) {
            return back()->with('error', 'Settlement cannot be cancelled.');
        }

        $settlement->update(['status' => 'cancelled']);

        return back()->with('success', 'Settlement cancelled.');
    }

    public function destroy(Settlement $settlement)
    {
        if (! $settlement->canDelete()) {
            return back()->with('error', 'Only draft settlements can be deleted.');
        }

        $settlement->delete();

        return redirect()->route('admin.settlements.index')->with('success', 'Settlement deleted.');
    }

    public function export(Request $request)
    {
        $query = DB::table('settlements as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->select('s.*', 'u.name as entity_name', 'u.phone as entity_phone');

        if ($request->filled('type'))   { $query->where('s.settlement_type', $request->type); }
        if ($request->filled('status')) { $query->where('s.status', $request->status); }
        if ($request->filled('search')) { $query->where('u.name', 'like', '%' . $request->search . '%'); }
        if ($request->filled('from'))   { $query->where('s.period_start', '>=', $request->from); }
        if ($request->filled('to'))     { $query->where('s.period_end', '<=', $request->to); }

        $rows = $query->orderByDesc('s.created_at')->get();

        $headings = ['#', 'Type', 'Entity', 'Phone', 'Period Start', 'Period End', 'Status',
                     'Gross Earnings', 'Commission', 'Tips', 'COD Collected',
                     'Orders', 'Delivery Fees', 'COD Handled',
                     'Adjustments', 'Net Payout (KHR)', 'Payment Method',
                     'Bank Name', 'Account No.', 'Account Holder',
                     'Payment Reference', 'Created At', 'Paid At'];

        $data = $rows->map(function ($s) {
            return [
                $s->id,
                ucfirst($s->settlement_type),
                $s->entity_name,
                $s->entity_phone,
                $s->period_start,
                $s->period_end,
                ucfirst($s->status),
                $s->gross_earnings,
                $s->commission_total,
                $s->tips_total,
                $s->cod_collected,
                $s->orders_count,
                $s->delivery_fees,
                $s->cod_handled,
                $s->adjustments,
                $s->net_payout,
                $s->payment_method ?? '',
                $s->bank_name ?? '',
                $s->bank_account ?? '',
                $s->account_holder ?? '',
                $s->payment_reference ?? '',
                $s->created_at,
                $s->paid_at ?? '',
            ];
        })->toArray();

        $meta = ['Generated' => now()->format('d M Y H:i'), 'Total Records' => count($data)];

        return Excel::download(
            new GenericReportExport('Payment Settlements', $headings, $data, $meta),
            'settlements-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function receipt(Settlement $settlement)
    {
        $settlement->load('user', 'creator', 'approver', 'processor');

        $lineItems = $settlement->settlement_type === 'driver'
            ? $this->driverLineItems($settlement)
            : $this->partnerLineItems($settlement);

        return view('admin.settlements.receipt', compact('settlement', 'lineItems'));
    }

    public function receiptPdf(Settlement $settlement)
    {
        $settlement->load('user', 'creator', 'approver', 'processor');

        $lineItems = $settlement->settlement_type === 'driver'
            ? $this->driverLineItems($settlement)
            : $this->partnerLineItems($settlement);

        $pdf = Pdf::loadView('admin.settlements.receipt', compact('settlement', 'lineItems'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('settlement-STLMT-' . str_pad($settlement->id, 6, '0', STR_PAD_LEFT) . '.pdf');
    }

    public function bulkApprove(Request $request)
    {
        $ids = $request->input('ids', []);
        $count = Settlement::whereIn('id', $ids)->where('status', 'pending')->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "{$count} settlement(s) approved.");
    }

    public function bulkProcess(Request $request)
    {
        $ids = $request->input('ids', []);
        $count = Settlement::whereIn('id', $ids)->where('status', 'approved')->update([
            'status'       => 'processing',
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ]);

        return back()->with('success', "{$count} settlement(s) moved to processing.");
    }

    // ─── Calculation Helpers ─────────────────────────────────────────────────

    private function calcDriver(int $driverId, string $start, string $end): array
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->endOfDay();

        $wallet = DB::table('wallet_transactions')
            ->where('user_id', $driverId)
            ->whereBetween('created_at', [$s, $e])
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $rideEarnings     = (int) ($wallet->get('trip_earning')?->total ?? 0);
        $deliveryEarnings = (int) ($wallet->get('delivery_payment')?->total ?? 0);
        $commission       = (int) ($wallet->get('platform_commission')?->total ?? 0);
        $tips             = (int) ($wallet->get('tip_in')?->total ?? 0);
        $ridesCount       = (int) ($wallet->get('trip_earning')?->cnt ?? 0);
        $deliveryCount    = (int) ($wallet->get('delivery_payment')?->cnt ?? 0);
        $grossEarnings    = $rideEarnings + $deliveryEarnings;

        $codCollected = (int) DB::table('deliveries')
            ->where('driver_id', $driverId)
            ->where('payment_by', 'recipient')
            ->whereIn('status', ['delivered', 'completed'])
            ->whereBetween('updated_at', [$s, $e])
            ->sum('package_amount');

        $netPayout = $grossEarnings - $commission + $tips - $codCollected;

        return [
            'rides_count'      => $ridesCount,
            'deliveries_count' => $deliveryCount,
            'gross_earnings'   => $grossEarnings,
            'commission_total' => $commission,
            'tips_total'       => $tips,
            'cod_collected'    => $codCollected,
            'net_payout'       => $netPayout,
            // partner fields zeroed
            'orders_count'     => 0,
            'delivery_fees'    => 0,
            'cod_handled'      => 0,
        ];
    }

    private function calcPartner(int $partnerId, string $start, string $end): array
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->endOfDay();

        $row = DB::table('deliveries')
            ->where('partner_id', $partnerId)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereBetween('updated_at', [$s, $e])
            ->selectRaw("
                COUNT(*) as orders_count,
                SUM(fee) as fee_total,
                SUM(CASE WHEN payment_by='recipient' THEN COALESCE(package_amount, 0) ELSE 0 END) as cod_total
            ")->first();

        $ordersCount = (int) ($row?->orders_count ?? 0);
        $feeTotal    = (int) ($row?->fee_total ?? 0);
        $codTotal    = (int) ($row?->cod_total ?? 0);
        $netPayout   = $codTotal - $feeTotal;

        return [
            'orders_count'     => $ordersCount,
            'delivery_fees'    => $feeTotal,
            'cod_handled'      => $codTotal,
            'net_payout'       => $netPayout,
            // driver fields zeroed
            'rides_count'      => 0,
            'deliveries_count' => 0,
            'gross_earnings'   => 0,
            'commission_total' => 0,
            'tips_total'       => 0,
            'cod_collected'    => 0,
        ];
    }

    // ─── Line items for show page ────────────────────────────────────────────

    private function driverLineItems(Settlement $s): array
    {
        $start = $s->period_start->startOfDay();
        $end   = $s->period_end->endOfDay();

        $txns = DB::table('wallet_transactions')
            ->where('user_id', $s->user_id)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('type', ['trip_earning', 'delivery_payment', 'platform_commission', 'tip_in', 'tip_out'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $items = [];
        foreach ($txns as $t) {
            $items[] = [
                'date'   => $t->created_at,
                'type'   => $t->type,
                'note'   => $t->note,
                'amount' => $t->amount,
                'credit' => in_array($t->type, ['trip_earning', 'delivery_payment', 'tip_in']) ? $t->amount : 0,
                'debit'  => in_array($t->type, ['platform_commission', 'tip_out']) ? $t->amount : 0,
            ];
        }

        if ($s->cod_collected > 0) {
            $items[] = [
                'date'   => $s->period_end,
                'type'   => 'cod_collected',
                'note'   => 'COD cash collected from recipients',
                'amount' => -$s->cod_collected,
                'credit' => 0,
                'debit'  => $s->cod_collected,
            ];
        }

        return $items;
    }

    private function partnerLineItems(Settlement $s): array
    {
        $start = $s->period_start->startOfDay();
        $end   = $s->period_end->endOfDay();

        $deliveries = DB::table('deliveries as d')
            ->leftJoin('users as drv', 'drv.id', '=', 'd.driver_id')
            ->where('d.partner_id', $s->user_id)
            ->whereIn('d.status', ['delivered', 'completed'])
            ->whereBetween('d.updated_at', [$start, $end])
            ->select('d.id', 'd.fee', 'd.package_amount', 'd.payment_by', 'd.status', 'd.updated_at', 'drv.name as driver_name')
            ->orderByDesc('d.updated_at')
            ->limit(100)
            ->get();

        $items = [];
        foreach ($deliveries as $d) {
            $items[] = [
                'date'         => $d->updated_at,
                'type'         => 'delivery_fee',
                'note'         => "Delivery #" . $d->id . ($d->driver_name ? " (Driver: {$d->driver_name})" : ''),
                'amount'       => -$d->fee,
                'credit'       => 0,
                'debit'        => $d->fee,
            ];
            if ($d->payment_by === 'recipient' && $d->package_amount > 0) {
                $items[] = [
                    'date'   => $d->updated_at,
                    'type'   => 'cod_received',
                    'note'   => "COD collected for delivery #" . $d->id,
                    'amount' => $d->package_amount,
                    'credit' => $d->package_amount,
                    'debit'  => 0,
                ];
            }
        }

        return $items;
    }
}
