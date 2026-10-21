<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;

class ApplicationCodeFieldService
{
    public const CORE_FIELDS = ['application_number', 'applicant_name'];

    public const RESERVED_NAMES = [
        'id', 'app_ref', 'user_id', 'distributor_id', 'data_type_id', 'district_id', 'block_id',
        'fee_debited', 'status', 'created_at', 'updated_at', 'csrf_token', 'password', 'role',
        'is_locked', 'assigned_operator_id', 'assigned_at', 'assignment_method'
    ];

    public const SUPPORTED_TYPES = [
        'text', 'number', 'mobile', 'aadhaar', 'date', 'select', 'radio', 'textarea', 'file', 'email'
    ];

    /**
     * Get all known application codes from rate rules, data types, and custom forms.
     */
    public static function getAvailableApplicationCodes(?int $dataTypeId = null, ?PDO $db = null): array
    {
        if ($db === null) {
            $db = Database::getConnection();
        }
        if ($dataTypeId === null || $dataTypeId <= 0) {
            $dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
            $dataTypeId = (int)($dtStmt->fetchColumn() ?: 4);
        }

        $codeMap = [];

        // 1. From data_type_fields options_json
        try {
            $dtf = $db->query("SELECT options_json FROM data_type_fields WHERE field_name = 'application_code' AND options_json IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($dtf && !empty($dtf['options_json'])) {
                $opts = json_decode($dtf['options_json'], true) ?: [];
                foreach ($opts as $o) {
                    $c = strtoupper(trim($o['value'] ?? ''));
                    if ($c !== '') {
                        $codeMap[$c] = [
                            'application_code' => $c,
                            'rule_label' => $o['label_en'] ?? $c,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 2. From service_rate_rules
        try {
            $srr = $db->query("SELECT DISTINCT application_code, rule_label FROM service_rate_rules WHERE application_code IS NOT NULL AND application_code != ''")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($srr as $r) {
                $c = strtoupper(trim($r['application_code']));
                if ($c !== '') {
                    if (!isset($codeMap[$c])) {
                        $codeMap[$c] = [
                            'application_code' => $c,
                            'rule_label' => $r['rule_label'] ?: $c,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 3. From application_code_fields
        try {
            $acf = $db->query("SELECT DISTINCT application_code FROM application_code_fields WHERE application_code IS NOT NULL AND application_code != ''")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($acf as $c) {
                $c = strtoupper(trim($c));
                if ($c !== '' && !isset($codeMap[$c])) {
                    $codeMap[$c] = [
                        'application_code' => $c,
                        'rule_label' => $c,
                    ];
                }
            }
        } catch (\Throwable $e) {}

        // Always ensure LABOURCARD is present
        if (!isset($codeMap['LABOURCARD'])) {
            $codeMap['LABOURCARD'] = ['application_code' => 'LABOURCARD', 'rule_label' => 'Labour Card / BOCW Registration'];
        }

        // Add active field counts
        try {
            $counts = $db->query("SELECT application_code, COUNT(*) as cnt FROM application_code_fields WHERE is_active = 1 GROUP BY application_code")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $e) {
            $counts = [];
        }

        $result = [];
        foreach ($codeMap as $code => $info) {
            $info['field_count'] = (int)($counts[$code] ?? 0);
            $result[] = $info;
        }

        usort($result, function($a, $b) {
            return strcmp($a['application_code'], $b['application_code']);
        });

        return $result;
    }

    /**
     * Fetch configured fields for an Application Code.
     */
    public static function getFieldsForCode(string $code, ?int $dataTypeId = null, bool $activeOnly = false, ?PDO $db = null): array
    {
        if ($db === null) {
            $db = Database::getConnection();
        }

        $code = strtoupper(trim($code));
        if ($code === '') {
            return [];
        }

        if ($dataTypeId === null || $dataTypeId <= 0) {
            $dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
            $dataTypeId = (int)($dtStmt->fetchColumn() ?: 4);
        }

        $sql = "
            SELECT * FROM application_code_fields
            WHERE data_type_id = ? AND application_code = ?
        ";
        if ($activeOnly) {
            $sql .= " AND is_active = 1 ";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$dataTypeId, $code]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows) && !$activeOnly) {
            self::ensureCoreFieldsForCode($code, $dataTypeId, $db);
            $stmt->execute([$dataTypeId, $code]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($rows as &$r) {
            $r['options'] = !empty($r['options_json']) ? (json_decode($r['options_json'], true) ?: []) : [];
        }
        unset($r);

        return $rows;
    }

    /**
     * Ensure baseline core fields (application_number, applicant_name) exist for code.
     */
    public static function ensureCoreFieldsForCode(string $code, int $dataTypeId, PDO $db): void
    {
        $code = strtoupper(trim($code));
        if ($code === '' || $dataTypeId <= 0) return;

        try {
            $check = $db->prepare("SELECT id FROM application_code_fields WHERE data_type_id = ? AND application_code = ? AND field_name = 'application_number'");
            $check->execute([$dataTypeId, $code]);
            if (!$check->fetch()) {
                $ins = $db->prepare("
                    INSERT INTO application_code_fields (
                        data_type_id, application_code, field_name, label, field_type,
                        placeholder, help_text, validation_pattern, min_length, max_length,
                        is_required, is_unique, is_active, sort_order, created_at
                    ) VALUES (
                        ?, ?, 'application_number', 'Application Number', 'text',
                        ?, 'Enter application receipt number', '^[A-Za-z0-9/_ -]{5,64}$', 5, 64,
                        1, 1, 1, 1, NOW()
                    )
                ");
                $ins->execute([$dataTypeId, $code, "e.g. {$code}/2026/12345"]);
            }

            $check2 = $db->prepare("SELECT id FROM application_code_fields WHERE data_type_id = ? AND application_code = ? AND field_name = 'applicant_name'");
            $check2->execute([$dataTypeId, $code]);
            if (!$check2->fetch()) {
                $ins2 = $db->prepare("
                    INSERT INTO application_code_fields (
                        data_type_id, application_code, field_name, label, field_type,
                        placeholder, help_text, min_length, max_length,
                        is_required, is_unique, is_active, sort_order, created_at
                    ) VALUES (
                        ?, ?, 'applicant_name', 'Applicant Name', 'text',
                        'Full Name of Applicant', NULL, 2, 150,
                        1, 0, 1, 2, NOW()
                    )
                ");
                $ins2->execute([$dataTypeId, $code]);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Fetch public/sanitized field definitions for Retailer Wizard API.
     */
    public static function getPublicFieldsForCode(string $code, ?int $dataTypeId = null, ?PDO $db = null): array
    {
        $all = self::getFieldsForCode($code, $dataTypeId, true, $db);
        $public = [];

        foreach ($all as $f) {
            $public[] = [
                'id' => (int)$f['id'],
                'field_name' => $f['field_name'],
                'label' => $f['label'],
                'field_type' => $f['field_type'],
                'placeholder' => $f['placeholder'],
                'help_text' => $f['help_text'],
                'options' => $f['options'],
                'validation_pattern' => $f['validation_pattern'],
                'min_length' => $f['min_length'] !== null ? (int)$f['min_length'] : null,
                'max_length' => $f['max_length'] !== null ? (int)$f['max_length'] : null,
                'min_value' => $f['min_value'] !== null ? (float)$f['min_value'] : null,
                'max_value' => $f['max_value'] !== null ? (float)$f['max_value'] : null,
                'allowed_file_types' => $f['allowed_file_types'],
                'max_file_size_mb' => $f['max_file_size_mb'] !== null ? (int)$f['max_file_size_mb'] : 2,
                'is_required' => (int)$f['is_required'],
                'is_unique' => (int)$f['is_unique'],
                'sort_order' => (int)$f['sort_order']
            ];
        }

        return $public;
    }

    /**
     * Validate field name according to rules.
     */
    public static function validateFieldName(string $fieldName): string
    {
        $fName = strtolower(trim($fieldName));
        if ($fName === '') {
            throw new Exception("Field name cannot be empty.");
        }

        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $fName)) {
            throw new Exception("Field name '{$fName}' is invalid. It must use lowercase snake_case (letters, numbers, underscore) and begin with a letter.");
        }

        if (in_array($fName, self::RESERVED_NAMES, true)) {
            throw new Exception("Field name '{$fName}' is a reserved internal name and cannot be used.");
        }

        return $fName;
    }

    /**
     * Validate submitted application fields against configured field definitions.
     */
    public static function validateSubmittedFields(
        string $code,
        array $submittedValues,
        int $dataTypeId,
        PDO $db,
        array $uploadedFiles = []
    ): array {
        $code = strtoupper(trim($code));
        $fields = self::getFieldsForCode($code, $dataTypeId, true, $db);

        if (empty($fields)) {
            throw new Exception("Application form is not configured for code '{$code}'.");
        }

        $validatedData = [];
        $coreData = [
            'application_number' => '',
            'applicant_name' => '',
            'applicant_mobile' => ''
        ];

        foreach ($fields as $field) {
            $fName = $field['field_name'];
            $fType = $field['field_type'];
            $fLabel = $field['label'];
            $isReq = (int)$field['is_required'] === 1;

            if ($fType === 'file') {
                $file = $uploadedFiles[$fName] ?? null;
                if ($isReq && (empty($file) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK)) {
                    throw new Exception("File '{$fLabel}' is required.");
                }
                if (!empty($file) && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
                    $savedPath = self::handleSecureFileUpload($file, $field);
                    $validatedData[] = [
                        'code_field_id' => $field['id'],
                        'field_name' => $fName,
                        'field_value' => $savedPath,
                        'is_unique' => (int)$field['is_unique']
                    ];
                }
                continue;
            }

            $rawVal = $submittedValues[$fName] ?? '';
            $val = is_string($rawVal) ? trim($rawVal) : (string)$rawVal;

            if ($isReq && $val === '') {
                throw new Exception("Field '{$fLabel}' is required.");
            }

            if ($val !== '') {
                if ($fType === 'aadhaar') {
                    $cleaned = preg_replace('/\D/', '', $val);
                    if (strlen($cleaned) !== 12) {
                        throw new Exception("Field '{$fLabel}' must be exactly 12 digits.");
                    }
                    $val = $cleaned;
                }

                if ($fType === 'mobile') {
                    $cleaned = preg_replace('/\D/', '', $val);
                    if (strlen($cleaned) !== 10) {
                        throw new Exception("Field '{$fLabel}' must be a valid 10-digit mobile number.");
                    }
                    $val = $cleaned;
                }

                if ($fType === 'email') {
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Field '{$fLabel}' must be a valid email address.");
                    }
                }

                if ($fType === 'number') {
                    if (!is_numeric($val)) {
                        throw new Exception("Field '{$fLabel}' must be a valid number.");
                    }
                    $numVal = (float)$val;
                    if ($field['min_value'] !== null && $numVal < (float)$field['min_value']) {
                        throw new Exception("Field '{$fLabel}' cannot be less than {$field['min_value']}.");
                    }
                    if ($field['max_value'] !== null && $numVal > (float)$field['max_value']) {
                        throw new Exception("Field '{$fLabel}' cannot be greater than {$field['max_value']}.");
                    }
                }

                if ($field['min_length'] !== null && mb_strlen($val) < (int)$field['min_length']) {
                    throw new Exception("Field '{$fLabel}' must be at least {$field['min_length']} characters.");
                }
                if ($field['max_length'] !== null && mb_strlen($val) > (int)$field['max_length']) {
                    throw new Exception("Field '{$fLabel}' cannot exceed {$field['max_length']} characters.");
                }

                if (in_array($fType, ['select', 'radio'], true) && !empty($field['options'])) {
                    $allowedVals = [];
                    foreach ($field['options'] as $opt) {
                        $allowedVals[] = is_array($opt) ? (string)($opt['value'] ?? '') : (string)$opt;
                    }
                    if (!in_array($val, $allowedVals, true)) {
                        throw new Exception("Selected value for '{$fLabel}' is invalid.");
                    }
                }

                if (!empty($field['validation_pattern'])) {
                    $pattern = $field['validation_pattern'];
                    if (strlen($pattern) <= 250) {
                        $delimPattern = '~' . str_replace('~', '\~', $pattern) . '~u';
                        if (!@preg_match($delimPattern, $val)) {
                            throw new Exception("Field '{$fLabel}' format is invalid.");
                        }
                    }
                }

                if ((int)$field['is_unique'] === 1 && $val !== '') {
                    $uStmt = $db->prepare("
                        SELECT a.id, a.app_ref
                        FROM application_field_values afv
                        JOIN applications a ON afv.application_id = a.id
                        WHERE (afv.code_field_id = ? OR (afv.field_name = ? AND (a.application_code = ? OR a.application_code IS NULL OR a.application_code = '')))
                          AND afv.field_value = ?
                          AND a.status != 'REJECTED'
                        LIMIT 1
                    ");
                    $uStmt->execute([$field['id'], $fName, $code, $val]);
                    $dup = $uStmt->fetch(PDO::FETCH_ASSOC);
                    if ($dup) {
                        throw new Exception("Duplicate value: A record with '{$fLabel}' = '{$val}' already exists (Ref: {$dup['app_ref']}).");
                    }
                }
            }

            if ($fName === 'application_number') {
                $coreData['application_number'] = $val;
            } elseif ($fName === 'applicant_name') {
                $coreData['applicant_name'] = $val;
            } elseif (in_array($fName, ['mobile_no', 'applicant_mobile', 'mobile'], true)) {
                $coreData['applicant_mobile'] = $val;
            }

            $validatedData[] = [
                'code_field_id' => $field['id'],
                'field_name' => $fName,
                'field_value' => $val,
                'is_unique' => (int)$field['is_unique']
            ];
        }

        if (empty($coreData['application_number'])) {
            throw new Exception("Application Number is required.");
        }
        if (empty($coreData['applicant_name'])) {
            throw new Exception("Applicant Name is required.");
        }

        return [
            'core' => $coreData,
            'fields' => $validatedData
        ];
    }

    /**
     * Handle secure file upload with validation.
     */
    private static function handleSecureFileUpload(array $file, array $field): string
    {
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!empty($field['allowed_file_types'])) {
            $parsed = array_map('trim', explode(',', strtolower($field['allowed_file_types'])));
            $allowedExts = array_intersect($allowedExts, $parsed);
        }

        $maxBytes = ((int)($field['max_file_size_mb'] ?? 2)) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new Exception("Uploaded file for '{$field['label']}' exceeds the maximum allowed size of {$field['max_file_size_mb']}MB.");
        }

        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts, true)) {
            throw new Exception("File type '.{$ext}' is not permitted for '{$field['label']}'. Allowed: " . implode(', ', $allowedExts));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];
        if (!isset($allowedMimes[$ext]) || $mime !== $allowedMimes[$ext]) {
            throw new Exception("Uploaded file content does not match extension '.{$ext}'.");
        }

        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/dynamic_fields/' . date('Y/m');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $randName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = $uploadDir . '/' . $randName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception("Failed to store uploaded file.");
        }

        return 'uploads/dynamic_fields/' . date('Y/m') . '/' . $randName;
    }

    /**
     * Mask Aadhaar number for list and normal views (XXXXXXXX1234).
     */
    public static function maskAadhaar(?string $aadhaar): string
    {
        if (empty($aadhaar)) {
            return '—';
        }
        $digits = preg_replace('/\D/', '', $aadhaar);
        if (strlen($digits) !== 12) {
            return htmlspecialchars($aadhaar, ENT_QUOTES, 'UTF-8');
        }
        return 'XXXXXXXX' . substr($digits, -4);
    }
}
