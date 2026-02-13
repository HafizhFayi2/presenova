<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/push_service.php';

if (!isPushEnabled()) {
    exit;
}

$timezone = new DateTimeZone('Asia/Jakarta');
$now = new DateTime('now', $timezone);
$nowMinute = $now->format('Y-m-d H:i:00');
$today = $now->format('Y-m-d');

$siteStmt = $db->query("SELECT time_tolerance FROM site LIMIT 1");
$siteSetting = $siteStmt ? $siteStmt->fetch(PDO::FETCH_ASSOC) : null;
$toleranceMinutes = isset($siteSetting['time_tolerance']) ? (int) $siteSetting['time_tolerance'] : 15;
if ($toleranceMinutes < 0) {
    $toleranceMinutes = 0;
}

function toDateTime($date, $time, $timezone) {
    if (empty($date) || empty($time)) {
        return null;
    }
    return DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, $timezone);
}

function isDueAtMinute($eventTime, $nowMinute) {
    if (!$eventTime) {
        return false;
    }
    return $eventTime->format('Y-m-d H:i:00') === $nowMinute;
}

function insertNotificationLog($db, $studentId, $scheduleId, $type, $scheduledAt) {
    $stmt = $db->query(
        "INSERT IGNORE INTO push_notification_logs (student_id, student_schedule_id, type, scheduled_at, status, created_at)
         VALUES (?, ?, ?, ?, 'PENDING', NOW())",
        [$studentId, $scheduleId, $type, $scheduledAt]
    );
    if ($stmt && $stmt->rowCount() > 0) {
        return $db->lastInsertId();
    }
    return null;
}

function updateNotificationLog($db, $logId, $status, $errorMessage = null) {
    $db->query(
        "UPDATE push_notification_logs SET status = ?, sent_at = NOW(), error_message = ? WHERE id = ?",
        [$status, $errorMessage, $logId]
    );
}

function pushPayload($title, $body, $url) {
    return [
        'title' => $title,
        'body' => $body,
        'url' => $url
    ];
}

$tokens = fetchActivePushTokens($db);
$tokensByStudent = [];
foreach ($tokens as $token) {
    $tokensByStudent[$token['student_id']][] = $token;
}

if (empty($tokensByStudent)) {
    exit;
}

$sqlSchedules = "
    SELECT ss.student_schedule_id, ss.student_id, ss.schedule_date, ss.time_in, ss.time_out,
           ts.subject, sh.shift_name
    FROM student_schedule ss
    JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
    LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE ss.schedule_date = ?
      AND ss.status = 'ACTIVE'
";

$stmtSchedules = $db->query($sqlSchedules, [$today]);
$schedules = $stmtSchedules ? $stmtSchedules->fetchAll(PDO::FETCH_ASSOC) : [];

foreach ($schedules as $schedule) {
    $studentId = (int) $schedule['student_id'];
    if (empty($tokensByStudent[$studentId])) {
        continue;
    }

    $scheduleId = (int) $schedule['student_schedule_id'];
    $subject = $schedule['subject'] ?? 'Mata pelajaran';
    $timeIn = $schedule['time_in'];
    $timeOut = $schedule['time_out'];

    $timeInDt = toDateTime($schedule['schedule_date'], $timeIn, $timezone);
    $timeOutDt = toDateTime($schedule['schedule_date'], $timeOut, $timezone);

    if (!$timeInDt || !$timeOutDt) {
        continue;
    }

    $reminderDt = (clone $timeInDt)->modify('-2 minutes');
    $lastTenDt = (clone $timeOutDt)->modify('-10 minutes');
    $toleranceStartDt = (clone $timeOutDt)->modify('-' . $toleranceMinutes . ' minutes');
    $overdueStartDt = clone $timeOutDt;

    $events = [
        [
            'type' => 'reminder_2min',
            'time' => $reminderDt,
            'title' => 'Pengingat Jadwal',
            'body' => "2 menit lagi jadwal $subject dimulai. Siapkan absensi wajah.",
            'url' => '/dashboard/siswa.php?page=face_recognition'
        ],
        [
            'type' => 'schedule_start',
            'time' => $timeInDt,
            'title' => 'Jadwal Dimulai',
            'body' => "Jadwal $subject sudah dimulai. Anda sudah bisa absen wajah.",
            'url' => '/dashboard/siswa.php?page=face_recognition'
        ],
        [
            'type' => 'last_10_min',
            'time' => $lastTenDt,
            'title' => 'Sisa 10 Menit',
            'body' => "Sisa 10 menit sebelum jadwal $subject berakhir. Segera absensi.",
            'url' => '/dashboard/siswa.php?page=face_recognition'
        ]
    ];

    if ($toleranceMinutes > 0) {
        $events[] = [
            'type' => 'tolerance_start',
            'time' => $toleranceStartDt,
            'title' => 'Masuk Waktu Toleransi',
            'body' => "Jadwal $subject memasuki waktu toleransi. Segera absensi sebelum terlambat.",
            'url' => '/dashboard/siswa.php?page=face_recognition'
        ];
        $events[] = [
            'type' => 'overdue_start',
            'time' => $overdueStartDt,
            'title' => 'Absensi Overdue',
            'body' => "Waktu toleransi $subject selesai. Absensi dianggap overdue.",
            'url' => '/dashboard/siswa.php?page=face_recognition'
        ];
    }

    foreach ($events as $event) {
        if (!isDueAtMinute($event['time'], $nowMinute)) {
            continue;
        }

        $logId = insertNotificationLog(
            $db,
            $studentId,
            $scheduleId,
            $event['type'],
            $event['time']->format('Y-m-d H:i:s')
        );

        if (!$logId) {
            continue;
        }

        $payload = pushPayload($event['title'], $event['body'], $event['url']);
        $result = sendPushToStudent($db, $studentId, $payload);

        if ($result['failed'] === 0) {
            updateNotificationLog($db, $logId, 'SENT');
        } else {
            $errorMessage = !empty($result['errors']) ? implode(' | ', $result['errors']) : 'Push failed';
            updateNotificationLog($db, $logId, 'FAILED', $errorMessage);
        }
    }
}

// Tomorrow schedule notification (default 18:00)
$tomorrowNotifyTime = '18:00:00';
$tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
$tomorrowDt = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $tomorrowNotifyTime, $timezone);

if ($tomorrowDt && isDueAtMinute($tomorrowDt, $nowMinute)) {
    $sqlTomorrow = "
        SELECT ss.student_id, ss.time_in, ss.time_out, ts.subject
        FROM student_schedule ss
        JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
        WHERE ss.schedule_date = ?
          AND ss.status = 'ACTIVE'
        ORDER BY ss.time_in ASC
    ";

    $stmtTomorrow = $db->query($sqlTomorrow, [$tomorrow]);
    $tomorrowRows = $stmtTomorrow ? $stmtTomorrow->fetchAll(PDO::FETCH_ASSOC) : [];

    $byStudent = [];
    foreach ($tomorrowRows as $row) {
        $byStudent[$row['student_id']][] = $row;
    }

    foreach ($tokensByStudent as $studentId => $list) {
        $rows = $byStudent[$studentId] ?? [];

        if (empty($rows)) {
            $body = 'Besok tidak ada jadwal pelajaran. Nikmati waktu istirahat.';
        } else {
            $items = [];
            foreach ($rows as $row) {
                $timeRange = substr($row['time_in'], 0, 5) . '-' . substr($row['time_out'], 0, 5);
                $items[] = ($row['subject'] ?? 'Pelajaran') . ' ' . $timeRange;
                if (count($items) >= 3) {
                    break;
                }
            }
            $moreCount = max(0, count($rows) - count($items));
            $body = 'Jadwal besok: ' . implode(', ', $items);
            if ($moreCount > 0) {
                $body .= " (+$moreCount lainnya)";
            }
        }

        $logId = insertNotificationLog(
            $db,
            $studentId,
            null,
            'tomorrow_schedule',
            $tomorrowDt->format('Y-m-d H:i:s')
        );

        if (!$logId) {
            continue;
        }

        $payload = pushPayload('Jadwal Esok Hari', $body, '/dashboard/siswa.php?page=jadwal');
        $result = sendPushToStudent($db, $studentId, $payload);

        if ($result['failed'] === 0) {
            updateNotificationLog($db, $logId, 'SENT');
        } else {
            $errorMessage = !empty($result['errors']) ? implode(' | ', $result['errors']) : 'Push failed';
            updateNotificationLog($db, $logId, 'FAILED', $errorMessage);
        }
    }
}
