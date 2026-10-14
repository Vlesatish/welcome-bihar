<?php
namespace App\Services;

interface PaymentProviderInterface
{
    /**
     * Generate UPI Scan & Pay QR code for an internal order
     *
     * @param string $orderId Internal unique order reference (e.g. ORD-20261011-XXXXX)
     * @param float $amount Top-up amount in INR
     * @param array $customer Customer information (name, mobile, email)
     * @return array [success => bool, qr_image_url => string, payment_url => string, provider_txn_id => string, raw => array, message => string]
     */
    public function createQrPayment(string $orderId, float $amount, array $customer): array;

    /**
     * Check status of UPI payment order with provider
     *
     * @param string $orderId Internal order reference
     * @return array [success => bool, status => 'PENDING'|'PAID'|'FAILED'|'EXPIRED', provider_txn_id => ?string, utr => ?string, raw => array, message => string]
     */
    public function checkPaymentStatus(string $orderId): array;

    /**
     * Validate incoming webhook or server callback
     *
     * @param array $payload Webhook POST payload
     * @param string|null $signature Optional signature or header
     * @param string|null $rawBody Optional raw HTTP request body
     * @return array [is_valid => bool, order_id => ?string, status => string, provider_txn_id => ?string, utr => ?string, event_id => string]
     */
    public function validateWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): array;

    /**
     * Service provider metadata and capabilities
     */
    public function getProviderInfo(): array;
}
