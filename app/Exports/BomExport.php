<?php

namespace App\Exports;

use App\Models\Bom;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BomExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $search;

    public function __construct($search = null)
    {
        $this->search = $search;
    }

    public function collection()
    {
        $query = Bom::with(['product', 'component'])
            ->orderBy('updated_at', 'desc');

        if ($this->search) {
            $query->whereHas('product', function($q) {
                $q->where('Inv_id', 'like', "%{$this->search}%")
                  ->orWhere('Part_name', 'like', "%{$this->search}%");
            })->orWhereHas('component', function($q) {
                $q->where('Inv_id', 'like', "%{$this->search}%")
                  ->orWhere('Part_name', 'like', "%{$this->search}%");
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Created/Revised Date',
            'Product Code',
            'Product Name',
            'Component Code',
            'Component Name',
            'Quantity',
            'Unit'
        ];
    }

    public function map($bom): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $bom->updated_at->format('Y-m-d H:i:s'),
            $bom->product->Inv_id,
            $bom->product->Part_name,
            $bom->component->Inv_id,
            $bom->component->Part_name,
            number_format($bom->quantity, 2),
            $bom->unit
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row style
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => 'center']
            ],
            // Data rows style
            'A2:G'.$sheet->getHighestRow() => [
                'alignment' => ['vertical' => 'center']
            ],
            // Quantity column
            'G2:G'.$sheet->getHighestRow() => [
                'alignment' => ['horizontal' => 'right']
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,  // No
            'B' => 20, // Date
            'C' => 15, // Product Code
            'D' => 30, // Product Name
            'E' => 15, // Component Code
            'F' => 30, // Component Name
            'G' => 12,  // Quantity
            'H' => 10   // Unit
        ];
    }
}
