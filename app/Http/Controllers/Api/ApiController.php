<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FaceMatcherService;
use App\Services\ScheduleSyncService;
use App\Services\StudentPushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function __construct(
        private readonly ScheduleSyncService $scheduleSyncService,
        private readonly StudentPushNotificationService $studentPushNotificationService
    ) {
    }

    public function checkLocation(Request $request): JsonResponse
    {
        $payload = is_array($request->json()->all()) ? $request->json()->all() : [];
        $latitude = $payload['latitude'] ?? $request->input('latitude');
        $longitude = $payload['longitude'] ?? $request->input('longitude');
        $accuracy = $payload['accuracy'] ?? $request->input('accuracy');

        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return response()->json(['success' => false, 'message' => 'Koordinat tidak ditemukan']);
        }

        try {
            $school = $this->getDefaultSchoolLocation();
            if ($school === null) {
                return response()->json(['success' => false, 'message' => 'Lokasi sekolah belum dikonfigurasi']);
            }

            $distance = $this->calculateDistance((float) $latitude, (float) $longitude, (float) $school['latitude'], (float) $school['longitude']);
            $accuracyValue = is_numeric($accuracy) ? (float) $accuracy : null;
            $radius = (float) ($school['radius'] ?? 0);
            $accuracyBuffer = 0.0;
            if ($accuracyValue !== null && $accuracyValue > 0) {
                $accuracyBuffer = min($accuracyValue, max(50, $radius * 1.5));
            }
            $withinRadius = $distance <= ($radius + $accuracyBuffer);

            return response()->json([
                'success' => true,
                'data' => [
                    'distance' => round($distance, 2),
                    'within_radius' => $withinRadius,
                    'radius_limit' => $school['radius'],
                    'accuracy' => $accuracyValue !== null ? round($accuracyValue, 2) : null,
                    'accuracy_buffer' => round($accuracyBuffer, 2),
                    'school_location' => $school,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getPublicKey(): JsonResponse
    {
        $enabled = filter_var((string) env('PUSH_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
        $publicKey = (string) env('PUSH_VAPID_PUBLIC_KEY', '');
        $privateKey = (string) env('PUSH_VAPID_PRIVATE_KEY', '');

        if (!$enabled || $publicKey === '' || $privateKey === '') {
            return response()->json(['success' => false, 'message' => 'Push service not configured']);
        }

        return response()->json([
            'success' => true,
            'publicKey' => $publicKey,
        ]);
    }

    public function saveSubscription(Request $request): JsonResponse
    {
        $studentId = (int) session('student_id', 0);
        if ($studentId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized']);
        }

        $enabled = filter_var((string) env('PUSH_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
        $publicKey = (string) env('PUSH_VAPID_PUBLIC_KEY', '');
        $privateKey = (string) env('PUSH_VAPID_PRIVATE_KEY', '');
        if (!$enabled || $publicKey === '' || $privateKey === '') {
            return response()->json(['success' => false, 'message' => 'Push service not configured']);
        }

        $data = $request->json()->all();
        if (!is_array($data) || $data === []) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if (!is_array($data) || empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            return response()->json(['success' => false, 'message' => 'Data subscription tidak lengkap']);
        }

        $endpoint = (string) $data['endpoint'];
        $p256dh = (string) $data['keys']['p256dh'];
        $auth = (string) $data['keys']['auth'];
        $contentEncoding = $data['contentEncoding'] ?? $data['content_encoding'] ?? null;
        $userAgent = (string) $request->header('User-Agent', '');
        $platform = (string) $request->header('Sec-CH-UA-Platform', '');
        $browser = $this->detectBrowser($userAgent);
        $now = now();

        try {
            $existingToken = DB::table('push_tokens')
                ->where('endpoint', $endpoint)
                ->orderByDesc('id')
                ->select('id')
                ->first();

            $payload = [
                'student_id' => $studentId,
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $auth,
                'content_encoding' => $contentEncoding,
                'browser' => $browser,
                'platform' => $platform !== '' ? $platform : null,
                'user_agent' => $userAgent !== '' ? $userAgent : null,
                'is_active' => 'Y',
                'updated_at' => $now,
            ];

            if ($existingToken) {
                $tokenId = (int) ($existingToken->id ?? 0);
                if ($tokenId > 0) {
                    DB::table('push_tokens')
                        ->where('id', $tokenId)
                        ->update($payload);

                    DB::table('push_tokens')
                        ->where('endpoint', $endpoint)
                        ->where('id', '!=', $tokenId)
                        ->update([
                            'is_active' => 'N',
                            'updated_at' => $now,
                        ]);
                } else {
                    $tokenId = (int) DB::table('push_tokens')->insertGetId(array_merge($payload, [
                        'created_at' => $now,
                    ]));
                }
            } else {
                $tokenId = (int) DB::table('push_tokens')->insertGetId(array_merge($payload, [
                    'created_at' => $now,
                ]));
            }

            return response()->json([
                'success' => true,
                'token_id' => $tokenId > 0 ? $tokenId : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan subscription']);
        }
    }

    public function removeSubscription(Request $request): JsonResponse
    {
        $studentId = (int) session('student_id', 0);
        if ($studentId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized']);
        }

        $data = $request->json()->all();
        if (!is_array($data) || $data === []) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        try {
            if (is_array($data) && !empty($data['endpoint'])) {
                DB::table('push_tokens')
                    ->where('student_id', $studentId)
                    ->where('endpoint', (string) $data['endpoint'])
                    ->update([
                        'is_active' => 'N',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('push_tokens')
                    ->where('student_id', $studentId)
                    ->update([
                        'is_active' => 'N',
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus subscription']);
        }

        return response()->json(['success' => true]);
    }

    public function getSchedule(Request $request): JsonResponse
    {
        if (!$this->hasAuthenticatedSession()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => null,
            ]);
        }

        $date = trim((string) $request->query('date', date('Y-m-d')));
        if ($date === '') {
            $date = date('Y-m-d');
        }
        $dayOfWeek = (int) date('N', strtotime($date));

        $studentId = (int) session('student_id', 0);
        if ($studentId > 0) {
            $student = DB::table('student')
                ->select('class_id')
                ->where('id', $studentId)
                ->first();
            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan', 'data' => null]);
            }

            $classId = (int) ($student->class_id ?? 0);
            $schedules = DB::table('teacher_schedule as ts')
                ->join('teacher as t', 'ts.teacher_id', '=', 't.id')
                ->join('class as c', 'ts.class_id', '=', 'c.class_id')
                ->join('day as d', 'ts.day_id', '=', 'd.day_id')
                ->join('shift as sh', 'ts.shift_id', '=', 'sh.shift_id')
                ->where('ts.day_id', $dayOfWeek)
                ->where('ts.class_id', $classId)
                ->where('d.is_active', 'Y')
                ->orderBy('sh.time_in')
                ->select(
                    'ts.*',
                    't.teacher_name',
                    'c.class_name',
                    'd.day_name',
                    'sh.time_in',
                    'sh.time_out'
                )
                ->get()
                ->map(function ($schedule) use ($studentId, $date) {
                    $attendance = DB::table('presence')
                        ->where('student_id', $studentId)
                        ->whereDate('presence_date', $date)
                        ->whereIn('student_schedule_id', function ($query) use ($schedule) {
                            $query->select('student_schedule_id')
                                ->from('student_schedule')
                                ->where('teacher_schedule_id', (int) $schedule->schedule_id);
                        })
                        ->first();

                    $schedule->has_attended = $attendance !== null;
                    $schedule->attendance_status = $attendance->present_id ?? null;
                    $schedule->attendance_time = $attendance->time_in ?? null;

                    return (array) $schedule;
                })
                ->all();

            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => [
                    'date' => $date,
                    'schedules' => $schedules,
                ],
            ]);
        }

        $teacherId = (int) session('teacher_id', 0);
        if ($teacherId > 0) {
            $schedules = DB::table('teacher_schedule as ts')
                ->join('class as c', 'ts.class_id', '=', 'c.class_id')
                ->join('day as d', 'ts.day_id', '=', 'd.day_id')
                ->join('shift as sh', 'ts.shift_id', '=', 'sh.shift_id')
                ->where('ts.teacher_id', $teacherId)
                ->where('ts.day_id', $dayOfWeek)
                ->where('d.is_active', 'Y')
                ->orderBy('sh.time_in')
                ->select(
                    'ts.*',
                    'c.class_name',
                    'd.day_name',
                    'sh.time_in',
                    'sh.time_out'
                )
                ->get()
                ->map(static fn ($row) => (array) $row)
                ->all();

            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => [
                    'date' => $date,
                    'schedules' => $schedules,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'User type tidak dikenali',
            'data' => null,
        ]);
    }

    public function faceMatching(Request $request, FaceMatcherService $faceMatcher): JsonResponse
    {
        if (strtoupper((string) $request->method()) !== 'POST') {
            return response()->json(['success' => false, 'message' => 'Method not allowed']);
        }

        $studentId = (int) session('student_id', 0);
        if ($studentId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized']);
        }

        $capturedImage = trim((string) $request->input('captured_image', ''));
        if ($capturedImage === '') {
            return response()->json(['success' => false, 'message' => 'Data tidak lengkap']);
        }

        $studentNisn = trim((string) session('student_nisn', ''));
        $studentName = trim((string) session('student_name', ''));
        $photoReference = trim((string) session('photo_reference', ''));

        if ($studentNisn === '' || $studentName === '') {
            $student = DB::table('student')
                ->select('student_nisn', 'student_name', 'photo_reference')
                ->where('id', $studentId)
                ->first();
            if ($student) {
                $studentNisn = $studentNisn !== '' ? $studentNisn : trim((string) ($student->student_nisn ?? ''));
                $studentName = $studentName !== '' ? $studentName : trim((string) ($student->student_name ?? ''));
                $photoReference = $photoReference !== '' ? $photoReference : trim((string) ($student->photo_reference ?? ''));
            }
        }

        if ($studentNisn === '') {
            return response()->json(['success' => false, 'message' => 'NISN tidak ditemukan']);
        }

        $referencePath = $faceMatcher->getReferencePath($studentNisn, $photoReference !== '' ? $photoReference : null);
        if (!$referencePath) {
            return response()->json(['success' => false, 'message' => 'Foto referensi tidak ditemukan']);
        }

        $selfieResult = $faceMatcher->saveSelfie($studentId, $capturedImage);
        if (empty($selfieResult['success'])) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan foto']);
        }

        $label = $studentName !== '' ? $studentName : $studentNisn;
        $matchResult = $faceMatcher->matchFaces($referencePath, (string) $selfieResult['path'], [
            'label' => $label,
        ]);

        if (!empty($selfieResult['path']) && is_file((string) $selfieResult['path'])) {
            @unlink((string) $selfieResult['path']);
        }

        if (empty($matchResult['success'])) {
            return response()->json([
                'success' => false,
                'message' => $matchResult['error'] ?? 'Gagal memproses wajah',
            ]);
        }

        $similarity = (float) ($matchResult['similarity'] ?? 0);
        $passed = !empty($matchResult['passed']);
        $payload = [
            'success' => true,
            'passed' => $passed,
            'similarity' => $similarity,
            'threshold' => (float) (env('FACE_MATCH_THRESHOLD', 89)),
            'label' => $label,
            'details' => $matchResult['details'] ?? [],
        ];

        if (!$passed) {
            session()->forget('face_match_ticket');
            $payload['message'] = 'Verifikasi wajah belum lolos';

            return response()->json($payload);
        }

        try {
            $matchToken = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            $matchToken = sha1($studentId . '|' . microtime(true) . '|' . mt_rand());
        }
        $expiresAt = time() + 600;
        session([
            'face_match_ticket' => [
                'token' => $matchToken,
                'student_id' => $studentId,
                'passed' => true,
                'similarity' => $similarity,
                'threshold' => (float) (env('FACE_MATCH_THRESHOLD', 89)),
                'issued_at' => time(),
                'expires_at' => $expiresAt,
            ],
        ]);

        $payload['match_token'] = $matchToken;
        $payload['token_expires_at'] = $expiresAt;
        $payload['message'] = 'Verifikasi wajah berhasil';

        $this->notifyStudent(
            $studentId,
            'deepface_verified',
            'Verifikasi Wajah Berhasil',
            'DeepFace berhasil memverifikasi wajah Anda dengan skor ' . number_format($similarity, 2) . '%.',
            '/dashboard/siswa.php?page=face_recognition'
        );

        return response()->json($payload);
    }

    public function savePoseFrames(Request $request): JsonResponse
    {
        if (strtoupper((string) $request->method()) !== 'POST') {
            return response()->json(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $studentId = (int) session('student_id', 0);
        if ($studentId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();
        if (!is_array($payload) || $payload === []) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        if (!is_array($payload)) {
            return response()->json(['success' => false, 'message' => 'Payload tidak valid'], 400);
        }

        $rightFrames = isset($payload['right']) && is_array($payload['right']) ? $payload['right'] : [];
        $leftFrames = isset($payload['left']) && is_array($payload['left']) ? $payload['left'] : [];
        $frontFrames = isset($payload['front']) && is_array($payload['front']) ? $payload['front'] : [];
        if (count($rightFrames) < 5 || count($leftFrames) < 5 || count($frontFrames) < 1) {
            return response()->json(['success' => false, 'message' => 'Jumlah frame pose belum lengkap'], 422);
        }

        $student = DB::table('student as s')
            ->leftJoin('class as c', 's.class_id', '=', 'c.class_id')
            ->where('s.id', $studentId)
            ->select('s.student_nisn', 's.student_name', 'c.class_name')
            ->first();

        $nisn = trim((string) ($student->student_nisn ?? ''));
        if ($nisn === '') {
            return response()->json(['success' => false, 'message' => 'NISN siswa tidak ditemukan'], 422);
        }

        $classFolder = $this->storageClassFolder((string) ($student->class_name ?? 'kelas'));
        $studentFolder = $this->storageStudentFolder((string) ($student->student_name ?? ('siswa_' . $nisn)));
        $poseDir = public_path('uploads/faces/' . $classFolder . '/' . $studentFolder . '/pose');
        if (!is_dir($poseDir) && !@mkdir($poseDir, 0777, true) && !is_dir($poseDir)) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat folder pose'], 500);
        }

        $oldFiles = glob($poseDir . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($oldFiles as $oldFile) {
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        $savedRight = $this->writePoseFrames($rightFrames, 'right', 5, $poseDir);
        $savedLeft = $this->writePoseFrames($leftFrames, 'left', 5, $poseDir);
        $savedFront = $this->writePoseFrames($frontFrames, 'front', 1, $poseDir);

        if ($savedRight < 5 || $savedLeft < 5 || $savedFront < 1) {
            return response()->json(['success' => false, 'message' => 'Sebagian frame pose gagal disimpan'], 500);
        }

        $manifest = [
            'student_id' => $studentId,
            'student_nisn' => $nisn,
            'saved_at' => date('c'),
            'counts' => [
                'right' => $savedRight,
                'left' => $savedLeft,
                'front' => $savedFront,
            ],
        ];
        @file_put_contents($poseDir . DIRECTORY_SEPARATOR . 'pose_manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        session(['has_pose_capture' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Frame pose berhasil disimpan',
            'counts' => $manifest['counts'],
        ]);
    }

    public function registerFace(Request $request): JsonResponse
    {
        $studentId = (int) session('student_id', 0);
        if (!$this->hasAuthenticatedSession() || $studentId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized']);
        }

        $student = DB::table('student as s')
            ->leftJoin('class as c', 's.class_id', '=', 'c.class_id')
            ->where('s.id', $studentId)
            ->select('s.id', 's.photo_reference', 's.student_name', 's.student_nisn', 'c.class_name')
            ->first();

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan']);
        }
        if (!empty($student->photo_reference)) {
            return response()->json(['success' => false, 'message' => 'Wajah sudah terdaftar sebelumnya']);
        }

        $imageData = trim((string) $request->input('image_data', ''));
        if ($imageData === '') {
            return response()->json(['success' => false, 'message' => 'Tidak ada data gambar']);
        }

        $binary = $this->decodeBase64Image($imageData);
        if ($binary === null) {
            return response()->json(['success' => false, 'message' => 'Gambar tidak valid']);
        }

        $classFolder = $this->storageClassFolder((string) ($student->class_name ?? 'kelas'));
        $studentFolder = $this->storageStudentFolder((string) ($student->student_name ?? ('siswa_' . $studentId)));
        $filename = $this->storageFaceReferenceFilename((string) ($student->student_nisn ?? $studentId), (string) ($student->student_name ?? 'siswa'));
        $relativePath = $classFolder . '/' . $studentFolder . '/' . $filename;
        $faceDir = public_path('uploads/faces/' . $classFolder . '/' . $studentFolder);
        if (!is_dir($faceDir) && !@mkdir($faceDir, 0777, true) && !is_dir($faceDir)) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat folder wajah']);
        }

        $filePath = $faceDir . DIRECTORY_SEPARATOR . $filename;
        if (@file_put_contents($filePath, $binary) === false) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan gambar']);
        }

        $faceEmbedding = hash('sha256', $binary);
        $updated = DB::table('student')
            ->where('id', $studentId)
            ->update([
                'photo_reference' => $relativePath,
                'face_embedding' => $faceEmbedding,
                'last_face_update' => now(),
            ]);

        if ($updated === 0) {
            @unlink($filePath);

            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data ke database']);
        }

        session(['has_face' => true]);
        $this->logActivity($studentId, 'student', 'register_face', 'Registrasi wajah berhasil');
        $this->notifyStudent(
            $studentId,
            'face_registration_success',
            'Registrasi Wajah Berhasil',
            'Foto referensi wajah berhasil disimpan dan siap dipakai untuk verifikasi absensi.',
            '/dashboard/siswa.php?page=profil'
        );

        return response()->json([
            'success' => true,
            'message' => 'Registrasi wajah berhasil',
            'data' => ['filename' => $filename],
        ]);
    }

    public function saveAttendance(Request $request, FaceMatcherService $faceMatcher): JsonResponse
    {
        $requestPath = trim((string) $request->path(), '/');
        $studentId = (int) session('student_id', 0);
        if ($studentId <= 0) {
            return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Anda harus login terlebih dahulu'], 401);
        }

        $scheduleId = (int) ($request->input('student_schedule_id') ?: $request->input('schedule_id') ?: 0);
        $base64Image = (string) ($request->input('captured_image') ?: $request->input('photo_data') ?: $request->input('image_data') ?: '');
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $accuracy = $request->input('accuracy');
        $presentId = (int) ($request->input('present_id') ?: 1);
        $information = trim((string) $request->input('information', ''));
        $faceSimilarity = $request->input('face_similarity');
        $faceDistance = $request->input('face_distance');
        $faceVerified = (string) $request->input('face_verified', '') === '1';
        $faceMatchToken = trim((string) $request->input('face_match_token', ''));
        $validationSnapshot = trim((string) $request->input('validation_snapshot', ''));
        $descriptorThreshold = (float) env('FACE_DESCRIPTOR_THRESHOLD', 0.55);

        if (str_ends_with($requestPath, 'api/submit_attendance.php')) {
            if ($information === '') {
                $information = 'submitted_via_deprecated_api_submit_attendance';
            }
        }
        if (str_ends_with($requestPath, 'dashboard/ajax/save_attendance.php')) {
            if ($information === '') {
                $information = 'submitted_via_deprecated_dashboard_ajax_save_attendance';
            }
        }

        if ($scheduleId <= 0 || $base64Image === '' || $latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Data tidak lengkap'], 422);
        }

        try {
            $schedule = DB::table('student_schedule as ss')
                ->join('teacher_schedule as ts', 'ss.teacher_schedule_id', '=', 'ts.schedule_id')
                ->join('shift as sh', 'ts.shift_id', '=', 'sh.shift_id')
                ->join('student as s', 'ss.student_id', '=', 's.id')
                ->leftJoin('class as c', 's.class_id', '=', 'c.class_id')
                ->leftJoin('teacher as t', 'ts.teacher_id', '=', 't.id')
                ->leftJoin('day as d', 'ts.day_id', '=', 'd.day_id')
                ->where('ss.student_schedule_id', $scheduleId)
                ->where('ss.student_id', $studentId)
                ->where('ss.status', 'ACTIVE')
                ->select(
                    'ss.*',
                    'ss.time_in',
                    'ss.time_out',
                    'sh.shift_name',
                    'ts.subject',
                    's.student_nisn',
                    's.student_name',
                    's.class_id',
                    's.photo_reference',
                    'c.class_name',
                    't.teacher_name',
                    't.teacher_code',
                    'd.day_name'
                )
                ->first();

            if (!$schedule) {
                return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
            }

            $scheduleDate = (string) ($schedule->schedule_date ?? date('Y-m-d'));
            $timeIn = (string) ($schedule->time_in ?? '');
            $timeOut = (string) ($schedule->time_out ?? '');
            if ((string) ($schedule->shift_name ?? '') === 'Full Day') {
                $timeIn = '06:00:00';
                $timeOut = '23:00:00';
            }

            $site = DB::table('site')->select('time_tolerance')->first();
            $toleranceMinutes = max(0, (int) ($site->time_tolerance ?? 15));

            $tz = new \DateTimeZone('Asia/Jakarta');
            $now = new \DateTimeImmutable('now', $tz);
            $currentTime = $now->format('H:i:s');
            [$startDt, $endDt, $baseEndDt] = $this->buildScheduleWindow($scheduleDate, $timeIn, $timeOut, $tz, $toleranceMinutes);

            $canAttend = ($now >= $startDt && $now <= $endDt);
            $isLate = $toleranceMinutes > 0 && $now > $baseEndDt;
            $lateTime = $isLate ? max(0, (int) round(($now->getTimestamp() - $baseEndDt->getTimestamp()) / 60)) : 0;

            if (!$canAttend) {
                if ($now < $startDt) {
                    return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Belum masuk waktu absensi'], 403);
                }

                return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Waktu absensi sudah ditutup'], 403);
            }

            $gpsCheck = $this->checkLocationData((float) $latitude, (float) $longitude, $accuracy);
            if (!$gpsCheck['success']) {
                return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Gagal memvalidasi lokasi'], 422);
            }

            $withinRadius = !empty($gpsCheck['data']['within_radius']);
            if ($presentId === 1 && !$withinRadius) {
                return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Anda berada di luar radius sekolah'], 403);
            }

            $selfieResult = $faceMatcher->saveSelfie($studentId, $base64Image);
            if (empty($selfieResult['success']) || empty($selfieResult['path']) || !is_file((string) $selfieResult['path'])) {
                return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Gagal menyimpan foto'], 500);
            }

            $trustedMatch = null;
            $faceMatchTicket = session('face_match_ticket');
            if ($faceMatchToken !== '' && is_array($faceMatchTicket)) {
                $ticketToken = (string) ($faceMatchTicket['token'] ?? '');
                $ticketStudentId = (int) ($faceMatchTicket['student_id'] ?? 0);
                $ticketPassed = !empty($faceMatchTicket['passed']);
                $ticketExpires = (int) ($faceMatchTicket['expires_at'] ?? 0);
                if (
                    $ticketToken !== '' &&
                    hash_equals($ticketToken, $faceMatchToken) &&
                    $ticketStudentId === $studentId &&
                    $ticketPassed &&
                    $ticketExpires >= time()
                ) {
                    $trustedMatch = $faceMatchTicket;
                }
            }

            if ($trustedMatch) {
                $matchResult = [
                    'success' => true,
                    'passed' => true,
                    'similarity' => max(
                        (float) ($trustedMatch['similarity'] ?? 0),
                        is_numeric($faceSimilarity) ? (float) $faceSimilarity : 0.0,
                        (float) env('FACE_MATCH_THRESHOLD', 89)
                    ),
                    'details' => [
                        'match_source' => 'face_matching_ticket',
                        'ticket_issued_at' => (int) ($trustedMatch['issued_at'] ?? time()),
                        'ticket_expires_at' => (int) ($trustedMatch['expires_at'] ?? time()),
                        'client_distance' => is_numeric($faceDistance) ? (float) $faceDistance : null,
                    ],
                ];
                session()->forget('face_match_ticket');
            } else {
                $referencePath = $faceMatcher->getReferencePath(
                    (string) ($schedule->student_nisn ?? ''),
                    (string) ($schedule->photo_reference ?? '')
                );
                if (!$referencePath) {
                    @unlink((string) $selfieResult['path']);

                    return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Foto referensi tidak ditemukan'], 404);
                }

                $matchResult = $faceMatcher->matchFaces($referencePath, (string) $selfieResult['path'], [
                    'label' => (string) (($schedule->student_name ?? '') ?: ($schedule->student_nisn ?? '')),
                ]);

                if (empty($matchResult['success'])) {
                    @unlink((string) $selfieResult['path']);

                    return $this->attendanceJson($requestPath, [
                        'success' => false,
                        'message' => (string) ($matchResult['error'] ?? 'Gagal proses face matching'),
                    ], 500);
                }
            }

            $serverPassed = !empty($matchResult['passed']);
            $clientDistanceProvided = is_numeric($faceDistance) && (float) $faceDistance > 0 && (float) $faceDistance <= 2.0;
            $clientDistancePassed = $clientDistanceProvided ? ((float) $faceDistance <= $descriptorThreshold) : null;
            $clientStrongVerified = $faceVerified && $clientDistancePassed === true;

            if ($serverPassed && $clientDistancePassed === false) {
                $matchResult['details'] = array_merge((array) ($matchResult['details'] ?? []), [
                    'client_distance' => is_numeric($faceDistance) ? (float) $faceDistance : null,
                    'client_distance_threshold' => $descriptorThreshold,
                    'client_descriptor_mismatch' => true,
                ]);
            }

            if (!$serverPassed && !$clientStrongVerified) {
                $faceMatcher->saveMatchResult(
                    $studentId,
                    (float) ($matchResult['similarity'] ?? 0),
                    false,
                    array_merge((array) ($matchResult['details'] ?? []), [
                        'client_distance' => $clientDistanceProvided ? (float) $faceDistance : null,
                        'client_distance_threshold' => $descriptorThreshold,
                        'client_verified' => $faceVerified ? 1 : 0,
                    ])
                );
                @unlink((string) $selfieResult['path']);

                return $this->attendanceJson($requestPath, [
                    'success' => false,
                    'message' => 'Verifikasi wajah gagal',
                    'similarity' => (float) ($matchResult['similarity'] ?? 0),
                ], 403);
            }

            if (!$serverPassed && $clientStrongVerified) {
                $matchResult['passed'] = true;
                $matchResult['similarity'] = max(
                    (float) ($matchResult['similarity'] ?? 0),
                    is_numeric($faceSimilarity) ? (float) $faceSimilarity : 0.0,
                    (float) env('FACE_MATCH_THRESHOLD', 89)
                );
                $matchResult['details'] = array_merge((array) ($matchResult['details'] ?? []), [
                    'client_distance' => is_numeric($faceDistance) ? (float) $faceDistance : null,
                    'client_distance_threshold' => $descriptorThreshold,
                    'client_descriptor_override' => true,
                ]);
            }

            $matchLog = $faceMatcher->saveMatchResult(
                $studentId,
                (float) ($matchResult['similarity'] ?? 0),
                true,
                (array) ($matchResult['details'] ?? [])
            );

            $attendanceDate = $scheduleDate !== '' ? $scheduleDate : date('Y-m-d');
            $dateTimeFolder = $this->storageAttendanceDatetimeFolder($attendanceDate, $currentTime);
            $classFolder = $this->storageClassFolder((string) (($schedule->class_name ?? '') ?: ($schedule->class_id ?? 'kelas')));
            $attendanceDir = public_path('uploads/attendance/' . $dateTimeFolder . '/' . $classFolder);
            if (!is_dir($attendanceDir) && !@mkdir($attendanceDir, 0777, true) && !is_dir($attendanceDir)) {
                @unlink((string) $selfieResult['path']);

                return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Gagal membuat folder absensi'], 500);
            }

            $studentNameRaw = (string) (($schedule->student_name ?? '') ?: ('siswa_' . $studentId));
            $studentNisn = (string) ($schedule->student_nisn ?? '');
            $baseFilename = $this->storageAttendanceBasename($studentNameRaw, $studentNisn !== '' ? $studentNisn : (string) $studentId, $attendanceDate);

            $rawFilename = $baseFilename . '_raw.jpg';
            $rawPath = $attendanceDir . DIRECTORY_SEPARATOR . $rawFilename;
            if (!@rename((string) $selfieResult['path'], $rawPath)) {
                @unlink((string) $selfieResult['path']);

                return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Gagal memindahkan foto absensi'], 500);
            }

            $validationFilename = $baseFilename . '.jpg';
            $validationPath = $attendanceDir . DIRECTORY_SEPARATOR . $validationFilename;
            $validationGenerated = false;
            if ($validationSnapshot !== '') {
                $validationGenerated = $this->saveBase64Image($validationSnapshot, $validationPath);
            }

            $storedFilename = $validationGenerated ? $validationFilename : $rawFilename;
            $attendancePath = $validationGenerated ? $validationPath : $rawPath;
            $attendanceFilename = $dateTimeFolder . '/' . $classFolder . '/' . $storedFilename;

            if ($validationGenerated && is_file($rawPath)) {
                @unlink($rawPath);
            }

            DB::beginTransaction();
            try {
                DB::table('presence')->insert([
                    'student_id' => $studentId,
                    'student_schedule_id' => $scheduleId,
                    'presence_date' => $scheduleDate,
                    'time_in' => $currentTime,
                    'picture_in' => $attendanceFilename,
                    'present_id' => $presentId,
                    'latitude_in' => $latitude,
                    'longitude_in' => $longitude,
                    'distance_in' => (float) ($gpsCheck['data']['distance'] ?? 0),
                    'is_late' => $isLate ? 'Y' : 'N',
                    'late_time' => $lateTime,
                    'information' => $information,
                ]);

                DB::table('student_schedule')
                    ->where('student_schedule_id', $scheduleId)
                    ->update([
                        'status' => 'COMPLETED',
                        'updated_at' => now(),
                    ]);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->logAttendanceError('save_attendance_db_failed', [
                    'message' => $e->getMessage(),
                    'student_id' => $studentId,
                    'schedule_id' => $scheduleId,
                ]);

                return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Gagal menyimpan data absensi'], 500);
            }

            $presentLabel = $presentId === 2 ? 'Sakit' : ($presentId === 3 ? 'Izin' : 'Hadir');
            $subjectName = trim((string) ($schedule->subject ?? ''));
            $className = trim((string) ($schedule->class_name ?? ''));
            $dateLabel = $scheduleDate !== '' ? date('d/m/Y', strtotime($scheduleDate)) : $scheduleDate;
            $timeLabel = $currentTime !== '' ? date('H:i', strtotime($currentTime)) : $currentTime;
            $detailParts = ["Absensi {$presentLabel}"];
            if ($subjectName !== '') {
                $detailParts[] = "Mapel: {$subjectName}";
            }
            if ($className !== '') {
                $detailParts[] = "Kelas: {$className}";
            }
            if ($dateLabel !== '' || $timeLabel !== '') {
                $detailParts[] = 'Waktu: ' . trim($dateLabel . ' ' . $timeLabel);
            }
            if ($presentLabel === 'Hadir' && $isLate) {
                $detailParts[] = "Terlambat: {$lateTime} menit";
            }
            $this->logActivity($studentId, 'student', 'attendance', implode(' | ', $detailParts));

            $scheduleLabelParts = [];
            if ($subjectName !== '') {
                $scheduleLabelParts[] = $subjectName;
            }
            if ($className !== '') {
                $scheduleLabelParts[] = $className;
            }
            $scheduleLabel = $scheduleLabelParts !== [] ? implode(' - ', $scheduleLabelParts) : 'jadwal saat ini';

            if ($isLate) {
                $this->notifyStudent(
                    $studentId,
                    'attendance_overdue',
                    'Absensi Terlambat',
                    "Absensi {$presentLabel} tercatat terlambat {$lateTime} menit pada {$scheduleLabel}.",
                    '/dashboard/siswa.php?page=riwayat',
                    $scheduleId > 0 ? $scheduleId : null
                );
            } else {
                $this->notifyStudent(
                    $studentId,
                    'attendance_success',
                    'Absensi Berhasil',
                    "Absensi {$presentLabel} berhasil tercatat pada {$scheduleLabel}.",
                    '/dashboard/siswa.php?page=riwayat',
                    $scheduleId > 0 ? $scheduleId : null
                );
            }

            return $this->attendanceJson($requestPath, [
                'success' => true,
                'message' => 'Absensi berhasil',
                'data' => [
                    'similarity' => (float) ($matchResult['similarity'] ?? 0),
                    'match_log' => $matchLog,
                    'attendance_path' => str_replace('\\', '/', str_replace(public_path() . DIRECTORY_SEPARATOR, '', (string) $attendancePath)),
                    'validation_path' => $validationGenerated
                        ? str_replace('\\', '/', str_replace(public_path() . DIRECTORY_SEPARATOR, '', (string) $validationPath))
                        : null,
                    'status' => $isLate ? 'OVERDUE' : 'SUCCESS',
                    'attendance_time' => $currentTime,
                    'attendance_date' => $scheduleDate,
                    'day_name' => $schedule->day_name ?? null,
                    'subject' => $schedule->subject ?? null,
                    'jp_range' => (string) (($schedule->shift_name ?? '') ?: ($timeIn . ' - ' . $timeOut)),
                    'teacher_name' => $schedule->teacher_name ?? null,
                    'student_name' => $schedule->student_name ?? null,
                    'present_label' => $presentLabel,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logAttendanceError('save_attendance_failed', [
                'message' => $e->getMessage(),
                'student_id' => $studentId,
                'schedule_id' => $scheduleId,
            ]);
            if ($studentId > 0) {
                $this->notifyStudent(
                    $studentId,
                    'system_error',
                    'Gangguan Sistem Absensi',
                    'Sistem mengalami kendala saat menyimpan absensi. Silakan coba kembali beberapa saat lagi.',
                    '/dashboard/siswa.php?page=face_recognition',
                    $scheduleId > 0 ? $scheduleId : null
                );
            }

            return $this->attendanceJson($requestPath, ['success' => false, 'message' => 'Terjadi kesalahan server. Silakan coba lagi.'], 500);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function attendanceJson(string $requestPath, array $payload, int $statusCode = 200): JsonResponse
    {
        $response = response()->json($payload, $statusCode);
        if (str_ends_with($requestPath, 'api/submit_attendance.php') || str_ends_with($requestPath, 'dashboard/ajax/save_attendance.php')) {
            $response->headers->set('X-Presenova-Deprecated', 'true');
            $response->headers->set('X-Presenova-Deprecated-Replacement', '/api/save_attendance.php');
        }

        return $response;
    }

    public function getAttendanceDetails(Request $request): Response
    {
        if (!$this->hasAuthenticatedSession()) {
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

    public function syncSchedule(Request $request): JsonResponse
    {
        if (strtoupper((string) $request->method()) !== 'POST') {
            return response()->json(['success' => false, 'message' => 'Method not allowed']);
        }

        $studentId = (int) ($request->input('student_id') ?: session('student_id', 0));
        if ($studentId <= 0) {
            return response()->json(['success' => false, 'message' => 'Student ID tidak valid']);
        }

        $student = DB::table('student')
            ->select('class_id')
            ->where('id', $studentId)
            ->first();
        if (!$student || (int) ($student->class_id ?? 0) <= 0) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan atau belum memiliki kelas']);
        }

        $added = $this->scheduleSyncService->ensureStudentSchedulesForStudent(
            $studentId,
            (int) $student->class_id,
            6
        );

        return response()->json([
            'success' => true,
            'message' => 'Sinkronisasi berhasil',
            'added' => $added,
            'total_schedules' => $added,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getDefaultSchoolLocation(): ?array
    {
        $site = DB::table('site')
            ->select('default_location_id')
            ->first();
        $locationId = (int) ($site->default_location_id ?? 0);

        if ($locationId > 0) {
            $school = DB::table('school_location')
                ->where('location_id', $locationId)
                ->first();
            if ($school && (string) ($school->is_active ?? 'N') === 'Y') {
                return (array) $school;
            }
        }

        $fallback = DB::table('school_location')
            ->where('is_active', 'Y')
            ->orderByDesc('location_id')
            ->first();
        if (!$fallback) {
            return null;
        }

        return (array) $fallback;
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;
        $a = sin($deltaLat / 2) * sin($deltaLat / 2)
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function hasAuthenticatedSession(): bool
    {
        if (session('logged_in') !== true) {
            return false;
        }

        if ((int) session('student_id', 0) > 0) {
            return true;
        }
        if ((int) session('teacher_id', 0) > 0) {
            return true;
        }
        if ((int) session('user_id', 0) > 0) {
            return true;
        }

        return false;
    }

    private function detectBrowser(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }
        if (stripos($userAgent, 'Edg') !== false) {
            return 'Edge';
        }
        if (stripos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        }
        if (stripos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        }
        if (stripos($userAgent, 'Safari') !== false) {
            return 'Safari';
        }

        return null;
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

    private function logActivity(int $userId, string $userType, string $action, string $details = ''): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => $userId,
                'user_type' => $userType,
                'action' => $action,
                'details' => $details,
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Keep API non-blocking if logging fails.
        }
    }

    private function notifyStudent(
        int $studentId,
        string $type,
        string $title,
        string $body,
        string $url = '/dashboard/siswa.php?page=jadwal',
        ?int $scheduleId = null
    ): void {
        if ($studentId <= 0) {
            return;
        }

        try {
            $this->studentPushNotificationService->notifyStudent(
                $studentId,
                trim($type) !== '' ? trim($type) : 'system_notice',
                trim($title) !== '' ? trim($title) : 'Notifikasi Sistem',
                trim($body),
                $url,
                $scheduleId
            );
        } catch (\Throwable) {
            // Keep API response non-blocking.
        }
    }

    private function decodeBase64Image(string $dataUrl): ?string
    {
        if ($dataUrl === '') {
            return null;
        }
        if (!preg_match('#^data:image/(jpeg|jpg|png);base64,#i', $dataUrl)) {
            return null;
        }

        $raw = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
        $raw = str_replace(' ', '+', (string) $raw);
        $binary = base64_decode($raw, true);
        if ($binary === false || strlen($binary) < 128) {
            return null;
        }

        return $binary;
    }

    private function writePoseFrames(array $frames, string $prefix, int $maxCount, string $poseDir): int
    {
        $saved = 0;
        $limited = array_slice($frames, 0, $maxCount);
        foreach ($limited as $index => $frame) {
            $binary = $this->decodeBase64Image((string) $frame);
            if ($binary === null) {
                continue;
            }
            $filename = sprintf('%s_%02d.jpg', $prefix, $index + 1);
            $path = $poseDir . DIRECTORY_SEPARATOR . $filename;
            if (@file_put_contents($path, $binary) !== false) {
                $saved++;
            }
        }

        return $saved;
    }

    private function storageSlug(string $text, string $default = 'item'): string
    {
        $text = trim($text);
        if ($text === '') {
            return $default;
        }
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
        $text = preg_replace('~[^-\w]+~', '', $text) ?? '';
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text) ?? '';
        $text = strtolower($text);

        return $text !== '' ? $text : $default;
    }

    private function storageClassFolder(string $className): string
    {
        return $this->storageSlug($className, 'kelas');
    }

    private function storageStudentFolder(string $studentName): string
    {
        return $this->storageSlug($studentName, 'siswa');
    }

    private function storageFaceReferenceFilename(string $nisn, string $studentName, string $extension = 'jpg'): string
    {
        $studentSlug = $this->storageSlug($studentName, 'siswa');
        $nisn = trim($nisn);
        $ext = strtolower(trim($extension));
        if ($ext === '') {
            $ext = 'jpg';
        }
        $base = $nisn !== '' ? ($nisn . '-' . $studentSlug) : $studentSlug;

        return $base . '.' . $ext;
    }

    private function logAttendanceError(string $message, array $context = []): void
    {
        $logFile = public_path('uploads/temp/attendance_error.log');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $entry = [
            'time' => date('c'),
            'message' => $message,
            'context' => $context,
        ];

        @file_put_contents(
            $logFile,
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: \DateTimeImmutable}
     */
    private function buildScheduleWindow(
        string $scheduleDate,
        string $timeIn,
        string $timeOut,
        \DateTimeZone $tz,
        int $toleranceMinutes
    ): array {
        $scheduleDate = trim($scheduleDate) !== '' ? trim($scheduleDate) : date('Y-m-d');
        $timeIn = trim($timeIn) !== '' ? trim($timeIn) : '00:00:00';
        $timeOut = trim($timeOut) !== '' ? trim($timeOut) : '00:00:00';

        $start = new \DateTimeImmutable($scheduleDate . ' ' . $timeIn, $tz);
        $end = new \DateTimeImmutable($scheduleDate . ' ' . $timeOut, $tz);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        $toleranceMinutes = max(0, $toleranceMinutes);
        $baseEnd = $end;
        if ($toleranceMinutes > 0) {
            $baseEnd = $baseEnd->modify('-' . $toleranceMinutes . ' minutes');
        }
        if ($baseEnd < $start) {
            $baseEnd = $start;
        }

        return [$start, $end, $baseEnd];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkLocationData(float $latitude, float $longitude, mixed $accuracy = null): array
    {
        $school = $this->getDefaultSchoolLocation();
        if ($school === null) {
            return ['success' => false, 'message' => 'Lokasi sekolah belum dikonfigurasi'];
        }

        $distance = $this->calculateDistance(
            $latitude,
            $longitude,
            (float) ($school['latitude'] ?? 0),
            (float) ($school['longitude'] ?? 0)
        );

        $accuracyValue = is_numeric($accuracy) ? (float) $accuracy : null;
        $radius = (float) ($school['radius'] ?? 0);
        $accuracyBuffer = 0.0;
        if ($accuracyValue !== null && $accuracyValue > 0) {
            $accuracyBuffer = min($accuracyValue, max(50, $radius * 1.5));
        }

        return [
            'success' => true,
            'data' => [
                'distance' => round($distance, 2),
                'within_radius' => $distance <= ($radius + $accuracyBuffer),
                'radius_limit' => $school['radius'] ?? 0,
                'accuracy' => $accuracyValue !== null ? round($accuracyValue, 2) : null,
                'accuracy_buffer' => round($accuracyBuffer, 2),
            ],
        ];
    }

    private function storageIndonesianDayName(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return 'hari';
        }

        try {
            $dt = new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return 'hari';
        }

        $names = ['', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
        $index = (int) $dt->format('N');

        return $names[$index] ?? 'hari';
    }

    private function storageAttendanceDatetimeFolder(string $date, string $time): string
    {
        $datePart = '';
        $timePart = '';
        if (trim($date) !== '') {
            $timestamp = strtotime($date);
            if ($timestamp !== false) {
                $datePart = date('Y-m-d', $timestamp);
            }
        }
        if (trim($time) !== '') {
            $digits = preg_replace('/\D/', '', $time) ?? '';
            if (strlen($digits) >= 6) {
                $timePart = substr($digits, 0, 6);
            } elseif ($digits !== '') {
                $timePart = str_pad($digits, 6, '0', STR_PAD_RIGHT);
            }
        }
        if ($datePart === '') {
            $datePart = date('Y-m-d');
        }
        if ($timePart === '') {
            $timePart = date('His');
        }

        return $datePart . '_' . $timePart;
    }

    private function storageAttendanceBasename(string $studentName, string $nisn, string $date): string
    {
        $studentSlug = $this->storageSlug($studentName, 'siswa');
        $nisn = trim($nisn);
        $dayName = $this->storageIndonesianDayName($date);
        $parts = [$studentSlug];
        if ($nisn !== '') {
            $parts[] = $nisn;
        }
        $parts[] = $dayName;

        return implode('-', $parts);
    }

    private function saveBase64Image(string $base64Image, string $outputPath): bool
    {
        $base64Image = trim($base64Image);
        if ($base64Image === '') {
            return false;
        }

        $data = preg_replace('#^data:image/\w+;base64,#i', '', $base64Image);
        $binary = base64_decode((string) $data, true);
        if ($binary === false) {
            return false;
        }

        return @file_put_contents($outputPath, $binary) !== false;
    }
}

