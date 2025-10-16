<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\HeadArea;
use App\Models\Plant;
use App\Models\UserSession;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Events\DailyStoReminderEvent;
use Carbon\Carbon;

class AuthController extends Controller
{
    //
    public function showAdmin()
    {
        // // buat email pengingat jika belum upload stok harian
        // // Trigger event hanya jika jam 10-18 (menit berapapun)
        // $now = Carbon::now();
        // if ($now->hour >= 10 && $now->hour <= 18) {
        //     // Pastikan hanya trigger pada jam genap (10, 12, 14, 16, 18)
        //     $allowedHours = [10, 12, 14, 16, 18];
        //     if (in_array($now->hour, $allowedHours)) {
        //         event(new DailyStoReminderEvent($allowedHours));
        //     }
        // }    

        return view('auth.admin');  // Tampilan login
    }
    /**
     * Menampilkan halaman login untuk user biasa.
     *
     * @return \Illuminate\View\View
     */
    // AuthController.php
    public function showUser()
    {
        $plans = Plant::all(); // Get all plans from plans table
        $areas = HeadArea::all(); // Get all areas
        return view('auth.user-login', compact('plans', 'areas'));
    }

    public function login(Request $request)
    {
        // Validasi awal
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $credentials = $request->only('username', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            Auth::login($user);
            // Log login berhasil
            Log::info('Login berhasil', [
                'username' => $user->username,
                'roles' => $user->roles->pluck('name')->toArray(),
                'login_time' => now()->toDateTimeString()
            ]);

            // Simpan daftar role ke session
            session(['user_roles' => $user->roles->pluck('name')->toArray()]);
            // Lanjut ke dashboard
            return redirect()->route('dashboard')->with('login-sukses', 'Login successful');
        }

        // Jika gagal login
        return redirect()->back()
            ->withErrors(['password' => 'Username atau password salah.'])
            ->withInput();
    }

    public function userLogin(Request $request)
    {
        // dd($request->all());

        $validator = Validator::make(
            $request->all(),
            [
                'nik' => 'required|string',
                'plan_id' => 'required', // Validasi plan_id harus ada di tabel
            ]
        );

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = User::where('nik', $request->nik)->first();

        if ($user) {

            // Simpan data area ke session
            session([
                'selected_plan_id' => $request->plan_id,
                'selected_plan_name' => Plant::find($request->plan_id)->name ?? 'Unknown Plan',
            ]);

            Auth::login($user); // login manual tanpa password
            // Simpan daftar role ke session
            session(['user_roles' => $user->roles->pluck('name')->toArray()]);
            return redirect()->route('dashboardinout.index')->with('success', 'Login berhasil');
        }

        // Jika gagal login
        return redirect()->back()
            ->withErrors(['nik' => 'ID Card tidak ditemukan'])
            ->withInput();
    }

    public function logout()
    {
        Auth::logout();  // Keluar dari akun
        return redirect()->route('admin.login')->with('success.logout', 'Logout successful');
    }
    public function logoutUser()
    {
        Auth::logout();  // Keluar dari akun
        return redirect()->route('user.login')->with('success.logout', 'Logout successful');
    }
}