<?php

namespace App\Exports;

use App\Models\Inventory;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StoExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    protected $categoryId;
    protected $remark;
    protected $invId;
    protected $month;

    public function __construct($categoryId = null, $remark = null, $invId = null, $month = null)
    {
        $this->categoryId = $categoryId;
        $this->remark = $remark;
        $this->invId = $invId;
        $this->month = $month;
    }

    public function query()
    {
        return Inventory::query()
            ->with('part.category', 'part.plant', 'part.area')
            ->when($this->categoryId, function($q) {
                $q->whereHas('part', fn($q) =>
                    $q->where('id_category', $this->categoryId));
            })
            ->when($this->remark, function($q) {
                $q->where('remark', $this->remark);
            })
            ->when($this->invId, function($q) {
                $q->whereHas('part', function($q) {
                    $q->where('Inv_id', 'like', '%' . $this->invId . '%');
                });
            })
            ->when($this->month, function($q) {
                $parts = explode('-', $this->month);
                if(count($parts) == 2) {
                    $q->whereYear('created_at', $parts[0])
                      ->whereMonth('created_at', $parts[1]);
                }
            })
            ->orderBy('updated_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'INV ID',
            'Part Number',
            'Part Name',
            'Category',
            'Plant',
            'Area',
            'Plan Stock',
            'Actual Stock',
            'Status',
        ];
    }

    public function map($inventory): array
    {
        return [
            $inventory->part->Inv_id ?? '-',
            $inventory->part->Part_number ?? '-',
            $inventory->part->Part_name ?? '-',
            optional($inventory->part->category)->name ?? '-',
            optional($inventory->part->plant)->name ?? '-',
            optional($inventory->part->area)->name ?? '-',
            number_format($inventory->plan_stock ?? 0, 0, ',', '.'),
            number_format($inventory->act_stock ?? 0, 0, ',', '.'),
            $inventory->remark ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style untuk header
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Style untuk seluruh tabel (border)
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:I' . $highestRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Optional: Auto size kolom
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}