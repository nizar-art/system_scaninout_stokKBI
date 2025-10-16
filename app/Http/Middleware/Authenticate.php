<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo($request)
    {
        if (!$request->expectsJson()) {
            session()->flash('expired', 'Session Anda telah habis. Silakan login kembali.');

            if ($request->is('user/*')) {
                return route('user.login');
            }

            return route('admin.login');
        }
    }

}
