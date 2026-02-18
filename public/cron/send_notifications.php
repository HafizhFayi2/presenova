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
    $inserted = DB::table('push_notification_logs')->insertOrIgnore([
        'student_id' => $studentId,
        'student_schedule_id' => $scheduleId,
        'type' => $type,
        'scheduled_at' => $scheduledAt,
        'status' => 'PENDING',
        'created_at' => now(),
    ]);

    if ($inserted <= 0) {
        return null;
    }

    return (int) DB::getPdo()->lastInsertId();
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
function sendPushToStudent(int $studentId, array $payload, string $nodeBin, string $sendScript, string $publicKey, string $privateKey, string $subject): array
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
                'TTL' => 120,
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

    $reminderDt = $timeInDt->modify('-2 minutes');
    $lastTenDt = $timeOutDt->modify('-10 minutes');
    $toleranceStartDt = $timeOutDt->modify('-' . $toleranceMinutes . ' minutes');
    $overdueStartDt = $timeOutDt;

    $events = [
        ['type' => 'reminder_2min', 'time' => $reminderDt, 'title' => 'Pengingat Jadwal', 'body' => "2 menit lagi jadwal {$subjectName} dimulai. Siapkan absensi wajah.", 'url' => '/dashboard/siswa.php?page=face_recognition'],
        ['type' => 'schedule_start', 'time' => $timeInDt, 'title' => 'Jadwal Dimulai', 'body' => "Jadwal {$subjectName} sudah dimulai. Anda sudah bisa absen wajah.", 'url' => '/dashboard/siswa.php?page=face_recognition'],
        ['type' => 'last_10_min', 'time' => $lastTenDt, 'title' => 'Sisa 10 Menit', 'body' => "Sisa 10 menit sebelum jadwal {$subjectName} berakhir. Segera absensi.", 'url' => '/dashboard/siswa.php?page=face_recognition'],
    ];

    if ($toleranceMinutes > 0) {
        $events[] = ['type' => 'tolerance_start', 'time' => $toleranceStartDt, 'title' => 'Masuk Waktu Toleransi', 'body' => "Jadwal {$subjectName} memasuki waktu toleransi. Segera absensi sebelum terlambat.", 'url' => '/dashboard/siswa.php?page=face_recognition'];
        $events[] = ['type' => 'overdue_start', 'time' => $overdueStartDt, 'title' => 'Absensi Overdue', 'body' => "Waktu toleransi {$subjectName} selesai. Absensi dianggap overdue.", 'url' => '/dashboard/siswa.php?page=face_recognition'];
    }

    foreach ($events as $event) {
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
            $subject
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
            $subject
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