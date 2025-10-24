<?php

namespace App\Exports;

use App\Models\StockScanHistory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockScanHistoryExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filters;
    protected $rowNumber = 0; // ✅ Tambahkan counter manual

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = StockScanHistory::with(['user', 'part']);

        if (!empty($this->filters['inventory_id'])) {
            $query->whereHas('part', function ($q) {
                $q->where('Inv_id', 'like', '%' . $this->filters['inventory_id'] . '%');
            });
        }

        if (!empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['scan_date'])) {
            $query->whereDate('scanned_at', $this->filters['scan_date']);
        }

        return $query->orderBy('scanned_at', 'desc')->get();
    }

    public function map($item): array
    {
        // ✅ Tambah nomor urut manual
        $this->rowNumber++;

        return [
            $this->rowNumber, // <--- nomor urut mulai dari 1
            $item->part->Inv_id ?? '-',
            $item->user->username ?? '-',
            $item->qrcode_raw ?? '-',
            $item->stok_inout ?? 0,
            ucfirst($item->status),
            optional($item->scanned_at)->format('Y-m-d H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'No',
            'Inventory ID',
            'Prepared By',
            'QR Code',
            'Jumlah',
            'Status',
            'Tanggal Scan',
        ];
    }
}
