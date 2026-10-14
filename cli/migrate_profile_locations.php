<?php
/**
 * Safe Migration: Add profile location columns to users and migrate existing retailer data.
 */
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use App\Config\Database;

$db = Database::getConnection();

echo "=== MIGRATION: RETAILER PROFILE LOCATION & PERMISSION CLEANUP ===\n";

// 1. Check existing columns in users table
$cols = $db->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('profile_state', $cols, true)) {
    $db->exec("ALTER TABLE users ADD COLUMN profile_state VARCHAR(100) DEFAULT 'Bihar' AFTER center_name");
    echo "[OK] Added users.profile_state\n";
} else {
    echo "[SKIP] users.profile_state already exists\n";
}

if (!in_array('profile_district_id', $cols, true)) {
    $db->exec("ALTER TABLE users ADD COLUMN profile_district_id INT(11) NULL AFTER profile_state, ADD INDEX (profile_district_id)");
    echo "[OK] Added users.profile_district_id\n";
} else {
    echo "[SKIP] users.profile_district_id already exists\n";
}

if (!in_array('profile_block_id', $cols, true)) {
    $db->exec("ALTER TABLE users ADD COLUMN profile_block_id INT(11) NULL AFTER profile_district_id, ADD INDEX (profile_block_id)");
    echo "[OK] Added users.profile_block_id\n";
} else {
    echo "[SKIP] users.profile_block_id already exists\n";
}

if (!in_array('center_address', $cols, true)) {
    $db->exec("ALTER TABLE users ADD COLUMN center_address TEXT NULL AFTER profile_block_id");
    echo "[OK] Added users.center_address\n";
} else {
    echo "[SKIP] users.center_address already exists\n";
}

// 2. Migrate existing retailer data into profile_district_id & profile_block_id
$users = $db->query("SELECT id, assigned_district_id, assigned_block_id, profile_district_id, profile_block_id FROM users WHERE role = 'USER'")->fetchAll(PDO::FETCH_ASSOC);
$migratedCount = 0;

foreach ($users as $u) {
    $userId = (int)$u['id'];
    $pDist = $u['profile_district_id'];
    $pBlk = $u['profile_block_id'];

    if (empty($pDist)) {
        // Try assigned_district_id first
        if (!empty($u['assigned_district_id'])) {
            $pDist = (int)$u['assigned_district_id'];
            $pBlk = !empty($u['assigned_block_id']) ? (int)$u['assigned_block_id'] : null;
        } else {
            // Check location_permissions
            $lp = $db->query("SELECT district_id, block_id FROM location_permissions WHERE user_id = {$userId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($lp) {
                $pDist = (int)$lp['district_id'];
                $pBlk = !empty($lp['block_id']) ? (int)$lp['block_id'] : null;
            }
        }

        if (!empty($pDist)) {
            $upd = $db->prepare("UPDATE users SET profile_state = 'Bihar', profile_district_id = ?, profile_block_id = ? WHERE id = ?");
            $upd->execute([$pDist, $pBlk, $userId]);
            $migratedCount++;
        }
    }
}
echo "[OK] Migrated profile locations for {$migratedCount} existing retailers.\n";

// 3. Remove location_permissions entries for USER role so they have full access across active Bihar locations
$deletedLp = $db->exec("DELETE lp FROM location_permissions lp JOIN users u ON lp.user_id = u.id WHERE u.role = 'USER'");
echo "[OK] Cleaned up {$deletedLp} location_permissions entries for USER role.\n";

echo "=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
