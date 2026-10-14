<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row justify-content-center py-3">
    <div class="col-md-7 col-lg-5">
        <div class="qr-payment-box" id="payment-qr-container" data-order-id="<?= e($order['order_id']) ?>">

            <!-- Header Badge -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1 rounded-pill small">
                    <i class="bi bi-shield-lock-fill me-1"></i> UPI Scan & Pay
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

            <!-- Order Reference Card (Prominent & Clear) -->
            <div class="p-2.5 bg-light rounded-3 border d-flex justify-content-between align-items-center mb-3">
                <div class="text-start">
                    <span class="text-muted text-uppercase d-block" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.5px;">Order ID</span>
                    <span class="font-monospace fw-bold text-dark fs-6" id="order-id-display"><?= e($order['order_id']) ?></span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-copy-clipboard px-2.5 py-1 fw-semibold shadow-2xs"
                        data-copy="<?= e($order['order_id']) ?>" title="Copy Order ID">
                    <i class="bi bi-copy me-1"></i> Copy ID
                </button>
            </div>

            <!-- Generated QR Code Image -->
            <div class="qr-image-wrapper">
                <div class="pulse-border"></div>
                <?php if (!empty($order['qr_image_url']) && $order['status'] !== 'FAILED'): ?>
                    <img src="<?= e($order['qr_image_url']) ?>" alt="UPI QR Code" id="upi_qr_img">
                <?php else: ?>
                    <div style="width:240px; height:240px;" class="d-flex flex-column align-items-center justify-content-center bg-light text-danger p-3 text-center border rounded">
                        <i class="bi bi-exclamation-triangle fs-2 mb-2 text-danger"></i>
                        <span class="fw-bold small">QR Generation Failed</span>
                        <a href="/wallet/topup" class="btn btn-sm btn-outline-danger mt-2">Retry Top-Up</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Supported UPI Apps / Mobile 1-Click Pay -->
            <div class="mb-3">
                <span class="text-muted small fw-semibold d-block mb-2">Direct Pay with UPI Apps:</span>
                <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                    <?php if (!empty($phonepeUrl)): ?>
                        <a href="<?= e($phonepeUrl) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-2xs fw-bold">
                            PhonePe
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($gpayUrl)): ?>
                        <a href="<?= e($gpayUrl) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 shadow-2xs fw-bold">
                            <i class="bi bi-google me-1"></i> Google Pay
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($paytmUrl)): ?>
                        <a href="<?= e($paytmUrl) ?>" class="btn btn-sm btn-outline-info rounded-pill px-3 py-1 shadow-2xs fw-bold">
                            Paytm
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($bhimUrl)): ?>
                        <a href="<?= e($bhimUrl) ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 shadow-2xs fw-bold">
                            BHIM / Any UPI
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Clear Instructions -->
            <p class="small text-muted mb-4 px-2">
                <?= e(__('scan_instructions', 'Scan the QR code using any UPI app on your phone (Google Pay, PhonePe, Paytm, BHIM, Cred) to complete payment.')) ?>
            </p>

            <!-- Status Alert Container (updated by AJAX polling) -->
            <div id="payment-status-message" class="alert alert-info py-2 small mb-4">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Listening for verified merchant payment confirmation...
            </div>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="button" id="btn-manual-check-status" class="btn btn-primary fw-bold py-2 shadow-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i> <?= e(__('check_status_now', 'Check Live Status')) ?>
                </button>

                <?php if (!empty($order['payment_url'])): ?>
                    <a href="<?= e($order['payment_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm py-2">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Open Gateway Checkout Page
                    </a>
                <?php endif; ?>

                <a href="/wallet/passbook" class="btn btn-link text-muted small text-decoration-none mt-2">
                    Back to Wallet Passbook
                </a>
            </div>

            <!-- Provider Notice -->
            <div class="mt-4 pt-3 border-top text-muted" style="font-size: 0.75rem;">
                <i class="bi bi-shield-check text-success me-1"></i> Connected to ALLAPI.in Official UPI Gateway
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
