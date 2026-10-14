<?php
/**
 * CLI Utility: Create or Reset Administrator Account
 * Usage: php cli/create_admin.php [name] [mobile] [email] [password]
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/Config/Env.php';
require dirname(__DIR__) . '/app/Config/Database.php';

\App\Config\Env::load(dirname(__DIR__) . '/.env');

$name = $argv[1] ?? 'Super Administrator';
$mobile = $argv[2] ?? '9999900001';
$email = $argv[3] ?? 'admin@welcomebihar.gov.in';

// If password is not explicitly supplied as 4th argument, generate a secure random password
if (!empty($argv[4])) {
    $password = $argv[4];
} else {
    $randomBytes = bin2hex(random_bytes(6));
    $password = 'Adm!' . ucfirst($randomBytes) . '@26';
}

$cleanMobile = preg_replace('/\D/', '', $mobile);

echo "========================================================\n";
echo "WELCOME BIHAR - ADMIN ACCOUNT CREATOR\n";
echo "========================================================\n";
echo "Name:     $name\n";
echo "Mobile:   $cleanMobile\n";
echo "Email:    $email\n";

$db = \App\Config\Database::getConnection();

// Check if user exists
$stmt = $db->prepare("SELECT id FROM users WHERE mobile = ? OR email = ?");
$stmt->execute([$cleanMobile, $email]);
$existingId = $stmt->fetchColumn();

$hash = password_hash($password, PASSWORD_BCRYPT);

if ($existingId) {
    $upd = $db->prepare("
        UPDATE users
        SET name = ?, password_hash = ?, role = 'ADMIN', status = 'ACTIVE',
            assigned_all_districts = 1, updated_at = NOW()
        WHERE id = ?
    ");
    $upd->execute([$name, $hash, $existingId]);
    echo "Successfully updated existing user (ID: $existingId) to ADMIN.\n";
} else {
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $ins = $db->prepare("
        INSERT INTO users (uuid, role, name, mobile, email, password_hash, status, preferred_lang, center_name, assigned_all_districts, created_at)
        VALUES (?, 'ADMIN', ?, ?, ?, ?, 'ACTIVE', 'hi', 'State Headquarters Patna', 1, NOW())
    ");
    $ins->execute([$uuid, $name, $cleanMobile, $email, $hash]);
    $newId = $db->lastInsertId();

    // Create wallet for admin
    $db->prepare("INSERT IGNORE INTO wallets (user_id, balance, created_at) VALUES (?, 0.00, NOW())")->execute([$newId]);

    echo "Successfully created new ADMIN user (ID: $newId).\n";
}

echo "Login Email:  $email\n";
echo "Login Mobile: $cleanMobile\n";
echo "Password:     [Provided as input]\n";
echo "Done.\n";
