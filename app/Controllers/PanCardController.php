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
     * Retailer View: New Pan Card Apply Page (Form + Previous Requests)
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

        $panDataTypeId = $this->getPanDataTypeId();
        // Ensure wallet exists and fetch fresh balance
        WalletService::getOrCreateWallet($userId);
        $walletBalance = WalletService::getBalance($userId);

        $statusFilter = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
        $search = trim((string)($_GET['search'] ?? ''));

        // Query Previous Requests
        $where = ["(a.application_code = 'PAN_CARD' OR a.data_type_id = :pan_dt_id)"];
        $params = [':pan_dt_id' => $panDataTypeId];

        if ($isAdmin) {
            // Admin sees all applications
        } elseif ($isOperator) {
            $assignedBlocks = AuthService::getOperatorAssignedBlocks($userId);
            if (!empty($assignedBlocks)) {
                $bIds = array_column($assignedBlocks, 'block_id');
                $placeholders = implode(',', array_map('intval', $bIds));
                $where[] = "(a.block_id IN ({$placeholders}) OR a.assigned_operator_id = {$userId} OR a.assigned_operator_id IS NULL)";
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
            $assignedBlocks = AuthService::getOperatorAssignedBlocks($userId);
            if (!empty($assignedBlocks)) {
                $bIds = array_column($assignedBlocks, 'block_id');
                $placeholders = implode(',', array_map('intval', $bIds));
                $countUserWhere = " AND (a.block_id IN ({$placeholders}) OR a.assigned_operator_id = {$userId} OR a.assigned_operator_id IS NULL)";
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

        require dirname(__DIR__) . '/Views/user/pan_card.php';
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
            $panFee = 107.00;
            $distCommission = 7.00;
            $panDataTypeId = $this->getPanDataTypeId();

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
                    'message' => "Pan Card application #{$appRef} submitted successfully! ₹" . number_format($panFee, 2) . " debited from wallet."
                ]);
                exit;
            }

            $_SESSION['flash_success'] = "Pan Card application #{$appRef} submitted successfully! ₹" . number_format($panFee, 2) . " debited from wallet.";
            header('Location: /services/pan-card');
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

            if (!is_dir($pubDir)) @mkdir($pubDir, 0755, true);
            if (!is_dir($storDir)) @mkdir($storDir, 0755, true);

            $fileName = $inputName . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
            $pubPath = $pubDir . '/' . $fileName;
            $storPath = $storDir . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $pubPath)) {
                throw new Exception("Failed to upload {$label}.");
            }

            @copy($pubPath, $storPath);
            $trackedFiles[] = $pubPath;
            $trackedFiles[] = $storPath;

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

                    if (!is_dir($pubDir)) @mkdir($pubDir, 0755, true);
                    if (!is_dir($storDir)) @mkdir($storDir, 0755, true);

                    $fileName = $inputName . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
                    $pubPath = $pubDir . '/' . $fileName;
                    $storPath = $storDir . '/' . $fileName;

                    file_put_contents($pubPath, $decoded);
                    @copy($pubPath, $storPath);
                    $trackedFiles[] = $pubPath;
                    $trackedFiles[] = $storPath;

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
            $candidate1 = $baseDir . '/public' . $rawUrl;
            $candidate2 = $baseDir . '/storage' . $rawUrl;
            if (file_exists($candidate1) && is_file($candidate1)) {
                $localPath = $candidate1;
            } elseif (file_exists($candidate2) && is_file($candidate2)) {
                $localPath = $candidate2;
            }
        } elseif (preg_match('#^https?://#i', $rawUrl)) {
            $parsed = parse_url($rawUrl, PHP_URL_PATH);
            if ($parsed && str_starts_with($parsed, '/uploads/')) {
                $candidate1 = $baseDir . '/public' . $parsed;
                $candidate2 = $baseDir . '/storage' . $parsed;
                if (file_exists($candidate1) && is_file($candidate1)) {
                    $localPath = $candidate1;
                } elseif (file_exists($candidate2) && is_file($candidate2)) {
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

            $padding = 10;
            $maxDrawW = $targetW - ($padding * 2);
            $maxDrawH = $targetH - ($padding * 2);

            $ratio = $srcW / $srcH;
            if ($ratio > ($maxDrawW / $maxDrawH)) {
                $drawW = $maxDrawW;
                $drawH = (int)round($maxDrawW / $ratio);
            } else {
                $drawH = $maxDrawH;
                $drawW = (int)round($maxDrawH * $ratio);
            }

            $dx = (int)(($targetW - $drawW) / 2);
            $dy = (int)(($targetH - $drawH) / 2);

            imagecopyresampled($dest, $tempImg, $dx, $dy, 0, 0, $drawW, $drawH, $srcW, $srcH);
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

            $targetRatio = $targetW / $targetH;
            $srcRatio = $srcW / $srcH;

            if ($srcRatio > $targetRatio) {
                $sH = $srcH;
                $sW = (int)round($srcH * $targetRatio);
                $sx = (int)(($srcW - $sW) / 2);
                $sy = 0;
            } else {
                $sW = $srcW;
                $sH = (int)round($srcW / $targetRatio);
                $sx = 0;
                $sy = (int)max(0, ($srcH - $sH) * 0.2);
            }

            imagecopyresampled($dest, $srcImg, 0, 0, $sx, $sy, $targetW, $targetH, $sW, $sH);
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
}
