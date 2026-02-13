<?php
// api/check_location.php
require_once '../includes/config.php';
require_once '../includes/database.php';

if (!function_exists('getDefaultSchoolLocation')) {
    function getDefaultSchoolLocation($db) {
        $siteStmt = $db->query("SELECT default_location_id FROM site LIMIT 1");
        $site = $siteStmt ? $siteStmt->fetch(PDO::FETCH_ASSOC) : null;
        $locationId = !empty($site['default_location_id']) ? (int) $site['default_location_id'] : 0;

        $school = null;
        if ($locationId > 0) {
            $locStmt = $db->query("SELECT * FROM school_location WHERE location_id = ? LIMIT 1", [$locationId]);
            $school = $locStmt ? $locStmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($school && $school['is_active'] !== 'Y') {
                $school = null;
            }
        }

        if (!$school) {
            $stmt = $db->query("SELECT * FROM school_location WHERE is_active = 'Y' ORDER BY location_id DESC LIMIT 1");
            $school = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        }

        return $school;
    }
}

if (!function_exists('calculateDistance')) {
    /**
     * Hitung jarak dalam meter menggunakan Haversine formula
     */
    function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // Radius bumi dalam meter

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

// Jalankan sebagai endpoint hanya jika file diakses langsung
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);
    $latitude = $data['latitude'] ?? $_POST['latitude'] ?? null;
    $longitude = $data['longitude'] ?? $_POST['longitude'] ?? null;
    $accuracy = $data['accuracy'] ?? $_POST['accuracy'] ?? null;

    if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
        echo json_encode(['success' => false, 'message' => 'Koordinat tidak ditemukan']);
        exit;
    }

    try {
        $school = getDefaultSchoolLocation($db);
        if (!$school) {
            echo json_encode(['success' => false, 'message' => 'Lokasi sekolah belum dikonfigurasi']);
            exit;
        }

        $distance = calculateDistance($latitude, $longitude, $school['latitude'], $school['longitude']);
        $accuracyVal = is_numeric($accuracy) ? (float) $accuracy : null;
        $radius = (float) $school['radius'];
        $accuracyBuffer = 0;
        if ($accuracyVal !== null && $accuracyVal > 0) {
            $accuracyBuffer = min($accuracyVal, max(50, $radius * 1.5));
        }
        $withinRadius = $distance <= ($radius + $accuracyBuffer);

        echo json_encode([
            'success' => true,
            'data' => [
                'distance' => round($distance, 2),
                'within_radius' => $withinRadius,
                'radius_limit' => $school['radius'],
                'accuracy' => $accuracyVal !== null ? round($accuracyVal, 2) : null,
                'accuracy_buffer' => round($accuracyBuffer, 2),
                'school_location' => $school
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
