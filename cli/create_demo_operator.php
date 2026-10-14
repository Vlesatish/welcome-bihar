<?php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});
require_once dirname(__DIR__) . '/app/helpers.php';
\App\Config\Config::init();

$db = \App\Config\Database::getConnection();

$demoHash = password_hash('Welcome@2026', PASSWORD_BCRYPT);

// 1. Ensure danapur.operator@welcomebihar.gov.in exists with role OPERATOR
$stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR mobile = ?");
$stmt->execute(['danapur.operator@welcomebihar.gov.in', '9999900004']);
$existingOp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existingOp) {
    $ins = $db->prepare("
        INSERT INTO users (uuid, role, name, mobile, email, password_hash, status, preferred_lang, center_name, assigned_district_id, assigned_block_id, assigned_all_districts)
        VALUES (UUID(), 'OPERATOR', 'Danapur Block Operator', '9999900004', 'danapur.operator@welcomebihar.gov.in', ?, 'ACTIVE', 'hi', 'Danapur Block Verification Cell', 26, 448, 0)
    ");
    $ins->execute([$demoHash]);
    echo "[OK] Created demo operator danapur.operator@welcomebihar.gov.in\n";
} else {
    $up = $db->prepare("
        UPDATE users 
        SET role = 'OPERATOR', name = 'Danapur Block Operator', password_hash = ?, status = 'ACTIVE', assigned_district_id = 26, assigned_block_id = 448
        WHERE id = ?
    ");
    $up->execute([$demoHash, $existingOp['id']]);
    echo "[OK] Updated demo operator id " . $existingOp['id'] . "\n";
}

// 2. Also ensure User 56 (Satish Kumar) has Welcome@2026 if needed
$db->prepare("UPDATE users SET password_hash = ? WHERE id = 56")->execute([$demoHash]);
echo "[OK] Updated Satish Kumar password to Welcome@2026\n";

echo "Done.\n";
