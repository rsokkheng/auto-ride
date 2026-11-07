<?php

namespace App\Http\Controllers;

use App\Exports\PartnerStatementExport;
use App\Models\Delivery;
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

    public function adminIndex(Request $request)
    {
        [$from, $to] = PS::dayRange($request->query('date'));
        $appTz = config('app.timezone');
        [$a, $b] = [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)];

        // Every partner with something closed that day.
        $ids = Delivery::whereNotNull('partner_id')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereIn('status', ['delivered', 'completed'])->whereBetween('completed_at', [$a, $b]))
                ->orWhere(fn ($w) => $w->where('status', 'delivery_failed')->whereBetween('failed_at', [$a, $b]))
                ->orWhere(fn ($w) => $w->where('status', 'returned')->whereBetween('returned_at', [$a, $b]))
                ->orWhere(fn ($w) => $w->where('status', 'cancelled')->whereBetween(\DB::raw('COALESCE(cancelled_at, updated_at)'), [$a, $b])))
            ->distinct()->pluck('partner_id');

        $rows = User::whereIn('id', $ids)->with('partnerProfile')->orderBy('name')->get()
            ->map(fn (User $p) => ['partner' => $p, 'summary' => $this->statements->build($p, $from, $to)['summary']]);

        $totals = [
            'successful'   => $rows->sum(fn ($r) => $r['summary']['successful_count']),
            'unsuccessful' => $rows->sum(fn ($r) => $r['summary']['unsuccessful_count']),
            'cod'          => $rows->sum(fn ($r) => $r['summary']['cod_collected']),
            'fees'         => $rows->sum(fn ($r) => $r['summary']['service_fees']),
            'net'          => $rows->sum(fn ($r) => $r['summary']['net_to_partner']),
        ];

        return view('admin.partner-statements', ['rows' => $rows, 'totals' => $totals, 'date' => $from->toDateString()]);
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
        return in_array($request->query('type'), ['successful', 'unsuccessful'], true) ? $request->query('type') : 'both';
    }

    private function switchUrls(callable $url): array
    {
        return [
            '✅ ជោគជ័យ · Successful'       => $url('successful'),
            '⚠️ មិនជោគជ័យ · Unsuccessful' => $url('unsuccessful'),
            '📄 ទាំងពីរ · Both'            => $url('both'),
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
