<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row justify-content-center py-4">
    <div class="col-md-6 col-lg-5">
        <div class="wb-card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-3">
                        <i class="bi bi-shield-lock fs-1"></i>
                    </div>
                    <h2 class="h4 fw-bold mb-1"><?= e(__('login')) ?></h2>
                    <p class="text-muted small mb-0"><?= e(__('app_tagline')) ?></p>
                </div>

                <form method="POST" action="/login" autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="login_identifier">
                            <i class="bi bi-person me-1 text-primary"></i> Mobile or Email
                        </label>
                        <input type="text" class="form-control form-control-lg fs-6" id="login_identifier" name="identifier"
                               placeholder="e.g. 9999900001 or admin@welcomebihar.gov.in" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="login_password">
                            <i class="bi bi-key me-1 text-primary"></i> Password
                        </label>
                        <input type="password" class="form-control form-control-lg fs-6" id="login_password" name="password"
                               placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm py-2 mb-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i> <?= e(__('login')) ?>
                    </button>
                </form>

                <div class="text-center mb-4">
                    <span class="text-muted small">Don't have an operator kiosk account?</span>
                    <a href="/register" class="fw-bold ms-1 text-primary text-decoration-none"><?= e(__('register')) ?></a>
                </div>

                <!-- Demo Credentials Quick-Fill Helper -->
                <div class="p-3 bg-light rounded-3 border">
                    <div class="fw-bold small text-muted text-uppercase mb-2 text-center">
                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Demo Access Quick Fill
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary text-start"
                                onclick="fillDemo('admin@welcomebihar.gov.in', 'Welcome@2026')">
                            <span class="badge bg-primary me-2">ADMIN</span>
                            <span class="fw-semibold">Super Admin</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary text-start"
                                onclick="fillDemo('distributor@welcomebihar.gov.in', 'Welcome@2026')">
                            <span class="badge bg-secondary me-2">DISTRIBUTOR</span>
                            <span class="fw-semibold">Patna Distributor</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success text-start"
                                onclick="fillDemo('operator@welcomebihar.gov.in', 'Welcome@2026')">
                            <span class="badge bg-success me-2">USER</span>
                            <span class="fw-semibold">Danapur Operator</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('login_identifier').value = email;
    document.getElementById('login_password').value = pass;
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
