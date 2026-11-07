<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** One styled sheet of the Partner report workbook. */
class PartnerReportSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private string $name,
        private array $rows,
        private array $headerRows = [],
        private array $sectionRows = [],
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->name;
    }

    public function styles(Worksheet $sheet): void
    {
        $lastCol = $sheet->getHighestColumn();
        foreach ($this->headerRows as $r) {
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF00B14F']],
            ]);
        }
        foreach ($this->sectionRows as $r) {
            $sheet->getStyle("A{$r}:B{$r}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F7EE']],
            ]);
        }
        $sheet->freezePane('A2');
    }
}
