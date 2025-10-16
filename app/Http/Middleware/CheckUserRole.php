<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (Auth::check()) {
            $userRoles = Auth::user()->roles->pluck('name')->toArray();

            // Izinkan jika user punya role 'User' (boleh punya role lain juga)
            if (in_array('User', $userRoles)) {
                return $next($request);
            }
        }

        Auth::logout(); // Logout jika tidak punya role yang sesuai
        return redirect()->route('user.login')->with('warning', 'Akses ditolak: hanya untuk user dengan role User.');
    }
}