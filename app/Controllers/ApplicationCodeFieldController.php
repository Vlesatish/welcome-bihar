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
        $hasCoreFields = false;
        if ($selectedCode !== '') {
            $fields = ApplicationCodeFieldService::getFieldsForCode($selectedCode, $dataTypeId, false, $db);
            $hasCoreFields = ApplicationCodeFieldService::hasActiveCoreFields($selectedCode, $dataTypeId, $db);
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

        $rawMinLen = trim((string)($_POST['min_length'] ?? ''));
        $rawMaxLen = trim((string)($_POST['max_length'] ?? ''));
        $rawMinVal = trim((string)($_POST['min_value'] ?? ''));
        $rawMaxVal = trim((string)($_POST['max_value'] ?? ''));
        $rawMaxFileSize = trim((string)($_POST['max_file_size_mb'] ?? ''));

        $minLength = ($rawMinLen !== '') ? (int)$rawMinLen : null;
        $maxLength = ($rawMaxLen !== '') ? (int)$rawMaxLen : null;
        $minValue = ($rawMinVal !== '') ? (float)$rawMinVal : null;
        $maxValue = ($rawMaxVal !== '') ? (float)$rawMaxVal : null;
        $allowedFileTypes = trim((string)($_POST['allowed_file_types'] ?? '')) ?: null;
        $maxFileSizeMb = ($rawMaxFileSize !== '') ? (int)$rawMaxFileSize : 2;
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $isUnique = isset($_POST['is_unique']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = max(0, min(9999, (int)($_POST['sort_order'] ?? 0)));

        try {
            if ($appCode === '') {
                throw new Exception("Application Code is required.");
            }
            if ($label === '') {
                throw new Exception("Field label is required.");
            }
            if (mb_strlen($label) > 100) {
                throw new Exception("Field label cannot exceed 100 characters.");
            }
            if ($placeholder !== null && mb_strlen($placeholder) > 150) {
                throw new Exception("Placeholder cannot exceed 150 characters.");
            }
            if ($helpText !== null && mb_strlen($helpText) > 255) {
                throw new Exception("Help text cannot exceed 255 characters.");
            }

            if (!in_array($fieldType, ApplicationCodeFieldService::SUPPORTED_TYPES, true)) {
                throw new Exception("Unsupported field type '{$fieldType}'.");
            }

            // Numeric and length constraints validation
            if ($minLength !== null && $minLength < 0) {
                throw new Exception("Minimum length cannot be negative.");
            }
            if ($maxLength !== null && $maxLength < 0) {
                throw new Exception("Maximum length cannot be negative.");
            }
            if ($minLength !== null && $maxLength !== null && $minLength > $maxLength) {
                throw new Exception("Minimum length cannot exceed maximum length.");
            }

            if ($minValue !== null && $minValue < 0) {
                throw new Exception("Minimum value cannot be negative.");
            }
            if ($maxValue !== null && $maxValue < 0) {
                throw new Exception("Maximum value cannot be negative.");
            }
            if ($minValue !== null && $maxValue !== null && $minValue > $maxValue) {
                throw new Exception("Minimum value cannot exceed maximum value.");
            }

            if ($maxFileSizeMb < 1 || $maxFileSizeMb > 25) {
                throw new Exception("Maximum file size must be between 1 MB and 25 MB.");
            }

            // Regular expression safety
            if ($validationPattern !== null) {
                if (mb_strlen($validationPattern) > 255) {
                    throw new Exception("Validation pattern is too long (maximum 255 characters).");
                }
                $testPattern = '#' . str_replace('#', '\#', $validationPattern) . '#u';
                $testResult = @preg_match($testPattern, '');
                if ($testResult === false) {
                    throw new Exception("Invalid regular expression pattern.");
                }
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
            if (in_array($fieldType, ['select', 'radio'], true)) {
                if ($rawOptions === '') {
                    throw new Exception("Options are required for {$fieldType} fields.");
                }
                $parsedOptions = [];
                $seenValues = [];
                $lines = explode("\n", str_replace("\r", "", $rawOptions));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $parts = explode('|', $line, 2);
                    $val = trim($parts[0]);
                    $optLabel = trim($parts[1] ?? $val);
                    if ($val === '') {
                        throw new Exception("Option value cannot be empty.");
                    }
                    if ($optLabel === '') {
                        throw new Exception("Option label cannot be empty.");
                    }
                    if (isset($seenValues[$val])) {
                        throw new Exception("Duplicate option value '{$val}' is not allowed.");
                    }
                    $seenValues[$val] = true;
                    $parsedOptions[] = [
                        'value' => htmlspecialchars($val, ENT_QUOTES, 'UTF-8'),
                        'label' => htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8')
                    ];
                }
                if (empty($parsedOptions)) {
                    throw new Exception("At least one valid option is required.");
                }
                $optionsJson = json_encode($parsedOptions, JSON_UNESCAPED_UNICODE);
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
     * Explicit Admin Action: Initialize core fields (application_number and applicant_name) for code.
     */
    public function initializeCoreFields(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }

        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/application-code-forms?code=' . urlencode($code));
            exit;
        }

        if ($code === '') {
            $_SESSION['flash_error'] = 'Application Code is required.';
            header('Location: /admin/application-code-forms');
            exit;
        }

        $db = $this->db;
        $db->beginTransaction();
        try {
            $dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
            $dataTypeId = (int)($dtStmt->fetchColumn() ?: 4);

            ApplicationCodeFieldService::createCoreFieldsForCode($code, $dataTypeId, $db);
            $db->commit();

            AuditService::log('APPLICATION_CODE_CORE_FIELDS_INIT', 'APPLICATION_CODE_FIELD', $code, [
                'code' => $code
            ]);

            $_SESSION['flash_success'] = "Core fields initialized successfully for {$code}.";
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = 'Failed to initialize core fields: ' . $e->getMessage();
        }

        header('Location: /admin/application-code-forms?code=' . urlencode($code));
        exit;
    }

    /**
     * Reorder fields
     */
    public function reorder(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!AuthService::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?: $_POST;

        $csrf = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!AuthService::validateCsrfToken($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        $orders = $data['orders'] ?? [];
        $code = strtoupper(trim((string)($data['code'] ?? '')));

        if ($code === '' || !is_array($orders) || empty($orders)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Invalid order data.']);
            exit;
        }

        $db = $this->db;
        $dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
        $dataTypeId = (int)($dtStmt->fetchColumn() ?: 4);

        // Fetch valid field IDs for this service and code
        $validStmt = $db->prepare("SELECT id FROM application_code_fields WHERE data_type_id = ? AND application_code = ?");
        $validStmt->execute([$dataTypeId, $code]);
        $validIds = $validStmt->fetchAll(PDO::FETCH_COLUMN);
        $validIdMap = array_fill_keys(array_map('intval', $validIds), true);

        // Validate duplicates and ownership
        $seenIds = [];
        foreach ($orders as $item) {
            $fId = (int)($item['id'] ?? 0);
            $order = (int)($item['sort_order'] ?? 0);
            if ($fId <= 0 || !isset($validIdMap[$fId])) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => "Field ID {$fId} does not belong to {$code}."]);
                exit;
            }
            if (isset($seenIds[$fId])) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => "Duplicate field ID {$fId} in reorder payload."]);
                exit;
            }
            if ($order < 0 || $order > 9999) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => "Invalid sort order {$order}."]);
                exit;
            }
            $seenIds[$fId] = true;
        }

        $db->beginTransaction();
        try {
            $upd = $db->prepare("UPDATE application_code_fields SET sort_order = ?, updated_at = NOW() WHERE id = ? AND data_type_id = ? AND application_code = ?");
            foreach ($orders as $item) {
                $fId = (int)$item['id'];
                $order = (int)$item['sort_order'];
                $upd->execute([$order, $fId, $dataTypeId, $code]);
            }
            $db->commit();

            AuditService::log('APPLICATION_CODE_FIELD_REORDERED', 'APPLICATION_CODE_FIELD', $code, [
                'count' => count($orders)
            ]);

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update order.']);
        }
        exit;
    }

    /**
     * Public JSON API for Retailer Dynamic Form Wizard
     * GET /api/application-code-fields?district_id={id}&block_id={id}&application_code={code}
     */
    public function apiGetFields(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // 1. User is authenticated
        $user = AuthService::user();
        if (!$user) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Authentication required.']);
            exit;
        }

        // 2. User role is allowed to submit
        $role = $user['role'] ?? '';
        if (!in_array($role, ['USER', 'DISTRIBUTOR', 'ADMIN', 'OPERATOR'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'User role is not authorized to submit applications.']);
            exit;
        }

        $db = $this->db;

        // 3. Service srv_block_app is active
        $service = $db->query("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$service || (int)$service['is_active'] !== 1) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Block application service is currently inactive.']);
            exit;
        }
        $dataTypeId = (int)$service['id'];

        // 4. District is active
        $districtId = (int)($_GET['district_id'] ?? 0);
        if ($districtId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'District ID is required.']);
            exit;
        }
        $distStmt = $db->prepare("SELECT id, is_active FROM districts WHERE id = ? LIMIT 1");
        $distStmt->execute([$districtId]);
        $dist = $distStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dist || (int)$dist['is_active'] !== 1) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Selected district is not active.']);
            exit;
        }

        // 5. Block is active and belongs to District
        $blockId = (int)($_GET['block_id'] ?? 0);
        if ($blockId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Block ID is required.']);
            exit;
        }
        $blkStmt = $db->prepare("SELECT id, is_active, district_id FROM blocks WHERE id = ? LIMIT 1");
        $blkStmt->execute([$blockId]);
        $blk = $blkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$blk || (int)$blk['is_active'] !== 1 || (int)$blk['district_id'] !== $districtId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Selected block is not active or does not belong to the district.']);
            exit;
        }

        // 6. Block Service Availability is LIVE
        $sbaStmt = $db->prepare("SELECT status FROM service_block_availability WHERE data_type_id = ? AND district_id = ? AND block_id = ? LIMIT 1");
        $sbaStmt->execute([$dataTypeId, $districtId, $blockId]);
        $sbaStatus = $sbaStmt->fetchColumn();
        if ($sbaStatus !== 'LIVE') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Service is currently unavailable in this block.']);
            exit;
        }

        // 7. Exact active positive rate exists for District + Block + Application Code
        $code = strtoupper(trim((string)($_GET['application_code'] ?? '')));
        if ($code === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Application code is required.']);
            exit;
        }
        $rateStmt = $db->prepare("
            SELECT id, fee_amount FROM service_rate_rules
            WHERE data_type_id = ? AND district_id = ? AND block_id = ? AND application_code = ? AND is_active = 1
            LIMIT 1
        ");
        $rateStmt->execute([$dataTypeId, $districtId, $blockId, $code]);
        $rateRow = $rateStmt->fetch(PDO::FETCH_ASSOC);
        if (!$rateRow || (float)$rateRow['fee_amount'] <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => "No active positive rate configured for code '{$code}' in this location."]);
            exit;
        }

        // 8. The code form contains active core fields
        if (!ApplicationCodeFieldService::hasActiveCoreFields($code, $dataTypeId, $db)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => "Form configuration is incomplete for code '{$code}'."]);
            exit;
        }

        $fields = ApplicationCodeFieldService::getPublicFieldsForCode($code, $dataTypeId, $db);

        echo json_encode([
            'success' => true,
            'application_code' => $code,
            'configured' => !empty($fields),
            'rate' => (float)$rateRow['fee_amount'],
            'fields' => $fields
        ]);
        exit;
    }
}
