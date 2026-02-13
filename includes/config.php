<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'presenova');

// Konfigurasi Sistem
define('SITE_NAME', 'Absensi Online SMK');
define('SITE_URL', 'http://localhost/absensi-smk/');

// Konfigurasi Absensi
define('ATTENDANCE_RADIUS', 100); // Radius dalam meter
define('FACE_MATCH_THRESHOLD', 89); // Threshold kecocokan wajah (89%)
define('FACE_DESCRIPTOR_DISTANCE_THRESHOLD', 0.55); // Batas maksimal jarak descriptor (semakin kecil semakin mirip)
define('TIME_TOLERANCE_MINUTES', 15); // Toleransi keterlambatan
define('SCHOOL_LATITUDE', -6.3519595510017455);
define('SCHOOL_LONGITUDE', 107.10615744323621);

// Python runtime (untuk face matching server-side)
define('PYTHON_BIN', 'python');

// Google Drive API (untuk upload foto ke cloud)
define('GOOGLE_DRIVE_FOLDER_ID', 'YOUR_GOOGLE_DRIVE_FOLDER_ID');

// Google Maps API (untuk peta di Admin)
define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY');

// JWT Secret
define('JWT_SECRET', 'absensi_smk_secret_key_2024');
define('JWT_EXPIRE', 30); // 30 hari

// Password Hash Salt (HARUS SAMA dengan yang ada di database!)
define('PASSWORD_SALT', '$%DSuTyr47542@#&*!=QxR094{a911}+');

// Debug mode
define('DEBUG', true);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
