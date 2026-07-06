<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class GenericReportExport implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private int $headingRow;
    private int $totalCols;

    public function __construct(
        private string $reportTitle,
        private array  $headings,
        private array  $rows,
        private array  $meta = []
    ) {
        // row 1 = title, row 2..n = meta, row n+1 = column headings
        $this->headingRow = 2 + count($this->meta);
        $this->totalCols  = count($this->headings);
    }

    public function array(): array
    {
        $out = [];

        // Title row
        $out[] = [$this->reportTitle];

        // Meta rows (Generated At, Period, etc.)
        foreach ($this->meta as $label => $value) {
            $out[] = ["{$label}: {$value}"];
        }

        // Column headings row
        $out[] = $this->headings;

        // Data rows
        foreach ($this->rows as $row) {
            $out[] = array_values((array) $row);
        }

        return $out;
    }

    public function title(): string
    {
        return mb_substr($this->reportTitle, 0, 31);
    }

    public function styles(Worksheet $sheet): void
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalCols);
        $dataEnd = $this->headingRow + count($this->rows);

        // Merge title across all columns
        if ($this->totalCols > 1) {
            $sheet->mergeCells("A1:{$lastCol}1");
        }
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1e3a5f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Meta rows
        for ($r = 2; $r < $this->headingRow; $r++) {
            if ($this->totalCols > 1) {
                $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            }
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF374151']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFf0f4ff']],
            ]);
        }

        // Column heading row
        $hr = $this->headingRow;
        $sheet->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($hr)->setRowHeight(18);

        // Data rows: alternating background
        for ($r = $hr + 1; $r <= $dataEnd; $r++) {
            $bg = ($r % 2 === 0) ? 'FFFAFAFA' : 'FFFFFFFF';
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
            ]);
        }

        // Border on data range
        if ($dataEnd >= $hr) {
            $sheet->getStyle("A{$hr}:{$lastCol}{$dataEnd}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']],
                ],
            ]);
        }
    }
}
