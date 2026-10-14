<?php
/**
 * Welcome Bihar - Automated Database Backup Utility
 *
 * Exports full MySQL schema & data to timestamped SQL dump in storage/backups/
 * Usage:
 *   php cli/db_backup.php               # Run full backup
 *   php cli/db_backup.php --dry-run     # Test connection and storage without dumping
 *   php cli/db_backup.php --keep=7      # Keep only last 7 backups, purge older
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/Config/Env.php';
require dirname(__DIR__) . '/app/Config/Database.php';

\App\Config\Env::load(dirname(__DIR__) . '/.env');

$isDryRun = in_array('--dry-run', $argv, true);
$keepCount = 14;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--keep=')) {
        $keepCount = max(1, (int)substr($arg, 7));
    }
}

$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir)) {
    if (!@mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
        fwrite(STDERR, "[ERROR] Could not create backup directory: {$backupDir}\n");
        exit(1);
    }
}

echo "========================================================\n";
echo "WELCOME BIHAR - DATABASE BACKUP UTILITY\n";
echo "========================================================\n";
echo "Timestamp:   " . date('Y-m-d H:i:s T') . "\n";
echo "Target Dir:  {$backupDir}\n";
echo "Mode:        " . ($isDryRun ? "DRY-RUN (Verification only)" : "FULL BACKUP") . "\n";
echo "Retention:   Keep latest {$keepCount} dumps\n";
echo "--------------------------------------------------------\n";

try {
    $db = \App\Config\Database::getConnection();
    echo "[+] Database connection successful.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

if ($isDryRun) {
    echo "[+] Dry-run completed: Database and storage directories are verified and ready.\n";
    exit(0);
}

$dbName = $_ENV['DB_NAME'] ?? 'welcome_bihar';
$timestamp = date('Ymd_His');
$sqlFilename = "backup_{$dbName}_{$timestamp}.sql";
$sqlFilePath = "{$backupDir}/{$sqlFilename}";

$fp = fopen($sqlFilePath, 'w');
if (!$fp) {
    fwrite(STDERR, "[ERROR] Cannot open output file for writing: {$sqlFilePath}\n");
    exit(1);
}

// Write dump header
fwrite($fp, "-- ========================================================\n");
fwrite($fp, "-- Welcome Bihar Database Dump\n");
fwrite($fp, "-- Database: {$dbName}\n");
fwrite($fp, "-- Date: " . date('Y-m-d H:i:s') . "\n");
fwrite($fp, "-- ========================================================\n\n");
fwrite($fp, "SET NAMES utf8mb4;\n");
fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n");
fwrite($fp, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

$tablesStmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = $tablesStmt->fetchAll(\PDO::FETCH_NUM);

$totalRowsBackedUp = 0;

foreach ($tables as $tRow) {
    $tableName = $tRow[0];
    echo "  -> Backing up table: {$tableName}... ";

    // Table structure
    fwrite($fp, "\n-- --------------------------------------------------------\n");
    fwrite($fp, "-- Table structure for `{$tableName}`\n");
    fwrite($fp, "-- --------------------------------------------------------\n");
    fwrite($fp, "DROP TABLE IF EXISTS `{$tableName}`;\n");

    $createStmt = $db->query("SHOW CREATE TABLE `{$tableName}`");
    $createRow = $createStmt->fetch(\PDO::FETCH_ASSOC);
    fwrite($fp, $createRow['Create Table'] . ";\n\n");

    // Table data
    $countStmt = $db->query("SELECT COUNT(*) FROM `{$tableName}`");
    $rowCount = (int)$countStmt->fetchColumn();

    if ($rowCount > 0) {
        fwrite($fp, "-- Dumping data for table `{$tableName}` ({$rowCount} rows)\n");
        fwrite($fp, "LOCK TABLES `{$tableName}` WRITE;\n");

        $dataStmt = $db->query("SELECT * FROM `{$tableName}`");
        $chunkSize = 100;
        $batch = [];

        while ($row = $dataStmt->fetch(\PDO::FETCH_ASSOC)) {
            $escaped = array_map(function ($val) use ($db) {
                if ($val === null) {
                    return 'NULL';
                }
                return $db->quote((string)$val);
            }, $row);

            $batch[] = "(" . implode(', ', $escaped) . ")";

            if (count($batch) >= $chunkSize) {
                $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
                fwrite($fp, "INSERT INTO `{$tableName}` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
            fwrite($fp, "INSERT INTO `{$tableName}` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
        }

        fwrite($fp, "UNLOCK TABLES;\n\n");
    }

    $totalRowsBackedUp += $rowCount;
    echo "Done ({$rowCount} rows)\n";
}

fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
fwrite($fp, "-- Dump complete. Total records: {$totalRowsBackedUp}\n");
fclose($fp);

$fileSizeBytes = filesize($sqlFilePath);
$fileSizeMb = round($fileSizeBytes / (1024 * 1024), 2);
$sha256 = hash_file('sha256', $sqlFilePath);

echo "--------------------------------------------------------\n";
echo "[SUCCESS] Database backup generated successfully!\n";
echo "File:     {$sqlFilename}\n";
echo "Size:     {$fileSizeMb} MB ({$fileSizeBytes} bytes)\n";
echo "SHA256:   {$sha256}\n";
echo "Tables:   " . count($tables) . "\n";
echo "Records:  {$totalRowsBackedUp}\n";

// Retention cleanup: Keep newest $keepCount files
$existingBackups = glob("{$backupDir}/backup_{$dbName}_*.sql*");
if (count($existingBackups) > $keepCount) {
    usort($existingBackups, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $toDelete = array_slice($existingBackups, $keepCount);
    foreach ($toDelete as $oldFile) {
        @unlink($oldFile);
        echo " [PURGE] Removed older backup: " . basename($oldFile) . "\n";
    }
}

echo "========================================================\n";
exit(0);
