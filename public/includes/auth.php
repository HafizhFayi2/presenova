<?php
require_once 'config.php';
require_once 'database.php';
require_once dirname(__DIR__) . '/helpers/storage_path_helper.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }

    private function getFaceReferencePath($photo_reference) {
        if (empty($photo_reference)) {
            return null;
        }
        $baseDir = rtrim(dirname(__DIR__), '/\\');
        $photo_reference = ltrim((string) $photo_reference, '/\\');
        if (stripos($photo_reference, 'uploads/faces/') === 0) {
            $photo_reference = substr($photo_reference, strlen('uploads/faces/'));
        }
        $path = $baseDir . '/uploads/faces/' . $photo_reference;
        return file_exists($path) ? $path : null;
    }

    private function ensureStudentFaceReference($student) {
        if (!$student || empty($student['id'])) {
            return false;
        }

        $photoReference = $student['photo_reference'] ?? '';
        if (empty($photoReference)) {
            return false;
        }

        $path = $this->getFaceReferencePath($photoReference);
        if ($path) {
            return true;
        }

        $this->db->query(
            "UPDATE student SET photo_reference = NULL, face_embedding = NULL WHERE id = ?",
            [$student['id']]
        );

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['face_reference_missing'] = true;
        }

        return false;
    }

    private function hasPoseCaptureDataset($studentNisn) {
        $studentNisn = trim((string) $studentNisn);
        if ($studentNisn === '') {
            return false;
        }

        $studentStmt = $this->db->query(
            "SELECT s.student_name, c.class_name
             FROM student s
             LEFT JOIN class c ON s.class_id = c.class_id
             WHERE s.student_nisn = ? LIMIT 1",
            [$studentNisn]
        );
        $student = $studentStmt ? $studentStmt->fetch() : null;
        $classFolder = storage_class_folder($student['class_name'] ?? 'kelas');
        $studentFolder = storage_student_folder($student['student_name'] ?? ('siswa_' . $studentNisn));

        $baseDir = rtrim(dirname(__DIR__), '/\\');
        $poseDir = $baseDir . '/uploads/faces/' . $classFolder . '/' . $studentFolder . '/pose';
        if (!is_dir($poseDir)) {
            return false;
        }

        $right = glob($poseDir . '/right_*.jpg') ?: [];
        $left = glob($poseDir . '/left_*.jpg') ?: [];
        $front = glob($poseDir . '/front_*.jpg') ?: [];

        return count($right) >= 5 && count($left) >= 5 && count($front) >= 1;
    }
    
    // Login admin
    public function loginAdmin($username, $password) {
        $sql = "SELECT * FROM user WHERE username = ? AND level = 1";
        $stmt = $this->db->query($sql, [$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            $hashed_password = hash('sha256', $password . PASSWORD_SALT);
            
            if ($hashed_password === $user['password']) {
                // Update last login
                $this->updateLastLogin($user['user_id']);
                
                // Create session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['level'] = $user['level'];
                $_SESSION['role'] = 'admin'; // Tambahkan role
                $_SESSION['logged_in'] = true;
                
                // Log activity
                $this->logActivity($user['user_id'], 'admin', 'login', 'Admin login successful');
                
                // Create JWT token
                $token = $this->createJWT($user['user_id'], 'admin');
                setcookie('attendance_token', $token, time() + (86400 * JWT_EXPIRE), "/");
                
                return true;
            } else {
                // Log failed attempt
                $this->logActivity(0, 'system', 'failed_login', "Failed login attempt for admin: $username");
            }
        }
        return false;
    }
    
    // Login siswa - DIPERBAIKI
    public function loginSiswa($nisn, $password) {
        $sql = "SELECT * FROM student WHERE student_nisn = ?";
        $stmt = $this->db->query($sql, [$nisn]);
        $siswa = $stmt->fetch();
        
        if ($siswa) {
            // Gunakan PASSWORD_SALT yang sama dengan config.php
            $hashed_password = hash('sha256', $password . PASSWORD_SALT);
            
            if ($hashed_password === $siswa['student_password']) {
                // Check if student has uploaded face photo
                $has_face = $this->ensureStudentFaceReference($siswa);
                
                // Create session - DIPERBAIKI untuk konsisten
                $_SESSION['student_id'] = $siswa['id'];
                $_SESSION['student_nisn'] = $siswa['student_nisn'];
                $_SESSION['student_name'] = $siswa['student_name'];
                $_SESSION['class_id'] = $siswa['class_id'];
                $_SESSION['role'] = 'siswa'; // Tambahkan role
                $_SESSION['has_face'] = $has_face;
                $_SESSION['has_pose_capture'] = $this->hasPoseCaptureDataset($siswa['student_nisn'] ?? '');
                $_SESSION['logged_in'] = true;
                
                // Update last login
                $this->updateStudentLogin($siswa['id']);
                
                // Create JWT token
                $token = $this->createJWT($siswa['id'], 'student');
                setcookie('attendance_token', $token, time() + (86400 * JWT_EXPIRE), "/");
                
                return [
                    'success' => true, 
                    'id' => $siswa['id'],
                    'name' => $siswa['student_name'],
                    'class' => $siswa['class_id'],
                    'has_face' => $has_face,
                    'has_pose_capture' => $_SESSION['has_pose_capture']
                ];
            }
        }
        return ['success' => false, 'message' => 'NISN atau password salah'];
    }
    
    // Login guru - TAMBAHKAN METHOD INI
    public function loginGuru($username, $password) {
        $sql = "SELECT * FROM teacher WHERE teacher_code = ? OR teacher_username = ?";
        $stmt = $this->db->query($sql, [$username, $username]);
        $teacher = $stmt->fetch();
        
        if ($teacher) {
            $hashed_password = hash('sha256', $password . PASSWORD_SALT);
            
            if ($hashed_password === $teacher['teacher_password']) {
                // Create session untuk guru
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_code'] = $teacher['teacher_code'];
                $_SESSION['teacher_name'] = $teacher['teacher_name'];
                $_SESSION['role'] = 'guru';
                $_SESSION['level'] = 2;
                $_SESSION['logged_in'] = true;
                
                // Update last login
                $sql_update = "UPDATE teacher SET created_login = NOW() WHERE id = ?";
                $this->db->query($sql_update, [$teacher['id']]);
                
                // Log activity
                $this->logActivity($teacher['id'], 'guru', 'login', 'Guru login successful');
                
                // Create JWT token
                $token = $this->createJWT($teacher['id'], 'guru');
                setcookie('attendance_token', $token, time() + (86400 * JWT_EXPIRE), "/");
                
                return [
                    'success' => true,
                    'id' => $teacher['id'],
                    'name' => $teacher['teacher_name']
                ];
            }
        }
        return ['success' => false, 'message' => 'Kode guru atau password salah'];
    }
    
    // Create JWT Token
    private function createJWT($user_id, $role = 'admin') {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user_id,
            'role' => $role,
            'exp' => time() + (86400 * JWT_EXPIRE)
        ]);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    // Verify JWT Token
    public function verifyJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) != 3) return false;
        
        list($header, $payload, $signature) = $parts;
        
        $validSignature = hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true);
        $validSignatureBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));
        
        if ($signature !== $validSignatureBase64) return false;
        
        $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if ($payloadData['exp'] < time()) return false;
        
        return $payloadData;
    }
    
    // Update last login
    private function updateLastLogin($user_id) {
        $sql = "UPDATE user SET last_login = NOW() WHERE user_id = ?";
        $this->db->query($sql, [$user_id]);
    }
    
    private function updateStudentLogin($student_id) {
        $sql = "UPDATE student SET created_login = NOW() WHERE id = ?";
        $this->db->query($sql, [$student_id]);
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            if (isset($_SESSION['role']) && $_SESSION['role'] == 'siswa' && isset($_SESSION['student_id'])) {
                $stmt = $this->db->query(
                    "SELECT id, photo_reference FROM student WHERE id = ?",
                    [$_SESSION['student_id']]
                );
                $siswa = $stmt ? $stmt->fetch() : null;
                $_SESSION['has_face'] = $this->ensureStudentFaceReference($siswa);
                $_SESSION['has_pose_capture'] = $this->hasPoseCaptureDataset($siswa['student_nisn'] ?? '');
            }
            return true;
        }
        
        // Check cookie token
        if (isset($_COOKIE['attendance_token'])) {
            $payload = $this->verifyJWT($_COOKIE['attendance_token']);
            if ($payload) {
                // Restore session from token
                if ($payload['role'] == 'student') {
                    $sql = "SELECT * FROM student WHERE id = ?";
                    $stmt = $this->db->query($sql, [$payload['user_id']]);
                    $siswa = $stmt->fetch();
                    
                    if ($siswa) {
                        $_SESSION['student_id'] = $siswa['id'];
                        $_SESSION['student_nisn'] = $siswa['student_nisn'];
                        $_SESSION['student_name'] = $siswa['student_name'];
                        $_SESSION['class_id'] = $siswa['class_id'];
                        $_SESSION['has_face'] = $this->ensureStudentFaceReference($siswa);
                        $_SESSION['has_pose_capture'] = $this->hasPoseCaptureDataset($siswa['student_nisn'] ?? '');
                        $_SESSION['role'] = 'siswa';
                        $_SESSION['logged_in'] = true;
                        return true;
                    }
                } elseif ($payload['role'] == 'guru') {
                    $sql = "SELECT * FROM teacher WHERE id = ?";
                    $stmt = $this->db->query($sql, [$payload['user_id']]);
                    $teacher = $stmt->fetch();
                    
                    if ($teacher) {
                        $_SESSION['teacher_id'] = $teacher['id'];
                        $_SESSION['teacher_name'] = $teacher['teacher_name'];
                        $_SESSION['role'] = 'guru';
                        $_SESSION['level'] = 2;
                        $_SESSION['logged_in'] = true;
                        return true;
                    }
                } else {
                    // For admin
                    $sql = "SELECT * FROM user WHERE user_id = ?";
                    $stmt = $this->db->query($sql, [$payload['user_id']]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['fullname'] = $user['fullname'];
                        $_SESSION['level'] = $user['level'];
                        $_SESSION['role'] = 'admin';
                        $_SESSION['logged_in'] = true;
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    // Fungsi untuk log aktivitas
    private function logActivity($user_id, $user_type, $action, $details = '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $sql = "INSERT INTO activity_logs (user_id, user_type, action, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $user_id ?: NULL,
            $user_type,
            $action,
            $details,
            $ip,
            $user_agent
        ]);
    }
    
    // Logout
    public function logout() {
        session_destroy();
        setcookie('attendance_token', '', time() - 3600, "/");
    }
}
?>
