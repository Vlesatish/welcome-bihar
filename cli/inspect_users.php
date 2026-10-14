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
use App\Config\Database;
use App\Config\Config;
Config::init();
$db = Database::getConnection();
$cols = $db->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
echo "USERS COLUMNS:\n";
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
echo "\nEXISTING USERS PASSWORD CHECK:\n";
$users = $db->query("SELECT id, role, name, email, mobile, password_hash FROM users WHERE id <= 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    $match = password_verify('Welcome@2026', $u['password_hash']) ? 'MATCH' : 'NO MATCH';
    echo "ID: {$u['id']} | Email: {$u['email']} | Welcome@2026: {$match}\n";
    echo "Hash: {$u['password_hash']}\n";
}


