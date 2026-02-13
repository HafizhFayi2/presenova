<?php
// Fungsi-fungsi helper

/**
 * Menghitung jarak antara dua koordinat (latitude, longitude) menggunakan formula Haversine.
 * @param float $lat1 Latitude titik pertama
 * @param float $lon1 Longitude titik pertama
 * @param float $lat2 Latitude titik kedua
 * @param float $lon2 Longitude titik kedua
 * @return float Jarak dalam meter
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Radius bumi dalam meter

    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    
    return $angle * $earthRadius;
}

/**
 * Mengompres gambar
 * @param string $source Path sumber gambar
 * @param string $destination Path tujuan
 * @param int $quality Kualitas (0-100)
 * @return bool Berhasil atau tidak
 */
function compressImage($source, $destination, $quality) {
    // Cek apakah GD library tersedia
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
        error_log("GD library tidak tersedia, skip kompresi gambar");
        return false;
    }
    
    $info = getimagesize($source);

    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
        imagedestroy($image);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        
        // Untuk PNG, quality adalah compression level (0-9)
        $quality_png = 9 - round($quality / 10);
        if ($quality_png < 0) $quality_png = 0;
        if ($quality_png > 9) $quality_png = 9;
        
        imagepng($image, $destination, $quality_png);
        imagedestroy($image);
    } elseif ($info['mime'] == 'image/gif') {
        $image = imagecreatefromgif($source);
        imagegif($image, $destination);
        imagedestroy($image);
    } else {
        error_log("Format gambar tidak didukung: " . $info['mime']);
        return false;
    }

    return true;
}

/**
 * Mengirim notifikasi push (simulasi)
 * @param string $title Judul notifikasi
 * @param string $body Isi notifikasi
 * @param array $tokens Array token perangkat
 * @return bool Berhasil atau tidak
 */
function sendPushNotification($title, $body, $tokens) {
    // Implementasi sebenarnya menggunakan FCM (Firebase Cloud Messaging)
    // Ini hanya simulasi
    error_log("Push Notification: $title - $body to " . count($tokens) . " devices");
    return true;
}

/**
 * Mendapatkan client IP address
 * @return string IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Log aktivitas pengguna
 * @param int $user_id ID pengguna
 * @param string $user_type Tipe pengguna (student, teacher, admin)
 * @param string $action Aksi yang dilakukan
 * @param string $details Detail aksi
 * @return bool Berhasil atau tidak
 */
function logActivity($user_id, $user_type, $action, $details = '') {
    global $db;
    
    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $sql = "INSERT INTO activity_logs (user_id, user_type, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->query($sql, [$user_id, $user_type, $action, $details, $ip_address, $user_agent]);
    
    return $stmt !== false;
}

/**
 * Validasi token JWT (simplified)
 * @param string $token Token JWT
 * @return array|false Data payload atau false jika invalid
 */
function validateJWT($token) {
    // Implementasi JWT validation yang sesuai dengan sistem Anda
    // Ini hanya contoh sederhana
    $parts = explode('.', $token);
    if (count($parts) != 3) {
        return false;
    }
    
    $payload = json_decode(base64_decode($parts[1]), true);
    if (!$payload) {
        return false;
    }
    
    // Cek expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

/**
 * Membuat respons JSON standar
 * @param bool $success Status sukses atau tidak
 * @param string $message Pesan untuk client
 * @param mixed $data Data tambahan
 * @return string JSON string
 */
function jsonResponse($success, $message = '', $data = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

/**
 * Mendapatkan setting dari tabel site
 * @param string $key Key setting
 * @return mixed Nilai setting
 */
function getSetting($key) {
    global $db;
    
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $db->query("SELECT * FROM site LIMIT 1");
        $settings = $stmt->fetch();
    }
    
    return isset($settings[$key]) ? $settings[$key] : null;
}
?>