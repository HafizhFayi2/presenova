<?php
/**
 * File untuk fix password di database
 * Jalankan sekali saja di browser: http://localhost/absenyess/fix_database.php
 */

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'smk_attendance';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔧 Fix Database Passwords</h2>";
    echo "<hr>";
    
    // Salt yang digunakan
    $salt = '$%DSuTyr47542@#&*!=QxR094{a911}+';
    
    // Daftar password yang akan di-update
    $updates = [
        'admin' => [
            'table' => 'user',
            'password' => 'admin123',
            'where_column' => 'username',
            'where_value' => 'admin',
            'password_column' => 'password'
        ],
        'operator' => [
            'table' => 'user',
            'password' => 'operator123',
            'where_column' => 'username',
            'where_value' => 'operator',
            'password_column' => 'password'
        ],
        'teacher1' => [
            'table' => 'teacher',
            'password' => 'guru123',
            'where_column' => 'teacher_code',
            'where_value' => 'GR001',
            'password_column' => 'teacher_password'
        ],
        'teacher2' => [
            'table' => 'teacher',
            'password' => 'guru123',
            'where_column' => 'teacher_code',
            'where_value' => 'GR002',
            'password_column' => 'teacher_password'
        ],
        'student1' => [
            'table' => 'student',
            'password' => '1234567890',
            'where_column' => 'student_nisn',
            'where_value' => '1234567890',
            'password_column' => 'student_password'
        ],
        'student2' => [
            'table' => 'student',
            'password' => '1234567891',
            'where_column' => 'student_nisn',
            'where_value' => '1234567891',
            'password_column' => 'student_password'
        ]
    ];
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Account</th><th>Password</th><th>Hash</th><th>Status</th>";
    echo "</tr>";
    
    foreach ($updates as $name => $data) {
        $hash = hash('sha256', $data['password'] . $salt);
        
        $sql = "UPDATE {$data['table']} 
                SET {$data['password_column']} = :hash 
                WHERE {$data['where_column']} = :where_value";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'hash' => $hash,
            'where_value' => $data['where_value']
        ]);
        
        $status = $stmt->rowCount() > 0 ? '✅ Updated' : '⚠️ Not Found';
        $color = $stmt->rowCount() > 0 ? 'green' : 'orange';
        
        echo "<tr>";
        echo "<td><strong>{$name}</strong> ({$data['where_value']})</td>";
        echo "<td><code>{$data['password']}</code></td>";
        echo "<td style='font-size: 10px;'><code>{$hash}</code></td>";
        echo "<td style='color: $color; font-weight: bold;'>{$status}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>✅ Database passwords updated successfully!</h3>";
    echo "<p><strong>Login credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username = admin, password = admin123</li>";
    echo "<li><strong>Operator:</strong> username = operator, password = operator123</li>";
    echo "<li><strong>Teacher:</strong> username = budi.santoso, password = guru123</li>";
    echo "<li><strong>Student:</strong> NISN = 1234567890, password = 1234567890</li>";
    echo "</ul>";
    
    echo "<hr>";
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>➜ Go to Login Page</a></p>";
    
    echo "<hr>";
    echo "<p style='color: red;'><strong>⚠️ IMPORTANT:</strong> Delete this file after use for security!</p>";
    
} catch (PDOException $e) {
    echo "<div style='background: #fee; border: 1px solid red; padding: 20px; color: red;'>";
    echo "<h3>❌ Database Connection Error</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Solution:</strong></p>";
    echo "<ul>";
    echo "<li>Check if MySQL is running</li>";
    echo "<li>Verify database name: <code>smk_attendance</code></li>";
    echo "<li>Check database credentials in this file</li>";
    echo "</ul>";
    echo "</div>";
}
?>