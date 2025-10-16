<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardInOutController extends Controller
{
    public function index()
    {
        return view('dashboard_inout.inout');
    }
}
