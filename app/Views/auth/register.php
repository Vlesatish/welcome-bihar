<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Ultra-Clean, Minimalist & Spacious Register Page -->
<div class="flex-grow-1 d-flex align-items-center justify-content-center py-5 px-3">
    <div class="w-100" style="max-width: 540px;">
        
        <!-- Clean Centered Card -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-sm-5">
            
            <!-- Minimalist Brand Header -->
            <div class="text-center mb-4 pb-2">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                     style="width: 56px; height: 56px; background-color: #fef3c7; color: #d97706;">
                    <i class="bi bi-person-plus fs-3"></i>
                </div>
                <h1 class="h4 fw-bold text-dark mb-1"><?= e(__('register', 'Register Here')) ?></h1>
                <p class="text-muted small mb-0">Create your CSC / Cyber Cafe Kiosk Account</p>
            </div>

            <!-- Registration Form -->
            <form method="POST" action="/register" autocomplete="off">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <!-- Operator Name -->
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">
                            Operator Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control form-control-lg fs-6 py-2 px-3 border rounded-3" placeholder="e.g. Ramesh Kumar" required>
                    </div>

                    <!-- Mobile -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">
                            Mobile Number <span class="text-danger">*</span>
                        </label>
                        <input type="tel" name="mobile" class="form-control form-control-lg fs-6 py-2 px-3 border rounded-3" pattern="^[6-9][0-9]{9}$" placeholder="10-digit mobile" required>
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <input type="email" name="email" class="form-control form-control-lg fs-6 py-2 px-3 border rounded-3" placeholder="operator@gmail.com" required>
                    </div>

                    <!-- Center Name -->
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">
                            Center / Kiosk Name
                        </label>
                        <input type="text" name="center_name" class="form-control form-control-lg fs-6 py-2 px-3 border rounded-3" placeholder="e.g. Maa Sharda Digital Seva Kendra">
                    </div>

                    <!-- Operating District -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">
                            Center District <span class="text-danger">*</span>
                        </label>
                        <select name="district_id" id="register_district_id" class="form-select form-select-lg fs-6 py-2 px-3 border rounded-3" required>
                            <option value="">-- <?= e(__('select_district')) ?> --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Operating Block -->
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">
                            Center Block
                        </label>
                        <select name="block_id" id="register_block_id" class="form-select form-select-lg fs-6 py-2 px-3 border rounded-3" disabled>
                            <option value="">-- Select Block --</option>
                        </select>
                    </div>

                    <!-- Center Address -->
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">
                            Center / Shop Address
                        </label>
                        <input type="text" name="center_address" class="form-control form-control-lg fs-6 py-2 px-3 border rounded-3" placeholder="Shop / Office address, Market, Pincode">
                    </div>


                    <!-- Password -->
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">
                            Account Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" name="password" minlength="6" class="form-control form-control-lg fs-6 py-2 px-3 border rounded-3" placeholder="Minimum 6 characters" required>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mt-4 mb-3">
                    <button type="submit" class="btn btn-warning btn-lg w-100 fw-semibold py-2 rounded-3 shadow-sm">
                        Create Account
                    </button>
                </div>
            </form>

            <!-- Clean Footer Link -->
            <div class="text-center pt-2">
                <span class="text-muted small">Already have an account?</span>
                <a href="/login" class="fw-semibold text-primary text-decoration-none small ms-1">
                    Sign In
                </a>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const distSelect = document.getElementById('register_district_id');
    const blkSelect = document.getElementById('register_block_id');

    if (distSelect && blkSelect) {
        distSelect.addEventListener('change', function () {
            const distId = this.value;
            blkSelect.innerHTML = '<option value="">-- Select Block --</option>';
            if (!distId) {
                blkSelect.disabled = true;
                return;
            }

            blkSelect.disabled = true;
            fetch('/locations/blocks?district_id=' + encodeURIComponent(distId))
                .then(res => res.json())
                .then(blocks => {
                    blkSelect.innerHTML = '<option value="">-- Select Block --</option>';
                    if (Array.isArray(blocks) && blocks.length > 0) {
                        blocks.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.id;
                            opt.textContent = b.name;
                            blkSelect.appendChild(opt);
                        });
                        blkSelect.disabled = false;
                    }
                })
                .catch(() => {
                    blkSelect.disabled = false;
                });
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
