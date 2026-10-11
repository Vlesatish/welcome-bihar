<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-gear-fill text-primary me-2"></i><?= e(__('system_settings')) ?>
        </h1>
        <p class="text-muted mb-0">System Branding, Workflow Rules, and ALL API UPI QR Provider Settings</p>
    </div>
</div>

<form method="POST" action="/admin/settings">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- General Portal Branding -->
        <div class="col-lg-6">
            <div class="wb-card h-100">
                <div class="wb-card-header">
                    <span class="fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>General &amp; Branding Settings</span>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Portal Title</label>
                        <input type="text" name="portal_name" class="form-control" value="<?= e($settings['portal_name'] ?? 'Welcome Bihar') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Portal Tagline</label>
                        <input type="text" name="portal_tagline" class="form-control" value="<?= e($settings['portal_tagline'] ?? '') ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Helpline Number</label>
                            <input type="text" name="support_phone" class="form-control" value="<?= e($settings['support_phone'] ?? '+91 1800-345-6262') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Support Email</label>
                            <input type="email" name="support_email" class="form-control" value="<?= e($settings['support_email'] ?? 'support@welcomebihar.gov.in') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflow Rules & Verification Settings -->
        <div class="col-lg-6">
            <div class="wb-card h-100">
                <div class="wb-card-header">
                    <span class="fw-bold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Application Workflow &amp; Refund Rules</span>
                </div>
                <div class="card-body p-4">
                    <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 border">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="distributor_review_required" id="dist_review_check" value="1"
                               <?= ((int)($settings['distributor_review_required'] ?? 1) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="dist_review_check">
                            Require Distributor Review Before Admin Approval
                        </label>
                        <div class="small text-muted mt-1 ps-5">
                            When enabled, applications submitted by operators must be reviewed by their supervising distributor (marked Under Review) before final Administrative approval.
                        </div>
                    </div>

                    <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 border">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="refund_on_rejection" id="refund_check" value="1"
                               <?= ((int)($settings['refund_on_rejection'] ?? 1) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="refund_check">
                            Automatic Wallet Refund on Rejection
                        </label>
                        <div class="small text-muted mt-1 ps-5">
                            When enabled, if an administrator or distributor rejects an application, the debited application fee is automatically credited back to the submitting operator's wallet with a mandatory audit record.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALL API UPI QR Service Configuration -->
        <div class="col-12">
            <div class="wb-card border-primary shadow-sm">
                <div class="wb-card-header bg-primary-subtle text-primary-emphasis">
                    <span class="fw-bold"><i class="bi bi-qr-code-scan me-2 text-primary"></i>ALL API UPI Scan &amp; Pay QR Integration Configuration</span>
                    <span class="badge bg-primary text-uppercase"><?= e($settings['allapi_mode'] ?? 'SANDBOX') ?></span>
                </div>
                <div class="card-body p-4">
                    <!-- Critical Provider Architecture Notice -->
                    <div class="alert alert-warning small p-3 mb-4 rounded-3 border-0 bg-warning-subtle text-warning-emphasis">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-1">ALL API Service Scope Disclosure:</h6>
                                <p class="mb-0">
                                    ALL API provides dynamic UPI QR code generation and order/QR status tracking. It is <strong>NOT</strong> a payment gateway, bank, fund holder, settlement service, or UPI merchant account. The portal administrator must connect their own eligible UPI merchant account with ALL API. Sensitive API tokens are kept server-side only in protected configuration and never exposed to the client.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Integration Mode <span class="text-danger">*</span></label>
                            <select name="allapi_mode" class="form-select fw-bold">
                                <option value="SANDBOX" <?= ($settings['allapi_mode'] ?? 'SANDBOX') === 'SANDBOX' ? 'selected' : '' ?>>SANDBOX (Development Simulation)</option>
                                <option value="LIVE" <?= ($settings['allapi_mode'] ?? '') === 'LIVE' ? 'selected' : '' ?>>LIVE (Official ALL API Merchant Account)</option>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">ALL API Base URL</label>
                            <input type="url" name="allapi_base_url" class="form-control" value="<?= e($settings['allapi_base_url'] ?? 'https://allapi.in') ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Merchant Connected API Token</label>
                            <input type="password" name="allapi_token" class="form-control" value="<?= e($settings['allapi_token'] ?? '') ?>" placeholder="Keep token secret">
                            <div class="form-text small">Stored server-side only. You can also configure via <code>ALLAPI_TOKEN</code> in <code>.env</code>.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Create Order Endpoint</label>
                            <input type="text" name="allapi_create_endpoint" class="form-control" value="<?= e($settings['allapi_create_endpoint'] ?? '/order/create') ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status Check Endpoint</label>
                            <input type="text" name="allapi_status_endpoint" class="form-control" value="<?= e($settings['allapi_status_endpoint'] ?? '/order/status') ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">User Return Redirect URL</label>
                            <input type="text" name="allapi_redirect_url" class="form-control" value="<?= e($settings['allapi_redirect_url'] ?? 'http://127.0.0.1:8080/wallet/topup/callback') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mt-4 mb-5">
        <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow-sm">
            <i class="bi bi-save me-2"></i> Save All Settings
        </button>
    </div>
</form>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
