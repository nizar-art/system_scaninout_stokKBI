<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat user SuperAdmin
        $superAdmin = User::create([
            'username' => 'TES2025',
            'first_name' => 'salman',
            'last_name' => 'fauzi',
            'nik' => 'TES2025',
            'password' => Hash::make('TES2025'),
        ]);


      // Ambil role_id untuk role 'superAdmin'
      $superAdminRole = Role::where('name', 'user')->first();

      // Menetapkan role_id ke user
      $superAdmin->role()->associate($superAdminRole);
      $superAdmin->save();
    }
}
