<?php

namespace App\Http\Controllers\Dashboard\Ajax;

use App\Http\Controllers\Controller;
use App\Services\FaceMatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardAjaxController extends Controller
{
    public function config(): Response
    {
        return response('', 204);
    }

    public function getData(Request $request): Response
    {
        return $this->getForm($request);
    }

    public function getForm(Request $request): Response
    {
        if (strtoupper((string) $request->getMethod()) !== 'POST') {
            return response()->view('dashboard.ajax.invalid-request');
        }

        $allowed = session('logged_in') === true
            && (string) session('role', '') === 'admin'
            && in_array((int) session('level', 0), [1, 2], true);
        if (!$allowed) {
            return response()->view('dashboard.ajax.access-denied');
        }

        $table = trim((string) $request->input('table', ''));
        $id = (int) $request->input('id', 0);
        $isEdit = $id > 0;

        if ($table === '') {
            return response()->view('dashboard.ajax.invalid-request', [
                'message' => 'Parameter "table" tidak ditemukan. Pastikan Anda mengakses form dengan benar.',
            ]);
        }

        $payload = [
            'table' => $table,
            'id' => $id,
            'isEdit' => $isEdit,
            'isOperator' => (int) session('level', 0) === 2,
            'student' => null,
            'teacher' => null,
            'classes' => [],
            'classJurusanMap' => [],
        ];

        if ($table === 'student') {
            if ($isEdit) {
                $student = DB::table('student')->where('id', $id)->first();
                if (!$student) {
                    return response('<div class="alert alert-danger">Data siswa tidak ditemukan</div>');
                }
                $payload['student'] = (array) $student;
            }

            $classes = DB::table('class as c')
                ->leftJoin('jurusan as j', 'c.jurusan_id', '=', 'j.jurusan_id')
                ->orderBy('c.class_name')
                ->select(
                    'c.class_id',
                    'c.class_name',
                    'j.jurusan_id',
                    'j.name as jurusan_name',
                    'j.code as jurusan_code'
                )
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
            $payload['classes'] = $classes;

            $map = [];
            foreach ($classes as $class) {
                $map[(string) ($class['class_id'] ?? '')] = [
                    'jurusan_id' => $class['jurusan_id'] ?? null,
                    'jurusan_name' => $class['jurusan_name'] ?? '',
                    'jurusan_code' => $class['jurusan_code'] ?? '',
                ];
            }
            $payload['classJurusanMap'] = $map;
        } elseif ($table === 'teacher') {
            if ($isEdit) {
                $teacher = DB::table('teacher')->where('id', $id)->first();
                if (!$teacher) {
                    return response('<div class="alert alert-danger">Data guru tidak ditemukan</div>');
                }
                $payload['teacher'] = (array) $teacher;
            }
        } else {
            return response()->view('dashboard.ajax.form-not-found', ['table' => $table]);
        }

        return response()->view('dashboard.ajax.get-form', $payload);
    }

    public function getScheduleForm(Request $request): Response
    {
        if (strtoupper((string) $request->getMethod()) !== 'POST') {
            return response()->view('dashboard.ajax.invalid-request');
        }

        $allowed = session('logged_in') === true
            && (string) session('role', '') === 'admin'
            && in_array((int) session('level', 0), [1, 2], true);
        if (!$allowed) {
            return response()->view('dashboard.ajax.access-denied');
        }

        $scheduleId = (int) $request->input('id', 0);
        $schedule = null;
        $jpStart = 1;
        $jpEnd = 1;

        if ($scheduleId > 0) {
            $row = DB::table('teacher_schedule')->where('schedule_id', $scheduleId)->first();
            if ($row) {
                $schedule = (array) $row;
                if (!empty($schedule['shift_id'])) {
                    $shiftInfo = DB::table('shift')
                        ->where('shift_id', (int) $schedule['shift_id'])
                        ->value('shift_name');
                    if (is_string($shiftInfo) && preg_match('/JP(\d+)-JP(\d+)/', $shiftInfo, $matches)) {
                        $jpStart = (int) $matches[1];
                        $jpEnd = (int) $matches[2];
                    }
                }
            }
        }

        $teachers = DB::table('teacher')
            ->orderBy('teacher_name')
            ->select('id', 'teacher_code', 'teacher_name', 'subject')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
        $classes = DB::table('class')
            ->orderBy('class_name')
            ->select('class_id', 'class_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
        $days = DB::table('day')
            ->where('is_active', 'Y')
            ->orderBy('day_order')
            ->select('day_id', 'day_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
        $shifts = DB::table('shift')
            ->orderBy('time_in')
            ->select('shift_id', 'shift_name', 'time_in', 'time_out')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return response()->view('dashboard.ajax.get-schedule-form', [
            'schedule_id' => $scheduleId,
            'scheduleId' => $scheduleId,
            'schedule' => $schedule,
            'teachers' => $teachers,
            'classes' => $classes,
            'days' => $days,
            'shifts' => $shifts,
            'jpStart' => $jpStart,
            'jpEnd' => $jpEnd,
        ]);
    }

    public function loadAttendanceForm(Request $request, FaceMatcherService $faceMatcher): Response
    {
        if (session('logged_in') !== true || (string) session('role', '') !== 'siswa') {
            return response('<div class="alert alert-danger">Akses ditolak</div>');
        }

        $studentScheduleId = (int) $request->input('student_schedule_id', 0);
        $subject = trim((string) $request->input('subject', ''));
        $teacher = trim((string) $request->input('teacher', ''));

        if ($studentScheduleId <= 0) {
            return response('<div class="alert alert-danger">ID Jadwal tidak valid</div>');
        }

        $studentId = (int) session('student_id', 0);
        if ($studentId <= 0) {
            return response('<div class="alert alert-danger">Sesi siswa tidak valid</div>');
        }

        $student = DB::table('student_schedule as ss')
            ->join('student as s', 'ss.student_id', '=', 's.id')
            ->where('ss.student_schedule_id', $studentScheduleId)
            ->where('ss.student_id', $studentId)
            ->select('s.student_nisn', 's.student_name', 's.id', 's.photo_reference')
            ->first();

        if (!$student) {
            return response('<div class="alert alert-danger">Data siswa tidak ditemukan</div>');
        }

        $studentData = (array) $student;
        $referencePath = $faceMatcher->getReferencePath(
            (string) ($student->student_nisn ?? ''),
            (string) ($student->photo_reference ?? '')
        );
        $hasReference = is_string($referencePath) && $referencePath !== '';
        $referenceUrl = $hasReference ? $this->toBrowserPublicPath((string) $referencePath, '..') : '';

        $similarityThreshold = (int) env('FACE_MATCH_THRESHOLD', 89);
        if ($similarityThreshold <= 0 || $similarityThreshold > 100) {
            $similarityThreshold = 89;
        }

        return response()->view('dashboard.ajax.load-attendance-form', [
            'studentScheduleId' => $studentScheduleId,
            'subject' => $subject,
            'teacher' => $teacher,
            'student' => $studentData,
            'hasReference' => $hasReference,
            'referenceUrl' => $referenceUrl,
            'similarityThreshold' => $similarityThreshold,
            'studentLabel' => (string) (($student->student_name ?? '') ?: ($student->student_nisn ?? 'Siswa')),
        ]);
    }

    public function addJurusan(Request $request): JsonResponse
    {
        if ($denied = $this->assertAdminPost($request)) {
            return $denied;
        }

        try {
            $code = strtoupper(trim((string) ($request->input('code') ?? $request->input('kode_jurusan') ?? '')));
            $name = trim((string) $request->input('name', ''));

            if ($code === '' || $name === '') {
                return response()->json(['success' => false, 'message' => 'Semua field harus diisi']);
            }

            if (strlen($code) > 10) {
                return response()->json(['success' => false, 'message' => 'Kode jurusan maksimal 10 karakter']);
            }

            $existsCode = DB::table('jurusan')->where('code', $code)->exists();
            if ($existsCode) {
                return response()->json(['success' => false, 'message' => 'Kode jurusan sudah digunakan']);
            }

            $existsName = DB::table('jurusan')->where('name', $name)->exists();
            if ($existsName) {
                return response()->json(['success' => false, 'message' => 'Nama jurusan sudah ada']);
            }

            DB::table('jurusan')->insert([
                'code' => $code,
                'name' => $name,
            ]);

            return response()->json(['success' => true, 'message' => 'Jurusan berhasil ditambahkan']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function addClass(Request $request): JsonResponse
    {
        if ($denied = $this->assertAdminPost($request)) {
            return $denied;
        }

        try {
            $className = trim((string) $request->input('class_name', ''));
            $jurusanId = (int) $request->input('jurusan_id', 0);

            if ($className === '' || $jurusanId <= 0) {
                return response()->json(['success' => false, 'message' => 'Semua field harus diisi']);
            }

            $jurusan = DB::table('jurusan')
                ->select('jurusan_id', 'code')
                ->where('jurusan_id', $jurusanId)
                ->first();
            if (!$jurusan) {
                return response()->json(['success' => false, 'message' => 'Jurusan tidak ditemukan']);
            }

            $existsClass = DB::table('class')->where('class_name', $className)->exists();
            if ($existsClass) {
                return response()->json(['success' => false, 'message' => 'Kelas dengan nama tersebut sudah ada']);
            }

            DB::table('class')->insert([
                'class_name' => $className,
                'jurusan_id' => $jurusanId,
            ]);

            return response()->json(['success' => true, 'message' => 'Kelas berhasil ditambahkan']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function addStudent(Request $request): JsonResponse
    {
        if ($denied = $this->assertAdminPost($request)) {
            return $denied;
        }

        try {
            $studentNisn = trim((string) $request->input('student_nisn', ''));
            $studentCode = strtoupper(preg_replace('/\s+/', '', trim((string) $request->input('student_code', ''))));
            $studentName = trim((string) $request->input('student_name', ''));
            $classId = (int) $request->input('class_id', 0);
            $jurusanId = (int) $request->input('jurusan_id', 0);

            if ($studentCode !== '' && !str_starts_with($studentCode, 'SW')) {
                $studentCode = 'SW' . $studentCode;
            }

            if ($studentNisn === '' || $studentName === '' || $classId <= 0 || $jurusanId <= 0) {
                return response()->json(['success' => false, 'message' => 'Semua field harus diisi']);
            }

            if ($studentCode === '') {
                $studentCode = $this->generateStudentCode();
                if ($studentCode === '') {
                    return response()->json(['success' => false, 'message' => 'Gagal membuat kode siswa unik']);
                }
            }

            if (DB::table('student')->where('student_nisn', $studentNisn)->exists()) {
                return response()->json(['success' => false, 'message' => 'NISN sudah terdaftar']);
            }
            if (DB::table('student')->where('student_code', $studentCode)->exists()) {
                return response()->json(['success' => false, 'message' => 'Kode siswa sudah digunakan']);
            }

            $classData = DB::table('class')
                ->select('jurusan_id')
                ->where('class_id', $classId)
                ->first();
            if (!$classData) {
                return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan']);
            }

            if ((int) ($classData->jurusan_id ?? 0) !== $jurusanId) {
                return response()->json(['success' => false, 'message' => 'Jurusan tidak sesuai dengan kelas']);
            }

            $defaultPassword = hash('sha256', $studentNisn . $this->passwordSalt());
            DB::table('student')->insert([
                'student_nisn' => $studentNisn,
                'student_code' => $studentCode,
                'student_password' => $defaultPassword,
                'student_name' => $studentName,
                'class_id' => $classId,
                'jurusan_id' => $jurusanId,
                'location_id' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil ditambahkan',
                'student_code' => $studentCode,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function editStudent(Request $request): JsonResponse
    {
        if ($denied = $this->assertAdminPost($request)) {
            return $denied;
        }

        try {
            $id = (int) $request->input('id', 0);
            $studentNisn = trim((string) $request->input('student_nisn', ''));
            $studentCode = strtoupper(preg_replace('/\s+/', '', trim((string) $request->input('student_code', ''))));
            $studentName = trim((string) $request->input('student_name', ''));
            $classId = (int) $request->input('class_id', 0);
            $jurusanId = (int) $request->input('jurusan_id', 0);
            $passwordInput = trim((string) $request->input('password', ''));

            if ($studentCode !== '' && !str_starts_with($studentCode, 'SW')) {
                $studentCode = 'SW' . $studentCode;
            }

            if ($id <= 0 || $studentNisn === '' || $studentName === '' || $classId <= 0 || $jurusanId <= 0) {
                return response()->json(['success' => false, 'message' => 'Semua field harus diisi']);
            }

            $student = DB::table('student')
                ->select('id', 'student_code')
                ->where('id', $id)
                ->first();
            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan']);
            }

            if ($studentCode === '') {
                $studentCode = strtoupper(trim((string) ($student->student_code ?? '')));
            }

            $existsNisn = DB::table('student')
                ->where('student_nisn', $studentNisn)
                ->where('id', '!=', $id)
                ->exists();
            if ($existsNisn) {
                return response()->json(['success' => false, 'message' => 'NISN sudah digunakan oleh siswa lain']);
            }

            $existsCode = DB::table('student')
                ->where('student_code', $studentCode)
                ->where('id', '!=', $id)
                ->exists();
            if ($existsCode) {
                return response()->json(['success' => false, 'message' => 'Kode siswa sudah digunakan oleh siswa lain']);
            }

            $classData = DB::table('class')
                ->select('jurusan_id')
                ->where('class_id', $classId)
                ->first();
            if (!$classData) {
                return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan']);
            }

            if ((int) ($classData->jurusan_id ?? 0) !== $jurusanId) {
                return response()->json(['success' => false, 'message' => 'Jurusan tidak sesuai dengan kelas']);
            }

            $payload = [
                'student_nisn' => $studentNisn,
                'student_code' => $studentCode,
                'student_name' => $studentName,
                'class_id' => $classId,
                'jurusan_id' => $jurusanId,
            ];

            if ($passwordInput !== '') {
                $payload['student_password'] = hash('sha256', $passwordInput . $this->passwordSalt());
            }

            $updated = DB::table('student')->where('id', $id)->update($payload);
            if ($updated === 0) {
                return response()->json(['success' => false, 'message' => 'Gagal mengupdate data siswa']);
            }

            return response()->json(['success' => true, 'message' => 'Data siswa berhasil diupdate']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        if ($denied = $this->assertAdminPost($request)) {
            return $denied;
        }

        try {
            $id = (int) $request->input('id', 0);
            $type = trim((string) $request->input('type', ''));
            if ($id <= 0 || $type === '') {
                return response()->json(['success' => false, 'message' => 'Parameter tidak lengkap']);
            }

            if ($type === 'student') {
                $student = DB::table('student')
                    ->select('id', 'student_nisn')
                    ->where('id', $id)
                    ->first();
                if (!$student) {
                    return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan']);
                }

                $studentNisn = trim((string) ($student->student_nisn ?? ''));
                if ($studentNisn === '') {
                    return response()->json(['success' => false, 'message' => 'NISN siswa tidak valid']);
                }

                $password = hash('sha256', $studentNisn . $this->passwordSalt());
                $updated = DB::table('student')
                    ->where('id', $id)
                    ->update(['student_password' => $password]);
                if ($updated === 0) {
                    $currentHash = (string) (DB::table('student')->where('id', $id)->value('student_password') ?? '');
                    if ($currentHash === '' || !hash_equals($currentHash, $password)) {
                        return response()->json(['success' => false, 'message' => 'Gagal mereset password siswa']);
                    }
                }

                $this->recordAudit(
                    (int) session('user_id', 0),
                    (string) session('role', 'admin'),
                    'credential',
                    (string) $id,
                    'reset_student_password_default',
                    ['password' => 'masked'],
                    ['password' => 'masked'],
                    ['target' => 'student', 'reset_to' => 'student_nisn']
                );

                return response()->json(['success' => true, 'message' => 'Password siswa berhasil direset ke NISN']);
            }

            if ($type === 'teacher') {
                $password = hash('sha256', 'guru123' . $this->passwordSalt());
                $updated = DB::table('teacher')
                    ->where('id', $id)
                    ->update(['teacher_password' => $password]);
                if ($updated === 0) {
                    $currentHash = (string) (DB::table('teacher')->where('id', $id)->value('teacher_password') ?? '');
                    if ($currentHash === '' || !hash_equals($currentHash, $password)) {
                        return response()->json(['success' => false, 'message' => 'Gagal mereset password guru']);
                    }
                }

                $this->recordAudit(
                    (int) session('user_id', 0),
                    (string) session('role', 'admin'),
                    'credential',
                    (string) $id,
                    'reset_teacher_password_default',
                    ['password' => 'masked'],
                    ['password' => 'masked'],
                    ['target' => 'teacher', 'reset_to' => 'guru123']
                );

                return response()->json(['success' => true, 'message' => 'Password berhasil direset ke "guru123"']);
            }

            return response()->json(['success' => false, 'message' => 'Tipe tidak valid']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        if ($denied = $this->assertGuruPost($request)) {
            return $denied;
        }

        $teacherId = (int) session('teacher_id', 0);
        $oldPassword = trim((string) $request->input('old_password', ''));
        $newPassword = trim((string) $request->input('new_password', ''));
        $confirmPassword = trim((string) $request->input('confirm_password', ''));

        if ($teacherId <= 0) {
            return response()->json(['success' => false, 'message' => 'Sesi guru tidak valid']);
        }

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return response()->json(['success' => false, 'message' => 'Semua field harus diisi']);
        }

        if (strlen($newPassword) < 6) {
            return response()->json(['success' => false, 'message' => 'Password baru minimal 6 karakter']);
        }

        if ($newPassword !== $confirmPassword) {
            return response()->json(['success' => false, 'message' => 'Konfirmasi password tidak cocok']);
        }

        $teacher = DB::table('teacher')
            ->select('id', 'teacher_password')
            ->where('id', $teacherId)
            ->first();
        if (!$teacher) {
            return response()->json(['success' => false, 'message' => 'Data guru tidak ditemukan']);
        }

        $currentHash = (string) ($teacher->teacher_password ?? '');
        $oldHash = hash('sha256', $oldPassword . $this->passwordSalt());
        if ($currentHash === '' || !hash_equals($currentHash, $oldHash)) {
            return response()->json(['success' => false, 'message' => 'Password lama tidak sesuai']);
        }

        $newHash = hash('sha256', $newPassword . $this->passwordSalt());
        if (hash_equals($currentHash, $newHash)) {
            return response()->json(['success' => false, 'message' => 'Password baru harus berbeda dari password lama']);
        }

        if (strtolower($newPassword) === 'guru123') {
            return response()->json(['success' => false, 'message' => 'Password baru tidak boleh menggunakan default']);
        }

        $updated = DB::table('teacher')
            ->where('id', $teacherId)
            ->update(['teacher_password' => $newHash]);

        if ($updated === 0) {
            $latestHash = (string) (DB::table('teacher')->where('id', $teacherId)->value('teacher_password') ?? '');
            if ($latestHash === '' || !hash_equals($latestHash, $newHash)) {
                return response()->json(['success' => false, 'message' => 'Gagal mengubah password']);
            }
        }

        $this->recordAudit(
            $teacherId,
            'guru',
            'credential',
            (string) $teacherId,
            'change_password',
            ['password' => 'masked'],
            ['password' => 'masked'],
            ['source' => 'guru/profil']
        );

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah']);
    }

    public function optimizeDatabase(Request $request): JsonResponse
    {
        if ($denied = $this->assertAdminPost($request)) {
            return $denied;
        }

        try {
            $rows = DB::select('SHOW TABLES');
            $optimized = [];
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                $tableName = (string) reset($rowArray);
                if ($tableName === '') {
                    continue;
                }
                DB::statement('OPTIMIZE TABLE `' . str_replace('`', '``', $tableName) . '`');
                $optimized[] = $tableName;
            }

            return response()->json([
                'success' => true,
                'message' => 'Optimasi database selesai',
                'tables' => $optimized,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal optimasi database: ' . $e->getMessage()]);
        }
    }

    public function saveSecurity(Request $request): JsonResponse
    {
        if ($denied = $this->assertAdminPost($request)) {
            return $denied;
        }

        $payload = [
            'force_ssl' => filter_var((string) $request->input('forceSSL', 'false'), FILTER_VALIDATE_BOOLEAN),
            'rate_limit' => filter_var((string) $request->input('rateLimit', 'false'), FILTER_VALIDATE_BOOLEAN),
            'audit_log' => filter_var((string) $request->input('auditLog', 'true'), FILTER_VALIDATE_BOOLEAN),
            'updated_by' => (int) session('user_id', 0),
            'updated_at' => now()->toIso8601String(),
        ];

        try {
            $settingsPath = storage_path('app/security-settings.json');
            $directory = dirname($settingsPath);
            if (!is_dir($directory)) {
                @mkdir($directory, 0777, true);
            }

            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($encoded === false || @file_put_contents($settingsPath, $encoded) === false) {
                return response()->json(['success' => false, 'message' => 'Gagal menyimpan konfigurasi keamanan']);
            }

            return response()->json(['success' => true, 'message' => 'Pengaturan keamanan tersimpan']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan pengaturan: ' . $e->getMessage()]);
        }
    }

    public function revealStudentCode(Request $request): JsonResponse
    {
        if (strtoupper((string) $request->getMethod()) !== 'POST') {
            return response()->json(['success' => false, 'message' => 'Invalid request method']);
        }

        $isLoggedIn = session('logged_in') === true && (string) session('role', '') !== '';
        if (!$isLoggedIn) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak']);
        }

        $password = trim((string) $request->input('password', ''));
        if ($password === '') {
            return response()->json(['success' => false, 'message' => 'Password wajib diisi']);
        }

        $role = (string) session('role', '');
        $passwordHash = hash('sha256', $password . $this->passwordSalt());

        if ($role === 'siswa') {
            $studentId = (int) session('student_id', 0);
            if ($studentId <= 0) {
                return response()->json(['success' => false, 'message' => 'Session siswa tidak valid']);
            }

            $student = DB::table('student')
                ->select('student_code', 'student_password')
                ->where('id', $studentId)
                ->first();
            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan']);
            }

            $expectedHash = (string) ($student->student_password ?? '');
            if ($expectedHash === '' || !hash_equals($expectedHash, $passwordHash)) {
                return response()->json(['success' => false, 'message' => 'Password siswa tidak sesuai']);
            }

            return response()->json([
                'success' => true,
                'student_code' => strtoupper(trim((string) ($student->student_code ?? ''))),
            ]);
        }

        $isAdminLevel1 = $role === 'admin' && (int) session('level', 0) === 1;
        if ($isAdminLevel1) {
            $adminId = (int) session('user_id', 0);
            $studentId = (int) $request->input('student_id', 0);
            if ($adminId <= 0 || $studentId <= 0) {
                return response()->json(['success' => false, 'message' => 'Parameter tidak valid']);
            }

            $admin = DB::table('user')
                ->select('password')
                ->where('user_id', $adminId)
                ->where('level', 1)
                ->first();
            if (!$admin) {
                return response()->json(['success' => false, 'message' => 'Data admin tidak ditemukan']);
            }

            $expectedHash = (string) ($admin->password ?? '');
            if ($expectedHash === '' || !hash_equals($expectedHash, $passwordHash)) {
                return response()->json(['success' => false, 'message' => 'Password admin tidak sesuai']);
            }

            $student = DB::table('student')
                ->select('student_code')
                ->where('id', $studentId)
                ->first();
            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan']);
            }

            return response()->json([
                'success' => true,
                'student_code' => strtoupper(trim((string) ($student->student_code ?? ''))),
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Role tidak memiliki izin']);
    }

    public function checkSchedule(Request $request): JsonResponse
    {
        if ($denied = $this->assertAdminPost($request)) {
            return $denied;
        }

        $teacherId = (int) $request->input('teacher_id', 0);
        $dayId = (int) $request->input('day_id', 0);
        $shiftId = (int) $request->input('shift_id', 0);
        $scheduleId = (int) $request->input('schedule_id', 0);

        if ($teacherId <= 0 || $dayId <= 0 || $shiftId <= 0) {
            return response()->json(['conflict' => false]);
        }

        $count = DB::table('teacher_schedule')
            ->where('teacher_id', $teacherId)
            ->where('day_id', $dayId)
            ->where('shift_id', $shiftId)
            ->where('schedule_id', '!=', $scheduleId)
            ->count();

        return response()->json(['conflict' => $count > 0]);
    }

    public function getAttendanceDetails(Request $request): Response
    {
        if (session('logged_in') !== true) {
            return response('<div class="alert alert-danger">Unauthorized</div>');
        }

        $attendanceId = (int) $request->input('id', 0);
        if ($attendanceId <= 0) {
            return response('<div class="alert alert-warning">ID absensi tidak valid</div>');
        }

        $attendance = DB::table('presence as p')
            ->join('student as s', 'p.student_id', '=', 's.id')
            ->join('class as c', 's.class_id', '=', 'c.class_id')
            ->join('present_status as ps', 'p.present_id', '=', 'ps.present_id')
            ->leftJoin('student_schedule as ss', 'p.student_schedule_id', '=', 'ss.student_schedule_id')
            ->leftJoin('teacher_schedule as ts', 'ss.teacher_schedule_id', '=', 'ts.schedule_id')
            ->leftJoin('teacher as t', 'ts.teacher_id', '=', 't.id')
            ->where('p.presence_id', $attendanceId)
            ->select(
                'p.*',
                's.student_name',
                's.student_nisn',
                'c.class_name',
                'ps.present_name',
                'ts.subject',
                't.teacher_name',
                'p.latitude_in',
                'p.longitude_in',
                'p.picture_in',
                'p.late_time',
                'p.information',
                'p.distance_in'
            )
            ->first();

        if (!$attendance) {
            return response('<div class="alert alert-warning">Data tidak ditemukan</div>');
        }

        $attendanceData = (array) $attendance;
        $photoPath = $this->resolveAttendancePhotoPath($attendanceData, '..');

        return response()->view('pages.partials.attendance-details', [
            'attendance' => $attendanceData,
            'photoPath' => $photoPath,
        ]);
    }

    public function getAttendanceStats(Request $request): JsonResponse
    {
        if (session('logged_in') !== true) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak']);
        }

        try {
            $tz = new \DateTimeZone('Asia/Jakarta');
            $today = new \DateTimeImmutable('now', $tz);

            $filterDateSingle = trim((string) $request->query('date', ''));
            $filterDateFrom = (string) $request->query('date_from', $filterDateSingle !== '' ? $filterDateSingle : $today->format('Y-m-d'));
            $filterDateTo = (string) $request->query('date_to', $filterDateSingle !== '' ? $filterDateSingle : $filterDateFrom);
            $filterClass = trim((string) $request->query('class', ''));
            $filterStatus = trim((string) $request->query('status', ''));

            $fromObj = $this->parseFilterDate($filterDateFrom, $tz, new \DateTimeImmutable($today->format('Y-m-d'), $tz));
            $toObj = $this->parseFilterDate($filterDateTo, $tz, $fromObj);
            if ($fromObj > $toObj) {
                [$fromObj, $toObj] = [$toObj, $fromObj];
            }

            $filterDateFrom = $fromObj->format('Y-m-d');
            $filterDateTo = $toObj->format('Y-m-d');
            $cycleStart = $filterDateFrom . ' 00:00:00';
            $cycleEnd = $filterDateTo . ' 23:59:59';

            $statusRows = DB::table('present_status')
                ->select('present_id', 'present_name')
                ->get();
            $alpaStatusIds = [];
            $statusNameById = [];
            foreach ($statusRows as $statusRow) {
                $sid = (string) ($statusRow->present_id ?? '');
                $name = strtolower(trim((string) ($statusRow->present_name ?? '')));
                if ($sid !== '') {
                    $statusNameById[$sid] = $name;
                }
                if (in_array($name, ['alpa', 'tidak hadir'], true) && $sid !== '') {
                    $alpaStatusIds[] = $sid;
                }
            }

            $defaultAlpaId = !empty($alpaStatusIds) ? (int) $alpaStatusIds[0] : 4;

            $filterStatusNormalized = strtolower($filterStatus);
            $isAlpaFilter = $filterStatus !== '' && (
                $filterStatusNormalized === 'alpa' ||
                in_array($filterStatus, $alpaStatusIds, true)
            );

            $presenceQuery = DB::table('presence as p')
                ->join('student as s', 'p.student_id', '=', 's.id')
                ->whereBetween('p.presence_date', [$cycleStart, $cycleEnd])
                ->select('p.present_id', 'p.is_late');
            if ($filterClass !== '') {
                $presenceQuery->where('s.class_id', $filterClass);
            }
            if ($filterStatus !== '' && !$isAlpaFilter) {
                $presenceQuery->where('p.present_id', $filterStatus);
            }
            $presenceRows = $presenceQuery->get()->map(static fn ($row) => (array) $row)->all();

            $alpaQuery = DB::table('student_schedule as ss')
                ->join('student as s', 'ss.student_id', '=', 's.id')
                ->join('teacher_schedule as ts', 'ss.teacher_schedule_id', '=', 'ts.schedule_id')
                ->leftJoin('shift as sh', 'ts.shift_id', '=', 'sh.shift_id')
                ->leftJoin('presence as p', 'p.student_schedule_id', '=', 'ss.student_schedule_id')
                ->whereBetween('ss.schedule_date', [$filterDateFrom, $filterDateTo])
                ->whereNull('p.presence_id')
                ->select('ss.schedule_date', 'ss.time_in as schedule_time_in', 'ss.time_out as schedule_time_out', 'sh.time_in as shift_time_in', 'sh.time_out as shift_time_out');
            if ($filterClass !== '') {
                $alpaQuery->where('s.class_id', $filterClass);
            }
            $alpaRowsRaw = $alpaQuery->get();
            $alpaRows = [];
            foreach ($alpaRowsRaw as $row) {
                $scheduleDate = (string) ($row->schedule_date ?? '');
                if ($scheduleDate === '') {
                    continue;
                }

                $timeIn = (string) ($row->schedule_time_in ?: $row->shift_time_in ?: '00:00:00');
                $timeOut = (string) ($row->schedule_time_out ?: $row->shift_time_out ?: '00:00:00');
                [$startDt, $endDt] = $this->buildScheduleWindow($scheduleDate, $timeIn, $timeOut, $tz, 0);
                if ($today <= $endDt) {
                    continue;
                }
                $alpaRows[] = (array) $row;
            }

            $rowsForCount = $presenceRows;
            if ($isAlpaFilter) {
                $rowsForCount = array_map(static fn () => [
                    'present_id' => 0,
                    'present_name' => 'Alpa',
                    'is_late' => 'N',
                ], $alpaRows);
            } elseif ($filterStatus === '') {
                foreach ($alpaRows as $_ignored) {
                    $rowsForCount[] = [
                        'present_id' => $defaultAlpaId,
                        'present_name' => 'Alpa',
                        'is_late' => 'N',
                    ];
                }
            }

            $total = count($rowsForCount);
            $present = 0;
            $sick = 0;
            $permission = 0;
            $late = 0;
            $alpa = 0;
            foreach ($rowsForCount as $row) {
                $statusCategory = $this->resolveStatusCategory($row, $statusNameById);
                if ($statusCategory === 'hadir') {
                    $present++;
                    if (strtoupper((string) ($row['is_late'] ?? 'N')) === 'Y') {
                        $late++;
                    }
                    continue;
                }

                if ($statusCategory === 'sakit') {
                    $sick++;
                    continue;
                }

                if ($statusCategory === 'izin') {
                    $permission++;
                    continue;
                }

                if ($statusCategory === 'alpa') {
                    $alpa++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'present' => $present,
                    'sick' => $sick,
                    'permission' => $permission,
                    'late' => $late,
                    'alpa' => $alpa,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getSystemStats(): JsonResponse
    {
        try {
            $disks = [];
            $osFamily = PHP_OS_FAMILY;

            if ($osFamily === 'Windows') {
                foreach (range('C', 'Z') as $letter) {
                    $path = $letter . ':\\';
                    $total = @disk_total_space($path);
                    if ($total === false || $total <= 0) {
                        continue;
                    }
                    $free = @disk_free_space($path);
                    $used = ($free !== false) ? ($total - $free) : null;
                    $disks[] = [
                        'name' => $letter . ':',
                        'total' => $total,
                        'used' => $used,
                    ];
                }
            } else {
                $mounts = [];
                if (is_readable('/proc/mounts')) {
                    $lines = file('/proc/mounts', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                    foreach ($lines as $line) {
                        $parts = preg_split('/\s+/', $line);
                        if (!is_array($parts) || count($parts) < 3) {
                            continue;
                        }
                        $mountPoint = $parts[1];
                        $fsType = $parts[2];
                        if (in_array($fsType, ['proc', 'sysfs', 'tmpfs', 'devtmpfs', 'cgroup', 'overlay', 'squashfs'], true)) {
                            continue;
                        }
                        $mounts[$mountPoint] = true;
                    }
                }

                foreach (array_keys($mounts) as $mountPoint) {
                    $total = @disk_total_space($mountPoint);
                    if ($total === false || $total <= 0) {
                        continue;
                    }
                    $free = @disk_free_space($mountPoint);
                    $used = ($free !== false) ? ($total - $free) : null;
                    $disks[] = [
                        'name' => $mountPoint,
                        'total' => $total,
                        'used' => $used,
                    ];
                }
            }

            $ramTotal = null;
            $ramFree = null;
            if ($osFamily === 'Windows' && function_exists('shell_exec')) {
                $wmic = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
                if (is_string($wmic) && $wmic !== '') {
                    foreach (explode("\n", $wmic) as $line) {
                        $line = trim((string) $line);
                        if (stripos($line, 'FreePhysicalMemory=') === 0) {
                            $ramFree = (int) substr($line, strlen('FreePhysicalMemory=')) * 1024;
                        }
                        if (stripos($line, 'TotalVisibleMemorySize=') === 0) {
                            $ramTotal = (int) substr($line, strlen('TotalVisibleMemorySize=')) * 1024;
                        }
                    }
                }
            } elseif (is_readable('/proc/meminfo')) {
                $meminfo = file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($meminfo as $line) {
                    if (str_starts_with($line, 'MemTotal:')) {
                        $ramTotal = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
                    }
                    if (str_starts_with($line, 'MemAvailable:')) {
                        $ramFree = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
                    }
                }
            }

            $ramUsed = null;
            if ($ramTotal !== null && $ramFree !== null) {
                $ramUsed = $ramTotal - $ramFree;
            } else {
                $ramUsed = memory_get_usage(true);
                $limit = ini_get('memory_limit');
                if ($limit && $limit !== '-1') {
                    $unit = strtoupper(substr($limit, -1));
                    $value = (int) $limit;
                    $mult = 1;
                    if ($unit === 'G') {
                        $mult = 1024 * 1024 * 1024;
                    }
                    if ($unit === 'M') {
                        $mult = 1024 * 1024;
                    }
                    if ($unit === 'K') {
                        $mult = 1024;
                    }
                    $ramTotal = $value * $mult;
                }
            }

            $cpuLoad = null;
            if ($osFamily === 'Windows' && function_exists('shell_exec')) {
                $cpuOut = @shell_exec('wmic cpu get loadpercentage /Value');
                if (is_string($cpuOut) && preg_match('/LoadPercentage=(\d+)/i', $cpuOut, $m)) {
                    $cpuLoad = (int) $m[1] . '%';
                }
            } elseif (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                if (is_array($load) && isset($load[0])) {
                    $cpuLoad = $load[0];
                }
            }

            $uptime = null;
            if (is_readable('/proc/uptime')) {
                $uptimeRaw = trim((string) file_get_contents('/proc/uptime'));
                $uptimeSeconds = (int) floor((float) explode(' ', $uptimeRaw)[0]);
                $hours = (int) floor($uptimeSeconds / 3600);
                $minutes = (int) floor(($uptimeSeconds % 3600) / 60);
                $uptime = $hours . 'h ' . $minutes . 'm';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'disks' => $disks,
                    'ram_total' => $ramTotal,
                    'ram_used' => $ramUsed,
                    'cpu_load' => $cpuLoad,
                    'uptime' => $uptime,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function downloadSystemLogs(): Response|StreamedResponse
    {
        $timestamp = date('Ymd_His');
        $exportedBy = trim((string) (session('fullname') ?: session('username') ?: ''));
        if ($exportedBy === '') {
            $exportedBy = 'Administrator';
        }

        $logs = DB::table('activity_logs as l')
            ->leftJoin('user as u', function ($join) {
                $join->on('l.user_id', '=', 'u.user_id')
                    ->where('l.user_type', '=', 'admin');
            })
            ->leftJoin('teacher as t', function ($join) {
                $join->on('l.user_id', '=', 't.id')
                    ->where('l.user_type', '=', 'guru');
            })
            ->leftJoin('student as s', function ($join) {
                $join->on('l.user_id', '=', 's.id')
                    ->whereIn('l.user_type', ['student', 'siswa']);
            })
            ->selectRaw(
                "l.user_type, l.action, l.details, l.created_at, COALESCE(u.fullname, t.teacher_name, s.student_name, '-') AS actor_name"
            )
            ->orderByDesc('l.created_at')
            ->get();

        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return response('PhpSpreadsheet dependency is not installed. Please run composer install.', 500);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Log Sistem');
        $sheet->setCellValue('A1', 'LOG SISTEM PRESENOVA');
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A2', 'Diekspor pada: ' . date('d/m/Y H:i:s') . ' oleh ' . $exportedBy);
        $sheet->mergeCells('A2:F2');

        $headers = ['No', 'Nama', 'Tipe User', 'Aktivitas', 'Detail', 'Waktu'];
        foreach ($headers as $index => $headerText) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($col . '4', $headerText);
        }

        $row = 5;
        $no = 1;
        foreach ($logs as $log) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, (string) ($log->actor_name ?? '-'));
            $sheet->setCellValue('C' . $row, (string) ($log->user_type ?? '-'));
            $sheet->setCellValue('D' . $row, (string) ($log->action ?? '-'));
            $sheet->setCellValue('E' . $row, (string) ($log->details ?? '-'));
            $sheet->setCellValue('F' . $row, (string) ($log->created_at ?? '-'));
            $row++;
        }

        foreach (['A' => 6, 'B' => 22, 'C' => 14, 'D' => 20, 'E' => 38, 'F' => 22] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $filename = 'log_system_' . $timestamp . '.xlsx';
        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function assertAdminPost(Request $request): ?JsonResponse
    {
        if (strtoupper((string) $request->getMethod()) !== 'POST') {
            return response()->json(['success' => false, 'message' => 'Invalid request method']);
        }

        $allowed = session('logged_in') === true
            && (string) session('role', '') === 'admin'
            && in_array((int) session('level', 0), [1, 2], true);

        if (!$allowed) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak']);
        }

        return null;
    }

    private function assertGuruPost(Request $request): ?JsonResponse
    {
        if (strtoupper((string) $request->getMethod()) !== 'POST') {
            return response()->json(['success' => false, 'message' => 'Invalid request method']);
        }

        $allowed = session('logged_in') === true
            && (string) session('role', '') === 'guru'
            && (int) session('teacher_id', 0) > 0;

        if (!$allowed) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak']);
        }

        return null;
    }

    private function generateStudentCode(): string
    {
        for ($i = 0; $i < 100; $i++) {
            $candidate = 'SW' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $exists = DB::table('student')->where('student_code', $candidate)->exists();
            if (!$exists) {
                return $candidate;
            }
        }

        return '';
    }

    private function passwordSalt(): string
    {
        return (string) (env('PASSWORD_SALT') ?: '$%DSuTyr47542@#&*!=QxR094{a911}+');
    }

    private function recordAudit(
        int $actorId,
        string $actorRole,
        string $entityType,
        string $entityId,
        string $action,
        ?array $before,
        ?array $after,
        array $meta = []
    ): void {
        try {
            DB::table('master_data_audit_logs')->insert([
                'actor_id' => (string) $actorId,
                'actor_role' => $actorRole,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                'after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
                'meta_json' => json_encode(array_merge([
                    'ip_address' => request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ], $meta), JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Audit failures must not break CRUD flow.
        }
    }

    /**
     * @param array<string, mixed> $attendance
     */
    private function resolveAttendancePhotoPath(array $attendance, string $relativePrefix = '..'): string
    {
        $rawPhoto = trim((string) ($attendance['picture_in'] ?? ''));
        if ($rawPhoto === '') {
            return '';
        }

        if (preg_match('~^https?://~', $rawPhoto)) {
            return $rawPhoto;
        }

        $cleanPhoto = ltrim($rawPhoto, '/');
        $prefix = rtrim($relativePrefix, '/');

        if (str_starts_with($cleanPhoto, 'uploads/')) {
            return $prefix . '/' . $cleanPhoto;
        }

        if (str_starts_with($cleanPhoto, '../')) {
            return $cleanPhoto;
        }

        if (!str_contains($cleanPhoto, '/')) {
            $presenceDate = trim((string) ($attendance['presence_date'] ?? ''));
            if ($presenceDate !== '') {
                $dateDir = date('Y-m-d', strtotime($presenceDate));
                return $prefix . '/uploads/attendance/' . $dateDir . '/' . $cleanPhoto;
            }
        }

        if (str_starts_with($cleanPhoto, 'attendance/')) {
            return $prefix . '/uploads/' . $cleanPhoto;
        }

        return $prefix . '/uploads/attendance/' . $cleanPhoto;
    }

    private function toBrowserPublicPath(string $path, string $prefix = '..'): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $realPath = realpath($path);
        if ($realPath !== false) {
            $normalizedPath = str_replace('\\', '/', $realPath);
        }

        $publicRoot = realpath(public_path());
        if ($publicRoot === false) {
            return $normalizedPath;
        }

        $publicRoot = rtrim(str_replace('\\', '/', $publicRoot), '/');
        if (!str_starts_with($normalizedPath, $publicRoot . '/')) {
            return $normalizedPath;
        }

        $relative = ltrim(substr($normalizedPath, strlen($publicRoot)), '/');
        $prefix = rtrim($prefix, '/');
        if ($prefix === '' || $prefix === '.') {
            return $relative;
        }

        return $prefix . '/' . $relative;
    }

    private function parseFilterDate(string $rawDate, \DateTimeZone $timezone, \DateTimeImmutable $fallback): \DateTimeImmutable
    {
        $value = trim($rawDate);
        if ($value === '') {
            return $fallback;
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $value, $timezone);
            if (!$parsed instanceof \DateTimeImmutable) {
                continue;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors === false || (((int) $errors['warning_count'] === 0) && ((int) $errors['error_count'] === 0))) {
                return $parsed;
            }
        }

        try {
            return new \DateTimeImmutable($value, $timezone);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @return array{0:\DateTimeImmutable,1:\DateTimeImmutable,2:\DateTimeImmutable}
     */
    private function buildScheduleWindow(
        string $scheduleDate,
        string $timeIn,
        string $timeOut,
        \DateTimeZone $timezone,
        int $toleranceMinutes = 0
    ): array {
        $date = $scheduleDate !== '' ? $scheduleDate : date('Y-m-d');
        $start = new \DateTimeImmutable($date . ' ' . ($timeIn !== '' ? $timeIn : '00:00:00'), $timezone);
        $end = new \DateTimeImmutable($date . ' ' . ($timeOut !== '' ? $timeOut : '00:00:00'), $timezone);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        $baseEnd = $end;
        $minutes = max(0, $toleranceMinutes);
        if ($minutes > 0) {
            $baseEnd = $baseEnd->modify('-' . $minutes . ' minutes');
            if ($baseEnd < $start) {
                $baseEnd = $start;
            }
        }

        return [$start, $end, $baseEnd];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $statusNameById
     */
    private function resolveStatusCategory(array $row, array $statusNameById): string
    {
        $name = strtolower(trim((string) ($row['present_name'] ?? '')));
        if ($name === '') {
            $sid = (string) ($row['present_id'] ?? '');
            if ($sid !== '' && isset($statusNameById[$sid])) {
                $name = $statusNameById[$sid];
            }
        }

        if ($name === 'tidak hadir') {
            return 'alpa';
        }
        if (in_array($name, ['hadir', 'sakit', 'izin', 'alpa'], true)) {
            return $name;
        }

        return '';
    }
}

