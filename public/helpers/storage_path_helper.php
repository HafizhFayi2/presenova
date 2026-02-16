<?php

if (!function_exists('storage_slug')) {
    function storage_slug($text, $default = 'item') {
        $text = trim((string) $text);
        if ($text === '') {
            return $default;
        }
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return $text !== '' ? $text : $default;
    }
}

if (!function_exists('storage_class_folder')) {
    function storage_class_folder($className) {
        return storage_slug($className, 'kelas');
    }
}

if (!function_exists('storage_student_folder')) {
    function storage_student_folder($studentName) {
        return storage_slug($studentName, 'siswa');
    }
}

if (!function_exists('storage_indonesian_day_name')) {
    function storage_indonesian_day_name($date) {
        $date = trim((string) $date);
        if ($date === '') {
            return 'hari';
        }
        try {
            $dt = new DateTime($date);
        } catch (Exception $e) {
            return 'hari';
        }
        $names = ['', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
        $idx = (int) $dt->format('N');
        return $names[$idx] ?? 'hari';
    }
}

if (!function_exists('storage_attendance_datetime_folder')) {
    function storage_attendance_datetime_folder($date, $time) {
        $datePart = '';
        $timePart = '';
        if ($date) {
            $datePart = date('Y-m-d', strtotime((string) $date));
        }
        if ($time) {
            $digits = preg_replace('/\D/', '', (string) $time);
            if (strlen($digits) >= 6) {
                $timePart = substr($digits, 0, 6);
            } elseif (strlen($digits) > 0) {
                $timePart = str_pad($digits, 6, '0', STR_PAD_RIGHT);
            }
        }
        if ($datePart === '') {
            $datePart = date('Y-m-d');
        }
        if ($timePart === '') {
            $timePart = date('His');
        }
        return $datePart . '_' . $timePart;
    }
}

if (!function_exists('storage_attendance_basename')) {
    function storage_attendance_basename($studentName, $nisn, $date) {
        $studentSlug = storage_slug($studentName, 'siswa');
        $nisn = trim((string) $nisn);
        $dayName = storage_indonesian_day_name($date);
        $parts = [$studentSlug];
        if ($nisn !== '') {
            $parts[] = $nisn;
        }
        $parts[] = $dayName;
        return implode('-', $parts);
    }
}

if (!function_exists('storage_face_reference_filename')) {
    function storage_face_reference_filename($nisn, $studentName, $extension = 'jpg') {
        $studentSlug = storage_slug($studentName, 'siswa');
        $nisn = trim((string) $nisn);
        $ext = strtolower(trim((string) $extension));
        if ($ext === '') {
            $ext = 'jpg';
        }
        $base = $nisn !== '' ? ($nisn . '-' . $studentSlug) : $studentSlug;
        return $base . '.' . $ext;
    }
}
