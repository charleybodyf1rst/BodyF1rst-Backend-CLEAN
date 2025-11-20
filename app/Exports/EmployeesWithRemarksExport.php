<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesWithRemarksExport implements FromCollection, WithHeadings, WithEvents, WithStyles, ShouldAutoSize
{
    private $rowsWithRemarks = [];
    const ADDED_SUCCESSFULLY = 'Added successfully';
    const POC_ADDED_SUCCESSFULLY = 'Ready To Go';
    public function __construct(array $rowsWithRemarks)
    {
        $this->rowsWithRemarks = [];
        $this->rowsWithRemarks = $rowsWithRemarks;
    }

    public function collection()
    {
        return collect($this->rowsWithRemarks);
    }

    public function headings(): array
    {
        return [
            'First Name',
            'Last Name',
            'Email',
            'Department',
            'Remark'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Set header colors and font styles
        $sheet->getStyle('A1:E1')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => Color::COLOR_BLACK],
            ],
            'font' => [
                'color' => ['rgb' => Color::COLOR_WHITE],
                'bold' => true,
            ],
        ]);

        // Apply borders to all cells
        $sheet->getStyle('A1:E' . $sheet->getHighestRow())->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->rowsWithRemarks as $index => $row) {
                    $rowIndex = $index + 2;
                    if ($row['remark'] === self::ADDED_SUCCESSFULLY || $row['remark'] === self::POC_ADDED_SUCCESSFULLY) {
                        $sheet->getStyle("A{$rowIndex}:E{$rowIndex}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => 'C6EFCE'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => '000000'],
                                ],
                            ],
                        ]);
                    } else {
                        $sheet->getStyle("A{$rowIndex}:E{$rowIndex}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => 'FFC0C0'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => 'FF0000'],
                                ],
                            ],
                        ]);
                    }
                }
            }
        ];
    }
}
