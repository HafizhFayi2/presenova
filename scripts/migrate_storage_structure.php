<?php
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$basePath = realpath(__DIR__ . '/..');
if ($basePath === false) {
    fwrite(STDERR, "Gagal menemukan root project.\n");
    exit(1);
}

require $basePath . '/vendor/autoload.php';
$app = require $basePath . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once $basePath . '/app/Support/runtime_helpers.php';

$db = new Database();
$pdo = $db->getConnection();

$publicRoot = realpath(__DIR__ . '/../public');
if ($publicRoot === false) {
    fwrite(STDERR, "Gagal menemukan folder public.\n");
    exit(1);
}
$facesRoot = $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'faces';
$attendanceRoot = $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'attendance';
$tempDir = $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp';
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0777, true);
}
$logFile = $tempDir . DIRECTORY_SEPARATOR . 'migration_storage.log';

function logLine($message) {
    global $logFile;
    $line = '[' . date('c') . '] ' . $message . PHP_EOL;
    echo $line;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function ensureDir($path) {
    if (is_dir($path)) {
        return true;
    }
    return @mkdir($path, 0777, true) || is_dir($path);
}

function resolveFaceFilePath($publicRoot, $facesRoot, $photoReference) {
    $photoReference = trim((string) $photoReference);
    if ($photoReference === '') {
        return null;
    }
    $normalized = str_replace('\\', '/', $photoReference);
    $normalized = ltrim($normalized, '/');
    if (stripos($normalized, 'uploads/faces/') === 0) {
        $candidate = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    $candidate = $facesRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (is_file($candidate)) {
        return $candidate;
    }
    $candidate = $publicRoot . DIRECTORY_SEPARATOR . $normalized;
    if (is_file($candidate)) {
        return $candidate;
    }
    return null;
}

function resolveAttendanceFilePath($publicRoot, $attendanceRoot, $presenceDate, $filename) {
    $filename = trim((string) $filename);
    if ($filename === '') {
        return null;
    }
    $normalized = str_replace('\\', '/', $filename);
    $normalized = ltrim($normalized, '/');

    if (stripos($normalized, 'uploads/attendance/') === 0 || stripos($normalized, 'uploads/attandance/') === 0) {
        $candidate = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    if (strpos($normalized, '/') !== false) {
        $candidate = $attendanceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    if ($presenceDate) {
        $candidate = $attendanceRoot . DIRECTORY_SEPARATOR . $presenceDate . DIRECTORY_SEPARATOR . $normalized;
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $candidate = $attendanceRoot . DIRECTORY_SEPARATOR . $normalized;
    if (is_file($candidate)) {
        return $candidate;
    }

    return null;
}

function safeMoveFile($sourcePath, $targetPath) {
    $targetDir = dirname($targetPath);
    if (!ensureDir($targetDir)) {
        return null;
    }
    if (realpath($sourcePath) === realpath($targetPath)) {
        return $targetPath;
    }
    $pathInfo = pathinfo($targetPath);
    $base = $pathInfo['filename'] ?? 'file';
    $ext = isset($pathInfo['extension']) && $pathInfo['extension'] !== '' ? '.' . $pathInfo['extension'] : '';
    $candidate = $targetPath;
    $counter = 1;
    while (file_exists($candidate)) {
        $candidate = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $base . '-' . $counter . $ext;
        $counter++;
    }
    if (@rename($sourcePath, $candidate)) {
        return $candidate;
    }
    if (@copy($sourcePath, $candidate)) {
        @unlink($sourcePath);
        return $candidate;
    }
    return null;
}

logLine('Mulai migrasi struktur uploads.');

// Migrate face references + pose dataset
$students = $pdo->query(
    "SELECT s.id, s.student_nisn, s.student_name, s.photo_reference, s.photo, c.class_name
     FROM student s
     LEFT JOIN class c ON s.class_id = c.class_id"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as $student) {
    $studentId = (int) ($student['id'] ?? 0);
    $nisn = trim((string) ($student['student_nisn'] ?? ''));
    $studentName = $student['student_name'] ?? '';
    $classFolder = storage_class_folder($student['class_name'] ?? 'kelas');
    $studentFolder = storage_student_folder($studentName ?: ('siswa_' . $nisn));
    $targetBaseDir = $facesRoot . DIRECTORY_SEPARATOR . $classFolder . DIRECTORY_SEPARATOR . $studentFolder;
    ensureDir($targetBaseDir);

    if (!empty($student['photo_reference'])) {
        $source = resolveFaceFilePath($publicRoot, $facesRoot, $student['photo_reference']);
        if ($source) {
            $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
            $targetFilename = storage_face_reference_filename($nisn ?: $studentId, $studentName, $ext);
            $targetPath = $targetBaseDir . DIRECTORY_SEPARATOR . $targetFilename;
            $targetRelative = $classFolder . '/' . $studentFolder . '/' . $targetFilename;
            if (realpath($source) !== realpath($targetPath)) {
                $moved = safeMoveFile($source, $targetPath);
                if ($moved) {
                    logLine("Pindah face ref siswa {$studentId} -> {$targetRelative}");
                } else {
                    logLine("Gagal pindah face ref siswa {$studentId}");
                    continue;
                }
            }
            if ($student['photo_reference'] !== $targetRelative) {
                $stmt = $pdo->prepare("UPDATE student SET photo_reference = ? WHERE id = ?");
                $stmt->execute([$targetRelative, $studentId]);
            }
        } else {
            logLine("Face ref siswa {$studentId} tidak ditemukan ({$student['photo_reference']}).");
        }
    }

    if ($nisn !== '') {
        $oldPoseDir = $facesRoot . DIRECTORY_SEPARATOR . 'pose' . DIRECTORY_SEPARATOR . $nisn . DIRECTORY_SEPARATOR . 'latest';
        $newPoseDir = $targetBaseDir . DIRECTORY_SEPARATOR . 'pose';
        if (is_dir($oldPoseDir)) {
            ensureDir($newPoseDir);
            $oldFiles = glob($oldPoseDir . DIRECTORY_SEPARATOR . '*') ?: [];
            foreach ($oldFiles as $oldFile) {
                if (!is_file($oldFile)) continue;
                $targetFile = $newPoseDir . DIRECTORY_SEPARATOR . basename($oldFile);
                $moved = safeMoveFile($oldFile, $targetFile);
                if ($moved) {
                    logLine("Pindah pose {$oldFile} -> {$targetFile}");
                }
            }
            @rmdir($oldPoseDir);
        }
    }
}

// Migrate attendance photos
$presenceRows = $pdo->query(
    "SELECT p.presence_id, p.presence_date, p.time_in, p.picture_in, p.picture_out,
            s.student_name, s.student_nisn, c.class_name
     FROM presence p
     JOIN student s ON p.student_id = s.id
     LEFT JOIN class c ON s.class_id = c.class_id"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($presenceRows as $row) {
    $presenceId = (int) ($row['presence_id'] ?? 0);
    $presenceDate = $row['presence_date'] ?? '';
    $timeIn = $row['time_in'] ?? '';
    $classFolder = storage_class_folder($row['class_name'] ?? 'kelas');
    $baseName = storage_attendance_basename($row['student_name'] ?? 'siswa', $row['student_nisn'] ?? $presenceId, $presenceDate);
    $dateTimeFolder = storage_attendance_datetime_folder($presenceDate, $timeIn);

    foreach (['picture_in', 'picture_out'] as $field) {
        $rawValue = $row[$field] ?? '';
        if (!$rawValue) continue;

        $normalized = ltrim(str_replace('\\', '/', $rawValue), '/');
        if (stripos($normalized, 'uploads/attendance/') === 0) {
            $normalized = substr($normalized, strlen('uploads/attendance/'));
        }
        if (stripos($normalized, 'uploads/attandance/') === 0) {
            $normalized = substr($normalized, strlen('uploads/attandance/'));
        }

        $existingPath = resolveAttendanceFilePath($publicRoot, $attendanceRoot, $presenceDate, $rawValue);
        if (!$existingPath) {
            logLine("Attendance {$presenceId} file {$rawValue} tidak ditemukan.");
            continue;
        }

        $ext = strtolower(pathinfo($existingPath, PATHINFO_EXTENSION) ?: 'jpg');
        $suffix = ($field === 'picture_out') ? '-out' : '';
        $targetFilename = $baseName . $suffix . '.' . $ext;
        $targetRelative = $dateTimeFolder . '/' . $classFolder . '/' . $targetFilename;
        $targetPath = $attendanceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);

        if (realpath($existingPath) !== realpath($targetPath)) {
            $moved = safeMoveFile($existingPath, $targetPath);
            if (!$moved) {
                logLine("Gagal pindah attendance {$presenceId} ({$field}).");
                continue;
            }
            logLine("Pindah attendance {$presenceId} ({$field}) -> {$targetRelative}");
        }

        if ($rawValue !== $targetRelative) {
            $stmt = $pdo->prepare("UPDATE presence SET {$field} = ? WHERE presence_id = ?");
            $stmt->execute([$targetRelative, $presenceId]);
        }
    }
}

logLine('Selesai migrasi struktur uploads.');
