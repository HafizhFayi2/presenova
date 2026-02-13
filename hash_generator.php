<?php
/**
 * File ini untuk generate hash password dengan salt yang sama
 * Salt: $%DSuTyr47542@#&*!=QxR094{a911}+
 */

function generate_hash($password) {
    $salt = '$%DSuTyr47542@#&*!=QxR094{a911}+';
    return hash('sha256', $password . $salt);
}

// Test hash untuk verifikasi
echo "=== HASH PASSWORD GENERATOR ===\n\n";

$passwords = [
    'admin' => '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918',
    'operator' => 'c0fc26e449ec10285f6b28a7f92b91dc4497af26dbf02aade5bd798c567390dc',
    'guru123' => '88b3340abaa6acbf87abe45f68fa8960224c1e36f6a96433bcbc490c84c9c6d2',
    '1234567890' => '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4'
];

foreach ($passwords as $password => $expected_hash) {
    $generated_hash = generate_hash($password);
    $status = ($generated_hash === $expected_hash) ? '✓ BENAR' : '✗ SALAH';
    
    echo "Password: {$password}\n";
    echo "Expected: {$expected_hash}\n";
    echo "Generated: {$generated_hash}\n";
    echo "Status: {$status}\n";
    echo str_repeat("-", 80) . "\n";
}

// Generate hash untuk password baru jika diperlukan
echo "\n=== GENERATE HASH BARU ===\n";
echo "Masukkan password untuk generate hash: ";
$handle = fopen("php://stdin", "r");
$new_password = trim(fgets($handle));
fclose($handle);

$new_hash = generate_hash($new_password);
echo "\nPassword: {$new_password}\n";
echo "Hash: {$new_hash}\n";
?>