<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row g-3 g-lg-4 py-3">
    <!-- LEFT: Compact Add Balance Card -->
    <div class="col-12 col-lg-5 col-xl-4">
        <div class="card shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                    <i class="bi bi-wallet2 text-primary fs-5"></i>
                    <h1 class="h6 fw-bold mb-0 text-dark">Add Balance</h1>
                </div>

                <form method="POST" action="/wallet/topup/initiate" id="topup-initiate-form">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="topup_amount_input" class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            Amount (₹)
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light fw-bold text-dark border-end-0">₹</span>
                            <input type="number" name="amount" id="topup_amount_input"
                                   class="form-control form-control-lg fw-bold text-dark border-start-0"
                                   min="50" max="50000" step="any" value="" placeholder="Enter amount"
                                   required autocomplete="off">
                        </div>
                        <div id="amount-validation-feedback" class="text-danger small mt-1 d-none" style="font-size: 0.8rem;"></div>
                    </div>

                    <button type="submit" id="btn-submit-topup" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm py-2.5 d-flex align-items-center justify-content-center gap-2" disabled>
                        <i class="bi bi-qr-code-scan"></i>
                        <span>Generate UPI QR Code</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: Recent Top-Up Orders List -->
    <div class="col-12 col-lg-7 col-xl-8">
        <div class="card shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
            <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i>
                    <h2 class="h6 fw-bold mb-0 text-dark">Recent Top-Up Orders</h2>
                </div>
                <span class="badge bg-light text-secondary border font-monospace"><?= count($recentOrders) ?> Orders</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">
                            <tr>
                                <th class="ps-3 py-2.5">Order Reference</th>
                                <th class="py-2.5">Amount</th>
                                <th class="py-2.5">Status</th>
                                <th class="py-2.5">Date</th>
                                <th class="text-end pe-3 py-2.5">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted small">
                                        <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
                                        No recent top-up orders found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $ro): ?>
                                    <?php $st = strtoupper($ro['status'] ?? 'PENDING'); ?>
                                    <tr>
                                        <td class="ps-3 font-monospace small fw-bold text-dark">
                                            <?= e($ro['order_id']) ?>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            ₹<?= number_format($ro['amount'], 2) ?>
                                        </td>
                                        <td>
                                            <?php if ($st === 'PAID'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small fw-bold">
                                                    <i class="bi bi-check-circle-fill me-1"></i>PAID
                                                </span>
                                            <?php elseif ($st === 'FAILED'): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 small fw-bold">
                                                    FAILED
                                                </span>
                                            <?php elseif ($st === 'EXPIRED'): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 small fw-bold">
                                                    EXPIRED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 small fw-bold">
                                                    <span class="spinner-grow spinner-grow-sm me-1" style="width: 7px; height: 7px;"></span>PENDING
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= date('d M, h:i A', strtotime($ro['created_at'])) ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <?php if ($st === 'PENDING'): ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-warning fw-bold py-0.5 px-2.5 rounded-2 shadow-2xs btn-open-qr"
                                                        data-order-id="<?= e($ro['order_id']) ?>"
                                                        style="font-size: 0.75rem;">
                                                    Pay / Check
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: eCyberCafe-Style UPI Payment & Dynamic QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 pb-0 pt-3 px-3.5 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-success fs-5"></i>
                    <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.5px;">UPI Scan & Pay</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3.5 pt-2 text-center">
                <!-- Loading State -->
                <div id="qrLoadingState" class="py-5">
                    <div class="spinner-border text-warning" role="status" style="width: 2.75rem; height: 2.75rem;">
                        <span class="visually-hidden">Loading QR...</span>
                    </div>
                    <div class="small text-muted mt-2 fw-semibold">Connecting to UPI Gateway...</div>
                </div>

                <!-- Ready State -->
                <div id="qrReadyState" style="display: none;">
                    <!-- Order ID & Copy Action -->
                    <div class="p-2.5 bg-light rounded-3 border mb-3 text-start d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Order Reference</div>
                            <div class="d-flex align-items-center gap-1.5">
                                <span class="font-monospace fw-bold text-dark small" id="qrModalOrderId">—</span>
                                <button type="button" id="btnCopyOrderId" class="btn btn-link btn-sm p-0 text-primary text-decoration-none" title="Copy Order ID">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                                <span id="copyFeedback" class="badge bg-success small py-0.5 px-1.5" style="display: none; font-size: 0.65rem;">Copied!</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Pay Amount</div>
                            <span class="badge bg-primary text-white fs-6 fw-bold px-2.5 py-1" id="qrModalAmountBadge">₹0.00</span>
                        </div>
                    </div>

                    <!-- QR Canvas / Image -->
                    <div id="qrCanvasWrapper" class="d-flex justify-content-center align-items-center my-2" style="min-height: 240px;">
                        <div id="qrCanvasContainer" class="p-2 bg-white rounded-3 border shadow-2xs d-inline-block"></div>
                        <img id="qrModalImg" src="" alt="Payment QR" class="img-fluid rounded-3 border shadow-2xs" style="display: none; width: 230px; height: 230px; object-fit: contain;">
                    </div>

                    <!-- Polling Status -->
                    <div class="small text-muted my-2 d-flex align-items-center justify-content-center gap-1.5" id="qrPollingStatusText">
                        <span class="spinner-grow spinner-grow-sm text-warning" style="width: 8px; height: 8px;"></span>
                        <span>Awaiting UPI payment confirmation...</span>
                    </div>

                    <!-- Payment Link & UPI Intent Buttons -->
                    <div class="mt-3 pt-2 border-top">
                        <div class="small text-muted fw-semibold mb-2 text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;">Pay with UPI App</div>
                        <div class="row g-1.5 mb-2">
                            <div class="col-6">
                                <a id="upiIntentPhonePe" href="#" class="btn btn-sm btn-outline-secondary w-100 py-1.5 fw-bold d-flex align-items-center justify-content-center gap-1 text-dark" style="font-size: 0.78rem;">
                                    <i class="bi bi-phone text-primary"></i> PhonePe
                                </a>
                            </div>
                            <div class="col-6">
                                <a id="upiIntentGPay" href="#" class="btn btn-sm btn-outline-secondary w-100 py-1.5 fw-bold d-flex align-items-center justify-content-center gap-1 text-dark" style="font-size: 0.78rem;">
                                    <i class="bi bi-google text-danger"></i> Google Pay
                                </a>
                            </div>
                            <div class="col-6">
                                <a id="upiIntentPaytm" href="#" class="btn btn-sm btn-outline-secondary w-100 py-1.5 fw-bold d-flex align-items-center justify-content-center gap-1 text-dark" style="font-size: 0.78rem;">
                                    <i class="bi bi-wallet text-info"></i> Paytm
                                </a>
                            </div>
                            <div class="col-6">
                                <a id="upiIntentBhim" href="#" class="btn btn-sm btn-outline-secondary w-100 py-1.5 fw-bold d-flex align-items-center justify-content-center gap-1 text-dark" style="font-size: 0.78rem;">
                                    <i class="bi bi-upc-scan text-success"></i> BHIM / Other
                                </a>
                            </div>
                        </div>

                        <!-- Direct Web Payment Link if available -->
                        <div id="paymentUrlWrapper" style="display: none;" class="mb-2">
                            <a id="directPaymentUrl" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light border w-100 py-1 text-muted small fw-semibold">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Open Gateway Payment Page
                            </a>
                        </div>

                        <!-- Retry / Manual Check Button -->
                        <button type="button" id="btnManualRecheck" class="btn btn-sm btn-outline-primary w-100 py-1.5 fw-bold rounded-2 mt-1">
                            <i class="bi bi-arrow-repeat me-1"></i> Re-check Status Now
                        </button>
                    </div>
                </div>

                <!-- Success State -->
                <div id="qrSuccessState" class="py-4 text-center" style="display: none;">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle shadow" style="width: 80px; height: 80px; animation: pulseSuccess 0.4s ease;">
                            <i class="bi bi-check-lg" style="font-size: 3.5rem;"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold text-success mb-1">Payment Successful!</h5>
                    <p class="text-dark fw-semibold mb-2 fs-6">₹<span id="qrSuccessAmount">0.00</span> Credited to Wallet</p>
                    <div class="d-inline-block bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill small fw-bold mb-2">
                        <i class="bi bi-wallet2 me-1"></i> Balance: ₹<span id="qrSuccessNewBalance">—</span>
                    </div>
                    <div class="small text-muted mt-2">
                        <span class="spinner-grow spinner-grow-sm me-1"></span> Refreshing wallet passbook...
                    </div>
                </div>

                <!-- Error / Terminal State (Failed / Expired) -->
                <div id="qrErrorState" class="py-4 text-center" style="display: none;">
                    <i class="bi bi-exclamation-circle-fill text-danger fs-1"></i>
                    <h6 class="fw-bold text-danger mt-2 mb-1" id="qrErrorTitle">Payment Error</h6>
                    <p id="qrErrorMsg" class="small text-muted mb-3 px-3"></p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Close</button>
                        <button type="button" id="btnRetryFromError" class="btn btn-sm btn-warning px-3 fw-bold">Try Again</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulseSuccess {
    0% { transform: scale(0.6); opacity: 0; }
    70% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}

/* Remove browser number input spin arrows */
#topup_amount_input::-webkit-outer-spin-button,
#topup_amount_input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
#topup_amount_input {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>

<!-- Standalone High-Speed QR Generator -->
<script src="/assets/js/qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('qrModal');
    const qrModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
    const form = document.getElementById('topup-initiate-form');
    const submitBtn = document.getElementById('btn-submit-topup');
    const amountInput = document.getElementById('topup_amount_input');
    const loadingState = document.getElementById('qrLoadingState');
    const readyState = document.getElementById('qrReadyState');
    const successState = document.getElementById('qrSuccessState');
    const errorState = document.getElementById('qrErrorState');
    const errorTitle = document.getElementById('qrErrorTitle');
    const errorMsg = document.getElementById('qrErrorMsg');
    const btnCopy = document.getElementById('btnCopyOrderId');
    const copyFeedback = document.getElementById('copyFeedback');
    const btnRecheck = document.getElementById('btnManualRecheck');
    const btnRetryError = document.getElementById('btnRetryFromError');

    let pollInterval = null;
    let activeOrderId = null;
    let currentFixedUri = '';
    let isSubmitting = false;
    const POLL_INTERVAL_MS = 3500; // ~3.5 seconds strictly per specification
    const feedback = document.getElementById('amount-validation-feedback');

    // Amount validation logic
    function validateAmount(showError = false) {
        const rawVal = amountInput ? amountInput.value.trim() : '';

        if (rawVal === '') {
            if (submitBtn) submitBtn.disabled = true;
            if (feedback) {
                if (showError) {
                    feedback.textContent = 'Please enter an amount.';
                    feedback.classList.remove('d-none');
                } else {
                    feedback.classList.add('d-none');
                }
            }
            return false;
        }

        const val = parseFloat(rawVal);
        if (isNaN(val) || val <= 0) {
            if (submitBtn) submitBtn.disabled = true;
            if (feedback) {
                if (showError) {
                    feedback.textContent = 'Please enter a valid positive amount.';
                    feedback.classList.remove('d-none');
                } else {
                    feedback.classList.add('d-none');
                }
            }
            return false;
        }

        if (val < 50) {
            if (submitBtn) submitBtn.disabled = true;
            if (feedback) {
                if (showError) {
                    feedback.textContent = 'Minimum amount is ₹50.';
                    feedback.classList.remove('d-none');
                } else {
                    feedback.classList.add('d-none');
                }
            }
            return false;
        }

        if (val > 50000) {
            if (submitBtn) submitBtn.disabled = true;
            if (feedback) {
                if (showError) {
                    feedback.textContent = 'Maximum amount is ₹50,000.';
                    feedback.classList.remove('d-none');
                } else {
                    feedback.classList.add('d-none');
                }
            }
            return false;
        }

        if (feedback) {
            feedback.textContent = '';
            feedback.classList.add('d-none');
        }
        if (submitBtn) submitBtn.disabled = false;
        return true;
    }

    if (amountInput) {
        amountInput.addEventListener('input', function () {
            if (this.value.trim() !== '') {
                validateAmount(true);
            } else {
                validateAmount(false);
            }
        });

        amountInput.addEventListener('blur', function () {
            if (this.value.trim() !== '') {
                validateAmount(true);
            }
        });
    }

    // Initialize button state (disabled initially as amount is blank)
    validateAmount(false);

    // Copy Order ID
    if (btnCopy) {
        btnCopy.addEventListener('click', function () {
            const orderId = document.getElementById('qrModalOrderId').textContent.trim();
            if (!orderId || orderId === '—') return;
            navigator.clipboard.writeText(orderId).then(() => {
                copyFeedback.style.display = 'inline';
                setTimeout(() => { copyFeedback.style.display = 'none'; }, 2000);
            }).catch(() => {});
        });
    }

    function showState(state) {
        loadingState.style.display = (state === 'loading') ? 'block' : 'none';
        readyState.style.display = (state === 'ready') ? 'block' : 'none';
        successState.style.display = (state === 'success') ? 'block' : 'none';
        errorState.style.display = (state === 'error') ? 'block' : 'none';
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    function updateTopbarBalance(newBal) {
        if (!newBal) return;
        const formatted = '₹' + parseFloat(newBal).toFixed(2);
        document.querySelectorAll('.topbar-balance-text, [data-wallet-balance]').forEach(el => {
            el.textContent = formatted;
        });
    }

    function setUpiAppIntents(fixedUri, paymentUrl) {
        currentFixedUri = fixedUri || '';
        const uri = fixedUri || '';

        // Deep links for supported UPI apps
        const phonePeLink = document.getElementById('upiIntentPhonePe');
        const gPayLink = document.getElementById('upiIntentGPay');
        const paytmLink = document.getElementById('upiIntentPaytm');
        const bhimLink = document.getElementById('upiIntentBhim');
        const payUrlWrapper = document.getElementById('paymentUrlWrapper');
        const directPayUrl = document.getElementById('directPaymentUrl');

        if (uri) {
            phonePeLink.href = uri.replace(/^upi:\/\//i, 'phonepe://');
            gPayLink.href = uri.replace(/^upi:\/\//i, 'tez://upi/');
            paytmLink.href = uri.replace(/^upi:\/\//i, 'paytmmp://');
            bhimLink.href = uri;
        } else {
            phonePeLink.href = '#';
            gPayLink.href = '#';
            paytmLink.href = '#';
            bhimLink.href = '#';
        }

        if (paymentUrl) {
            directPayUrl.href = paymentUrl;
            payUrlWrapper.style.display = 'block';
        } else {
            payUrlWrapper.style.display = 'none';
        }
    }

    function renderQr(data) {
        document.getElementById('qrModalOrderId').textContent = data.order_id || '';
        const amt = parseFloat(data.amount || 0);
        document.getElementById('qrModalAmountBadge').textContent = '₹' + amt.toFixed(2);
        document.getElementById('qrSuccessAmount').textContent = amt.toFixed(2);

        setUpiAppIntents(data.fixed_uri, data.payment_url);

        const container = document.getElementById('qrCanvasContainer');
        const fallbackImg = document.getElementById('qrModalImg');
        container.innerHTML = '';

        if (data.fixed_uri && typeof QRCode !== 'undefined') {
            try {
                new QRCode(container, {
                    text: data.fixed_uri,
                    width: 230,
                    height: 230,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                container.style.display = 'inline-block';
                fallbackImg.style.display = 'none';
            } catch(e) {
                container.style.display = 'none';
                fallbackImg.src = data.qr_image_url || '';
                fallbackImg.style.display = 'block';
            }
        } else if (data.qr_image_url) {
            container.style.display = 'none';
            fallbackImg.src = data.qr_image_url;
            fallbackImg.style.display = 'block';
        }

        showState('ready');
        startPolling(data.order_id);
    }

    function handleSuccess(data) {
        stopPolling();
        if (data.amount) {
            document.getElementById('qrSuccessAmount').textContent = parseFloat(data.amount).toFixed(2);
        }
        if (data.new_balance) {
            document.getElementById('qrSuccessNewBalance').textContent = parseFloat(data.new_balance).toFixed(2);
            updateTopbarBalance(data.new_balance);
        }
        showState('success');
        setTimeout(function () {
            window.location.href = '/wallet/topup';
        }, 2200);
    }

    function handleTerminalFailure(status, message) {
        stopPolling();
        errorTitle.textContent = (status === 'EXPIRED') ? 'Order Expired' : 'Payment Failed';
        errorMsg.textContent = message || ((status === 'EXPIRED') ? 'This payment order has expired. Please initiate a new recharge.' : 'Payment failed or was declined by the bank.');
        showState('error');
    }

    function pollCheck(orderId) {
        if (!orderId) return;
        fetch('/wallet/topup/status?order_id=' + encodeURIComponent(orderId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data && (data.status === 'PAID' || data.credited)) {
                handleSuccess(data);
            } else if (data && (data.status === 'FAILED' || data.status === 'EXPIRED')) {
                // Polling MUST STOP on terminal states!
                handleTerminalFailure(data.status, data.message);
            }
        })
        .catch(() => {});
    }

    function startPolling(orderId) {
        stopPolling();
        activeOrderId = orderId;
        pollInterval = setInterval(function () {
            if (!activeOrderId) {
                stopPolling();
                return;
            }
            pollCheck(activeOrderId);
        }, POLL_INTERVAL_MS);
    }

    // Modal closed -> immediately stop polling & restore button
    modalEl.addEventListener('hidden.bs.modal', function () {
        stopPolling();
        activeOrderId = null;
        isSubmitting = false;
        validateAmount(false);
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="bi bi-qr-code-scan"></i> <span>Generate UPI QR Code</span>';
        }
    });

    // Manual Recheck button clicked
    if (btnRecheck) {
        btnRecheck.addEventListener('click', function () {
            if (!activeOrderId) return;
            btnRecheck.disabled = true;
            btnRecheck.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking...';
            fetch('/wallet/topup/status?order_id=' + encodeURIComponent(activeOrderId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && (data.status === 'PAID' || data.credited)) {
                    handleSuccess(data);
                } else if (data && (data.status === 'FAILED' || data.status === 'EXPIRED')) {
                    handleTerminalFailure(data.status, data.message);
                } else {
                    btnRecheck.disabled = false;
                    btnRecheck.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Re-check Status Now';
                }
            })
            .catch(() => {
                btnRecheck.disabled = false;
                btnRecheck.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Re-check Status Now';
            });
        });
    }

    // Retry from error state button
    if (btnRetryError) {
        btnRetryError.addEventListener('click', function () {
            qrModal.hide();
            if (form) form.requestSubmit();
        });
    }

    // Form submit -> AJAX initiate and open QR modal
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return; // Prevent double-click duplicate order creation

            if (!validateAmount(true)) {
                if (amountInput) amountInput.focus();
                return;
            }

            isSubmitting = true;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <span>Generating QR...</span>';

            showState('loading');
            qrModal.show();

            const formData = new FormData(form);
            formData.append('ajax', '1');

            fetch('/wallet/topup/initiate', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && (data.qr_image_url || data.fixed_uri)) {
                    renderQr(data);
                } else {
                    errorTitle.textContent = 'Initiation Failed';
                    errorMsg.textContent = data.message || 'Unable to generate QR code.';
                    showState('error');
                }
            })
            .catch(err => {
                errorTitle.textContent = 'Connection Error';
                errorMsg.textContent = 'Network error while generating QR. Please try again.';
                showState('error');
            })
            .finally(() => {
                isSubmitting = false;
                validateAmount(false);
                submitBtn.innerHTML = '<i class="bi bi-qr-code-scan"></i> <span>Generate UPI QR Code</span>';
            });
        });
    }

    // "Pay / Check" button clicked on pending orders in the table
    document.querySelectorAll('.btn-open-qr').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const orderId = this.getAttribute('data-order-id');
            if (!orderId) return;

            showState('loading');
            qrModal.show();

            fetch('/wallet/topup/pay?order_id=' + encodeURIComponent(orderId) + '&ajax=1', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'PAID') {
                    handleSuccess(data);
                    return;
                }
                if (data.success && (data.qr_image_url || data.fixed_uri)) {
                    renderQr(data);
                } else {
                    errorTitle.textContent = 'Order Unavailable';
                    errorMsg.textContent = data.message || 'Could not load QR code.';
                    showState('error');
                }
            })
            .catch(() => {
                errorTitle.textContent = 'Network Error';
                errorMsg.textContent = 'Failed to load QR code.';
                showState('error');
            });
        });
    });

    // Check for URL parameter open_order
    const urlParams = new URLSearchParams(window.location.search);
    const autoOpenOrder = urlParams.get('open_order');
    if (autoOpenOrder) {
        showState('loading');
        qrModal.show();
        fetch('/wallet/topup/pay?order_id=' + encodeURIComponent(autoOpenOrder) + '&ajax=1', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'PAID') {
                handleSuccess(data);
                return;
            }
            if (data.success && (data.qr_image_url || data.fixed_uri)) {
                renderQr(data);
            } else {
                errorTitle.textContent = 'Order Unavailable';
                errorMsg.textContent = data.message || 'Could not load QR code.';
                showState('error');
            }
        })
        .catch(() => {
            errorTitle.textContent = 'Network Error';
            errorMsg.textContent = 'Failed to load QR code.';
            showState('error');
        });
    }

    // Non-blocking background reconciliation for pending orders after page loads
    const hasPendingOrder = document.querySelector('.btn-open-qr');
    if (hasPendingOrder) {
        setTimeout(function () {
            fetch('/wallet/topup/reconcile-pending', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.reconciled > 0) {
                    updateTopbarBalance(data.new_balance);
                    if (!activeOrderId) {
                        window.location.reload();
                    }
                }
            })
            .catch(() => {});
        }, 1500);
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
