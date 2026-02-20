<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\DashboardPageController;
use App\Http\Controllers\Dashboard\Ajax\DashboardAjaxController;
use App\Http\Controllers\Dashboard\Print\SchedulePrintController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UtilityPageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$appUrlPathPrefix = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
$prefixes = $appUrlPathPrefix !== '' ? [$appUrlPathPrefix, ''] : [''];

foreach ($prefixes as $prefix) {
    $isBaseRoutes = $prefix === '';
    $groupAttributes = $prefix !== '' ? ['prefix' => $prefix] : [];

    Route::group($groupAttributes, function () use ($isBaseRoutes) {
        $homeRoute = Route::get('/', [HomeController::class, 'getStarted']);
        $getStartedRoute = Route::get('/getstarted/index.php', [HomeController::class, 'getStarted']);
        Route::get('/public/getstarted/index.php', [HomeController::class, 'getStarted']);
        $indexRoute = Route::get('/index.php', [HomeController::class, 'index']);
        if ($isBaseRoutes) {
            $homeRoute->name('home');
            $getStartedRoute->name('home.getstarted');
            $indexRoute->name('home.index');
        }

        Route::get('/laravel-health', function () {
            return response()->json([
                'app' => config('app.name'),
                'status' => 'ok',
                'time' => now()->toIso8601String(),
            ]);
        });

        Route::get('/404.php', [HomeController::class, 'notFound']);
        Route::get('/{errorCode}.php', [HomeController::class, 'error'])
            ->where('errorCode', '[1-5][0-9]{2}');

        $loginShow = Route::get('/login.php', [LoginController::class, 'show']);
        $loginAuth = Route::post('/login.php', [LoginController::class, 'authenticate']);
        $logout = Route::get('/logout.php', [LoginController::class, 'logout']);
        Route::get('/login', [LoginController::class, 'show']);
        Route::post('/login', [LoginController::class, 'authenticate']);
        Route::get('/logout', [LoginController::class, 'logout']);
        Route::get('/dashboard/login.php', [LoginController::class, 'show']);
        Route::post('/dashboard/login.php', [LoginController::class, 'authenticate']);
        Route::get('/dashboard/logout.php', [LoginController::class, 'logout']);
        Route::get('/dashboard/login', [LoginController::class, 'show']);
        Route::post('/dashboard/login', [LoginController::class, 'authenticate']);
        Route::get('/dashboard/logout', [LoginController::class, 'logout']);
        if ($isBaseRoutes) {
            $loginShow->name('auth.login.show');
            $loginAuth->name('auth.login.authenticate');
            $logout->name('auth.logout');
        }

        // Dashboard URL compatibility: keep both /.php and path variant.
        $adminPath = Route::get('/dashboard/admin', [DashboardPageController::class, 'admin']);
        $guruPath = Route::get('/dashboard/guru', [DashboardPageController::class, 'guru']);
        $siswaPath = Route::get('/dashboard/siswa', [DashboardPageController::class, 'siswa']);
        Route::match(['GET', 'POST'], '/dashboard/admin.php', [DashboardPageController::class, 'admin']);
        Route::match(['GET', 'POST'], '/dashboard/guru.php', [DashboardPageController::class, 'guru']);
        Route::match(['GET', 'POST'], '/dashboard/siswa.php', [DashboardPageController::class, 'siswa']);
        if ($isBaseRoutes) {
            $adminPath->name('dashboard.admin');
            $guruPath->name('dashboard.guru');
            $siswaPath->name('dashboard.siswa');
        }

        // Migrated dashboard ajax endpoints.
        Route::match(['GET', 'POST'], '/dashboard/ajax/config.php', [DashboardAjaxController::class, 'config']);
        Route::match(['GET', 'POST'], '/dashboard/ajax/get_data.php', [DashboardAjaxController::class, 'getData']);
        Route::match(['GET', 'POST'], '/dashboard/ajax/get_form.php', [DashboardAjaxController::class, 'getForm']);
        Route::match(['GET', 'POST'], '/dashboard/ajax/get_schedule_form.php', [DashboardAjaxController::class, 'getScheduleForm']);
        Route::match(['GET', 'POST'], '/dashboard/ajax/load_attendance_form.php', [DashboardAjaxController::class, 'loadAttendanceForm']);
        Route::post('/dashboard/ajax/add_jurusan.php', [DashboardAjaxController::class, 'addJurusan']);
        Route::post('/dashboard/ajax/add_class.php', [DashboardAjaxController::class, 'addClass']);
        Route::post('/dashboard/ajax/add_student.php', [DashboardAjaxController::class, 'addStudent']);
        Route::post('/dashboard/ajax/edit_student.php', [DashboardAjaxController::class, 'editStudent']);
        Route::post('/dashboard/ajax/reset_password.php', [DashboardAjaxController::class, 'resetPassword']);
        Route::post('/dashboard/ajax/reveal_student_code.php', [DashboardAjaxController::class, 'revealStudentCode']);
        Route::post('/dashboard/ajax/change_password.php', [DashboardAjaxController::class, 'changePassword']);
        Route::post('/dashboard/ajax/check_schedule.php', [DashboardAjaxController::class, 'checkSchedule']);
        Route::post('/dashboard/ajax/get_attendance_details.php', [DashboardAjaxController::class, 'getAttendanceDetails']);
        Route::match(['GET', 'POST'], '/dashboard/ajax/get_attendance_stats.php', [DashboardAjaxController::class, 'getAttendanceStats']);
        Route::match(['GET', 'POST'], '/dashboard/ajax/get_system_stats.php', [DashboardAjaxController::class, 'getSystemStats']);
        Route::match(['GET', 'POST'], '/dashboard/ajax/download_system_logs.php', [DashboardAjaxController::class, 'downloadSystemLogs']);
        Route::post('/dashboard/ajax/optimize_database.php', [DashboardAjaxController::class, 'optimizeDatabase']);
        Route::post('/dashboard/ajax/save_security.php', [DashboardAjaxController::class, 'saveSecurity']);

        // Migrated print endpoints.
        Route::get('/dashboard/roles/admin/print/jadwal_print.php', [SchedulePrintController::class, 'admin']);
        Route::get('/dashboard/roles/guru/print/jadwal_print.php', [SchedulePrintController::class, 'guru']);
        Route::get('/dashboard/roles/siswa/print/jadwal_print.php', [SchedulePrintController::class, 'siswa']);

        // Migrated API endpoints.
        Route::match(['GET', 'POST'], '/api/check_location.php', [ApiController::class, 'checkLocation']);
        Route::match(['GET', 'POST'], '/api/face_matching.php', [ApiController::class, 'faceMatching']);
        Route::get('/api/get-public-key.php', [ApiController::class, 'getPublicKey']);
        Route::post('/api/save-subscription.php', [ApiController::class, 'saveSubscription']);
        Route::post('/api/remove-subscription.php', [ApiController::class, 'removeSubscription']);
        Route::match(['GET', 'POST'], '/api/register_face.php', [ApiController::class, 'registerFace']);
        Route::get('/api/get_schedule.php', [ApiController::class, 'getSchedule']);
        Route::match(['GET', 'POST'], '/api/get_attendance_details.php', [ApiController::class, 'getAttendanceDetails']);
        Route::match(['GET', 'POST'], '/api/save_attendance.php', [ApiController::class, 'saveAttendance']);
        Route::match(['GET', 'POST'], '/api/submit_attendance.php', [ApiController::class, 'saveAttendance']);
        Route::match(['GET', 'POST'], '/api/save_pose_frames.php', [ApiController::class, 'savePoseFrames']);
        Route::post('/api/sync_schedule.php', [ApiController::class, 'syncSchedule']);
        Route::match(['GET', 'POST'], '/dashboard/ajax/save_attendance.php', [ApiController::class, 'saveAttendance']);

        // Utility pages mapped directly to Laravel controllers.
        Route::match(['GET', 'POST'], '/call.php', [UtilityPageController::class, 'call']);
        Route::match(['GET', 'POST'], '/forgot-password.php', [UtilityPageController::class, 'forgotPassword']);
        Route::match(['GET', 'POST'], '/register.php', [UtilityPageController::class, 'register']);
        Route::match(['GET', 'POST'], '/reset_password.php', [UtilityPageController::class, 'resetPassword']);
    });
}

Route::fallback(function (Request $request) {
    return app(HomeController::class)->error($request, 404);
});
