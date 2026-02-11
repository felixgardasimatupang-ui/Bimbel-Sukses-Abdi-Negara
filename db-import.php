<?php
/**
 * Database Import Script - Bimbel Abdi Negara
 * Jalankan: php db-import.php
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'bimbel_abdi_negara';

echo "=== Database Import untuk Bimbel Abdi Negara ===\n\n";

// Connect to MySQL
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[✓] Terhubung ke MySQL\n";
} catch (PDOException $e) {
    echo "[✗] Gagal koneksi: " . $e->getMessage() . "\n";
    exit(1);
}

// Create database
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[✓] Database '$dbname' dibuat/ada\n";
} catch (PDOException $e) {
    echo "[✗] Gagal buat database: " . $e->getMessage() . "\n";
    exit(1);
}

// Select database
$pdo->exec("USE $dbname");

// Read SQL file
$sqlFile = __DIR__ . '/database/setup.sql';
if (!file_exists($sqlFile)) {
    echo "[✗] File $sqlFile tidak ditemukan\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);

// Remove comments and split statements
$statements = array_filter(array_map('trim', preg_split('/;/', $sql)), 'strlen');

$success = 0;
$failed = 0;

echo "\n[>] Mengimpor tabel...\n";

foreach ($statements as $i => $statement) {
    // Skip empty and delimiter statements
    if (empty($statement) || strpos($statement, 'DELIMITER') !== false) {
        continue;
    }
    
    try {
        $pdo->exec($statement);
        $success++;
    } catch (PDOException $e) {
        $failed++;
        echo "[!] Error pada statement $i: " . substr($e->getMessage(), 0, 100) . "...\n";
    }
    
    // Progress indicator
    if (($i + 1) % 5 === 0) {
        echo ".";
    }
}

echo "\n\n=== Hasil Import ===\n";
echo "[✓] Berhasil: $success statement\n";
if ($failed > 0) {
    echo "[!] Gagal: $failed statement\n";
}

echo "\n[✓] Database siap!\n";
echo "\nLogin Admin: admin / admin123\n";
echo "URL: http://localhost:8000/admin/\n";
