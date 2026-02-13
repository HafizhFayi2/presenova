<?php
if (!function_exists('getJpDurationMinutes')) {
    function getJpDurationMinutes($jp) {
        return ($jp == 5 || $jp == 9) ? 15 : 45;
    }
}

if (!function_exists('getTimeToleranceMinutes')) {
    function getTimeToleranceMinutes($db) {
        try {
            $stmt = $db->query("SELECT time_tolerance FROM site LIMIT 1");
            $row = $stmt ? $stmt->fetch() : null;
            $minutes = isset($row['time_tolerance']) ? (int) $row['time_tolerance'] : 0;
            return max(0, $minutes);
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('parseJpRangeFromShift')) {
    function parseJpRangeFromShift($shift_name) {
        if (preg_match('/JP(\d+)-JP(\d+)/i', (string) $shift_name, $m)) {
            return [intval($m[1]), intval($m[2])];
        }
        return null;
    }
}

if (!function_exists('calculateJpTimeRange')) {
    function calculateJpTimeRange($jp_start, $jp_end, $base_start = '07:00', $pre_minutes = 0, $tolerance_minutes = 0) {
        $jp_start = intval($jp_start);
        $jp_end = intval($jp_end);
        if ($jp_start < 1 || $jp_start > 12 || $jp_end < 1 || $jp_end > 12 || $jp_end < $jp_start) {
            return null;
        }

        $base = DateTime::createFromFormat('H:i', (string) $base_start);
        if (!$base) {
            $base = DateTime::createFromFormat('H:i:s', (string) $base_start);
        }
        if (!$base) {
            return null;
        }

        $pre_minutes = max(0, intval($pre_minutes));
        if ($pre_minutes > 0) {
            $base->modify('+' . $pre_minutes . ' minutes');
        }

        $time_in_obj = clone $base;
        $minutes_before = 0;
        for ($jp = 1; $jp < $jp_start; $jp++) {
            $minutes_before += getJpDurationMinutes($jp);
        }
        if ($minutes_before > 0) {
            $time_in_obj->modify('+' . $minutes_before . ' minutes');
        }

        $duration_minutes = 0;
        for ($jp = $jp_start; $jp <= $jp_end; $jp++) {
            $duration_minutes += getJpDurationMinutes($jp);
        }

        $time_out_obj = clone $time_in_obj;
        $time_out_obj->modify('+' . $duration_minutes . ' minutes');

        $tolerance_minutes = max(0, (int) $tolerance_minutes);
        if ($tolerance_minutes > 0) {
            $time_out_obj->modify('+' . $tolerance_minutes . ' minutes');
        }

        return [
            $time_in_obj->format('H:i:s'),
            $time_out_obj->format('H:i:s')
        ];
    }
}

if (!function_exists('getDefaultDayId')) {
    function getDefaultDayId($db) {
        try {
            $row = $db->query(
                "SELECT d.day_id
                 FROM day d
                 LEFT JOIN day_schedule_config cfg ON cfg.day_id = d.day_id
                 WHERE d.is_active = 'Y'
                 ORDER BY COALESCE(cfg.activity1_minutes, 0) + COALESCE(cfg.activity2_minutes, 0) ASC,
                          d.day_order ASC
                 LIMIT 1"
            )?->fetch();
            if ($row && isset($row['day_id'])) {
                return (int) $row['day_id'];
            }
        } catch (Exception $e) {
            // ignore and fallback
        }
        return 1;
    }
}

if (!function_exists('getDayScheduleConfig')) {
    function getDayScheduleConfig($db, $day_id) {
        $day_id = intval($day_id);
        $default = [
            'school_start_time' => '06:30:00',
            'activity1_label' => '',
            'activity1_minutes' => 0,
            'activity2_label' => '',
            'activity2_minutes' => 0,
            'pre_minutes' => 0
        ];

        if ($day_id <= 0) {
            return $default;
        }

        static $cache = [];
        if (isset($cache[$day_id])) {
            return $cache[$day_id];
        }

        try {
            $row = $db->query(
                "SELECT school_start_time, activity1_label, activity1_minutes, activity2_label, activity2_minutes
                 FROM day_schedule_config WHERE day_id = ?",
                [$day_id]
            )?->fetch();
        } catch (Exception $e) {
            $row = null;
        }

        if (!$row) {
            $cache[$day_id] = $default;
            return $default;
        }

        $school_start_time = $row['school_start_time'] ?? $default['school_start_time'];
        $activity1_label = array_key_exists('activity1_label', $row)
            ? trim((string)$row['activity1_label'])
            : $default['activity1_label'];
        $activity2_label = array_key_exists('activity2_label', $row)
            ? trim((string)$row['activity2_label'])
            : $default['activity2_label'];
        $activity1_minutes = max(0, (int)($row['activity1_minutes'] ?? 0));
        $activity2_minutes = max(0, (int)($row['activity2_minutes'] ?? 0));

        $config = [
            'school_start_time' => $school_start_time,
            'activity1_label' => $activity1_label,
            'activity1_minutes' => $activity1_minutes,
            'activity2_label' => $activity2_label,
            'activity2_minutes' => $activity2_minutes,
            'pre_minutes' => $activity1_minutes + $activity2_minutes
        ];

        $cache[$day_id] = $config;
        return $config;
    }
}

if (!function_exists('calculateJpTimeRangeForDay')) {
    function calculateJpTimeRangeForDay($db, $jp_start, $jp_end, $day_id) {
        $config = getDayScheduleConfig($db, $day_id);
        $tolerance = getTimeToleranceMinutes($db);
        return calculateJpTimeRange($jp_start, $jp_end, $config['school_start_time'], $config['pre_minutes'], $tolerance);
    }
}

if (!function_exists('calculateJpTimeRangeFromShiftForDay')) {
    function calculateJpTimeRangeFromShiftForDay($db, $shift_name, $day_id) {
        $range = parseJpRangeFromShift($shift_name);
        if (!$range) return null;
        return calculateJpTimeRangeForDay($db, $range[0], $range[1], $day_id);
    }
}

if (!function_exists('buildScheduleWindow')) {
    function buildScheduleWindow($schedule_date, $time_in, $time_out, $tz = null, $tolerance_minutes = 0) {
        $tz = $tz ?: new DateTimeZone('Asia/Jakarta');
        $schedule_date = $schedule_date ?: date('Y-m-d');
        $time_in = $time_in ?: '00:00:00';
        $time_out = $time_out ?: '00:00:00';

        $start = new DateTime($schedule_date . ' ' . $time_in, $tz);
        $end = new DateTime($schedule_date . ' ' . $time_out, $tz);
        if ($end <= $start) {
            $end->modify('+1 day');
        }

        $tolerance_minutes = max(0, (int) $tolerance_minutes);
        $base_end = clone $end;
        if ($tolerance_minutes > 0) {
            $base_end->modify('-' . $tolerance_minutes . ' minutes');
        }
        if ($base_end < $start) {
            $base_end = clone $start;
        }

        return [$start, $end, $base_end];
    }
}
?>
