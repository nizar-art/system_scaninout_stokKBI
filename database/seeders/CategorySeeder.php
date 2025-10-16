<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            "Finished Good",
            "WIP",
            "Packaging",
            "ChildPart",
            "Raw Material",
        ];

        foreach ($categories as $category) {
            DB::table('tbl_category')->updateOrInsert(
                ['name' => $category],
                [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]
            );
        }
    }
}
