<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockUserRole
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $roles = Auth::user()->roles->pluck('name');
            // Blokir hanya jika user hanya punya satu role dan itu 'User'
            if ($roles->count() === 1 && $roles->contains('User')) {
                Auth::logout();
                return redirect()->route('user.login')->with('warning', 'Akses ditolak. Hanya untuk Admin.');
            }
        }

        return $next($request); // lanjut jika bukan role user
    }
}