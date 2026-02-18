<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $siteUrl = (string) (env('SITE_URL') ?: rtrim((string) config('app.url'), '/') . '/');

        $requestPath = trim((string) $request->getBasePath(), '/');
        $configPath = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        $appPath = $requestPath !== '' ? $requestPath : $configPath;
        $basePath = $appPath === '' ? '' : '/' . $appPath;
        $loginUrl = $basePath . '/login.php';

        return response()->view('pages.home', [
            'siteUrl' => $siteUrl,
            'fullurl' => ($basePath === '' ? '/index.php' : $basePath . '/index.php'),
            'loginUrl' => $loginUrl,
            'loginAdminUrl' => $loginUrl . '?role=admin',
            'loginGuruUrl' => $loginUrl . '?role=teacher',
        ]);
    }

    public function notFound(Request $request): Response
    {
        return response()->view('pages.not-found', [], 404);
    }
}
