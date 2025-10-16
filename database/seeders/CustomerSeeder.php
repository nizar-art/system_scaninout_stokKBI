<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Customer;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $customers = [
            'PT. ASTRA DAIHATSU MOTOR - KAP',
            'PT. ASTRA DAIHATSU MOTOR - KEP',
            'PT. ASTRA DAIHATSU MOTOR - SAP',
            'PT. ASTRA DAIHATSU MOTOR - SEP',
            'PT. ASTRA DAIHATSU MOTOR - SPD',
            'PT. ISUZU ASTRA MOTOR INDONESIA - DMIA',
            'PT. DENSO INDONESIA',
            'PT. GAYA MOTOR KARAWANG',
            'PT. HONDA AUTO COMPONENTS',
            'PT. HINO MOTORS MANUFACTURING INDONESIA',
            'PT. HINO MOTORS MANUFACTURING INDONESIA - SPD',
            'PT. HYUNDAI MOTORS MANUFACTURING INDONESIA',
            'PT. HYUNDAI MOTORS MANUFACTURING INDONESIA - EKSPORT',
            'PT. HONDA PROSPECT MOTOR',
            'PT. HONDA PROSPECT MOTOR - SPD LOKAL',
            'PT. ISUZU ASTRA MOTOR INDONESIA',
            'PT. INOAC POLYTECH INDONESIA',
            'PT. IRC INOAC INDONESIA',
            'PT. KRAMA YUDHA TIGA BERLIAN MOTORS',
            'PT. KRAMA YUDHA TIGA BERLIAN MOTORS - SPD',
            'PT. MAH SING INDONESIA',
            'PT. MITSUBISHI MOTORS KRAMA YUDHA INDONESIA',
            'PT. MITSUBISHI MOTORS KRAMA YUDHA INDONESIA - SPD',
            'PT. NAFUCO INDONESIA',
            'PT. NAGASSE BUMI KENCANA',
            'PT. NISSEN CHEMITEC INDONESIA',
            'PT. PAKO BUSANA INDONESIA',
            'PT. SWAKARYA INSAN MANDIRI',
            'PT. SWAKARYA INSAN MANDIRI - SPD',
            'PT. SUZUKI MOTOR INDONESIA',
            'PT. TOYOTA MOTOR MANUFACTURING INDONESIA',
            'PT. TOYOTA MOTOR MANUFACTURING INDONESIA - POQ',
            'PT. TRIDINDO',
            'PT. VALEO AC INDONESIA',
            'PT. YAMAHA MUSIC MANUFACTURING INDONESIA',
            'PT.INDONESIA THAI SUMMIT PLASTECH'
        ];

        $usernames = [
            'ADM-KAP',
            'ADM-KEP',
            'ADM-SAP',
            'ADM-SEP',
            'ADM-SPD',
            'ASMO-DMIA',
            'DENSO',
            'GMK',
            'HAC',
            'HINO',
            'HINO-SPD',
            'HMMI',
            'HMMI-EKSPORT',
            'HPM',
            'HPM-SPD LOKAL',
            'IAMI',
            'IPI',
            'IRC',
            'KTB',
            'KTB-SPD',
            'MAH SING',
            'MMKI',
            'MMKI-SPD',
            'NAFUCO',
            'NAGASSE',
            'NISSEN',
            'PBI',
            'SIM',
            'SIM-SPD',
            'SMI',
            'TMMIN',
            'TMMIN-POQ',
            'TRID',
            'VALEO',
            'YMPI',
            'ITSP'
        ];

        foreach ($customers as $index => $customer) {
            Customer::create([
                'name' => $customer,
                'username' => $usernames[$index]
            ]);
        }
    }
}
