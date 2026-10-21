<?php
namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;
use App\Services\StaffRoutingService;
use App\Services\Traits\ApplicationStatusTrait;

class ApplicationService
{
    use ApplicationStatusTrait;

    /**
     * Submit a new application with validation, duplicate checks, file upload handling, and atomic wallet fee debit
     */
    public static function submit(
        int $userId,
        int $dataTypeId,
        int $districtId,
        int $blockId,
        array $submittedValues,
        array $uploadedFiles = []
    ): array {
        $db = Database::getConnection();

        // 1. Verify user status and role
        $userStmt = $db->prepare("SELECT id, role, parent_distributor_id, status FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        if (!$user || $user['status'] !== 'ACTIVE') {
            throw new Exception(__("account_inactive", "Your account is not active."));
        }

        if ($user['role'] === 'OPERATOR') {
            throw new Exception("Operators are not authorized to submit applications. Operators can only process assigned applications.");
        }

        // Verify District exists and is active
        $dChk = $db->prepare("SELECT id, state, is_active FROM districts WHERE id = ? LIMIT 1");
        $dChk->execute([$districtId]);
        $districtRow = $dChk->fetch(PDO::FETCH_ASSOC);
        if (!$districtRow || (int)$districtRow['is_active'] !== 1) {
            throw new Exception("Invalid or inactive District selected.");
        }

        // Verify Block exists, is active, and belongs to selected District
        $bChk = $db->prepare("SELECT id, is_active FROM blocks WHERE id = ? AND district_id = ? LIMIT 1");
        $bChk->execute([$blockId, $districtId]);
        $blockRow = $bChk->fetch(PDO::FETCH_ASSOC);
        if (!$blockRow || (int)$blockRow['is_active'] !== 1) {
            throw new Exception("Invalid Block selected or Block does not belong to the selected District.");
        }

        // 2. Fetch Data Type configuration
        $dtStmt = $db->prepare("SELECT * FROM data_types WHERE id = ?");
        $dtStmt->execute([$dataTypeId]);
        $dataType = $dtStmt->fetch();

        if (!$dataType || (int)$dataType['is_active'] === 0) {
            throw new Exception(__("service_unavailable", "Selected service is currently inactive or disabled by administrator."));
        }

        if ($dataType['code'] !== 'srv_block_app') {
            throw new Exception("Invalid service selected.");
        }

        // Validate block-level service availability is LIVE
        $sbaStmt = $db->prepare("
            SELECT status FROM service_block_availability
            WHERE data_type_id = ? AND district_id = ? AND block_id = ?
            LIMIT 1
        ");
        $sbaStmt->execute([$dataTypeId, $districtId, $blockId]);
        $sbaStatus = $sbaStmt->fetchColumn();

        if ($sbaStatus !== 'LIVE') {
            throw new Exception("Service is temporarily unavailable for the selected block.");
        }

        // 3. Dynamic Price Calculation (Server-Side Rate Rules)

        $pricing = PricingService::calculatePrice($dataTypeId, $districtId, $blockId, $submittedValues);
        $feeAmount = (float)$pricing['final_fee'];
        if ($feeAmount <= 0) {
            throw new Exception("No active rate is configured for the selected block and application code.");
        }
        $distributorCommission = (float)$pricing['final_commission'];
        $distributorId = $user['parent_distributor_id'] ? (int)$user['parent_distributor_id'] : null;

        // 4. Fetch Data Type Fields
        $fStmt = $db->prepare("SELECT * FROM data_type_fields WHERE data_type_id = ? ORDER BY sort_order ASC, id ASC");
        $fStmt->execute([$dataTypeId]);
        $fields = $fStmt->fetchAll();

        // 5. Validate Fields & Handle Document Uploads
        $validatedData = [];
        $applicantName = '';
        $applicantMobile = '';
        $applicantIdentifier = null;

        $uploadDir = dirname(dirname(__DIR__)) . '/public/uploads/documents';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($fields as $field) {
            $fName = $field['field_name'];
            $fieldType = $field['field_type'];
            $val = '';

            if ($fieldType === 'file') {
                $file = $uploadedFiles[$fName] ?? null;
                $hasFile = ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && $file['size'] > 0);

                if ($field['is_required'] && !$hasFile) {
                    throw new Exception("Document '{$field['label_en']}' is required.");
                }

                if ($hasFile) {
                    // Check file extension
                    $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowedExts = array_map('trim', explode(',', strtolower($field['file_types'] ?: 'pdf,jpg,jpeg,png')));
                    $disallowedExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'cgi', 'pl', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm'];

                    if (in_array($origExt, $disallowedExts, true) || !in_array($origExt, $allowedExts, true)) {
                        throw new Exception("File format '.{$origExt}' not permitted for '{$field['label_en']}'. Allowed: " . implode(', ', $allowedExts));
                    }

                    // Check file size
                    $maxSizeMb = (int)($field['max_size_mb'] ?: 2);
                    $maxSizeBytes = $maxSizeMb * 1024 * 1024;
                    if ($file['size'] > $maxSizeBytes) {
                        throw new Exception("File for '{$field['label_en']}' exceeds max allowed size of {$maxSizeMb}MB.");
                    }

                    // Validate MIME type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    $allowedMimes = [
                        'pdf' => 'application/pdf',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png'
                    ];

                    $safeMime = false;
                    foreach ($allowedExts as $ext) {
                        if (isset($allowedMimes[$ext]) && $allowedMimes[$ext] === $mime) {
                            $safeMime = true;
                            break;
                        }
                    }

                    if (!$safeMime && !str_starts_with($mime, 'image/') && $mime !== 'application/pdf') {
                        throw new Exception("Invalid file content MIME type for '{$field['label_en']}'.");
                    }

                    // Store safely
                    $safeFileName = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $origExt;
                    $targetPath = $uploadDir . '/' . $safeFileName;

                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        throw new Exception("Failed to upload document for '{$field['label_en']}'. Please try again.");
                    }

                    $val = '/uploads/documents/' . $safeFileName;
                }
            } else {
                $val = trim((string)($submittedValues[$fName] ?? ''));
                if ($val === '') {
                    if (in_array($fName, ['applicant_mobile', 'mobile_no', 'mobile', 'contact_mobile', 'phone'], true)) {
                        $val = trim((string)($submittedValues['applicant_mobile'] ?? $submittedValues['mobile'] ?? $submittedValues['mobile_no'] ?? $submittedValues['contact_mobile'] ?? ''));
                    } elseif (in_array($fName, ['applicant_name', 'name', 'full_name'], true)) {
                        $val = trim((string)($submittedValues['applicant_name'] ?? $submittedValues['name'] ?? $submittedValues['full_name'] ?? ''));
                    } elseif (in_array($fName, ['application_number', 'application_no', 'app_number'], true)) {
                        $val = trim((string)($submittedValues['application_number'] ?? $submittedValues['application_no'] ?? $submittedValues['app_number'] ?? ''));
                    } elseif (in_array($fName, ['application_code', 'app_code'], true)) {
                        $val = trim((string)($submittedValues['application_code'] ?? $submittedValues['app_code'] ?? ''));
                    }
                }
                if ($fName === 'state' && $val === '') {
                    if ($districtId > 0) {
                        $stStmt = $db->prepare("SELECT state FROM districts WHERE id = ?");
                        $stStmt->execute([$districtId]);
                        $val = $stStmt->fetchColumn() ?: 'Bihar';
                    } else {
                        $val = 'Bihar';
                    }
                }

                // Required check
                if ($field['is_required'] && $val === '') {
                    throw new Exception("Field '{$field['label_en']}' is required.");
                }

                // Pattern validation
                if ($val !== '' && !empty($field['validation_pattern'])) {
                    $pattern = '~' . str_replace('~', '\~', $field['validation_pattern']) . '~u';
                    if (!@preg_match($pattern, $val)) {
                        throw new Exception("Field '{$field['label_en']}' has an invalid format.");
                    }
                }
            }

            // Common field extraction
            if (in_array($fName, ['applicant_name', 'head_of_family', 'name'], true) && empty($applicantName)) {
                $applicantName = $val;
            }
            if (in_array($fName, ['mobile_no', 'applicant_mobile', 'mobile'], true) && empty($applicantMobile)) {
                $applicantMobile = $val;
            }
            if ($fName === $dataType['duplicate_check_field'] || (!empty($field['is_unique']) && empty($applicantIdentifier))) {
                $applicantIdentifier = $val;
            }

            $validatedData[$fName] = [
                'field_id' => $field['id'],
                'field_name' => $fName,
                'value' => $val,
                'is_unique' => (int)$field['is_unique']
            ];
        }

        // Code and Application Number alignment check strictly for Block & District App Pass
        if ($dataType['code'] === 'srv_block_app') {
            $appCode = (string)($submittedValues['application_code'] ?? '');
            $appNum = (string)($submittedValues['application_number'] ?? ($applicantIdentifier ?? ''));
            self::validateApplicationCodeAndNumber($appCode, $appNum);
        }

        if (empty($applicantName)) {
            $applicantName = 'Applicant';
        }
        if (empty($applicantMobile)) {
            $applicantMobile = '9999999999';
        }

        // 6. Check Duplicate Entry & Idempotency in this data type
        if (!empty($applicantIdentifier)) {
            // Rapid duplicate/idempotency check (within 30 seconds)
            $idemStmt = $db->prepare("
                SELECT id, app_ref FROM applications
                WHERE user_id = ? AND applicant_identifier = ? AND created_at >= NOW() - INTERVAL 30 SECOND
                LIMIT 1
            ");
            $idemStmt->execute([$userId, $applicantIdentifier]);
            if ($idemStmt->fetch()) {
                throw new Exception("Duplicate submission prevented. A request with application number '{$applicantIdentifier}' was just submitted.");
            }

            $dupStmt = $db->prepare("
                SELECT id, app_ref, created_at FROM applications
                WHERE data_type_id = ? AND applicant_identifier = ? AND status != 'REJECTED'
                LIMIT 1
            ");
            $dupStmt->execute([$dataTypeId, $applicantIdentifier]);
            $existing = $dupStmt->fetch();
            if ($existing) {
                throw new Exception(__("duplicate_warning", "An active application with identifier '{$applicantIdentifier}' already exists in system (Ref: {$existing['app_ref']})."));
            }
        }

        // 7. Check Wallet Balance before starting transaction
        $currentBalance = WalletService::getBalance($userId);
        if ($feeAmount > 0 && $currentBalance < $feeAmount) {
            throw new Exception(__("insufficient_balance", "Insufficient wallet balance (Available: ₹" . number_format($currentBalance, 2) . ", Required: ₹" . number_format($feeAmount, 2) . "). Please recharge your wallet."));
        }

        // 8. Atomic DB Transaction with Row-Level Locking
        $db->beginTransaction();

        try {
            // Re-validate service block availability inside transaction to prevent race conditions
            $txSbaStmt = $db->prepare("
                SELECT status FROM service_block_availability
                WHERE data_type_id = ? AND district_id = ? AND block_id = ?
                FOR UPDATE
            ");
            $txSbaStmt->execute([$dataTypeId, $districtId, $blockId]);
            $txSbaStatus = $txSbaStmt->fetchColumn();

            if ($txSbaStatus !== 'LIVE') {
                throw new Exception("This service is currently unavailable for the selected block.");
            }

            // Row lock on wallet to prevent race conditions
            $lockStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
            $lockStmt->execute([$userId]);
            $lockedBalance = (float)$lockStmt->fetchColumn();


            if ($feeAmount > 0 && $lockedBalance < $feeAmount) {
                throw new Exception(__("insufficient_balance", "Insufficient wallet balance (Available: ₹" . number_format($lockedBalance, 2) . ", Required: ₹" . number_format($feeAmount, 2) . "). Please recharge your wallet."));
            }

            $submittedAppCode = strtoupper(trim((string)($submittedValues['application_code'] ?? ($submittedValues['app_code'] ?? ''))));
            if (empty($submittedAppCode)) {
                $submittedAppCode = 'WB-PASS';
            }

            $assignedOperatorId = StaffRoutingService::resolveOperatorForApplication($districtId, $blockId, $submittedAppCode, $db);
            $assignmentMethod = $assignedOperatorId ? 'AUTO_ROUND_ROBIN' : 'UNASSIGNED';

            // Generate unique application reference
            $appRef = 'WB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $rateSnapshot = "Applied Rate: " . ($pricing['rate_note'] ?? 'Standard Base Rate') . " (Charged: ₹" . number_format($feeAmount, 2) . ")";

            // Insert Application
            $insApp = $db->prepare("
                INSERT INTO applications (
                    app_ref, user_id, distributor_id, data_type_id, application_code,
                    assigned_operator_id, assigned_at, assignment_method,
                    district_id, block_id, applicant_name, applicant_mobile, applicant_identifier,
                    fee_debited, commission_credited, status, remarks, is_locked, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, 'PENDING', ?, 1, NOW()
                )
            ");
            $insApp->execute([
                $appRef,
                $userId,
                $distributorId,
                $dataTypeId,
                $submittedAppCode,
                $assignedOperatorId,
                $assignedOperatorId ? date('Y-m-d H:i:s') : null,
                $assignmentMethod,
                $districtId,
                $blockId,
                $applicantName,
                $applicantMobile,
                $applicantIdentifier,
                $feeAmount,
                ($distributorId && $distributorCommission > 0) ? $distributorCommission : 0.00,
                $rateSnapshot
            ]);
            $appId = (int)$db->lastInsertId();

            // Record assignment in immutable assignment history and audit logs
            StaffRoutingService::recordAssignment(
                $appId,
                $assignedOperatorId,
                $submittedAppCode,
                $assignmentMethod,
                null,
                "Initial submission routing",
                $db
            );

            // Include any additional dynamic fields submitted (e.g. aadhaar_number)
            $knownFieldNames = array_column($validatedData, 'field_name');
            $skipSingleKeys = ['csrf_token', 'redirect', 'data_type_id', 'data_type_code', 'district_id', 'block_id'];
            foreach ($submittedValues as $sKey => $sVal) {
                if (in_array($sKey, $skipSingleKeys, true) || in_array($sKey, $knownFieldNames, true)) {
                    continue;
                }
                $sValTrim = trim((string)$sVal);
                if ($sValTrim !== '') {
                    $validatedData[] = [
                        'field_id' => null,
                        'field_name' => $sKey,
                        'value' => $sValTrim,
                        'is_unique' => 0
                    ];
                    $knownFieldNames[] = $sKey;
                }
            }

            // Insert dynamic field values
            $insVal = $db->prepare("
                INSERT INTO application_field_values (application_id, field_id, field_name, field_value, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            foreach ($validatedData as $f) {
                $insVal->execute([$appId, $f['field_id'], $f['field_name'], $f['value']]);
            }

            // Initial status history
            $insHist = $db->prepare("
                INSERT INTO application_status_history (
                    application_id, from_status, to_status, actor_user_id, actor_name,
                    actor_role, remarks, created_at
                ) VALUES (
                    ?, 'NONE', 'PENDING', ?, ?,
                    ?, 'Application submitted by operator', NOW()
                )
            ");
            $actorName = $_SESSION['user_name'] ?? 'User';
            $actorRole = $_SESSION['user_role'] ?? 'USER';
            $insHist->execute([$appId, $userId, $actorName, $actorRole]);

            // 9. Debit Application Fee from user wallet
            if ($feeAmount > 0) {
                $debitIdemKey = "APP_FEE_DEBIT_{$appId}_{$appRef}";
                WalletService::debit(
                    $userId,
                    $feeAmount,
                    'APPLICATION_FEE_DEBIT',
                    'APPLICATION',
                    (string)$appId,
                    $debitIdemKey,
                    "Application submission fee ({$dataType['name_en']}) - Ref: {$appRef}",
                    "Application submission fee ({$dataType['name_en']}) - Ref: {$appRef}",
                    "Debited for application {$appRef}",
                    $userId
                );
            }

            // Note: Distributor Commission is paid when status transitions to COMPLETED, matching reference flow.
            // Eligible commission is recorded in database for subsequent payout upon approval/completion.

            AuditService::log(
                'APPLICATION_SUBMITTED',
                'APPLICATION',
                (string)$appId,
                [
                    'app_ref' => $appRef,
                    'data_type' => $dataType['name_en'],
                    'applicant' => $applicantName,
                    'fee' => $feeAmount
                ],
                $userId,
                $user['role']
            );

            // 11. Send In-App Notification to User
            NotificationService::create(
                $userId,
                "Application Submitted Successfully",
                "Your request for {$dataType['name_en']} has been registered with Reference #{$appRef}.",
                'SUCCESS',
                "/applications/view?id={$appId}"
            );

            // Construct formData object for immediate client rendering without page reload
            $formDataAssoc = [];
            foreach ($validatedData as $k => $f) {
                $formDataAssoc[$k] = $f['value'];
            }
            $distName = (string)$db->query("SELECT name FROM districts WHERE id = {$districtId}")->fetchColumn();
            $blkName = (string)$db->query("SELECT name FROM blocks WHERE id = {$blockId}")->fetchColumn();
            $formDataAssoc['district_name'] = $distName;
            $formDataAssoc['block_name'] = $blkName;

            $remainingBal = max(0.00, $lockedBalance - $feeAmount);

            // Queue WhatsApp notification to active block operators for this application's district & block
            $waData = [
                'applicant_name' => $applicantName,
                'application_number' => $applicantIdentifier ?: ($submittedValues['application_number'] ?? $appRef),
                'app_ref' => $appRef,
                'district_name' => $distName,
                'block_name' => $blkName,
                'application_code' => $submittedAppCode
            ];
            foreach ($validatedData as $vf) {
                if (empty($waData[$vf['field_name']])) {
                    $waData[$vf['field_name']] = $vf['value'];
                }
            }
            WhatsAppNotificationService::queueSubmissionNotification(
                $appId,
                $districtId,
                $blockId,
                $waData,
                $db,
                $assignedOperatorId
            );

            $db->commit();

            // Dispatch pending WhatsApp outbox messages after transaction commit
            try {
                WhatsAppNotificationService::dispatchPending(5);
            } catch (\Throwable $we) {
                error_log("WhatsApp post-commit dispatch error: " . $we->getMessage());
            }

            return [
                'success' => true,
                'application_id' => $appId,
                'app_ref' => $appRef,
                'requestNumber' => $appId,
                'price' => $feeAmount,
                'fee_debited' => $feeAmount,
                'status' => 'PENDING',
                'formData' => $formDataAssoc,
                'remainingWalletBalance' => $remainingBal,
                'createdAt' => date('c'),
                'created_at' => date('Y-m-d H:i:s'),
                'message' => __("application_submitted_success", "Application submitted successfully! Reference: ") . $appRef
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atomic Batch Submission for Multiple Applications (1, 5, or more applications)
     */
    public static function submitBatch(
        int $userId,
        int $dataTypeId,
        array $batchItems,
        array $options = []
    ): array {
        $db = Database::getConnection();

        // 1. Verify user status
        $userStmt = $db->prepare("SELECT id, role, parent_distributor_id, status, mobile FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        if (!$user || $user['status'] !== 'ACTIVE') {
            throw new Exception(__("account_inactive", "Your account is not active."));
        }

        if ($user['role'] === 'OPERATOR') {
            throw new Exception("Operators are not authorized to submit batch applications.");
        }

        // 2. Fetch Data Type configuration
        $dtStmt = $db->prepare("SELECT * FROM data_types WHERE id = ?");
        $dtStmt->execute([$dataTypeId]);
        $dataType = $dtStmt->fetch();

        if (!$dataType || (int)$dataType['is_active'] === 0) {
            throw new Exception(__("service_unavailable", "Selected service is currently inactive or disabled by administrator."));
        }

        if ($dataType['code'] !== 'srv_block_app') {
            throw new Exception("Invalid service selected.");
        }

        if (empty($batchItems) || !is_array($batchItems)) {
            throw new Exception("Batch submission cannot be empty. Please add at least one application.");
        }

        $distributorId = $user['parent_distributor_id'] ? (int)$user['parent_distributor_id'] : null;

        // Fetch Data Type Fields
        $fStmt = $db->prepare("SELECT * FROM data_type_fields WHERE data_type_id = ? ORDER BY sort_order ASC, id ASC");
        $fStmt->execute([$dataTypeId]);
        $fields = $fStmt->fetchAll();

        $seenBatchIdentifiers = [];
        $preparedItems = [];
        $priceChanges = [];
        $totalBatchFee = 0.00;

        foreach ($batchItems as $idx => $item) {
            $rowNum = $idx + 1;

            $districtId = (int)($item['district_id'] ?? 0);
            $blockId = (int)($item['block_id'] ?? 0);

            if ($districtId <= 0 || $blockId <= 0) {
                throw new Exception("Row #{$rowNum}: Please select a valid District and Block.");
            }

            // Verify District exists and is active
            $dChk = $db->prepare("SELECT id, name FROM districts WHERE id = ? AND is_active = 1 LIMIT 1");
            $dChk->execute([$districtId]);
            $distRow = $dChk->fetch(PDO::FETCH_ASSOC);
            if (!$distRow) {
                throw new Exception("Row #{$rowNum}: Selected District does not exist or is inactive.");
            }

            // Verify Block exists, is active, and belongs to selected District
            $bChk = $db->prepare("SELECT id, name FROM blocks WHERE id = ? AND district_id = ? AND is_active = 1 LIMIT 1");
            $bChk->execute([$blockId, $districtId]);
            $blkRow = $bChk->fetch(PDO::FETCH_ASSOC);
            if (!$blkRow) {
                throw new Exception("Row #{$rowNum}: Selected Block does not belong to the selected District or is inactive.");
            }

            // Verify block service availability is LIVE
            $sbaChk = $db->prepare("
                SELECT status FROM service_block_availability
                WHERE data_type_id = ? AND district_id = ? AND block_id = ?
                LIMIT 1
            ");
            $sbaChk->execute([$dataTypeId, $districtId, $blockId]);
            $sbaStatus = $sbaChk->fetchColumn();
            if ($sbaStatus !== 'LIVE') {
                throw new Exception("Row #{$rowNum}: Service is temporarily unavailable for the selected block ({$blkRow['name']}, {$distRow['name']}).");
            }


            // Normalise & validate submitted fields
            $applicantName = trim((string)($item['applicant_name'] ?? $item['name'] ?? ''));
            if ($applicantName === '') {
                throw new Exception("Row #{$rowNum}: Applicant / Firm Name is required.");
            }

            $appCode = strtoupper(trim((string)($item['application_code'] ?? $item['app_code'] ?? '')));
            if ($appCode === '') {
                throw new Exception("Row #{$rowNum}: Application Code is required.");
            }

            $appNumber = trim((string)($item['application_number'] ?? $item['app_number'] ?? ''));
            if ($appNumber === '') {
                throw new Exception("Row #{$rowNum}: Application Number is required.");
            }

            if ($dataType['code'] === 'srv_block_app') {
                self::validateApplicationCodeAndNumber($appCode, $appNumber);
            }

            // Intra-batch duplicate check
            $normalisedIdentifier = strtoupper($appNumber);
            if (isset($seenBatchIdentifiers[$normalisedIdentifier])) {
                throw new Exception("Row #{$rowNum}: Repeated application number '{$appNumber}' found in the batch (already added at Row #{$seenBatchIdentifiers[$normalisedIdentifier]}). Each application in a batch must be unique.");
            }
            $seenBatchIdentifiers[$normalisedIdentifier] = $rowNum;

            // Database duplicate check (active application)
            $dupStmt = $db->prepare("
                SELECT id, app_ref, created_at FROM applications
                WHERE data_type_id = ? AND applicant_identifier = ? AND status != 'REJECTED'
                LIMIT 1
            ");
            $dupStmt->execute([$dataTypeId, $appNumber]);
            $existing = $dupStmt->fetch();
            if ($existing) {
                throw new Exception("Row #{$rowNum}: An active application with number '{$appNumber}' already exists in system (Ref: {$existing['app_ref']}).");
            }

            // Rapid idempotency check
            $idemStmt = $db->prepare("
                SELECT id, app_ref FROM applications
                WHERE user_id = ? AND applicant_identifier = ? AND created_at >= NOW() - INTERVAL 30 SECOND
                LIMIT 1
            ");
            $idemStmt->execute([$userId, $appNumber]);
            if ($idemStmt->fetch()) {
                throw new Exception("Row #{$rowNum}: A request with application number '{$appNumber}' was submitted less than 30 seconds ago. Please wait.");
            }

            // Calculate exact server fee
            try {
                $pricing = PricingService::calculatePrice($dataTypeId, $districtId, $blockId, $item);
                $serverFee = (float)$pricing['final_fee'];
                $serverCommission = (float)$pricing['final_commission'];
                if ($serverFee <= 0) {
                    throw new Exception("No active rate is configured for the selected block and application code.");
                }
            } catch (Exception $pe) {
                throw new Exception("Row #{$rowNum}: " . $pe->getMessage() . " ({$distRow['name']} > {$blkRow['name']} > {$appCode})");
            }

            // Check if client price changed
            $clientFee = isset($item['client_fee']) ? (float)$item['client_fee'] : (isset($item['fee']) ? (float)$item['fee'] : null);
            if ($clientFee !== null && abs($clientFee - $serverFee) > 0.01) {
                $priceChanges[] = [
                    'row' => $rowNum,
                    'applicant_name' => $applicantName,
                    'application_number' => $appNumber,
                    'old_fee' => $clientFee,
                    'new_fee' => $serverFee,
                    'rate_note' => $pricing['rate_note']
                ];
            }

            // Build validated fields array
            $itemValidatedFields = [];
            foreach ($fields as $f) {
                $fName = $f['field_name'];
                $val = trim((string)($item[$fName] ?? ''));
                if ($val === '') {
                    if ($fName === 'applicant_name') $val = $applicantName;
                    elseif ($fName === 'application_code') $val = $appCode;
                    elseif ($fName === 'application_number') $val = $appNumber;
                    elseif ($fName === 'state') $val = trim((string)($item['state'] ?? 'Bihar'));
                }
                $itemValidatedFields[] = [
                    'field_id' => $f['id'],
                    'field_name' => $fName,
                    'value' => $val
                ];
            }

            // Include any additional dynamic fields (e.g. aadhaar_number, registration_number) from submitted item
            $knownFields = array_column($itemValidatedFields, 'field_name');
            $skipKeys = ['district_id', 'block_id', 'client_fee', 'fee', 'row_num', 'csrf_token'];
            foreach ($item as $iKey => $iVal) {
                if (in_array($iKey, $skipKeys, true) || in_array($iKey, $knownFields, true)) {
                    continue;
                }
                if ($iKey === 'fields' && is_array($iVal)) {
                    foreach ($iVal as $fKey => $fVal) {
                        if (is_scalar($fVal) && !in_array($fKey, $knownFields, true)) {
                            $fValTrim = trim((string)$fVal);
                            if ($fValTrim !== '') {
                                $itemValidatedFields[] = [
                                    'field_id' => null,
                                    'field_name' => $fKey,
                                    'value' => $fValTrim
                                ];
                                $knownFields[] = $fKey;
                            }
                        }
                    }
                    continue;
                }
                if (!is_scalar($iVal)) {
                    continue;
                }
                $valTrim = trim((string)$iVal);
                if ($valTrim !== '') {
                    $itemValidatedFields[] = [
                        'field_id' => null,
                        'field_name' => $iKey,
                        'value' => $valTrim
                    ];
                    $knownFields[] = $iKey;
                }
            }

            $totalBatchFee += $serverFee;

            $preparedItems[] = [
                'row_num' => $rowNum,
                'district_id' => $districtId,
                'block_id' => $blockId,
                'applicant_name' => $applicantName,
                'applicant_mobile' => !empty($item['applicant_mobile']) ? trim($item['applicant_mobile']) : (!empty($user['mobile']) ? $user['mobile'] : '9999999999'),
                'applicant_identifier' => $appNumber,
                'application_code' => $appCode,
                'fee' => $serverFee,
                'commission' => ($distributorId && $serverCommission > 0) ? $serverCommission : 0.00,
                'rate_note' => $pricing['rate_note'] ?? 'Standard Base Rate',
                'fields' => $itemValidatedFields,
                'raw_item' => $item
            ];
        }

        // If prices changed and user has not confirmed yet, return re-acceptance required
        if (!empty($priceChanges) && empty($options['accept_price_change'])) {
            return [
                'success' => false,
                'price_changed' => true,
                'changes' => $priceChanges,
                'new_total_fee' => $totalBatchFee,
                'error' => "Rates for " . count($priceChanges) . " application(s) in this batch have updated. Please review and confirm the new total fee of ₹" . number_format($totalBatchFee, 2) . "."
            ];
        }

        // Check Wallet Balance before starting transaction
        $currentBalance = WalletService::getBalance($userId);
        if ($totalBatchFee > 0 && $currentBalance < $totalBatchFee) {
            $shortfall = $totalBatchFee - $currentBalance;
            throw new Exception("Insufficient Wallet Balance! Required: ₹" . number_format($totalBatchFee, 2) . ", Available: ₹" . number_format($currentBalance, 2) . ". Shortfall: ₹" . number_format($shortfall, 2) . ". Please recharge your wallet.");
        }

        // 4. Atomic Database Transaction
        $db->beginTransaction();

        try {
            // Re-validate all items' service block availability and exact rates inside transaction to prevent race conditions
            foreach ($preparedItems as $pItem) {
                $txSbaStmt = $db->prepare("
                    SELECT status FROM service_block_availability
                    WHERE data_type_id = ? AND district_id = ? AND block_id = ?
                    FOR UPDATE
                ");
                $txSbaStmt->execute([$dataTypeId, $pItem['district_id'], $pItem['block_id']]);
                if ($txSbaStmt->fetchColumn() !== 'LIVE') {
                    throw new Exception("Row #{$pItem['row_num']}: Service is temporarily unavailable for the selected block.");
                }

                $txRateStmt = $db->prepare("
                    SELECT fee_amount FROM service_rate_rules
                    WHERE data_type_id = ? AND district_id = ? AND block_id = ? AND application_code = ? AND is_active = 1 AND fee_amount > 0
                    FOR UPDATE
                ");
                $txRateStmt->execute([$dataTypeId, $pItem['district_id'], $pItem['block_id'], $pItem['application_code']]);
                $txRateFee = (float)$txRateStmt->fetchColumn();
                if ($txRateFee <= 0 || abs($txRateFee - (float)$pItem['fee']) > 0.01) {
                    throw new Exception("Row #{$pItem['row_num']}: No active rate is configured for the selected block and application code.");
                }
            }

            // Lock wallet row
            $lockStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
            $lockStmt->execute([$userId]);
            $lockedBalance = (float)$lockStmt->fetchColumn();


            if ($totalBatchFee > 0 && $lockedBalance < $totalBatchFee) {
                $shortfall = $totalBatchFee - $lockedBalance;
                throw new Exception("Insufficient Wallet Balance! Required: ₹" . number_format($totalBatchFee, 2) . ", Available: ₹" . number_format($lockedBalance, 2) . ". Shortfall: ₹" . number_format($shortfall, 2) . ". Please recharge your wallet.");
            }

            $batchRef = 'BATCH-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

            $insApp = $db->prepare("
                INSERT INTO applications (
                    app_ref, batch_ref, user_id, distributor_id, data_type_id, application_code,
                    assigned_operator_id, assigned_at, assignment_method,
                    district_id, block_id, applicant_name, applicant_mobile, applicant_identifier,
                    fee_debited, commission_credited, status, remarks, is_locked, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, 'PENDING', ?, 1, NOW()
                )
            ");

            $insVal = $db->prepare("
                INSERT INTO application_field_values (application_id, field_id, field_name, field_value, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            $insHist = $db->prepare("
                INSERT INTO application_status_history (
                    application_id, from_status, to_status, actor_user_id, actor_name,
                    actor_role, remarks, created_at
                ) VALUES (
                    ?, 'NONE', 'PENDING', ?, ?,
                    ?, ?, NOW()
                )
            ");

            $actorName = $_SESSION['user_name'] ?? 'User';
            $actorRole = $_SESSION['user_role'] ?? 'USER';

            $createdApplications = [];

            foreach ($preparedItems as $prep) {
                $appRef = 'WB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $rateSnapshot = "Applied Rate: {$prep['rate_note']} (Charged: ₹" . number_format($prep['fee'], 2) . ")";

                $prepCode = $prep['application_code'];
                $rowAssignedOpId = StaffRoutingService::resolveOperatorForApplication($prep['district_id'], $prep['block_id'], $prepCode, $db);
                $rowMethod = $rowAssignedOpId ? 'AUTO_ROUND_ROBIN' : 'UNASSIGNED';

                $insApp->execute([
                    $appRef,
                    $batchRef,
                    $userId,
                    $distributorId,
                    $dataTypeId,
                    $prepCode,
                    $rowAssignedOpId,
                    $rowAssignedOpId ? date('Y-m-d H:i:s') : null,
                    $rowMethod,
                    $prep['district_id'],
                    $prep['block_id'],
                    $prep['applicant_name'],
                    $prep['applicant_mobile'],
                    $prep['applicant_identifier'],
                    $prep['fee'],
                    $prep['commission'],
                    $rateSnapshot
                ]);
                $appId = (int)$db->lastInsertId();

                // Record immutable assignment history
                StaffRoutingService::recordAssignment(
                    $appId,
                    $rowAssignedOpId,
                    $prepCode,
                    $rowMethod,
                    null,
                    "Batch submission routing #{$batchRef}",
                    $db
                );

                foreach ($prep['fields'] as $f) {
                    $insVal->execute([$appId, $f['field_id'], $f['field_name'], $f['value']]);
                }

                $insHist->execute([
                    $appId,
                    $userId,
                    $actorName,
                    $actorRole,
                    "Application submitted as part of Batch #{$batchRef}"
                ]);

                // Construct formatted application for immediate UI display
                $distName = (string)$db->query("SELECT name FROM districts WHERE id = {$prep['district_id']}")->fetchColumn();
                $blkName = (string)$db->query("SELECT name FROM blocks WHERE id = {$prep['block_id']}")->fetchColumn();

                $formDataAssoc = [
                    'state' => $prep['raw_item']['state'] ?? 'Bihar',
                    'district_name' => $distName,
                    'block_name' => $blkName,
                    'application_code' => $prep['application_code'],
                    'application_number' => $prep['applicant_identifier'],
                    'applicant_name' => $prep['applicant_name'],
                    'mobile' => $prep['applicant_mobile']
                ];

                $createdApplications[] = [
                    'id' => (string)$appId,
                    'requestNumber' => (string)$appId,
                    'app_ref' => $appRef,
                    'batch_ref' => $batchRef,
                    'serviceId' => 'srv_block_app',
                    'serviceTitle' => $dataType['name_en'] ?: 'Block & District Application Pass Service',
                    'status' => 'PENDING',
                    'raw_status' => 'PENDING',
                    'price' => (float)$prep['fee'],
                    'fee_debited' => (float)$prep['fee'],
                    'createdAt' => date('c'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'formData' => $formDataAssoc,
                    'adminRemarks' => $rateSnapshot,
                    'outputAttachmentUrl' => null,
                    'isRefunded' => false,
                    'isCommissionPaid' => false
                ];

                // Queue WhatsApp notification directly and only to the assigned operator for this application
                $waItemData = [
                    'applicant_name' => $prep['applicant_name'],
                    'application_number' => $prep['applicant_identifier'],
                    'app_ref' => $appRef,
                    'district_name' => $distName,
                    'block_name' => $blkName,
                    'application_code' => $prep['application_code']
                ];
                foreach ($prep['fields'] as $pf) {
                    $waItemData[$pf['field_name']] = $pf['value'];
                }
                WhatsAppNotificationService::queueSubmissionNotification(
                    $appId,
                    $prep['district_id'],
                    $prep['block_id'],
                    $waItemData,
                    $db,
                    $rowAssignedOpId
                );
            }

            // Atomic Wallet Debit for entire batch
            if ($totalBatchFee > 0) {
                $batchIdemKey = "BATCH_FEE_DEBIT_{$batchRef}";
                WalletService::debit(
                    $userId,
                    $totalBatchFee,
                    'APPLICATION_FEE_DEBIT',
                    'APPLICATION_BATCH',
                    $batchRef,
                    $batchIdemKey,
                    "Batch submission fee for " . count($preparedItems) . " applications (Batch #{$batchRef})",
                    "Batch fee (" . count($preparedItems) . " applications) - Batch #{$batchRef}",
                    "Debited for batch {$batchRef}",
                    $userId
                );
            }

            AuditService::log(
                'BATCH_SUBMITTED',
                'APPLICATION_BATCH',
                $batchRef,
                [
                    'batch_ref' => $batchRef,
                    'count' => count($preparedItems),
                    'total_fee' => $totalBatchFee
                ],
                $userId,
                $user['role']
            );

            NotificationService::create(
                $userId,
                "Batch Applications Submitted",
                "Successfully submitted " . count($preparedItems) . " applications under Batch #{$batchRef}.",
                'SUCCESS',
                "/applications"
            );

            $newBalance = max(0.00, $lockedBalance - $totalBatchFee);

            $db->commit();

            // Dispatch pending WhatsApp outbox messages after transaction commit
            try {
                WhatsAppNotificationService::dispatchPending(max(5, count($createdApplications) * 2));
            } catch (\Throwable $we) {
                error_log("Batch WhatsApp post-commit dispatch error: " . $we->getMessage());
            }

            return [
                'success' => true,
                'batch_ref' => $batchRef,
                'total_items' => count($createdApplications),
                'total_fee' => $totalBatchFee,
                'remainingWalletBalance' => $newBalance,
                'applications' => $createdApplications,
                'message' => "Successfully submitted " . count($createdApplications) . " applications (Batch #{$batchRef})! Total fee ₹" . number_format($totalBatchFee, 2) . " debited."
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function validateApplicationCodeAndNumber(string $code, string $number): void
    {
        $code = strtoupper(trim($code));
        $number = strtoupper(trim($number));

        if (empty($number)) {
            throw new Exception("Full Application Number is required.");
        }

        $minLen = ($code === 'LABOURCARD') ? 1 : 5;
        if (strlen($number) < $minLen || strlen($number) > 64) {
            throw new Exception("Application number length must be between {$minLen} and 64 characters.");
        }

        if (!preg_match('/^[A-Z0-9\/_\- ]{' . $minLen . ',64}$/', $number)) {
            throw new Exception("Application number contains invalid characters. Only alphanumeric, slash (/), hyphen (-), underscore (_), and spaces are allowed.");
        }

        // Strict code-to-number prefix/format alignment
        $codeFormatRules = [
            'BICCO' => [
                'expected_prefixes' => ['BICCO', 'BICC', 'BIIC', 'INC', 'AAY'],
                'conflicting_prefixes' => ['BCCCO', 'BCCC', 'BRCCO', 'BRCC', 'BIRC', 'BOBC', 'BCOBC', 'NCLCO', 'NCLC', 'BINC', 'BIEW', 'EWS', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'Income Certificate (BICCO)'
            ],
            'BCCCO' => [
                'expected_prefixes' => ['BCCCO', 'BCCC', 'JAAT', 'CST'],
                'conflicting_prefixes' => ['BICCO', 'BICC', 'BIIC', 'BRCCO', 'BRCC', 'BIRC', 'BOBC', 'BCOBC', 'NCLCO', 'NCLC', 'BINC', 'BIEW', 'EWS', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'Caste Certificate (BCCCO)'
            ],
            'BRCCO' => [
                'expected_prefixes' => ['BRCCO', 'BRCC', 'BIRC', 'NIWAS', 'RES', 'DOM'],
                'conflicting_prefixes' => ['BCCCO', 'BCCC', 'BICCO', 'BICC', 'BIIC', 'BOBC', 'BCOBC', 'NCLCO', 'NCLC', 'BINC', 'BIEW', 'EWS', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'Residential Certificate (BRCCO)'
            ],
            'BOBC' => [
                'expected_prefixes' => ['BOBC', 'BCOBC', 'BCOB', 'OBC'],
                'conflicting_prefixes' => ['BRCCO', 'BRCC', 'BIRC', 'BICCO', 'BICC', 'BIIC', 'BCCCO', 'BCCC', 'BIEW', 'EWS', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'OBC / Backward Class Certificate (BOBC)'
            ],
            'NCLCO' => [
                'expected_prefixes' => ['NCLCO', 'NCLC', 'BINC', 'NCL'],
                'conflicting_prefixes' => ['BRCCO', 'BRCC', 'BIRC', 'BICCO', 'BICC', 'BIIC', 'BCCCO', 'BCCC', 'BIEW', 'EWS', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'Non-Creamy Layer Certificate (NCLCO)'
            ],
            'RTPS' => [
                'expected_prefixes' => ['RTPS', 'BR', 'BICCO', 'BCCCO', 'BRCCO', 'BICC', 'BIRC', 'BIIC', 'BINC', 'BIEW'],
                'conflicting_prefixes' => ['BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'RTPS General Certificate'
            ],
            'OTHER' => [
                'expected_prefixes' => [],
                'conflicting_prefixes' => [],
                'label' => 'Other Citizen Service'
            ],
            'JAAT' => [
                'expected_prefixes' => ['BCCCO', 'BCCC', 'JAAT', 'CST'],
                'conflicting_prefixes' => ['BICCO', 'BICC', 'BIRC', 'BRCCO', 'BIIC', 'BIEW', 'BINC', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'Caste Certificate (JAAT)'
            ],
            'NIWAS' => [
                'expected_prefixes' => ['BRCCO', 'BRCC', 'BIRC', 'NIWAS', 'RES'],
                'conflicting_prefixes' => ['BCCCO', 'BCCC', 'BIIC', 'BICCO', 'BICC', 'BIEW', 'BINC', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'Residence Certificate (NIWAS)'
            ],
            'AAY' => [
                'expected_prefixes' => ['BICCO', 'BIIC', 'BICC', 'AAY', 'INC'],
                'conflicting_prefixes' => ['BCCCO', 'BCCC', 'BIRC', 'BRCCO', 'BIEW', 'BINC', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'Income Certificate (AAY)'
            ],
            'EWS' => [
                'expected_prefixes' => ['BIEW', 'EWS'],
                'conflicting_prefixes' => ['BCCCO', 'BCCC', 'BIRC', 'BRCCO', 'BIIC', 'BICCO', 'BICC', 'BOCW', 'EPDS', 'LPC', 'MUT'],
                'label' => 'EWS Certificate (BIEW)'
            ],
            'NON_CREAMY' => [
                'expected_prefixes' => ['BINC', 'NCLCO', 'NCLC', 'BCOBC', 'OBC'],
                'conflicting_prefixes' => ['BCCCO', 'BCCC', 'BIRC', 'BRCCO', 'BIIC', 'BICCO', 'BICC', 'BIEW', 'BOCW', 'EPDS'],
                'label' => 'Non-Creamy Layer (NON_CREAMY)'
            ]
        ];

        if (isset($codeFormatRules[$code])) {
            $rule = $codeFormatRules[$code];

            // Check conflicting prefixes
            foreach ($rule['conflicting_prefixes'] as $cp) {
                if (str_starts_with($number, $cp . '/') || str_starts_with($number, $cp . '-') || str_starts_with($number, $cp . '_') || $number === $cp) {
                    throw new Exception("Application number prefix mismatch: '{$number}' begins with '{$cp}', but selected Code is '{$code}'. Please enter the correct application number or change the Code.");
                }
            }

            // If expected prefixes defined, ensure number starts with an expected prefix
            if (!empty($rule['expected_prefixes'])) {
                $matchedPrefix = false;
                foreach ($rule['expected_prefixes'] as $ep) {
                    if (str_starts_with($number, $ep . '/') || str_starts_with($number, $ep . '-') || str_starts_with($number, $ep . '_') || str_starts_with($number, $ep)) {
                        $matchedPrefix = true;
                        break;
                    }
                }
                if (!$matchedPrefix) {
                    $expectedPrefixList = implode(', ', $rule['expected_prefixes']);
                    throw new Exception("Application number '{$number}' does not match Code '{$code}'. Expected prefix: {$expectedPrefixList} (e.g. {$code}/2026/...).");
                }
            }
        }
    }


    /**
     * Retrieve chat messages for a specific application
     */
    public static function getChats(int $appId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, application_id, sender_id, sender_name, sender_role, message, attachment_url, is_read, created_at
            FROM application_chats
            WHERE application_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$appId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Post a new chat message for a request
     */
    public static function addChat(
        int $appId,
        int $senderId,
        string $senderName,
        string $senderRole,
        string $message,
        ?string $attachmentUrl = null
    ): array {
        $db = Database::getConnection();
        $ins = $db->prepare("
            INSERT INTO application_chats (application_id, sender_id, sender_name, sender_role, message, attachment_url, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $ins->execute([$appId, $senderId, $senderName, $senderRole, $message, $attachmentUrl]);
        $msgId = (int)$db->lastInsertId();

        return [
            'id' => $msgId,
            'application_id' => $appId,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'sender_role' => $senderRole,
            'message' => $message,
            'attachment_url' => $attachmentUrl,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}
