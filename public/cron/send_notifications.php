<?php
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$basePath = dirname(__DIR__, 2);
require $basePath . '/vendor/autoload.php';
$app = require $basePath . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pushEnabled = filter_var((string) env('PUSH_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
$publicKey = trim((string) env('PUSH_VAPID_PUBLIC_KEY', ''));
$privateKey = trim((string) env('PUSH_VAPID_PRIVATE_KEY', ''));
$subject = trim((string) env('PUSH_VAPID_SUBJECT', 'mailto:admin@presenova.local'));
$nodeBin = trim((string) env('PUSH_NODE_BIN', 'node'));
$sendScript = trim((string) env('PUSH_SEND_SCRIPT', public_path('scripts/webpush/send.js')));
$pushTtlSeconds = (int) env('PUSH_TTL_SECONDS', 86400);
if ($pushTtlSeconds < 60) {
    $pushTtlSeconds = 60;
} elseif ($pushTtlSeconds > 604800) {
    $pushTtlSeconds = 604800;
}

if (!$pushEnabled || $publicKey === '' || $privateKey === '' || $nodeBin === '' || $sendScript === '' || !is_file($sendScript)) {
    exit(0);
}

$timezone = new DateTimeZone('Asia/Jakarta');
$now = new DateTimeImmutable('now', $timezone);
$nowMinute = $now->format('Y-m-d H:i:00');
$today = $now->format('Y-m-d');
$toleranceMinutes = (int) (DB::table('site')->value('time_tolerance') ?? 15);
if ($toleranceMinutes < 0) {
    $toleranceMinutes = 0;
}

/**
 * @return DateTimeImmutable|null
 */
function toDateTime(string $date, string $time, DateTimeZone $timezone): ?DateTimeImmutable
{
    $date = trim($date);
    $time = trim($time);
    if ($date === '' || $time === '') {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, $timezone);
    return $dt instanceof DateTimeImmutable ? $dt : null;
}

function isDueAtMinute(?DateTimeImmutable $eventTime, string $nowMinute): bool
{
    if (!$eventTime) {
        return false;
    }

    return $eventTime->format('Y-m-d H:i:00') === $nowMinute;
}

function insertNotificationLog(int $studentId, ?int $scheduleId, string $type, string $scheduledAt): ?int
{
    $checkQuery = DB::table('push_notification_logs')
        ->where('student_id', $studentId)
        ->where('type', $type)
        ->where('scheduled_at', $scheduledAt);
    if ($scheduleId === null) {
        $checkQuery->whereNull('student_schedule_id');
    } else {
        $checkQuery->where('student_schedule_id', $scheduleId);
    }

    $existingId = (int) ($checkQuery->value('id') ?? 0);
    if ($existingId > 0) {
        return null;
    }

    DB::table('push_notification_logs')->insert([
        'student_id' => $studentId,
        'student_schedule_id' => $scheduleId,
        'type' => $type,
        'scheduled_at' => $scheduledAt,
        'status' => 'PENDING',
        'created_at' => now(),
    ]);

    $lastId = (int) DB::getPdo()->lastInsertId();
    if ($lastId > 0) {
        return $lastId;
    }

    return (int) ($checkQuery->orderByDesc('id')->value('id') ?? 0) ?: null;
}

function updateNotificationLog(int $logId, string $status, ?string $errorMessage = null): void
{
    DB::table('push_notification_logs')
        ->where('id', $logId)
        ->update([
            'status' => $status,
            'sent_at' => now(),
            'error_message' => $errorMessage,
        ]);
}

/**
 * @param array<int, array<string, mixed>> $tasks
 * @return array<string, array<string, mixed>>
 */
function dispatchPushTasks(array $tasks, string $nodeBin, string $sendScript, string $publicKey, string $privateKey, string $subject): array
{
    if ($tasks === []) {
        return [];
    }

    $payload = [
        'vapid' => [
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
            'subject' => $subject,
        ],
        'tasks' => $tasks,
    ];

    $storageApp = storage_path('app');
    if (!is_dir($storageApp)) {
        @mkdir($storageApp, 0777, true);
    }

    try {
        $token = bin2hex(random_bytes(8));
    } catch (Throwable) {
        $token = sha1(microtime(true) . '|' . mt_rand());
    }

    $inputPath = $storageApp . DIRECTORY_SEPARATOR . 'push-send-' . $token . '.json';
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || @file_put_contents($inputPath, $encoded) === false) {
        return [];
    }

    $command = escapeshellarg($nodeBin) . ' ' . escapeshellarg($sendScript) . ' ' . escapeshellarg($inputPath) . ' 2>&1';
    $raw = shell_exec($command);
    @unlink($inputPath);

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode(trim($raw), true);
    if (!is_array($decoded) || !isset($decoded['results']) || !is_array($decoded['results'])) {
        if (!preg_match('/\{.*\}\s*$/s', trim($raw), $match)) {
            return [];
        }
        $decoded = json_decode($match[0], true);
        if (!is_array($decoded) || !isset($decoded['results']) || !is_array($decoded['results'])) {
            return [];
        }
    }

    $results = [];
    foreach ($decoded['results'] as $row) {
        if (!is_array($row) || !isset($row['id'])) {
            continue;
        }
        $results[(string) $row['id']] = $row;
    }

    return $results;
}

/**
 * @param array<string, mixed> $payload
 * @return array{failed:int, errors:array<int, string>}
 */
function sendPushToStudent(
    int $studentId,
    array $payload,
    string $nodeBin,
    string $sendScript,
    string $publicKey,
    string $privateKey,
    string $subject,
    int $pushTtlSeconds
): array
{
    $tokens = DB::table('push_tokens')
        ->where('student_id', $studentId)
        ->where('is_active', 'Y')
        ->select('id', 'endpoint', 'p256dh', 'auth', 'content_encoding')
        ->get()
        ->map(static fn ($row) => (array) $row)
        ->all();

    if ($tokens === []) {
        return ['failed' => 0, 'errors' => []];
    }

    $tasks = [];
    foreach ($tokens as $token) {
        $endpoint = trim((string) ($token['endpoint'] ?? ''));
        $p256dh = trim((string) ($token['p256dh'] ?? ''));
        $auth = trim((string) ($token['auth'] ?? ''));
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            continue;
        }

        $tasks[] = [
            'id' => (string) ($token['id'] ?? ''),
            'subscription' => [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => $p256dh,
                    'auth' => $auth,
                ],
            ],
            'payload' => $payload,
            'options' => [
                'TTL' => $pushTtlSeconds,
            ],
        ];
    }

    if ($tasks === []) {
        return ['failed' => 0, 'errors' => []];
    }

    $results = dispatchPushTasks($tasks, $nodeBin, $sendScript, $publicKey, $privateKey, $subject);
    $failed = 0;
    $errors = [];

    foreach ($tasks as $task) {
        $taskId = (string) ($task['id'] ?? '');
        $result = $results[$taskId] ?? null;
        $isSuccess = is_array($result) && !empty($result['success']);

        if ($isSuccess) {
            continue;
        }

        $failed++;
        $message = is_array($result)
            ? trim((string) ($result['message'] ?? 'Push failed'))
            : 'Push dispatcher failed';
        $statusCode = is_array($result) ? (int) ($result['statusCode'] ?? 0) : 0;
        $errors[] = $message !== '' ? $message : 'Push failed';

        if (in_array($statusCode, [404, 410], true) && $taskId !== '') {
            DB::table('push_tokens')
                ->where('id', (int) $taskId)
                ->update([
                    'is_active' => 'N',
                    'updated_at' => now(),
                ]);
        }
    }

    return ['failed' => $failed, 'errors' => $errors];
}

$tokenStudentIds = DB::table('push_tokens')
    ->where('is_active', 'Y')
    ->distinct()
    ->pluck('student_id')
    ->map(static fn ($id) => (int) $id)
    ->all();

if ($tokenStudentIds === []) {
    exit(0);
}

$tokenStudentLookup = array_fill_keys($tokenStudentIds, true);

$schedules = DB::table('student_schedule as ss')
    ->join('teacher_schedule as ts', 'ss.teacher_schedule_id', '=', 'ts.schedule_id')
    ->leftJoin('shift as sh', 'ts.shift_id', '=', 'sh.shift_id')
    ->whereDate('ss.schedule_date', $today)
    ->where('ss.status', 'ACTIVE')
    ->select('ss.student_schedule_id', 'ss.student_id', 'ss.schedule_date', 'ss.time_in', 'ss.time_out', 'ts.subject', 'sh.shift_name')
    ->get()
    ->map(static fn ($row) => (array) $row)
    ->all();

$scheduleIds = array_values(array_filter(array_map(static fn ($row): int => (int) ($row['student_schedule_id'] ?? 0), $schedules)));
$presenceBySchedule = [];
if ($scheduleIds !== []) {
    $presenceRows = DB::table('presence')
        ->whereIn('student_schedule_id', $scheduleIds)
        ->whereDate('presence_date', $today)
        ->select('student_schedule_id', 'present_id', 'is_late')
        ->get()
        ->map(static fn ($row) => (array) $row)
        ->all();
    foreach ($presenceRows as $presenceRow) {
        $presenceScheduleId = (int) ($presenceRow['student_schedule_id'] ?? 0);
        if ($presenceScheduleId > 0) {
            $presenceBySchedule[$presenceScheduleId] = $presenceRow;
        }
    }
}

foreach ($schedules as $schedule) {
    $studentId = (int) ($schedule['student_id'] ?? 0);
    if ($studentId <= 0 || !isset($tokenStudentLookup[$studentId])) {
        continue;
    }

    $scheduleId = (int) ($schedule['student_schedule_id'] ?? 0);
    $subjectName = trim((string) ($schedule['subject'] ?? 'Mata pelajaran'));
    $timeIn = (string) ($schedule['time_in'] ?? '');
    $timeOut = (string) ($schedule['time_out'] ?? '');
    $scheduleDate = (string) ($schedule['schedule_date'] ?? '');

    $timeInDt = toDateTime($scheduleDate, $timeIn, $timezone);
    $timeOutDt = toDateTime($scheduleDate, $timeOut, $timezone);
    if (!$timeInDt || !$timeOutDt) {
        continue;
    }

    $hasAttendance = $scheduleId > 0 && isset($presenceBySchedule[$scheduleId]);
    $reminderDt = $timeInDt->modify('-2 minutes');
    $reminderFiveDt = $timeInDt->modify('-5 minutes');
    $lastTenDt = $timeOutDt->modify('-10 minutes');
    $toleranceStartDt = $timeOutDt->modify('-' . $toleranceMinutes . ' minutes');
    $overdueStartDt = $timeOutDt;

    $events = [
        ['type' => 'reminder_5min', 'time' => $reminderFiveDt, 'title' => 'Pengingat Jadwal', 'body' => "5 menit lagi jadwal {$subjectName} dimulai. Siapkan absensi wajah.", 'url' => '/dashboard/siswa.php?page=face_recognition'],
        ['type' => 'reminder_2min', 'time' => $reminderDt, 'title' => 'Pengingat Jadwal', 'body' => "2 menit lagi jadwal {$subjectName} dimulai. Siapkan absensi wajah.", 'url' => '/dashboard/siswa.php?page=face_recognition'],
        ['type' => 'schedule_start', 'time' => $timeInDt, 'title' => 'Jadwal Dimulai', 'body' => "Jadwal {$subjectName} sudah dimulai. Anda sudah bisa absen wajah.", 'url' => '/dashboard/siswa.php?page=face_recognition'],
        ['type' => 'last_10_min', 'time' => $lastTenDt, 'title' => 'Sisa 10 Menit', 'body' => "Sisa 10 menit sebelum jadwal {$subjectName} berakhir. Segera absensi.", 'url' => '/dashboard/siswa.php?page=face_recognition'],
    ];

    if ($toleranceMinutes > 0) {
        $events[] = ['type' => 'tolerance_start', 'time' => $toleranceStartDt, 'title' => 'Masuk Waktu Toleransi', 'body' => "Jadwal {$subjectName} memasuki waktu toleransi. Segera absensi sebelum terlambat.", 'url' => '/dashboard/siswa.php?page=face_recognition'];
        $events[] = ['type' => 'overdue_start', 'time' => $overdueStartDt, 'title' => 'Absensi Overdue', 'body' => "Waktu toleransi {$subjectName} selesai. Absensi dianggap overdue.", 'url' => '/dashboard/siswa.php?page=face_recognition'];
    }

    foreach ($events as $event) {
        if ($hasAttendance) {
            continue;
        }

        $eventTime = $event['time'] instanceof DateTimeImmutable ? $event['time'] : null;
        if (!isDueAtMinute($eventTime, $nowMinute)) {
            continue;
        }

        $logId = insertNotificationLog($studentId, $scheduleId > 0 ? $scheduleId : null, (string) $event['type'], $eventTime?->format('Y-m-d H:i:s') ?? $now->format('Y-m-d H:i:s'));
        if ($logId === null) {
            continue;
        }

        $result = sendPushToStudent(
            $studentId,
            ['title' => (string) $event['title'], 'body' => (string) $event['body'], 'url' => (string) $event['url']],
            $nodeBin,
            $sendScript,
            $publicKey,
            $privateKey,
            $subject,
            $pushTtlSeconds
        );

        if (($result['failed'] ?? 0) === 0) {
            updateNotificationLog($logId, 'SENT');
            continue;
        }

        $errors = $result['errors'] ?? [];
        $errorMessage = is_array($errors) && $errors !== [] ? implode(' | ', $errors) : 'Push failed';
        updateNotificationLog($logId, 'FAILED', $errorMessage);
    }

    if (!$hasAttendance && $now >= $reminderFiveDt && $now < $timeInDt) {
        $remainingSeconds = max(0, $timeInDt->getTimestamp() - $now->getTimestamp());
        $remainingMinutes = (int) ceil(max(0, $remainingSeconds) / 60);
        if ($remainingMinutes >= 0 && $remainingMinutes <= 5) {
            $countdownTitle = 'Countdown Absensi Berjalan';
            $countdownBody = $remainingMinutes > 0
                ? "Countdown jadwal {$subjectName}: {$remainingMinutes} menit menuju waktu mulai."
                : "Countdown jadwal {$subjectName}: waktu mulai sudah tiba, segera lakukan absensi.";
            $countdownLogId = insertNotificationLog(
                $studentId,
                $scheduleId > 0 ? $scheduleId : null,
                'countdown_running',
                $now->format('Y-m-d H:i:s')
            );

            if ($countdownLogId !== null) {
                $countdownResult = sendPushToStudent(
                    $studentId,
                    ['title' => $countdownTitle, 'body' => $countdownBody, 'url' => '/dashboard/siswa.php?page=face_recognition'],
                    $nodeBin,
                    $sendScript,
                    $publicKey,
                    $privateKey,
                    $subject,
                    $pushTtlSeconds
                );

                if (($countdownResult['failed'] ?? 0) === 0) {
                    updateNotificationLog($countdownLogId, 'SENT');
                } else {
                    $countdownErrors = $countdownResult['errors'] ?? [];
                    $countdownErrorMessage = is_array($countdownErrors) && $countdownErrors !== [] ? implode(' | ', $countdownErrors) : 'Push failed';
                    updateNotificationLog($countdownLogId, 'FAILED', $countdownErrorMessage);
                }
            }
        }
    }

    if (!$hasAttendance && $now >= $timeInDt && $now <= $timeOutDt) {
        $minuteNumber = (int) $now->format('i');
        if (($minuteNumber % 10) === 0) {
            $tenMinuteLogId = insertNotificationLog(
                $studentId,
                $scheduleId > 0 ? $scheduleId : null,
                'reminder_every_10min',
                $now->format('Y-m-d H:i:s')
            );

            if ($tenMinuteLogId !== null) {
                $tenMinuteResult = sendPushToStudent(
                    $studentId,
                    [
                        'title' => 'Pengingat Absensi',
                        'body' => "Anda belum absensi pada jadwal {$subjectName}. Pengingat otomatis tiap 10 menit selama jadwal masih aktif.",
                        'url' => '/dashboard/siswa.php?page=face_recognition',
                    ],
                    $nodeBin,
                    $sendScript,
                    $publicKey,
                    $privateKey,
                    $subject,
                    $pushTtlSeconds
                );

                if (($tenMinuteResult['failed'] ?? 0) === 0) {
                    updateNotificationLog($tenMinuteLogId, 'SENT');
                } else {
                    $tenMinuteErrors = $tenMinuteResult['errors'] ?? [];
                    $tenMinuteErrorMessage = is_array($tenMinuteErrors) && $tenMinuteErrors !== [] ? implode(' | ', $tenMinuteErrors) : 'Push failed';
                    updateNotificationLog($tenMinuteLogId, 'FAILED', $tenMinuteErrorMessage);
                }
            }
        }
    }

    if (!$hasAttendance && isDueAtMinute($timeOutDt, $nowMinute)) {
        $alpaLogId = insertNotificationLog(
            $studentId,
            $scheduleId > 0 ? $scheduleId : null,
            'alpa_detected',
            $timeOutDt->format('Y-m-d H:i:s')
        );

        if ($alpaLogId !== null) {
            $alpaResult = sendPushToStudent(
                $studentId,
                [
                    'title' => 'Status Alpa Terdeteksi',
                    'body' => "Jadwal {$subjectName} ditutup tanpa absensi. Status tercatat sebagai Alpa.",
                    'url' => '/dashboard/siswa.php?page=riwayat',
                ],
                $nodeBin,
                $sendScript,
                $publicKey,
                $privateKey,
                $subject,
                $pushTtlSeconds
            );

            if (($alpaResult['failed'] ?? 0) === 0) {
                updateNotificationLog($alpaLogId, 'SENT');
            } else {
                $alpaErrors = $alpaResult['errors'] ?? [];
                $alpaErrorMessage = is_array($alpaErrors) && $alpaErrors !== [] ? implode(' | ', $alpaErrors) : 'Push failed';
                updateNotificationLog($alpaLogId, 'FAILED', $alpaErrorMessage);
            }
        }
    }
}

$tomorrowNotifyTime = '18:00:00';
$tomorrow = $now->modify('+1 day')->format('Y-m-d');
$tomorrowDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $today . ' ' . $tomorrowNotifyTime, $timezone);

if ($tomorrowDt instanceof DateTimeImmutable && isDueAtMinute($tomorrowDt, $nowMinute)) {
    $tomorrowRows = DB::table('student_schedule as ss')
        ->join('teacher_schedule as ts', 'ss.teacher_schedule_id', '=', 'ts.schedule_id')
        ->whereDate('ss.schedule_date', $tomorrow)
        ->where('ss.status', 'ACTIVE')
        ->orderBy('ss.time_in')
        ->select('ss.student_id', 'ss.time_in', 'ss.time_out', 'ts.subject')
        ->get()
        ->map(static fn ($row) => (array) $row)
        ->all();

    $rowsByStudent = [];
    foreach ($tomorrowRows as $row) {
        $sid = (int) ($row['student_id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        if (!isset($rowsByStudent[$sid])) {
            $rowsByStudent[$sid] = [];
        }
        $rowsByStudent[$sid][] = $row;
    }

    foreach ($tokenStudentIds as $studentId) {
        $studentId = (int) $studentId;
        $rows = $rowsByStudent[$studentId] ?? [];

        if ($rows === []) {
            $body = 'Besok tidak ada jadwal pelajaran. Nikmati waktu istirahat.';
        } else {
            $items = [];
            foreach ($rows as $row) {
                $timeIn = substr((string) ($row['time_in'] ?? ''), 0, 5);
                $timeOut = substr((string) ($row['time_out'] ?? ''), 0, 5);
                $items[] = trim((string) ($row['subject'] ?? 'Pelajaran')) . ' ' . $timeIn . '-' . $timeOut;
                if (count($items) >= 3) {
                    break;
                }
            }
            $moreCount = max(0, count($rows) - count($items));
            $body = 'Jadwal besok: ' . implode(', ', $items);
            if ($moreCount > 0) {
                $body .= " (+{$moreCount} lainnya)";
            }
        }

        $logId = insertNotificationLog($studentId, null, 'tomorrow_schedule', $tomorrowDt->format('Y-m-d H:i:s'));
        if ($logId === null) {
            continue;
        }

        $result = sendPushToStudent(
            $studentId,
            ['title' => 'Jadwal Esok Hari', 'body' => $body, 'url' => '/dashboard/siswa.php?page=jadwal'],
            $nodeBin,
            $sendScript,
            $publicKey,
            $privateKey,
            $subject,
            $pushTtlSeconds
        );

        if (($result['failed'] ?? 0) === 0) {
            updateNotificationLog($logId, 'SENT');
            continue;
        }

        $errors = $result['errors'] ?? [];
        $errorMessage = is_array($errors) && $errors !== [] ? implode(' | ', $errors) : 'Push failed';
        updateNotificationLog($logId, 'FAILED', $errorMessage);
    }
}

$weeklyResetTime = '15:00:00';
$weeklyResetDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $today . ' ' . $weeklyResetTime, $timezone);
if ((int) $now->format('N') === 6 && $weeklyResetDt instanceof DateTimeImmutable && isDueAtMinute($weeklyResetDt, $nowMinute)) {
    $weekStartDate = $now->modify('monday this week')->format('Y-m-d');
    $weekEndDate = $today;

    $weeklySchedules = DB::table('student_schedule')
        ->whereIn('student_id', $tokenStudentIds)
        ->whereBetween('schedule_date', [$weekStartDate, $weekEndDate])
        ->whereIn('status', ['ACTIVE', 'COMPLETED', 'CLOSED'])
        ->select('student_schedule_id', 'student_id', 'schedule_date', 'time_in', 'time_out')
        ->get()
        ->map(static fn ($row) => (array) $row)
        ->all();

    $weeklyScheduleIds = array_values(array_filter(array_map(static fn ($row): int => (int) ($row['student_schedule_id'] ?? 0), $weeklySchedules)));
    $weeklyPresenceMap = [];
    if ($weeklyScheduleIds !== []) {
        $weeklyPresenceRows = DB::table('presence')
            ->whereIn('student_schedule_id', $weeklyScheduleIds)
            ->whereBetween('presence_date', [$weekStartDate, $weekEndDate])
            ->select('student_schedule_id', 'present_id', 'is_late')
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();

        foreach ($weeklyPresenceRows as $presenceRow) {
            $presenceScheduleId = (int) ($presenceRow['student_schedule_id'] ?? 0);
            if ($presenceScheduleId > 0) {
                $weeklyPresenceMap[$presenceScheduleId] = $presenceRow;
            }
        }
    }

    $studentSummary = [];
    foreach ($tokenStudentIds as $studentIdRaw) {
        $studentId = (int) $studentIdRaw;
        if ($studentId <= 0) {
            continue;
        }
        $studentSummary[$studentId] = [
            'hadir' => 0,
            'terlambat' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpa' => 0,
            'menunggu' => 0,
        ];
    }

    foreach ($weeklySchedules as $schedule) {
        $studentId = (int) ($schedule['student_id'] ?? 0);
        $scheduleId = (int) ($schedule['student_schedule_id'] ?? 0);
        if ($studentId <= 0 || !isset($studentSummary[$studentId]) || $scheduleId <= 0) {
            continue;
        }

        if (isset($weeklyPresenceMap[$scheduleId])) {
            $presentId = (int) ($weeklyPresenceMap[$scheduleId]['present_id'] ?? 0);
            $isLate = strtoupper((string) ($weeklyPresenceMap[$scheduleId]['is_late'] ?? 'N')) === 'Y';
            if ($presentId === 1) {
                if ($isLate) {
                    $studentSummary[$studentId]['terlambat']++;
                } else {
                    $studentSummary[$studentId]['hadir']++;
                }
            } elseif ($presentId === 2) {
                $studentSummary[$studentId]['sakit']++;
            } elseif ($presentId === 3) {
                $studentSummary[$studentId]['izin']++;
            } else {
                $studentSummary[$studentId]['alpa']++;
            }
            continue;
        }

        $scheduleDate = (string) ($schedule['schedule_date'] ?? '');
        $timeIn = (string) ($schedule['time_in'] ?? '');
        $timeOut = (string) ($schedule['time_out'] ?? '');
        $startDt = toDateTime($scheduleDate, $timeIn, $timezone);
        $endDt = toDateTime($scheduleDate, $timeOut, $timezone);
        if (!$startDt || !$endDt) {
            $studentSummary[$studentId]['menunggu']++;
            continue;
        }
        if ($endDt <= $startDt) {
            $endDt = $endDt->modify('+1 day');
        }

        if ($now > $endDt) {
            $studentSummary[$studentId]['alpa']++;
        } else {
            $studentSummary[$studentId]['menunggu']++;
        }
    }

    foreach ($studentSummary as $studentId => $summary) {
        $hadir = (int) ($summary['hadir'] ?? 0);
        $terlambat = (int) ($summary['terlambat'] ?? 0);
        $sakit = (int) ($summary['sakit'] ?? 0);
        $izin = (int) ($summary['izin'] ?? 0);
        $alpa = (int) ($summary['alpa'] ?? 0);
        $menunggu = (int) ($summary['menunggu'] ?? 0);
        $totalFinal = $hadir + $terlambat + $sakit + $izin + $alpa;

        $body = "Rekap reset Sabtu: Hadir {$hadir}, Terlambat {$terlambat}, Sakit {$sakit}, Izin {$izin}, Alpa {$alpa}.";
        if ($menunggu > 0) {
            $body .= " Jadwal menunggu final: {$menunggu}.";
        }
        $body .= " Total final: {$totalFinal}.";

        $logId = insertNotificationLog($studentId, null, 'weekly_reset_recap', $weeklyResetDt->format('Y-m-d H:i:s'));
        if ($logId === null) {
            continue;
        }

        $result = sendPushToStudent(
            $studentId,
            ['title' => 'Rekap Mingguan Absensi', 'body' => $body, 'url' => '/dashboard/siswa.php?page=riwayat'],
            $nodeBin,
            $sendScript,
            $publicKey,
            $privateKey,
            $subject,
            $pushTtlSeconds
        );

        if (($result['failed'] ?? 0) === 0) {
            updateNotificationLog($logId, 'SENT');
            continue;
        }

        $errors = $result['errors'] ?? [];
        $errorMessage = is_array($errors) && $errors !== [] ? implode(' | ', $errors) : 'Push failed';
        updateNotificationLog($logId, 'FAILED', $errorMessage);
    }
}

exit(0);
