<?php

namespace App\Http\Controllers\Dashboard\Print;

use App\Http\Controllers\Controller;
use App\Services\ScheduleSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SchedulePrintController extends Controller
{
    public function __construct(
        private readonly ScheduleSyncService $scheduleSyncService
    ) {
    }

    public function admin(Request $request): Response|RedirectResponse
    {
        if (!$this->isAdminSession()) {
            return redirect($this->appPath('login.php'));
        }

        $filterDay = $this->inputInt($request, 'filter_day');
        $filterShift = $this->inputInt($request, 'filter_shift');
        $filterTeacher = $this->inputInt($request, 'filter_teacher');
        $filterClass = $this->inputInt($request, 'filter_class');

        $query = DB::table('teacher_schedule as ts')
            ->leftJoin('teacher as t', 'ts.teacher_id', '=', 't.id')
            ->leftJoin('class as c', 'ts.class_id', '=', 'c.class_id')
            ->leftJoin('day as d', 'ts.day_id', '=', 'd.day_id')
            ->leftJoin('shift as sh', 'ts.shift_id', '=', 'sh.shift_id')
            ->select(
                'ts.schedule_id',
                'ts.day_id',
                'd.day_name',
                'd.day_order',
                't.teacher_name',
                'c.class_name',
                'ts.subject',
                'sh.shift_name',
                'sh.time_in',
                'sh.time_out'
            );

        if ($filterDay > 0) {
            $query->where('ts.day_id', $filterDay);
        }
        if ($filterShift > 0) {
            $query->where('ts.shift_id', $filterShift);
        }
        if ($filterTeacher > 0) {
            $query->where('ts.teacher_id', $filterTeacher);
        }
        if ($filterClass > 0) {
            $query->where('ts.class_id', $filterClass);
        }

        $schedules = $query
            ->orderBy('d.day_order')
            ->orderBy('sh.time_in')
            ->orderBy('ts.schedule_id')
            ->get()
            ->map(function ($row) {
                $schedule = (array) $row;
                [$timeIn, $timeOut] = $this->resolveShiftTimesForDay(
                    (string) ($schedule['shift_name'] ?? ''),
                    (string) ($schedule['time_in'] ?? ''),
                    (string) ($schedule['time_out'] ?? ''),
                    (int) ($schedule['day_id'] ?? 0)
                );
                $schedule['time_in'] = $timeIn;
                $schedule['time_out'] = $timeOut;

                return $schedule;
            })
            ->all();

        return response()->view('dashboard.print.admin-jadwal', [
            'schedules' => $schedules,
            'dayLabel' => $this->lookupLabel('day', 'day_id', 'day_name', $filterDay, 'Semua Hari'),
            'shiftLabel' => $this->lookupLabel('shift', 'shift_id', 'shift_name', $filterShift, 'Semua Shift'),
            'teacherLabel' => $this->lookupLabel('teacher', 'id', 'teacher_name', $filterTeacher, 'Semua Guru'),
            'classLabel' => $this->lookupLabel('class', 'class_id', 'class_name', $filterClass, 'Semua Kelas'),
            ...$this->buildPrintMeta(
                trim((string) (session('fullname') ?: session('username') ?: 'Administrator')),
                'jadwal_admin_',
                $this->resolveAdminPrintRole((int) session('level', 0))
            ),
            'orientation' => $this->resolveOrientation((string) $request->query('orientation', 'landscape')),
            'downloadPdf' => (string) $request->query('download', '') === 'pdf',
            'autoprint' => (string) $request->query('autoprint', '') === '1',
        ]);
    }

    public function guru(Request $request): Response|RedirectResponse
    {
        if (!$this->isGuruSession()) {
            return redirect($this->appPath('login.php'));
        }

        $teacherId = (int) session('teacher_id', 0);
        $teacher = DB::table('teacher')
            ->select('teacher_name', 'teacher_code', 'subject')
            ->where('id', $teacherId)
            ->first();

        if (!$teacher) {
            return response('Data guru tidak ditemukan.', 404);
        }

        $filterDay = $this->inputInt($request, 'day_id');
        $filterClass = $this->inputInt($request, 'class_id');

        $query = DB::table('teacher_schedule as ts')
            ->join('day as d', 'ts.day_id', '=', 'd.day_id')
            ->join('class as c', 'ts.class_id', '=', 'c.class_id')
            ->join('shift as sh', 'ts.shift_id', '=', 'sh.shift_id')
            ->where('ts.teacher_id', $teacherId)
            ->select(
                'ts.schedule_id',
                'ts.day_id',
                'd.day_name',
                'd.day_order',
                'c.class_name',
                'ts.subject',
                'sh.shift_name',
                'sh.time_in',
                'sh.time_out'
            );

        if ($filterDay > 0) {
            $query->where('ts.day_id', $filterDay);
        }
        if ($filterClass > 0) {
            $query->where('ts.class_id', $filterClass);
        }

        $schedules = $query
            ->orderBy('d.day_order')
            ->orderBy('sh.time_in')
            ->orderBy('c.class_name')
            ->get()
            ->map(function ($row) {
                $schedule = (array) $row;
                [$timeIn, $timeOut] = $this->resolveShiftTimesForDay(
                    (string) ($schedule['shift_name'] ?? ''),
                    (string) ($schedule['time_in'] ?? ''),
                    (string) ($schedule['time_out'] ?? ''),
                    (int) ($schedule['day_id'] ?? 0)
                );
                $schedule['time_in'] = $timeIn;
                $schedule['time_out'] = $timeOut;

                return $schedule;
            })
            ->all();

        $printedBy = trim((string) ($teacher->teacher_name ?? 'Guru'));
        if (!empty($teacher->teacher_code)) {
            $printedBy .= ' (' . (string) $teacher->teacher_code . ')';
        }

        return response()->view('dashboard.print.guru-jadwal', [
            'teacher' => (array) $teacher,
            'schedules' => $schedules,
            'dayLabel' => $this->lookupLabel('day', 'day_id', 'day_name', $filterDay, 'Semua Hari'),
            'classLabel' => $this->lookupLabel('class', 'class_id', 'class_name', $filterClass, 'Semua Kelas'),
            ...$this->buildPrintMeta($printedBy, 'jadwal_guru_', 'Guru'),
            'orientation' => $this->resolveOrientation((string) $request->query('orientation', 'landscape')),
            'downloadPdf' => (string) $request->query('download', '') === 'pdf',
            'autoprint' => (string) $request->query('autoprint', '') === '1',
        ]);
    }

    public function siswa(Request $request): Response|RedirectResponse
    {
        if (!$this->isSiswaSession()) {
            return redirect($this->appPath('login.php'));
        }

        $studentId = (int) session('student_id', 0);
        if ($studentId <= 0) {
            return response('Akses ditolak.', 403);
        }

        $studentData = DB::table('student as s')
            ->leftJoin('class as c', 's.class_id', '=', 'c.class_id')
            ->leftJoin('jurusan as j', 's.jurusan_id', '=', 'j.jurusan_id')
            ->where('s.id', $studentId)
            ->select('s.*', 'c.class_name', 'j.name as jurusan_name')
            ->first();

        if (!$studentData || (int) ($studentData->class_id ?? 0) <= 0) {
            return response('Data siswa tidak ditemukan atau belum terdaftar di kelas.', 404);
        }

        $classId = (int) ($studentData->class_id ?? 0);
        $this->scheduleSyncService->ensureStudentSchedulesForStudent($studentId, $classId, 6);

        $tz = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTimeImmutable('now', $tz);
        $todayDate = $now->format('Y-m-d');

        [$cycleStart, $cycleEnd] = $this->resolveScheduleCycleWindow($now);
        $timeTolerance = max(0, (int) (DB::table('site')->value('time_tolerance') ?? 15));

        $rawSchedules = DB::select(
            "
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
            ",
            [
                $studentId,
                $cycleStart,
                $cycleEnd,
                $studentId,
                $cycleStart,
                $cycleEnd,
                $classId,
            ]
        );

        $schedules = [];
        foreach ($rawSchedules as $row) {
            $schedule = (array) $row;
            [$timeIn, $timeOut] = $this->resolveShiftTimesForDay(
                (string) ($schedule['shift_name'] ?? ''),
                (string) ($schedule['time_in'] ?? ''),
                (string) ($schedule['time_out'] ?? ''),
                (int) ($schedule['day_id'] ?? 0)
            );
            $schedule['time_in'] = $timeIn;
            $schedule['time_out'] = $timeOut;
            $schedule['attendance_count'] = (int) ($schedule['attendance_count'] ?? 0);
            $schedules[] = $schedule;
        }

        $dayOrderMap = [
            'Senin' => 1,
            'Selasa' => 2,
            'Rabu' => 3,
            'Kamis' => 4,
            'Jumat' => 5,
            'Sabtu' => 6,
            'Minggu' => 7,
        ];
        $todayIndonesian = $this->todayIndonesian($now);
        $countdownSeconds = 120;

        $groupedSchedule = [];
        $attendedCount = 0;
        $pendingCount = 0;
        $alpaCount = 0;
        $teacherCountSet = [];

        foreach ($schedules as $schedule) {
            $dayName = (string) ($schedule['day_name'] ?? '-');
            $status = $this->resolveStudentScheduleStatus(
                $schedule,
                $now,
                $todayDate,
                $timeTolerance,
                $countdownSeconds,
                $dayOrderMap
            );
            $schedule['resolved_status'] = $status;

            if (!isset($groupedSchedule[$dayName])) {
                $groupedSchedule[$dayName] = [];
            }
            $groupedSchedule[$dayName][] = $schedule;

            if ((int) ($schedule['attendance_count'] ?? 0) > 0) {
                $attendedCount++;
            } elseif (!empty($status['is_alpa'])) {
                $alpaCount++;
            } else {
                $pendingCount++;
            }

            $teacherName = trim((string) ($schedule['teacher_name'] ?? ''));
            if ($teacherName !== '') {
                $teacherCountSet[$teacherName] = true;
            }
        }

        uksort($groupedSchedule, function (string $a, string $b) use ($dayOrderMap): int {
            $indexA = $dayOrderMap[$a] ?? 99;
            $indexB = $dayOrderMap[$b] ?? 99;
            if ($indexA === $indexB) {
                return strcmp($a, $b);
            }

            return $indexA <=> $indexB;
        });

        $printedBy = trim((string) ($studentData->student_name ?? session('student_name', 'Siswa')));
        if (!empty($studentData->student_nisn)) {
            $printedBy .= ' (' . (string) $studentData->student_nisn . ')';
        }

        return response()->view('dashboard.print.siswa-jadwal', [
            'studentData' => (array) $studentData,
            'groupedSchedule' => $groupedSchedule,
            'todayIndonesian' => $todayIndonesian,
            'todayLabel' => $now->format('d F Y'),
            'attendedCount' => $attendedCount,
            'pendingCount' => $pendingCount,
            'alpaCount' => $alpaCount,
            'teacherCount' => count($teacherCountSet),
            ...$this->buildPrintMeta($printedBy, 'jadwal_siswa_', 'Siswa'),
            'orientation' => $this->resolveOrientation((string) $request->query('orientation', 'auto')),
            'downloadPdf' => (string) $request->query('download', '') === 'pdf',
            'autoprint' => (string) $request->query('autoprint', '') === '1',
        ]);
    }

    private function isAdminSession(): bool
    {
        return session('logged_in') === true
            && (string) session('role', '') === 'admin'
            && in_array((int) session('level', 0), [1, 2], true);
    }

    private function isGuruSession(): bool
    {
        return session('logged_in') === true
            && (string) session('role', '') === 'guru'
            && (int) session('teacher_id', 0) > 0;
    }

    private function isSiswaSession(): bool
    {
        return session('logged_in') === true
            && (string) session('role', '') === 'siswa'
            && (int) session('student_id', 0) > 0;
    }

    private function appPath(string $path): string
    {
        if (preg_match('~^https?://~i', $path) === 1) {
            return $path;
        }

        $path = ltrim($path, '/');
        $root = $this->resolveAppRootUrl();
        if ($path === '') {
            return $root . '/';
        }

        return $root . '/' . $path;
    }

    private function resolveAppPrefix(): string
    {
        $configPrefix = $this->normalizePathPrefix((string) parse_url((string) config('app.url'), PHP_URL_PATH));
        if ($configPrefix !== '') {
            return $configPrefix;
        }

        return $this->normalizePathPrefix((string) request()->getBasePath());
    }

    private function normalizePathPrefix(string $prefix): string
    {
        $prefix = trim($prefix, '/');
        if ($prefix === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $prefix), static fn (string $segment): bool => $segment !== ''));
        $segmentCount = count($segments);
        if ($segmentCount < 2) {
            return implode('/', $segments);
        }

        for ($size = intdiv($segmentCount, 2); $size >= 1; $size--) {
            if (($segmentCount % $size) !== 0 || $segmentCount < ($size * 2)) {
                continue;
            }

            $pattern = array_slice($segments, 0, $size);
            $allSame = true;
            for ($index = $size; $index < $segmentCount; $index += $size) {
                if (array_slice($segments, $index, $size) !== $pattern) {
                    $allSame = false;
                    break;
                }
            }

            if ($allSame) {
                return implode('/', $pattern);
            }
        }

        return implode('/', $segments);
    }

    private function resolveAppRootUrl(): string
    {
        $configuredUrl = trim((string) config('app.url'));
        if ($configuredUrl !== '') {
            $parsed = parse_url($configuredUrl);
            if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
                $port = isset($parsed['port']) ? ':' . (string) $parsed['port'] : '';
                $prefix = $this->normalizePathPrefix((string) ($parsed['path'] ?? ''));

                return $parsed['scheme'] . '://' . $parsed['host'] . $port . ($prefix !== '' ? '/' . $prefix : '');
            }
        }

        $hostUrl = rtrim((string) request()->getSchemeAndHttpHost(), '/');
        $prefix = $this->resolveAppPrefix();

        return $hostUrl . ($prefix !== '' ? '/' . $prefix : '');
    }

    private function inputInt(Request $request, string $key): int
    {
        $value = (string) $request->query($key, '');
        if (!ctype_digit($value)) {
            return 0;
        }

        return (int) $value;
    }

    /**
     * @return array{printedAt:string,pdfFilename:string,pdfOrientation:string,printedBy:string}
     */
    private function buildPrintMeta(string $printedBy, string $filenamePrefix, string $printedRole = ''): array
    {
        $tz = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTimeImmutable('now', $tz);
        $printedBy = trim($printedBy);
        if ($printedBy === '') {
            $printedBy = 'Administrator';
        }
        $printedRole = trim($printedRole);
        if ($printedRole === '') {
            $printedRole = 'Administrator';
        }

        return [
            'printedAt' => $now->format('d F Y H:i:s') . ' WIB',
            'pdfFilename' => $filenamePrefix . $now->format('Ymd_His') . '.pdf',
            'pdfOrientation' => 'landscape',
            'printedBy' => $printedBy,
            'printedRole' => $printedRole,
        ];
    }

    private function resolveAdminPrintRole(int $level): string
    {
        return match ($level) {
            2 => 'Operator',
            1 => 'Admin',
            default => 'Admin/Operator',
        };
    }

    private function resolveOrientation(string $orientation): string
    {
        $orientation = strtolower(trim($orientation));
        if (!in_array($orientation, ['auto', 'portrait', 'landscape'], true)) {
            return 'landscape';
        }

        return $orientation;
    }

    private function lookupLabel(
        string $table,
        string $idColumn,
        string $labelColumn,
        int $id,
        string $fallback
    ): string {
        if ($id <= 0) {
            return $fallback;
        }

        $row = DB::table($table)
            ->select($labelColumn . ' as label')
            ->where($idColumn, $id)
            ->first();

        $label = trim((string) ($row->label ?? ''));
        return $label !== '' ? $label : $fallback;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveShiftTimesForDay(
        string $shiftName,
        string $fallbackIn,
        string $fallbackOut,
        int $dayId
    ): array {
        if (!preg_match('/JP(\d+)-JP(\d+)/i', $shiftName, $matches)) {
            return [
                $fallbackIn !== '' ? $fallbackIn : '00:00:00',
                $fallbackOut !== '' ? $fallbackOut : '00:00:00',
            ];
        }

        $jpStart = (int) $matches[1];
        $jpEnd = (int) $matches[2];
        if ($jpStart < 1 || $jpEnd < $jpStart) {
            return [
                $fallbackIn !== '' ? $fallbackIn : '00:00:00',
                $fallbackOut !== '' ? $fallbackOut : '00:00:00',
            ];
        }

        $config = DB::table('day_schedule_config')
            ->select('school_start_time', 'activity1_minutes', 'activity2_minutes')
            ->where('day_id', $dayId)
            ->first();
        $baseStart = (string) ($config->school_start_time ?? '06:30:00');
        $preMinutes = max(0, (int) ($config->activity1_minutes ?? 0)) + max(0, (int) ($config->activity2_minutes ?? 0));
        $toleranceMinutes = max(0, (int) (DB::table('site')->value('time_tolerance') ?? 0));

        $base = \DateTimeImmutable::createFromFormat('H:i', $baseStart)
            ?: \DateTimeImmutable::createFromFormat('H:i:s', $baseStart);
        if (!$base instanceof \DateTimeImmutable) {
            return [
                $fallbackIn !== '' ? $fallbackIn : '00:00:00',
                $fallbackOut !== '' ? $fallbackOut : '00:00:00',
            ];
        }

        if ($preMinutes > 0) {
            $base = $base->modify('+' . $preMinutes . ' minutes');
        }

        $minutesBefore = 0;
        for ($jp = 1; $jp < $jpStart; $jp++) {
            $minutesBefore += ($jp === 5 || $jp === 9) ? 15 : 45;
        }

        $durationMinutes = 0;
        for ($jp = $jpStart; $jp <= $jpEnd; $jp++) {
            $durationMinutes += ($jp === 5 || $jp === 9) ? 15 : 45;
        }

        $timeIn = $minutesBefore > 0 ? $base->modify('+' . $minutesBefore . ' minutes') : $base;
        $timeOut = $timeIn->modify('+' . $durationMinutes . ' minutes');
        if ($toleranceMinutes > 0) {
            $timeOut = $timeOut->modify('+' . $toleranceMinutes . ' minutes');
        }

        return [$timeIn->format('H:i:s'), $timeOut->format('H:i:s')];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveScheduleCycleWindow(\DateTimeImmutable $now): array
    {
        $weekStart = $now->modify('monday this week')->setTime(0, 0, 0);
        $resetTime = $weekStart->modify('+5 days')->setTime(15, 0, 0);
        if ($now >= $resetTime) {
            $weekStart = $weekStart->modify('+7 days');
            $resetTime = $resetTime->modify('+7 days');
        }

        return [$weekStart->format('Y-m-d'), $resetTime->format('Y-m-d')];
    }

    private function todayIndonesian(\DateTimeImmutable $date): string
    {
        return match ($date->format('l')) {
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $schedule
     * @param array<string, int> $dayOrderMap
     * @return array<string, mixed>
     */
    private function resolveStudentScheduleStatus(
        array $schedule,
        \DateTimeImmutable $now,
        string $todayDate,
        int $timeTolerance,
        int $countdownSeconds,
        array $dayOrderMap
    ): array {
        $attendanceCount = (int) ($schedule['attendance_count'] ?? 0);
        $attendanceLate = (string) ($schedule['attendance_is_late'] ?? '') === 'Y';

        $result = [
            'status_class' => 'status-muted',
            'status_text' => 'MENUNGGU',
            'action_text' => '-',
            'is_alpa' => false,
        ];

        if ($attendanceCount > 0) {
            $result['status_class'] = $attendanceLate ? 'status-overdue' : 'status-success';
            $result['status_text'] = $attendanceLate ? 'OVERDUE' : 'SUCCESS';
            $result['action_text'] = 'Done';

            return $result;
        }

        $dayName = (string) ($schedule['day_name'] ?? '');
        $dayIndex = (int) ($schedule['day_id'] ?? 0);
        if ($dayIndex <= 0) {
            $dayIndex = $dayOrderMap[$dayName] ?? 0;
        }
        if ($dayIndex <= 0) {
            return $result;
        }

        $weekStart = $now->modify('monday this week')->setTime(0, 0, 0);
        $resetTime = $weekStart->modify('+5 days')->setTime(15, 0, 0);
        if ($now >= $resetTime) {
            $weekStart = $weekStart->modify('+7 days');
        }
        $scheduleDateObj = $weekStart->modify('+' . ($dayIndex - 1) . ' days');
        $scheduleDate = $scheduleDateObj->format('Y-m-d');

        if ($scheduleDate > $todayDate) {
            return $result;
        }

        [$startDt, $endDt, $baseEndDt] = $this->buildScheduleWindow(
            $scheduleDate,
            (string) ($schedule['time_in'] ?? '00:00:00'),
            (string) ($schedule['time_out'] ?? '00:00:00'),
            new \DateTimeZone('Asia/Jakarta'),
            $timeTolerance
        );

        $nowTs = $now->getTimestamp();
        $startTs = $startDt->getTimestamp();
        $endTs = $endDt->getTimestamp();
        $baseEndTs = $baseEndDt->getTimestamp();
        $countdownStart = $startTs - $countdownSeconds;

        if ($nowTs < $countdownStart) {
            return $result;
        }

        if ($nowTs >= $countdownStart && $nowTs < $startTs) {
            $result['status_class'] = 'status-countdown';
            $result['status_text'] = 'COUNTDOWN';

            return $result;
        }

        if ($nowTs >= $startTs && $nowTs <= $baseEndTs) {
            $result['status_class'] = 'status-active';
            $result['status_text'] = 'ACTIVE';
            $result['action_text'] = 'Absen';

            return $result;
        }

        if ($nowTs > $baseEndTs && $nowTs <= $endTs) {
            $result['status_class'] = 'status-overdue';
            $result['status_text'] = 'OVERDUE';
            $result['action_text'] = 'Absen';

            return $result;
        }

        $result['status_class'] = 'status-closed';
        $result['status_text'] = 'CLOSED';
        $result['is_alpa'] = true;

        return $result;
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
}
