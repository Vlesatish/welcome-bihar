<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
    <div>
        <h1 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-credit-card-2-front text-primary"></i>
            <span>Payment Settings</span>
        </h1>
        <div class="small text-muted" style="font-size: 0.74rem;">
            Automatic UPI QR gateway, merchant credentials, and callback configuration
        </div>
    </div>
</div>

<form method="POST" action="/admin/settings">
    <?= csrf_field() ?>

    <div class="row g-3">
        <!-- 1. MAIN PAYMENT SETTINGS CARD -->
        <div class="col-12">
            <div class="card shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
                <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-qr-code-scan text-primary"></i>
                        <h2 class="h6 fw-bold mb-0 text-dark">UPI QR Gateway Settings</h2>
                    </div>
                    <?php if (!empty($settings['allapi_token'])): ?>
                        <span class="badge bg-success-subtle text-success border border-success fw-bold px-2.5 py-1" style="font-size: 0.72rem;">
                            <i class="bi bi-shield-check me-1"></i>Token Configured
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning fw-bold px-2.5 py-1" style="font-size: 0.72rem;">
                            <i class="bi bi-exclamation-triangle me-1"></i>Token Not Set
                        </span>
                    <?php endif; ?>
                </div>

                <div class="card-body p-4">
                    <div class="row g-3">
                        <!-- Automatic UPI Toggle -->
                        <div class="col-12">
                            <div class="form-check form-switch p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                                <div>
                                    <label class="form-check-label fw-bold text-dark d-block" for="gateway_auto_upi">
                                        Automatic UPI Gateway
                                    </label>
                                    <span class="text-muted small">Enable automated UPI dynamic QR generation and real-time wallet recharge for retailers.</span>
                                </div>
                                <input class="form-check-input ms-3" type="checkbox" role="switch" name="gateway_auto_upi" id="gateway_auto_upi" value="1"
                                       <?= ((int)($settings['gateway_auto_upi'] ?? 1) === 1) ? 'checked' : '' ?> style="width: 2.5em; height: 1.25em; cursor: pointer;">
                            </div>
                        </div>

                        <!-- Merchant Name -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">Merchant Name <span class="text-danger">*</span></label>
                            <input type="text" name="gateway_merchant_name" class="form-control form-control-sm" value="<?= e($settings['gateway_merchant_name'] ?? 'Welcome Bihar') ?>" placeholder="e.g. Welcome Bihar" required>
                            <div class="form-text small" style="font-size: 0.7rem;">Display name shown to retailers on the UPI payment screen.</div>
                        </div>

                        <!-- UPI ID -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">UPI ID (VPA) <span class="text-danger">*</span></label>
                            <input type="text" name="gateway_upi_id" class="form-control form-control-sm font-monospace" value="<?= e($settings['gateway_upi_id'] ?? 'welcomebihar@upi') ?>" placeholder="e.g. merchant@okhdfcbank" required>
                            <div class="form-text small" style="font-size: 0.7rem;">Official merchant UPI virtual payment address connected with provider.</div>
                        </div>

                        <!-- API Token (SECURE: Not rendered in value attribute!) -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">
                                API Token
                                <?php if (!empty($settings['allapi_token'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success ms-1" style="font-size: 0.65rem;">Active</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-key-fill"></i></span>
                                <input type="password" name="allapi_token" class="form-control form-control-sm font-monospace" value="" placeholder="<?= !empty($settings['allapi_token']) ? '•••••••••••••••••••••••••••••••• (Leave blank to keep existing token)' : 'Enter API token...' ?>" autocomplete="new-password">
                            </div>
                            <div class="form-text small" style="font-size: 0.7rem;">
                                Security: Token is never exposed in HTML source, JavaScript, or API responses. Submitting a blank field preserves the current token.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. ADVANCED PROVIDER SECTION (Collapsible) -->
        <div class="col-12">
            <div class="card shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
                <div class="card-header bg-light py-2.5 px-3.5 d-flex align-items-center justify-content-between" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-sliders2 text-muted"></i>
                        <span class="small fw-bold text-dark">Advanced Provider Endpoints & Callbacks</span>
                    </div>
                    <i class="bi bi-chevron-down text-muted small"></i>
                </div>

                <div class="collapse" id="collapseAdvanced">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-dark mb-1">Integration Mode</label>
                                <select name="allapi_mode" class="form-select form-select-sm fw-bold">
                                    <option value="LIVE" <?= ($settings['allapi_mode'] ?? 'LIVE') === 'LIVE' ? 'selected' : '' ?>>LIVE (Production)</option>
                                    <option value="SANDBOX" <?= ($settings['allapi_mode'] ?? '') === 'SANDBOX' ? 'selected' : '' ?>>SANDBOX (Simulation)</option>
                                </select>
                            </div>

                            <div class="col-md-9">
                                <label class="form-label small fw-bold text-dark mb-1">Provider Base URL</label>
                                <input type="url" name="allapi_base_url" class="form-control form-control-sm font-monospace" value="<?= e($settings['allapi_base_url'] ?? 'https://allapi.in') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Create Order Endpoint</label>
                                <input type="text" name="allapi_create_endpoint" class="form-control form-control-sm font-monospace" value="<?= e($settings['allapi_create_endpoint'] ?? '/order/create') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Status Check Endpoint</label>
                                <input type="text" name="allapi_status_endpoint" class="form-control form-control-sm font-monospace" value="<?= e($settings['allapi_status_endpoint'] ?? '/order/status') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Return / Redirect URL</label>
                                <input type="text" name="allapi_redirect_url" class="form-control form-control-sm font-monospace" value="<?= e($settings['allapi_redirect_url'] ?? 'https://welcomebihar.in/wallet/passbook') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Callback / Webhook URL</label>
                                <input type="text" name="allapi_callback_url" class="form-control form-control-sm font-monospace" value="<?= e($settings['allapi_callback_url'] ?? 'https://welcomebihar.in/wallet/webhook/allapi') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. WORKFLOW RULES & REFUND POLICIES -->
        <div class="col-12">
            <div class="card shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
                <div class="card-header bg-white py-2.5 px-3.5 border-bottom">
                    <span class="small fw-bold text-dark"><i class="bi bi-shield-check text-primary me-1"></i>Workflow & Refund Policy</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch p-2.5 bg-light rounded-3 border d-flex align-items-center justify-content-between m-0">
                                <div>
                                    <label class="form-check-label small fw-bold text-dark d-block" for="dist_review_check">
                                        Distributor Review Step
                                    </label>
                                    <span class="text-muted" style="font-size: 0.72rem;">Distributors review operator requests before final pass.</span>
                                </div>
                                <input class="form-check-input ms-2" type="checkbox" role="switch" name="distributor_review_required" id="dist_review_check" value="1"
                                       <?= ((int)($settings['distributor_review_required'] ?? 1) === 1) ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch p-2.5 bg-light rounded-3 border d-flex align-items-center justify-content-between m-0">
                                <div>
                                    <label class="form-check-label small fw-bold text-dark d-block" for="refund_check">
                                        Automatic Refund on Rejection
                                    </label>
                                    <span class="text-muted" style="font-size: 0.72rem;">Automatically credit fee back to submitting wallet exactly once.</span>
                                </div>
                                <input class="form-check-input ms-2" type="checkbox" role="switch" name="refund_on_rejection" id="refund_check" value="1"
                                       <?= ((int)($settings['refund_on_rejection'] ?? 1) === 1) ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- 4. WHATSAPP NOTIFICATION SETTINGS (WBAPI) -->
        <div class="col-12" id="whatsapp-settings">
            <div class="card shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
                <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-whatsapp text-success fs-5"></i>
                        <div>
                            <h2 class="h6 fw-bold mb-0 text-dark">WhatsApp Notifications (WBAPI)</h2>
                            <span class="text-muted small" style="font-size: 0.72rem;">Server-side notifications to Block Operators via WBAPI</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php
                        $waEnabled = ((int)($settings['whatsapp_enabled'] ?? 1) === 1);
                        $hasToken = !empty($settings['whatsapp_api_token']) || !empty($_ENV['WHATSAPP_API_TOKEN']) || getenv('WHATSAPP_API_TOKEN') !== false;
                        $hasSession = !empty($settings['whatsapp_session_id']) || !empty($_ENV['WHATSAPP_SESSION_ID']) || getenv('WHATSAPP_SESSION_ID') !== false;
                        $sessionStatus = $settings['whatsapp_last_status'] ?? (!$hasToken || !$hasSession ? 'Not Configured' : 'Disconnected');
                        $isEnvControlled = !empty($settings['is_env_controlled']);
                        ?>

                        <!-- Session Connection Status Badge -->
                        <?php if ($sessionStatus === 'Connected'): ?>
                            <span class="badge bg-success-subtle text-success border border-success fw-bold px-2.5 py-1" style="font-size: 0.72rem;">
                                <i class="bi bi-wifi me-1"></i>Connected
                            </span>
                        <?php elseif ($sessionStatus === 'Disconnected'): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning fw-bold px-2.5 py-1" style="font-size: 0.72rem;">
                                <i class="bi bi-wifi-off me-1"></i>Disconnected
                            </span>
                        <?php elseif ($sessionStatus === 'API Error'): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger fw-bold px-2.5 py-1" style="font-size: 0.72rem;">
                                <i class="bi bi-exclamation-triangle me-1"></i>API Error
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary fw-bold px-2.5 py-1" style="font-size: 0.72rem;">
                                <i class="bi bi-question-circle me-1"></i>Not Configured
                            </span>
                        <?php endif; ?>

                        <!-- Check Connection Button (Does not send real test WhatsApp message) -->
                        <button type="submit" form="checkSessionForm" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill fw-bold" style="font-size: 0.7rem;" title="Query connect-api.php status endpoint without sending messages">
                            <i class="bi bi-arrow-repeat me-1"></i>Check Connection
                        </button>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- Provider Block Alert Banner -->
                    <?php if (!empty($settings['whatsapp_provider_blocked'])): ?>
                        <div class="alert alert-danger py-2.5 px-3 small d-flex align-items-center justify-content-between mb-3 rounded-3 border-danger shadow-2xs">
                            <div>
                                <i class="bi bi-shield-slash-fill me-2 fs-6 text-danger"></i>
                                <strong>Provider Sending Blocked:</strong> <?= e($settings['whatsapp_provider_blocked_reason'] ?: 'Authentication failure (HTTP 401).') ?>
                                <div class="text-muted mt-0.5" style="font-size: 0.7rem;">Automated sending is halted. Save updated credentials and click 'Clear Block & Verify' to restore service.</div>
                            </div>
                            <button type="submit" form="unblockForm" class="btn btn-sm btn-danger rounded-pill px-3 py-1 fw-bold text-nowrap" style="font-size: 0.72rem;">
                                <i class="bi bi-unlock-fill me-1"></i>Clear Block & Verify
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <!-- Enable Toggle -->
                        <div class="col-12">
                            <div class="form-check form-switch p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                                <div>
                                    <label class="form-check-label fw-bold text-dark d-inline-flex align-items-center" for="whatsapp_enabled">
                                        <span>Enable WhatsApp Notifications</span>
                                        <?php if ($isEnvControlled): ?>
                                            <span class="badge bg-info-subtle text-info border border-info ms-2" style="font-size: 0.68rem;">
                                                <i class="bi bi-file-earmark-code me-1"></i>Controlled by environment
                                            </span>
                                        <?php endif; ?>
                                    </label>
                                    <span class="text-muted small d-block">
                                        Automatically send WhatsApp notifications to active Block Operators when an application is submitted.
                                        <?php if ($isEnvControlled): ?>
                                            <em class="text-secondary">(Active state locked by WHATSAPP_ENABLED in .env)</em>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <input class="form-check-input ms-3" type="checkbox" role="switch" name="whatsapp_enabled" id="whatsapp_enabled" value="1"
                                       <?= $waEnabled ? 'checked' : '' ?> <?= $isEnvControlled ? 'disabled' : '' ?> style="width: 2.5em; height: 1.25em; cursor: pointer;">
                            </div>
                        </div>

                        <!-- API Base URL -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                API Base URL <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-link-45deg"></i></span>
                                <input type="url" name="whatsapp_api_base_url" class="form-control form-control-sm font-monospace"
                                       value="<?= e($settings['whatsapp_api_base_url'] ?? 'https://wbapi.in') ?>"
                                       placeholder="https://wbapi.in" required pattern="https://wbapi\.in/?.*">
                            </div>
                            <div class="form-text small" style="font-size: 0.7rem;">
                                Host is strictly restricted to <code class="text-dark">wbapi.in</code> using HTTPS. Server uses <code class="text-dark">POST /send-api.php</code>.
                            </div>
                        </div>

                        <!-- Default Country Code -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                Default Country Code <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-globe"></i></span>
                                <input type="text" name="whatsapp_default_country_code" class="form-control form-control-sm font-monospace"
                                       value="<?= e($settings['whatsapp_default_country_code'] ?? '91') ?>"
                                       placeholder="91" maxlength="5" required>
                            </div>
                            <div class="form-text small" style="font-size: 0.7rem;">
                                Standard country calling prefix prepended to 10-digit operator mobile numbers (e.g. 91).
                            </div>
                        </div>

                        <!-- Session ID -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                WhatsApp Session ID
                                <?php if ($hasSession): ?>
                                    <span class="badge bg-success-subtle text-success border border-success ms-1" style="font-size: 0.65rem;">Active</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-chat-dots-fill"></i></span>
                                <input type="text" name="whatsapp_session_id" class="form-control form-control-sm font-monospace"
                                       value="<?= e($settings['whatsapp_session_id'] ?? '') ?>"
                                       placeholder="e.g. session_abc123" autocomplete="off">
                            </div>
                            <div class="form-text small" style="font-size: 0.7rem;">
                                Connected WhatsApp session ID configured in your WBAPI console.
                            </div>
                        </div>

                        <!-- API Token (SECURE: Masked display, never exposed in value attribute) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                API Token
                                <?php if ($hasToken): ?>
                                    <span class="badge bg-success-subtle text-success border border-success ms-1" style="font-size: 0.65rem;">Active</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-key-fill"></i></span>
                                <input type="password" name="whatsapp_api_token" class="form-control form-control-sm font-monospace"
                                       value=""
                                       placeholder="<?= $hasToken ? '•••••••••••••••••••••••••••••••• (Leave blank to keep current token)' : 'Enter WBAPI x-api-key token...' ?>"
                                       autocomplete="new-password">
                            </div>
                            <div class="form-text small" style="font-size: 0.7rem;">
                                Security: Token is never rendered in HTML or JS. Leave blank to preserve existing token.
                            </div>
                        </div>

                        <!-- Quick Link to Delivery Logs -->
                        <div class="col-12 mt-2 pt-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <span class="small text-muted" style="font-size: 0.74rem;">
                                <i class="bi bi-info-circle me-1"></i>Track dispatched notifications, response codes, and operator message statuses in real time.
                            </span>
                            <a href="/admin/whatsapp/logs" class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 fw-bold" style="font-size: 0.76rem;">
                                <i class="bi bi-journal-text me-1"></i>View WhatsApp Delivery Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-3 mb-4">
        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 py-1.5 fw-bold shadow-2xs">
            <i class="bi bi-save me-1"></i> Save Settings
        </button>
    </div>
</form>

<!-- Dedicated Action Forms for WhatsApp Status and Unblock with CSRF Protection -->
<form id="checkSessionForm" method="POST" action="/admin/whatsapp/check-session" class="d-none">
    <?= csrf_field() ?>
</form>

<form id="unblockForm" method="POST" action="/admin/whatsapp/unblock" class="d-none">
    <?= csrf_field() ?>
</form>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
