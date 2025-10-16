<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkDays;
use Carbon\Carbon;

class WorkDaysSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 12) as $month) {
            WorkDays::create([
                'month' => Carbon::create(date('Y'), $month, 1)->format('Y-m-01'),
            ]);
        }
    }
}
