<?php
/**
 * File untuk test login dengan berbagai user
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>test login - presenova</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .test-case { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .credentials { font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Test Login System</h1>";

$auth = new Auth();

// Test cases
$test_cases = [
    [
        'type' => 'Admin',
        'username' => 'admin',
        'password' => 'admin',
        'expected' => true
    ],
    [
        'type' => 'Operator',
        'username' => 'operator',
        'password' => 'operator',
        'expected' => true
    ],
    [
        'type' => 'Student 1',
        'username' => '1234567890',
        'password' => '1234567890',
        'expected' => true
    ],
    [
        'type' => 'Wrong Password',
        'username' => 'admin',
        'password' => 'wrongpassword',
        'expected' => false
    ]
];

foreach ($test_cases as $test) {
    echo "<div class='test-case'>";
    echo "<h3>{$test['type']}</h3>";
    echo "<p class='credentials'>Username: {$test['username']}<br>Password: {$test['password']}</p>";
    
    if ($test['type'] == 'Student 1') {
        $result = $auth->loginSiswa($test['username'], $test['password']);
        $success = $result['success'] ?? false;
    } else {
        $success = $auth->loginAdmin($test['username'], $test['password']);
    }
    
    if ($success === $test['expected']) {
        echo "<p class='success'>✓ PASS: Login " . ($success ? 'successful' : 'failed') . " as expected</p>";
    } else {
        echo "<p class='error'>✗ FAIL: Expected " . ($test['expected'] ? 'success' : 'failure') . 
             " but got " . ($success ? 'success' : 'failure') . "</p>";
    }
    
    echo "</div>";
}

// Logout semua session
session_destroy();
setcookie('attendance_token', '', time() - 3600, "/");

echo "<hr>
    <h2>Login Information</h2>
    <ul>
        <li><strong>Admin:</strong> username: admin, password: admin</li>
        <li><strong>Operator:</strong> username: operator, password: operator</li>
        <li><strong>Student 1:</strong> NISN: 1234567890, password: 1234567890</li>
        <li><strong>Student 2:</strong> NISN: 1234567891, password: 1234567891</li>
        <li><strong>Teacher 1:</strong> username: budi.santoso, password: guru123</li>
        <li><strong>Teacher 2:</strong> username: siti.rahayu, password: guru123</li>
    </ul>
    
    <p><a href='login.php'>Go to Login Page</a></p>
    </div>
</body>
</html>";
?>
