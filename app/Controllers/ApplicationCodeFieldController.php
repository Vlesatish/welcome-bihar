<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Services\ApplicationCodeFieldService;
use App\Services\AuditService;
use App\Services\AuthService;
use Exception;
use PDO;

class ApplicationCodeFieldController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Admin Form Builder main dashboard
     */
    public function index(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }

        $db = $this->db;
        $dtStmt = $db->query("SELECT id, name_en FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
        $dataType = $dtStmt->fetch(PDO::FETCH_ASSOC);
        $dataTypeId = $dataType ? (int)$dataType['id'] : 4;

        // Fetch distinct districts that have active block rate rules
        $districts = $db->query("
            SELECT DISTINCT d.id, d.name
            FROM districts d
            JOIN service_rate_rules srr ON srr.district_id = d.id
            WHERE srr.data_type_id = {$dataTypeId} AND srr.is_active = 1
            ORDER BY d.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all distinct application codes across rate rules, data types, and custom forms
        $rateCodes = ApplicationCodeFieldService::getAvailableApplicationCodes($dataTypeId, $db);

        $selectedCode = strtoupper(trim((string)($_GET['code'] ?? '')));
        if ($selectedCode === '' && !empty($rateCodes)) {
            $selectedCode = $rateCodes[0]['application_code'];
        }

        // Fetch configured fields for selected code
        $fields = [];
        if ($selectedCode !== '') {
            $fields = ApplicationCodeFieldService::getFieldsForCode($selectedCode, $dataTypeId, false, $db);
        }

        $supportedTypes = ApplicationCodeFieldService::SUPPORTED_TYPES;

        require dirname(__DIR__) . '/Views/admin/application_code_forms.php';
    }

    /**
     * Save/Update a field configuration
     */
    public function saveField(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/application-code-forms');
            exit;
        }

        $db = $this->db;
        $id = (int)($_POST['id'] ?? 0);
        $appCode = strtoupper(trim((string)($_POST['application_code'] ?? '')));
        $fieldName = strtolower(trim((string)($_POST['field_name'] ?? '')));
        $label = trim((string)($_POST['label'] ?? ''));
        $fieldType = strtolower(trim((string)($_POST['field_type'] ?? 'text')));
        $placeholder = trim((string)($_POST['placeholder'] ?? '')) ?: null;
        $helpText = trim((string)($_POST['help_text'] ?? '')) ?: null;
        $validationPattern = trim((string)($_POST['validation_pattern'] ?? '')) ?: null;
        $minLength = ($_POST['min_length'] !== '' && $_POST['min_length'] !== null) ? (int)$_POST['min_length'] : null;
        $maxLength = ($_POST['max_length'] !== '' && $_POST['max_length'] !== null) ? (int)$_POST['max_length'] : null;
        $minValue = ($_POST['min_value'] !== '' && $_POST['min_value'] !== null) ? (float)$_POST['min_value'] : null;
        $maxValue = ($_POST['max_value'] !== '' && $_POST['max_value'] !== null) ? (float)$_POST['max_value'] : null;
        $allowedFileTypes = trim((string)($_POST['allowed_file_types'] ?? '')) ?: null;
        $maxFileSizeMb = ($_POST['max_file_size_mb'] !== '' && $_POST['max_file_size_mb'] !== null) ? (int)$_POST['max_file_size_mb'] : 2;
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $isUnique = isset($_POST['is_unique']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        try {
            if ($appCode === '') {
                throw new Exception("Application Code is required.");
            }
            if ($label === '') {
                throw new Exception("Field label is required.");
            }
            if (!in_array($fieldType, ApplicationCodeFieldService::SUPPORTED_TYPES, true)) {
                throw new Exception("Unsupported field type '{$fieldType}'.");
            }

            // Enforce field name rules
            $fieldName = ApplicationCodeFieldService::validateFieldName($fieldName);

            // Core fields protection
            if (in_array($fieldName, ApplicationCodeFieldService::CORE_FIELDS, true)) {
                $isRequired = 1;
                $isActive = 1;
            }

            // Parse options for select/radio
            $optionsJson = null;
            $rawOptions = trim((string)($_POST['options_raw'] ?? ''));
            if (in_array($fieldType, ['select', 'radio'], true) && $rawOptions !== '') {
                $parsedOptions = [];
                $lines = explode("\n", str_replace("\r", "", $rawOptions));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $parts = explode('|', $line, 2);
                    $val = trim($parts[0]);
                    $lbl = trim($parts[1] ?? $val);
                    $parsedOptions[] = ['value' => $val, 'label' => $lbl];
                }
                if (!empty($parsedOptions)) {
                    $optionsJson = json_encode($parsedOptions, JSON_UNESCAPED_UNICODE);
                }
            }

            $dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
            $dataTypeId = (int)($dtStmt->fetchColumn() ?: 4);

            if ($id > 0) {
                // Update
                $upd = $db->prepare("
                    UPDATE application_code_fields
                    SET label = ?,
                        field_type = ?,
                        placeholder = ?,
                        help_text = ?,
                        options_json = ?,
                        validation_pattern = ?,
                        min_length = ?,
                        max_length = ?,
                        min_value = ?,
                        max_value = ?,
                        allowed_file_types = ?,
                        max_file_size_mb = ?,
                        is_required = ?,
                        is_unique = ?,
                        is_active = ?,
                        sort_order = ?,
                        updated_at = NOW()
                    WHERE id = ? AND data_type_id = ? AND application_code = ?
                ");
                $upd->execute([
                    $label, $fieldType, $placeholder, $helpText, $optionsJson,
                    $validationPattern, $minLength, $maxLength, $minValue, $maxValue,
                    $allowedFileTypes, $maxFileSizeMb, $isRequired, $isUnique, $isActive,
                    $sortOrder, $id, $dataTypeId, $appCode
                ]);

                AuditService::log('APPLICATION_CODE_FIELD_UPDATED', 'APPLICATION_CODE_FIELD', (string)$id, [
                    'code' => $appCode,
                    'field_name' => $fieldName
                ]);

                $_SESSION['flash_success'] = "Field '{$label}' updated successfully.";
            } else {
                // Insert new
                $ins = $db->prepare("
                    INSERT INTO application_code_fields (
                        data_type_id, application_code, field_name, label, field_type,
                        placeholder, help_text, options_json, validation_pattern, min_length,
                        max_length, min_value, max_value, allowed_file_types, max_file_size_mb,
                        is_required, is_unique, is_active, sort_order, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, NOW(), NOW()
                    )
                ");
                $ins->execute([
                    $dataTypeId, $appCode, $fieldName, $label, $fieldType,
                    $placeholder, $helpText, $optionsJson, $validationPattern, $minLength,
                    $maxLength, $minValue, $maxValue, $allowedFileTypes, $maxFileSizeMb,
                    $isRequired, $isUnique, $isActive, $sortOrder
                ]);
                $newId = (int)$db->lastInsertId();

                AuditService::log('APPLICATION_CODE_FIELD_CREATED', 'APPLICATION_CODE_FIELD', (string)$newId, [
                    'code' => $appCode,
                    'field_name' => $fieldName
                ]);

                $_SESSION['flash_success'] = "Field '{$label}' added successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/application-code-forms?code=' . urlencode($appCode));
        exit;
    }

    /**
     * Toggle field active/inactive state
     */
    public function toggleActive(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/application-code-forms?code=' . urlencode($code));
            exit;
        }

        $db = $this->db;
        $field = $db->query("SELECT * FROM application_code_fields WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);

        if (!$field) {
            $_SESSION['flash_error'] = 'Field not found.';
            header('Location: /admin/application-code-forms?code=' . urlencode($code));
            exit;
        }

        if (in_array($field['field_name'], ApplicationCodeFieldService::CORE_FIELDS, true)) {
            $_SESSION['flash_error'] = 'Core fields cannot be deactivated.';
            header('Location: /admin/application-code-forms?code=' . urlencode($code));
            exit;
        }

        $newActive = ((int)$field['is_active'] === 1) ? 0 : 1;
        $db->prepare("UPDATE application_code_fields SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$newActive, $id]);

        AuditService::log('APPLICATION_CODE_FIELD_DISABLED', 'APPLICATION_CODE_FIELD', (string)$id, [
            'code' => $field['application_code'],
            'field_name' => $field['field_name'],
            'new_active' => $newActive
        ]);

        $_SESSION['flash_success'] = "Field '{$field['label']}' " . ($newActive ? 'activated' : 'deactivated') . ".";
        header('Location: /admin/application-code-forms?code=' . urlencode($code));
        exit;
    }

    /**
     * Delete unused field or soft-deactivate if historical values exist
     */
    public function deleteField(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/application-code-forms?code=' . urlencode($code));
            exit;
        }

        $db = $this->db;
        $field = $db->query("SELECT * FROM application_code_fields WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);

        if (!$field) {
            $_SESSION['flash_error'] = 'Field not found.';
            header('Location: /admin/application-code-forms?code=' . urlencode($code));
            exit;
        }

        if (in_array($field['field_name'], ApplicationCodeFieldService::CORE_FIELDS, true)) {
            $_SESSION['flash_error'] = 'Core fields cannot be deleted.';
            header('Location: /admin/application-code-forms?code=' . urlencode($code));
            exit;
        }

        // Check if field has submitted values
        $hasValues = (int)$db->query("SELECT COUNT(*) FROM application_field_values WHERE code_field_id = {$id}")->fetchColumn() > 0;

        if ($hasValues) {
            // Soft-deactivate to protect historical data
            $db->prepare("UPDATE application_code_fields SET is_active = 0, updated_at = NOW() WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = "Field has historical submissions; it was safely deactivated instead of deleted.";
        } else {
            // Hard delete unused field
            $db->prepare("DELETE FROM application_code_fields WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = "Unused field '{$field['label']}' deleted successfully.";
        }

        AuditService::log('APPLICATION_CODE_FIELD_DISABLED', 'APPLICATION_CODE_FIELD', (string)$id, [
            'code' => $field['application_code'],
            'field_name' => $field['field_name']
        ]);

        header('Location: /admin/application-code-forms?code=' . urlencode($code));
        exit;
    }

    /**
     * Reorder fields
     */
    public function reorder(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?: $_POST;
        $orders = $data['orders'] ?? [];
        $code = strtoupper(trim((string)($data['code'] ?? '')));

        if (!is_array($orders) || empty($orders)) {
            echo json_encode(['success' => false, 'error' => 'Invalid order data.']);
            exit;
        }

        $db = $this->db;
        $upd = $db->prepare("UPDATE application_code_fields SET sort_order = ?, updated_at = NOW() WHERE id = ?");
        foreach ($orders as $item) {
            $fId = (int)($item['id'] ?? 0);
            $order = (int)($item['sort_order'] ?? 0);
            if ($fId > 0) {
                $upd->execute([$order, $fId]);
            }
        }

        AuditService::log('APPLICATION_CODE_FIELD_REORDERED', 'APPLICATION_CODE_FIELD', $code, [
            'count' => count($orders)
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Public JSON API for Retailer Dynamic Form Wizard
     * GET /api/application-code-fields?application_code=XYZ
     */
    public function apiGetFields(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $code = strtoupper(trim((string)($_GET['application_code'] ?? '')));
        if ($code === '') {
            echo json_encode(['success' => false, 'error' => 'Application code is required.']);
            exit;
        }

        $fields = ApplicationCodeFieldService::getPublicFieldsForCode($code, null, $this->db);

        echo json_encode([
            'success' => true,
            'application_code' => $code,
            'configured' => !empty($fields),
            'fields' => $fields
        ]);
        exit;
    }
}
