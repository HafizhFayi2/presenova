<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardPageController extends Controller
{
    public function admin(Request $request): Response
    {
        return response()->view('dashboard.admin');
    }

    public function guru(Request $request): Response
    {
        return response()->view('dashboard.guru');
    }

    public function siswa(Request $request): Response
    {
        return response()->view('dashboard.siswa');
    }
}
