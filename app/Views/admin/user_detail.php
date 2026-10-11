<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Breadcrumb / Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <a href="/admin/users" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Users List
        </a>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-person-gear text-primary me-2"></i>Manage User: <?= e($targetUser['name']) ?>
        </h1>
        <div class="text-muted small">
            Role: <span class="badge bg-primary text-uppercase"><?= e($targetUser['role']) ?></span> &bull; Mobile: <strong><?= e($targetUser['mobile']) ?></strong> &bull; Email: <strong><?= e($targetUser['email']) ?></strong>
        </div>
    </div>
    <div class="text-end">
        <span class="fs-5 fw-bold text-primary border bg-light px-3 py-2 rounded-3 d-inline-block">
            <i class="bi bi-wallet2 text-warning me-1"></i> Balance: ₹<?= number_format((float)($targetUser['balance'] ?? 0), 2) ?>
        </span>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: User Status & Location Permissions -->
    <div class="col-lg-6">
        <!-- Account Status & Distributor Form -->
        <div class="wb-card mb-4">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>Account Status &amp; Hierarchy</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/users/update-status">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="ACTIVE" <?= $targetUser['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                            <option value="INACTIVE" <?= $targetUser['status'] === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                            <option value="BLOCKED" <?= $targetUser['status'] === 'BLOCKED' ? 'selected' : '' ?>>BLOCKED</option>
                        </select>
                    </div>

                    <?php if ($targetUser['role'] === 'USER'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Assign Supervising Distributor</label>
                            <select name="parent_distributor_id" class="form-select">
                                <option value="">-- No Distributor (Direct) --</option>
                                <?php foreach ($distributors as $dist): ?>
                                    <option value="<?= $dist['id'] ?>" <?= (int)$targetUser['parent_distributor_id'] === (int)$dist['id'] ? 'selected' : '' ?>>
                                        <?= e($dist['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="assigned_all_districts" id="all_dist_switch" value="1"
                               <?= (int)$targetUser['assigned_all_districts'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="all_dist_switch">
                            Grant Universal Bihar Access (All 38 Districts &amp; Blocks)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-save me-1"></i> Save Account Updates
                    </button>
                </form>
            </div>
        </div>

        <!-- Location Assignments / Permissions -->
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-geo-alt-fill me-2 text-danger"></i>Permitted Districts &amp; Blocks</span>
            </div>
            <div class="card-body p-4">
                <!-- Add Location Permission Form -->
                <form method="POST" action="/admin/users/add-perm" class="row g-2 mb-4 p-3 bg-light rounded-3 border">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Select District</label>
                        <select name="district_id" id="district_select" class="form-select form-select-sm" required>
                            <option value="">-- Choose District --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Select Block (Optional)</label>
                        <select name="block_id" id="block_select" class="form-select form-select-sm">
                            <option value="">-- All Blocks in District --</option>
                        </select>
                    </div>

                    <div class="col-12 text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-primary fw-semibold">
                            <i class="bi bi-plus-circle me-1"></i> Add Permission
                        </button>
                    </div>
                </form>

                <!-- Current Permissions List -->
                <?php if (empty($permissions)): ?>
                    <p class="text-muted small mb-0 text-center py-3">No specific district/block assigned yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>District</th>
                                    <th>Block Scope</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permissions as $p): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($p['district_name']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= e($p['block_name'] ?: 'All Blocks') ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="/admin/users/remove-perm" onsubmit="return confirm('Remove location assignment?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                                                <input type="hidden" name="perm_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2 small">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Manual Wallet Adjustment -->
    <div class="col-lg-6">
        <!-- Manual Balance Adjustment Card -->
        <div class="wb-card border-warning mb-4 shadow-sm">
            <div class="wb-card-header bg-warning-subtle">
                <span class="fw-bold"><i class="bi bi-cash-stack me-2 text-warning-emphasis"></i>Manual Wallet Adjustment (Audit Mandatory)</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/users/wallet-adjust" onsubmit="return confirm('Are you sure you want to apply this balance adjustment?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Adjustment Direction</label>
                            <select name="direction" class="form-select" required>
                                <option value="CREDIT">CREDIT (+) Add to Wallet</option>
                                <option value="DEBIT">DEBIT (-) Deduct from Wallet</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount (INR) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="amount" min="1" max="100000" step="0.01" class="form-control fw-bold" placeholder="e.g. 500.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mandatory Reason / Justification <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="Explain the administrative reason for this balance adjustment (permanent audit trail)..." required></textarea>
                        <div class="form-text small text-danger">This reason will be recorded in the immutable wallet ledger and audit log.</div>
                    </div>

                    <button type="submit" class="btn btn-warning fw-bold w-100 py-2">
                        <i class="bi bi-check-circle me-1"></i> Apply Wallet Adjustment
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Ledger Transactions</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($passbook['transactions'])): ?>
                    <div class="text-center py-4 text-muted small">No transactions on record.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref / Time</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>New Bal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($passbook['transactions'] as $tx): ?>
                                    <tr>
                                        <td>
                                            <div class="small font-monospace text-primary"><?= e($tx['txn_ref']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= date('d M, h:i A', strtotime($tx['created_at'])) ?></div>
                                        </td>
                                        <td class="small"><?= e($tx['type']) ?></td>
                                        <td class="<?= $tx['direction'] === 'CREDIT' ? 'text-credit' : 'text-debit' ?> fw-semibold small">
                                            <?= $tx['direction'] === 'CREDIT' ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                                        </td>
                                        <td class="small fw-bold">₹<?= number_format($tx['balance_after'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
