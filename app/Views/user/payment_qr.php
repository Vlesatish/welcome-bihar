<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row justify-content-center py-3">
    <div class="col-md-7 col-lg-5">
        <div class="qr-payment-box" id="payment-qr-container" data-order-id="<?= e($order['order_id']) ?>">

            <!-- Header Badge -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1 rounded-pill small">
                    <i class="bi bi-shield-lock-fill me-1"></i> UPI Scan &amp; Pay
                </span>
                <span id="payment-live-status" class="badge bg-warning text-dark p-2 fs-7">
                    <span class="spinner-grow spinner-grow-sm me-1"></span>
                    <?= e(__('awaiting_payment')) ?>
                </span>
            </div>

            <!-- Amount Display -->
            <div class="mb-2">
                <div class="text-muted small text-uppercase fw-semibold">Amount to Pay</div>
                <div class="display-5 fw-bold text-success">₹<?= number_format($order['amount'], 2) ?></div>
            </div>

            <!-- Order Reference -->
            <div class="p-2 bg-light rounded-3 border d-inline-flex align-items-center gap-2 mb-3">
                <span class="small text-muted font-monospace"><?= e($order['order_id']) ?></span>
                <button type="button" class="btn btn-xs btn-outline-secondary btn-copy-clipboard py-0 px-1"
                        data-copy="<?= e($order['order_id']) ?>" title="Copy Order ID">
                    <i class="bi bi-copy"></i>
                </button>
            </div>

            <!-- Generated QR Code Image -->
            <div class="qr-image-wrapper">
                <div class="pulse-border"></div>
                <?php if (!empty($order['qr_image_url'])): ?>
                    <img src="<?= e($order['qr_image_url']) ?>" alt="UPI QR Code" id="upi_qr_img">
                <?php else: ?>
                    <div style="width:240px; height:240px;" class="d-flex align-items-center justify-content-center bg-light text-muted">
                        <span>QR Code Loading...</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Supported UPI Apps Icons / Labels -->
            <div class="d-flex justify-content-center align-items-center gap-3 mb-3 text-secondary small">
                <span class="fw-semibold"><i class="bi bi-phone me-1"></i> Google Pay</span> &bull;
                <span class="fw-semibold">PhonePe</span> &bull;
                <span class="fw-semibold">Paytm</span> &bull;
                <span class="fw-semibold">BHIM UPI</span>
            </div>

            <!-- Clear Instructions -->
            <p class="small text-muted mb-4 px-2">
                <?= e(__('scan_instructions')) ?>
            </p>

            <!-- Status Alert Container (updated by AJAX polling) -->
            <div id="payment-status-message" class="alert alert-info py-2 small mb-4">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Listening for verified merchant payment confirmation...
            </div>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="button" id="btn-manual-check-status" class="btn btn-primary fw-bold py-2 shadow-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i> <?= e(__('check_status_now')) ?>
                </button>

                <?php if (!empty($order['payment_url'])): ?>
                    <a href="<?= e($order['payment_url']) ?>" class="btn btn-outline-secondary btn-sm py-2">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Open Directly in UPI App (Mobile)
                    </a>
                <?php endif; ?>

                <a href="/wallet/passbook" class="btn btn-link text-muted small text-decoration-none mt-2">
                    Back to Wallet Passbook
                </a>
            </div>

            <!-- Provider Notice -->
            <div class="mt-4 pt-3 border-top text-muted" style="font-size: 0.75rem;">
                <i class="bi bi-info-circle me-1"></i> <?= e(__('merchant_notice')) ?>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
