<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardPageController extends Controller
{
    public function admin(Request $request): Response
    {
        return $this->renderDashboard('dashboard.admin');
    }

    public function guru(Request $request): Response
    {
        return $this->renderDashboard('dashboard.guru');
    }

    public function siswa(Request $request): Response
    {
        return $this->renderDashboard('dashboard.siswa');
    }

    private function renderDashboard(string $view): Response
    {
        return response()
            ->view($view)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
