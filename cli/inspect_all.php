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
$db = Database::getConnection();

$settings = $db->query("SELECT setting_key, setting_group, LEFT(setting_value, 40) as val FROM settings")->fetchAll(PDO::FETCH_ASSOC);
echo "Settings:\n";
foreach ($settings as $s) {
    echo "  {$s['setting_key']} [{$s['setting_group']}]: {$s['val']}\n";
}
