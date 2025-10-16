<?php

namespace App\Exports;

use App\Models\DailyStockLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class DailyStockExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnFormatting, WithCustomStartCell
{
    protected $status;
    protected $category;
    protected $date;
    protected $customer;
    protected $plant;
    protected $inv_id;

    public function __construct($status = null, $category = null, $date = null, $customer = null, $plant = null, $inv_id = null)
    {
        $this->status = $status;
        $this->category = $category;
        $this->date = $date;
        $this->customer = $customer;
        $this->plant = $plant;
        $this->inv_id = $inv_id;
    }

    public function startCell(): string
    {
        // Mulai dari A2 agar A1 bisa dipakai untuk judul
        return 'A2';
    }

    public function collection()
    {
        // Tambah waktu eksekusi maksimal menjadi 120 detik
        set_time_limit(120);

        $query = DailyStockLog::with([
            'part.customer',
            'part.forecasts',
            'user',
            'areaHead.plan'
        ])
            ->orderBy('created_at', 'desc');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->category) {
            $query->whereHas('part', function ($q) {
                $q->where('id_category', $this->category);
            });
        }

        if ($this->date) {
            $query->whereDate('created_at', $this->date);
        }
        
        if ($this->customer) {
            $query->whereHas('part.customer', function ($q) {
                $q->where('username', $this->customer);
            });
        }
        
        if ($this->plant) {
            $query->whereHas('areaHead.plan', function ($q) {
                $q->where('id', $this->plant);
            });
        }
        
        if ($this->inv_id) {
            $query->whereHas('part', function ($q) {
                $q->where('Inv_id', 'like', '%' . $this->inv_id . '%');
            });
        }

        return $query->get()->map(function ($log, $index) {
            $part = optional($log->part);
            $forecastMonthDate = optional($log->created_at)->startOfMonth();

            $forecast = $part?->forecasts()
                ->whereYear('forecast_month', $forecastMonthDate->year)
                ->whereMonth('forecast_month', $forecastMonthDate->month)
                ->first();

            $forecastMin = optional($forecast)->min ?? '-';
            $forecastMax = optional($forecast)->max ?? '-';

            // Format stock_per_day dengan 1 angka di belakang koma
            $stockPerDay = $log->stock_per_day !== null
                ? number_format((float)$log->stock_per_day, 1)
                : '-';

            return [
                'No' => $index + 1,
                'Created At' => optional($log->created_at)->format('d-m-Y H:i:s'),
                'Date' => $log->date ? \Carbon\Carbon::parse($log->date)->format('d-m-Y') : '-',
                'Inv ID' => $part->Inv_id ?? '-',
                'Part Name' => $part->Part_name ?? '-',
                'Part No' => $part->Part_number ?? '-',
                'Forecast Min' => $log->forecast_min ?? '-',
                'Forecast Max' => $log->forecast_max ?? '-',
                'Total Qty' => $log->Total_qty ?? '-',
                'Stock Per Day' => $stockPerDay,
                'Area' => optional($log->areaHead)->nama_area ?? '-',
                'Plant' => optional($log->areaHead->plant)->name ?? '-',
                'Customer' => optional($part->customer)->username ?? '-',
                // 'Supplier' => $part->supplier ?? '-',
                'Status' => strtoupper($log->status ?? '-'),
                'Prepared By' => $log->user->username ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        // Header tetap, judul akan ditambahkan di baris pertama oleh WithStyles
        return [
            'No',
            'Created At',
            'Date',
            'Inv ID',
            'Part Name',
            'Part No',
            'Forecast Min',
            'Forecast Max',
            'Total Qty',
            'Stock Per Day',
            'Area',
            'Plant',
            'Customer',
            // 'Supplier',
            'Status',
            'Prepared By'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Tambahkan judul di baris pertama
        $sheet->mergeCells('A1:O1');
        $sheet->setCellValue('A1', 'DAILY STOCK REPORT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A1')->getAlignment()->setVertical('center');

        // Style header
        $sheet->getRowDimension(2)->setRowHeight(25); // Perbesar tinggi header
        $sheet->getStyle('A2:O2')->getFont()->setBold(true);
        $sheet->getStyle('A2:O2')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A2:O2')->getAlignment()->setVertical('center');
        $sheet->getStyle('A2:O2')->getFill()->setFillType('solid')->getStartColor()->setRGB('D9E1F2');

        // Set column width
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
        $widths = [5, 20, 15, 15, 30, 20, 15, 15, 15, 15, 20, 20, 20, 15, 20];

        foreach ($columns as $index => $column) {
            $sheet->getColumnDimension($column)->setWidth($widths[$index]);
        }

        // Add borders to entire table
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A2:O' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Wrap text untuk seluruh tabel
        $sheet->getStyle('A2:O' . $lastRow)->getAlignment()->setWrapText(true);

        // Freeze header
        $sheet->freezePane('A3');
    }

    public function title(): string
    {
        return 'Daily_Stock';
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'C' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'G' => NumberFormat::FORMAT_NUMBER,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER,
            'J' => NumberFormat::FORMAT_NUMBER,
        ];
    }
}