<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\AuditService;
use Exception;

class DataTypeController
{
    public function __construct()
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }
    }

    public function index(): void
    {
        $db = Database::getConnection();

        $dataTypes = $db->query("
            SELECT dt.*,
                   (SELECT COUNT(*) FROM data_type_fields WHERE data_type_id = dt.id) as field_count,
                   (SELECT COUNT(*) FROM applications WHERE data_type_id = dt.id) as app_count
            FROM data_types dt
            ORDER BY dt.sort_order ASC
        ")->fetchAll();

        require dirname(__DIR__) . '/Views/admin/data_types.php';
    }

    public function saveDataType(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $code = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['code'] ?? '')));
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameHi = trim($_POST['name_hi'] ?? '') ?: $nameEn;
        $descEn = trim($_POST['description_en'] ?? '');
        $descHi = trim($_POST['description_hi'] ?? '') ?: $descEn;
        $icon = trim($_POST['icon'] ?? 'bi-folder2-open');
        $fee = (float)($_POST['fee_amount'] ?? 50.00);
        $comm = (float)($_POST['distributor_commission'] ?? 10.00);
        $dupField = trim($_POST['duplicate_check_field'] ?? 'aadhaar_no');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE data_types
                    SET name_en = ?, name_hi = ?, description_en = ?, description_hi = ?,
                        icon = ?, fee_amount = ?, distributor_commission = ?, duplicate_check_field = ?,
                        is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$nameEn, $nameHi, $descEn, $descHi, $icon, $fee, $comm, $dupField, $isActive, $id]);
                AuditService::log('DATA_TYPE_UPDATED', 'DATA_TYPE', (string)$id, ['name' => $nameEn]);
                $_SESSION['flash_success'] = 'Data type updated successfully.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO data_types (code, name_en, name_hi, description_en, description_hi, icon, fee_amount, distributor_commission, duplicate_check_field, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$code, $nameEn, $nameHi, $descEn, $descHi, $icon, $fee, $comm, $dupField, $isActive]);
                AuditService::log('DATA_TYPE_CREATED', 'DATA_TYPE', (string)$db->lastInsertId(), ['name' => $nameEn]);
                $_SESSION['flash_success'] = 'Data type created successfully.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /admin/data-types');
        exit;
    }

    public function fields(): void
    {
        $dataTypeId = (int)($_GET['id'] ?? 0);
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM data_types WHERE id = ?");
        $stmt->execute([$dataTypeId]);
        $dataType = $stmt->fetch();

        if (!$dataType) {
            $_SESSION['flash_error'] = 'Data type not found.';
            header('Location: /admin/data-types');
            exit;
        }

        $fStmt = $db->prepare("SELECT * FROM data_type_fields WHERE data_type_id = ? ORDER BY sort_order ASC");
        $fStmt->execute([$dataTypeId]);
        $fields = $fStmt->fetchAll();

        require dirname(__DIR__) . '/Views/admin/data_type_fields.php';
    }

    public function saveField(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);
        $fieldName = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['field_name'] ?? '')));
        $labelEn = trim($_POST['label_en'] ?? '');
        $labelHi = trim($_POST['label_hi'] ?? '') ?: $labelEn;
        $fieldType = trim($_POST['field_type'] ?? 'text');
        $placeholderEn = trim($_POST['placeholder_en'] ?? '');
        $placeholderHi = trim($_POST['placeholder_hi'] ?? '') ?: $placeholderEn;
        $helpTextEn = trim($_POST['help_text_en'] ?? '');
        $helpTextHi = trim($_POST['help_text_hi'] ?? '') ?: $helpTextEn;
        $optionsRaw = trim($_POST['options_raw'] ?? '');
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $isUnique = isset($_POST['is_unique']) ? 1 : 0;
        $validationPattern = trim($_POST['validation_pattern'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        // Parse options if provided
        $optionsJson = null;
        if (!empty($optionsRaw)) {
            $parsedOptions = [];
            $lines = explode("\n", str_replace("\r", "", $optionsRaw));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = explode('|', $line);
                $val = trim($parts[0]);
                $lblEn = trim($parts[1] ?? $val);
                $lblHi = trim($parts[2] ?? $lblEn);
                $parsedOptions[] = ['value' => $val, 'label_en' => $lblEn, 'label_hi' => $lblHi];
            }
            if (!empty($parsedOptions)) {
                $optionsJson = json_encode($parsedOptions, JSON_UNESCAPED_UNICODE);
            }
        }

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE data_type_fields
                    SET field_name = ?, label_en = ?, label_hi = ?, field_type = ?,
                        placeholder_en = ?, placeholder_hi = ?, help_text_en = ?, help_text_hi = ?,
                        options_json = ?, is_required = ?, is_unique = ?, validation_pattern = ?,
                        sort_order = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $fieldName, $labelEn, $labelHi, $fieldType,
                    $placeholderEn, $placeholderHi, $helpTextEn, $helpTextHi,
                    $optionsJson, $isRequired, $isUnique, $validationPattern,
                    $sortOrder, $id
                ]);
                AuditService::log('FIELD_UPDATED', 'FIELD', (string)$id, ['field_name' => $fieldName]);
                $_SESSION['flash_success'] = 'Field updated successfully.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO data_type_fields (
                        data_type_id, field_name, label_en, label_hi, field_type,
                        placeholder_en, placeholder_hi, help_text_en, help_text_hi,
                        options_json, is_required, is_unique, validation_pattern,
                        sort_order, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, NOW()
                    )
                ");
                $stmt->execute([
                    $dataTypeId, $fieldName, $labelEn, $labelHi, $fieldType,
                    $placeholderEn, $placeholderHi, $helpTextEn, $helpTextHi,
                    $optionsJson, $isRequired, $isUnique, $validationPattern,
                    $sortOrder
                ]);
                AuditService::log('FIELD_CREATED', 'FIELD', (string)$db->lastInsertId(), ['field_name' => $fieldName]);
                $_SESSION['flash_success'] = 'Field added successfully.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /admin/data-types/fields?id=' . $dataTypeId);
        exit;
    }

    public function deleteField(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $fieldId = (int)($_POST['field_id'] ?? 0);
        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);

        $db = Database::getConnection();
        $db->prepare("DELETE FROM data_type_fields WHERE id = ?")->execute([$fieldId]);

        AuditService::log('FIELD_DELETED', 'FIELD', (string)$fieldId);
        $_SESSION['flash_success'] = 'Field deleted.';

        header('Location: /admin/data-types/fields?id=' . $dataTypeId);
        exit;
    }
}
