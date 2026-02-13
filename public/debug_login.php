<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Debug Login System</h2>";
echo "<hr>";

// 1. Cek Config
echo "<h3>1. Check Configuration</h3>";
require_once 'includes/config.php';
echo "✓ Config loaded<br>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "PASSWORD_SALT: " . PASSWORD_SALT . "<br>";
echo "<hr>";

// 2. Cek Database Connection
echo "<h3>2. Check Database Connection</h3>";
try {
    require_once 'includes/database.php';
    echo "✓ Database connected successfully<br>";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
    die();
}
echo "<hr>";

// 3. Cek Data User di Database
echo "<h3>3. Check User Data in Database</h3>";
$sql = "SELECT user_id, username, password, fullname, level FROM user WHERE username = 'admin'";
$stmt = $db->query($sql);
$user = $stmt->fetch();

if ($user) {
    echo "✓ User 'admin' found in database<br>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>user_id</td><td>{$user['user_id']}</td></tr>";
    echo "<tr><td>username</td><td>{$user['username']}</td></tr>";
    echo "<tr><td>password (hash)</td><td><code>{$user['password']}</code></td></tr>";
    echo "<tr><td>fullname</td><td>{$user['fullname']}</td></tr>";
    echo "<tr><td>level</td><td>{$user['level']}</td></tr>";
    echo "</table>";
} else {
    echo "✗ User 'admin' NOT FOUND in database<br>";
    die();
}
echo "<hr>";

// 4. Test Password Hashing
echo "<h3>4. Test Password Hashing</h3>";
$test_passwords = ['admin', 'admin123', 'password123'];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Test Password</th><th>Generated Hash</th><th>Match?</th></tr>";

foreach ($test_passwords as $test_pass) {
    $generated_hash = hash('sha256', $test_pass . PASSWORD_SALT);
    $match = ($generated_hash === $user['password']);
    $status = $match ? '✓ YES' : '✗ NO';
    $color = $match ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td><strong>$test_pass</strong></td>";
    echo "<td><code>$generated_hash</code></td>";
    echo "<td style='color: $color; font-weight: bold;'>$status</td>";
    echo "</tr>";
}
echo "</table>";
echo "<hr>";

// 5. Test Auth Class
echo "<h3>5. Test Auth Class Login</h3>";
require_once 'includes/auth.php';
$auth = new Auth();

$test_credentials = [
    ['username' => 'admin', 'password' => 'admin'],
    ['username' => 'admin', 'password' => 'admin123'],
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Username</th><th>Password</th><th>Result</th></tr>";

foreach ($test_credentials as $cred) {
    session_destroy();
    session_start();
    
    $result = $auth->loginAdmin($cred['username'], $cred['password']);
    $status = $result ? '✓ SUCCESS' : '✗ FAILED';
    $color = $result ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td>{$cred['username']}</td>";
    echo "<td>{$cred['password']}</td>";
    echo "<td style='color: $color; font-weight: bold;'>$status</td>";
    echo "</tr>";
}
echo "</table>";
echo "<hr>";

// 6. Manual Hash Check
echo "<h3>6. Manual Hash Verification</h3>";
$manual_username = 'admin';
$manual_password = 'admin123'; // Ganti dengan password yang ingin dicoba

$manual_hash = hash('sha256', $manual_password . PASSWORD_SALT);

echo "Testing credentials:<br>";
echo "Username: <strong>$manual_username</strong><br>";
echo "Password: <strong>$manual_password</strong><br>";
echo "Generated Hash: <code>$manual_hash</code><br>";
echo "Database Hash: <code>{$user['password']}</code><br>";
echo "<br>";

if ($manual_hash === $user['password']) {
    echo "✓ <span style='color: green; font-weight: bold;'>HASH MATCH! Password is correct.</span><br>";
} else {
    echo "✗ <span style='color: red; font-weight: bold;'>HASH MISMATCH! Password is wrong.</span><br>";
    echo "<br>Possible issues:<br>";
    echo "1. Wrong salt in config.php<br>";
    echo "2. Wrong password in database<br>";
    echo "3. Encoding/character set issue<br>";
}
?>