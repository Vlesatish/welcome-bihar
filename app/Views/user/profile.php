<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row justify-content-center py-3">
    <div class="col-md-8 col-lg-6">
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-person-circle me-2 text-primary"></i><?= e(__('profile')) ?> &amp; Account Settings</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/user/profile">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <input type="text" class="form-control bg-light" value="<?= e($user['role']) ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="text" class="form-control bg-light" value="<?= e($user['mobile']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control bg-light" value="<?= e($user['email']) ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kiosk / Center Name</label>
                        <input type="text" name="center_name" class="form-control" value="<?= e($user['center_name']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Change Password (Leave blank to keep unchanged)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="••••••••" minlength="6">
                    </div>

                    <!-- Assigned Locations -->
                    <div class="mb-4 p-3 bg-light rounded-3 border">
                        <label class="form-label fw-bold small text-uppercase text-muted mb-2">Permitted Operating Districts &amp; Blocks:</label>
                        <?php if ((int)$user['assigned_all_districts'] === 1 || $user['role'] === 'ADMIN'): ?>
                            <span class="badge bg-success-subtle text-success border border-success p-2">
                                <i class="bi bi-globe me-1"></i> Universal Access (All 38 Bihar Districts)
                            </span>
                        <?php elseif (!empty($locations)): ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($locations as $loc): ?>
                                    <span class="badge bg-light text-dark border p-2">
                                        <i class="bi bi-geo-alt text-primary me-1"></i>
                                        <?= e($loc['district_name']) ?> <?= !empty($loc['block_name']) ? ' &bull; ' . e($loc['block_name']) : ' (All Blocks)' ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="small text-muted">No specific location assigned yet. Contact administrator.</div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold w-100 py-2">
                        <i class="bi bi-save me-1"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
