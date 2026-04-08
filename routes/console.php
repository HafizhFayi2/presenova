<?php

use App\Services\ScheduleSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('presenova:sync-schedules
    {--student-id= : Sinkronkan hanya 1 siswa berdasarkan ID}
    {--class-id= : Sinkronkan hanya siswa pada class_id tertentu}
    {--months=6 : Horizon bulan ke depan yang ingin dibuat}
    {--limit=0 : Batasi jumlah siswa yang diproses (0 = tanpa batas)}
', function (): int {
    $monthsAhead = max(1, (int) $this->option('months'));
    $limit = max(0, (int) $this->option('limit'));
    $studentIdFilter = (int) ($this->option('student-id') ?: 0);
    $classIdFilter = (int) ($this->option('class-id') ?: 0);

    $query = DB::table('student')
        ->select('id', 'student_name', 'student_nisn', 'class_id')
        ->orderBy('id');

    if ($studentIdFilter > 0) {
        $query->where('id', $studentIdFilter);
    }
    if ($classIdFilter > 0) {
        $query->where('class_id', $classIdFilter);
    }
    if ($limit > 0) {
        $query->limit($limit);
    }

    try {
        $students = $query->get();
    } catch (\Throwable $e) {
        $this->error('Gagal mengambil data siswa untuk sinkronisasi: ' . $e->getMessage());
        return self::FAILURE;
    }
    if ($students->isEmpty()) {
        $this->warn('Tidak ada siswa yang memenuhi filter sinkronisasi.');
        return self::SUCCESS;
    }

    /** @var ScheduleSyncService $syncService */
    $syncService = app(ScheduleSyncService::class);
    $totalAdded = 0;
    $processed = 0;
    $failed = 0;

    $this->line('Memulai sinkronisasi jadwal siswa...');
    $this->line('Total target siswa: ' . $students->count());

    foreach ($students as $student) {
        $processed++;
        $studentId = (int) ($student->id ?? 0);
        $classId = (int) ($student->class_id ?? 0);
        $studentLabel = trim((string) (($student->student_name ?? '') ?: ($student->student_nisn ?? ('ID ' . $studentId))));

        if ($studentId <= 0 || $classId <= 0) {
            $failed++;
            $this->warn("[SKIP] {$studentLabel} - class_id tidak valid.");
            continue;
        }

        try {
            $added = $syncService->ensureStudentSchedulesForStudent($studentId, $classId, $monthsAhead);
            $totalAdded += $added;
            $this->line("[OK] {$studentLabel} | added={$added}");
        } catch (\Throwable $e) {
            $failed++;
            $this->error("[FAIL] {$studentLabel} | " . $e->getMessage());
        }
    }

    $this->newLine();
    $this->info('Sinkronisasi selesai.');
    $this->table(
        ['Metric', 'Value'],
        [
            ['processed_students', (string) $processed],
            ['total_added_student_schedule', (string) $totalAdded],
            ['failed_students', (string) $failed],
            ['months_ahead', (string) $monthsAhead],
        ]
    );

    return $failed > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Sinkronisasi massal student_schedule agar data admin/guru/siswa tetap konsisten');

Artisan::command('presenova:health-check
    {--strict : Jika ada warning data, exit code jadi failure}
    {--domain= : Domain produksi untuk cek HTTP/HTTPS cepat (opsional)}
', function (): int {
    $errors = [];
    $warnings = [];
    $passes = 0;

    $pass = function (string $message) use (&$passes): void {
        $passes++;
        $this->info('[PASS] ' . $message);
    };
    $warn = function (string $message) use (&$warnings): void {
        $warnings[] = $message;
        $this->warn('[WARN] ' . $message);
    };
    $fail = function (string $message) use (&$errors): void {
        $errors[] = $message;
        $this->error('[FAIL] ' . $message);
    };

    $this->line('Presenova health check berjalan...');

    // Environment and app security baseline.
    $ttl = (int) env('MEDIA_SIGNED_URL_TTL_MINUTES', 15);
    if ($ttl >= 1 && $ttl <= 1440) {
        $pass('MEDIA_SIGNED_URL_TTL_MINUTES valid (' . $ttl . ' menit)');
        if ($ttl < 30) {
            $warn('MEDIA_SIGNED_URL_TTL_MINUTES rendah. Disarankan >= 60 menit agar preview history tidak cepat expired.');
        }
    } else {
        $fail('MEDIA_SIGNED_URL_TTL_MINUTES tidak valid. Gunakan rentang 1-1440.');
    }

    $appUrl = trim((string) config('app.url', ''));
    if ($appUrl !== '') {
        $pass('APP_URL terisi: ' . $appUrl);
    } else {
        $warn('APP_URL kosong.');
    }

    $forceHttps = filter_var((string) env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN);
    $secureCookie = filter_var((string) env('SESSION_SECURE_COOKIE', false), FILTER_VALIDATE_BOOLEAN);
    if ($forceHttps) {
        $pass('FORCE_HTTPS aktif');
    } else {
        $warn('FORCE_HTTPS belum aktif.');
    }
    if ($secureCookie) {
        $pass('SESSION_SECURE_COOKIE aktif');
    } else {
        $warn('SESSION_SECURE_COOKIE belum aktif.');
    }

    // Private uploads hardening in Apache rewrites.
    $rootHtaccess = base_path('.htaccess');
    $publicHtaccess = public_path('.htaccess');
    $blockRuleSnippet = 'uploads/(faces|attendance)';
    foreach ([['root', $rootHtaccess], ['public', $publicHtaccess]] as $item) {
        [$label, $path] = $item;
        if (!is_file($path)) {
            $fail("File .htaccess {$label} tidak ditemukan: {$path}");
            continue;
        }
        $contents = (string) @file_get_contents($path);
        if (str_contains($contents, $blockRuleSnippet)) {
            $pass("Rule blok direct uploads terpasang di {$label} .htaccess");
        } else {
            $fail("Rule blok direct uploads belum ada di {$label} .htaccess");
        }
    }

    // Core directories that must be writable by web user.
    $requiredWritableDirs = [
        public_path('uploads'),
        public_path('uploads/faces'),
        public_path('uploads/attendance'),
        public_path('uploads/temp'),
        storage_path(),
        base_path('bootstrap/cache'),
    ];
    foreach ($requiredWritableDirs as $dir) {
        if (!is_dir($dir)) {
            $warn("Direktori belum ada: {$dir}");
            continue;
        }
        if (is_writable($dir)) {
            $pass("Direktori writable: {$dir}");
        } else {
            $fail("Direktori tidak writable: {$dir}");
        }
    }

    // Route checks for secure media + attendance endpoints.
    $router = app('router');
    $routes = $router->getRoutes();
    $namedMediaRoutes = ['media.face', 'media.attendance'];
    foreach ($namedMediaRoutes as $routeName) {
        $route = $routes->getByName($routeName);
        if (!$route) {
            $fail("Route {$routeName} tidak terdaftar.");
            continue;
        }
        $middlewares = $route->gatherMiddleware();
        if (collect($middlewares)->contains(static fn ($value): bool => str_starts_with((string) $value, 'signed'))) {
            $pass("Route {$routeName} menggunakan middleware signed");
        } else {
            $fail("Route {$routeName} belum menggunakan middleware signed");
        }
        if (collect($middlewares)->contains(static fn ($value): bool => str_starts_with((string) $value, 'throttle:'))) {
            $pass("Route {$routeName} memiliki middleware throttle");
        } else {
            $warn("Route {$routeName} belum memiliki throttle.");
        }
    }

    try {
        $signedFaceUrl = URL::temporarySignedRoute('media.face', now()->addMinutes(5), ['ref' => 'health-check'], false);
        $signedAttendanceUrl = URL::temporarySignedRoute('media.attendance', now()->addMinutes(5), ['ref' => 'health-check'], false);
        if (str_contains($signedFaceUrl, 'signature=') && str_contains($signedAttendanceUrl, 'signature=')) {
            $pass('Signed URL media berhasil digenerate');
        } else {
            $fail('Signed URL media gagal digenerate.');
        }
    } catch (\Throwable $e) {
        $fail('Gagal generate signed URL media: ' . $e->getMessage());
    }

    // DeepFace runtime checks.
    $faceScript = public_path('face/faces_conf/face_match.py');
    if (is_file($faceScript)) {
        $pass('face_match.py ditemukan');
    } else {
        $fail('face_match.py tidak ditemukan di public/face/faces_conf.');
    }

    $pythonBin = trim((string) env('PYTHON_BIN', 'python3'));
    $candidatePythonBin = $pythonBin;
    $isAbsolute = (bool) preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $candidatePythonBin);
    if (!$isAbsolute && !str_contains($candidatePythonBin, ' ')) {
        $baseCandidate = base_path($candidatePythonBin);
        if (is_file($baseCandidate)) {
            $candidatePythonBin = $baseCandidate;
        }
    }

    try {
        $importProcess = new Process([$candidatePythonBin, '-c', 'from deepface import DeepFace; print("DeepFace import OK")']);
        $importProcess->setTimeout(45);
        $importProcess->run();
        if ($importProcess->isSuccessful()) {
            $pass('DeepFace import OK via PYTHON_BIN');
        } else {
            $fail('DeepFace import gagal: ' . trim($importProcess->getErrorOutput() . "\n" . $importProcess->getOutput()));
        }
    } catch (\Throwable $e) {
        $fail('Tidak bisa menjalankan PYTHON_BIN untuk DeepFace: ' . $e->getMessage());
    }

    // Database + synchronization integrity checks.
    try {
        DB::select('SELECT 1');
        $pass('Koneksi database OK');

        $tableExists = static function (string $table): bool {
            return DB::getSchemaBuilder()->hasTable($table);
        };
        $requiredTables = ['user', 'teacher', 'student', 'teacher_schedule', 'student_schedule', 'presence', 'site'];
        foreach ($requiredTables as $table) {
            if ($tableExists($table)) {
                $pass("Tabel {$table} tersedia");
            } else {
                $fail("Tabel {$table} tidak ditemukan");
            }
        }

        if ($tableExists('student_schedule') && $tableExists('student') && $tableExists('teacher_schedule')) {
            $orphanStudentSchedule = DB::table('student_schedule as ss')
                ->leftJoin('student as s', 'ss.student_id', '=', 's.id')
                ->leftJoin('teacher_schedule as ts', 'ss.teacher_schedule_id', '=', 'ts.schedule_id')
                ->where(static function ($query): void {
                    $query->whereNull('s.id')->orWhereNull('ts.schedule_id');
                })
                ->count();
            if ($orphanStudentSchedule === 0) {
                $pass('student_schedule tidak memiliki data orphan');
            } else {
                $warn("Ditemukan {$orphanStudentSchedule} baris orphan pada student_schedule");
            }

            $classMismatch = DB::table('student_schedule as ss')
                ->join('student as s', 'ss.student_id', '=', 's.id')
                ->join('teacher_schedule as ts', 'ss.teacher_schedule_id', '=', 'ts.schedule_id')
                ->whereColumn('s.class_id', '!=', 'ts.class_id')
                ->count();
            if ($classMismatch === 0) {
                $pass('Kelas student_schedule sinkron dengan teacher_schedule');
            } else {
                $warn("Ada {$classMismatch} mismatch class antara student dan teacher_schedule");
            }

            $duplicateRow = DB::selectOne(
                "SELECT COUNT(*) AS total FROM (
                    SELECT student_id, teacher_schedule_id, schedule_date
                    FROM student_schedule
                    GROUP BY student_id, teacher_schedule_id, schedule_date
                    HAVING COUNT(*) > 1
                ) dup"
            );
            $duplicateCount = (int) ($duplicateRow->total ?? 0);
            if ($duplicateCount === 0) {
                $pass('Tidak ada duplikasi student_schedule per siswa/jadwal/tanggal');
            } else {
                $warn("Ada {$duplicateCount} grup duplikasi di student_schedule");
            }
        }

        if ($tableExists('presence') && $tableExists('student_schedule') && $tableExists('student')) {
            $orphanPresence = DB::table('presence as p')
                ->leftJoin('student_schedule as ss', 'p.student_schedule_id', '=', 'ss.student_schedule_id')
                ->leftJoin('student as s', 'p.student_id', '=', 's.id')
                ->where(static function ($query): void {
                    $query->whereNull('ss.student_schedule_id')->orWhereNull('s.id');
                })
                ->count();
            if ($orphanPresence === 0) {
                $pass('Presence tidak memiliki relasi orphan');
            } else {
                $warn("Ditemukan {$orphanPresence} data presence orphan");
            }

            $presenceMismatch = DB::table('presence as p')
                ->join('student_schedule as ss', 'p.student_schedule_id', '=', 'ss.student_schedule_id')
                ->whereColumn('p.student_id', '!=', 'ss.student_id')
                ->count();
            if ($presenceMismatch === 0) {
                $pass('Presence sinkron dengan student_schedule');
            } else {
                $warn("Ditemukan {$presenceMismatch} mismatch student_id antara presence dan student_schedule");
            }
        }

        $adminCount = DB::table('user')->whereIn('level', [1, 2])->count();
        $teacherCount = DB::table('teacher')->count();
        $studentCount = DB::table('student')->count();
        if ($adminCount > 0) {
            $pass("Data admin terdeteksi ({$adminCount})");
        } else {
            $warn('Belum ada data admin.');
        }
        if ($teacherCount > 0) {
            $pass("Data guru terdeteksi ({$teacherCount})");
        } else {
            $warn('Belum ada data guru.');
        }
        if ($studentCount > 0) {
            $pass("Data siswa terdeteksi ({$studentCount})");
        } else {
            $warn('Belum ada data siswa.');
        }
    } catch (\Throwable $e) {
        $fail('Pemeriksaan database gagal: ' . $e->getMessage());
    }

    $domain = trim((string) $this->option('domain'));
    if ($domain !== '') {
        try {
            $curlHttp = new Process(['curl', '-I', '-sS', '--max-time', '20', 'http://' . $domain]);
            $curlHttp->setTimeout(25);
            $curlHttp->run();
            if ($curlHttp->isSuccessful()) {
                $pass("HTTP endpoint domain merespons ({$domain})");
            } else {
                $warn('HTTP check domain gagal: ' . trim($curlHttp->getErrorOutput()));
            }
        } catch (\Throwable $e) {
            $warn('Lewati HTTP check domain: ' . $e->getMessage());
        }

        try {
            $curlHttps = new Process(['curl', '-I', '-sS', '--max-time', '20', 'https://' . $domain]);
            $curlHttps->setTimeout(25);
            $curlHttps->run();
            if ($curlHttps->isSuccessful()) {
                $pass("HTTPS endpoint domain merespons ({$domain})");
            } else {
                $warn('HTTPS check domain gagal: ' . trim($curlHttps->getErrorOutput()));
            }
        } catch (\Throwable $e) {
            $warn('Lewati HTTPS check domain: ' . $e->getMessage());
        }
    }

    $this->newLine();
    $this->table(
        ['Summary', 'Count'],
        [
            ['passes', (string) $passes],
            ['warnings', (string) count($warnings)],
            ['errors', (string) count($errors)],
        ]
    );

    if ($errors !== []) {
        return self::FAILURE;
    }

    if ($this->option('strict') && $warnings !== []) {
        return self::FAILURE;
    }

    return self::SUCCESS;
})->purpose('Health check end-to-end Presenova (deepface, media security, upload, DB integrity, sinkronisasi data)');
