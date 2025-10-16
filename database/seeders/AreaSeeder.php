<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaSeeder extends Seeder
{
    public function run()
    {
        $areaList = [
            // KBI 1
            ['label' => 'Childpart Area Rak A', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Area Rak B', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Area Rak C', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Area Rak D', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Area Rak E', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Area Rak F', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Area Molding (Mesin)', 'plan' => 'KBI 1'],
            ['label' => 'Childpart On Trolly no.1-16', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Area Temporary', 'plan' => 'KBI 1'],
            ['label' => 'Carton Box Warehouse+ Pkg YPC', 'plan' => 'KBI 1'],
            ['label' => 'Finished Good Warehouse', 'plan' => 'KBI 1'],
            ['label' => 'Shutter FG, Prep MMKI & ADM', 'plan' => 'KBI 1'],
            ['label' => 'WIP Subcont', 'plan' => 'KBI 1'],
            ['label' => 'Area Delivery', 'plan' => 'KBI 1'],
            ['label' => 'Material Transit', 'plan' => 'KBI 1'],
            ['label' => 'Material Area Workshop', 'plan' => 'KBI 1'],
            ['label' => 'Shutter FG Fin Line 1-23', 'plan' => 'KBI 1'],
            ['label' => 'QC Office room', 'plan' => 'KBI 1'],
            ['label' => 'Manufacture Office', 'plan' => 'KBI 1'],
            ['label' => 'Line Produksi (Finishing) WIP', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Fin Line 1-10', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Fin Line 11-20', 'plan' => 'KBI 1'],
            ['label' => 'Childpart Fin Line 21-24', 'plan' => 'KBI 1'],
            ['label' => 'WIP Shutter Molding No.1-30', 'plan' => 'KBI 1'],
            ['label' => 'WIP Shutter Molding No.31-58', 'plan' => 'KBI 1'],
            ['label' => 'WIP Rack - WIP Pianica', 'plan' => 'KBI 1'],
            ['label' => 'WIP WH-2', 'plan' => 'KBI 1'],
            ['label' => 'Molding (WIP) + Area NG', 'plan' => 'KBI 1'],
            ['label' => 'Line Molding (Raw Material) & Transit Material', 'plan' => 'KBI 1'],
            ['label' => 'WIP Rak Daisha', 'plan' => 'KBI 1'],
            ['label' => 'Rekap STO (Cut Off Delivery Before STO)', 'plan' => 'KBI 1'],
            ['label' => 'Admin Delivery (Cut Off DO)', 'plan' => 'KBI 1'],

            // KBI 2
            ['label' => 'WIP Line Blowmolding', 'plan' => 'KBI 2'],
            ['label' => 'Material Line Blowmolding', 'plan' => 'KBI 2'],
            ['label' => 'WIP Shutter Spoiler', 'plan' => 'KBI 2'],
            ['label' => 'WIP Sanding Area', 'plan' => 'KBI 2'],
            ['label' => 'FG Area NG Spoiler', 'plan' => 'KBI 2'],
            ['label' => 'WIP Shutter 1', 'plan' => 'KBI 2'],
            ['label' => 'WIP Shutter 2', 'plan' => 'KBI 2'],
            ['label' => 'Material Warehouse', 'plan' => 'KBI 2'],
            ['label' => 'Packaging WH', 'plan' => 'KBI 2'],
            ['label' => 'WIP Ducting WH', 'plan' => 'KBI 2'],
            ['label' => 'Finishing Line 1-9', 'plan' => 'KBI 2'],
            ['label' => 'Finishing Line 10-18', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - A', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - B', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - C', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - D', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - E', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - F', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - G', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - H', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - I', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - J', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Pallet Area', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - K', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - L', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - M', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - NA', 'plan' => 'KBI 2'],
            ['label' => 'Childpart Rack - NB', 'plan' => 'KBI 2'],
            ['label' => 'Receiving Cpart & Temporary Area', 'plan' => 'KBI 2'],
            ['label' => 'FG Shutter A', 'plan' => 'KBI 2'],
            ['label' => 'FG Shutter B', 'plan' => 'KBI 2'],
            ['label' => 'WIP Inoac', 'plan' => 'KBI 2'],
            ['label' => 'FG Area Prepare Denso', 'plan' => 'KBI 2'],
            ['label' => 'FG Palet', 'plan' => 'KBI 2'],
            ['label' => 'FG Export +', 'plan' => 'KBI 2'],
            ['label' => 'FG Prepare ADM', 'plan' => 'KBI 2'],
            ['label' => 'FG Prepare SPD', 'plan' => 'KBI 2'],
            ['label' => 'FG DMIA WH+', 'plan' => 'KBI 2'],
            ['label' => 'FG Injection Area', 'plan' => 'KBI 2'],
            ['label' => 'PE Room', 'plan' => 'KBI 2'],
            ['label' => 'Area Crusher & Material Injection', 'plan' => 'KBI 2'],
            ['label' => 'Delivery Area +', 'plan' => 'KBI 2'],
            ['label' => 'Lantai-2', 'plan' => 'KBI 2'],
            ['label' => 'DOJO Area', 'plan' => 'KBI 2'],
        ];

        foreach ($areaList as $area) {
            $planId = DB::table('tbl_plan')->where('name', $area['plan'])->value('id');
            DB::table('tbl_head_area')->updateOrInsert(
                [
                    'nama_area' => $area['label'],
                    'id_plan' => $planId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}
