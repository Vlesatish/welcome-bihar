<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Breadcrumbs & Heading -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="/distributor/dashboard" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/distributor/team" class="text-decoration-none"><?= e(__('team_management')) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Add New Retailer</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(37, 99, 235, 0.12); color: #2563eb;">
                <i class="bi bi-person-plus-fill"></i>
            </span>
            Add New Retailer
        </h1>
        <p class="text-muted mb-0 small">
            Register and onboard a new Kiosk Operator / CSC Center under your distributor network.
        </p>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="/distributor/team" class="btn btn-outline-secondary rounded-pill px-3 py-1.5 fw-semibold small">
            <i class="bi bi-arrow-left me-1"></i> Back to Team
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        
        <!-- Distributor Network Context Alert -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, rgba(239, 246, 255, 0.9) 0%, rgba(219, 234, 254, 0.6) 100%); border: 1px solid rgba(191, 219, 254, 0.8) !important;">
            <div class="card-body p-3.5 d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: rgba(37, 99, 235, 0.15); color: #1d4ed8;">
                    <i class="bi bi-diagram-3-fill fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark small">Supervising Distributor</div>
                    <div class="small text-secondary">
                        <strong><?= e($currentUser['name']) ?></strong> &bull; <?= e($currentUser['center_name'] ?: 'Regional Distributor') ?> &bull; <span class="font-monospace"><?= e($currentUser['mobile']) ?></span>
                    </div>
                    <div class="small text-primary mt-0.5" style="font-size: 0.73rem;">
                        <i class="bi bi-shield-check me-1"></i> All applications submitted by this retailer will be linked to your distributor commission.
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Retailer Form Card -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-md-5">
            <form method="POST" action="/distributor/retailers/new" autocomplete="off" id="addRetailerForm">
                <?= csrf_field() ?>

                <div class="row g-3">
                    
                    <!-- Section 1: Basic Information -->
                    <div class="col-12 border-bottom pb-2 mb-2">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-person-badge text-primary"></i> 1. Retailer Personal Details
                        </h6>
                    </div>

                    <!-- Full Name -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary" for="retailer_name">
                            Operator / Retailer Full Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" 
                                   class="form-control border-start-0 py-2 px-2.5 rounded-end-3" 
                                   id="retailer_name" 
                                   name="name" 
                                   placeholder="e.g. Ramesh Kumar" 
                                   required 
                                   autofocus>
                        </div>
                    </div>

                    <!-- Center / Shop Name -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary" for="retailer_center">
                            Center / Shop Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shop"></i></span>
                            <input type="text" 
                                   class="form-control border-start-0 py-2 px-2.5 rounded-end-3" 
                                   id="retailer_center" 
                                   name="center_name" 
                                   placeholder="e.g. Ramesh Digital Seva Kendra" 
                                   required>
                        </div>
                    </div>

                    <!-- Mobile Number -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary" for="retailer_mobile">
                            10-Digit Mobile Number (Login ID) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-phone"></i> +91</span>
                            <input type="tel" 
                                   class="form-control border-start-0 py-2 px-2.5 rounded-end-3 font-monospace" 
                                   id="retailer_mobile" 
                                   name="mobile" 
                                   maxlength="10" 
                                   pattern="[0-9]{10}"
                                   placeholder="9876543210" 
                                   required>
                        </div>
                        <div class="form-text small" style="font-size: 0.72rem;">This will be used by retailer to log in.</div>
                    </div>

                    <!-- Email Address -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary" for="retailer_email">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" 
                                   class="form-control border-start-0 py-2 px-2.5 rounded-end-3" 
                                   id="retailer_email" 
                                   name="email" 
                                   placeholder="ramesh.csc@gmail.com" 
                                   required>
                        </div>
                    </div>

                    <!-- Password with Generator and Toggle -->
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold text-secondary mb-0" for="retailer_password">
                                Initial Login Password <span class="text-danger">*</span>
                            </label>
                            <button type="button" class="btn btn-link btn-xs text-decoration-none p-0 fw-semibold" id="btnGenPassword" onclick="generateRandomPassword()">
                                <i class="bi bi-magic me-1"></i> Auto-Generate Password
                            </button>
                        </div>
                        <div class="position-relative">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" 
                                       class="form-control border-start-0 py-2 px-2.5 pe-5 font-monospace" 
                                       id="retailer_password" 
                                       name="password" 
                                       placeholder="••••••••" 
                                       minlength="6" 
                                       required>
                                <button class="btn btn-outline-secondary border-start-0" 
                                        type="button" 
                                        id="togglePassBtn" 
                                        onclick="togglePasswordVisibility()">
                                    <i class="bi bi-eye" id="togglePassIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-text small" style="font-size: 0.72rem;">Minimum 6 characters. Provide this password to the retailer.</div>
                    </div>

                    <!-- Section 2: Location Assignment -->
                    <div class="col-12 border-bottom pb-2 mb-2 mt-4">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt text-primary"></i> 2. Location Assignment
                        </h6>
                    </div>

                    <!-- District Selection -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary" for="retailer_district">
                            Assigned District <span class="text-danger">*</span>
                        </label>
                        <select class="form-select py-2 px-3 rounded-3" id="retailer_district" name="district_id" required onchange="onDistrictChange(this.value)">
                            <option value="">-- Select District --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= (int)$d['id'] ?>">
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Block Selection (Dynamic) -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary" for="retailer_block">
                            Assigned Block <span class="text-muted fw-normal">(Optional)</span>
                        </label>
                        <select class="form-select py-2 px-3 rounded-3" id="retailer_block" name="block_id">
                            <option value="">-- All Blocks / General District Access --</option>
                        </select>
                        <div class="form-text small" id="blockHelpText" style="font-size: 0.72rem;">Select district first to filter blocks.</div>
                    </div>

                    <!-- Wallet & Role Notice -->
                    <div class="col-12 mt-3">
                        <div class="p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-wallet2 text-success fs-5"></i>
                                <div>
                                    <div class="fw-bold small text-dark">Retailer Wallet Creation</div>
                                    <div class="small text-muted" style="font-size: 0.73rem;">Wallet will be activated with ₹0.00 balance. Retailer can top-up via UPI QR code.</div>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1">Role: Kiosk / User</span>
                        </div>
                    </div>

                    <!-- Submit & Cancel Buttons -->
                    <div class="col-12 mt-4 pt-2 border-top d-flex align-items-center justify-content-between">
                        <a href="/distributor/team" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-2" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;">
                            <i class="bi bi-check-lg fs-5"></i> Create Retailer Account
                        </button>
                    </div>

                </div>
            </form>
        </div>

    </div>
</div>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('retailer_password');
    const icon = document.getElementById('togglePassIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function generateRandomPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$';
    let pass = 'Ret@';
    for (let i = 0; i < 6; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input = document.getElementById('retailer_password');
    input.value = pass;
    input.type = 'text';
    document.getElementById('togglePassIcon').className = 'bi bi-eye-slash';
}

function onDistrictChange(districtId) {
    const blockSelect = document.getElementById('retailer_block');
    const helpText = document.getElementById('blockHelpText');
    
    blockSelect.innerHTML = '<option value="">-- All Blocks / General District Access --</option>';
    
    if (!districtId) {
        helpText.textContent = 'Select district first to filter blocks.';
        return;
    }

    helpText.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-1" style="width: 12px; height: 12px;"></span> Loading blocks...';

    fetch('/api/blocks?district_id=' + encodeURIComponent(districtId))
        .then(response => {
            if (!response.ok) throw new Error('Failed to load blocks');
            return response.json();
        })
        .then(blocks => {
            if (Array.isArray(blocks) && blocks.length > 0) {
                blocks.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.name;
                    blockSelect.appendChild(opt);
                });
                helpText.textContent = blocks.length + ' blocks available in this district.';
            } else {
                helpText.textContent = 'No specific blocks found. Retailer will have district-wide access.';
            }
        })
        .catch(err => {
            console.error('Error fetching blocks:', err);
            helpText.textContent = 'Retailer will have district-wide access.';
        });
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
