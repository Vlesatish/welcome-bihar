<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-6">
        <div class="wb-card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-warning-subtle text-warning mb-2">
                        <i class="bi bi-person-plus-fill fs-2"></i>
                    </div>
                    <h2 class="h4 fw-bold mb-1"><?= e(__('register')) ?></h2>
                    <p class="text-muted small mb-0">Register your CSC / Vasudha Kendra Kiosk for Bihar Services</p>
                </div>

                <form method="POST" action="/register" autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Operator Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Ramesh Kumar" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" name="mobile" class="form-control" pattern="^[6-9][0-9]{9}$" placeholder="10-digit mobile" required>
                            <div class="form-text small">Used for login and SMS notifications</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="operator@gmail.com" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Center / Kiosk Name</label>
                            <input type="text" name="center_name" class="form-control" placeholder="e.g. Maa Sharda Digital Seva Kendra">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Operating District <span class="text-danger">*</span></label>
                            <select name="district_id" class="form-select" required>
                                <option value="">-- <?= e(__('select_district')) ?> --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= e($d['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assign Distributor</label>
                            <select name="distributor_id" class="form-select">
                                <option value="">-- Direct (No Distributor) --</option>
                                <?php foreach ($distributors as $dist): ?>
                                    <option value="<?= $dist['id'] ?>">
                                        <?= e($dist['name']) ?> (<?= e($dist['center_name'] ?: $dist['mobile']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Account Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" minlength="6" class="form-control" placeholder="Minimum 6 characters" required>
                        </div>
                    </div>

                    <div class="mt-4 mb-3">
                        <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm py-2">
                            <i class="bi bi-check2-circle me-1"></i> <?= e(__('register')) ?>
                        </button>
                    </div>
                </form>

                <div class="text-center">
                    <span class="text-muted small">Already registered?</span>
                    <a href="/login" class="fw-bold ms-1 text-primary text-decoration-none"><?= e(__('login')) ?></a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
