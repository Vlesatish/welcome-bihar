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
$cols = $db->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
echo "USERS COLUMNS:\n";
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
