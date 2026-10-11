<?php
namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;

class CsvService
{
    /**
     * Stream CSV output to browser
     */
    public static function streamCsv(string $filename, array $headers, iterable $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM so Excel opens Hindi characters correctly
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Import Districts & Blocks from CSV
     * Format: District Name, District Code, Block Name, Block Code, Is Active (1/0)
     */
    public static function importLocations(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Uploaded CSV file not found.");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Unable to open CSV file.");
        }

        $db = Database::getConnection();
        $importedDistricts = 0;
        $importedBlocks = 0;
        $rowNum = 0;

        $db->beginTransaction();

        try {
            while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                $rowNum++;
                // Skip header row if matches common terms
                if ($rowNum === 1 && stripos($data[0], 'district') !== false) {
                    continue;
                }

                $distName = trim($data[0] ?? '');
                $distCode = strtoupper(trim($data[1] ?? ''));
                $blockName = trim($data[2] ?? '');
                $blockCode = trim($data[3] ?? '');
                $isActive = isset($data[4]) ? (int)$data[4] : 1;

                if (empty($distName)) {
                    continue;
                }

                if (empty($distCode)) {
                    $distCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $distName), 0, 3));
                }

                // Insert or fetch district
                $dStmt = $db->prepare("SELECT id FROM districts WHERE name = ? OR code = ? LIMIT 1");
                $dStmt->execute([$distName, $distCode]);
                $distId = $dStmt->fetchColumn();

                if (!$distId) {
                    $dIns = $db->prepare("INSERT INTO districts (name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
                    $dIns->execute([$distName, $distName, $distCode]);
                    $distId = (int)$db->lastInsertId();
                    $importedDistricts++;
                }

                // If block specified, insert or update
                if (!empty($blockName)) {
                    if (empty($blockCode)) {
                        $blockCode = 'BLK-' . $distId . '-' . substr(md5($blockName), 0, 5);
                    }

                    $bStmt = $db->prepare("
                        INSERT INTO blocks (district_id, name, name_hi, code, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE code = VALUES(code), is_active = VALUES(is_active)
                    ");
                    $bStmt->execute([$distId, $blockName, $blockName, $blockCode, $isActive]);
                    $importedBlocks++;
                }
            }

            fclose($handle);
            $db->commit();

            return [
                'success' => true,
                'districts_count' => $importedDistricts,
                'blocks_count' => $importedBlocks,
                'total_rows_processed' => $rowNum
            ];
        } catch (Exception $e) {
            fclose($handle);
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
