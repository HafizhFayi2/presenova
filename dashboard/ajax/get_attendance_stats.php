<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../helpers/jp_time_helper.php';

header('Content-Type: application/json');

try {
    $db = new Database();

    $tz = new DateTimeZone('Asia/Jakarta');
    $today = new DateTime('now', $tz);
    $parse_filter_date = static function ($rawDate, DateTimeZone $timezone, DateTime $fallbackDate): DateTime {
        $value = trim((string)$rawDate);
        if ($value === '') {
            return clone $fallbackDate;
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            $parsed = DateTime::createFromFormat('!' . $format, $value, $timezone);
            if ($parsed instanceof DateTime) {
                $errors = DateTime::getLastErrors();
                if ($errors === false || (((int)$errors['warning_count'] === 0) && ((int)$errors['error_count'] === 0))) {
                    return $parsed;
                }
            }
        }

        try {
            return new DateTime($value, $timezone);
        } catch (Exception $e) {
            return clone $fallbackDate;
        }
    };

    $filter_date_legacy = $_GET['date'] ?? '';
    $filter_date_from = $_GET['date_from'] ?? ($filter_date_legacy !== '' ? $filter_date_legacy : $today->format('Y-m-d'));
    $filter_date_to = $_GET['date_to'] ?? ($filter_date_legacy !== '' ? $filter_date_legacy : $filter_date_from);
    $filter_class = trim((string)($_GET['class'] ?? ''));
    $filter_status = trim((string)($_GET['status'] ?? ''));

    $fromObj = $parse_filter_date($filter_date_from, $tz, new DateTime($today->format('Y-m-d'), $tz));
    $toObj = $parse_filter_date($filter_date_to, $tz, $fromObj);
    if ($fromObj > $toObj) {
        $tmp = $fromObj;
        $fromObj = $toObj;
        $toObj = $tmp;
    }
    $filter_date_from = $fromObj->format('Y-m-d');
    $filter_date_to = $toObj->format('Y-m-d');
    $cycle_start = $filter_date_from . ' 00:00:00';
    $cycle_end = $filter_date_to . ' 23:59:59';

    $status_rows = $db->query("SELECT present_id, present_name FROM present_status")->fetchAll();
    $alpa_status_ids = [];
    $status_name_by_id = [];
    foreach ($status_rows as $sr) {
        $sid = (string)($sr['present_id'] ?? '');
        $name = strtolower(trim((string)($sr['present_name'] ?? '')));
        if ($sid !== '') {
            $status_name_by_id[$sid] = $name;
        }
        if ($name === 'alpa' || $name === 'tidak hadir') {
            if ($sid !== '') {
                $alpa_status_ids[] = $sid;
            }
        }
    }
    $default_alpa_id = !empty($alpa_status_ids) ? (int)$alpa_status_ids[0] : 4;
    $resolve_status_category = static function (array $row) use ($status_name_by_id): string {
        $name = strtolower(trim((string)($row['present_name'] ?? '')));
        if ($name === '') {
            $sid = (string)($row['present_id'] ?? '');
            if ($sid !== '' && isset($status_name_by_id[$sid])) {
                $name = $status_name_by_id[$sid];
            }
        }

        if ($name === 'tidak hadir') {
            return 'alpa';
        }
        if (in_array($name, ['hadir', 'sakit', 'izin', 'alpa'], true)) {
            return $name;
        }
        return '';
    };

    $filter_status_normalized = strtolower(trim((string)$filter_status));
    $is_alpa_filter = $filter_status !== '' && (
        $filter_status_normalized === 'alpa' ||
        in_array((string)$filter_status, $alpa_status_ids, true)
    );

    // Presence rows
    $presence_sql = "SELECT p.present_id, p.is_late
                     FROM presence p
                     JOIN student s ON p.student_id = s.id
                     WHERE p.presence_date BETWEEN ? AND ?";
    $presence_params = [$cycle_start, $cycle_end];
    if ($filter_class !== '') {
        $presence_sql .= " AND s.class_id = ?";
        $presence_params[] = $filter_class;
    }
    if ($filter_status !== '' && !$is_alpa_filter) {
        $presence_sql .= " AND p.present_id = ?";
        $presence_params[] = $filter_status;
    }
    $presence_rows = $db->query($presence_sql, $presence_params)->fetchAll();

    // Alpa rows
    $alpa_sql = "SELECT ss.schedule_date,
                        COALESCE(ss.time_in, sh.time_in) as schedule_time_in,
                        COALESCE(ss.time_out, sh.time_out) as schedule_time_out
                 FROM student_schedule ss
                 JOIN student s ON ss.student_id = s.id
                 JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
                 LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
                 LEFT JOIN presence p ON p.student_schedule_id = ss.student_schedule_id
                 WHERE ss.schedule_date BETWEEN ? AND ?
                 AND p.presence_id IS NULL";
    $alpa_params = [$filter_date_from, $filter_date_to];
    if ($filter_class !== '') {
        $alpa_sql .= " AND s.class_id = ?";
        $alpa_params[] = $filter_class;
    }
    $alpa_rows_raw = $db->query($alpa_sql, $alpa_params)->fetchAll();
    $alpa_rows = [];
    foreach ($alpa_rows_raw as $row) {
        $schedule_date = $row['schedule_date'] ?? '';
        if (!$schedule_date) {
            continue;
        }
        [$start_dt, $end_dt] = buildScheduleWindow(
            $schedule_date,
            $row['schedule_time_in'] ?? '00:00:00',
            $row['schedule_time_out'] ?? '00:00:00',
            $tz,
            0
        );
        if ($today <= $end_dt) {
            continue;
        }
        $alpa_rows[] = $row;
    }

    // Gabungkan sesuai filter status
    $rows_for_count = $presence_rows;
    if ($is_alpa_filter) {
        $rows_for_count = [];
    }
    if ($is_alpa_filter) {
        $rows_for_count = array_map(function($row) {
            return ['present_id' => 0, 'present_name' => 'Alpa', 'is_late' => 'N'];
        }, $alpa_rows);
    } elseif ($filter_status === '') {
        foreach ($alpa_rows as $ignored) {
            $rows_for_count[] = ['present_id' => $default_alpa_id, 'present_name' => 'Alpa', 'is_late' => 'N'];
        }
    }

    $totalValue = count($rows_for_count);
    $presentValue = 0;
    $sickValue = 0;
    $permissionValue = 0;
    $lateValue = 0;
    $alpaValue = 0;
    foreach ($rows_for_count as $row) {
        $status_category = $resolve_status_category($row);
        if ($status_category === 'hadir') {
            $presentValue++;
            if (($row['is_late'] ?? 'N') === 'Y') {
                $lateValue++;
            }
        } elseif ($status_category === 'sakit') {
            $sickValue++;
        } elseif ($status_category === 'izin') {
            $permissionValue++;
        } elseif ($status_category === 'alpa') {
            $alpaValue++;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $totalValue,
            'present' => $presentValue,
            'sick' => $sickValue,
            'permission' => $permissionValue,
            'late' => $lateValue,
            'alpa' => $alpaValue,
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
