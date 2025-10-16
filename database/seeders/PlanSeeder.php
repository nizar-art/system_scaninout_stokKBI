<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run()
    {
        $plans = ['KBI 1', 'KBI 2'];

        foreach ($plans as $plan) {
            DB::table('tbl_plan')->updateOrInsert(['name' => $plan]);
        }
    }
}
