<?php

namespace App\Exports;

use App\Services\PartnerStatementService as PS;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Daily Partner statement workbook: Summary · Successful · Unsuccessful. */
class PartnerStatementExport implements WithMultipleSheets
{
    public function __construct(private array $s) {}

    public function sheets(): array
    {
        $s = $this->s; $m = $s['summary'];
        $date = $s['from'] === $s['to'] ? $s['from'] : "{$s['from']} → {$s['to']}";
        $paidBy = ['partner_prepaid' => 'Partner prepaid / ដៃគូបង់រួច', 'partner_from_cod' => 'From COD / កាត់ពី COD', 'partner_pays' => 'Partner pays ROTEH / ដៃគូបង់'];

        $summary = [
            ['ROTEH Partner Statement / របាយការណ៍ដៃគូ — ' . $s['statement_no']],
            ['Partner / ដៃគូ', $s['partner']['business_name']],
            ['Phone / ទូរស័ព្ទ', $s['partner']['phone']],
            ['Closing date / ថ្ងៃបិទបញ្ជី', $date],
            [],
            ['Successful / កញ្ចប់ដឹកជោគជ័យ', $m['successful_count']],
            ['· with COD / មាន COD', $m['cod_packages']],
            ['· without COD / គ្មាន COD', $m['non_cod_packages']],
            ['COD collected from receivers / COD បានប្រមូល (៛)', $m['cod_collected']],
            ['ROTEH delivery fees (charged to partner) / ថ្លៃដឹក ROTEH (៛)', $m['service_fees']],
            ['Delivery fees settled by / ការទូទាត់ថ្លៃដឹក', ''],
            ['· prepaid by partner / ដៃគូបង់រួច', $m['fees_prepaid']],
            ['· deducted from COD / កាត់ពី COD', $m['fees_from_cod'] - $m['failed_fees']],
            [],
            ['Unsuccessful / កញ្ចប់ដឹកមិនបានជោគជ័យ', $m['unsuccessful_count']],
            ['· returned to shop / ប្រគល់មកហាងវិញ', $m['returned_count']],
            ['· returning / កំពុងយកត្រឡប់', $m['returning_count']],
            ['· cancelled / បានបោះបង់', $m['cancelled_count']],
            ['COD not collected / COD មិនបានប្រមូល (៛)', $m['cod_not_collected']],
            ['Fees charged for unsuccessful / ថ្លៃគិតលើកញ្ចប់មិនជោគជ័យ (៛)', $m['failed_fees']],
            [],
            ['ROTEH pays partner / ROTEH ត្រូវបង់ដៃគូ (៛)', $m['roteh_pays_partner']],
            ['Partner pays ROTEH / ដៃគូត្រូវបង់ ROTEH (៛)', $m['partner_pays_roteh']],
            [$m['net_to_partner'] < 0 ? 'BALANCE — Partner pays ROTEH / សមតុល្យ ដៃគូបង់ ROTEH (៛)' : 'BALANCE — ROTEH pays partner / សមតុល្យ ROTEH បង់ដៃគូ (៛)', abs($m['net_to_partner'])],
            [],
            ['All statuses / គ្រប់ស្ថានភាព', $m['total_count'] ?? 0],
        ];

        $ok = [['#', 'Code', 'Reference', 'Delivered at / ពេលដឹកដល់', 'Receiver / អ្នកទទួល', 'Phone', 'Address / អាសយដ្ឋាន',
            'Size', 'Express', 'Driver / អ្នកបើកបរ', 'COD (៛)', 'Delivery fee (៛)', 'Fee settled / ការទូទាត់ថ្លៃដឹក',
            'ROTEH pays partner (៛)', 'Partner pays ROTEH (៛)']];
        foreach ($s['successful'] as $i => $r) {
            $ok[] = [$i + 1, $r['code'], $r['reference'], $r['completed_at'], $r['receiver_name'], $r['receiver_phone'], $r['dropoff_address'],
                $r['package_size'], $r['express'] ? 'Yes' : '', $r['driver'], $r['cod_collected'], $r['fee'], $paidBy[$r['fee_paid_by']] ?? $r['fee_paid_by'],
                $r['roteh_pays_partner'], $r['partner_pays_roteh']];
        }
        $okRows = collect($s['successful']);
        $ok[] = ['', 'TOTAL / សរុប', '', '', '', '', '', '', '', '', $m['cod_collected'], $m['service_fees'], '',
            $okRows->sum('roteh_pays_partner'), $okRows->sum('partner_pays_roteh')];

        $bad = [['#', 'Code', 'Reference', 'Status / ស្ថានភាព', 'Closed at / ពេលបិទ', 'Reason / មូលហេតុ', 'Note', 'Receiver / អ្នកទទួល', 'Phone',
            'Address / អាសយដ្ឋាន', 'Driver / អ្នកបើកបរ', 'COD not collected (៛)', 'Delivery fee (៛)', 'Partner pays ROTEH (៛)']];
        foreach ($s['unsuccessful'] as $i => $r) {
            $st = PS::UNSUCCESSFUL_STATUS[$r['status']] ?? [$r['status'], ''];
            $re = $r['reason'] ? (PS::REASONS[$r['reason']] ?? [$r['reason'], '']) : null;
            $bad[] = [$i + 1, $r['code'], $r['reference'], "{$st[0]} / {$st[1]}", $r['closed_at'], $re ? "{$re[0]} / {$re[1]}" : '', $r['note'],
                $r['receiver_name'], $r['receiver_phone'], $r['dropoff_address'], $r['driver'], $r['cod_amount'], $r['fee'], $r['fee_charged']];
        }
        $bad[] = ['', 'TOTAL / សរុប', '', '', '', '', '', '', '', '', '', $m['cod_not_collected'], collect($s['unsuccessful'])->sum('fee'), $m['failed_fees']];

        $all = [['#', 'Code', 'Reference', 'Created / បង្កើត', 'Status / ស្ថានភាព', 'Reason / មូលហេតុ', 'Closed at / ពេលបិទ',
            'Receiver / អ្នកទទួល', 'Phone', 'Address / អាសយដ្ឋាន', 'Driver / អ្នកបើកបរ', 'COD (៛)', 'Service fee (៛)']];
        foreach ($s['all'] ?? [] as $i => $r) {
            $st = PS::STATUS_LABELS[$r['group']] ?? [$r['group'], ''];
            $re = $r['reason'] ? (PS::REASONS[$r['reason']] ?? [$r['reason'], '']) : null;
            $all[] = [$i + 1, $r['code'], $r['reference'], $r['created_at'], "{$st[0]} / {$st[1]}", $re ? "{$re[0]} / {$re[1]}" : '',
                $r['closed_at'], $r['receiver_name'], $r['receiver_phone'], $r['dropoff_address'], $r['driver'], $r['cod_amount'], $r['fee']];
        }
        foreach (PS::STATUS_LABELS as $g => $lbl) {
            $summary[] = ["{$lbl[0]} / {$lbl[1]}", $m['status_counts'][$g] ?? 0];
        }

        return [
            new PartnerReportSheet('Summary', $summary, headerRows: [1], sectionRows: [6, 16, 25]),
            new PartnerReportSheet('Successful', $ok, headerRows: [1], sectionRows: [count($ok)]),
            new PartnerReportSheet('Unsuccessful', $bad, headerRows: [1], sectionRows: [count($bad)]),
            new PartnerReportSheet('All packages', $all, headerRows: [1]),
        ];
    }
}
