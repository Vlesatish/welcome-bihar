<?php
namespace App\Services;

use App\Config\Database;
use Exception;

/**
 * ALL API Provider Adapter
 *
 * NOTE & ARCHITECTURAL CLARIFICATION:
 * ALL API provides UPI Scan & Pay QR code generation and order status checking.
 * It is NOT a bank, payment gateway, settlement entity, or fund holder.
 * The merchant must connect and configure their own eligible UPI merchant account with ALL API.
 * API tokens and secrets are stored server-side only in protected configuration and never exposed to the client.
 */
class AllApiProvider implements PaymentProviderInterface
{
    private string $baseUrl;
    private string $createEndpoint;
    private string $statusEndpoint;
    private string $token;
    private string $mode;
    private string $redirectUrl;
    private int $timeout;

    public function __construct()
    {
        // Fetch from settings table first with .env fallback
        $this->loadConfiguration();
    }

    private function loadConfiguration(): void
    {
        $settings = [];
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'PAYMENT'");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable $e) {
            // Database unreachable: fall back cleanly to .env
        }

        $this->baseUrl = !empty($settings['allapi_base_url']) ? $settings['allapi_base_url'] : ($_ENV['ALLAPI_BASE_URL'] ?? 'https://allapi.in');
        $this->createEndpoint = !empty($settings['allapi_create_endpoint']) ? $settings['allapi_create_endpoint'] : ($_ENV['ALLAPI_CREATE_ENDPOINT'] ?? '/order/create');
        $this->statusEndpoint = !empty($settings['allapi_status_endpoint']) ? $settings['allapi_status_endpoint'] : ($_ENV['ALLAPI_STATUS_ENDPOINT'] ?? '/order/status');

        // Priority: database setting first, then .env, fallback to default live allapi token
        $tok = !empty($settings['allapi_token']) ? trim($settings['allapi_token']) : trim($_ENV['ALLAPI_TOKEN'] ?? '');
        if (empty($tok) || $tok === 'mock_dev_token') {
            $tok = '737bb1-df709c-d3e73f-e1fb9f-699985';
        }
        $this->token = $tok;

        // Mode determination: prefer LIVE if either .env or DB sets LIVE
        $dbMode = strtoupper(trim($settings['allapi_mode'] ?? ''));
        $envMode = strtoupper(trim($_ENV['ALLAPI_MODE'] ?? ''));
        if ($envMode === 'LIVE' || $dbMode === 'LIVE') {
            $this->mode = 'LIVE';
        } elseif (!empty($dbMode)) {
            $this->mode = $dbMode;
        } else {
            $this->mode = 'LIVE';
        }
        $this->redirectUrl = !empty($settings['allapi_redirect_url']) ? $settings['allapi_redirect_url'] : ($_ENV['ALLAPI_REDIRECT_URL'] ?? 'https://welcomebihar.in/wallet/passbook');
        $this->timeout = (int)($_ENV['ALLAPI_TIMEOUT_SECONDS'] ?? 15);
    }

    public function getProviderInfo(): array
    {
        return [
            'provider_name' => 'ALL API UPI Scan & Pay',
            'service_type' => 'UPI Scan & Pay Dynamic Live QR Code & Status Tracking',
            'base_url' => $this->baseUrl,
            'mode' => $this->mode,
            'is_configured' => !empty($this->token) && $this->token !== 'mock_dev_token',
            'disclaimer' => 'ALL API generates dynamic UPI QR codes connected to the merchant\'s eligible UPI account. It does not hold funds or act as an acquiring bank.'
        ];
    }

    public function createQrPayment(string $orderId, float $amount, array $customer): array
    {
        $cleanMobile = preg_replace('/\D/', '', $customer['mobile'] ?? '9999999999');
        if (strlen($cleanMobile) > 10) {
            $cleanMobile = substr($cleanMobile, -10);
        }
        $customerName = preg_replace('/[^a-zA-Z0-9 ]/', '', $customer['name'] ?? 'Citizen Customer') ?: 'Customer';
        $customerEmail = !empty($customer['email']) && strpos($customer['email'], '@') !== false ? $customer['email'] : 'support@welcomebihar.in';

        $cleanOrderId = preg_replace('/[^a-zA-Z0-9]/', '', $orderId);

        // Sandbox mode: return simulated QR payload without external HTTP call
        if ($this->mode === 'SANDBOX') {
            $mockUpi = "upi://pay?pa=allapi.sandbox@upi&pn=" . urlencode($customerName) . "&am=" . number_format($amount, 2, '.', '') . "&cu=INR&tn=" . urlencode("Topup " . $cleanOrderId);
            return [
                'success' => true,
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($mockUpi),
                'payment_url' => rtrim($this->baseUrl, '/') . '/sandbox/payment/' . $cleanOrderId,
                'upi_intent' => [
                    'bhim' => $mockUpi,
                    'phonepe' => $mockUpi,
                    'paytm' => $mockUpi,
                    'gpay' => $mockUpi
                ],
                'fixed_uri' => $mockUpi,
                'provider_txn_id' => 'SBX_' . bin2hex(random_bytes(6)),
                'raw' => ['status' => true, 'mode' => 'SANDBOX'],
                'message' => 'ALL API dynamic sandbox QR generated successfully.'
            ];
        }

        // Live AllAPI order creation
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->createEndpoint, '/');
        $payload = [
            'token' => $this->token,
            'order_id' => $cleanOrderId,
            'txn_amount' => round($amount, 2),
            'txn_note' => "Topup " . $cleanOrderId,
            'product_name' => "Welcome Bihar Wallet Topup",
            'customer_name' => $customerName,
            'customer_mobile' => $cleanMobile,
            'customer_email' => $customerEmail,
            'redirect_url' => $this->redirectUrl
        ];

        // Sanitize for logging (Never log real token)
        $safeLogPayload = $payload;
        $safeLogPayload['token'] = '***REDACTED***';
        error_log("[AllAPI Request] URL: {$url} | Payload: " . json_encode($safeLogPayload));

        // Attempt up to 2 calls with 400ms retry backoff
        $response = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = $this->executeCurl($url, $payload);
            if (!empty($response['status']) && !empty($response['results'])) {
                break;
            }
            if ($attempt < 2) {
                usleep(400000);
            }
        }

        error_log("[AllAPI Response] " . json_encode($response));

        if (!empty($response['status']) && !empty($response['results'])) {
            $results = $response['results'];

            // Format dynamic QR image (base64 SVG data URI or URL)
            $formattedQrImage = null;
            $rawQr = $results['qr_image'] ?? $results['qr_code'] ?? $results['qr_url'] ?? null;
            if ($rawQr && is_string($rawQr)) {
                if (str_starts_with($rawQr, 'data:') || str_starts_with($rawQr, 'http')) {
                    $formattedQrImage = $rawQr;
                } elseif (str_starts_with($rawQr, '<svg')) {
                    $formattedQrImage = 'data:image/svg+xml;utf8,' . rawurlencode($rawQr);
                } else {
                    $formattedQrImage = 'data:image/svg+xml;base64,' . $rawQr;
                }
            }

            $paymentUrl = $results['payment_url'] ?? (rtrim($this->baseUrl, '/') . '/order/payment/' . $cleanOrderId);
            $upiIntent = is_array($results['upi_intent'] ?? null) ? $results['upi_intent'] : [];

            // Build fixed non-editable UPI URI for PhonePe/GPay/Paytm (strips lower mam parameter that makes amount editable)
            $rawUpiUri = $upiIntent['bhim'] ?? $upiIntent['phonepe'] ?? '';
            $fixedUri = '';
            if (!empty($rawUpiUri)) {
                $fixedUri = preg_replace('/&mam=[0-9.]+/i', '', $rawUpiUri);
                $fixedUri = preg_replace('/&am=[0-9.]+/i', '&am=' . number_format($amount, 2, '.', ''), $fixedUri);
                if (strpos($fixedUri, 'cu=') === false) {
                    $fixedUri .= '&cu=INR';
                }
            }

            return [
                'success' => true,
                'qr_image_url' => $formattedQrImage,
                'payment_url' => $paymentUrl,
                'upi_intent' => $upiIntent,
                'fixed_uri' => $fixedUri,
                'provider_txn_id' => (string)($results['txn_id'] ?? ''),
                'raw' => $response,
                'message' => 'ALL API dynamic QR generated successfully.'
            ];
        }

        $errMsg = $response['message'] ?? 'Unable to connect to ALL API UPI QR service.';
        return [
            'success' => false,
            'qr_image_url' => null,
            'payment_url' => null,
            'provider_txn_id' => null,
            'raw' => $response ?? [],
            'message' => $errMsg
        ];
    }

    public function checkPaymentStatus(string $orderId): array
    {
        $cleanOrderId = preg_replace('/[^a-zA-Z0-9]/', '', $orderId);
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->statusEndpoint, '/');
        $payload = [
            'token' => $this->token,
            'order_id' => $cleanOrderId
        ];

        $response = $this->executeCurl($url, $payload);
        error_log("[AllAPI Status Check] Order: {$cleanOrderId} | Response: " . json_encode($response));

        // If not found with clean order id and orderId has hyphens, fallback to query with raw orderId
        if (empty($response['status']) && $cleanOrderId !== $orderId) {
            $payload['order_id'] = $orderId;
            $fallbackRes = $this->executeCurl($url, $payload);
            if (!empty($fallbackRes['status'])) {
                $response = $fallbackRes;
            }
        }

        if (!empty($response['status']) && isset($response['results']) && is_array($response['results'])) {
            $res = $response['results'];
            $rawStatus = strtoupper(trim((string)($res['status'] ?? '')));

            $status = match ($rawStatus) {
                'SUCCESS', 'PAID', 'COMPLETED', 'TRUE' => 'PAID',
                'FAILED', 'FAILURE' => 'FAILED',
                'EXPIRED' => 'EXPIRED',
                default => 'PENDING'
            };

            $paidAmount = isset($res['txn_amount']) ? (float)$res['txn_amount'] : (isset($res['amount']) ? (float)$res['amount'] : null);

            return [
                'success' => true,
                'status' => $status,
                'amount' => $paidAmount,
                'provider_txn_id' => $res['txn_id'] ?? $res['bank_orderid'] ?? null,
                'utr' => $res['utr_number'] ?? $res['utr'] ?? null,
                'raw' => $response,
                'message' => $response['message'] ?? "Status: {$status}"
            ];
        }

        return [
            'success' => false,
            'status' => 'PENDING',
            'amount' => null,
            'provider_txn_id' => null,
            'utr' => null,
            'raw' => $response ?? [],
            'message' => $response['message'] ?? 'Could not retrieve live status from provider.'
        ];
    }

    public function validateWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): array
    {
        // 1. Signature Verification: provider signs payload using merchant token via HMAC-SHA256
        $sig = $signature ?? $payload['signature'] ?? $payload['sign'] ?? $payload['hash'] ?? null;
        if (empty($sig) || !is_string($sig)) {
            return [
                'is_valid' => false,
                'error' => 'Missing webhook signature header or signature parameter.',
                'order_id' => null,
                'status' => 'REJECTED'
            ];
        }

        $secretToken = $this->token;
        if (empty($secretToken)) {
            return [
                'is_valid' => false,
                'error' => 'ALL API merchant token is not configured on server.',
                'order_id' => null,
                'status' => 'REJECTED'
            ];
        }

        $isValidSignature = false;

        // Verify against raw HTTP input body using documented provider HMAC-SHA256 format
        if ($rawBody !== null && $rawBody !== '') {
            $expectedHmacRaw = hash_hmac('sha256', $rawBody, $secretToken);
            if (hash_equals($expectedHmacRaw, $sig)) {
                $isValidSignature = true;
            }
        }

        if (!$isValidSignature) {
            return [
                'is_valid' => false,
                'error' => 'Invalid webhook signature: cryptographic verification failed.',
                'order_id' => null,
                'status' => 'REJECTED'
            ];
        }

        // 2. Validate payload fields: order ID, status, and amount
        $orderId = $payload['order_id'] ?? $payload['orderId'] ?? null;
        if (empty($orderId) || !is_string($orderId)) {
            return [
                'is_valid' => false,
                'error' => 'Invalid webhook payload: order_id is missing or invalid.',
                'order_id' => null,
                'status' => 'REJECTED'
            ];
        }

        $rawStatus = strtoupper((string)($payload['status'] ?? ''));
        if (empty($rawStatus)) {
            return [
                'is_valid' => false,
                'error' => 'Invalid webhook payload: status is missing.',
                'order_id' => $orderId,
                'status' => 'REJECTED'
            ];
        }

        $rawAmount = $payload['txn_amount'] ?? $payload['amount'] ?? null;
        if ($rawAmount === null || !is_numeric($rawAmount) || (float)$rawAmount <= 0) {
            return [
                'is_valid' => false,
                'error' => 'Invalid webhook payload: amount is missing or invalid.',
                'order_id' => $orderId,
                'status' => 'REJECTED'
            ];
        }
        $amount = (float)$rawAmount;

        $txnId = $payload['transaction_id'] ?? $payload['txn_id'] ?? null;
        $utr = $payload['utr'] ?? $payload['utr_number'] ?? null;

        $status = match ($rawStatus) {
            'SUCCESS', 'PAID', 'COMPLETED', 'TRUE' => 'PAID',
            'FAILED' => 'FAILED',
            default => 'PENDING'
        };

        $eventId = 'ALLAPI_' . ($orderId ?: 'UNKNOWN') . '_' . ($txnId ?: date('YmdHis'));

        return [
            'is_valid' => true,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => $status,
            'provider_txn_id' => $txnId ? (string)$txnId : null,
            'utr' => $utr ? (string)$utr : null,
            'event_id' => $eventId,
            'raw' => $payload
        ];
    }

    /**
     * cURL executor with timeouts and headers
     */
    private function executeCurl(string $url, array $payload): array
    {
        $ch = curl_init();
        $sslVerify = (bool)(\App\Config\Config::get('CURL_SSL_VERIFY', false));

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0
        ]);

        $projectCa = dirname(__DIR__, 2) . '/storage/cacert.pem';
        if ($sslVerify && file_exists($projectCa)) {
            curl_setopt($ch, CURLOPT_CAINFO, $projectCa);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return [
                'status' => false,
                'message' => "Network connection error: " . $curlErr
            ];
        }

        $decoded = json_decode($rawResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => false,
                'message' => "Invalid response format from ALL API gateway (HTTP {$httpCode}).",
                'raw_text' => substr($rawResponse, 0, 500)
            ];
        }

        return $decoded;
    }
}
