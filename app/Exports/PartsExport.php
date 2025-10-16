<?php

namespace App\Exports;

use App\Models\Part;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PartsExport implements FromCollection, WithHeadings, WithStyles, WithMapping, ShouldAutoSize
{
    protected $category_id;
    protected $export_type; // 'customer' atau 'supplier'

    public function __construct($category_id = null, $export_type = 'all')
    {
        $this->category_id = $category_id;
        $this->export_type = $export_type;
    }

    public function collection()
    {
        $query = Part::with(['category', 'customer', 'package', 'plant', 'area']);

        if ($this->category_id) {
            $query->where('id_category', $this->category_id);
        }

        switch ($this->export_type) {
            case 'customer':
                $query->whereHas('category', function ($q) {
                    $q->whereIn('name', ['Finished Good', 'WIP']);
                });
                break;

            case 'supplier':
                $query->whereHas('category', function ($q) {
                    $q->whereIn('name', ['Raw Material', 'ChildPart', 'Packaging']);
                });
                break;
        }

        return $query->get();
    }

    public function map($part): array
    {
        return [
            $part->Inv_id,
            $part->Part_name,
            $part->Part_number,
            $part->category->name ?? null,
            $part->customer->username ?? null,
            $part->package->type_pkg ?? null,
            $part->package->qty ?? null,
            $part->plant->name ?? null,
            $part->area->nama_area ?? null,
            $part->supplier ?? null,
            $part->subcont ?? null,
        ];
    }

    public function headings(): array
    {
        $baseHeadings = [
            'inv_id',
            'part_name',
            'part_number',
            'kategori',
            'customer',
            'type_pkg',
            'qty_kanban',
            'plan',
            'area',
        ];

        // Tambahkan kolom supplier dan subcont
        return array_merge($baseHeadings, ['supplier', 'subcont']);
    }

    public function styles(Worksheet $sheet)
    {
        // Header (baris pertama)
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => 'thin',
                ],
            ],
        ]);

        // Semua sel (otomatis border dan wrap text)
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:K$highestRow")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle("A2:K$highestRow")->getAlignment()->setWrapText(true);

        return [];
    }
}
