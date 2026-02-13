<?php
require_once 'includes/config.php';

echo "<h2>🔐 Verify Salt & Generate Correct Hashes</h2>";
echo "<hr>";

$salt = PASSWORD_SALT;
echo "<h3>Current Salt:</h3>";
echo "<pre>$salt</pre>";
echo "Salt Length: " . strlen($salt) . " characters<br>";
echo "<hr>";

echo "<h3>Generate Hashes for Common Passwords:</h3>";

$passwords = [
    'admin',
    'admin123',
    'operator',
    'operator123',
    'guru123',
    '1234567890'
];

echo "<table border='1' cellpadding='10' style='width: 100%;'>";
echo "<tr><th>Password</th><th>SHA256 Hash (with salt)</th></tr>";

foreach ($passwords as $pass) {
    $hash = hash('sha256', $pass . $salt);
    echo "<tr>";
    echo "<td><strong>$pass</strong></td>";
    echo "<td><code style='font-size: 11px;'>$hash</code></td>";
    echo "</tr>";
}

echo "</table>";
echo "<hr>";

echo "<h3>SQL Update Commands:</h3>";
echo "<p>Copy dan jalankan di phpMyAdmin:</p>";
echo "<textarea rows='15' cols='100' style='font-family: monospace;'>";
echo "-- Update admin password to 'admin123'\n";
echo "UPDATE `user` SET `password` = '" . hash('sha256', 'admin123' . $salt) . "' WHERE `username` = 'admin';\n\n";

echo "-- Update operator password to 'operator123'\n";
echo "UPDATE `user` SET `password` = '" . hash('sha256', 'operator123' . $salt) . "' WHERE `username` = 'operator';\n\n";

echo "-- Update teacher password to 'guru123'\n";
echo "UPDATE `teacher` SET `teacher_password` = '" . hash('sha256', 'guru123' . $salt) . "' WHERE `teacher_code` = 'GR001';\n\n";

echo "-- Update student password to NISN\n";
echo "UPDATE `student` SET `student_password` = '" . hash('sha256', '1234567890' . $salt) . "' WHERE `student_nisn` = '1234567890';\n";
echo "</textarea>";
?>