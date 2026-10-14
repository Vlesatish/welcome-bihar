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

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES:\n" . implode(", ", $tables) . "\n\n";

$rows = $db->query("SELECT id, code, name_en, fee_amount, is_active FROM data_types")->fetchAll(PDO::FETCH_ASSOC);
echo "DATA TYPES:\n";
print_r($rows);

