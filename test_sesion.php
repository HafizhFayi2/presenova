<?php
// File untuk testing session setelah login
session_start();

echo "<h2>Session Debug Information</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";
echo "<h3>Session Variables:</h3>";
echo "<p><strong>student_id:</strong> " . (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'NOT SET') . "</p>";
echo "<p><strong>teacher_id:</strong> " . (isset($_SESSION['teacher_id']) ? $_SESSION['teacher_id'] : 'NOT SET') . "</p>";
echo "<p><strong>role:</strong> " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET') . "</p>";
echo "<p><strong>level:</strong> " . (isset($_SESSION['level']) ? $_SESSION['level'] : 'NOT SET') . "</p>";

echo "<hr>";
echo "<h3>Actions:</h3>";
echo "<a href='login.php'>Kembali ke Login</a><br>";
echo "<a href='dashboard/siswa.php'>Ke Dashboard Siswa</a><br>";
echo "<a href='dashboard/guru.php'>Ke Dashboard Guru</a><br>";
echo "<a href='dashboard/admin.php'>Ke Dashboard Admin</a><br>";
echo "<a href='login.php?logout=1'>Logout</a>";
?>