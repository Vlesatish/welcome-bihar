<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\CsvService;
use App\Services\AuditService;
use Exception;
use PDO;

class LocationController
{
    /**
     * AJAX endpoint: Fetch blocks for a given district
     */
    public function getBlocks(): void
    {
        header('Content-Type: application/json');

        $districtId = (int)($_GET['district_id'] ?? 0);
        if ($districtId <= 0) {
            echo json_encode([]);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, name, name_hi, code FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([$districtId]);
        $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($blocks);
        exit;
    }

    /**
     * Admin view for Districts & Blocks management
     */
    public function index(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /user/dashboard');
            exit;
        }

        $db = Database::getConnection();
        $selectedDistrictId = (int)($_GET['district_id'] ?? 0);

        // Fetch all 38 districts with block count
        $districts = $db->query("
            SELECT d.*, COUNT(b.id) as block_count
            FROM districts d
            LEFT JOIN blocks b ON d.id = b.district_id
            GROUP BY d.id
            ORDER BY d.name ASC
        ")->fetchAll();

        $blocks = [];
        $currentDistrict = null;
        if ($selectedDistrictId > 0) {
            $stmt = $db->prepare("SELECT * FROM districts WHERE id = ?");
            $stmt->execute([$selectedDistrictId]);
            $currentDistrict = $stmt->fetch();

            $bStmt = $db->prepare("SELECT * FROM blocks WHERE district_id = ? ORDER BY name ASC");
            $bStmt->execute([$selectedDistrictId]);
            $blocks = $bStmt->fetchAll();
        }

        require dirname(__DIR__) . '/Views/admin/locations.php';
    }

    public function saveDistrict(): void
    {
        if (!AuthService::isAdmin() || !AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Unauthorized.';
            header('Location: /admin/locations');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $nameHi = trim($_POST['name_hi'] ?? '') ?: $name;
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $db = Database::getConnection();
        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE districts SET name = ?, name_hi = ?, code = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $nameHi, $code, $isActive, $id]);
                AuditService::log('DISTRICT_UPDATED', 'DISTRICT', (string)$id, ['name' => $name]);
                $_SESSION['flash_success'] = 'District updated successfully.';
            } else {
                $stmt = $db->prepare("INSERT INTO districts (name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $nameHi, $code, $isActive]);
                AuditService::log('DISTRICT_CREATED', 'DISTRICT', (string)$db->lastInsertId(), ['name' => $name]);
                $_SESSION['flash_success'] = 'District created successfully.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /admin/locations');
        exit;
    }

    public function saveBlock(): void
    {
        if (!AuthService::isAdmin() || !AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Unauthorized.';
            header('Location: /admin/locations');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $nameHi = trim($_POST['name_hi'] ?? '') ?: $name;
        $code = trim($_POST['code'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $db = Database::getConnection();
        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE blocks SET name = ?, name_hi = ?, code = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $nameHi, $code, $isActive, $id]);
                AuditService::log('BLOCK_UPDATED', 'BLOCK', (string)$id, ['name' => $name]);
                $_SESSION['flash_success'] = 'Block updated successfully.';
            } else {
                $stmt = $db->prepare("INSERT INTO blocks (district_id, name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$districtId, $name, $nameHi, $code, $isActive]);
                AuditService::log('BLOCK_CREATED', 'BLOCK', (string)$db->lastInsertId(), ['name' => $name]);
                $_SESSION['flash_success'] = 'Block created successfully.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /admin/locations?district_id=' . $districtId);
        exit;
    }

    public function exportCsv(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();
        $sql = "
            SELECT d.name as district_name, d.name_hi as district_name_hi, d.code as district_code,
                   b.name as block_name, b.name_hi as block_name_hi, b.code as block_code,
                   b.is_active
            FROM blocks b
            JOIN districts d ON b.district_id = d.id
            ORDER BY d.name ASC, b.name ASC
        ";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $headers = ['District Name', 'District Alt Name', 'District Code', 'Block Name', 'Block Alt Name', 'Block Code', 'Is Active'];
        CsvService::streamCsv('bihar_districts_blocks_' . date('Ymd') . '.csv', $headers, $rows);
    }

    public function importCsv(): void
    {
        if (!AuthService::isAdmin() || !AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Unauthorized.';
            header('Location: /admin/locations');
            exit;
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Please select a valid CSV file.';
            header('Location: /admin/locations');
            exit;
        }

        try {
            $res = CsvService::importLocations($_FILES['csv_file']['tmp_name']);
            $_SESSION['flash_success'] = "CSV imported: {$res['districts_count']} districts and {$res['blocks_count']} blocks updated.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'CSV import error: ' . $e->getMessage();
        }

        header('Location: /admin/locations');
        exit;
    }
}
