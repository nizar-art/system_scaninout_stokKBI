<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat user SuperAdmin
        $superAdmin = User::create([
            'username' => 'superAdmin',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'password' => Hash::make('superadminKbi'),
        ]);


      // Ambil role_id untuk role 'superAdmin'
      $superAdminRole = Role::where('name', 'superAdmin')->first();

      // Menetapkan role_id ke user
      $superAdmin->role()->associate($superAdminRole);
      $superAdmin->save();
    }
}