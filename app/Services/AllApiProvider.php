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
        $db = Database::getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'PAYMENT'");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $this->baseUrl = $settings['allapi_base_url'] ?? $_ENV['ALLAPI_BASE_URL'] ?? 'https://allapi.in';
        $this->createEndpoint = $settings['allapi_create_endpoint'] ?? $_ENV['ALLAPI_CREATE_ENDPOINT'] ?? '/order/create';
        $this->statusEndpoint = $settings['allapi_status_endpoint'] ?? $_ENV['ALLAPI_STATUS_ENDPOINT'] ?? '/order/status';
        $this->token = $_ENV['ALLAPI_TOKEN'] ?? $settings['allapi_token'] ?? '';
        $this->mode = strtoupper($settings['allapi_mode'] ?? $_ENV['ALLAPI_MODE'] ?? 'SANDBOX');
        $this->redirectUrl = $settings['allapi_redirect_url'] ?? $_ENV['ALLAPI_REDIRECT_URL'] ?? 'http://127.0.0.1:8080/wallet/topup/callback';
        $this->timeout = (int)($_ENV['ALLAPI_TIMEOUT_SECONDS'] ?? 10);
    }

    public function getProviderInfo(): array
    {
        return [
            'provider_name' => 'ALL API UPI Scan & Pay',
            'service_type' => 'UPI Scan & Pay QR Code Generation & Status Tracking',
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
        $customerEmail = !empty($customer['email']) && strpos($customer['email'], '@') !== false ? $customer['email'] : 'support@welcomebihar.gov.in';

        // Check if Sandbox / Demo mode or Token is not configured
        if ($this->mode === 'SANDBOX' || empty($this->token) || $this->token === 'mock_dev_token') {
            return $this->createSandboxQrPayment($orderId, $amount, $customerName);
        }

        // Live AllAPI call
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->createEndpoint, '/');
        $payload = [
            'token' => $this->token,
            'order_id' => $orderId,
            'txn_amount' => round($amount, 2),
            'txn_note' => "Topup " . $orderId,
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

        $response = $this->executeCurl($url, $payload);
        error_log("[AllAPI Response] " . json_encode($response));

        if (!empty($response['status']) && !empty($response['results'])) {
            $results = $response['results'];
            return [
                'success' => true,
                'qr_image_url' => $results['qr_image'] ?? $this->generateFallbackQrUrl($orderId, $amount),
                'payment_url' => $results['payment_url'] ?? '',
                'provider_txn_id' => (string)($results['txn_id'] ?? ''),
                'raw' => $response,
                'message' => 'UPI Scan & Pay QR generated successfully.'
            ];
        }

        // Fallback to sandbox if API error occurs
        $errMsg = $response['message'] ?? 'Unable to connect to ALL API UPI QR service.';
        return [
            'success' => false,
            'qr_image_url' => null,
            'payment_url' => null,
            'provider_txn_id' => null,
            'raw' => $response,
            'message' => $errMsg
        ];
    }

    public function checkPaymentStatus(string $orderId): array
    {
        if ($this->mode === 'SANDBOX' || empty($this->token) || $this->token === 'mock_dev_token') {
            return $this->checkSandboxPaymentStatus($orderId);
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->statusEndpoint, '/');
        $payload = [
            'token' => $this->token,
            'order_id' => $orderId
        ];

        $response = $this->executeCurl($url, $payload);
        error_log("[AllAPI Status Check] Order: {$orderId} | Response: " . json_encode($response));

        if (!empty($response['status']) && isset($response['results'])) {
            $res = $response['results'];
            $rawStatus = strtoupper($res['status'] ?? '');

            $status = match ($rawStatus) {
                'SUCCESS', 'PAID', 'COMPLETED' => 'PAID',
                'FAILED' => 'FAILED',
                'EXPIRED' => 'EXPIRED',
                default => 'PENDING'
            };

            return [
                'success' => true,
                'status' => $status,
                'provider_txn_id' => $res['txn_id'] ?? $res['bank_orderid'] ?? null,
                'utr' => $res['utr_number'] ?? $res['utr'] ?? null,
                'raw' => $response,
                'message' => $response['message'] ?? "Status: {$status}"
            ];
        }

        return [
            'success' => false,
            'status' => 'PENDING',
            'provider_txn_id' => null,
            'utr' => null,
            'raw' => $response,
            'message' => $response['message'] ?? 'Could not retrieve live status from provider.'
        ];
    }

    public function validateWebhook(array $payload, ?string $signature = null): array
    {
        $orderId = $payload['order_id'] ?? $payload['orderId'] ?? null;
        $txnId = $payload['transaction_id'] ?? $payload['txn_id'] ?? null;
        $utr = $payload['utr'] ?? $payload['utr_number'] ?? null;
        $rawStatus = strtoupper($payload['status'] ?? '');

        $status = match ($rawStatus) {
            'SUCCESS', 'PAID', 'COMPLETED', 'TRUE' => 'PAID',
            'FAILED' => 'FAILED',
            default => 'PENDING'
        };

        $eventId = 'ALLAPI_' . ($orderId ?: 'UNKNOWN') . '_' . ($txnId ?: date('YmdHis'));

        return [
            'is_valid' => !empty($orderId),
            'order_id' => $orderId,
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
            CURLOPT_SSL_VERIFYPEER => true
        ]);

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

    /**
     * Deterministic Sandbox QR Generator for Development and Verification
     */
    private function createSandboxQrPayment(string $orderId, float $amount, string $customerName): array
    {
        $qrImageUrl = $this->generateFallbackQrUrl($orderId, $amount);
        $mockTxnId = 'SANDBOX_' . date('Ymd') . '_' . substr(md5($orderId), 0, 8);

        return [
            'success' => true,
            'qr_image_url' => $qrImageUrl,
            'payment_url' => "upi://pay?pa=biharwelcome@upi&pn=WelcomeBihar&am=" . number_format($amount, 2, '.', '') . "&cu=INR&tn=" . urlencode($orderId),
            'provider_txn_id' => $mockTxnId,
            'raw' => [
                'mode' => 'SANDBOX',
                'status' => true,
                'results' => [
                    'order_id' => $orderId,
                    'txn_id' => $mockTxnId,
                    'qr_image' => $qrImageUrl,
                    'payment_url' => "upi://pay?pa=biharwelcome@upi&pn=WelcomeBihar&am=" . number_format($amount, 2, '.', '') . "&cu=INR&tn=" . urlencode($orderId)
                ],
                'message' => 'Sandbox UPI Scan & Pay QR generated. Scan with UPI App or verify in admin review.'
            ],
            'message' => 'Sandbox UPI Scan & Pay QR generated.'
        ];
    }

    private function checkSandboxPaymentStatus(string $orderId): array
    {
        // In sandbox mode, check if order is marked paid in topup_orders
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT status, provider_txn_id, provider_utr FROM topup_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();

        if ($row && $row['status'] === 'PAID') {
            return [
                'success' => true,
                'status' => 'PAID',
                'provider_txn_id' => $row['provider_txn_id'] ?? 'SANDBOX_TXN',
                'utr' => $row['provider_utr'] ?? 'SANDBOX_UTR',
                'raw' => ['mode' => 'SANDBOX', 'status' => 'PAID'],
                'message' => 'Order verified successfully in Sandbox.'
            ];
        }

        return [
            'success' => true,
            'status' => 'PENDING',
            'provider_txn_id' => null,
            'utr' => null,
            'raw' => ['mode' => 'SANDBOX', 'status' => 'PENDING'],
            'message' => 'Payment pending. Complete in UPI app or verify in Admin Order Reconciliation.'
        ];
    }

    private function generateFallbackQrUrl(string $orderId, float $amount): string
    {
        $upiUri = "upi://pay?pa=biharwelcome@upi&pn=WelcomeBihar&am=" . number_format($amount, 2, '.', '') . "&cu=INR&tn=" . urlencode($orderId);
        // Using standard Google Chart / QR Server API for rendering
        return "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($upiUri);
    }
}
