<?php
namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;

class CsvService
{
    public const MAX_IMPORT_ROWS = 5000;

    /**
     * Sanitize cell value to prevent formula injection in spreadsheet applications.
     * Cells starting with =, +, -, @, \t, \r are escaped with a leading single quote,
     * while preserving Hindi and standard UTF-8 strings.
     */
    public static function sanitizeCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        $str = (string)$value;
        if ($str === '') {
            return '';
        }

        // Allow plain unsigned numbers without operators (e.g. "100.50", "0")
        if (preg_match('/^[0-9]+(\.[0-9]+)?$/', $str)) {
            return $str;
        }

        // Check if value (after stripping leading space only) starts with formula trigger characters: =, +, -, @, tab, cr
        $trimmedSpaces = ltrim($str, " ");
        if ($trimmedSpaces !== '') {
            $firstChar = $trimmedSpaces[0];
            if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $str;
            }
        }

        return $str;
    }

    /**
     * Stream CSV output to browser with formula injection protection and UTF-8 BOM
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

        $sanitizedHeaders = array_map([self::class, 'sanitizeCellValue'], $headers);
        fputcsv($output, $sanitizedHeaders);

        foreach ($rows as $row) {
            if (is_array($row)) {
                $sanitizedRow = array_map([self::class, 'sanitizeCellValue'], $row);
                fputcsv($output, $sanitizedRow);
            }
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
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("Uploaded CSV file not found or is not readable.");
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

                if ($rowNum > self::MAX_IMPORT_ROWS) {
                    throw new Exception("CSV file exceeds maximum allowed limit of " . self::MAX_IMPORT_ROWS . " rows.");
                }

                // Check for blank line
                if (empty($data) || (count($data) === 1 && $data[0] === null)) {
                    continue;
                }

                // Skip header row if matches common terms
                if ($rowNum === 1 && stripos((string)($data[0] ?? ''), 'district') !== false) {
                    continue;
                }

                // Validate column count
                if (count($data) < 1) {
                    throw new Exception("Malformed CSV record at line {$rowNum}: missing columns.");
                }

                $distName = trim((string)($data[0] ?? ''));
                $distCode = strtoupper(trim((string)($data[1] ?? '')));
                $blockName = trim((string)($data[2] ?? ''));
                $blockCode = trim((string)($data[3] ?? ''));
                $isActive = isset($data[4]) && $data[4] !== '' ? (int)$data[4] : 1;

                // Validate malformed condition: block or code given without district name
                if (empty($distName)) {
                    if (!empty($blockName) || !empty($distCode)) {
                        throw new Exception("Malformed CSV row at line {$rowNum}: Block or code provided without District Name.");
                    }
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
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
