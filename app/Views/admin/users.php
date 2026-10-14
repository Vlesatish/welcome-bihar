<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-people-fill text-primary me-2"></i>Users & Block Operators
        </h1>
        <p class="text-muted mb-0">Manage Block Operators, Regional Distributors, Kiosks & Administrator Accounts</p>
    </div>
    <div class="col-md-5 text-md-end">
        <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addOperatorModal">
            <i class="bi bi-person-plus-fill me-1"></i> Add Block Operator
        </button>
    </div>
</div>

<!-- Filters Card -->
<div class="wb-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/users" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search Name / Mobile / Email / Center..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">-- All Roles --</option>
                    <option value="OPERATOR" <?= $role === 'OPERATOR' ? 'selected' : '' ?>>Block Operator</option>
                    <option value="USER" <?= $role === 'USER' ? 'selected' : '' ?>><?= e(__('role_user')) ?></option>
                    <option value="DISTRIBUTOR" <?= $role === 'DISTRIBUTOR' ? 'selected' : '' ?>><?= e(__('role_distributor')) ?></option>
                    <option value="ADMIN" <?= $role === 'ADMIN' ? 'selected' : '' ?>><?= e(__('role_admin')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- All Statuses --</option>
                    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                    <option value="BLOCKED" <?= $status === 'BLOCKED' ? 'selected' : '' ?>>BLOCKED</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="/admin/users" class="btn btn-outline-secondary" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="wb-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Assigned District</th>
                        <th>Assigned Block</th>
                        <th>Status</th>
                        <th class="text-end">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                                No users or operators found matching the criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <a href="/admin/users/detail?id=<?= $u['id'] ?>" class="text-decoration-none">
                                            <?= e($u['name']) ?>
                                        </a>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-telephone me-1"></i><?= e($u['mobile']) ?>
                                        <?php if (!empty($u['email'])): ?>
                                            &bull; <?= e($u['email']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($u['center_name'])): ?>
                                            &bull; <span class="badge bg-light text-secondary border"><?= e($u['center_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'OPERATOR'): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold px-2 py-1">
                                            <i class="bi bi-shield-lock me-1"></i>OPERATOR
                                        </span>
                                    <?php elseif ($u['role'] === 'ADMIN'): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold px-2 py-1"><?= e($u['role']) ?></span>
                                    <?php elseif ($u['role'] === 'DISTRIBUTOR'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 fw-bold"><?= e($u['role']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border px-2 py-1"><?= e($u['role']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['assigned_district_name'])): ?>
                                        <span class="fw-semibold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1 small"></i><?= e($u['assigned_district_name']) ?></span>
                                    <?php elseif ((int)$u['assigned_all_districts'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">All Districts</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['assigned_block_name'])): ?>
                                        <span class="badge bg-light text-dark border fw-bold"><?= e($u['assigned_block_name']) ?></span>
                                    <?php elseif ((int)$u['assigned_all_districts'] === 1): ?>
                                        <span class="text-muted small">All Blocks</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $u['status'] === 'ACTIVE' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                        <?= e($u['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($u['role'] === 'OPERATOR'): ?>
                                            <!-- Status Toggle Form -->
                                            <form method="POST" action="/admin/users/update-status" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="redirect" value="users">
                                                <input type="hidden" name="status" value="<?= $u['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                                                <button type="submit" class="btn <?= $u['status'] === 'ACTIVE' ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-sm"
                                                        title="<?= $u['status'] === 'ACTIVE' ? 'Deactivate Operator' : 'Activate Operator' ?>"
                                                        onclick="return confirm('Change status of operator <?= htmlspecialchars($u['name'], ENT_QUOTES) ?> to <?= $u['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>?');">
                                                    <i class="bi <?= $u['status'] === 'ACTIVE' ? 'bi-pause-fill' : 'bi-play-fill' ?>"></i>
                                                    <?= $u['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>

                                            <!-- Reassign Button -->
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    onclick="openReassignModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>', <?= (int)($u['assigned_district_id'] ?? 0) ?>, <?= (int)($u['assigned_block_id'] ?? 0) ?>)"
                                                    title="Reassign District & Block">
                                                <i class="bi bi-geo-alt"></i> Reassign
                                            </button>

                                            <!-- Password Reset Button -->
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="openResetPasswordModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')"
                                                    title="Reset Password">
                                                <i class="bi bi-key-fill"></i> Reset
                                            </button>
                                        <?php endif; ?>

                                        <a href="/admin/users/detail?id=<?= $u['id'] ?>" class="btn btn-outline-primary btn-sm fw-semibold" title="Full User Details">
                                            <i class="bi bi-gear-fill"></i> Manage
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Block Operator -->
<div class="modal fade" id="addOperatorModal" tabindex="-1" aria-labelledby="addOperatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold" id="addOperatorModalLabel">
                    <i class="bi bi-person-plus-fill me-2"></i>Add Block Operator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/admin/operators/create">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Create an Operator account. Operator will be strictly scoped to the assigned District and Block.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Operator Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Ramesh Kumar" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Mobile Number (10 Digits) <span class="text-danger">*</span></label>
                            <input type="text" name="mobile" maxlength="10" pattern="[0-9]{10}" class="form-control" placeholder="9876543210" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Email Address (Optional)</label>
                            <input type="email" name="email" class="form-control" placeholder="operator@gmail.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" minlength="6" class="form-control" placeholder="Minimum 6 characters" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assigned District <span class="text-danger">*</span></label>
                            <select name="district_id" id="modal_operator_district" class="form-select" onchange="onModalDistrictChange(this.value, 'modal_operator_block')" required>
                                <option value="">-- Choose District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assigned Block <span class="text-danger">*</span></label>
                            <select name="block_id" id="modal_operator_block" class="form-select" required>
                                <option value="">-- Select District First --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="ACTIVE" selected>ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="bi bi-check2-circle me-1"></i> Create Operator
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reassign Operator District & Block -->
<div class="modal fade" id="reassignOperatorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header bg-dark text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-geo-alt-fill text-warning me-2"></i>Reassign Block Operator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin/operators/reassign">
                <?= csrf_field() ?>
                <input type="hidden" name="operator_id" id="reassign_operator_id">
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <div class="small text-muted">Operator:</div>
                        <div class="fw-bold fs-6 text-dark" id="reassign_operator_name">-</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">New District <span class="text-danger">*</span></label>
                            <select name="district_id" id="reassign_district_id" class="form-select" onchange="onModalDistrictChange(this.value, 'reassign_block_id')" required>
                                <option value="">-- Choose District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">New Block <span class="text-danger">*</span></label>
                            <select name="block_id" id="reassign_block_id" class="form-select" required>
                                <option value="">-- Select District First --</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reset Operator Password -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header bg-danger text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-key-fill me-2"></i>Reset Password
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin/operators/reset-password">
                <?= csrf_field() ?>
                <input type="hidden" name="operator_id" id="reset_operator_id">
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <div class="small text-muted">Operator:</div>
                        <div class="fw-bold fs-6 text-dark" id="reset_operator_name">-</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">New Password (Min 6 chars) <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" minlength="6" class="form-control" placeholder="Enter new strong password" required>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.allBlocksGrouped = <?= json_encode($allBlocksGrouped ?? []) ?>;

function onModalDistrictChange(districtId, blockSelectId, selectedBlockId = 0) {
    const blockSelect = document.getElementById(blockSelectId);
    if (!blockSelect) return;
    blockSelect.innerHTML = '<option value="">-- Choose Block --</option>';

    if (!districtId || !window.allBlocksGrouped[districtId]) {
        return;
    }

    const blocks = window.allBlocksGrouped[districtId];
    blocks.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = b.name;
        if (selectedBlockId && parseInt(selectedBlockId) === parseInt(b.id)) {
            opt.selected = true;
        }
        blockSelect.appendChild(opt);
    });
}

function openReassignModal(opId, opName, currentDistId, currentBlockId) {
    document.getElementById('reassign_operator_id').value = opId;
    document.getElementById('reassign_operator_name').textContent = opName;
    const distSelect = document.getElementById('reassign_district_id');
    distSelect.value = currentDistId || '';
    onModalDistrictChange(currentDistId, 'reassign_block_id', currentBlockId);
    new bootstrap.Modal(document.getElementById('reassignOperatorModal')).show();
}

function openResetPasswordModal(opId, opName) {
    document.getElementById('reset_operator_id').value = opId;
    document.getElementById('reset_operator_name').textContent = opName;
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
