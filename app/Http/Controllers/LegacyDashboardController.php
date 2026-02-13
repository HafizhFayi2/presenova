<?php

namespace App\Http\Controllers;

use App\Support\LegacyPhpRenderer;
use Illuminate\Http\Response;

class LegacyDashboardController extends Controller
{
    public function __construct(
        private readonly LegacyPhpRenderer $renderer
    ) {
    }

    public function admin(): Response
    {
        return $this->renderDashboard('dashboard/admin.php', 'dashboard.admin');
    }

    public function guru(): Response
    {
        return $this->renderDashboard('dashboard/guru.php', 'dashboard.guru');
    }

    public function siswa(): Response
    {
        return $this->renderDashboard('dashboard/siswa.php', 'dashboard.siswa');
    }

    private function renderDashboard(string $legacyScript, string $view): Response
    {
        return response()->view($view, [
            'html' => $this->renderer->render($legacyScript),
        ]);
    }
}

