<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use DateTime;
use DateTimeZone;
use Exception;
use PDO;

class RtpsCertificateService
{
    public const PORTAL_URL = 'https://swcs.bihar.gov.in/RtpsReportView/certificate_download.jsp';
    public const PORTAL_DOWNLOAD_ACTION = 'https://swcs.bihar.gov.in/RtpsReportView/certificateDownload.do';
    public const SERVICEONLINE_BASE_URL = 'https://serviceonline.bihar.gov.in';

    /**
     * Resolve the service type for the RTPS Portal form ('RTPS' or 'OTHER')
     */
    /**
     * Detect specific application code from code and/or application number (e.g. BCCCO/2026/12721009 -> BCCCO)
     */
    public static function detectApplicationCode(string $applicationCode = '', string $applicationNumber = ''): string
    {
        $code = strtoupper(trim($applicationCode));
        $appNum = strtoupper(trim($applicationNumber));

        if (!empty($code) && $code !== 'OTHER' && $code !== 'RTPS') {
            return $code;
        }

        if (preg_match('/^([A-Z]{3,10})/i', $appNum, $m)) {
            return strtoupper($m[1]);
        }

        return !empty($code) ? $code : 'RTPS';
    }

    /**
     * Resolve the service type for the RTPS Portal form ('RTPS' or 'OTHER')
     */
    public static function resolveServiceType(string $applicationCode = '', string $applicationNumber = ''): string
    {
        $code = strtoupper(trim($applicationCode));
        $appNum = strtoupper(trim($applicationNumber));

        if (empty($code) || $code === 'OTHER' || $code === 'RTPS') {
            if (preg_match('/^([A-Z]{3,10})/i', $appNum, $m)) {
                $code = strtoupper($m[1]);
            }
        }

        // Known Bihar RTPS prefixes:
        // BCCCO (Caste), BRCCO (Residence), BICCO (Income), BOBC (OBC), NCLCO (Non-Creamy Layer),
        // EWSCO (Economically Weaker Section), DMCO, SDMCO, ROTI, RTPS
        if (preg_match('/^(BCC|BRC|BIC|BOBC|NCL|EWS|DM|SDM|ROTI|RTPS)/i', $code)
            || preg_match('/^(BCC|BRC|BIC|BOBC|NCL|EWS|DM|SDM|ROTI|RTPS)/i', $appNum)) {
            return 'RTPS';
        }

        return 'OTHER';
    }

    /**
     * Calculate the next check time in Asia/Kolkata timezone.
     * Default: 5 hours from current time (runs every 5 hours).
     * Supported presets:
     * - '5_hours' / null: Now + 5 hours
     * - '7_am_today' / '7am_today': Today 07:00:00 (for immediate test verification)
     * - '7_am_tomorrow' / '7am_tomorrow': Tomorrow 07:00:00
     * - 'due_now' / 'now': 1 minute in the past (immediately eligible)
     */
    public static function calculateNextCheckTime(int $intervalHours = 5, ?string $preset = null): string
    {
        $tz = new DateTimeZone('Asia/Kolkata');
        $now = new DateTime('now', $tz);

        if ($preset === '7_am_today' || $preset === '7am_today') {
            return $now->format('Y-m-d 07:00:00');
        }

        if ($preset === '7_am_tomorrow' || $preset === '7am_tomorrow') {
            $tom = (clone $now)->modify('+1 day');
            return $tom->format('Y-m-d 07:00:00');
        }

        if ($preset === 'due_now' || $preset === 'now') {
            $past = (clone $now)->modify('-1 minute');
            return $past->format('Y-m-d H:i:s');
        }

        // Default: 5 hours from now (har 5 ghante me run ho)
        $next = (clone $now)->modify("+{$intervalHours} hours");
        return $next->format('Y-m-d H:i:s');
    }

    /**
     * Automatically enqueue an application into the RTPS Certificate Check Queue
     * when it is routed / moved to IN_PROCESS.
     */
    public static function enqueueApplication(int $appId): ?int
    {
        $db = Database::getConnection();

        $appStmt = $db->prepare("
            SELECT id, app_ref, application_code, applicant_identifier, applicant_name,
                   district_id, block_id, status, result_file
            FROM applications
            WHERE id = ?
            LIMIT 1
        ");
        $appStmt->execute([$appId]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            return null;
        }

        // Only queue if certificate is not already attached and not in terminal REJECTED state
        if (!empty($app['result_file']) || $app['status'] === 'REJECTED') {
            return null;
        }

        $appNum = trim((string)$app['applicant_identifier']);
        $appCode = self::detectApplicationCode((string)$app['application_code'], $appNum);

        // Auto-heal applications table if code was empty or OTHER
        if ((empty($app['application_code']) || $app['application_code'] === 'OTHER') && !empty($appCode) && $appCode !== 'OTHER') {
            $db->prepare("UPDATE applications SET application_code = ? WHERE id = ?")->execute([$appCode, $appId]);
        }

        $applicantName = trim((string)$app['applicant_name']);
        $districtId = (int)$app['district_id'];
        $blockId = (int)$app['block_id'];
        $serviceType = self::resolveServiceType($appCode, $appNum);
        $nextCheckAt = self::calculateNextCheckTime();

        // Check if queue entry exists
        $chk = $db->prepare("SELECT id, status, check_count FROM rtps_certificate_queue WHERE application_id = ?");
        $chk->execute([$appId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // If already attached, do not overwrite
            if ($existing['status'] === 'ATTACHED') {
                return (int)$existing['id'];
            }

            // Update queue item
            $upd = $db->prepare("
                UPDATE rtps_certificate_queue
                SET service_type = ?,
                    application_code = ?,
                    application_number = ?,
                    applicant_name = ?,
                    district_id = ?,
                    block_id = ?,
                    status = IF(status = 'ATTACHED', 'ATTACHED', 'QUEUED'),
                    next_check_at = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $serviceType,
                $appCode,
                $appNum,
                $applicantName,
                $districtId,
                $blockId,
                $nextCheckAt,
                $existing['id']
            ]);

            return (int)$existing['id'];
        }

        // Insert new queue record
        $ins = $db->prepare("
            INSERT INTO rtps_certificate_queue (
                application_id, service_type, application_code, application_number,
                applicant_name, district_id, block_id, status, portal_url,
                check_count, next_check_at, last_status_message, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, 'QUEUED', ?,
                0, ?, 'Queued for automatic scheduled checking (Every 5 Hours) Asia/Kolkata', NOW(), NOW()
            )
        ");
        $ins->execute([
            $appId,
            $serviceType,
            $appCode,
            $appNum,
            $applicantName,
            $districtId,
            $blockId,
            self::PORTAL_URL,
            $nextCheckAt
        ]);

        $queueId = (int)$db->lastInsertId();

        AuditService::log('RTPS_CERTIFICATE_ENQUEUED', 'APPLICATION', (string)$appId, [
            'queue_id' => $queueId,
            'application_number' => $appNum,
            'applicant_name' => $applicantName,
            'service_type' => $serviceType,
            'next_check_at' => $nextCheckAt
        ]);

        return $queueId;
    }

    /**
     * Ensure Windows native OCR script exists in temp directory
     */
    private static function ensureOcrScript(): string
    {
        $psPath = sys_get_temp_dir() . '/wb_ocr.ps1';
        if (!file_exists($psPath) || filesize($psPath) < 50) {
            $code = <<<'POWERSHELL'
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$asTaskGeneric = [System.WindowsRuntimeSystemExtensions].GetMethods() | ? { $_.Name -eq 'AsTask' -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' }
function Await($WinRtTask, $ResultType) {
    $asTask = $asTaskGeneric.MakeGenericMethod($ResultType)
    $netTask = $asTask.Invoke($null, @($WinRtTask))
    $netTask.Wait(-1) | Out-Null
    $netTask.Result
}
[Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime] | Out-Null
[Windows.Media.Ocr.OcrEngine, Windows.Foundation, ContentType = WindowsRuntime] | Out-Null
[Windows.Graphics.Imaging.BitmapDecoder, Windows.Foundation, ContentType = WindowsRuntime] | Out-Null
$imagePath = [System.IO.Path]::GetFullPath($args[0])
$file = Await ([Windows.Storage.StorageFile]::GetFileFromPathAsync($imagePath)) ([Windows.Storage.StorageFile])
$stream = Await ($file.OpenAsync([Windows.Storage.FileAccessMode]::Read)) ([Windows.Storage.Streams.IRandomAccessStream])
$decoder = Await ([Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync($stream)) ([Windows.Graphics.Imaging.BitmapDecoder])
$bitmap = Await ($decoder.GetSoftwareBitmapAsync()) ([Windows.Graphics.Imaging.SoftwareBitmap])
$engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages()
if ($null -eq $engine) { exit 1 }
$ocrResult = Await ($engine.RecognizeAsync($bitmap)) ([Windows.Media.Ocr.OcrResult])
Write-Output $ocrResult.Text
POWERSHELL;
            @file_put_contents($psPath, $code);
        }
        return $psPath;
    }

    /**
     * Solve ServicePlus 6-digit captcha using color-filtering and Windows native OCR
     */
    public static function solveServiceOnlineCaptcha(string $rawImgBytes): ?string
    {
        $rawPath = sys_get_temp_dir() . '/so_cap_' . uniqid() . '.jpg';
        file_put_contents($rawPath, $rawImgBytes);

        $img = @imagecreatefromjpeg($rawPath);
        if (!$img) {
            @unlink($rawPath);
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $out = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($out, 255, 255, 255);
        $black = imagecolorallocate($out, 0, 0, 0);
        imagefill($out, 0, 0, $white);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($g > 100 && $g > ($r + 30) && $g > ($b + 30)) {
                    imagesetpixel($out, $x, $y, $black);
                }
            }
        }

        $cleanPath = sys_get_temp_dir() . '/so_clean_' . uniqid() . '.png';
        imagepng($out, $cleanPath);
        imagedestroy($img);
        imagedestroy($out);
        @unlink($rawPath);

        $psScript = self::ensureOcrScript();
        $cmd = 'powershell -ExecutionPolicy Bypass -File ' . escapeshellarg($psScript) . ' ' . escapeshellarg($cleanPath);
        $res = shell_exec($cmd);
        @unlink($cleanPath);

        $digits = preg_replace('/[^0-9]/', '', trim((string)$res));
        return (strlen($digits) === 6) ? $digits : null;
    }

    /**
     * Real-time live check and certificate download from serviceonline.bihar.gov.in tracking flow.
     * Overcomes the 24-hour delay on swcs.bihar.gov.in by downloading certificates the same day they are approved.
     */
    public static function fetchLiveCertificateFromServiceOnline(
        string $applicationNumber,
        string $applicantName,
        array $candidateDates = [],
        string $fatherName = '',
        string $motherName = ''
    ): array {
        $appNum = trim($applicationNumber);
        $cleanName = trim(preg_replace('/\s+/', ' ', $applicantName));
        if (empty($appNum) || empty($cleanName)) {
            return ['success' => false, 'message' => 'Missing Application Number or Applicant Name.'];
        }

        $cleanNameNormalized = trim(preg_replace('/^(Mr\.?|Mrs\.?|Ms\.?|Shri\.?|Smt\.?)\s+/i', '', $cleanName));

        $dates = [];
        foreach ($candidateDates as $d) {
            $ts = is_numeric($d) ? (int)$d : strtotime(str_replace('/', '-', (string)$d));
            if ($ts && $ts > 0) {
                $dates[] = date('d/m/Y', $ts);
            }
        }
        $dates[] = date('d/m/Y');
        $dates[] = date('d/m/Y', strtotime('-1 day'));
        $dates = array_unique(array_filter($dates));

        $cookieJar = tempnam(sys_get_temp_dir(), 'so_ck_');

        $curlFn = function (string $url, $post = null, ?string $referer = null) use ($cookieJar): array {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
            if ($referer) {
                curl_setopt($ch, CURLOPT_REFERER, $referer);
            }
            if ($post !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
            }
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [$code, $res ?: ''];
        };

        try {
            $curlFn(self::SERVICEONLINE_BASE_URL . '/');

            list($caCode, $caHtml) = $curlFn(
                self::SERVICEONLINE_BASE_URL . '/officials/citizenApplication.html',
                ['' => ''],
                self::SERVICEONLINE_BASE_URL . '/'
            );

            if ($caCode !== 200 || empty($caHtml)) {
                return ['success' => false, 'message' => "Live portal citizenApplication.html unreachable (HTTP $caCode)"];
            }

            preg_match('/name="OWASP_CSRFTOKEN"\s+value="([^"]+)"/i', $caHtml, $csrfM);
            $csrfToken = $csrfM[1] ?? '';

            $trackOptions = [2, 1]; // 2 = Delivery Date, 1 = Submission Date
            $step1Success = false;
            $s1Html = '';
            $matchedDate = '';
            $matchedTrackUsing = 2;

            foreach ($dates as $dateStr) {
                foreach ($trackOptions as $trackUsing) {
                    $captcha = null;
                    for ($att = 1; $att <= 3; $att++) {
                        list($cCap, $capBytes) = $curlFn(
                            self::SERVICEONLINE_BASE_URL . '/officials/captchaImage',
                            null,
                            self::SERVICEONLINE_BASE_URL . '/officials/citizenApplication.html'
                        );
                        if ($cCap === 200 && !empty($capBytes)) {
                            $captcha = self::solveServiceOnlineCaptcha($capBytes);
                            if (!empty($captcha)) break;
                        }
                        usleep(200000);
                    }

                    if (empty($captcha)) {
                        continue;
                    }

                    $postStep1 = [
                        'OWASP_CSRFTOKEN' => $csrfToken,
                        'track_option' => 'R',
                        'spdv_appl_ref_no' => $appNum,
                        'trackusing' => (string)$trackUsing,
                        'applyDate' => $dateStr,
                        'captchaAnswer' => $captcha,
                        'whichTab' => 'liATab1',
                        'submitFlag' => '',
                        'otpDivShowFlag' => '',
                    ];

                    list($c1, $res1) = $curlFn(
                        self::SERVICEONLINE_BASE_URL . '/officials/citizenServiceTrackList.do',
                        $postStep1,
                        self::SERVICEONLINE_BASE_URL . '/officials/citizenApplication.html'
                    );

                    if ($c1 === 200 && stripos($res1, "secTabOpen = 'YES'") !== false) {
                        $step1Success = true;
                        $s1Html = $res1;
                        $matchedDate = $dateStr;
                        $matchedTrackUsing = $trackUsing;
                        break 2;
                    }
                }
            }

            if (!$step1Success || empty($s1Html)) {
                return [
                    'success' => false,
                    'message' => 'Application reference or date not matched on serviceonline.bihar.gov.in tracking.'
                ];
            }

            preg_match('/applRefServiceId\s*=\s*\'([^\']+)\'/i', $s1Html, $sidM);
            $serviceId = $sidM[1] ?? '4630012';

            preg_match('/applRefBaseServiceId\s*=\s*\'([^\']+)\'/i', $s1Html, $bsidM);
            $baseServiceId = $bsidM[1] ?? '463';

            $postRaf = [
                'OWASP_CSRFTOKEN' => $csrfToken,
                'track_option' => 'R',
                'spdv_appl_ref_no' => $appNum,
                'trackusing' => (string)$matchedTrackUsing,
                'applyDate' => $matchedDate,
                'whichTab' => 'liATab2',
                'docSelectedVal' => '1',
                'viewDocument' => '1',
                'trackThrough' => 'A',
                'trackThroughOption' => 'A',
                'trackThroughOptionSelectedVal' => 'A',
                'serviceId' => $serviceId,
                'currentServiceId' => $serviceId,
                'applRefHiddenServiceId' => $serviceId,
                'currentBaseServiceId' => $baseServiceId,
            ];

            list($cRaf, $rafHtml) = $curlFn(
                self::SERVICEONLINE_BASE_URL . '/officials/renderAdditionalFields.do',
                $postRaf,
                self::SERVICEONLINE_BASE_URL . '/officials/citizenServiceTrackList.do'
            );

            preg_match('/name="attributeIds"\s+id="attributeIds"\s+value="([^"]+)"/i', $rafHtml, $attrM);
            $rawAttrs = trim($attrM[1] ?? '', ',');
            $attrIds = explode(',', $rawAttrs);

            $nameVal = $cleanNameNormalized;
            $fatherVal = !empty($fatherName) ? trim($fatherName) : 'NA';
            $motherVal = !empty($motherName) ? trim($motherName) : 'NA';

            $postStep2 = $postRaf;
            $postStep2['attributeIds'] = ',' . $rawAttrs;

            $applAttrArr = [];
            $applAttrEditArr = [];

            foreach ($attrIds as $idx => $aId) {
                $aId = trim($aId);
                if (empty($aId)) continue;

                $val = $nameVal;
                if ($idx === 1) $val = $fatherVal;
                if ($idx === 2) $val = $motherVal;

                $postStep2[$aId] = $val;
                $applAttrArr[] = "('$aId',''$val'')";
                $applAttrEditArr[] = "{$aId}~$~{$val}~$@~";
            }

            $postStep2['serviceReportInboxApplAttrString'] = "'" . implode(',', $applAttrArr) . "'";
            $postStep2['serviceReportInboxapplAttrStringEdit'] = implode('', $applAttrEditArr);

            list($c2, $s2Html) = $curlFn(
                self::SERVICEONLINE_BASE_URL . '/officials/citizenServiceTrackList.do',
                $postStep2,
                self::SERVICEONLINE_BASE_URL . '/officials/renderAdditionalFields.do'
            );

            $isDelivered = (stripos($s2Html, 'Delivered') !== false);
            $isRejected = (stripos($s2Html, 'Rejected') !== false);
            $isUnderProcess = (stripos($s2Html, 'Under Process') !== false || stripos($s2Html, 'Forwarded') !== false || stripos($s2Html, 'Initiated') !== false);

            if (!$isDelivered) {
                $msg = $isRejected
                    ? 'Application has been REJECTED by department officials on live portal.'
                    : ($isUnderProcess ? 'Live Status: Application is UNDER PROCESS with department officials (not yet delivered).' : 'Certificate not yet delivered.');
                return [
                    'success' => false,
                    'status' => $isRejected ? 'REJECTED' : 'IN_PROCESS',
                    'message' => $msg
                ];
            }

            preg_match("/showDocument\('([^']+)','([^']+)','([^']+)','([^']+)','([123])','([^']+)'\)\">([^<]*Certificate[^<]*)</i", $s2Html, $docM);
            if (!$docM) {
                preg_match("/showDocument\('([^']+)','([^']+)','([^']+)','([^']+)','([123])','([^']+)'\)/i", $s2Html, $docM);
            }

            if (!$docM) {
                return [
                    'success' => false,
                    'status' => 'DELIVERED_NO_LINK',
                    'message' => 'Status is Delivered on live portal, but certificate link could not be parsed.'
                ];
            }

            $applID = $docM[1];
            $spdiCurrentProcId = $docM[2];
            $applicationCertId = $docM[3];
            $token = $docM[6];
            $certTitle = trim($docM[7] ?? 'Certificate');

            preg_match('/name="relatedDocumentToken"\s+value="([^"]+)"/i', $s2Html, $relM);
            $relToken = $relM[1] ?? '';
            parse_str($relToken, $csrfArr);

            $downloadPost = array_merge($csrfArr, [
                'token' => $token,
                'applicationId' => $applID,
                'applicationCertId' => $applicationCertId,
                'currentProcessId' => $spdiCurrentProcId,
            ]);

            list($cDl, $pdfBytes) = $curlFn(
                self::SERVICEONLINE_BASE_URL . '/officials/viewDeliveredCertificate.do',
                $downloadPost,
                self::SERVICEONLINE_BASE_URL . '/officials/citizenServiceTrackList.do'
            );

            if ($cDl === 200 && is_string($pdfBytes) && strlen($pdfBytes) >= 500 && strncmp($pdfBytes, '%PDF-', 5) === 0) {
                return [
                    'success' => true,
                    'status' => 'DELIVERED',
                    'pdf' => $pdfBytes,
                    'size' => strlen($pdfBytes),
                    'matched_service' => $certTitle,
                    'message' => 'Official certificate PDF downloaded live from serviceonline.bihar.gov.in tracking.'
                ];
            }

            return [
                'success' => false,
                'message' => "Download request returned HTTP $cDl or non-PDF binary."
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'ServiceOnline tracking exception: ' . $e->getMessage()
            ];
        } finally {
            if (file_exists($cookieJar)) {
                @unlink($cookieJar);
            }
        }
    }

    /**
     * Connects to Bihar RTPS portal session, fetches form token,
     * submits application reference & applicant name, and returns PDF binary if ready.
     * First checks live same-day tracking on serviceonline.bihar.gov.in,
     * and falls back to swcs.bihar.gov.in report download.
     */
    public static function fetchCertificateFromPortal(
        string $applicationNumber,
        string $applicantName,
        string $serviceType = 'RTPS',
        array $candidateDates = [],
        string $fatherName = '',
        string $motherName = ''
    ): array {
        $appNum = trim($applicationNumber);
        $cleanName = trim(preg_replace('/\s+/', ' ', $applicantName));

        if (empty($appNum) || empty($cleanName)) {
            return [
                'success' => false,
                'message' => 'Missing Application Number or Applicant Name for portal check.'
            ];
        }

        // 1. Live Same-Day Check via serviceonline.bihar.gov.in
        $liveRes = self::fetchLiveCertificateFromServiceOnline(
            $appNum,
            $cleanName,
            $candidateDates,
            $fatherName,
            $motherName
        );

        if (!empty($liveRes['success']) && !empty($liveRes['pdf'])) {
            return $liveRes;
        }

        if (!empty($liveRes['status']) && in_array($liveRes['status'], ['IN_PROCESS', 'REJECTED'])) {
            return $liveRes;
        }

        // 2. Fallback to swcs.bihar.gov.in portal download
        $cleanNameNormalized = trim(preg_replace('/^(Mr\.?|Mrs\.?|Ms\.?|Shri\.?|Smt\.?)\s+/i', '', $cleanName));

        // Smart candidate service types
        $isRtpsFormat = (bool)preg_match('/^(BCC|BRC|BIC|BOBC|NCL|EWS|DM|SDM|ROTI|RTPS)/i', $appNum);
        if ($isRtpsFormat) {
            $candidateServices = ['RTPS', 'OTHER'];
        } else {
            $incoming = strtoupper(trim($serviceType)) ?: 'RTPS';
            $candidateServices = [$incoming, $incoming === 'RTPS' ? 'OTHER' : 'RTPS'];
        }
        $candidateServices = array_unique($candidateServices);

        // Name casing variants - Bihar portal requires strict casing match
        $nameVariants = [
            ucwords(strtolower($cleanNameNormalized)), // Title Case (Standard official format, e.g. Vikash Kumar)
            $cleanNameNormalized,                      // As entered without honorific
            $cleanName,                                // Raw string
            strtoupper($cleanNameNormalized)           // UPPERCASE
        ];
        $nameVariants = array_unique(array_filter($nameVariants));

        $cookieJar = tempnam(sys_get_temp_dir(), 'rtps_ck_');

        try {
            // Step 1: Open initial portal page to obtain valid session cookie and form action
            $ch1 = curl_init(self::PORTAL_URL);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($ch1, CURLOPT_COOKIEFILE, $cookieJar);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch1, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
            $html = curl_exec($ch1);
            $initHttpCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            curl_close($ch1);

            if ($initHttpCode !== 200 || empty($html)) {
                return [
                    'success' => false,
                    'message' => "Government RTPS portal unreachable (HTTP {$initHttpCode}). Will retry at scheduled time."
                ];
            }

            // Extract dynamic action URL (contains ;jsessionid=...)
            $actionUrl = self::PORTAL_DOWNLOAD_ACTION;
            if (preg_match('/action="([^"]+)"/i', $html, $m)) {
                $rawAction = trim($m[1]);
                if (strpos($rawAction, 'http') === 0) {
                    $actionUrl = $rawAction;
                } else {
                    $actionUrl = 'https://swcs.bihar.gov.in' . (strpos($rawAction, '/') === 0 ? '' : '/') . $rawAction;
                }
            }

            // Step 2: Try combinations of candidate services and name variants
            foreach ($candidateServices as $service) {
                foreach ($nameVariants as $name) {
                    $postData = [
                        'serviceType' => $service,
                        'appl_ref_no' => $appNum,
                        'applicant_name_en' => $name,
                        'captcha' => ''
                    ];

                    $ch2 = curl_init($actionUrl);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookieJar);
                    curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieJar);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch2, CURLOPT_POST, true);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($postData));
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 25);
                    curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
                    $resp = curl_exec($ch2);
                    $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);

                    // Step 3: Validate if response is genuine official PDF
                    if ($httpCode === 200 && is_string($resp) && strlen($resp) >= 500 && strncmp($resp, '%PDF-', 5) === 0) {
                        return [
                            'success' => true,
                            'pdf' => $resp,
                            'size' => strlen($resp),
                            'matched_service' => $service,
                            'matched_name' => $name,
                            'message' => 'Official certificate PDF successfully retrieved from Bihar RTPS portal.'
                        ];
                    }
                }
            }

            // If not a PDF after testing candidates, portal returned HTML indicating pending or not generated yet
            return [
                'success' => false,
                'message' => 'Certificate not yet generated or pending on RTPS portal. Next check scheduled in 5 hours.'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Portal communication exception: ' . $e->getMessage()
            ];
        } finally {
            if (file_exists($cookieJar)) {
                @unlink($cookieJar);
            }
        }
    }

    /**
     * Perform an immediate portal check for a single application and attach if ready
     */
    public static function checkAndProcessApplication(int $appId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT id, app_ref, application_code, applicant_identifier, applicant_name, status, result_file, created_at
            FROM applications
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            return ['success' => false, 'message' => "Application ID {$appId} not found."];
        }

        if (!empty($app['result_file']) && $app['status'] === 'COMPLETED') {
            return ['success' => true, 'already_completed' => true, 'result_file' => $app['result_file']];
        }

        $appNum = trim((string)$app['applicant_identifier']);
        $applicantName = trim((string)$app['applicant_name']);
        $detectedCode = self::detectApplicationCode((string)$app['application_code'], $appNum);
        $serviceType = self::resolveServiceType($detectedCode, $appNum);

        // Auto-heal application code in applications table if needed
        if ((empty($app['application_code']) || $app['application_code'] === 'OTHER') && !empty($detectedCode) && $detectedCode !== 'OTHER') {
            $db->prepare("UPDATE applications SET application_code = ? WHERE id = ?")->execute([$detectedCode, $appId]);
        }

        // Update queue item to CHECKING with auto-healed code and service_type
        $db->prepare("UPDATE rtps_certificate_queue SET status = 'CHECKING', application_code = ?, service_type = ?, updated_at = NOW() WHERE application_id = ?")
           ->execute([$detectedCode, $serviceType, $appId]);

        // Collect candidate dates and applicant details
        $candidateDates = [];
        if (!empty($app['created_at'])) {
            $candidateDates[] = date('d/m/Y', strtotime((string)$app['created_at']));
        }
        $candidateDates[] = date('d/m/Y');
        $candidateDates[] = date('d/m/Y', strtotime('-1 day'));

        $fatherName = '';
        $motherName = '';
        try {
            $fStmt = $db->prepare("SELECT field_name, field_value FROM application_field_values WHERE application_id = ?");
            $fStmt->execute([$appId]);
            while ($fRow = $fStmt->fetch(PDO::FETCH_ASSOC)) {
                $fName = strtolower(trim((string)$fRow['field_name']));
                $fVal = trim((string)$fRow['field_value']);
                if (str_contains($fName, 'father')) $fatherName = $fVal;
                if (str_contains($fName, 'mother')) $motherName = $fVal;
                if (str_contains($fName, 'date') && !empty($fVal)) $candidateDates[] = $fVal;
            }
        } catch (\Throwable $e) {}

        $fetchRes = self::fetchCertificateFromPortal($appNum, $applicantName, $serviceType, $candidateDates, $fatherName, $motherName);

        if (!empty($fetchRes['success']) && !empty($fetchRes['pdf'])) {
            // Update queue with matched service if different
            if (!empty($fetchRes['matched_service'])) {
                $db->prepare("UPDATE rtps_certificate_queue SET service_type = ? WHERE application_id = ?")
                   ->execute([$fetchRes['matched_service'], $appId]);
            }

            // Attach genuine PDF and transition status to COMPLETED
            $attachRes = self::attachCertificatePdf($appId, $fetchRes['pdf'], null, null, 'SYSTEM');
            return [
                'success' => true,
                'status' => 'ATTACHED',
                'app_id' => $appId,
                'result_file' => $attachRes['result_file'],
                'message' => 'Official certificate PDF downloaded and attached automatically in background.'
            ];
        }

        // Not ready yet: mark unavailable and reschedule for next 5-hour check
        self::markCertificateUnavailable($appId, $fetchRes['message'] ?? 'Certificate not yet generated on portal');
        return [
            'success' => false,
            'status' => 'UNAVAILABLE',
            'app_id' => $appId,
            'message' => $fetchRes['message'] ?? 'Certificate pending on portal; scheduled for next 5-hour check.'
        ];
    }

    /**
     * Attach downloaded certificate PDF to application and mark it APPROVED/COMPLETED
     */
    public static function attachCertificatePdf(
        int $appId,
        string $filePathOrBinary,
        ?string $originalFileName = null,
        ?int $actorUserId = null,
        ?string $actorRole = 'SYSTEM'
    ): array {
        $db = Database::getConnection();

        $appStmt = $db->prepare("
            SELECT id, app_ref, status, applicant_identifier, applicant_name
            FROM applications
            WHERE id = ?
            LIMIT 1
        ");
        $appStmt->execute([$appId]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new Exception("Application ID {$appId} not found.");
        }

        // 1. Determine whether $filePathOrBinary is a URL, an existing file path, or raw binary string
        $binary = null;
        if (preg_match('/^https?:\/\//i', trim($filePathOrBinary))) {
            $ch = curl_init(trim($filePathOrBinary));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $binary = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($binary)) {
                throw new Exception("Failed to download certificate from URL (HTTP {$httpCode}). Please verify the link or upload the PDF file directly.");
            }
        } elseif (file_exists($filePathOrBinary) && is_file($filePathOrBinary)) {
            $binary = file_get_contents($filePathOrBinary);
        } else {
            $binary = $filePathOrBinary;
        }

        if (empty($binary) || strlen($binary) < 500) {
            throw new Exception("Validation failed: Certificate file is too small (" . strlen((string)$binary) . " bytes) or empty. Please ensure a complete official certificate PDF is attached.");
        }

        // 2. Validate PDF signature (%PDF-)
        if (strncmp($binary, '%PDF-', 5) !== 0) {
            throw new Exception("Validation failed: The attached document is not a valid PDF file. Header '%PDF-' not detected.");
        }

        // 3. Save PDF securely into public/uploads/results/
        $resDir = dirname(dirname(__DIR__)) . '/public/uploads/results';
        if (!is_dir($resDir)) {
            @mkdir($resDir, 0755, true);
        }

        $cleanRef = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($app['applicant_identifier'] ?: $app['app_ref']));
        $safeFileName = 'rtps_cert_' . $appId . '_' . $cleanRef . '_' . date('Ymd_His') . '.pdf';
        $destPath = $resDir . '/' . $safeFileName;

        if (file_put_contents($destPath, $binary) === false) {
            throw new Exception("Failed to write certificate PDF to disk at {$destPath}.");
        }

        $webPath = '/uploads/results/' . $safeFileName;

        // 4. Update application status to APPROVED or COMPLETED
        // If current status is IN_PROCESS or PENDING or UNDER_REVIEW, transition to COMPLETED
        $currentStatus = $app['status'];
        if ($currentStatus !== 'COMPLETED') {
            $effectiveActorId = $actorUserId ?: 1;
            $effectiveRole = $actorRole ?: 'SYSTEM';
            $actorName = 'System Operator';
            if ($actorUserId) {
                $uStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                $uStmt->execute([$actorUserId]);
                $actorName = (string)($uStmt->fetchColumn() ?: 'Operator #' . $actorUserId);
            }

            ApplicationService::updateStatus(
                $appId,
                'COMPLETED',
                $effectiveActorId,
                $effectiveRole,
                $actorName,
                'RTPS Certificate successfully downloaded and verified from official portal.',
                null,
                'Certificate attached from RTPS portal',
                $webPath
            );
        } else {
            // If already COMPLETED, simply ensure result_file points to the certificate
            $upd = $db->prepare("UPDATE applications SET result_file = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$webPath, $appId]);
        }

        // 5. Update rtps_certificate_queue
        $qUpd = $db->prepare("
            UPDATE rtps_certificate_queue
            SET status = 'ATTACHED',
                certificate_file = ?,
                last_checked_at = NOW(),
                last_status_message = 'Certificate PDF downloaded, verified, and attached successfully. Application completed.',
                updated_at = NOW()
            WHERE application_id = ?
        ");
        $qUpd->execute([$webPath, $appId]);

        AuditService::log('RTPS_CERTIFICATE_ATTACHED', 'APPLICATION', (string)$appId, [
            'file_path' => $webPath,
            'file_size' => strlen($binary),
            'attached_by' => $actorUserId,
            'attached_by_role' => $actorRole
        ], $actorUserId, $actorRole);

        return [
            'success' => true,
            'app_id' => $appId,
            'result_file' => $webPath,
            'status' => 'COMPLETED',
            'message' => 'Certificate PDF attached and application completed successfully.'
        ];
    }

    /**
     * Mark certificate as unavailable on the portal (e.g. still under process).
     * Keeps application IN_PROCESS and schedules next check in 5 hours (or custom time).
     */
    public static function markCertificateUnavailable(
        int $appId,
        string $reason = 'Certificate not generated yet on RTPS portal',
        ?int $actorUserId = null,
        ?string $customNextCheckAt = null
    ): void {
        $db = Database::getConnection();
        $nextCheckAt = $customNextCheckAt ?: self::calculateNextCheckTime(5);

        $upd = $db->prepare("
            UPDATE rtps_certificate_queue
            SET status = 'UNAVAILABLE',
                check_count = check_count + 1,
                last_checked_at = NOW(),
                next_check_at = ?,
                last_status_message = ?,
                updated_at = NOW()
            WHERE application_id = ?
        ");
        $upd->execute([$nextCheckAt, $reason, $appId]);

        // Ensure application stays IN_PROCESS (never reject!)
        $db->prepare("
            UPDATE applications
            SET status = 'IN_PROCESS',
                updated_at = NOW()
            WHERE id = ? AND status = 'PENDING'
        ")->execute([$appId]);

        AuditService::log('RTPS_CERTIFICATE_CHECK_UNAVAILABLE', 'APPLICATION', (string)$appId, [
            'reason' => $reason,
            'next_check_at' => $nextCheckAt,
            'actor_user_id' => $actorUserId
        ], $actorUserId, 'SYSTEM');
    }

    /**
     * Mark a portal communication or network error.
     * Keeps application IN_PROCESS and schedules retry in 5 hours (or custom time).
     */
    public static function markPortalError(
        int $appId,
        string $errorMessage,
        ?int $actorUserId = null,
        ?string $customNextCheckAt = null
    ): void {
        $db = Database::getConnection();
        $nextCheckAt = $customNextCheckAt ?: self::calculateNextCheckTime(5);

        $upd = $db->prepare("
            UPDATE rtps_certificate_queue
            SET status = 'FAILED',
                check_count = check_count + 1,
                last_checked_at = NOW(),
                next_check_at = ?,
                last_status_message = ?,
                updated_at = NOW()
            WHERE application_id = ?
        ");
        $upd->execute([
            $nextCheckAt,
            'Portal Communication Issue: ' . $errorMessage . ' (Application remains In Process; will retry in 5 hours)',
            $appId
        ]);

        AuditService::log('RTPS_PORTAL_CHECK_ERROR', 'APPLICATION', (string)$appId, [
            'error' => $errorMessage,
            'next_check_at' => $nextCheckAt
        ], $actorUserId, 'SYSTEM');
    }

    /**
     * Auto-enqueues any applications in IN_PROCESS or PENDING state with RTPS codes
     * that are not yet in rtps_certificate_queue
     */
    public static function syncMissingApplications(): int
    {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT a.id
            FROM applications a
            LEFT JOIN rtps_certificate_queue q ON q.application_id = a.id
            WHERE q.id IS NULL
              AND a.status IN ('IN_PROCESS', 'PENDING', 'UNDER_REVIEW')
              AND a.result_file IS NULL
              AND a.applicant_identifier IS NOT NULL
              AND a.applicant_identifier != ''
              AND (
                  a.application_code IN ('BRCCO', 'BCCCO', 'BICCO', 'BOBC', 'NCLCO', 'ROTI', 'RTPS')
                  OR a.applicant_identifier LIKE 'B%/%'
              )
            ORDER BY a.id DESC
            LIMIT 100
        ");
        $missing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($missing as $appId) {
            if (self::enqueueApplication((int)$appId)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Process scheduled queue checks (12:00 AM scheduled runner or manual trigger)
     */
    public static function processScheduledQueue(int $limit = 50, bool $force = false): array
    {
        // First sync any missing applications
        self::syncMissingApplications();

        $db = Database::getConnection();

        $sql = "
            SELECT q.*, a.status as app_status, a.app_ref, a.result_file,
                   d.name as district_name, b.name as block_name
            FROM rtps_certificate_queue q
            JOIN applications a ON a.id = q.application_id
            LEFT JOIN districts d ON d.id = q.district_id
            LEFT JOIN blocks b ON b.id = q.block_id
            WHERE a.status IN ('IN_PROCESS', 'PENDING', 'UNDER_REVIEW')
              AND q.status != 'ATTACHED'
        ";

        if (!$force) {
            $sql .= " AND q.next_check_at <= NOW()";
        }

        $sql .= " ORDER BY q.next_check_at ASC LIMIT " . (int)$limit;

        $items = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;
        $ready = 0;
        $unavailable = 0;
        $errors = 0;

        foreach ($items as $item) {
            $processed++;
            $appId = (int)$item['application_id'];

            // Mark status CHECKING
            $db->prepare("UPDATE rtps_certificate_queue SET status = 'CHECKING', updated_at = NOW() WHERE id = ?")
               ->execute([$item['id']]);

            try {
                $candidateDates = [];
                if (!empty($item['app_created_at'])) {
                    $candidateDates[] = date('d/m/Y', strtotime((string)$item['app_created_at']));
                }
                $candidateDates[] = date('d/m/Y');
                $candidateDates[] = date('d/m/Y', strtotime('-1 day'));

                $fatherName = '';
                $motherName = '';
                try {
                    $fStmt = $db->prepare("SELECT field_name, field_value FROM application_field_values WHERE application_id = ?");
                    $fStmt->execute([$appId]);
                    while ($fRow = $fStmt->fetch(PDO::FETCH_ASSOC)) {
                        $fName = strtolower(trim((string)$fRow['field_name']));
                        $fVal = trim((string)$fRow['field_value']);
                        if (str_contains($fName, 'father')) $fatherName = $fVal;
                        if (str_contains($fName, 'mother')) $motherName = $fVal;
                        if (str_contains($fName, 'date') && !empty($fVal)) $candidateDates[] = $fVal;
                    }
                } catch (\Throwable $e) {}

                // Fetch certificate from Bihar RTPS official portal (Live ServiceOnline First, SWCS Fallback)
                $fetchRes = self::fetchCertificateFromPortal(
                    (string)$item['application_number'],
                    (string)$item['applicant_name'],
                    (string)$item['service_type'],
                    $candidateDates,
                    $fatherName,
                    $motherName
                );

                if (!empty($fetchRes['success']) && !empty($fetchRes['pdf'])) {
                    self::attachCertificatePdf($appId, $fetchRes['pdf'], null, null, 'SYSTEM');
                    $ready++;
                } else {
                    self::markCertificateUnavailable(
                        $appId,
                        $fetchRes['message'] ?? 'Certificate pending on portal; next check scheduled in 5 hours.'
                    );
                    $unavailable++;
                }
            } catch (\Throwable $e) {
                self::markPortalError($appId, 'Verification error: ' . $e->getMessage());
                $errors++;
            }
        }

        return [
            'total_eligible' => count($items),
            'processed' => $processed,
            'ready' => $ready,
            'unavailable' => $unavailable,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Automatic non-blocking background queue runner for web requests.
     * Evaluates due items (next_check_at <= NOW()) if at least 60 seconds have elapsed since last tick.
     */
    public static function autoCheckDueQueue(int $limit = 10): ?array
    {
        $tickFile = dirname(__DIR__, 2) . '/storage/locks/rtps_auto_tick.time';
        $nowTs = time();

        if (file_exists($tickFile)) {
            $lastRun = (int)@file_get_contents($tickFile);
            if (($nowTs - $lastRun) < 60) {
                // Rate-limited: ran within last 60 seconds
                return null;
            }
        }

        try {
            $db = Database::getConnection();
            $dueCount = (int)$db->query("
                SELECT COUNT(*) 
                FROM rtps_certificate_queue q
                JOIN applications a ON a.id = q.application_id
                WHERE a.status IN ('IN_PROCESS', 'PENDING', 'UNDER_REVIEW')
                  AND q.status != 'ATTACHED'
                  AND q.next_check_at <= NOW()
            ")->fetchColumn();

            if ($dueCount <= 0) {
                return null;
            }

            @file_put_contents($tickFile, (string)$nowTs);
            return self::processScheduledQueue($limit, false);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get queue statistics for Admin & Operator Dashboards
     */
    public static function getQueueStats(): array
    {
        self::syncMissingApplications();
        $db = Database::getConnection();

        $row = $db->query("
            SELECT
                COUNT(*) as total_queued,
                SUM(CASE WHEN q.status IN ('QUEUED', 'PENDING_CHECK') THEN 1 ELSE 0 END) as pending_check,
                SUM(CASE WHEN q.status = 'CHECKING' THEN 1 ELSE 0 END) as checking,
                SUM(CASE WHEN q.status = 'UNAVAILABLE' THEN 1 ELSE 0 END) as unavailable,
                SUM(CASE WHEN q.status = 'ATTACHED' THEN 1 ELSE 0 END) as attached_completed,
                SUM(CASE WHEN q.status = 'FAILED' THEN 1 ELSE 0 END) as failed_retrying,
                SUM(CASE WHEN q.next_check_at <= NOW() AND q.status != 'ATTACHED' THEN 1 ELSE 0 END) as due_now
            FROM rtps_certificate_queue q
            JOIN applications a ON a.id = q.application_id
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'total_queued' => (int)($row['total_queued'] ?? 0),
            'pending_check' => (int)($row['pending_check'] ?? 0),
            'checking' => (int)($row['checking'] ?? 0),
            'unavailable' => (int)($row['unavailable'] ?? 0),
            'attached_completed' => (int)($row['attached_completed'] ?? 0),
            'failed_retrying' => (int)($row['failed_retrying'] ?? 0),
            'due_now' => (int)($row['due_now'] ?? 0)
        ];
    }

    /**
     * Get list of queue items with filters and pagination
     */
    public static function getQueueItems(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        self::syncMissingApplications();
        $db = Database::getConnection();

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "q.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['district_id'])) {
            $where[] = "q.district_id = ?";
            $params[] = (int)$filters['district_id'];
        }

        if (!empty($filters['block_id'])) {
            $where[] = "q.block_id = ?";
            $params[] = (int)$filters['block_id'];
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(q.application_number LIKE ? OR q.applicant_name LIKE ? OR a.app_ref LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $db->prepare("
            SELECT COUNT(*)
            FROM rtps_certificate_queue q
            JOIN applications a ON a.id = q.application_id
            WHERE {$whereSql}
        ");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);

        $stmt = $db->prepare("
            SELECT q.*,
                   a.app_ref, a.status as app_status, a.result_file, a.created_at as app_created_at,
                   d.name as district_name, b.name as block_name,
                   u.name as retailer_name, u.mobile as retailer_mobile
            FROM rtps_certificate_queue q
            JOIN applications a ON a.id = q.application_id
            JOIN users u ON u.id = a.user_id
            LEFT JOIN districts d ON d.id = q.district_id
            LEFT JOIN blocks b ON b.id = q.block_id
            WHERE {$whereSql}
            ORDER BY q.next_check_at ASC, q.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage))
        ];
    }
}
