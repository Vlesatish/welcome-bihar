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

$rules = $db->query("SELECT * FROM service_rate_rules")->fetchAll(PDO::FETCH_ASSOC);
echo "Service Rate Rules count: " . count($rules) . "\n";
foreach ($rules as $r) {
    echo "ID: {$r['id']} | data_type_id: {$r['data_type_id']} | district_id: {$r['district_id']} | block_id: {$r['block_id']} | app_code: {$r['application_code']} | fee: {$r['fee_amount']} | active: {$r['is_active']}\n";
}

