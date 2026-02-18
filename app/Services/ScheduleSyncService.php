<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ScheduleSyncService
{
    public function ensureStudentSchedulesForStudent(int $studentId, int $classId, int $monthsAhead = 6): int
    {
        if ($studentId <= 0 || $classId <= 0) {
            return 0;
        }

        $monthsAhead = max(1, $monthsAhead);
        $tz = new \DateTimeZone('Asia/Jakarta');
        $startDate = new \DateTimeImmutable('today', $tz);
        $endDate = $startDate->modify('+' . $monthsAhead . ' months');
        $added = 0;

        $rows = DB::table('teacher_schedule as ts')
            ->join('shift as sh', 'ts.shift_id', '=', 'sh.shift_id')
            ->join('day as d', 'ts.day_id', '=', 'd.day_id')
            ->where('ts.class_id', $classId)
            ->where('d.is_active', 'Y')
            ->select('ts.schedule_id', 'ts.day_id', 'sh.shift_name', 'sh.time_in', 'sh.time_out')
            ->get();

        foreach ($rows as $row) {
            $dayId = (int) ($row->day_id ?? 0);
            if ($dayId <= 0) {
                continue;
            }

            [$timeIn, $timeOut] = $this->resolveShiftTimes(
                (string) ($row->shift_name ?? ''),
                (string) ($row->time_in ?? ''),
                (string) ($row->time_out ?? ''),
                $dayId
            );

            $date = $this->getFirstScheduleDate($startDate, $dayId);
            while ($date <= $endDate) {
                $scheduleDate = $date->format('Y-m-d');
                $exists = DB::table('student_schedule')
                    ->where('student_id', $studentId)
                    ->where('teacher_schedule_id', (int) $row->schedule_id)
                    ->where('schedule_date', $scheduleDate)
                    ->exists();

                if (!$exists) {
                    DB::table('student_schedule')->insert([
                        'student_id' => $studentId,
                        'teacher_schedule_id' => (int) $row->schedule_id,
                        'schedule_date' => $scheduleDate,
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                        'status' => 'ACTIVE',
                    ]);
                    $added++;
                }

                $date = $date->modify('+7 days');
            }
        }

        return $added;
    }

    private function getFirstScheduleDate(\DateTimeImmutable $startDate, int $dayId): \DateTimeImmutable
    {
        $dayId = max(1, min(7, $dayId));
        $startDow = (int) $startDate->format('N');
        $diff = ($dayId - $startDow + 7) % 7;
        if ($diff <= 0) {
            return $startDate;
        }

        return $startDate->modify('+' . $diff . ' days');
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveShiftTimes(string $shiftName, string $fallbackIn, string $fallbackOut, int $dayId): array
    {
        $jpRange = $this->parseJpRangeFromShift($shiftName);
        if ($jpRange === null) {
            return [
                $fallbackIn !== '' ? $fallbackIn : '00:00:00',
                $fallbackOut !== '' ? $fallbackOut : '00:00:00',
            ];
        }

        $config = $this->getDayScheduleConfig($dayId);
        $times = $this->calculateJpTimeRange(
            $jpRange[0],
            $jpRange[1],
            $config['school_start_time'],
            $config['pre_minutes'],
            $this->getTimeToleranceMinutes()
        );

        if ($times === null) {
            return [
                $fallbackIn !== '' ? $fallbackIn : '00:00:00',
                $fallbackOut !== '' ? $fallbackOut : '00:00:00',
            ];
        }

        return $times;
    }

    /**
     * @return array{0:int,1:int}|null
     */
    private function parseJpRangeFromShift(string $shiftName): ?array
    {
        if (!preg_match('/JP(\d+)-JP(\d+)/i', $shiftName, $matches)) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    /**
     * @return array{school_start_time:string,pre_minutes:int}
     */
    private function getDayScheduleConfig(int $dayId): array
    {
        $row = DB::table('day_schedule_config')
            ->select('school_start_time', 'activity1_minutes', 'activity2_minutes')
            ->where('day_id', $dayId)
            ->first();

        if (!$row) {
            return [
                'school_start_time' => '06:30:00',
                'pre_minutes' => 0,
            ];
        }

        return [
            'school_start_time' => (string) ($row->school_start_time ?? '06:30:00'),
            'pre_minutes' => max(0, (int) ($row->activity1_minutes ?? 0)) + max(0, (int) ($row->activity2_minutes ?? 0)),
        ];
    }

    private function getTimeToleranceMinutes(): int
    {
        $row = DB::table('site')->select('time_tolerance')->first();
        return max(0, (int) ($row->time_tolerance ?? 0));
    }

    private function getJpDurationMinutes(int $jp): int
    {
        return ($jp === 5 || $jp === 9) ? 15 : 45;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function calculateJpTimeRange(
        int $jpStart,
        int $jpEnd,
        string $baseStart = '07:00',
        int $preMinutes = 0,
        int $toleranceMinutes = 0
    ): ?array {
        if ($jpStart < 1 || $jpStart > 12 || $jpEnd < 1 || $jpEnd > 12 || $jpEnd < $jpStart) {
            return null;
        }

        $base = \DateTimeImmutable::createFromFormat('H:i', $baseStart)
            ?: \DateTimeImmutable::createFromFormat('H:i:s', $baseStart);
        if (!$base instanceof \DateTimeImmutable) {
            return null;
        }

        if ($preMinutes > 0) {
            $base = $base->modify('+' . $preMinutes . ' minutes');
        }

        $minutesBefore = 0;
        for ($jp = 1; $jp < $jpStart; $jp++) {
            $minutesBefore += $this->getJpDurationMinutes($jp);
        }

        $timeInObj = $minutesBefore > 0 ? $base->modify('+' . $minutesBefore . ' minutes') : $base;

        $durationMinutes = 0;
        for ($jp = $jpStart; $jp <= $jpEnd; $jp++) {
            $durationMinutes += $this->getJpDurationMinutes($jp);
        }

        $timeOutObj = $timeInObj->modify('+' . $durationMinutes . ' minutes');
        if ($toleranceMinutes > 0) {
            $timeOutObj = $timeOutObj->modify('+' . $toleranceMinutes . ' minutes');
        }

        return [
            $timeInObj->format('H:i:s'),
            $timeOutObj->format('H:i:s'),
        ];
    }
}

