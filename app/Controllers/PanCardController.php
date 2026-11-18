<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Services\ApplicationCodeFieldService;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\WalletService;
use Exception;
use PDO;

class PanCardController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get Pan Card Data Type ID
     */
    private function getPanDataTypeId(): int
    {
        $stmt = $this->db->query("SELECT id FROM data_types WHERE code = 'pan_card' LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : 5;
    }

    /**
    /**
     * Retailer View: New Pan Card Apply Page (Form Only)
     */
    public function index(): void
    {
        if (!AuthService::check()) {
            $_SESSION['flash_error'] = 'Please log in to access New Pan Card Apply.';
            header('Location: /login');
            exit;
        }

        $user = AuthService::user();
        $userId = (int)$user['id'];
        $role = $user['role'];
        $activeMode = AuthService::activePortalMode();
        $isAdmin = in_array($role, ['ADMIN', 'SUPER_ADMIN'], true);
        $isOperator = ($role === 'OPERATOR' || $role === 'STAFF' || (AuthService::canAccessOperatorMode($userId) && $activeMode === 'OPERATOR'));
        $isStaffOrAdmin = $isAdmin || $isOperator;

        // Staff and Admin manage applications directly on the list page
        if ($isStaffOrAdmin) {
            header('Location: /services/pan-card/list');
            exit;
        }

        $panDataTypeId = $this->getPanDataTypeId();
        WalletService::getOrCreateWallet($userId);
        $walletBalance = WalletService::getBalance($userId);

        // Count user's previous requests for header badge
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM applications 
            WHERE user_id = :uid AND (application_code = 'PAN_CARD' OR data_type_id = :pan_dt_id)
        ");
        $countStmt->execute([':uid' => $userId, ':pan_dt_id' => $panDataTypeId]);
        $totalRequests = (int)$countStmt->fetchColumn();

        $viewMode = 'form';
        require dirname(__DIR__) . '/Views/user/pan_card.php';
    }

    /**
     * New Pan Card List Page (History & Cards for Retailers, Staff and Admins)
     */
    public function list(): void
    {
        if (!AuthService::check()) {
            $_SESSION['flash_error'] = 'Please log in to access Pan Card List.';
            header('Location: /login');
            exit;
        }

        $user = AuthService::user();
        $userId = (int)$user['id'];
        $role = $user['role'];
        $activeMode = AuthService::activePortalMode();
        $isAdmin = in_array($role, ['ADMIN', 'SUPER_ADMIN'], true);
        $isOperator = ($role === 'OPERATOR' || $role === 'STAFF' || (AuthService::canAccessOperatorMode($userId) && $activeMode === 'OPERATOR'));
        $isStaffOrAdmin = $isAdmin || $isOperator;

        $panDataTypeId = $this->getPanDataTypeId();
        WalletService::getOrCreateWallet($userId);
        $walletBalance = WalletService::getBalance($userId);

        $statusFilter = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
        $search = trim((string)($_GET['search'] ?? ''));

        // Query Previous Requests (with permissions)
        $where = ["(a.application_code = 'PAN_CARD' OR a.data_type_id = :pan_dt_id)"];
        $params = [':pan_dt_id' => $panDataTypeId];

        if ($isAdmin) {
            // Admin sees all applications
        } elseif ($isOperator) {
            $assignedCodes = AuthService::getOperatorAssignedCodes($userId);
            $hasPanAssigned = in_array('PAN', $assignedCodes, true) || in_array('PAN_CARD', $assignedCodes, true);
            $assignedBlocks = AuthService::getOperatorAssignedBlocks($userId);
            if (!$hasPanAssigned || empty($assignedBlocks)) {
                $where[] = "1=0";
            } else {
                $bIds = array_column($assignedBlocks, 'block_id');
                $placeholders = implode(',', array_map('intval', $bIds));
                $where[] = "(a.block_id IN ({$placeholders}) AND (a.assigned_operator_id = {$userId} OR a.assigned_operator_id IS NULL))";
            }
        } elseif ($role === 'DISTRIBUTOR') {
            $where[] = "a.distributor_id = :dist_id";
            $params[':dist_id'] = $userId;
        } else {
            $where[] = "a.user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        if ($statusFilter === 'PENDING') {
            $where[] = "a.status IN ('PENDING', 'IN_PROCESS', 'UNDER_REVIEW')";
        } elseif ($statusFilter === 'COMPLETED' || $statusFilter === 'APPROVED') {
            $where[] = "a.status IN ('COMPLETED', 'APPROVED')";
        } elseif ($statusFilter === 'REJECTED') {
            $where[] = "a.status = 'REJECTED'";
        }

        if ($search !== '') {
            $where[] = "(a.app_ref LIKE :search OR a.applicant_name LIKE :search OR a.applicant_identifier LIKE :search OR a.applicant_mobile LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT a.*, u.name as retailer_name, u.mobile as retailer_mobile,
                   d.name as district_name, b.name as block_name
            FROM applications a
            LEFT JOIN users u ON u.id = a.user_id
            LEFT JOIN districts d ON d.id = a.district_id
            LEFT JOIN blocks b ON b.id = a.block_id
            WHERE {$whereSql}
            ORDER BY a.id DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch dynamic field values for each application
        if (!empty($requests)) {
            $appIds = array_column($requests, 'id');
            $placeholders = implode(',', array_fill(0, count($appIds), '?'));
            $fvStmt = $this->db->prepare("
                SELECT application_id, field_name, field_value
                FROM application_field_values
                WHERE application_id IN ({$placeholders})
            ");
            $fvStmt->execute($appIds);
            $fieldRows = $fvStmt->fetchAll(PDO::FETCH_ASSOC);

            $fieldsByApp = [];
            foreach ($fieldRows as $fr) {
                $fieldsByApp[$fr['application_id']][$fr['field_name']] = $fr['field_value'];
            }

            foreach ($requests as &$req) {
                $req['fields'] = $fieldsByApp[$req['id']] ?? [];
            }
            unset($req);
        }

        // Count totals for filter tabs
        $countParams = [':pan_dt_id' => $panDataTypeId];
        $countUserWhere = "";
        if ($isAdmin) {
            // No restriction
        } elseif ($isOperator) {
            $assignedCodes = AuthService::getOperatorAssignedCodes($userId);
            $hasPanAssigned = in_array('PAN', $assignedCodes, true) || in_array('PAN_CARD', $assignedCodes, true);
            $assignedBlocks = AuthService::getOperatorAssignedBlocks($userId);
            if (!$hasPanAssigned || empty($assignedBlocks)) {
                $countUserWhere = " AND 1=0";
            } else {
                $bIds = array_column($assignedBlocks, 'block_id');
                $placeholders = implode(',', array_map('intval', $bIds));
                $countUserWhere = " AND (a.block_id IN ({$placeholders}) AND (a.assigned_operator_id = {$userId} OR a.assigned_operator_id IS NULL))";
            }
        } elseif ($role === 'DISTRIBUTOR') {
            $countUserWhere = " AND a.distributor_id = :dist_id";
            $countParams[':dist_id'] = $userId;
        } else {
            $countUserWhere = " AND a.user_id = :user_id";
            $countParams[':user_id'] = $userId;
        }

        $countStmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN a.status IN ('PENDING', 'IN_PROCESS', 'UNDER_REVIEW') THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN a.status IN ('COMPLETED', 'APPROVED') THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN a.status = 'REJECTED' THEN 1 ELSE 0 END) as rejected_count
            FROM applications a
            WHERE (a.application_code = 'PAN_CARD' OR a.data_type_id = :pan_dt_id) {$countUserWhere}
        ");
        $countStmt->execute($countParams);
        $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalRequests = (int)($counts['total_count'] ?? 0);
        $pendingCount = (int)($counts['pending_count'] ?? 0);
        $completedCount = (int)($counts['completed_count'] ?? 0);
        $rejectedCount = (int)($counts['rejected_count'] ?? 0);

        $viewMode = 'list';
        require dirname(__DIR__) . '/Views/user/pan_card_list.php';
    }

    /**
     * Submit New Pan Card Application (Atomically debits ₹107.00 and uploads documents)
     */
    public function apply(): void
    {
        $isJson = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                  str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
                  !empty($_POST['ajax']);

        try {
            if (!AuthService::check()) {
                throw new Exception('Please log in to submit application.');
            }

            if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token (CSRF). Please refresh the page and try again.');
            }

            $user = AuthService::user();
            $userId = (int)$user['id'];
            $panDtRow = $this->db->query("SELECT id, fee_amount, distributor_commission, is_active FROM data_types WHERE code = 'pan_card' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($panDtRow && (int)($panDtRow['is_active'] ?? 1) === 0) {
                throw new Exception('PAN Card service is currently disabled by administrator.');
            }
            $panFee = ($panDtRow && (float)$panDtRow['fee_amount'] > 0) ? (float)$panDtRow['fee_amount'] : 107.00;
            $distCommission = ($panDtRow && isset($panDtRow['distributor_commission'])) ? (float)$panDtRow['distributor_commission'] : 7.00;
            $panDataTypeId = $panDtRow ? (int)$panDtRow['id'] : $this->getPanDataTypeId();

            // 1. Sanitize text fields
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $gender = trim((string)($_POST['gender'] ?? ''));
            $dob = trim((string)($_POST['dob'] ?? ''));
            $state = trim((string)($_POST['state'] ?? 'Bihar'));
            $aadhaarNo = preg_replace('/\D/', '', (string)($_POST['aadhaar_no'] ?? ''));
            $mobileNo = preg_replace('/\D/', '', (string)($_POST['mobile_no'] ?? ''));
            $emailId = strtolower(trim((string)($_POST['email_id'] ?? '')));
            $fatherName = trim((string)($_POST['father_name'] ?? ''));
            $motherName = trim((string)($_POST['mother_name'] ?? ''));
            $husbandName = trim((string)($_POST['husband_name'] ?? ''));
            $village = trim((string)($_POST['village'] ?? ''));
            $postOffice = trim((string)($_POST['post_office'] ?? ''));
            $blockName = trim((string)($_POST['block_name'] ?? ''));
            $districtName = trim((string)($_POST['district_name'] ?? ''));
            $pincode = preg_replace('/\D/', '', (string)($_POST['pincode'] ?? ''));

            // 2. Validate mandatory fields
            if (mb_strlen($fullName) < 2) {
                throw new Exception('FULL NAME is required (minimum 2 characters).');
            }
            if (!in_array($gender, ['Male', 'Female', 'Transgender'], true)) {
                throw new Exception('Please select a valid GENDER (Male, Female, or Transgender).');
            }
            if (empty($dob)) {
                throw new Exception('DATE OF BIRTH is required.');
            }
            if (empty($state)) {
                throw new Exception('Please select STATE.');
            }
            if (strlen($aadhaarNo) !== 12) {
                throw new Exception('AADHAAR NO must be exactly 12 digits.');
            }
            if (strlen($mobileNo) !== 10) {
                throw new Exception('MOBILE NO must be exactly 10 digits.');
            }
            if (empty($emailId) || !filter_var($emailId, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Valid EMAIL ID is required to deliver E-Pan Card.');
            }
            if (mb_strlen($fatherName) < 2) {
                throw new Exception('FATHER NAME is required.');
            }
            if (mb_strlen($motherName) < 2) {
                throw new Exception('MOTHER NAME is required.');
            }
            if (empty($village)) {
                throw new Exception('VILLAGE is required.');
            }
            if (empty($postOffice)) {
                throw new Exception('POST OFFICE is required.');
            }
            if (empty($blockName)) {
                throw new Exception('BLOCK NAME is required.');
            }
            if (empty($districtName)) {
                throw new Exception('DISTRICT NAME is required.');
            }
            if (strlen($pincode) !== 6) {
                throw new Exception('PINCODE must be exactly 6 digits.');
            }

            // 3. Process File Uploads (Photo, Signature, Aadhar Card, Pan Form)
            $trackedFiles = [];
            $photoPath = $this->saveUploadedDocument('photo', 'Photo', ['jpg', 'jpeg', 'png', 'pdf'], 2, $trackedFiles);
            $sigPath = $this->saveUploadedDocument('signature', 'Signature', ['jpg', 'jpeg', 'png', 'pdf'], 2, $trackedFiles);
            $aadharPath = $this->saveUploadedDocument('aadhar_card', 'Aadhar Card', ['pdf', 'jpg', 'jpeg', 'png'], 2, $trackedFiles);
            $panFormPath = $this->saveUploadedDocument('pan_form', 'PAN Form', ['pdf', 'jpg', 'jpeg', 'png'], 2, $trackedFiles);

            if (empty($photoPath)) throw new Exception('PHOTO is required (JPG, PNG, PDF max 2MB).');
            if (empty($sigPath)) throw new Exception('SIGNATURE is required (JPG, PNG, PDF max 2MB).');
            if (empty($aadharPath)) throw new Exception('AADHAR CARD is required (PDF, JPG, PNG max 2MB).');
            if (empty($panFormPath)) throw new Exception('PAN FORM is required (PDF, JPG, PNG max 2MB).');

            // 4. Wallet Balance Verification
            $isAdmin = in_array($user['role'] ?? '', ['ADMIN', 'SUPER_ADMIN'], true);
            $currentBalance = WalletService::getBalance($userId);
            if (!$isAdmin && $currentBalance < $panFee) {
                throw new Exception("Insufficient wallet balance (Available: ₹" . number_format($currentBalance, 2) . ", Required: ₹" . number_format($panFee, 2) . "). Please recharge your wallet first.");
            }

            // 5. Begin DB Transaction
            $this->db->beginTransaction();

            $appRef = 'WB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $parentDistributorId = !empty($user['parent_distributor_id']) ? (int)$user['parent_distributor_id'] : null;

            // Debit Wallet (Admins test for free without wallet deduction)
            if (!$isAdmin) {
                $debitRes = WalletService::debit(
                    $userId,
                    $panFee,
                    'DEBIT',
                    'APPLICATION',
                    $appRef,
                    'PANCARD_DEBIT_' . $appRef . '_' . time(),
                    "New Pan Card Application Fee (Ref: {$appRef})",
                    "नया पैन कार्ड आवेदन शुल्क (Ref: {$appRef})",
                    "Pan card application for {$fullName} ({$aadhaarNo})",
                    $userId
                );
            }

            // Resolve district_id and block_id (required by applications schema)
            $resolvedDistId = null;
            if (!empty($districtName)) {
                $dStmt = $this->db->prepare("SELECT id FROM districts WHERE name LIKE ? OR name_hi LIKE ? LIMIT 1");
                $dStmt->execute(['%' . $districtName . '%', '%' . $districtName . '%']);
                $resolvedDistId = $dStmt->fetchColumn() ?: null;
            }
            if (!$resolvedDistId && !empty($user['profile_district_id'])) {
                $resolvedDistId = (int)$user['profile_district_id'];
            }
            if (!$resolvedDistId) {
                $resolvedDistId = (int)($this->db->query("SELECT id FROM districts LIMIT 1")->fetchColumn() ?: 1);
            }

            $resolvedBlockId = null;
            if (!empty($blockName) && $resolvedDistId) {
                $bStmt = $this->db->prepare("SELECT id FROM blocks WHERE district_id = ? AND (name LIKE ? OR name_hi LIKE ?) LIMIT 1");
                $bStmt->execute([$resolvedDistId, '%' . $blockName . '%', '%' . $blockName . '%']);
                $resolvedBlockId = $bStmt->fetchColumn() ?: null;
            }
            if (!$resolvedBlockId && !empty($blockName)) {
                $bAnyStmt = $this->db->prepare("SELECT id, district_id FROM blocks WHERE name LIKE ? OR name_hi LIKE ? LIMIT 1");
                $bAnyStmt->execute(['%' . $blockName . '%', '%' . $blockName . '%']);
                $bRow = $bAnyStmt->fetch(PDO::FETCH_ASSOC);
                if ($bRow) {
                    $resolvedBlockId = (int)$bRow['id'];
                    $resolvedDistId = (int)$bRow['district_id'];
                }
            }
            if (!$resolvedBlockId && !empty($user['profile_block_id'])) {
                $resolvedBlockId = (int)$user['profile_block_id'];
            }
            if (!$resolvedBlockId) {
                $bFallback = $this->db->prepare("SELECT id FROM blocks WHERE district_id = ? LIMIT 1");
                $bFallback->execute([$resolvedDistId]);
                $resolvedBlockId = (int)($bFallback->fetchColumn() ?: 1);
            }

            // Resolve assigned operator via StaffRoutingService
            $assignedOperatorId = \App\Services\StaffRoutingService::resolveOperatorForApplication(
                $resolvedDistId,
                $resolvedBlockId,
                'PAN_CARD',
                $this->db
            );
            $assignmentMethod = $assignedOperatorId ? 'AUTO' : 'UNASSIGNED';

            // Insert into applications table
            $insApp = $this->db->prepare("
                INSERT INTO applications (
                    app_ref, user_id, distributor_id, data_type_id, application_code,
                    assigned_operator_id, assigned_at, assignment_method,
                    district_id, block_id, applicant_name, applicant_mobile, applicant_identifier,
                    fee_debited, commission_credited, status, remarks, is_locked, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, 'PAN_CARD',
                    ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, 'PENDING', ?, 1, NOW(), NOW()
                )
            ");
            $insApp->execute([
                $appRef,
                $userId,
                $parentDistributorId,
                $panDataTypeId,
                $assignedOperatorId,
                $assignedOperatorId ? date('Y-m-d H:i:s') : null,
                $assignmentMethod,
                $resolvedDistId,
                $resolvedBlockId,
                $fullName,
                $mobileNo,
                $aadhaarNo,
                $panFee,
                $parentDistributorId ? $distCommission : 0.00,
                "New Pan Card Apply (Sirf Aadhaar) - Village: {$village}, PO: {$postOffice}, Block: {$blockName}, Dist: {$districtName}, PIN: {$pincode}"
            ]);
            $appId = (int)$this->db->lastInsertId();

            if ($assignedOperatorId) {
                \App\Services\StaffRoutingService::recordAssignment(
                    $appId,
                    $assignedOperatorId,
                    'AUTO',
                    'Automatic staff routing on new Pan Card application submission',
                    $userId,
                    $this->db
                );
            }

            // Prepare all 19 field values to insert
            $fieldData = [
                'full_name' => $fullName,
                'gender' => $gender,
                'dob' => $dob,
                'state' => $state,
                'aadhaar_no' => $aadhaarNo,
                'mobile_no' => $mobileNo,
                'email_id' => $emailId,
                'father_name' => $fatherName,
                'mother_name' => $motherName,
                'husband_name' => $husbandName,
                'village' => $village,
                'post_office' => $postOffice,
                'block_name' => $blockName,
                'district_name' => $districtName,
                'pincode' => $pincode,
                'photo' => $photoPath,
                'signature' => $sigPath,
                'aadhar_card' => $aadharPath,
                'pan_form' => $panFormPath
            ];

            // Fetch code_field_ids for PAN_CARD
            $cfStmt = $this->db->prepare("
                SELECT id, field_name FROM application_code_fields
                WHERE application_code = 'PAN_CARD'
            ");
            $cfStmt->execute();
            $codeFieldMap = $cfStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $insFv = $this->db->prepare("
                INSERT INTO application_field_values (application_id, field_id, code_field_id, field_name, field_value, created_at)
                VALUES (?, NULL, ?, ?, ?, NOW())
            ");

            foreach ($fieldData as $fKey => $fVal) {
                $cfId = $codeFieldMap[$fKey] ?? null;
                $insFv->execute([$appId, $cfId, $fKey, (string)$fVal]);
            }

            // Insert initial status history
            $this->db->prepare("
                INSERT INTO application_status_history (
                    application_id, from_status, to_status, actor_user_id, actor_name, actor_role, remarks, created_at
                ) VALUES (
                    ?, 'NONE', 'PENDING', ?, ?, ?, 'New Pan Card application submitted successfully. Pending operator verification.', NOW()
                )
            ")->execute([$appId, $userId, $user['name'] ?? 'User', $user['role'] ?? 'USER']);

            // Audit
            AuditService::log('PANCARD_APPLICATION_SUBMITTED', 'APPLICATION', (string)$appId, [
                'app_ref' => $appRef,
                'applicant_name' => $fullName,
                'aadhaar_no' => $aadhaarNo,
                'mobile_no' => $mobileNo,
                'fee_debited' => $panFee
            ], $userId, $user['role']);

            $this->db->commit();

            $newBal = WalletService::getBalance($userId);

            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'app_id' => $appId,
                    'app_ref' => $appRef,
                    'new_balance' => $newBal,
                    'redirect_url' => '/services/pan-card/list',
                    'message' => "Pan Card application #{$appRef} submitted successfully! ₹" . number_format($panFee, 2) . " debited from wallet."
                ]);
                exit;
            }

            $_SESSION['flash_success'] = "Pan Card application #{$appRef} submitted successfully! ₹" . number_format($panFee, 2) . " debited from wallet.";
            header('Location: /services/pan-card/list');
            exit;

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            // Clean up stored files if any
            if (!empty($trackedFiles)) {
                ApplicationCodeFieldService::cleanupTrackedFiles($trackedFiles);
            }

            if ($isJson) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
                exit;
            }

            $_SESSION['flash_error'] = 'Application failed: ' . $e->getMessage();
            header('Location: /services/pan-card');
            exit;
        }
    }

    /**
     * Helper to safely save an uploaded document (file or base64)
     */
    private function saveUploadedDocument(string $inputName, string $label, array $allowedExts, int $maxMb, array &$trackedFiles): ?string
    {
        $file = $_FILES[$inputName] ?? null;

        // Check if raw file was passed directly
        if (!empty($file) && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $origName = (string)$file['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts, true)) {
                throw new Exception("Invalid file extension '.{$ext}' for {$label}. Allowed: " . implode(', ', $allowedExts));
            }

            if ($file['size'] > ($maxMb * 1024 * 1024)) {
                throw new Exception("File {$label} exceeds the maximum size limit of {$maxMb}MB.");
            }

            $baseDir = dirname(__DIR__, 2);
            $relFolder = 'uploads/pancard/' . date('Y/m');
            $pubDir = $baseDir . '/public/' . $relFolder;
            $storDir = $baseDir . '/storage/' . $relFolder;
            $safeDir = 'C:/Users/satis/welcome_bihar_uploads/pancard/' . date('Y/m');

            if (!is_dir($safeDir)) @mkdir($safeDir, 0777, true);
            if (!is_dir($pubDir)) @mkdir($pubDir, 0755, true);
            if (!is_dir($storDir)) @mkdir($storDir, 0755, true);

            $fileName = $inputName . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
            $pubPath = $pubDir . '/' . $fileName;
            $storPath = $storDir . '/' . $fileName;
            $safePath = $safeDir . '/' . $fileName;

            $uploaded = false;
            // 1. Write to safe directory on C: (fast, 100% reliable)
            if (@copy($file['tmp_name'], $safePath)) {
                $uploaded = true;
                $trackedFiles[] = $safePath;
            }

            // 2. Also try to write to public and storage if accessible
            if (@move_uploaded_file($file['tmp_name'], $pubPath)) {
                $uploaded = true;
                $trackedFiles[] = $pubPath;
                @copy($pubPath, $storPath);
                $trackedFiles[] = $storPath;
            } elseif ($uploaded) {
                @copy($safePath, $pubPath);
                @copy($safePath, $storPath);
            }

            if (!$uploaded) {
                throw new Exception("Failed to upload {$label}.");
            }

            return '/' . $relFolder . '/' . $fileName;
        }

        // Check if passed as base64 data URI (Drag & Drop or Canvas)
        $rawBase64 = trim((string)($_POST[$inputName . '_base64'] ?? ($_POST[$inputName] ?? '')));
        if (str_starts_with($rawBase64, 'data:')) {
            if (preg_match('/^data:([^;]+);(?:[^;]+;)*base64,(.+)$/', $rawBase64, $matches)) {
                $mimeType = strtolower(trim($matches[1]));
                $decoded = base64_decode($matches[2], true);
                if ($decoded !== false && strlen($decoded) > 0) {
                    if (strlen($decoded) > ($maxMb * 1024 * 1024)) {
                        throw new Exception("File {$label} exceeds maximum allowed size of {$maxMb}MB.");
                    }

                    $extMap = [
                        'application/pdf' => 'pdf',
                        'image/jpeg' => 'jpg',
                        'image/jpg' => 'jpg',
                        'image/png' => 'png'
                    ];
                    $ext = $extMap[$mimeType] ?? 'pdf';

                    $baseDir = dirname(__DIR__, 2);
                    $relFolder = 'uploads/pancard/' . date('Y/m');
                    $pubDir = $baseDir . '/public/' . $relFolder;
                    $storDir = $baseDir . '/storage/' . $relFolder;
                    $safeDir = 'C:/Users/satis/welcome_bihar_uploads/pancard/' . date('Y/m');

                    if (!is_dir($safeDir)) @mkdir($safeDir, 0777, true);
                    if (!is_dir($pubDir)) @mkdir($pubDir, 0755, true);
                    if (!is_dir($storDir)) @mkdir($storDir, 0755, true);

                    $fileName = $inputName . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
                    $pubPath = $pubDir . '/' . $fileName;
                    $storPath = $storDir . '/' . $fileName;
                    $safePath = $safeDir . '/' . $fileName;

                    $written = false;
                    if (@file_put_contents($safePath, $decoded) !== false) {
                        $written = true;
                        $trackedFiles[] = $safePath;
                    }
                    if (@file_put_contents($pubPath, $decoded) !== false) {
                        $written = true;
                        $trackedFiles[] = $pubPath;
                        @copy($pubPath, $storPath);
                        $trackedFiles[] = $storPath;
                    }

                    if (!$written) {
                        throw new Exception("Failed to save {$label}.");
                    }

                    return '/' . $relFolder . '/' . $fileName;
                }
            }
        }

        return null;
    }

    /**
     * UTI / NSDL PSA Crop & Level Processing Endpoint
     * GET/POST /api/uti-crop?type=photo|signature&url=/uploads/...&applicant=Name
     */
    public function utiCrop(): void
    {
        if (!AuthService::check()) {
            http_response_code(401);
            echo "Unauthorized access.";
            exit;
        }

        $type = strtolower(trim((string)($_GET['type'] ?? 'photo')));
        $rawUrl = trim((string)($_GET['url'] ?? ''));
        $applicant = trim((string)($_GET['applicant'] ?? 'Applicant'));
        $safeApplicant = preg_replace('/[^a-zA-Z0-9_-]/', '_', $applicant) ?: 'Applicant';
        $whiteLevel = max(100, min(250, (int)($_GET['white_level'] ?? 195)));
        $darkLevel = max(10, min(150, (int)($_GET['dark_level'] ?? 80)));

        if ($rawUrl === '') {
            http_response_code(400);
            echo "Missing image URL.";
            exit;
        }

        // Resolve local file path if relative or local URL
        $baseDir = dirname(__DIR__, 2);
        $localPath = null;

        if (str_starts_with($rawUrl, '/uploads/')) {
            $rel = substr($rawUrl, strlen('/uploads/'));
            $candidateSafe = 'C:/Users/satis/welcome_bihar_uploads/' . $rel;
            $candidate1 = $baseDir . '/public' . $rawUrl;
            $candidate2 = $baseDir . '/storage' . $rawUrl;
            if (@file_exists($candidateSafe) && @is_file($candidateSafe)) {
                $localPath = $candidateSafe;
            } elseif (@file_exists($candidate1) && @is_file($candidate1)) {
                $localPath = $candidate1;
            } elseif (@file_exists($candidate2) && @is_file($candidate2)) {
                $localPath = $candidate2;
            }
        } elseif (preg_match('#^https?://#i', $rawUrl)) {
            $parsed = parse_url($rawUrl, PHP_URL_PATH);
            if ($parsed && str_starts_with($parsed, '/uploads/')) {
                $rel = substr($parsed, strlen('/uploads/'));
                $candidateSafe = 'C:/Users/satis/welcome_bihar_uploads/' . $rel;
                $candidate1 = $baseDir . '/public' . $parsed;
                $candidate2 = $baseDir . '/storage' . $parsed;
                if (@file_exists($candidateSafe) && @is_file($candidateSafe)) {
                    $localPath = $candidateSafe;
                } elseif (@file_exists($candidate1) && @is_file($candidate1)) {
                    $localPath = $candidate1;
                } elseif (@file_exists($candidate2) && @is_file($candidate2)) {
                    $localPath = $candidate2;
                }
            }
        }

        $imgData = null;
        if ($localPath && file_exists($localPath)) {
            $imgData = @file_get_contents($localPath);
        } else {
            if (str_starts_with($rawUrl, 'data:')) {
                if (preg_match('/^data:image\/[a-z0-9]+;base64,(.+)$/i', $rawUrl, $m)) {
                    $imgData = base64_decode($m[1]);
                }
            } elseif (preg_match('#^https?://#i', $rawUrl)) {
                $ch = curl_init($rawUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0
                ]);
                $imgData = curl_exec($ch);
                curl_close($ch);
            }
        }

        if (!$imgData) {
            http_response_code(404);
            echo "Image file could not be found or read.";
            exit;
        }

        if (!extension_loaded('gd')) {
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="UTI_' . ucfirst($type) . '_' . $safeApplicant . '.jpg"');
            echo $imgData;
            exit;
        }

        $srcImg = @imagecreatefromstring($imgData);
        if (!$srcImg) {
            http_response_code(422);
            echo "Unsupported or corrupted image data.";
            exit;
        }

        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        if ($type === 'signature') {
            // UTI PSA Signature: 400x200 px, 600 DPI, < 60 KB, Level Enhanced (Darsh kam, Brith karo)
            $targetW = 400;
            $targetH = 200;
            $dpi = 600;
            $maxKb = 60;
            $filename = "UTI_Signature_{$safeApplicant}_400x200_600dpi_Fresh.jpg";

            $tempImg = imagecreatetruecolor($srcW, $srcH);
            imagecopy($tempImg, $srcImg, 0, 0, 0, 0, $srcW, $srcH);

            for ($y = 0; $y < $srcH; $y++) {
                for ($x = 0; $x < $srcW; $x++) {
                    $rgb = imagecolorat($tempImg, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;

                    if ($lum >= $whiteLevel) {
                        imagesetpixel($tempImg, $x, $y, 0xFFFFFF);
                    } elseif ($lum <= $darkLevel) {
                        imagesetpixel($tempImg, $x, $y, 0x000000);
                    } else {
                        $t = ($lum - $darkLevel) / ($whiteLevel - $darkLevel);
                        $c = (int)round(pow($t, 1.45) * 255);
                        $c = max(0, min(255, $c));
                        $col = ($c << 16) | ($c << 8) | $c;
                        imagesetpixel($tempImg, $x, $y, $col);
                    }
                }
            }

            $dest = imagecreatetruecolor($targetW, $targetH);
            $bgWhite = imagecolorallocate($dest, 255, 255, 255);
            imagefilledrectangle($dest, 0, 0, $targetW, $targetH, $bgWhite);

            // Direct stretch full signature without cropping
            imagecopyresampled($dest, $tempImg, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
            imagedestroy($tempImg);
            imagedestroy($srcImg);
        } else {
            // UTI PSA Photo: 213x213 px, 300 DPI, < 30 KB
            $targetW = 213;
            $targetH = 213;
            $dpi = 300;
            $maxKb = 30;
            $filename = "UTI_Photo_{$safeApplicant}_213x213_300dpi.jpg";

            $dest = imagecreatetruecolor($targetW, $targetH);
            $bgWhite = imagecolorallocate($dest, 255, 255, 255);
            imagefilledrectangle($dest, 0, 0, $targetW, $targetH, $bgWhite);

            // Direct stretch full photo without cropping
            imagecopyresampled($dest, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
            imagedestroy($srcImg);
        }

        if (function_exists('imageresolution')) {
            @imageresolution($dest, $dpi, $dpi);
        }

        $quality = 92;
        $jpegData = '';
        while ($quality >= 45) {
            ob_start();
            imagejpeg($dest, null, $quality);
            $jpegData = ob_get_clean();
            if (strlen($jpegData) / 1024 < $maxKb) {
                break;
            }
            $quality -= 8;
        }
        imagedestroy($dest);

        $finalData = $this->injectJfifDpi($jpegData, $dpi);

        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($finalData));
        header('Cache-Control: public, max-age=86400');
        echo $finalData;
        exit;
    }

    /**
     * Inject JFIF APP0 Segment with specified DPI (300 or 600)
     */
    private function injectJfifDpi(string $jpegData, int $dpi): string
    {
        if (strlen($jpegData) < 4 || substr($jpegData, 0, 2) !== "\xFF\xD8") {
            return $jpegData;
        }

        if (substr($jpegData, 2, 2) === "\xFF\xE0" && strlen($jpegData) >= 18) {
            $jpegData[13] = "\x01";
            $jpegData[14] = chr(($dpi >> 8) & 0xFF);
            $jpegData[15] = chr($dpi & 0xFF);
            $jpegData[16] = chr(($dpi >> 8) & 0xFF);
            $jpegData[17] = chr($dpi & 0xFF);
            return $jpegData;
        }

        $app0 = "\xFF\xE0\x00\x10JFIF\x00\x01\x02\x01" 
              . chr(($dpi >> 8) & 0xFF) . chr($dpi & 0xFF) 
              . chr(($dpi >> 8) & 0xFF) . chr($dpi & 0xFF) 
              . "\x00\x00";

        return substr($jpegData, 0, 2) . $app0 . substr($jpegData, 2);
    }

    /**
     * PAN Form Auto Processor (Photo, Signature & PDF Merger < 2 MB)
     * Strictly restricted to Administrators only
     */
    public function processor(): void
    {
        if (!AuthService::check()) {
            $_SESSION['flash_error'] = 'Please log in to access PAN Form Auto Processor.';
            header('Location: /login');
            exit;
        }

        $user = AuthService::user();
        $role = $user['role'] ?? 'USER';
        if (!in_array($role, ['ADMIN', 'SUPER_ADMIN'], true)) {
            $_SESSION['flash_error'] = 'Access denied. PAN Form Auto Processor is available only for Administrators.';
            header('Location: /dashboard');
            exit;
        }

        require dirname(__DIR__) . '/Views/user/pan_processor.php';
    }

    /**
     * Send CORS headers for Chrome Extension Worker API
     */
    private function sendCorsHeaders(string $methods = 'GET, POST, OPTIONS'): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: ' . $methods);
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Worker-Key');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * GET /api/pan-card/queue - Pending PAN Card applications for UTI Auto-Pilot Extension
     */
    public function apiQueue(): void
    {
        $this->sendCorsHeaders('GET, OPTIONS');

        try {
            $panDataTypeId = $this->getPanDataTypeId();
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
            $statusParam = strtoupper(trim((string)($_GET['status'] ?? '')));
            $searchParam = trim((string)($_GET['search'] ?? ''));

            $where = ["(a.application_code = 'PAN_CARD' OR a.data_type_id = :pan_dt_id)"];
            $params = [':pan_dt_id' => $panDataTypeId];

            if ($statusParam === 'ALL') {
                // Return any status (useful for document upload lookup)
            } elseif ($statusParam === 'COMPLETED') {
                $where[] = "a.status = 'COMPLETED'";
            } elseif ($statusParam !== '') {
                $where[] = "a.status = :status";
                $params[':status'] = $statusParam;
            } else {
                $where[] = "a.status IN ('PENDING', 'IN_PROCESS', 'UNDER_REVIEW')";
            }

            if ($searchParam !== '') {
                $where[] = "(a.applicant_name LIKE :s1 OR a.app_ref LIKE :s2 OR a.applicant_identifier LIKE :s3 OR a.applicant_mobile LIKE :s4 OR a.remarks LIKE :s5)";
                $params[':s1'] = '%' . $searchParam . '%';
                $params[':s2'] = '%' . $searchParam . '%';
                $params[':s3'] = '%' . $searchParam . '%';
                $params[':s4'] = '%' . $searchParam . '%';
                $params[':s5'] = '%' . $searchParam . '%';
            }

            $order = ($statusParam === 'ALL' || $statusParam === 'COMPLETED' || $searchParam !== '')
                ? "ORDER BY a.updated_at DESC, a.id DESC"
                : "ORDER BY a.id ASC";

            $whereSql = implode(' AND ', $where);

            $stmt = $this->db->prepare("
                SELECT a.id, a.app_ref, a.user_id, a.applicant_name, a.applicant_mobile,
                       a.applicant_identifier, a.status, a.created_at, a.remarks,
                       d.name as district_name, b.name as block_name
                FROM applications a
                LEFT JOIN districts d ON d.id = a.district_id
                LEFT JOIN blocks b ON b.id = a.block_id
                WHERE {$whereSql}
                {$order}
                LIMIT {$limit}
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $fieldsByApp = [];
            if (!empty($rows)) {
                $appIds = array_column($rows, 'id');
                $placeholders = implode(',', array_fill(0, count($appIds), '?'));
                $fvStmt = $this->db->prepare("
                    SELECT application_id, field_name, field_value
                    FROM application_field_values
                    WHERE application_id IN ({$placeholders})
                ");
                $fvStmt->execute($appIds);
                foreach ($fvStmt->fetchAll(PDO::FETCH_ASSOC) as $fr) {
                    $fieldsByApp[(int)$fr['application_id']][$fr['field_name']] = $fr['field_value'];
                }
            }

            $baseUrl = \App\Config\Config::baseUrl();
            $queue = [];
            foreach ($rows as $r) {
                $appId = (int)$r['id'];
                $f = $fieldsByApp[$appId] ?? [];
                $fullName = trim((string)($f['full_name'] ?? $r['applicant_name'] ?? ''));

                $toAbsUrl = static function (?string $rel) use ($baseUrl): ?string {
                    if (!$rel) return null;
                    if (preg_match('#^https?://#i', $rel)) return $rel;
                    return $baseUrl . '/' . ltrim($rel, '/');
                };

                $queue[] = [
                    'id' => $appId,
                    'app_ref' => $r['app_ref'],
                    'status' => $r['status'],
                    'created_at' => $r['created_at'],
                    'full_name' => $fullName,
                    'gender' => $f['gender'] ?? '',
                    'dob' => $f['dob'] ?? '',
                    'state' => $f['state'] ?? 'Bihar',
                    'aadhaar_no' => $f['aadhaar_no'] ?? $r['applicant_identifier'] ?? '',
                    'mobile_no' => $f['mobile_no'] ?? $r['applicant_mobile'] ?? '',
                    'email_id' => $f['email_id'] ?? '',
                    'father_name' => $f['father_name'] ?? '',
                    'mother_name' => $f['mother_name'] ?? '',
                    'husband_name' => $f['husband_name'] ?? '',
                    'village' => $f['village'] ?? '',
                    'post_office' => $f['post_office'] ?? '',
                    'block_name' => $f['block_name'] ?? $r['block_name'] ?? '',
                    'district_name' => $f['district_name'] ?? $r['district_name'] ?? '',
                    'pincode' => $f['pincode'] ?? '',
                    'photo_url' => $toAbsUrl($f['photo'] ?? null),
                    'signature_url' => $toAbsUrl($f['signature'] ?? null),
                    'aadhar_card_url' => $toAbsUrl($f['aadhar_card'] ?? null),
                    'pan_form_url' => $toAbsUrl($f['pan_form'] ?? null),
                ];
            }

            echo json_encode([
                'success' => true,
                'count' => count($queue),
                'data' => $queue,
            ], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Resolve application ID from id or app_ref or candidate attributes
     */
    private function resolvePanAppId(array $payload): int
    {
        $appId = (int)($payload['app_id'] ?? $payload['id'] ?? 0);
        if ($appId > 0) return $appId;

        $appRef = trim((string)($payload['app_ref'] ?? $payload['appRef'] ?? $payload['ref'] ?? ''));
        if ($appRef !== '') {
            $stmt = $this->db->prepare("SELECT id FROM applications WHERE app_ref = ? LIMIT 1");
            $stmt->execute([$appRef]);
            $found = (int)$stmt->fetchColumn();
            if ($found > 0) return $found;

            $stmt = $this->db->prepare("SELECT id FROM applications WHERE app_ref LIKE ? LIMIT 1");
            $stmt->execute(['%' . $appRef . '%']);
            $found = (int)$stmt->fetchColumn();
            if ($found > 0) return $found;
        }

        // Match by 10-digit mobile number
        $mob = preg_replace('/\D/', '', (string)($payload['mobile_no'] ?? $payload['mobile'] ?? ''));
        if ($mob !== '' && strlen($mob) >= 10) {
            $mob10 = substr($mob, -10);
            $stmt = $this->db->prepare("SELECT a.id FROM applications a JOIN application_field_values f ON f.application_id = a.id WHERE a.status = 'PENDING' AND f.field_name = 'mobile_no' AND f.field_value LIKE ? ORDER BY a.id DESC LIMIT 1");
            $stmt->execute(['%' . $mob10 . '%']);
            $found = (int)$stmt->fetchColumn();
            if ($found > 0) return $found;
        }

        // Match by Aadhaar last 4 digits
        $aadhaar = preg_replace('/\D/', '', (string)($payload['aadhaar_no'] ?? $payload['aadhaar'] ?? ''));
        if ($aadhaar !== '' && strlen($aadhaar) >= 4) {
            $last4 = substr($aadhaar, -4);
            $stmt = $this->db->prepare("SELECT a.id FROM applications a JOIN application_field_values f ON f.application_id = a.id WHERE a.status = 'PENDING' AND f.field_name = 'aadhaar_no' AND f.field_value LIKE ? ORDER BY a.id DESC LIMIT 1");
            $stmt->execute(['%' . $last4]);
            $found = (int)$stmt->fetchColumn();
            if ($found > 0) return $found;
        }

        // Match by applicant full name
        $name = trim((string)($payload['applicant_name'] ?? $payload['full_name'] ?? ''));
        if ($name !== '') {
            $stmt = $this->db->prepare("SELECT a.id FROM applications a JOIN application_field_values f ON f.application_id = a.id WHERE a.status = 'PENDING' AND f.field_name = 'full_name' AND LOWER(f.field_value) = LOWER(?) ORDER BY a.id DESC LIMIT 1");
            $stmt->execute([$name]);
            $found = (int)$stmt->fetchColumn();
            if ($found > 0) return $found;
        }

        // Fallback: Latest active PENDING PAN application
        $stmt = $this->db->prepare("SELECT id FROM applications WHERE status = 'PENDING' ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $found = (int)$stmt->fetchColumn();
        if ($found > 0) return $found;

        return 0;
    }

    /**
     * POST /api/pan-card/complete - Mark PAN application as COMPLETED from Extension
     */
    public function apiComplete(): void
    {
        $this->sendCorsHeaders('POST, OPTIONS');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '', true);
        $payload = is_array($json) ? $json : $_POST;

        $appId = $this->resolvePanAppId($payload);
        if ($appId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid app_id or app_ref is required.']);
            exit;
        }

        $utiRef = trim((string)($payload['uti_ref_no'] ?? ''));
        $remarks = trim((string)($payload['remarks'] ?? ''));
        if ($remarks === '') {
            $remarks = $utiRef !== ''
                ? "UTI PAN processed via Extension (Ref: {$utiRef})"
                : 'UTI PAN processed via Extension';
        }

        $resultFileUrl = trim((string)($payload['result_file_url'] ?? ''));

        // Optional base64 receipt/screenshot upload
        $b64 = trim((string)($payload['result_file_base64'] ?? $payload['screenshot'] ?? ''));
        if ($b64 !== '') {
            $decoded = false;
            $ext = 'jpg';
            if (str_starts_with($b64, 'data:') && preg_match('/^data:([^;]+);base64,(.+)$/', $b64, $m)) {
                $ext = str_contains($m[1], 'pdf') ? 'pdf' : (str_contains($m[1], 'png') ? 'png' : 'jpg');
                $decoded = base64_decode($m[2], true);
            } else {
                $decoded = base64_decode($b64, true);
            }
            if ($decoded !== false && strlen($decoded) > 0) {
                $resDir = dirname(__DIR__, 2) . '/public/uploads/results';
                if (!is_dir($resDir)) @mkdir($resDir, 0755, true);
                $fileName = 'uti_res_' . $appId . '_' . date('Ymd_His') . '.' . $ext;
                if (@file_put_contents($resDir . '/' . $fileName, $decoded) !== false) {
                    $resultFileUrl = '/uploads/results/' . $fileName;
                }
            }
        }

        try {
            $res = \App\Services\ApplicationService::updateStatus(
                $appId,
                'COMPLETED',
                1,
                'SYSTEM',
                'UTI Auto-Pilot Extension',
                $remarks,
                null,
                $remarks,
                $resultFileUrl !== '' ? $resultFileUrl : null
            );
            echo json_encode(['success' => true, 'message' => $res['message'] ?? 'Marked as COMPLETED']);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST /api/pan-card/reject - Reject PAN application and refund wallet from Extension
     */
    public function apiReject(): void
    {
        $this->sendCorsHeaders('POST, OPTIONS');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '', true);
        $payload = is_array($json) ? $json : $_POST;

        $appId = $this->resolvePanAppId($payload);
        if ($appId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid app_id or app_ref is required.']);
            exit;
        }

        $reason = trim((string)($payload['reason'] ?? 'Rejected by UTI Auto-Pilot Extension'));

        try {
            $res = \App\Services\ApplicationService::updateStatus(
                $appId,
                'REJECTED',
                1,
                'SYSTEM',
                'UTI Auto-Pilot Extension',
                $reason,
                $reason,
                $reason,
                null
            );
            echo json_encode(['success' => true, 'message' => $res['message'] ?? 'Marked as REJECTED']);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
