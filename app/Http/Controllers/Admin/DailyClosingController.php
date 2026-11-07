<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use App\Models\PartnerClosing;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\PartnerStatementService as PS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Accountant's daily process: close drivers → close partners → finish the day. */
class DailyClosingController extends Controller
{
    public function __construct(private DailyClosingService $closing) {}

    private function date(Request $request): string
    {
        return PS::dayRange($request->input('date'))[0]->toDateString();
    }

    public function index(Request $request)
    {
        $date = $this->date($request);

        return view('admin.daily-closing', [
            'date'     => $date,
            'drivers'  => $this->closing->driversForDay($date),
            'partners' => $this->closing->partnersForDay($date),
            'day'      => DailyClosing::with('closer:id,name')->whereDate('business_date', $date)->first(),
            'rate'     => DailyClosingService::rate(),
        ]);
    }

    public function closeDriver(Request $request, User $driver)
    {
        $data = $request->validate([
            'date'              => 'required|date_format:Y-m-d',
            'received_cash_khr' => 'required|integer|min:0',
            'received_cash_usd' => 'nullable|numeric|min:0',
            'bank_verified'     => 'nullable|boolean',
            'note'              => 'nullable|string|max:500',
        ]);
        $this->closing->closeDriver($driver, $data['date'], $data, Auth::user());

        return back()->with('success', "{$driver->name} closed for {$data['date']}.");
    }

    public function reopenDriver(Request $request, User $driver)
    {
        $date = $request->validate(['date' => 'required|date_format:Y-m-d'])['date'];
        $this->closing->reopenDriver($driver, $date);
        return back()->with('success', "{$driver->name} reopened.");
    }

    public function closePartner(Request $request, User $partner)
    {
        $data = $request->validate([
            'date'          => 'required|date_format:Y-m-d',
            'settle_method' => 'required|in:' . implode(',', PartnerClosing::METHODS),
            'settle_ref'    => 'nullable|string|max:100',
            'note'          => 'nullable|string|max:500',
        ]);
        $this->closing->closePartner($partner, $data['date'], $data, Auth::user());

        return back()->with('success', 'Partner closed for ' . $data['date'] . '.');
    }

    public function reopenPartner(Request $request, User $partner)
    {
        $date = $request->validate(['date' => 'required|date_format:Y-m-d'])['date'];
        $this->closing->reopenPartner($partner, $date);
        return back()->with('success', 'Partner reopened.');
    }

    public function finish(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'note' => 'nullable|string|max:500']);
        $this->closing->finishDay($data['date'], Auth::user(), $data['note'] ?? null);

        return back()->with('success', "Day {$data['date']} finished and locked.");
    }
}
