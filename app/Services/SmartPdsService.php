<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;

class SmartPdsService
{
    public const PORTAL_URL = 'https://br.smartpds.nic.in/track';
    public const API_TRACK_URL = 'https://br.smartpds.nic.in/rcms/api/v1/rc-details/trackApplication';
    public const API_TIMELINE_URL = 'https://br.smartpds.nic.in/workflow/api/v1/application/history/timelines';

    /**
     * Form Type ID -> Human Readable Label (from SmartPDS Portal)
     */
    public const FORM_TYPES = [
        1  => 'New Ration Card',
        2  => 'New FPS',
        3  => 'Modification',
        4  => 'Transfer RC',
        5  => 'Transfer Members',
        6  => 'FPS Modification',
        7  => 'Surrender RC',
        8  => 'Suspend RC',
        9  => 'Revoke RC',
        10 => 'Surrender FPS',
        11 => 'Suspend FPS',
        12 => 'Revoke FPS',
        13 => 'Renewable FPS',
        14 => 'Add Member',
        15 => 'Delete Member',
        16 => 'Common Details',
        17 => 'Change Scheme',
        18 => 'Renew Ration Card',
        19 => 'Delete Ration Card',
        20 => 'Query Back',
        21 => 'Reject',
        22 => 'Approve',
        23 => 'Query Revert Back',
        24 => 'Approve',
        51 => 'Change HOF',
        54 => 'Transfer FPS',
        55 => 'Split Ration Card',
        60 => 'Ration Card Transfer Approved',
    ];

    /**
     * Detect whether an application number / code belongs to Bihar SmartPDS Ration Card (e.g., RC2026...)
     */
    public static function isRationCardApplication(string $appNumber = '', string $appCode = ''): bool
    {
        $num = strtoupper(trim($appNumber));
        $code = strtoupper(trim($appCode));

        if ($num !== '' && preg_match('/^RC\d{8,}/i', $num)) {
            return true;
        }

        if (in_array($code, ['EPDS', 'RATION', 'RATION_CARD', 'RC', 'SMARTPDS'], true) && str_starts_with($num, 'RC')) {
            return true;
        }

        return false;
    }

    /**
     * Resolve SmartPDS Form Type label
     */
    public static function getFormTypeName($formId): string
    {
        $id = (int)$formId;
        if ($id <= 0) {
            return 'N/A';
        }
        return self::FORM_TYPES[$id] ?? ('Form #' . $id);
    }

    /**
     * Format SmartPDS ISO date strings into DD-MM-YYYY matching portal UI
     */
    public static function formatDate(?string $dateStr): string
    {
        $raw = trim((string)$dateStr);
        if ($raw === '') {
            return 'NA';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        return date('d-m-Y', $ts);
    }

    /**
     * Perform HTTP JSON request to br.smartpds.nic.in
     */
    private static function httpRequest(string $url, ?array $postJson = null): array
    {
        $headers = [
            'Accept: application/json, text/plain, */*',
            'Origin: https://br.smartpds.nic.in',
            'Referer: https://br.smartpds.nic.in/track',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ];
        if ($postJson !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $body = false;
        $httpCode = 0;
        $err = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($postJson !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postJson));
            }
            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
        }

        if ($body === false || $httpCode === 0) {
            // Fallback via PHP stream wrapper
            $opts = [
                'http' => [
                    'method' => $postJson !== null ? 'POST' : 'GET',
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'content' => $postJson !== null ? json_encode($postJson) : '',
                    'timeout' => 20,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ];
            $ctx = stream_context_create($opts);
            $streamRes = @file_get_contents($url, false, $ctx);
            if ($streamRes !== false) {
                $body = $streamRes;
                $httpCode = 200;
                $err = '';
                if (!empty($http_response_header) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
                    $httpCode = (int)$m[1];
                }
            }
        }

        if ($body === false || $body === '') {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error' => $err ?: 'Unable to connect to br.smartpds.nic.in',
                'json' => null,
            ];
        }

        $decoded = json_decode($body, true);
        return [
            'ok' => ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)),
            'http_code' => $httpCode,
            'error' => is_array($decoded) ? '' : 'Invalid JSON response from SmartPDS portal',
            'json' => $decoded,
        ];
    }

    /**
     * Fetch complete live application details and timeline from br.smartpds.nic.in
     */
    public static function trackByApplicationNo(string $rcId): array
    {
        $rcId = strtoupper(trim($rcId));
        if ($rcId === '') {
            return [
                'success' => false,
                'found' => false,
                'message' => 'Application / RC Number is empty.',
            ];
        }

        // 1. Fetch Application Details
        $res = self::httpRequest(self::API_TRACK_URL, ['rcId' => $rcId]);
        if (!$res['ok']) {
            $errMsg = $res['json']['errorMsg'] ?? $res['error'] ?? ('SmartPDS HTTP ' . $res['http_code']);
            return [
                'success' => false,
                'found' => false,
                'portal_error' => true,
                'message' => $errMsg,
            ];
        }

        $json = $res['json'];
        if (!empty($json['error']) || empty($json['data']) || !is_array($json['data'])) {
            return [
                'success' => false,
                'found' => false,
                'message' => $json['errorMsg'] ?: 'Application not found on SmartPDS portal (https://br.smartpds.nic.in/track).',
            ];
        }

        $rec = $json['data'][0];
        $applicationId = $rec['applicationId'] ?? null;

        // 2. Fetch Workflow Timeline History
        $timeline = [];
        if (!empty($applicationId)) {
            $tlRes = self::httpRequest(self::API_TIMELINE_URL . '?applicationId=' . urlencode((string)$applicationId));
            if ($tlRes['ok'] && !empty($tlRes['json']['data']) && is_array($tlRes['json']['data'])) {
                $rawSteps = array_reverse($tlRes['json']['data']);
                foreach ($rawSteps as $step) {
                    $stepStatus = trim((string)($step['status'] ?? ''));
                    $endDate = trim((string)($step['endDate'] ?? ''));
                    $startDate = trim((string)($step['startDate'] ?? ''));
                    $isCurrent = !empty($step['currentStep']);

                    if ($stepStatus === 'DRAFT') {
                        $stateLabel = 'DRAFT';
                    } elseif ($stepStatus === 'E-KYC_PENDING') {
                        $stateLabel = 'Pending';
                    } elseif ($endDate !== '') {
                        $stateLabel = 'Completed';
                    } else {
                        $stateLabel = 'Pending';
                    }

                    $timeline[] = [
                        'status' => $stepStatus,
                        'date' => self::formatDate($isCurrent && $startDate !== '' ? $startDate : ($endDate !== '' ? $endDate : $startDate)),
                        'state' => $stateLabel,
                        'office' => trim((string)($step['officeName'] ?? '')),
                        'code' => trim((string)($step['officeCode'] ?? '')),
                        'by' => trim((string)($step['user'] ?? '')),
                        'current_step' => $isCurrent,
                        'is_end_node' => !empty($step['isEndNode']),
                    ];
                }
            }
        }

        // 3. Determine authoritative status (matching SmartPDS Track UI behavior)
        $lastAction = strtoupper(trim((string)($rec['lastAction'] ?? '')));
        $appStatusRaw = trim((string)($rec['appStatus'] ?? ''));
        $appStatusUpper = strtoupper($appStatusRaw);

        $endNodeStatus = '';
        foreach ($timeline as $step) {
            if (!empty($step['is_end_node']) || !empty($step['current_step'])) {
                $endNodeStatus = $step['status'];
            }
        }
        $endNodeUpper = strtoupper($endNodeStatus);

        if (
            in_array($lastAction, ['APPROVED', 'PRINTED'], true) ||
            in_array($appStatusUpper, ['APPROVED', 'PRINTED'], true) ||
            in_array($endNodeUpper, ['APPROVED', 'PRINTED'], true)
        ) {
            $normalizedStatus = 'APPROVED';
            $statusDisplay = $lastAction !== '' ? $lastAction : ($appStatusRaw !== '' ? strtoupper($appStatusRaw) : 'APPROVED');
        } elseif (
            $lastAction === 'REJECTED' ||
            $appStatusUpper === 'REJECTED' ||
            $endNodeUpper === 'REJECTED'
        ) {
            $normalizedStatus = 'REJECTED';
            $statusDisplay = $appStatusRaw !== '' ? $appStatusRaw : 'Rejected';
        } else {
            $normalizedStatus = 'IN_PROCESS';
            $statusDisplay = $appStatusRaw !== '' ? $appStatusRaw : ($endNodeStatus !== '' ? $endNodeStatus : ($lastAction !== '' ? $lastAction : 'Under Process'));
        }

        $formId = $rec['formId'] ?? null;
        $formType = !empty($formId) ? self::getFormTypeName($formId) : (!empty($rec['formName']) ? (string)$rec['formName'] : 'Ration Card');
        $ackNo = !empty($rec['acknowledgementNumber']) ? (string)$rec['acknowledgementNumber'] : 'NA';
        $rcNumber = !empty($rec['rcNumber']) ? (string)$rec['rcNumber'] : 'NA';
        $existingRcNumber = !empty($rec['existingRcNumber']) ? (string)$rec['existingRcNumber'] : 'NA';
        $appDate = self::formatDate($rec['createdOn'] ?? '');
        $schemeName = !empty($rec['cardDesc']) ? (string)$rec['cardDesc'] : (!empty($rec['schemeId']) ? (string)$rec['schemeId'] : 'N/A');
        $hofName = !empty($rec['applicantName']) ? (string)$rec['applicantName'] : 'NA';
        $fpsId = !empty($rec['existingFpsId']) ? (string)$rec['existingFpsId'] : (!empty($rec['fpsId']) ? (string)$rec['fpsId'] : 'NA');
        $fpsName = !empty($rec['fpsName']) ? (string)$rec['fpsName'] : '';

        // Build Timeline Summary Text
        $stepParts = [];
        $lastOfficerPart = '';
        foreach ($timeline as $step) {
            $meta = [];
            if ($step['office'] !== '') $meta[] = $step['office'];
            if ($step['by'] !== '') $meta[] = $step['by'];
            if ($step['date'] !== '' && $step['date'] !== 'NA') $meta[] = $step['date'];
            $stepStr = $step['status'] . (!empty($meta) ? ' (' . implode(', ', $meta) . ')' : '');
            $stepParts[] = $stepStr;
            if (stripos($step['status'], 'Inspector') !== false || stripos($step['status'], 'SDO') !== false) {
                $lastOfficerPart = $stepStr;
            }
        }

        $summaryParts = [
            "SmartPDS Status: {$statusDisplay}",
            "Form: {$formType}",
            "HOF: {$hofName}",
            "Ack No: {$ackNo}",
            "RC No: {$rcNumber}",
            "Existing RC: {$existingRcNumber}",
            "Scheme: {$schemeName}",
            "FPS ID: {$fpsId}",
            "App Date: {$appDate}",
        ];
        if (!empty($stepParts)) {
            $summaryParts[] = "Timeline: " . implode(' -> ', $stepParts);
        }
        $summaryText = implode(' | ', $summaryParts);

        $rejectionReason = '';
        if ($normalizedStatus === 'REJECTED') {
            $rejectionReason = "Rejected on SmartPDS Portal ({$formType}) | Ack: {$ackNo} | HOF: {$hofName} | RC No: {$rcNumber}";
            if ($lastOfficerPart !== '') {
                $rejectionReason .= " | {$lastOfficerPart}";
            }
        }

        $trackingData = [
            'portal' => 'BR SmartPDS',
            'portal_url' => self::PORTAL_URL,
            'checked_at' => date('Y-m-d H:i:s'),
            'application_id' => $applicationId,
            'application_status' => $statusDisplay,
            'normalized_status' => $normalizedStatus,
            'application_number' => (string)($rec['rcId'] ?? $rcId),
            'acknowledgement_number' => $ackNo,
            'form_id' => $formId,
            'form_type' => $formType,
            'ration_card_number' => $rcNumber,
            'existing_rc_number' => $existingRcNumber,
            'application_date' => $appDate,
            'scheme_name' => $schemeName,
            'hof_name' => $hofName,
            'fps_id' => $fpsId,
            'fps_name' => $fpsName,
            'timeline' => $timeline,
            'summary_text' => $summaryText,
            'rejection_reason' => $rejectionReason,
        ];

        return [
            'success' => true,
            'found' => true,
            'is_smartpds' => true,
            'status' => $normalizedStatus,
            'portal_status' => $statusDisplay,
            'rejection_reason' => $rejectionReason,
            'smartpds' => $trackingData,
            'message' => $summaryText,
        ];
    }

    /**
     * Ensure portal_tracking_json column exists on applications table safely
     */
    public static function ensureTrackingColumn(PDO $db): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        try {
            $cols = $db->query("SHOW COLUMNS FROM applications LIKE 'portal_tracking_json'")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($cols)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `portal_tracking_json` LONGTEXT NULL AFTER `admin_remarks`");
            }
            $checked = true;
        } catch (\Throwable $e) {
            // Ignore if lacks permissions or already added concurrently
        }
    }

    /**
     * Check live SmartPDS status for an application record and sync status + full details to DB
     */
    public static function checkAndSyncApplication(int $appId, array $options = []): array
    {
        $db = Database::getConnection();
        self::ensureTrackingColumn($db);

        $stmt = $db->prepare("
            SELECT id, app_ref, application_code, applicant_identifier, applicant_name, status, result_file
            FROM applications
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            return ['success' => false, 'is_smartpds' => true, 'message' => "Application ID {$appId} not found."];
        }

        $rcNo = strtoupper(trim((string)($app['applicant_identifier'] ?? '')));
        if ($rcNo === '') {
            return ['success' => false, 'is_smartpds' => true, 'message' => 'Application Number (RC2026...) missing in this record.'];
        }

        $trackRes = self::trackByApplicationNo($rcNo);
        if (empty($trackRes['found']) || empty($trackRes['smartpds'])) {
            $msg = $trackRes['message'] ?? 'Application not found on SmartPDS portal.';
            $nextCheck = RtpsCertificateService::calculateNextCheckTime(4);

            try {
                $db->prepare("
                    UPDATE rtps_certificate_queue
                    SET status = ?,
                        check_count = check_count + 1,
                        last_checked_at = NOW(),
                        next_check_at = ?,
                        last_status_message = ?,
                        updated_at = NOW()
                    WHERE application_id = ? AND status != 'ATTACHED'
                ")->execute([
                    !empty($trackRes['portal_error']) ? 'FAILED' : 'UNAVAILABLE',
                    $nextCheck,
                    $msg,
                    $appId,
                ]);
            } catch (\Throwable $e) {}

            return [
                'success' => false,
                'is_smartpds' => true,
                'status' => $app['status'],
                'app_id' => $appId,
                'message' => $msg,
            ];
        }

        $trackingData = $trackRes['smartpds'];
        $trackingJson = json_encode($trackingData, JSON_UNESCAPED_UNICODE);
        $normStatus = $trackingData['normalized_status'];
        $summaryText = $trackingData['summary_text'];
        $currentStatus = strtoupper((string)$app['status']);

        // Persist full SmartPDS JSON snapshot
        $db->prepare("UPDATE applications SET portal_tracking_json = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$trackingJson, $appId]);

        $actorUserId = (int)($options['actor_user_id'] ?? 1);
        $actorName = (string)($options['actor_name'] ?? 'SmartPDS Auto Tracker');

        if ($normStatus === 'APPROVED') {
            if (in_array($currentStatus, ['PENDING', 'IN_PROCESS', 'UNDER_REVIEW'], true)) {
                ApplicationService::updateStatus(
                    $appId,
                    'APPROVED',
                    $actorUserId,
                    'SYSTEM',
                    $actorName,
                    $summaryText,
                    null,
                    $summaryText,
                    null
                );
            } else {
                $db->prepare("UPDATE applications SET admin_remarks = ?, remarks = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$summaryText, $summaryText, $appId]);
            }

            try {
                $db->prepare("
                    UPDATE rtps_certificate_queue
                    SET status = 'ATTACHED',
                        check_count = check_count + 1,
                        last_checked_at = NOW(),
                        last_status_message = ?,
                        updated_at = NOW()
                    WHERE application_id = ?
                ")->execute([$summaryText, $appId]);
            } catch (\Throwable $e) {}

            AuditService::log('SMARTPDS_STATUS_APPROVED', 'APPLICATION', (string)$appId, [
                'rc_id' => $rcNo,
                'ack_no' => $trackingData['acknowledgement_number'],
                'rc_number' => $trackingData['ration_card_number'],
                'form_type' => $trackingData['form_type'],
            ], $actorUserId, 'SYSTEM');

            return [
                'success' => true,
                'is_smartpds' => true,
                'app_id' => $appId,
                'status' => 'APPROVED',
                'portal_status' => $trackingData['application_status'],
                'smartpds' => $trackingData,
                'message' => $summaryText,
            ];
        }

        if ($normStatus === 'REJECTED') {
            $rejReason = $trackingData['rejection_reason'] ?: $summaryText;
            if (!in_array($currentStatus, ['REJECTED', 'COMPLETED'], true)) {
                ApplicationService::updateStatus(
                    $appId,
                    'REJECTED',
                    $actorUserId,
                    'SYSTEM',
                    $actorName,
                    $rejReason,
                    $rejReason,
                    $summaryText
                );
            } else {
                $db->prepare("UPDATE applications SET rejection_reason = IFNULL(NULLIF(rejection_reason, ''), ?), admin_remarks = ?, remarks = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$rejReason, $summaryText, $summaryText, $appId]);
            }

            try {
                $db->prepare("
                    UPDATE rtps_certificate_queue
                    SET status = 'FAILED',
                        check_count = check_count + 1,
                        last_checked_at = NOW(),
                        last_status_message = ?,
                        updated_at = NOW()
                    WHERE application_id = ?
                ")->execute([$rejReason, $appId]);
            } catch (\Throwable $e) {}

            AuditService::log('SMARTPDS_STATUS_REJECTED', 'APPLICATION', (string)$appId, [
                'rc_id' => $rcNo,
                'ack_no' => $trackingData['acknowledgement_number'],
                'form_type' => $trackingData['form_type'],
            ], $actorUserId, 'SYSTEM');

            return [
                'success' => true,
                'is_smartpds' => true,
                'app_id' => $appId,
                'status' => 'REJECTED',
                'portal_status' => $trackingData['application_status'],
                'rejection_reason' => $rejReason,
                'smartpds' => $trackingData,
                'message' => $summaryText,
            ];
        }

        // Still In Process / Under Review / Pending on SmartPDS
        $nextCheckAt = RtpsCertificateService::calculateNextCheckTime(4);
        if ($currentStatus === 'PENDING') {
            ApplicationService::updateStatus(
                $appId,
                'IN_PROCESS',
                $actorUserId,
                'SYSTEM',
                $actorName,
                $summaryText,
                null,
                $summaryText,
                null
            );
        } elseif (!in_array($currentStatus, ['COMPLETED', 'APPROVED', 'REJECTED'], true)) {
            $db->prepare("UPDATE applications SET status = 'IN_PROCESS', admin_remarks = ?, remarks = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$summaryText, $summaryText, $appId]);
        }

        try {
            $db->prepare("
                UPDATE rtps_certificate_queue
                SET status = 'UNAVAILABLE',
                    check_count = check_count + 1,
                    last_checked_at = NOW(),
                    next_check_at = ?,
                    last_status_message = ?,
                    updated_at = NOW()
                WHERE application_id = ? AND status != 'ATTACHED'
            ")->execute([$nextCheckAt, $summaryText, $appId]);
        } catch (\Throwable $e) {}

        return [
            'success' => true,
            'is_smartpds' => true,
            'app_id' => $appId,
            'status' => 'IN_PROCESS',
            'portal_status' => $trackingData['application_status'],
            'smartpds' => $trackingData,
            'message' => $summaryText,
        ];
    }
}
