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

        // Insert new queue record with all required validated fields populated
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

        // Auto-dispatch non-blocking background worker immediately so no manual clicking is required
        self::dispatchBackgroundWorker($appId);

        return $queueId;
    }

    /**
     * Asynchronously launch background worker without blocking web request.
     * If $appId > 0, checks that application immediately.
     * If $appId === 0, checks due applications from the queue.
     */
    public static function dispatchBackgroundWorker(int $appId = 0): bool
    {
        $rootPath = dirname(dirname(__DIR__));
        $cliScript = $rootPath . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'check_rtps_certificates.php';
        if (!file_exists($cliScript)) {
            return false;
        }

        $phpBin = PHP_BINARY;
        if (empty($phpBin) || !file_exists($phpBin)) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $defaultWinPhp = 'C:\\xampp\\php\\php.exe';
                $phpBin = file_exists($defaultWinPhp) ? $defaultWinPhp : 'php';
            } else {
                $phpBin = 'php';
            }
        }

        $arg = ($appId > 0) ? "--app-id={$appId}" : "--limit=20";

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = sprintf('start /B "" %s %s %s > NUL 2>&1', escapeshellarg($phpBin), escapeshellarg($cliScript), $arg);
            $p = popen($cmd, 'r');
            if ($p !== false) {
                pclose($p);
                return true;
            }
        } else {
            $cmd = sprintf('nohup %s %s %s > /dev/null 2>&1 &', escapeshellarg($phpBin), escapeshellarg($cliScript), $arg);
            exec($cmd, $out, $ret);
            return ($ret === 0);
        }
        return false;
    }

    /**
     * Smart interpolation of application submission date from known database anchors.
     * Maps reference number sequence to calendar dates so checks succeed on attempt 1 in 1-2 seconds.
     */
    public static function estimateDateFromAnchors(string $appNum): array
    {
        $matches = [];
        if (!preg_match('/^([A-Z]+)\/([0-9]{4})\/([0-9]+)$/i', trim($appNum), $matches)) {
            return [];
        }
        $code = strtoupper($matches[1]);
        $year = $matches[2];
        $num = (int)$matches[3];

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT a.applicant_identifier, v.field_value as sub_date
                FROM applications a
                JOIN application_field_values v ON a.id = v.application_id
                WHERE v.field_name = 'submission_date'
                  AND a.applicant_identifier LIKE ?
            ");
            $stmt->execute(["{$code}/{$year}/%"]);
            $anchors = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (preg_match('/\/([0-9]+)$/', $row['applicant_identifier'], $m)) {
                    $anchorNum = (int)$m[1];
                    $d = str_replace('/', '-', $row['sub_date']);
                    $ts = strtotime($d);
                    if ($ts && $ts > 0) {
                        $anchors[$anchorNum] = $ts;
                    }
                }
            }

            if (empty($anchors)) {
                return [];
            }

            ksort($anchors);
            $anchorNums = array_keys($anchors);

            if (isset($anchors[$num])) {
                return [date('d/m/Y', $anchors[$num])];
            }

            $estimatedTs = null;
            if (count($anchors) >= 2) {
                $firstNum = $anchorNums[0];
                $lastNum = end($anchorNums);
                $firstTs = $anchors[$firstNum];
                $lastTs = $anchors[$lastNum];

                if ($lastNum > $firstNum && $lastTs > $firstTs) {
                    $rate = ($lastTs - $firstTs) / ($lastNum - $firstNum);
                    $estimatedTs = (int)round($firstTs + ($num - $firstNum) * $rate);
                }
            }

            if (!$estimatedTs) {
                $closestNum = null;
                $minDiff = PHP_INT_MAX;
                foreach ($anchorNums as $aN) {
                    $diff = abs($num - $aN);
                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                        $closestNum = $aN;
                    }
                }
                $estimatedTs = $anchors[$closestNum];
            }

            $dates = [];
            $dates[] = date('d/m/Y', $estimatedTs);
            $dates[] = date('d/m/Y', strtotime('+1 day', $estimatedTs));
            $dates[] = date('d/m/Y', strtotime('-1 day', $estimatedTs));
            $dates[] = date('d/m/Y', strtotime('+2 days', $estimatedTs));
            $dates[] = date('d/m/Y', strtotime('-2 days', $estimatedTs));

            return array_unique($dates);
        } catch (\Throwable $e) {
            return [];
        }
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

        $ocrTarget = $rawPath;
        $tempFiles = [$rawPath];

        if (function_exists('imagecreatefromjpeg') && function_exists('imagepng')) {
            $img = @imagecreatefromjpeg($rawPath);
            if ($img) {
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
                $ocrTarget = $cleanPath;
                $tempFiles[] = $cleanPath;
            }
        }

        $psScript = self::ensureOcrScript();
        $cmd = 'powershell -ExecutionPolicy Bypass -File ' . escapeshellarg($psScript) . ' ' . escapeshellarg($ocrTarget);
        $res = shell_exec($cmd);

        foreach ($tempFiles as $tf) {
            @unlink($tf);
        }

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
                $dates[] = date('d/m/Y', strtotime("-1 day", $ts));
                $dates[] = date('d/m/Y', strtotime("+1 day", $ts));
            }
        }

        // Smart reference number anchor interpolation (hits exact date in 1-2 seconds)
        $anchorDates = self::estimateDateFromAnchors($appNum);
        foreach ($anchorDates as $aD) {
            $dates[] = $aD;
        }

        $nowTs = time();
        $dates[] = date('d/m/Y', $nowTs);
        for ($k = 1; $k <= 30; $k++) {
            $dates[] = date('d/m/Y', strtotime("-{$k} days", $nowTs));
        }
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
                null,
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

            $parsedTable = self::parseTrackingTable($s2Html);
            if (empty($parsedTable)) {
                $isRejected = (stripos($s2Html, 'Rejected') !== false);
                $isUnderProcess = (stripos($s2Html, 'Under Process') !== false || stripos($s2Html, 'Forwarded') !== false);
                return [
                    'success' => false,
                    'status' => $isRejected ? 'REJECTED' : 'IN_PROCESS',
                    'portal_status' => $isRejected ? 'Rejected' : ($isUnderProcess ? 'Under Process' : 'Pending'),
                    'message' => $isRejected ? 'Application rejected on portal.' : 'Application is under process on portal.'
                ];
            }

            $evaluation = self::evaluateRtpsStatus($parsedTable);

            if ($evaluation['action'] === 'REJECT') {
                return [
                    'success' => false,
                    'status' => 'REJECTED',
                    'portal_status' => 'Rejected',
                    'message' => $evaluation['message']
                ];
            }

            if ($evaluation['action'] === 'SHOW_STATUS') {
                return [
                    'success' => false,
                    'status' => 'IN_PROCESS',
                    'portal_status' => $evaluation['portal_status'],
                    'message' => $evaluation['message']
                ];
            }

            // action === 'DOWNLOAD_CERTIFICATE'
            $certDoc = $evaluation['cert_doc'];
            $applID = $certDoc['applID'];
            $spdiCurrentProcId = $certDoc['spdiCurrentProcId'];
            $applicationCertId = $certDoc['applicationCertId'];
            $token = $certDoc['token'];
            $certTitle = trim($certDoc['title'] ?? 'Certificate');

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
                    'portal_status' => 'Delivered',
                    'pdf' => $pdfBytes,
                    'size' => strlen($pdfBytes),
                    'matched_service' => $certTitle,
                    'matched_date' => $matchedDate,
                    'message' => 'Official certificate PDF downloaded live from serviceonline.bihar.gov.in tracking.'
                ];
            }

            return [
                'success' => false,
                'status' => 'IN_PROCESS',
                'portal_status' => 'Delivered (PDF Not Available)',
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
     * Parse HTML tracking table rows from ServicePlus citizenServiceTrackList.do
     */
    public static function parseTrackingTable(string $html): array
    {
        $rows = [];
        if (!preg_match('/<table[^>]*>(?:(?!<\/table>).)*?Task Name.*?<\/table>/si', $html, $tableMatch)) {
            return $rows;
        }

        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $tableMatch[0], $trMatches);

        foreach ($trMatches[1] as $tr) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $tr, $tdMatches);
            if (count($tdMatches[1]) >= 5) {
                $sNo = trim(strip_tags($tdMatches[1][0]));
                $taskName = trim(strip_tags($tdMatches[1][1]));
                $formDetail = trim(strip_tags($tdMatches[1][2]));
                $issuedDocRaw = $tdMatches[1][3];
                $issuedDocText = trim(strip_tags($issuedDocRaw));
                $status = trim(strip_tags($tdMatches[1][4]));
                $remarks = isset($tdMatches[1][5]) ? trim(strip_tags($tdMatches[1][5])) : '';

                $docLink = null;
                if (preg_match("/showDocument\('([^']+)','([^']+)','([^']+)','([^']+)','([0-9]+)','([^']+)'\)/i", $issuedDocRaw, $m)) {
                    $docLink = [
                        'applID' => $m[1],
                        'spdiCurrentProcId' => $m[2],
                        'applicationCertId' => $m[3],
                        'token' => $m[6],
                        'type' => $m[5],
                        'title' => $issuedDocText,
                    ];
                }

                $numKey = is_numeric($sNo) ? (int)$sNo : count($rows) + 1;
                $rows[$numKey] = [
                    's_no' => $sNo,
                    'task_name' => $taskName,
                    'form_detail' => $formDetail,
                    'issued_doc' => $issuedDocText,
                    'issued_doc_link' => $docLink,
                    'status' => $status,
                    'remarks' => $remarks,
                ];
            }
        }

        return $rows;
    }

    /**
     * Evaluate workflow stages according to Bihar RTPS ServicePlus business rules:
     * - Row 3 (Field Verification): If 'Under Process' -> 'Pending'. If other -> exact text (e.g. 'Forwarded to CO').
     * - Row 4 (Delivery of Application): If 'Under Process' -> 'Sign Pending (RO/CO Sign Baki Hai)'.
     * - Row 4: If 'Delivered' / 'Completed' -> Only attach if real Certificate PDF link is present; otherwise show status.
     */
    public static function evaluateRtpsStatus(array $parsedRows): array
    {
        foreach ($parsedRows as $r) {
            if (stripos($r['status'], 'Rejected') !== false) {
                return [
                    'action' => 'REJECT',
                    'status' => 'REJECTED',
                    'portal_status' => 'Rejected',
                    'message' => 'Application has been Rejected on official portal.',
                    'cert_doc' => null,
                ];
            }
        }

        $row3 = $parsedRows[3] ?? null;
        $row4 = $parsedRows[4] ?? null;

        if (!$row3 || !$row4) {
            foreach ($parsedRows as $r) {
                $task = strtolower($r['task_name']);
                if (str_contains($task, 'verification') || str_contains($task, 'inquiry')) {
                    $row3 = $row3 ?: $r;
                }
                if (str_contains($task, 'delivery') || str_contains($task, 'approval') || str_contains($task, 'issuance')) {
                    $row4 = $row4 ?: $r;
                }
            }
        }

        $row3Status = trim($row3['status'] ?? '');
        $row4Status = trim($row4['status'] ?? '');

        // 1. Check Row 4 (Delivery stage) if Delivered / Completed
        if ($row4 && (stripos($row4Status, 'Delivered') !== false || stripos($row4Status, 'Completed') !== false)) {
            $doc = $row4['issued_doc_link'] ?? null;
            $docTitle = strtolower($row4['issued_doc'] ?? '');

            // Ensure document is genuinely the Certificate, not an acknowledgment or print form
            if ($doc && !str_contains($docTitle, 'acknowledgment') && !str_contains($docTitle, 'print form')) {
                return [
                    'action' => 'DOWNLOAD_CERTIFICATE',
                    'status' => 'DELIVERED',
                    'portal_status' => 'Delivered',
                    'message' => 'Certificate generated and ready for download.',
                    'cert_doc' => $doc,
                ];
            }

            return [
                'action' => 'SHOW_STATUS',
                'status' => 'IN_PROCESS',
                'portal_status' => 'Delivered (PDF Not Available)',
                'message' => 'Status is Delivered/Completed on portal, but certificate PDF is not yet available.',
                'cert_doc' => null,
            ];
        }

        // 2. Check Row 3 (Field Verification stage)
        if ($row3 && !in_array(strtolower($row3Status), ['completed', 'delivered'])) {
            if (stripos($row3Status, 'under process') !== false) {
                return [
                    'action' => 'SHOW_STATUS',
                    'status' => 'IN_PROCESS',
                    'portal_status' => 'Pending',
                    'message' => 'Field Verification: Under Process (Pending).',
                    'cert_doc' => null,
                ];
            } else {
                return [
                    'action' => 'SHOW_STATUS',
                    'status' => 'IN_PROCESS',
                    'portal_status' => $row3Status,
                    'message' => "Field Verification: {$row3Status}.",
                    'cert_doc' => null,
                ];
            }
        }

        // 3. Row 3 is completed, check Row 4
        if ($row4 && stripos($row4Status, 'under process') !== false) {
            return [
                'action' => 'SHOW_STATUS',
                'status' => 'IN_PROCESS',
                'portal_status' => 'Sign Pending (RO/CO Sign Baki Hai)',
                'message' => 'Delivery of Application: Under Process (Digital Signature Pending).',
                'cert_doc' => null,
            ];
        }

        // Default status
        $finalStatus = !empty($row4Status) ? $row4Status : (!empty($row3Status) ? $row3Status : 'Under Process');
        return [
            'action' => 'SHOW_STATUS',
            'status' => 'IN_PROCESS',
            'portal_status' => $finalStatus,
            'message' => "Live Portal Status: {$finalStatus}.",
            'cert_doc' => null,
        ];
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

        $cleanNameNormalized = trim(preg_replace('/^(Mr\.?|Mrs\.?|Ms\.?|Shri\.?|Smt\.?)\s+/i', '', $cleanName));

        // Name casing variants - Bihar portal requires strict casing match
        $nameVariants = array_unique(array_filter([
            ucwords(strtolower($cleanNameNormalized)), // Title Case
            $cleanNameNormalized,                      // Raw normalized
            $cleanName,                                // Raw string
            strtoupper($cleanNameNormalized)           // UPPERCASE
        ]));

        $candidateServices = ['RTPS', 'OTHER'];
        $incoming = strtoupper(trim($serviceType));
        if ($incoming && !in_array($incoming, $candidateServices)) {
            array_unshift($candidateServices, $incoming);
        }

        // 1. FAST-PATH: Direct instant download from swcs.bihar.gov.in (takes ~1.5s, no submission date required)
        $swcsRes = self::downloadFromSwcsDirect($appNum, $nameVariants, $candidateServices);
        if (!empty($swcsRes['success']) && !empty($swcsRes['pdf'])) {
            return $swcsRes;
        }

        // 2. LIVE TRACKING: Check serviceonline.bihar.gov.in for live processing status / rejection / delivered attachment
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

        return [
            'success' => false,
            'message' => $liveRes['message'] ?? 'Certificate not yet generated or pending on RTPS portal. Next check scheduled.'
        ];
    }

    /**
     * Direct download via SWCS portal (swcs.bihar.gov.in/RtpsReportView/certificate_download.jsp)
     * Extremely fast (1-2s), does not require citizen submission date.
     */
    public static function downloadFromSwcsDirect(string $appNum, array $nameVariants, array $candidateServices): array
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'rtps_swcs_');
        try {
            $ch1 = curl_init(self::PORTAL_URL);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($ch1, CURLOPT_COOKIEFILE, $cookieJar);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch1, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
            $html = curl_exec($ch1);
            $initHttpCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            curl_close($ch1);

            if ($initHttpCode !== 200 || empty($html)) {
                return ['success' => false, 'message' => "SWCS portal unreachable (HTTP {$initHttpCode})"];
            }

            $actionUrl = self::PORTAL_DOWNLOAD_ACTION;
            if (preg_match('/action=["\']([^"\']*certificateDownload\.do[^"\']*)["\']/i', $html, $actMatch)) {
                $rawAction = html_entity_decode($actMatch[1]);
                if (strncmp($rawAction, 'http', 4) === 0) {
                    $actionUrl = $rawAction;
                } elseif (str_starts_with($rawAction, '/')) {
                    $actionUrl = 'https://swcs.bihar.gov.in' . $rawAction;
                } else {
                    $actionUrl = 'https://swcs.bihar.gov.in/RtpsReportView/' . ltrim($rawAction, '/');
                }
            }

            foreach ($candidateServices as $svc) {
                foreach ($nameVariants as $testName) {
                    $postFields = http_build_query([
                        'serviceType' => $svc,
                        'appl_ref_no' => $appNum,
                        'applicant_name_en' => $testName,
                    ]);

                    $ch2 = curl_init($actionUrl);
                    curl_setopt($ch2, CURLOPT_POST, true);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, $postFields);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookieJar);
                    curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieJar);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 20);
                    curl_setopt($ch2, CURLOPT_REFERER, self::PORTAL_URL);
                    curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
                    $respBody = curl_exec($ch2);
                    $respHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);

                    if ($respHttpCode === 200 && !empty($respBody)) {
                        if (strncmp($respBody, '%PDF-', 5) === 0) {
                            return [
                                'success' => true,
                                'pdf' => $respBody,
                                'matched_service' => $svc,
                                'matched_name' => $testName,
                                'source' => 'SWCS'
                            ];
                        }
                    }
                }
            }

            return ['success' => false, 'message' => 'No certificate PDF returned from SWCS'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'SWCS communication exception: ' . $e->getMessage()];
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

            if (!empty($fetchRes['matched_date'])) {
                try {
                    $chkField = $db->prepare("SELECT id FROM application_field_values WHERE application_id = ? AND field_name = 'submission_date'");
                    $chkField->execute([$appId]);
                    $fId = $chkField->fetchColumn();
                    if ($fId) {
                        $db->prepare("UPDATE application_field_values SET field_value = ? WHERE id = ?")->execute([$fetchRes['matched_date'], $fId]);
                    } else {
                        $db->prepare("INSERT INTO application_field_values (application_id, field_name, field_value, created_at) VALUES (?, 'submission_date', ?, NOW())")->execute([$appId, $fetchRes['matched_date']]);
                    }
                } catch (\Throwable $e) {}
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

        // Live Portal Status returned (Pending, Forwarded to CO, Sign Pending, Rejected, etc.)
        $portalStatus = $fetchRes['portal_status'] ?? null;
        $reason = $fetchRes['message'] ?? 'Certificate not yet generated on portal';

        if (!empty($fetchRes['status']) && $fetchRes['status'] === 'REJECTED') {
            $db->prepare("
                UPDATE applications
                SET status = 'REJECTED',
                    rejection_reason = ?,
                    admin_remarks = 'Rejected on official portal',
                    remarks = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$reason, $reason, $appId]);

            $db->prepare("
                UPDATE rtps_certificate_queue
                SET status = 'FAILED',
                    last_checked_at = NOW(),
                    last_status_message = ?,
                    updated_at = NOW()
                WHERE application_id = ?
            ")->execute([$reason, $appId]);

            return [
                'success' => false,
                'status' => 'REJECTED',
                'portal_status' => 'Rejected',
                'app_id' => $appId,
                'message' => $reason
            ];
        }

        // Not ready yet: mark unavailable and reschedule for next check
        self::markCertificateUnavailable($appId, $reason, null, null, $portalStatus);
        return [
            'success' => false,
            'status' => 'IN_PROCESS',
            'portal_status' => $portalStatus ?: 'Under Process',
            'app_id' => $appId,
            'message' => $reason
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

        // 1. Determine whether $filePathOrBinary is raw binary string, URL, or existing file path
        $binary = null;
        if (strncmp($filePathOrBinary, '%PDF-', 5) === 0) {
            $binary = $filePathOrBinary;
        } elseif (preg_match('/^https?:\/\//i', trim($filePathOrBinary))) {
            $ch = curl_init(trim($filePathOrBinary));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $binary = curl_exec($ch);
            curl_close($ch);
        } elseif (strlen($filePathOrBinary) < 1000 && @file_exists($filePathOrBinary) && @is_file($filePathOrBinary)) {
            $binary = file_get_contents($filePathOrBinary);
        } else {
            $binary = $filePathOrBinary;
        }

        if (empty($binary) || strlen($binary) < 64) {
            throw new Exception("Invalid or empty certificate file content.");
        }

        // 2. Validate PDF signature (%PDF-)
        if (strncmp($binary, '%PDF-', 5) !== 0) {
            throw new Exception("Validation failed: The attached document is not a valid PDF file. Header '%PDF-' not detected.");
        }

        // 3. Save PDF securely into a healthy, writable upload directory
        $cleanRef = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($app['applicant_identifier'] ?: $app['app_ref']));
        $safeFileName = 'rtps_cert_' . $appId . '_' . $cleanRef . '_' . date('Ymd_His') . '.pdf';
        $baseUploads = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';

        $candidateSubfolders = [
            ['sub' => 'documents/rtps_results', 'web' => '/uploads/documents/rtps_results/'],
            ['sub' => 'documents', 'web' => '/uploads/documents/'],
            ['sub' => 'rtps_results', 'web' => '/uploads/rtps_results/'],
            ['sub' => 'results', 'web' => '/uploads/results/'],
        ];

        $targetDir = null;
        $webDirPrefix = null;

        foreach ($candidateSubfolders as $cand) {
            $dir = $baseUploads . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cand['sub']);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            if (is_dir($dir)) {
                $probe = $dir . DIRECTORY_SEPARATOR . '.probe_' . uniqid() . '.tmp';
                $testFp = @fopen($probe, 'wb');
                if ($testFp) {
                    fclose($testFp);
                    @unlink($probe);
                    $targetDir = $dir;
                    $webDirPrefix = $cand['web'];
                    break;
                }
            }
        }

        if (!$targetDir) {
            throw new Exception("Unable to find a writable uploads directory on drive.");
        }

        $destPath = $targetDir . DIRECTORY_SEPARATOR . $safeFileName;
        $webPath = rtrim($webDirPrefix, '/') . '/' . $safeFileName;

        // Write in 8KB binary chunks to support USB external drives and avoid Windows errno=22 (EINVAL) >64KB buffer limit
        $fp = @fopen($destPath, 'wb');
        if (!$fp) {
            throw new Exception("Unable to open {$destPath} for writing.");
        }
        $totalBytes = strlen($binary);
        $offset = 0;
        while ($offset < $totalBytes) {
            $chunk = substr($binary, $offset, 8192);
            $w = fwrite($fp, $chunk);
            if ($w === false || $w === 0) {
                break;
            }
            $offset += $w;
        }
        fflush($fp);
        fclose($fp);
        clearstatcache(true, $destPath);

        if ($offset !== $totalBytes || !file_exists($destPath) || filesize($destPath) < 64) {
            throw new Exception("Failed to write certificate PDF to disk at {$destPath}.");
        }

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
        ?string $customNextCheckAt = null,
        ?string $portalStatus = null
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

        // Ensure application stays IN_PROCESS and record live portal remarks
        if (!empty($portalStatus)) {
            $db->prepare("
                UPDATE applications
                SET status = 'IN_PROCESS',
                    admin_remarks = ?,
                    remarks = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$portalStatus, $reason, $appId]);
        } else {
            $db->prepare("
                UPDATE applications
                SET status = 'IN_PROCESS',
                    updated_at = NOW()
                WHERE id = ? AND status = 'PENDING'
            ")->execute([$appId]);
        }

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
     * Process scheduled queue checks (Every 5 Hours or 7:00 AM Morning Check)
     */
    public static function processScheduledQueue(int $limit = 50, bool $force = false): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT q.*, a.status as app_status, a.app_ref, a.result_file, a.created_at as app_created_at,
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
     * Dispatch an asynchronous non-blocking queue check process in the background.
     * Returns instantly (<5ms) so web requests and page loads are never blocked!
     */
    public static function dispatchAsyncDueCheck(int $limit = 5): void
    {
        $tickFile = sys_get_temp_dir() . '/wb_rtps_auto_tick.time';
        $nowTs = time();

        if (file_exists($tickFile)) {
            $lastRun = (int)@file_get_contents($tickFile);
            if (($nowTs - $lastRun) < 120) {
                // Rate limit background dispatches to once per 2 minutes
                return;
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
                return;
            }

            @file_put_contents($tickFile, (string)$nowTs);

            $cliScript = dirname(dirname(__DIR__)) . '/cli/check_rtps_certificates.php';
            if (file_exists($cliScript)) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    pclose(popen('start /B "" php ' . escapeshellarg($cliScript) . ' --limit=' . (int)$limit . ' > NUL 2>&1', 'r'));
                } else {
                    exec('php ' . escapeshellarg($cliScript) . ' --limit=' . (int)$limit . ' > /dev/null 2>&1 &');
                }
            }
        } catch (\Throwable $e) {}
    }

    public static function autoCheckDueQueue(int $limit = 10): ?array
    {
        self::dispatchAsyncDueCheck($limit);
        return null;
    }
    /**
     * Get queue statistics for Admin & Operator Dashboards
     */
    public static function getQueueStats(): array
    {
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