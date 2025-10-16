<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat user SuperAdmin
        $superAdmin = User::create([
            'username' => 'adminSTO',
            'first_name' => 'admin',
            'last_name' => 'KBISystem',
            'nik' => 'AD2025',
            'password' => Hash::make('adminKbi'),
        ]);


      // Ambil role_id untuk role 'superAdmin'
      $superAdminRole = Role::where('name', 'admin')->first();

      // Menetapkan role_id ke user
      $superAdmin->role()->associate($superAdminRole);
      $superAdmin->save();
    }
}
