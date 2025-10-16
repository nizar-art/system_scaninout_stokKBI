<?php
namespace App\Exports;

use App\Models\Forecast;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ForecastExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnFormatting
{
    protected $customer;
    protected $forecast_month;
    protected $category;
    protected $inv_id;

    public function __construct($customer = null, $forecast_month = null, $category = null, $inv_id = null)
    {
        $this->customer = $customer;
        $this->forecast_month = $forecast_month;
        $this->category = $category;
        $this->inv_id = $inv_id;
    }

    public function collection()
    {
        $query = Forecast::with(['part.customer', 'workday'])
            ->orderBy('forecast_month', 'desc')
            ->orderBy('issued_at', 'desc');

        if ($this->customer) {
            $query->whereHas('part.customer', function ($q) {
                $q->where('username', $this->customer);
            });
        }

        if ($this->forecast_month) {
            $month = \Carbon\Carbon::createFromFormat('Y-m', $this->forecast_month)
                ->startOfMonth()
                ->format('Y-m-d');
            $query->where('forecast_month', $month);
        }

        if ($this->category) {
            $query->whereHas('part', function ($q) {
                $q->where('id_category', $this->category);
            });
        }
        if ($this->inv_id) {
            $query->whereHas('part', function ($q) {
                $q->where('Inv_id', $this->inv_id);
            });
        }

        return $query->get()->map(function ($forecast, $index) {
            return [
                $index + 1,
                \Carbon\Carbon::parse($forecast->created_at)->format('d-m-y'),
                $forecast->part->Inv_id ?? '-',
                $forecast->part->Part_name ?? '-',
                $forecast->part->Part_number ?? '-',
                $forecast->part->customer->username ?? '-',
                \Carbon\Carbon::parse($forecast->forecast_month)->format('M Y'),
                // $forecast->workday ? $forecast->workday->hari_kerja : '-',
                $forecast->frequensi_delivery,
                $forecast->PO_pcs,
                $forecast->min,
                $forecast->max,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'issued at',
            'inv_id',
            'part_name',
            'part_number',
            'customer',
            'forecast_month',
            'frequensi_delivery',
            'po_pcs',
            'min',
            'Max'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType('solid')
            ->getStartColor()->setRGB('D9E1F2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(10);

        // Add borders
        $sheet->getStyle('A1:K' . $sheet->getHighestRow())->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);
    }

    public function title(): string
    {
        return 'Forecast_Data';
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'G' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'I' => NumberFormat::FORMAT_NUMBER,
            'J' => NumberFormat::FORMAT_NUMBER,
            'K' => NumberFormat::FORMAT_NUMBER,
        ];
    }
}