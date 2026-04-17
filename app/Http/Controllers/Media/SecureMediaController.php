<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Services\FaceMatcherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SecureMediaController extends Controller
{
    public function face(Request $request): Response
    {
        if (!$this->isLoggedIn()) {
            abort(403);
        }

        $encodedRef = trim((string) $request->query('ref', ''));
        $decodedRef = presenova_media_ref_decode($encodedRef);
        $normalizedRef = normalize_face_reference_path($decodedRef);
        if ($normalizedRef === '') {
            abort(404);
        }

        $filePath = resolve_face_reference_file_path($normalizedRef);
        if ($filePath === null || !is_file($filePath)) {
            abort(404);
        }

        if (!$this->canAccessFaceReference($filePath)) {
            abort(403);
        }

        return $this->servePrivateImage($filePath);
    }

    public function attendance(Request $request): Response
    {
        if (!$this->isLoggedIn()) {
            abort(403);
        }

        $encodedRef = trim((string) $request->query('ref', ''));
        $decodedRef = presenova_media_ref_decode($encodedRef);
        $normalizedRef = normalize_attendance_reference_path($decodedRef);
        if ($normalizedRef === '') {
            abort(404);
        }

        $filePath = resolve_attendance_file_path($normalizedRef, null);
        if ($filePath === null || !is_file($filePath)) {
            abort(404);
        }

        if (!$this->canAccessAttendancePhoto($filePath)) {
            abort(403);
        }

        return $this->servePrivateImage($filePath);
    }

    private function isLoggedIn(): bool
    {
        $role = $this->currentRole();
        $hasIdentity = (int) $this->sessionValue('student_id', 0) > 0
            || (int) $this->sessionValue('teacher_id', 0) > 0
            || (int) $this->sessionValue('user_id', 0) > 0;

        if ($role === '' || !$hasIdentity) {
            return false;
        }

        if ($this->toBool($this->sessionValue('logged_in', false))) {
            return true;
        }

        // Backward compatibility: some legacy requests only persist role+id in native session.
        return true;
    }

    private function currentRole(): string
    {
        return strtolower(trim((string) $this->sessionValue('role', '')));
    }

    private function canAccessFaceReference(string $targetPath): bool
    {
        $role = $this->currentRole();
        if (in_array($role, ['admin', 'guru', 'teacher'], true)) {
            return true;
        }

        if (!in_array($role, ['siswa', 'student'], true)) {
            return false;
        }

        $studentId = (int) $this->sessionValue('student_id', 0);
        if ($studentId <= 0) {
            return false;
        }

        $student = DB::table('student')
            ->where('id', $studentId)
            ->select('photo_reference', 'photo', 'student_nisn')
            ->first();
        if (!$student) {
            return false;
        }

        $candidatePaths = [];
        $candidateReferences = array_filter([
            trim((string) ($student->photo_reference ?? '')),
            trim((string) ($student->photo ?? '')),
        ], static fn (string $value): bool => $value !== '');

        foreach ($candidateReferences as $candidateReference) {
            $studentPath = resolve_face_reference_file_path($candidateReference);
            if ($studentPath === null || !is_file($studentPath)) {
                continue;
            }
            $candidatePaths[] = $studentPath;
        }

        $studentNisn = trim((string) ($student->student_nisn ?? ''));
        if ($studentNisn !== '') {
            try {
                /** @var FaceMatcherService $faceMatcher */
                $faceMatcher = app(FaceMatcherService::class);
                $fallbackCandidates = $faceMatcher->getReferenceCandidates(
                    $studentNisn,
                    trim((string) ($student->photo_reference ?? ''))
                );
                foreach ($fallbackCandidates as $fallbackPath) {
                    if (is_string($fallbackPath) && $fallbackPath !== '' && is_file($fallbackPath)) {
                        $candidatePaths[] = $fallbackPath;
                    }
                }
            } catch (\Throwable) {
                // Ignore fallback lookup failure and keep strict DB candidates.
            }
        }

        $candidatePaths = array_values(array_unique($candidatePaths));
        foreach ($candidatePaths as $candidatePath) {
            if ($this->sameFile($targetPath, $candidatePath)) {
                return true;
            }
        }

        return false;
    }

    private function canAccessAttendancePhoto(string $targetPath): bool
    {
        $role = $this->currentRole();
        if ($role === 'admin') {
            return true;
        }

        $record = $this->findAttendanceRecordByFile($targetPath);
        if ($record === null) {
            return false;
        }

        if (in_array($role, ['siswa', 'student'], true)) {
            $studentId = (int) $this->sessionValue('student_id', 0);
            return $studentId > 0 && $studentId === (int) ($record['student_id'] ?? 0);
        }

        if (in_array($role, ['guru', 'teacher'], true)) {
            $teacherId = (int) $this->sessionValue('teacher_id', 0);
            $studentScheduleId = (int) ($record['student_schedule_id'] ?? 0);
            if ($teacherId <= 0 || $studentScheduleId <= 0) {
                return false;
            }

            return DB::table('student_schedule as ss')
                ->join('teacher_schedule as ts', 'ss.teacher_schedule_id', '=', 'ts.schedule_id')
                ->where('ss.student_schedule_id', $studentScheduleId)
                ->where('ts.teacher_id', $teacherId)
                ->exists();
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAttendanceRecordByFile(string $targetPath): ?array
    {
        $fileName = basename($targetPath);
        if ($fileName === '') {
            return null;
        }

        $rows = DB::table('presence')
            ->select('presence_id', 'student_id', 'student_schedule_id', 'presence_date', 'picture_in')
            ->where(function ($query) use ($fileName): void {
                $query->where('picture_in', $fileName)
                    ->orWhere('picture_in', 'like', '%/' . $fileName);
            })
            ->orderByDesc('presence_id')
            ->limit(300)
            ->get();

        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $presenceDate = (string) ($rowArray['presence_date'] ?? '');
            $resolved = resolve_attendance_file_path((string) ($rowArray['picture_in'] ?? ''), $presenceDate);
            if ($resolved === null || !is_file($resolved)) {
                continue;
            }
            if ($this->sameFile($targetPath, $resolved)) {
                return $rowArray;
            }
        }

        return null;
    }

    private function sameFile(string $pathA, string $pathB): bool
    {
        $realA = realpath($pathA);
        $realB = realpath($pathB);
        if ($realA === false || $realB === false) {
            return false;
        }

        return strcasecmp($realA, $realB) === 0;
    }

    private function servePrivateImage(string $filePath): Response
    {
        $mimeType = (string) (@mime_content_type($filePath) ?: 'application/octet-stream');
        if (!str_starts_with(strtolower($mimeType), 'image/')) {
            abort(404);
        }

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Robots-Tag' => 'noindex, noarchive, nosnippet, noimageindex',
            'Referrer-Policy' => 'same-origin',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="private-media.jpg"',
        ]);
    }

    private function sessionValue(string $key, mixed $default = null): mixed
    {
        try {
            if (session()->has($key)) {
                return session($key);
            }
        } catch (\Throwable) {
            // Ignore read errors and fallback to native session.
        }

        if (isset($_SESSION) && is_array($_SESSION) && array_key_exists($key, $_SESSION)) {
            return $_SESSION[$key];
        }

        return $default;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
