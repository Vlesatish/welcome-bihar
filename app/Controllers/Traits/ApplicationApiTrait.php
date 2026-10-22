<?php
declare(strict_types=1);

namespace App\Controllers\Traits;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\ApplicationService;
use App\Services\PricingService;
use App\Services\OperatorAuthorizationService;
use Exception;
use PDO;

trait ApplicationApiTrait
{
public function apiGetRequests(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }

        $db = Database::getConnection();
        $userId = (int)$user['id'];
        $role = $user['role'];

        $where = ["1=1"];
        $params = [];

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $role;
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        } elseif ($role === 'USER') {
            $effectiveRole = 'USER';
        }

        // Scope
        if ($effectiveRole === 'USER') {
            $where[] = "a.user_id = :scope_uid";
            $params[':scope_uid'] = $userId;
        } elseif ($effectiveRole === 'DISTRIBUTOR') {
            $where[] = "a.distributor_id = :scope_did";
            $params[':scope_did'] = $userId;
        } elseif ($effectiveRole === 'OPERATOR') {
            $where[] = OperatorAuthorizationService::getAuthorizedSqlCondition((int)$userId, 'a');
        }

        // Filters
        $status = trim($_GET['status'] ?? '');
        if (!empty($status) && $status !== 'ALL') {
            if ($status === 'IN_PROCESS') {
                $where[] = "(a.status = 'IN_PROCESS' OR a.status = 'UNDER_REVIEW')";
            } elseif ($status === 'COMPLETED') {
                $where[] = "(a.status = 'COMPLETED' OR a.status = 'APPROVED')";
            } else {
                $where[] = "a.status = :filter_status";
                $params[':filter_status'] = $status;
            }
        }

        $search = trim($_GET['search'] ?? '');
        if (!empty($search)) {
            $where[] = "(a.app_ref LIKE :q OR a.applicant_name LIKE :q OR a.applicant_mobile LIKE :q OR a.applicant_identifier LIKE :q OR CAST(a.id AS CHAR) LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        $dateFilter = trim($_GET['date_filter'] ?? $_GET['dateFilter'] ?? 'ALL');
        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(a.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT a.id, a.app_ref, a.user_id, a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.fee_debited, a.commission_credited, a.status, a.admin_remarks, a.remarks,
                   a.result_file, a.is_refunded, a.is_commission_paid, a.assigned_operator_id,
                   a.created_at, a.updated_at,
                   u.name as retailer_name, u.center_name, u.mobile as retailer_mobile,
                   op.name as operator_name,
                   d.name as district_name,
                   b.name as block_name,
                   dt.name_en as dt_name_en
            FROM applications a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN users op ON a.assigned_operator_id = op.id
            LEFT JOIN districts d ON a.district_id = d.id
            LEFT JOIN blocks b ON a.block_id = b.id
            JOIN data_types dt ON a.data_type_id = dt.id
            WHERE {$whereClause}
            ORDER BY a.id DESC
            LIMIT 150
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $appIds = array_column($rawRows, 'id');
        $fieldValuesByApp = [];
        if (!empty($appIds)) {
            $inClause = implode(',', array_map('intval', $appIds));
            $fvRows = $db->query("
                SELECT afv.application_id, afv.field_name, afv.field_value, dtf.label_en
                FROM application_field_values afv
                LEFT JOIN data_type_fields dtf ON afv.field_id = dtf.id
                WHERE afv.application_id IN ({$inClause})
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fvRows as $fv) {
                $fieldValuesByApp[$fv['application_id']][$fv['field_name']] = $fv['field_value'];
            }
        }

        $results = [];
        foreach ($rawRows as $r) {
            $fData = $fieldValuesByApp[$r['id']] ?? [];
            if (!isset($fData['applicant_name'])) $fData['applicant_name'] = $r['applicant_name'];
            if (!isset($fData['mobile'])) $fData['mobile'] = $r['applicant_mobile'];
            if (!isset($fData['application_number'])) $fData['application_number'] = $r['applicant_identifier'];
            if (!isset($fData['district_name'])) $fData['district_name'] = $r['district_name'];
            if (!isset($fData['block_name'])) $fData['block_name'] = $r['block_name'];

            $statusDisplay = $r['status'];
            if ($r['status'] === 'UNDER_REVIEW') $statusDisplay = 'IN_PROCESS';
            elseif ($r['status'] === 'APPROVED') $statusDisplay = 'COMPLETED';

            $results[] = [
                'id' => (string)$r['id'],
                'requestNumber' => (string)$r['id'],
                'app_ref' => $r['app_ref'],
                'retailerId' => (string)$r['user_id'],
                'retailerName' => $r['retailer_name'] . ($r['center_name'] ? " ({$r['center_name']})" : ''),
                'retailerMobile' => $r['retailer_mobile'],
                'serviceId' => 'srv_block_app',
                'serviceTitle' => $r['dt_name_en'] ?: 'Block & District Application Pass Service',
                'status' => $statusDisplay,
                'raw_status' => $r['status'],
                'price' => (float)$r['fee_debited'],
                'fee_debited' => (float)$r['fee_debited'],
                'createdAt' => date('c', strtotime($r['created_at'])),
                'created_at' => $r['created_at'],
                'formData' => $fData,
                'adminRemarks' => $r['admin_remarks'] ?: $r['remarks'],
                'outputAttachmentUrl' => $r['result_file'],
                'isRefunded' => (bool)$r['is_refunded'],
                'isCommissionPaid' => (bool)$r['is_commission_paid']
            ];
        }

        echo json_encode($results);
        exit;
    }

    /**
     * POST /api/requests - In-place AJAX submit request
     */
    public function apiSubmitRequest(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Please log in to submit requests.']);
            exit;
        }

        $userId = (int)$user['id'];
        $db = Database::getConnection();

        // Parse JSON or POST payload
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        // If batch payload submitted to /api/requests, dispatch to apiSubmitBatch
        if (($jsonData && (!empty($jsonData['items']) || !empty($jsonData['batch']))) || !empty($_POST['items']) || !empty($_POST['batch'])) {
            $this->apiSubmitBatch();
            return;
        }

        $formData = [];
        if ($jsonData && isset($jsonData['formData'])) {
            $formData = $jsonData['formData'];
        } else {
            $formData = array_merge($_POST, $_GET);
        }

        // Enforce active service
        $activeDataType = $db->query("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$activeDataType) {
            $activeDataType = $db->query("SELECT id, is_active FROM data_types WHERE id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        if (!$activeDataType || (int)$activeDataType['is_active'] === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Selected service is currently inactive or disabled by administrator.']);
            exit;
        }
        $dataTypeId = (int)$activeDataType['id'];

        // Resolve District ID
        $districtId = (int)($formData['district_id'] ?? 0);
        $districtName = trim((string)($formData['district'] ?? $formData['district_name'] ?? ''));
        if ($districtId <= 0 && !empty($districtName)) {
            $dStmt = $db->prepare("SELECT id FROM districts WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
            $dStmt->execute([$districtName]);
            $districtId = (int)($dStmt->fetchColumn() ?: 0);
        }

        // Resolve Block ID
        $blockId = (int)($formData['block_id'] ?? 0);
        $blockName = trim((string)($formData['block'] ?? $formData['block_name'] ?? ''));
        if ($blockId <= 0 && !empty($blockName)) {
            $bSql = "SELECT id FROM blocks WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))" . ($districtId > 0 ? " AND district_id = {$districtId}" : "") . " LIMIT 1";
            $bStmt = $db->prepare($bSql);
            $bStmt->execute([$blockName]);
            $blockId = (int)($bStmt->fetchColumn() ?: 0);
        }

        if ($districtId <= 0 || $blockId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Please select a valid State, District, and Block before submitting.']);
            exit;
        }

        // Dynamic price calculation
        $currentPricing = PricingService::calculatePrice($dataTypeId, $districtId, $blockId, $formData);
        $serverFee = (float)$currentPricing['final_fee'];

        // Validate displayed price (re-acceptance if rate changed)
        $displayedPrice = isset($formData['displayed_price']) ? (float)$formData['displayed_price'] : (isset($formData['confirmed_fee']) ? (float)$formData['confirmed_fee'] : null);
        if ($displayedPrice !== null && abs($displayedPrice - $serverFee) > 0.01) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'price_changed' => true,
                'new_price' => $serverFee,
                'error' => "Notice: Service rate for this location has updated to ₹" . number_format($serverFee, 2) . " ({$currentPricing['rate_note']}). Please review and re-confirm."
            ]);
            exit;
        }

        // Check wallet balance
        $currentWallet = \App\Services\WalletService::getBalance($userId);
        if ($currentWallet < $serverFee) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Insufficient Wallet Balance! Required: ₹" . number_format($serverFee, 2) . ", Available: ₹" . number_format($currentWallet, 2) . ". Please recharge."
            ]);
            exit;
        }

        try {
            $result = \App\Services\ApplicationService::submit($userId, $dataTypeId, $districtId, $blockId, $formData, $_FILES);
            $newBalance = \App\Services\WalletService::getBalance($userId);

            $reqObject = [
                'id' => (string)$result['application_id'],
                'requestNumber' => (string)$result['application_id'],
                'app_ref' => $result['app_ref'],
                'serviceId' => 'srv_block_app',
                'serviceTitle' => 'Block & District Application Pass Service',
                'status' => 'PENDING',
                'raw_status' => 'PENDING',
                'price' => (float)$result['price'],
                'fee_debited' => (float)$result['price'],
                'createdAt' => date('c'),
                'created_at' => date('Y-m-d H:i:s'),
                'formData' => $result['formData'],
                'adminRemarks' => null,
                'outputAttachmentUrl' => null,
                'isRefunded' => false,
                'isCommissionPaid' => false
            ];

            echo json_encode([
                'success' => true,
                'request' => $reqObject,
                'remainingWalletBalance' => $newBalance,
                'message' => "Request #{$result['app_ref']} submitted successfully! Fee ₹" . number_format($serverFee, 2) . " deducted."
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            $newBalance = \App\Services\WalletService::getBalance($userId);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'remainingWalletBalance' => $newBalance
            ]);
            exit;
        }
    }

    /**
     * POST /api/requests/batch - Atomic batch submission for multiple applications
     */
    public function apiSubmitBatch(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Please log in to submit requests.']);
            exit;
        }

        $userId = (int)$user['id'];
        $db = Database::getConnection();

        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true) ?: [];

        $items = $jsonData['items'] ?? $jsonData['batch'] ?? ($_POST['items'] ?? $_POST['batch'] ?? []);
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        if (empty($items) || !is_array($items)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Batch list is empty. Please add at least one application.']);
            exit;
        }

        // Active service
        $activeDataType = $db->query("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$activeDataType) {
            $activeDataType = $db->query("SELECT id, is_active FROM data_types WHERE id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        if (!$activeDataType || (int)$activeDataType['is_active'] === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Selected service is currently inactive or disabled by administrator.']);
            exit;
        }
        $dataTypeId = (int)$activeDataType['id'];

        $options = [
            'accept_price_change' => !empty($jsonData['accept_price_change']) || !empty($_POST['accept_price_change'])
        ];

        try {
            $result = \App\Services\ApplicationService::submitBatch($userId, $dataTypeId, $items, $options);
            if (isset($result['price_changed']) && $result['price_changed'] === true) {
                http_response_code(409);
                echo json_encode($result);
                exit;
            }

            echo json_encode($result);
            exit;
        } catch (\Exception $e) {
            http_response_code(400);
            $currentWallet = \App\Services\WalletService::getBalance($userId);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'remainingWalletBalance' => $currentWallet
            ]);
            exit;
        }
    }

    /**
     * POST /api/requests/update-status (and /api/requests/:id/status)
     */
    public function apiUpdateStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $user['role'];
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        }

        if (!$user || !in_array($effectiveRole, ['ADMIN', 'OPERATOR', 'DISTRIBUTOR'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized action.']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true) ?: [];

        $appId = (int)($_POST['application_id'] ?? $_POST['id'] ?? $jsonData['id'] ?? $jsonData['application_id'] ?? ($_GET['id'] ?? 0));
        $newStatus = trim((string)($_POST['status'] ?? $jsonData['status'] ?? ''));
        $adminRemarks = trim((string)($_POST['adminRemarks'] ?? $_POST['admin_remarks'] ?? $jsonData['adminRemarks'] ?? $jsonData['admin_remarks'] ?? ''));
        $rejectionReason = trim((string)($_POST['rejectionReason'] ?? $_POST['rejection_reason'] ?? $jsonData['rejectionReason'] ?? $jsonData['rejection_reason'] ?? ''));
        $outputUrl = trim((string)($_POST['outputAttachmentUrl'] ?? $jsonData['outputAttachmentUrl'] ?? ''));

        // Handle uploaded file if present
        if (!empty($_FILES['result_file']['name']) && $_FILES['result_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['result_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!in_array($ext, $allowedExts, true) || $file['size'] > 15 * 1024 * 1024) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Invalid file format or file exceeds 15MB limit.']);
                exit;
            }

            // Verify binary MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($mime, $allowedMimes, true)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => "Invalid file content ({$mime}). Only genuine PDF and images allowed."]);
                exit;
            }

            $resDir = dirname(dirname(__DIR__)) . '/public/uploads/results';
            if (!is_dir($resDir)) mkdir($resDir, 0755, true);
            $safeName = 'res_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $resDir . '/' . $safeName)) {
                $outputUrl = '/uploads/results/' . $safeName;
            }
        }

        if ($effectiveRole === 'OPERATOR') {
            if (!AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden: You do not have permission to access or update applications outside your assigned District and Block.']);
                exit;
            }
        }

        if ($newStatus === 'REJECTED' && empty(trim($rejectionReason))) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Rejection reason is mandatory. Please provide a clear explanation for rejecting the application.']);
            exit;
        }

        if (($newStatus === 'APPROVED' || $newStatus === 'COMPLETED') && empty($outputUrl)) {
            $db = Database::getConnection();
            $checkStmt = $db->prepare("SELECT result_file FROM applications WHERE id = ?");
            $checkStmt->execute([$appId]);
            $outputUrl = $checkStmt->fetchColumn() ?: null;
        }

        try {
            $result = \App\Services\ApplicationService::updateStatus(
                $appId,
                $newStatus,
                $user['id'],
                $effectiveRole,
                $user['name'],
                $adminRemarks,
                $rejectionReason,
                $adminRemarks,
                $outputUrl ?: null
            );

            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'status' => $newStatus,
                'outputAttachmentUrl' => $outputUrl ?: null
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }


    /**
     * GET /api/requests/chat
     */
    public function apiGetChat(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }

        $appId = (int)($_GET['application_id'] ?? $_GET['id'] ?? 0);
        if ($appId <= 0) {
            echo json_encode(['messages' => []]);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT a.id, a.user_id, a.assigned_operator_id, dt.enable_chat FROM applications a JOIN data_types dt ON a.data_type_id = dt.id WHERE a.id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            http_response_code(404);
            echo json_encode(['error' => 'Application not found.']);
            exit;
        }

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $user['role'];
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        } elseif ($user['role'] === 'USER') {
            $effectiveRole = 'USER';
        }

        // Operator authorization check
        if ($effectiveRole === 'OPERATOR') {
            if ((int)$app['user_id'] === (int)$user['id']) {
                http_response_code(403);
                echo json_encode(['error' => 'You cannot view chats as operator on an application submitted by your own retailer account.']);
                exit;
            }
            if (!AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: You do not have permission to view chats for applications outside your assigned District and Block.']);
                exit;
            }
        } elseif ($effectiveRole === 'USER' && (int)$app['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized: You do not have permission to view chats for this application.']);
            exit;
        }

        $chats = \App\Services\ApplicationService::getChats($appId);
        echo json_encode(['messages' => $chats]);
        exit;
    }

    /**
     * POST /api/requests/chat
     */
    public function apiSendChat(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true) ?: [];

        $appId = (int)($_POST['application_id'] ?? $jsonData['application_id'] ?? ($_GET['id'] ?? 0));
        $message = trim((string)($_POST['message'] ?? $jsonData['message'] ?? ''));
        $attachmentUrl = trim((string)($_POST['attachment_url'] ?? $jsonData['attachment_url'] ?? ''));

        if ($appId <= 0 || empty($message)) {
            http_response_code(422);
            echo json_encode(['error' => 'Message and application ID are required.']);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT a.id, a.user_id, a.assigned_operator_id, dt.enable_chat FROM applications a JOIN data_types dt ON a.data_type_id = dt.id WHERE a.id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            http_response_code(404);
            echo json_encode(['error' => 'Application not found.']);
            exit;
        }

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $user['role'];
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        } elseif ($user['role'] === 'USER') {
            $effectiveRole = 'USER';
        }

        // Operator authorization check
        if ($effectiveRole === 'OPERATOR') {
            if ((int)$app['user_id'] === (int)$user['id']) {
                http_response_code(403);
                echo json_encode(['error' => 'You cannot process or send chat as operator on an application submitted by your own retailer account.']);
                exit;
            }
            if (!AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: You do not have permission to send chat messages for applications outside your assigned District and Block.']);
                exit;
            }
        }

        // Check if chat is enabled
        if ((int)($app['enable_chat'] ?? 1) === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Chat is disabled for this service by administrator.']);
            exit;
        }

        // Retailer ownership check
        if ($effectiveRole === 'USER' && (int)$app['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized: You do not own this application.']);
            exit;
        }

        try {
            $chat = \App\Services\ApplicationService::addChat(
                $appId,
                $user['id'],
                $user['name'],
                $effectiveRole,
                $message,
                $attachmentUrl ?: null
            );
            echo json_encode(['success' => true, 'message' => $chat]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/requests/upload - File upload endpoint for photos/documents
     */
    public function apiUploadFile(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!AuthService::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }

        $file = $_FILES['file'] ?? $_FILES['attachment'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid file uploaded.']);
            exit;
        }

        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowedExts, true)) {
            http_response_code(422);
            echo json_encode(['error' => "Invalid file extension '.{$ext}'. Allowed: " . implode(', ', $allowedExts)]);
            exit;
        }

        // Validate MIME type against file content
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp']
        ];

        if (!isset($allowedMimes[$ext]) || !in_array($mime, $allowedMimes[$ext], true)) {
            http_response_code(422);
            echo json_encode(['error' => "Security error: File MIME type '{$mime}' does not match extension '.{$ext}'."]);
            exit;
        }

        if ($file['size'] > 25 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(['error' => 'File size exceeds 25MB limit.']);
            exit;
        }

        $targetDir = dirname(dirname(__DIR__)) . '/public/uploads/documents';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

        $safeName = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $targetDir . '/' . $safeName)) {
            $url = '/uploads/documents/' . $safeName;
            $sizeStr = ($file['size'] > 1024 * 1024)
                ? round($file['size'] / (1024 * 1024), 2) . ' MB'
                : round($file['size'] / 1024, 1) . ' KB';

            echo json_encode([
                'success' => true,
                'url' => $url,
                'filename' => $origName,
                'size' => $sizeStr
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file on server.']);
        exit;
    }

    /**
     * GET /api/block-rates - Fetch active block rates
     */
    
}
