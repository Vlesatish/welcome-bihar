<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row justify-content-center py-3">
    <div class="col-md-9 col-lg-7">
        <div class="card shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6"><i class="bi bi-person-circle me-2 text-primary"></i><?= e(__('profile')) ?> & Center Settings</span>
                <span class="badge bg-light text-secondary border font-monospace"><?= e($user['role']) ?></span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/user/profile" id="profileForm">
                    <?= csrf_field() ?>

                    <!-- Personal & Kiosk Info -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="bi bi-person me-1 text-primary"></i> Basic Information
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control rounded-2" value="<?= e($user['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Kiosk / Center Name</label>
                                <input type="text" name="center_name" class="form-control rounded-2" value="<?= e($user['center_name'] ?? '') ?>" placeholder="e.g. Digital Seva Kendra">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Mobile Number</label>
                                <input type="text" class="form-control rounded-2 bg-light text-muted" value="<?= e($user['mobile']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Email Address</label>
                                <input type="email" class="form-control rounded-2 bg-light text-muted" value="<?= e($user['email']) ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Permanent Center / Shop Location -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-2 d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-geo-alt me-1 text-primary"></i> Center Permanent Location</span>
                            <span class="badge bg-info-subtle text-info-emphasis border small fw-normal" style="font-size: 0.72rem;">Shop & Kiosk Address</span>
                        </h6>
                        <div class="alert alert-light border small text-muted py-2 px-3 mb-3 rounded-2">
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            Your permanent center location is used for shop identification and reports. You can submit applications for <strong>any active District and Block</strong> across Bihar.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">State</label>
                                <input type="text" name="profile_state" class="form-control rounded-2 bg-light" value="<?= e($user['profile_state'] ?? 'Bihar') ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="profile_district_id" class="form-label small fw-semibold text-secondary">Center District <span class="text-danger">*</span></label>
                                <select name="profile_district_id" id="profile_district_id" class="form-select rounded-2" required>
                                    <option value="">-- Select District --</option>
                                    <?php foreach ($districts as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= (int)($user['profile_district_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                                            <?= e($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="profile_block_id" class="form-label small fw-semibold text-secondary">Center Block <span class="text-danger">*</span></label>
                                <select name="profile_block_id" id="profile_block_id" class="form-select rounded-2" <?= empty($blocks) ? 'disabled' : '' ?> required>
                                    <option value="">-- Select Block --</option>
                                    <?php foreach ($blocks as $b): ?>
                                        <option value="<?= $b['id'] ?>" <?= (int)($user['profile_block_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
                                            <?= e($b['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-secondary">Center / Shop Full Address</label>
                                <textarea name="center_address" class="form-control rounded-2" rows="2" placeholder="Shop No, Market/Gali, Landmark, Pincode"><?= e($user['center_address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Security / Password -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="bi bi-shield-lock me-1 text-primary"></i> Security
                        </h6>
                        <div>
                            <label class="form-label small fw-semibold text-secondary">Change Password <span class="text-muted fw-normal">(Leave blank to keep unchanged)</span></label>
                            <input type="password" name="new_password" class="form-control rounded-2" placeholder="••••••••" minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold w-100 py-2.5 rounded-2 shadow-sm">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const distSelect = document.getElementById('profile_district_id');
    const blkSelect = document.getElementById('profile_block_id');
    const currentBlkId = <?= (int)($user['profile_block_id'] ?? 0) ?>;

    if (distSelect && blkSelect) {
        distSelect.addEventListener('change', function () {
            const distId = this.value;
            blkSelect.innerHTML = '<option value="">-- Select Block --</option>';
            if (!distId) {
                blkSelect.disabled = true;
                return;
            }

            blkSelect.disabled = true;
            fetch('/api/blocks?district_id=' + encodeURIComponent(distId))
                .then(res => {
                    if (!res.ok) throw new Error('Failed to load blocks');
                    return res.json();
                })
                .then(blocks => {
                    blkSelect.innerHTML = '<option value="">-- Select Block --</option>';
                    if (Array.isArray(blocks) && blocks.length > 0) {
                        blocks.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.id;
                            opt.textContent = b.name + (b.name_hi ? ' (' + b.name_hi + ')' : '');
                            if (parseInt(b.id) === currentBlkId) {
                                opt.selected = true;
                            }
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
