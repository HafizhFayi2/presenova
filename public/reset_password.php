<?php
/**
 * File untuk reset password ke default jika ada masalah
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

$db = new Database();

echo "=== RESET PASSWORD TOOL ===\n\n";

// Reset admin password
$admin_password = 'admin';
$admin_hash = hash('sha256', $admin_password . PASSWORD_SALT);

$sql = "UPDATE user SET password = ? WHERE username = 'admin'";
$stmt = $db->query($sql, [$admin_hash]);

if ($stmt) {
    echo "✓ Admin password reset to: admin\n";
    echo "Hash: $admin_hash\n";
} else {
    echo "✗ Failed to reset admin password\n";
}

// Reset operator password
$operator_password = 'operator';
$operator_hash = hash('sha256', $operator_password . PASSWORD_SALT);

$sql = "UPDATE user SET password = ? WHERE username = 'operator'";
$stmt = $db->query($sql, [$operator_hash]);

if ($stmt) {
    echo "✓ Operator password reset to: operator\n";
    echo "Hash: $operator_hash\n";
} else {
    echo "✗ Failed to reset operator password\n";
}

// Reset student passwords to their NISN
$sql = "SELECT id, student_nisn FROM student";
$stmt = $db->query($sql);
$students = $stmt->fetchAll();

foreach ($students as $student) {
    $student_hash = hash('sha256', $student['student_nisn'] . PASSWORD_SALT);
    
    $update_sql = "UPDATE student SET student_password = ? WHERE id = ?";
    $update_stmt = $db->query($update_sql, [$student_hash, $student['id']]);
    
    echo "✓ Student ID {$student['id']} password reset to NISN\n";
}

echo "\n=== RESET COMPLETE ===\n";
echo "You can now login with:\n";
echo "- Admin: username 'admin', password 'admin'\n";
echo "- Operator: username 'operator', password 'operator'\n";
echo "- Student: NISN as both username and password\n";
?>