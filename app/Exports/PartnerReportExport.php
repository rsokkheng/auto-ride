<?php

namespace App\Exports;

use App\Models\Delivery;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Partner package report (Passenger app → Reports → Excel): Summary, Breakdown, Packages. */
class PartnerReportExport implements WithMultipleSheets
{
    public const STATUS = [
        'pending' => 'Pending / កំពុងរង់ចាំ', 'confirmed' => 'Confirmed / បានបញ្ជាក់',
        'driver_assigned' => 'Driver assigned / មានអ្នកបើកបរ', 'picked_up' => 'Picked up / បានមកយក',
        'in_transit' => 'In transit / កំពុងដឹក', 'completed' => 'Completed / បានបញ្ចប់',
        'failed' => 'Not delivered / មិនបានដឹកដល់', 'returned' => 'Returned to shop / ប្រគល់មកហាងវិញ',
        'cancelled' => 'Cancelled / បានបោះបង់',
    ];

    public const REASONS = [
        'cannot_contact' => 'Cannot contact receiver / មិនអាចទាក់ទងអ្នកទទួល',
        'receiver_refused' => 'Receiver refused / អ្នកទទួលបដិសេធ',
        'wrong_address' => 'Wrong address / អាសយដ្ឋានខុស',
        'receiver_postponed' => 'Receiver asked to deliver later / ស្នើឲ្យដឹកពេលក្រោយ',
        'other' => 'Other / ផ្សេងៗ',
    ];

    public function __construct(private array $report, private Collection $rows, private string $business) {}

    public function sheets(): array
    {
        $r = $this->report;
        $t = $r['totals'];
        $m = $r['money'];
        $period = ['day' => 'Daily / ប្រចាំថ្ងៃ', 'week' => 'Weekly / ប្រចាំសប្តាហ៍', 'month' => 'Monthly / ប្រចាំខែ'][$r['period']] ?? $r['period'];
        $range  = $r['from'] === $r['to'] ? $r['from'] : "{$r['from']} → {$r['to']}";

        $summary = [
            ['ROTEH Partner Report — ' . $this->business],
            ["{$period} · {$range}"],
            [],
            ['Packages / កញ្ចប់', ''],
            ['Total packages / កញ្ចប់សរុប', $t['total']],
            ['Completed / បានបញ្ចប់', $t['completed']],
            ['Not delivered (returning) / មិនបានដឹកដល់', $t['failed']],
            ['Returned to shop / ប្រគល់មកហាងវិញ', $t['returned']],
            ['In progress / កំពុងដំណើរការ', $t['in_progress']],
            ['Cancelled / បានបោះបង់', $t['cancelled']],
            ['Success rate / អត្រាជោគជ័យ', $r['success_rate'] === null ? '—' : $r['success_rate'] . '%'],
            ['Avg. delivery time (min) / រយៈពេលដឹកជាមធ្យម', $r['avg_delivery_minutes'] ?? '—'],
            [],
            ['Money (KHR) / ប្រាក់ (៛)', ''],
            ['Delivery fees / ថ្លៃដឹកសរុប', $m['delivery_fees']],
            ['COD collected / COD បានប្រមូល', $m['cod_collected']],
            ['COD still to collect / COD ត្រូវប្រមូល', $m['cod_pending']],
            [],
            ['Why not delivered / មូលហេតុមិនបានដឹកដល់', ''],
        ];
        foreach ($r['failure_reasons'] as $k => $n) $summary[] = [self::REASONS[$k] ?? $k, $n];

        $breakdown = [[$r['period'] === 'day' ? 'Hour / ម៉ោង' : 'Date / ថ្ងៃ', 'Total / សរុប', 'Completed / បានបញ្ចប់', 'Not delivered / មិនបានដឹកដល់', 'Cancelled / បានបោះបង់']];
        foreach ($r['series'] as $b) {
            $breakdown[] = [$r['period'] === 'day' ? $b['key'] . ':00' : $b['key'], $b['total'], $b['completed'], $b['not_delivered'], $b['cancelled']];
        }

        $tz = config('ride.local_timezone', 'Asia/Phnom_Penh');
        $packages = [[
            'Code', 'Created / បង្កើត', 'Status / ស្ថានភាព', 'Reason / មូលហេតុ', 'Reference', 'Receiver / អ្នកទទួល', 'Phone',
            'Delivery address / អាសយដ្ឋានដឹកជូន', 'Type', 'Size', 'Weight (kg)', 'Speed', 'Fee (៛)', 'COD (៛)', 'Who pays', 'Completed at',
        ]];
        foreach ($this->rows as $d) {
            /** @var Delivery $d */
            $ps = $d->packageStatus();
            $packages[] = [
                'PKG-' . str_pad((string) $d->id, 6, '0', STR_PAD_LEFT),
                $d->created_at?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                self::STATUS[$ps] ?? $ps,
                in_array($ps, ['failed', 'returned'], true) ? (self::REASONS[$d->failure_reason ?? 'other'] ?? $d->failure_reason) : '',
                $d->partner_reference,
                $d->recipient_name,
                $d->recipient_phone,
                $d->dropoff_address,
                $d->package_type,
                $d->package_size,
                $d->package_weight,
                $d->service_option ?? 'normal',
                (int) $d->fee,
                (int) $d->package_amount,
                $d->payment_by === 'recipient' ? 'Receiver' : 'Partner',
                $d->completed_at?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
            ];
        }

        return [
            new PartnerReportSheet('Summary', $summary, headerRows: [1], sectionRows: [4, 14, 19]),
            new PartnerReportSheet($r['period'] === 'day' ? 'By hour' : 'By day', $breakdown, headerRows: [1]),
            new PartnerReportSheet('Packages', $packages, headerRows: [1]),
        ];
    }
}
