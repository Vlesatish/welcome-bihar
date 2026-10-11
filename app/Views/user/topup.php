<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-6">
        <div class="wb-card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-warning-subtle text-warning mb-2">
                        <i class="bi bi-qr-code-scan fs-1"></i>
                    </div>
                    <h2 class="h4 fw-bold mb-1"><?= e(__('topup_title')) ?></h2>
                    <p class="text-muted small mb-0">Recharge via PhonePe, Google Pay, Paytm, BHIM or any UPI Application</p>
                </div>

                <!-- Current Balance Display -->
                <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center mb-4">
                    <span class="text-muted fw-semibold"><?= e(__('current_balance')) ?>:</span>
                    <span class="fs-4 fw-bold text-primary">₹<?= number_format($balance, 2) ?></span>
                </div>

                <!-- Topup Form -->
                <form method="POST" action="/wallet/topup/initiate" id="topup-initiate-form">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= e(__('enter_amount')) ?> <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text fw-bold">₹</span>
                            <input type="number" name="amount" id="topup_amount_input" class="form-control fw-bold fs-4 text-primary"
                                   min="50" max="25000" step="1" value="500" required>
                        </div>
                        <div class="form-text small text-muted mt-1">
                            <?= e(__('min_amount_note')) ?>
                        </div>
                    </div>

                    <!-- Quick Amount Buttons -->
                    <div class="mb-4">
                        <label class="form-label small text-muted fw-semibold">Quick Amounts:</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="setAmount(100)">₹100</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="setAmount(200)">₹200</button>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold" onclick="setAmount(500)">₹500</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="setAmount(1000)">₹1,000</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="setAmount(2000)">₹2,000</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="setAmount(5000)">₹5,000</button>
                        </div>
                    </div>

                    <!-- Payment Provider Transparency Note -->
                    <div class="alert alert-info small p-3 mb-4 rounded-3 border-0 bg-info-subtle text-info-emphasis">
                        <i class="bi bi-shield-check fs-5 me-1 text-primary"></i>
                        <strong>UPI Scan &amp; Pay Service Notice:</strong>
                        <div class="mt-1">
                            UPI payment QR is generated via connected merchant UPI account through ALL API service.
                            <?= e(__('provider_disclaimer')) ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm py-2">
                        <i class="bi bi-qr-code me-2"></i> <?= e(__('generate_qr')) ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Top-Up Orders History -->
        <?php if (!empty($recentOrders)): ?>
            <div class="wb-card mt-4">
                <div class="wb-card-header">
                    <span class="fw-bold small text-uppercase"><i class="bi bi-clock-history me-1"></i> Recent Top-Up Orders</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order Reference</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $ro): ?>
                                    <tr>
                                        <td class="font-monospace small"><?= e($ro['order_id']) ?></td>
                                        <td class="fw-bold">₹<?= number_format($ro['amount'], 2) ?></td>
                                        <td>
                                            <span class="badge-status badge-<?= strtolower($ro['status']) ?> py-1">
                                                <?= e($ro['status']) ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?= date('d M, h:i A', strtotime($ro['created_at'])) ?></td>
                                        <td>
                                            <?php if ($ro['status'] === 'PENDING'): ?>
                                                <a href="/wallet/topup/pay?order_id=<?= urlencode($ro['order_id']) ?>" class="btn btn-xs btn-outline-warning py-0 px-2 small">
                                                    Pay / Check
                                                </a>
                                            <?php else: ?>
                                                <span class="small text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function setAmount(val) {
    document.getElementById('topup_amount_input').value = val;
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
