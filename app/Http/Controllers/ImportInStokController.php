<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ImportInStokController extends Controller
{
    public function index()
    {
        return view('dashboard_inout.importinstok');
    }
}
