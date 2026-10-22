<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Top Navigation & Action Bar -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item">
                <a href="/admin/users" class="text-decoration-none text-muted">
                    <i class="bi bi-people me-1"></i>Users Directory
                </a>
            </li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">
                <?= e($targetUser['name']) ?>
            </li>
        </ol>
    </nav>
    <a href="/admin/users" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-2xs">
        <i class="bi bi-arrow-left me-1"></i> Back to Users
    </a>
</div>

<!-- Modern User Profile Hero Card -->
<div class="wb-card bg-white border rounded-4 shadow-sm p-4 mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <!-- Left: Avatar & Key User Details -->
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-3 border border-primary-subtle" style="width: 58px; height: 58px; flex-shrink: 0;">
                <?= strtoupper(substr($targetUser['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h4 fw-bold mb-0 text-dark"><?= e($targetUser['name']) ?></h1>
                    <span class="badge <?= $targetUser['status'] === 'ACTIVE' ? 'bg-success' : ($targetUser['status'] === 'BLOCKED' ? 'bg-danger' : 'bg-secondary') ?> rounded-pill px-2.5 py-1">
                        <?= e($targetUser['status']) ?>
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1">
                        <?= e($targetUser['role']) ?>
                    </span>
                    <?php if ($operatorAccessActive): ?>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-person-badge me-1"></i>Operator Active
                        </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap text-muted small">
                    <span><i class="bi bi-telephone text-primary me-1"></i><?= e($targetUser['mobile']) ?></span>
                    <?php if (!empty($targetUser['email'])): ?>
                        <span><i class="bi bi-envelope text-primary me-1"></i><?= e($targetUser['email']) ?></span>
                    <?php endif; ?>
                    <span><i class="bi bi-hash text-muted me-0.5"></i>ID: <strong>#<?= (int)$targetUser['id'] ?></strong></span>
                    <?php if (!empty($targetUser['created_at'])): ?>
                        <span><i class="bi bi-calendar3 text-muted me-1"></i><?= date('d M Y', strtotime($targetUser['created_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Stat Pills -->
        <div class="d-flex align-items-center gap-2 ms-md-auto flex-wrap">
            <div class="p-2.5 px-3 bg-success-subtle border border-success-subtle rounded-3 text-center min-w-100">
                <div class="text-success-emphasis fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">WALLET BALANCE</div>
                <div class="fs-5 fw-bold text-success lh-1 mt-1">₹<?= number_format((float)($targetUser['balance'] ?? 0), 2) ?></div>
            </div>
            <div class="p-2.5 px-3 bg-light border rounded-3 text-center min-w-100">
                <div class="text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">ASSIGNED BLOCKS</div>
                <div class="fs-5 fw-bold text-dark lh-1 mt-1"><?= count($assignedBlockRows) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- LEFT COLUMN: Profile Location, Account Settings, Wallet -->
    <div class="col-lg-5">

        <!-- Center / Shop Location -->
        <div class="wb-card mb-4 shadow-sm border rounded-4 bg-white">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="bi bi-shop text-primary"></i>
                    <span>Center & Shop Location</span>
                </div>
                <button type="button" class="btn btn-xs btn-outline-primary fw-semibold rounded-pill px-3 shadow-2xs py-1" data-bs-toggle="modal" data-bs-target="#editUserDetailLocationModal">
                    <i class="bi bi-pencil-square me-1"></i> Edit
                </button>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Center Name</div>
                        <div class="fw-bold text-dark mt-0.5">
                            <?= !empty($targetUser['center_name']) ? e($targetUser['center_name']) : '<span class="text-muted fst-italic small">Not Set</span>' ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">State</div>
                        <div class="fw-semibold text-dark mt-0.5">
                            <?= e($targetUser['profile_state'] ?? 'Bihar') ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">District</div>
                        <div class="mt-1">
                            <?php if (!empty($targetUser['profile_district_name'])): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($targetUser['profile_district_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border rounded-pill">Not Set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Block</div>
                        <div class="mt-1">
                            <?php if (!empty($targetUser['profile_block_name'])): ?>
                                <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill">
                                    <?= e($targetUser['profile_block_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border rounded-pill">Not Set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Full Shop Address</div>
                        <div class="p-2.5 bg-light rounded-3 border mt-1 small text-secondary">
                            <?= !empty($targetUser['center_address']) ? nl2br(e($targetUser['center_address'])) : '<span class="text-muted fst-italic">No address provided.</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Settings -->
        <div class="wb-card mb-4 shadow-sm border rounded-4 bg-white">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <span class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="bi bi-sliders text-primary"></i>
                    <span>Account Settings</span>
                </span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/users/update-status">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="font-size: 0.72rem;">Account Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="ACTIVE" <?= $targetUser['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                            <option value="INACTIVE" <?= $targetUser['status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                            <option value="BLOCKED" <?= $targetUser['status'] === 'BLOCKED' ? 'selected' : '' ?>>Blocked</option>
                        </select>
                    </div>

                    <?php if ($targetUser['role'] === 'USER'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="font-size: 0.72rem;">Supervising Distributor</label>
                            <select name="parent_distributor_id" class="form-select form-select-sm">
                                <option value="">-- Direct (None) --</option>
                                <?php foreach ($distributors as $dist): ?>
                                    <option value="<?= $dist['id'] ?>" <?= (int)$targetUser['parent_distributor_id'] === (int)$dist['id'] ? 'selected' : '' ?>>
                                        <?= e($dist['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($targetUser['role'] !== 'OPERATOR'): ?>
                        <div class="form-check form-switch mb-3 p-3 bg-light rounded-3 border">
                            <input class="form-check-input ms-0 me-3" type="checkbox" name="assigned_all_districts" id="all_dist_switch" value="1"
                                   <?= (int)$targetUser['assigned_all_districts'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-dark cursor-pointer small" for="all_dist_switch">
                                Universal Bihar Access (All 38 Districts)
                            </label>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-sm btn-primary fw-semibold rounded-pill px-4">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Wallet & Balance Adjustment -->
        <div class="wb-card mb-4 shadow-sm border rounded-4 bg-white">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="bi bi-wallet2 text-success"></i>
                    <span>Wallet & Balance</span>
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                    ₹<?= number_format((float)($targetUser['balance'] ?? 0), 2) ?>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 border text-center">
                            <div class="text-muted small fw-medium" style="font-size: 0.68rem;">CURRENT</div>
                            <div class="fw-bold text-success mt-0.5">₹<?= number_format((float)($targetUser['balance'] ?? 0), 0) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 border text-center">
                            <div class="text-muted small fw-medium" style="font-size: 0.68rem;">TOTAL IN</div>
                            <div class="fw-bold text-dark mt-0.5">₹<?= number_format((float)($targetUser['total_credited'] ?? 0), 0) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 border text-center">
                            <div class="text-muted small fw-medium" style="font-size: 0.68rem;">TOTAL OUT</div>
                            <div class="fw-bold text-secondary mt-0.5">₹<?= number_format((float)($targetUser['total_debited'] ?? 0), 0) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Adjustment Form -->
                <div class="border rounded-3 p-3 bg-light">
                    <div class="fw-semibold small text-dark mb-2 d-flex align-items-center gap-1">
                        <i class="bi bi-cash-stack text-warning"></i> Quick Balance Adjustment
                    </div>
                    <form method="POST" action="/admin/users/wallet-adjust" onsubmit="return confirm('Apply this wallet balance adjustment?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                        <div class="row g-2 mb-2">
                            <div class="col-sm-5">
                                <select name="direction" class="form-select form-select-sm" required>
                                    <option value="CREDIT">Credit (+)</option>
                                    <option value="DEBIT">Debit (-)</option>
                                </select>
                            </div>
                            <div class="col-sm-7">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white">₹</span>
                                    <input type="number" name="amount" min="1" max="100000" step="0.01" class="form-control fw-bold" placeholder="Amount" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Adjustment reason / note" required>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm fw-bold w-100 rounded-pill shadow-2xs">
                            <i class="bi bi-arrow-repeat me-1"></i> Update Balance
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Assigned Work Locations, Add Assignment, Operator Status Controls -->
    <div class="col-lg-7">

        <!-- Assigned Work Locations & Codes -->
        <div class="wb-card mb-4 shadow-sm border rounded-4 bg-white">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="bi bi-geo-alt text-primary"></i>
                    <span>Assigned Work Locations</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5 small">
                        <?= count($assignedBlockRows) ?>
                    </span>
                </div>
                <div>
                    <?php if ($operatorAccessActive): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-check-circle-fill me-1"></i>Active
                        </span>
                    <?php elseif ($hasOperatorCapability): ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-pause-circle-fill me-1"></i>Suspended
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2.5 py-1">
                            Not Granted
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($assignedBlockRows)): ?>
                    <div class="text-center py-5 px-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle text-muted mb-2" style="width: 52px; height: 52px;">
                            <i class="bi bi-pin-map fs-3 text-secondary"></i>
                        </div>
                        <div class="fw-semibold text-dark">No Locations Assigned</div>
                        <div class="small text-muted mt-1">Assign a district and block below to configure staff application routing.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr style="font-size: 0.75rem;" class="text-uppercase text-muted">
                                    <th class="ps-4">District</th>
                                    <th>Block</th>
                                    <th>Service Codes</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedBlockRows as $idx => $blk): ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold text-dark small"><?= e($blk['district_name']) ?></td>
                                        <td class="small fw-semibold text-primary"><?= e($blk['block_name']) ?></td>
                                        <td>
                                            <?php if (!empty($blk['codes'])): ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($blk['codes'] as $c): ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size: 0.68rem;"><?= e($c) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill small" style="font-size: 0.68rem;">All / None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$blk['is_active'] === 1): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill py-0.5 px-2" style="font-size: 0.68rem;">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill py-0.5 px-2" style="font-size: 0.68rem;">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm shadow-2xs">
                                                <!-- Edit Codes Modal Trigger -->
                                                <button type="button" class="btn btn-outline-primary btn-sm py-0.5 px-2"
                                                        onclick="openEditCodesModal(<?= (int)$blk['district_id'] ?>, '<?= e(addslashes($blk['district_name'])) ?>', <?= (int)$blk['block_id'] ?>, '<?= e(addslashes($blk['block_name'])) ?>', <?= htmlspecialchars(json_encode($blk['codes']), ENT_QUOTES) ?>)"
                                                        title="Edit Codes">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>

                                                <!-- Toggle Active/Inactive -->
                                                <form method="POST" action="/admin/operators/toggle-block-assignment" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                                                    <input type="hidden" name="district_id" value="<?= (int)$blk['district_id'] ?>">
                                                    <input type="hidden" name="block_id" value="<?= (int)$blk['block_id'] ?>">
                                                    <input type="hidden" name="is_active" value="<?= (int)$blk['is_active'] === 1 ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm py-0.5 px-2" title="<?= (int)$blk['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>">
                                                        <i class="bi <?= (int)$blk['is_active'] === 1 ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Remove Block Assignment -->
                                                <form method="POST" action="/admin/operators/remove-block-assignment" class="d-inline" onsubmit="return confirm('Remove <?= htmlspecialchars($blk['block_name'], ENT_QUOTES) ?> (<?= htmlspecialchars($blk['district_name'], ENT_QUOTES) ?>)?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                                                    <input type="hidden" name="district_id" value="<?= (int)$blk['district_id'] ?>">
                                                    <input type="hidden" name="block_id" value="<?= (int)$blk['block_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0.5 px-2" title="Remove block">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Block Assignment Card -->
        <div class="wb-card mb-4 shadow-sm border rounded-4 bg-white">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle text-success"></i>
                    <span>Add Work Location</span>
                </span>
                <span id="add_blk_codes_badge" class="badge bg-secondary-subtle text-secondary small rounded-pill px-2.5 py-1">Select Block</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/operators/save-block-assignment" id="addBlockAssignmentForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size: 0.72rem;">District <span class="text-danger">*</span></label>
                            <select name="district_id" id="add_blk_district_id" class="form-select form-select-sm" onchange="onAddDistrictChange(this.value)" required>
                                <option value="">-- Choose District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size: 0.72rem;">Block <span class="text-danger">*</span></label>
                            <select name="block_id" id="add_blk_block_id" class="form-select form-select-sm" onchange="onAddBlockChange()" required disabled>
                                <option value="">-- First Select District --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase d-block" style="font-size: 0.72rem;">Application Codes <span class="text-danger">*</span></label>
                        <div id="add_blk_codes_container" class="p-3 bg-light border rounded-3 d-flex flex-wrap gap-2 align-items-center" style="min-height: 52px;">
                            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Select District & Block above to load codes.</span>
                        </div>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size: 0.72rem;">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="ACTIVE" selected>Active</option>
                                <option value="INACTIVE">Inactive</option>
                            </select>
                        </div>
                        <div class="col-sm-6 text-sm-end">
                            <button type="submit" class="btn btn-sm btn-success fw-semibold rounded-pill px-4 w-100 w-sm-auto shadow-2xs">
                                <i class="bi bi-plus-lg me-1"></i> Add Location
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Operator Access Controls -->
        <div class="wb-card mb-4 shadow-sm border rounded-4 bg-white">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock text-primary"></i>
                    <span>Operator Access Controls</span>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <div class="fw-semibold text-dark small">Operator Privileges</div>
                        <div class="text-muted small">
                            <?= $operatorAccessActive ? 'Active — User can process assigned block applications.' : ($hasOperatorCapability ? 'Suspended — Processing paused; user remains in Retailer mode.' : 'Not granted.') ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($operatorAccessActive): ?>
                            <form method="POST" action="/admin/operators/suspend-access" class="d-inline" onsubmit="return confirm('Suspend operator access for <?= htmlspecialchars($targetUser['name'], ENT_QUOTES) ?>?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning fw-semibold rounded-pill px-3 shadow-2xs">
                                    <i class="bi bi-pause-circle me-1"></i> Suspend Access
                                </button>
                            </form>
                        <?php elseif ($hasOperatorCapability): ?>
                            <form method="POST" action="/admin/operators/restore-access" class="d-inline" onsubmit="return confirm('Restore operator access for <?= htmlspecialchars($targetUser['name'], ENT_QUOTES) ?>?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success fw-semibold rounded-pill px-3 shadow-2xs">
                                    <i class="bi bi-play-circle me-1"></i> Restore Access
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($hasOperatorCapability): ?>
                            <form method="POST" action="/admin/operators/remove-access" class="d-inline" onsubmit="return confirm('Completely remove operator role for <?= htmlspecialchars($targetUser['name'], ENT_QUOTES) ?>? Retailer account and balance will remain safe.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger fw-semibold rounded-pill px-3 shadow-2xs">
                                    <i class="bi bi-trash me-1"></i> Remove Role
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT APPLICATION CODES FOR A BLOCK  -->
<!-- ========================================== -->
<div class="modal fade" id="modalEditBlockCodes" tabindex="-1" aria-labelledby="modalEditBlockCodesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">
            <form method="POST" action="/admin/operators/save-block-assignment" id="editCodesForm">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                <input type="hidden" name="district_id" id="edit_modal_dist_id" value="">
                <input type="hidden" name="block_id" id="edit_modal_blk_id" value="">
                <input type="hidden" name="status" value="ACTIVE">

                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title fw-bold fs-6" id="modalEditBlockCodesLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Service Codes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="p-2.5 bg-light rounded-3 border mb-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase" style="font-size: 0.68rem;">District:</span>
                            <strong class="text-dark small ms-1" id="edit_modal_dist_name">--</strong>
                        </div>
                        <div>
                            <span class="text-muted small text-uppercase" style="font-size: 0.68rem;">Block:</span>
                            <strong class="text-primary small ms-1" id="edit_modal_blk_name">--</strong>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label small fw-semibold text-muted text-uppercase mb-0" style="font-size: 0.72rem;">Assigned Service Codes</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none small" onclick="toggleAllEditCodes(true)">Select All</button>
                                <span class="text-muted small">&bull;</span>
                                <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none small text-muted" onclick="toggleAllEditCodes(false)">Clear</button>
                            </div>
                        </div>
                        <div id="edit_modal_codes_container" class="p-3 bg-light border rounded-3 d-flex flex-wrap gap-2" style="max-height: 220px; overflow-y: auto;">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top py-2 px-4 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold rounded-pill px-4 shadow-2xs">
                        <i class="bi bi-check-lg me-1"></i> Update Codes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =================================================== -->
<!-- MODAL: EDIT USER PROFILE LOCATION (SHOP / KIOSK)   -->
<!-- =================================================== -->
<div class="modal fade" id="editUserDetailLocationModal" tabindex="-1" aria-labelledby="editUserDetailLocationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header bg-dark text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold fs-6" id="editUserDetailLocationModalLabel">
                    <i class="bi bi-geo-alt-fill text-warning me-2"></i>Update Shop / Profile Location
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/admin/users/update-profile-location">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int)$targetUser['id'] ?>">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="font-size: 0.72rem;">User</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= e($targetUser['name']) ?> (<?= e($targetUser['mobile']) ?>)" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="font-size: 0.72rem;">Center / Kiosk Name</label>
                        <input type="text" name="center_name" class="form-control form-control-sm" value="<?= e($targetUser['center_name'] ?? '') ?>" placeholder="e.g. Digital Seva Kendra">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="font-size: 0.72rem;">District <span class="text-danger">*</span></label>
                            <select name="profile_district_id" id="detail_edit_district_id" class="form-select form-select-sm" onchange="onUserDetailDistrictChange(this.value)" required>
                                <option value="">-- Choose District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>" <?= (int)($targetUser['profile_district_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="font-size: 0.72rem;">Block <span class="text-danger">*</span></label>
                            <select name="profile_block_id" id="detail_edit_block_id" class="form-select form-select-sm" required>
                                <option value="">-- Select District First --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="font-size: 0.72rem;">Center / Shop Full Address</label>
                        <textarea name="center_address" class="form-control form-control-sm" rows="2" placeholder="Market, Shop No., Landmark, Pincode"><?= e($targetUser['center_address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-2.5 rounded-bottom-4">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold rounded-pill px-4 shadow-2xs">
                        <i class="bi bi-save me-1"></i> Save Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Section: Dynamic Block Cascading for Adding Block Assignment
function onAddDistrictChange(districtId) {
    const blockSelect = document.getElementById('add_blk_block_id');
    const container = document.getElementById('add_blk_codes_container');
    const badge = document.getElementById('add_blk_codes_badge');

    if (!blockSelect) return;
    blockSelect.innerHTML = '<option value="">-- Loading blocks... --</option>';
    blockSelect.disabled = true;

    if (container) {
        container.innerHTML = '<span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Select District & Block above to load codes.</span>';
    }
    if (badge) {
        badge.textContent = 'Select Block';
        badge.className = 'badge bg-secondary-subtle text-secondary small rounded-pill px-2.5 py-1';
    }

    if (!districtId) {
        blockSelect.innerHTML = '<option value="">-- First Select District --</option>';
        return;
    }

    fetch('/api/blocks?district_id=' + encodeURIComponent(districtId))
        .then(res => res.json())
        .then(blocks => {
            blockSelect.innerHTML = '<option value="">-- Choose Block --</option>';
            if (Array.isArray(blocks) && blocks.length > 0) {
                blocks.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id || b.block_id;
                    opt.textContent = b.name || b.block_name;
                    blockSelect.appendChild(opt);
                });
                blockSelect.disabled = false;
            } else {
                blockSelect.innerHTML = '<option value="">-- No blocks available --</option>';
            }
        }).catch(() => {
            blockSelect.innerHTML = '<option value="">-- Error loading blocks --</option>';
        });
}

// Section: Load Active Codes with Rates for Selected Block
async function onAddBlockChange() {
    const distId = document.getElementById('add_blk_district_id')?.value;
    const blkId = document.getElementById('add_blk_block_id')?.value;
    const container = document.getElementById('add_blk_codes_container');
    const badge = document.getElementById('add_blk_codes_badge');

    if (!container) return;
    if (!distId || !blkId) {
        container.innerHTML = '<span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Select District & Block above to load codes.</span>';
        if (badge) { badge.textContent = 'Select Block'; badge.className = 'badge bg-secondary-subtle text-secondary small rounded-pill px-2.5 py-1'; }
        return;
    }

    container.innerHTML = '<span class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Loading service codes...</span>';
    if (badge) { badge.textContent = 'Loading...'; badge.className = 'badge bg-info-subtle text-info small rounded-pill px-2.5 py-1'; }

    try {
        const res = await fetch(`/admin/staff-routing/api-block-data?district_id=${encodeURIComponent(distId)}&block_id=${encodeURIComponent(blkId)}`);
        const data = await res.json();
        const codes = (data.rate_codes && data.rate_codes.length > 0) ? data.rate_codes : (data.active_codes || []);

        if (!codes || codes.length === 0) {
            const fallbackCodes = <?= json_encode($allApplicationCodes) ?>;
            renderCodeCheckboxes(container, fallbackCodes, true);
            if (badge) { badge.textContent = `${fallbackCodes.length} Available`; badge.className = 'badge bg-warning-subtle text-warning small rounded-pill px-2.5 py-1'; }
            return;
        }

        renderCodeCheckboxes(container, codes, true);
        if (badge) { badge.textContent = `${codes.length} Codes Active`; badge.className = 'badge bg-success-subtle text-success small rounded-pill px-2.5 py-1'; }
    } catch (e) {
        const fallbackCodes = <?= json_encode($allApplicationCodes) ?>;
        renderCodeCheckboxes(container, fallbackCodes, true);
        if (badge) { badge.textContent = 'Default Codes'; badge.className = 'badge bg-secondary-subtle text-secondary small rounded-pill px-2.5 py-1'; }
    }
}

function renderCodeCheckboxes(container, codesList, defaultChecked = false, checkedSet = []) {
    let html = '';
    const upperChecked = checkedSet.map(c => String(c).toUpperCase());
    const isModal = container.id.includes('modal');
    const prefix = isModal ? 'mcode_' : 'bcode_';

    codesList.forEach(item => {
        const code = (typeof item === 'object' ? item.code : item).toUpperCase();
        const fee = (typeof item === 'object' && item.fee_amount) ? ('₹' + parseFloat(item.fee_amount).toFixed(0)) : '';
        const isChecked = upperChecked.length > 0 ? upperChecked.includes(code) : defaultChecked;
        const inputId = `${prefix}${code}`;

        html += `
            <div class="form-check form-check-inline m-0">
                <input class="btn-check" type="checkbox" name="application_codes[]" value="${code}" id="${inputId}" ${isChecked ? 'checked' : ''} autocomplete="off">
                <label class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-2xs fw-semibold small" for="${inputId}">
                    <span>${code}</span>
                    ${fee ? `<span class="badge bg-light text-dark border ms-1" style="font-size:0.65rem;">${fee}</span>` : ''}
                </label>
            </div>
        `;
    });
    container.innerHTML = html;
}

// Modal: Open and load codes for a specific block
async function openEditCodesModal(distId, distName, blkId, blkName, currentCodes) {
    document.getElementById('edit_modal_dist_id').value = distId;
    document.getElementById('edit_modal_blk_id').value = blkId;
    document.getElementById('edit_modal_dist_name').textContent = distName;
    document.getElementById('edit_modal_blk_name').textContent = blkName;

    const container = document.getElementById('edit_modal_codes_container');
    container.innerHTML = '<div class="text-muted small py-2"><span class="spinner-border spinner-border-sm me-1"></span>Loading codes...</div>';

    const modal = new bootstrap.Modal(document.getElementById('modalEditBlockCodes'));
    modal.show();

    try {
        const res = await fetch(`/admin/staff-routing/api-block-data?district_id=${encodeURIComponent(distId)}&block_id=${encodeURIComponent(blkId)}`);
        const data = await res.json();
        let codes = (data.rate_codes && data.rate_codes.length > 0) ? data.rate_codes : (data.active_codes || []);

        if (!codes || codes.length === 0) {
            codes = <?= json_encode($allApplicationCodes) ?>;
        }

        renderCodeCheckboxes(container, codes, false, currentCodes || []);
    } catch (e) {
        const fallbackCodes = <?= json_encode($allApplicationCodes) ?>;
        renderCodeCheckboxes(container, fallbackCodes, false, currentCodes || []);
    }
}

function toggleAllEditCodes(checkState) {
    const chks = document.querySelectorAll('#edit_modal_codes_container input[type="checkbox"]');
    chks.forEach(c => c.checked = checkState);
}

// Profile Location Cascading
window.userDetailBlocksGrouped = <?= json_encode($allBlocksGrouped ?? []) ?>;

function onUserDetailDistrictChange(distId, selectedBlockId = 0) {
    const blkSelect = document.getElementById('detail_edit_block_id');
    if (!blkSelect) return;
    blkSelect.innerHTML = '<option value="">-- Select Block --</option>';
    if (!distId || !window.userDetailBlocksGrouped || !window.userDetailBlocksGrouped[distId]) {
        blkSelect.disabled = true;
        return;
    }
    const blocks = window.userDetailBlocksGrouped[distId];
    blocks.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = b.name + (b.name_hi ? ' (' + b.name_hi + ')' : '');
        if (selectedBlockId && parseInt(b.id) === parseInt(selectedBlockId)) {
            opt.selected = true;
        }
        blkSelect.appendChild(opt);
    });
    blkSelect.disabled = false;
}

document.addEventListener('DOMContentLoaded', function () {
    const currentDist = <?= (int)($targetUser['profile_district_id'] ?? 0) ?>;
    const currentBlock = <?= (int)($targetUser['profile_block_id'] ?? 0) ?>;
    if (currentDist > 0) {
        onUserDetailDistrictChange(currentDist, currentBlock);
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
