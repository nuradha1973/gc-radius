<?php
// config/database.php
$settingsFile = __DIR__ . '/settings.json';
$dbSettings = [];
if (file_exists($settingsFile)) {
    $dbSettings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

// Nilai default dikosongkan agar tidak ada kredensial yang ikut ter-commit.
// Isi kredensial sebenarnya di config/settings.json (tidak di-commit ke Git).
$host    = $dbSettings['db_host'] ?? 'localhost';
$db      = $dbSettings['db_name'] ?? 'radius_db';
$user    = $dbSettings['db_user'] ?? '';
$pass    = $dbSettings['db_pass'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$dbError = null;
$pdo = null;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    $dbError = $e->getMessage();
}
?>
