<?php

namespace App\Http\Controllers;

use App\Exports\PartnerStatementExport;
use App\Models\User;
use App\Services\PartnerStatementService as PS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Daily Partner statements (successful / unsuccessful), printable in the
 * browser (Khmer renders correctly → "Save as PDF") and as Excel:
 *   • ROTEH admin   /admin/partner-statements           (print & hand to partner)
 *   • Partner web   /partner/statements                 (reconcile)
 *   • Partner app   signed link from /v1/partner/statements/link
 */
class PartnerStatementController extends Controller
{
    public function __construct(private PS $statements) {}

    // ── ROTEH admin ──────────────────────────────────────────────────────────

    /**
     * All partners for a closing date — every status, with filters:
     * search, status (pending / open / successful / unsuccessful / returned /
     * owes), sort and paging. The same filtered list prints / exports.
     */
    public function adminIndex(Request $request)
    {
        [$rows, $from] = $this->overviewRows($request);

        $totals = $this->overviewTotals($rows);
        $perPage = min(max((int) $request->query('per_page', 25), 10), 100);
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.partner-statements', [
            'rows' => $paginator, 'totals' => $totals, 'date' => $from->toDateString(),
            'filters' => $request->only(['search', 'status', 'sort', 'per_page']),
        ]);
    }

    /** Printable all-partners report for the filtered list. */
    public function adminOverviewPrint(Request $request)
    {
        [$rows, $from] = $this->overviewRows($request);

        return view('statements.overview', [
            'rows' => $rows, 'totals' => $this->overviewTotals($rows), 'date' => $from->toDateString(),
            'filters' => $request->only(['search', 'status', 'sort']),
            'backUrl' => route('admin.partner-statements', $request->query()),
            'excelUrl' => route('admin.partner-statements.overview.excel', $request->query()),
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    public function adminOverviewExcel(Request $request)
    {
        [$rows, $from] = $this->overviewRows($request);
        $t = $this->overviewTotals($rows);
        $L = PS::STATUS_LABELS;

        $headings = ['#', 'Partner / ដៃគូ', 'Phone', 'Total / សរុប'];
        foreach ($L as $g => $lbl) $headings[] = "{$lbl[0]} / {$lbl[1]}";
        array_push($headings, 'Total collected (៛)', 'COD (៛)', 'ROTEH delivery fees (៛)', 'ROTEH pays partner (៛)', 'Partner pays ROTEH (៛)', 'Balance (៛, − = partner pays)');

        $data = $rows->values()->map(function ($r, $i) use ($L) {
            $line = [$i + 1, $r['name'], $r['phone'], $r['total']];
            foreach (array_keys($L) as $g) $line[] = $r['counts'][$g];
            return array_merge($line, [$r['total_collected'], $r['cod_collected'], $r['service_fees'], $r['roteh_pays_partner'], $r['partner_pays_roteh'], $r['net_to_partner']]);
        })->all();
        $totalLine = ['', 'TOTAL / សរុប', '', $t['total']];
        foreach (array_keys($L) as $g) $totalLine[] = $t['counts'][$g];
        $data[] = array_merge($totalLine, [$t['collected'], $t['cod'], $t['fees'], $t['roteh_pays'], $t['partner_pays'], $t['net']]);

        $export = new \App\Exports\GenericReportExport('Partner Statements ' . $from->toDateString(), $headings, $data, [
            'Closing date / ថ្ងៃបិទបញ្ជី' => $from->format('d M Y'),
            'Partners' => $rows->count(),
            'Generated' => now(PS::tz())->format('d M Y H:i'),
        ]);

        return Excel::download($export, 'ROTEH_partner_statements_' . $from->format('Ymd') . '.xlsx');
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: \Carbon\Carbon} */
    private function overviewRows(Request $request): array
    {
        [$from, $to] = PS::dayRange($request->query('date'));
        $ov = $this->statements->overview($from, $to);

        $search = trim((string) $request->query('search', ''));
        $users = User::whereIn('id', $ov->keys())
            ->with('partnerProfile')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhereHas('partnerProfile', fn ($p) => $p->where('business_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
            ->get();

        $rows = $users->map(fn (User $u) => $ov[$u->id] + [
            'partner' => $u,
            'name'    => $u->partnerProfile?->business_name ?: ($u->company_name ?: $u->name),
            'phone'   => $u->partnerProfile?->phone ?: $u->phone,
        ]);

        $rows = match ($request->query('status')) {
            'pending'      => $rows->filter(fn ($r) => $r['counts']['pending'] > 0),
            'open'         => $rows->filter(fn ($r) => $r['open'] > 0),
            'successful'   => $rows->filter(fn ($r) => $r['successful'] > 0),
            'unsuccessful' => $rows->filter(fn ($r) => $r['unsuccessful'] > 0),
            'returned'     => $rows->filter(fn ($r) => $r['counts']['returned'] + $r['counts']['failed'] > 0),
            'owes'         => $rows->filter(fn ($r) => $r['net_to_partner'] < 0),
            default        => $rows,
        };

        $rows = match ($request->query('sort', 'total')) {
            'name'         => $rows->sortBy(fn ($r) => mb_strtolower($r['name'])),
            'net'          => $rows->sortByDesc('net_to_partner'),
            'cod'          => $rows->sortByDesc('cod_collected'),
            'unsuccessful' => $rows->sortByDesc('unsuccessful'),
            'open'         => $rows->sortByDesc('open'),
            default        => $rows->sortByDesc('total'),
        };

        return [$rows->values(), $from];
    }

    private function overviewTotals(\Illuminate\Support\Collection $rows): array
    {
        $counts = [];
        foreach (array_keys(PS::STATUS_LABELS) as $g) $counts[$g] = $rows->sum(fn ($r) => $r['counts'][$g]);
        return [
            'partners'      => $rows->count(),
            'total'         => $rows->sum('total'),
            'counts'        => $counts,
            'successful'    => $rows->sum('successful'),
            'unsuccessful'  => $rows->sum('unsuccessful'),
            'open'          => $rows->sum('open'),
            'cod'           => $rows->sum('cod_collected'),
            'fees'          => $rows->sum('service_fees'),
            'fees_from_cod' => $rows->sum('fees_from_cod'),
            'failed_fees'   => $rows->sum('failed_fees'),
            'net'           => $rows->sum('net_to_partner'),
            'collected'     => $rows->sum('total_collected'),
            'roteh_pays'    => $rows->sum('roteh_pays_partner'),
            'partner_pays'  => $rows->sum('partner_pays_roteh'),
        ];
    }

    public function adminShow(Request $request, User $partner)
    {
        abort_unless($partner->role === 'partner' || $partner->partnerProfile, 404);
        $date = $request->query('date');
        $type = $this->type($request);

        return $this->render($partner, $date, $type, [
            'excelUrl'   => route('admin.partner-statements.excel', [$partner, 'date' => $date]),
            'backUrl'    => route('admin.partner-statements', ['date' => $date]),
            'dateAction' => route('admin.partner-statements.show', $partner),
            'dateKeep'   => ['type' => $type],
            'switchUrls' => $this->switchUrls(fn ($t) => route('admin.partner-statements.show', [$partner, 'date' => $date, 'type' => $t])),
            'autoPrint'  => $request->boolean('print'),
        ]);
    }

    public function adminExcel(Request $request, User $partner)
    {
        abort_unless($partner->role === 'partner' || $partner->partnerProfile, 404);
        return $this->excel($partner, $request->query('date'));
    }

    // ── Partner website (guard: partner) ────────────────────────────────────

    public function portalShow(Request $request)
    {
        $partner = Auth::guard('partner')->user();
        $date = $request->query('date');
        $type = $this->type($request);

        return $this->render($partner, $date, $type, [
            'excelUrl'   => route('partner.statements.excel', ['date' => $date]),
            'backUrl'    => route('partner.dashboard'),
            'dateAction' => route('partner.statements'),
            'dateKeep'   => ['type' => $type],
            'switchUrls' => $this->switchUrls(fn ($t) => route('partner.statements', ['date' => $date, 'type' => $t])),
        ]);
    }

    public function portalExcel(Request $request)
    {
        return $this->excel(Auth::guard('partner')->user(), $request->query('date'));
    }

    // ── Partner app (temporary signed link, opened in the phone's browser) ──

    public static function signedLink(User $partner, ?string $date, string $type): array
    {
        $exp = now()->addMinutes(30);
        return [
            'print_url' => URL::temporarySignedRoute('statements.partner.signed', $exp, ['partner' => $partner->id, 'date' => $date, 'type' => $type]),
            'excel_url' => URL::temporarySignedRoute('statements.partner.signed.excel', $exp, ['partner' => $partner->id, 'date' => $date]),
            'expires_at' => $exp->toIso8601String(),
        ];
    }

    public function signedShow(Request $request, User $partner)
    {
        $date = $request->query('date');
        $type = $this->type($request);
        $links = self::signedLink($partner, $date, $type);

        return $this->render($partner, $date, $type, [
            'excelUrl'   => $links['excel_url'],
            'switchUrls' => $this->switchUrls(fn ($t) => self::signedLink($partner, $date, $t)['print_url']),
        ]);
    }

    public function signedExcel(Request $request, User $partner)
    {
        return $this->excel($partner, $request->query('date'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function type(Request $request): string
    {
        return in_array($request->query('type'), ['successful', 'unsuccessful', 'all'], true) ? $request->query('type') : 'both';
    }

    private function switchUrls(callable $url): array
    {
        return [
            '✅ ជោគជ័យ · Successful'       => $url('successful'),
            '⚠️ មិនជោគជ័យ · Unsuccessful' => $url('unsuccessful'),
            '📄 ទាំងពីរ · Both'            => $url('both'),
            '📋 គ្រប់ស្ថានភាព · All statuses' => $url('all'),
        ];
    }

    private function render(User $partner, ?string $date, string $type, array $extra)
    {
        [$from, $to] = PS::dayRange($date);
        return view('statements.partner', $extra + [
            'statement' => $this->statements->build($partner, $from, $to),
            'type'      => $type,
        ]);
    }

    private function excel(User $partner, ?string $date)
    {
        [$from, $to] = PS::dayRange($date);
        $s = $this->statements->build($partner, $from, $to);
        return Excel::download(new PartnerStatementExport($s), $s['statement_no'] . '.xlsx');
    }
}
