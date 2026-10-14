<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Ultra-Clean, Minimalist & Spacious Login Page -->
<div class="flex-grow-1 d-flex align-items-center justify-content-center py-5 px-3">
    <div class="w-100" style="max-width: 420px;">
        
        <!-- Clean Centered Auth Card -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-sm-5">
            
            <!-- Minimalist Brand Header -->
            <div class="text-center mb-4 pb-2">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                     style="width: 56px; height: 56px; background-color: #eff6ff; color: #2563eb;">
                    <i class="bi bi-shield-lock fs-3"></i>
                </div>
                <h1 class="h4 fw-bold text-dark mb-1">Login</h1>
                <p class="text-muted small mb-0">Unified Citizen Services Portal</p>
            </div>

            <!-- Login Form -->
            <form method="POST" action="/login" autocomplete="off" id="portalLoginForm">
                <?= csrf_field() ?>

                <!-- Identifier -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary" for="login_identifier">
                        Mobile Number or Email
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg fs-6 py-2 px-3 border rounded-3" 
                           id="login_identifier" 
                           name="identifier"
                           placeholder="Enter your mobile or email" 
                           required 
                           autofocus>
                </div>

                <!-- Password with Toggle -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0" for="login_password">
                            Password
                        </label>
                    </div>
                    <div class="position-relative">
                        <input type="password" 
                               class="form-control form-control-lg fs-6 py-2 px-3 pe-5 border rounded-3" 
                               id="login_password" 
                               name="password"
                               placeholder="••••••••" 
                               required>
                        <button class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted text-decoration-none pe-3" 
                                type="button" 
                                id="togglePasswordBtn" 
                                onclick="togglePasswordVisibility()" 
                                tabindex="-1">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold py-2 rounded-3 shadow-sm mb-3">
                    Sign In
                </button>
            </form>

            <!-- Clean Footer Link -->
            <div class="text-center pt-2">
                <span class="text-muted small">Don't have an account?</span>
                <a href="/register" class="fw-semibold text-primary text-decoration-none small ms-1">
                    Register Here
                </a>
            </div>

            <?php
            $isProduction = strtolower(\App\Config\Config::get('APP_ENV', 'development')) === 'production'
                || strtolower(\App\Config\Config::get('APP_DEBUG', 'true')) === 'false';
            ?>
            <?php if (!$isProduction): ?>
                <!-- Subtle Minimal Demo Quick Fill -->
                <div class="mt-4 pt-3 border-top text-center">
                    <div class="text-muted small mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px; text-transform: uppercase;">
                        Quick Demo Fill
                    </div>
                    <div class="d-flex flex-wrap justify-content-center gap-1">
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1" style="font-size: 0.72rem;"
                                onclick="fillDemo('admin@welcomebihar.gov.in', 'Welcome@2026', 'Admin')">
                            Admin
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1" style="font-size: 0.72rem;"
                                onclick="fillDemo('distributor@welcomebihar.gov.in', 'Welcome@2026', 'Distributor')">
                            Distributor
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1" style="font-size: 0.72rem;"
                                onclick="fillDemo('danapur.operator@welcomebihar.gov.in', 'Welcome@2026', 'Operator')">
                            Operator
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1" style="font-size: 0.72rem;"
                                onclick="fillDemo('operator@welcomebihar.gov.in', 'Welcome@2026', 'Retailer')">
                            Retailer
                        </button>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const pwd = document.getElementById('login_password');
    const icon = document.getElementById('togglePasswordIcon');
    if (!pwd) return;
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

<?php if (!$isProduction): ?>
function fillDemo(email, pass) {
    const idInput = document.getElementById('login_identifier');
    const pwdInput = document.getElementById('login_password');
    if (idInput && pwdInput) {
        idInput.value = email;
        pwdInput.value = pass;
    }
}
<?php endif; ?>
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
