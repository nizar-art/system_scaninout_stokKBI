<?php

namespace App\Imports;

use App\Models\WorkDays;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class WorkDaysImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // Pastikan data bulan dan hari kerja tidak kosong
        if (empty($row['month']) || $row['hari_kerja'] === null) {
            return null;
        }

        try {
            // Konversi bulan dari Excel ke format Carbon
            if (is_numeric($row['month'])) {
                $carbonMonth = Carbon::instance(Date::excelToDateTimeObject($row['month']))->startOfMonth();
            } else {
                $carbonMonth = Carbon::parse($row['month'])->startOfMonth();
            }

            $formattedMonth = $carbonMonth->format('Y-m-d');

            // Temukan data bulan terkait
            $workDay = WorkDays::whereDate('month', $formattedMonth)->first();

            if ($workDay) {
                // Update meskipun hari_kerja tidak berubah, agar updated_at juga ikut update
                $workDay->hari_kerja = (int) $row['hari_kerja'];
                $workDay->touch(); // ini akan memperbarui updated_at
                $workDay->save();
            }

        } catch (\Exception $e) {
            Log::warning("Gagal parsing tanggal: " . $row['month']);
        }

        return null;
    }
}
