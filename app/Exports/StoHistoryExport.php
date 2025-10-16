<?php

namespace App\Exports;

use App\Models\PlanStock;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StoHistoryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $category_id;

    public function __construct($category_id = null)
    {
        $this->category_id = $category_id;
    }

    public function collection()
    {
        $query = PlanStock::with('inventory.part.category');

        if ($this->category_id) {
            $query->whereHas('inventory.part', function ($q) {
                $q->where('id_category', $this->category_id);
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Issued at',
            'Inv ID',
            'Part Name',
            'Part Number',
            'Kategori',
            'Plan Stock Sebelumnya',
            'Plan Stock Setelahnya',
        ];
    }

    public function map($row): array
    {
        return [
            $row->created_at ? $row->created_at->format('d-m-Y H:i:s') : '-',
            $row->inventory->part->Inv_id ?? '-',
            $row->inventory->part->Part_name ?? '-',
            $row->inventory->part->Part_number ?? '-',
            $row->inventory->part->category->name ?? '-',
            $row->plan_stock_before,
            $row->plan_stock_after,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [ // Header row
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Tanggal
            'B' => 15, // Inv ID
            'C' => 25, // Part Name
            'D' => 20, // Part Number
            'E' => 20, // Kategori
            'F' => 25, // Plan Stock Sebelumnya
            'G' => 25, // Plan Stock Setelahnya
        ];
    }
}
