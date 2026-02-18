<?php

namespace App\Support\Core;

use DateTime;
use DateTimeZone;
use PDO;

class DatabaseHelper
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    private function getFirstScheduleDate(DateTime $startDate, int $dayId): DateTime
    {
        $dayId = max(1, min(7, $dayId));
        $startDow = (int) $startDate->format('N');
        $diff = ($dayId - $startDow + 7) % 7;
        $first = clone $startDate;
        if ($diff > 0) {
            $first->modify('+' . $diff . ' days');
        }

        return $first;
    }

    public function ensureStudentSchedulesForStudent($student_id, $class_id, int $months_ahead = 6): int
    {
        $student_id = (int) $student_id;
        $class_id = (int) $class_id;
        if ($student_id <= 0 || $class_id <= 0) {
            return 0;
        }

        $tz = new DateTimeZone('Asia/Jakarta');
        $startDate = new DateTime('today', $tz);
        $endDate = (clone $startDate)->modify('+' . max(1, $months_ahead) . ' months');
        $added = 0;

        $sql = "
            SELECT ts.schedule_id, ts.day_id, ts.class_id, sh.shift_name, sh.time_in, sh.time_out
            FROM teacher_schedule ts
            JOIN shift sh ON ts.shift_id = sh.shift_id
            JOIN day d ON ts.day_id = d.day_id
            WHERE ts.class_id = ?
            AND d.is_active = 'Y'
        ";
        $rows = $this->db->query($sql, [$class_id])?->fetchAll() ?: [];

        foreach ($rows as $row) {
            $dayId = (int) ($row['day_id'] ?? 0);
            if ($dayId <= 0) {
                continue;
            }
            $computedTimes = \calculateJpTimeRangeFromShiftForDay($this->db, $row['shift_name'] ?? '', $dayId);
            $timeIn = $computedTimes[0] ?? $row['time_in'];
            $timeOut = $computedTimes[1] ?? $row['time_out'];

            $date = $this->getFirstScheduleDate($startDate, $dayId);
            while ($date <= $endDate) {
                $scheduleDate = $date->format('Y-m-d');
                $exists = $this->db->query(
                    "SELECT student_schedule_id FROM student_schedule
                     WHERE student_id = ? AND teacher_schedule_id = ? AND schedule_date = ?
                     LIMIT 1",
                    [$student_id, $row['schedule_id'], $scheduleDate]
                )?->fetch();
                if (!$exists) {
                    $inserted = $this->db->query(
                        "INSERT INTO student_schedule
                         (student_id, teacher_schedule_id, schedule_date, time_in, time_out, status)
                         VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
                        [$student_id, $row['schedule_id'], $scheduleDate, $timeIn, $timeOut]
                    );
                    if ($inserted) {
                        $added++;
                    }
                }
                $date->modify('+7 days');
            }
        }

        return $added;
    }

    public function ensureStudentSchedulesForAll(int $months_ahead = 6): void
    {
        $tz = new DateTimeZone('Asia/Jakarta');
        $startDate = new DateTime('today', $tz);
        $endDate = (clone $startDate)->modify('+' . max(1, $months_ahead) . ' months');

        $sql = "
            SELECT ts.schedule_id, ts.day_id, ts.class_id, sh.shift_name, sh.time_in, sh.time_out
            FROM teacher_schedule ts
            JOIN shift sh ON ts.shift_id = sh.shift_id
            JOIN day d ON ts.day_id = d.day_id
            WHERE d.is_active = 'Y'
        ";
        $rows = $this->db->query($sql)?->fetchAll() ?: [];

        foreach ($rows as $row) {
            $dayId = (int) ($row['day_id'] ?? 0);
            $classId = (int) ($row['class_id'] ?? 0);
            if ($dayId <= 0 || $classId <= 0) {
                continue;
            }
            $computedTimes = \calculateJpTimeRangeFromShiftForDay($this->db, $row['shift_name'] ?? '', $dayId);
            $timeIn = $computedTimes[0] ?? $row['time_in'];
            $timeOut = $computedTimes[1] ?? $row['time_out'];

            $date = $this->getFirstScheduleDate($startDate, $dayId);
            while ($date <= $endDate) {
                $scheduleDate = $date->format('Y-m-d');
                $this->db->query(
                    "INSERT INTO student_schedule
                     (student_id, teacher_schedule_id, schedule_date, time_in, time_out, status)
                     SELECT s.id, ?, ?, ?, ?, 'ACTIVE'
                     FROM student s
                     WHERE s.class_id = ?
                     AND NOT EXISTS (
                         SELECT 1 FROM student_schedule ss
                         WHERE ss.student_id = s.id
                         AND ss.teacher_schedule_id = ?
                         AND ss.schedule_date = ?
                     )",
                    [
                        $row['schedule_id'],
                        $scheduleDate,
                        $timeIn,
                        $timeOut,
                        $classId,
                        $row['schedule_id'],
                        $scheduleDate,
                    ]
                );
                $date->modify('+7 days');
            }
        }
    }

    public function getStudentSchedule($student_id, $class_id)
    {
        $this->ensureStudentSchedulesForStudent($student_id, $class_id, 6);
        $tz = new DateTimeZone('Asia/Jakarta');
        $now = new DateTime('now', $tz);
        $weekStart = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
        $resetTime = (clone $weekStart)->modify('+5 days')->setTime(15, 0, 0);
        if ($now >= $resetTime) {
            $weekStart->modify('+7 days');
            $resetTime->modify('+7 days');
        }
        $cycleStart = $weekStart->format('Y-m-d');
        $cycleEnd = $resetTime->format('Y-m-d');

        $sql = "
            SELECT
                ts.schedule_id,
                ts.subject,
                t.teacher_name,
                t.teacher_code,
                ts.day_id,
                d.day_name,
                sh.shift_name,
                COALESCE(ss.time_in, sh.time_in) as time_in,
                COALESCE(ss.time_out, sh.time_out) as time_out,
                ss.student_schedule_id,
                ss.schedule_date,
                ss.status,
                (
                    SELECT p.is_late
                    FROM presence p
                    WHERE p.student_schedule_id = ss.student_schedule_id
                    ORDER BY p.time_in DESC
                    LIMIT 1
                ) as attendance_is_late,
                (
                    SELECT COUNT(*)
                    FROM presence p
                    JOIN student_schedule ssp ON p.student_schedule_id = ssp.student_schedule_id
                    WHERE ssp.teacher_schedule_id = ts.schedule_id
                    AND ssp.student_id = ?
                    AND p.presence_date BETWEEN ? AND ?
                ) as attendance_count
            FROM teacher_schedule ts
            JOIN teacher t ON ts.teacher_id = t.id
            JOIN day d ON ts.day_id = d.day_id
            JOIN shift sh ON ts.shift_id = sh.shift_id
            LEFT JOIN (
                SELECT ss1.teacher_schedule_id,
                       ss1.student_schedule_id,
                       ss1.schedule_date,
                       ss1.status,
                       ss1.time_in,
                       ss1.time_out
                FROM student_schedule ss1
                INNER JOIN (
                    SELECT teacher_schedule_id,
                           MIN(student_schedule_id) AS student_schedule_id
                    FROM student_schedule
                    WHERE student_id = ?
                    AND schedule_date BETWEEN ? AND ?
                    GROUP BY teacher_schedule_id
                ) ssmin
                    ON ss1.teacher_schedule_id = ssmin.teacher_schedule_id
                    AND ss1.student_schedule_id = ssmin.student_schedule_id
            ) ss ON ts.schedule_id = ss.teacher_schedule_id
            WHERE ts.class_id = ?
            AND d.is_active = 'Y'
            ORDER BY
                FIELD(d.day_name, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
                sh.time_in
        ";

        $stmt = $this->db->query($sql, [
            $student_id,
            $cycleStart,
            $cycleEnd,
            $student_id,
            $cycleStart,
            $cycleEnd,
            $class_id,
        ]);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as &$row) {
            $computed = \calculateJpTimeRangeFromShiftForDay($this->db, $row['shift_name'] ?? '', $row['day_id'] ?? 0);
            if ($computed) {
                $row['time_in'] = $computed[0];
                $row['time_out'] = $computed[1];
            }
        }
        unset($row);

        return $rows;
    }

    public function canAttendSchedule($student_schedule_id)
    {
        $sql = "
            SELECT
                ss.*,
                COALESCE(ss.time_in, sh.time_in) as time_in,
                COALESCE(ss.time_out, sh.time_out) as time_out,
                sh.shift_name
            FROM student_schedule ss
            JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
            JOIN shift sh ON ts.shift_id = sh.shift_id
            WHERE ss.student_schedule_id = ?
            AND ss.status = 'ACTIVE'
        ";

        $stmt = $this->db->query($sql, [$student_schedule_id]);
        $schedule = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$schedule) {
            return false;
        }

        $tz = new DateTimeZone('Asia/Jakarta');
        $now = new DateTime('now', $tz);
        $time_in = $schedule['time_in'];
        $time_out = $schedule['time_out'];
        $scheduleDate = $schedule['schedule_date'] ?? date('Y-m-d');

        if (($schedule['shift_name'] ?? '') === 'Full Day') {
            $time_in = '06:00:00';
            $time_out = '23:00:00';
        }
        [$start, $end] = \buildScheduleWindow($scheduleDate, $time_in, $time_out, $tz, 0);

        return ($now >= $start && $now <= $end);
    }
}

