<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Breadcrumb / Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <a href="/admin/users" class="btn btn-sm btn-outline-secondary mb-2 rounded-pill px-3 shadow-2xs">
            <i class="bi bi-arrow-left me-1"></i> Back to Users List
        </a>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <i class="bi bi-person-gear text-primary"></i>
            <span>User Management: <?= e($targetUser['name']) ?></span>
        </h1>
        <div class="text-muted small">
            Base Role: <span class="badge bg-primary text-uppercase"><?= e($targetUser['role']) ?></span> 
            &bull; Mobile: <strong><?= e($targetUser['mobile']) ?></strong> 
            &bull; Email: <strong><?= e($targetUser['email']) ?></strong>
            &bull; User ID: <strong>#<?= (int)$targetUser['id'] ?></strong>
        </div>
    </div>
    <div class="text-end d-flex align-items-center gap-2">
        <span class="fs-6 fw-bold text-success border bg-white px-3 py-2 rounded-pill shadow-xs d-inline-flex align-items-center gap-2">
            <i class="bi bi-wallet2 text-success fs-5"></i> 
            <span>Wallet: ₹<?= number_format((float)($targetUser['balance'] ?? 0), 2) ?></span>
        </span>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- LEFT COLUMN: Account Information, Retailer Access, Operator Access -->
    <div class="col-lg-6">

        <!-- ========================================== -->
        <!-- SECTION 1: ACCOUNT INFORMATION             -->
        <!-- ========================================== -->
        <div class="wb-card mb-4 shadow-sm border rounded-3">
            <div class="wb-card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6">
                    <span class="badge bg-primary rounded-circle me-2 px-2 py-1">1</span>
                    Account Information
                </span>
                <span class="badge <?= $targetUser['status'] === 'ACTIVE' ? 'bg-success' : 'bg-danger' ?>">
                    <?= e($targetUser['status']) ?>
                </span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/users/update-status">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="ACTIVE" <?= $targetUser['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE (Fully Enabled)</option>
                            <option value="INACTIVE" <?= $targetUser['status'] === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE (Disabled)</option>
                            <option value="BLOCKED" <?= $targetUser['status'] === 'BLOCKED' ? 'selected' : '' ?>>BLOCKED (Restricted)</option>
                        </select>
                    </div>

                    <?php if ($targetUser['role'] === 'USER'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Supervising Distributor</label>
                            <select name="parent_distributor_id" class="form-select">
                                <option value="">-- Direct (No Supervising Distributor) --</option>
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
                            <label class="form-check-label fw-semibold text-dark cursor-pointer" for="all_dist_switch">
                                Universal Bihar Access (All 38 Districts & Blocks)
                            </label>
                            <div class="small text-muted mt-1">Allows submitting applications across all publicly available blocks in Bihar.</div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4">
                        <i class="bi bi-save me-1"></i> Save Account Updates
                    </button>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 2: RETAILER ACCESS                 -->
        <!-- ========================================== -->
        <div class="wb-card mb-4 shadow-sm border rounded-3">
            <div class="wb-card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6">
                    <span class="badge bg-primary rounded-circle me-2 px-2 py-1">2</span>
                    Retailer Access
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1">
                    <i class="bi bi-check-circle me-1"></i> Retailer Mode Active
                </span>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border border-info-subtle small py-2 px-3 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill text-info fs-5"></i>
                    <div>
                        <strong>Bihar-Wide Application Submission:</strong> Retailer can submit citizen service applications for any publicly enabled District + Block in Bihar.
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2.5 bg-light rounded-3 border text-center">
                            <div class="text-muted small" style="font-size: 0.72rem;">BALANCE</div>
                            <div class="fw-bold text-success fs-6">₹<?= number_format((float)($targetUser['balance'] ?? 0), 2) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2.5 bg-light rounded-3 border text-center">
                            <div class="text-muted small" style="font-size: 0.72rem;">CREDITED</div>
                            <div class="fw-bold text-dark fs-6">₹<?= number_format((float)($targetUser['total_credited'] ?? 0), 2) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2.5 bg-light rounded-3 border text-center">
                            <div class="text-muted small" style="font-size: 0.72rem;">DEBITED</div>
                            <div class="fw-bold text-secondary fs-6">₹<?= number_format((float)($targetUser['total_debited'] ?? 0), 2) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Manual Wallet Adjustment Card Form -->
                <div class="border rounded-3 p-3 bg-light">
                    <div class="fw-bold small text-dark mb-2 d-flex align-items-center gap-1">
                        <i class="bi bi-cash-stack text-warning"></i> Manual Balance Adjustment (Audited)
                    </div>
                    <form method="POST" action="/admin/users/wallet-adjust" onsubmit="return confirm('Are you sure you want to apply this balance adjustment?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                        <div class="row g-2 mb-2">
                            <div class="col-sm-6">
                                <select name="direction" class="form-select form-select-sm" required>
                                    <option value="CREDIT">CREDIT (+) Add to Wallet</option>
                                    <option value="DEBIT">DEBIT (-) Deduct from Wallet</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="amount" min="1" max="100000" step="0.01" class="form-control fw-bold" placeholder="Amount" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Mandatory adjustment reason (immutable audit log)..." required>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm fw-bold w-100">
                            <i class="bi bi-check-circle me-1"></i> Apply Wallet Adjustment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 3: OPERATOR ACCESS                 -->
        <!-- ========================================== -->
        <div class="wb-card mb-4 shadow-sm border rounded-3">
            <div class="wb-card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6">
                    <span class="badge bg-primary rounded-circle me-2 px-2 py-1">3</span>
                    Operator Access Status
                </span>
                <?php if ($operatorAccessActive): ?>
                    <span class="badge bg-success px-3 py-1.5 fs-7">
                        <i class="bi bi-check-circle-fill me-1"></i> ACTIVE (Enabled)
                    </span>
                <?php elseif ($hasOperatorCapability): ?>
                    <span class="badge bg-warning text-dark px-3 py-1.5 fs-7">
                        <i class="bi bi-pause-circle-fill me-1"></i> SUSPENDED (Disabled)
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary px-3 py-1.5 fs-7">
                        <i class="bi bi-x-circle me-1"></i> NOT GRANTED
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2.5 bg-light rounded-3 border">
                            <div class="text-muted small" style="font-size: 0.72rem;">ASSIGNED COMBINATIONS</div>
                            <div class="fw-bold text-primary fs-5"><?= count($assignedBlockRows) ?> Location(s)</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2.5 bg-light rounded-3 border">
                            <div class="text-muted small" style="font-size: 0.72rem;">SELF-PROCESSING RULE</div>
                            <div class="fw-bold text-success fs-6"><i class="bi bi-shield-check me-1"></i> STRICT ENFORCEMENT</div>
                        </div>
                    </div>
                </div>

                <div class="small text-muted mb-0">
                    <i class="bi bi-info-circle me-1"></i> Operator Mode allows processing applications matching the exact assigned combinations (District + Block + Application Code).
                </div>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Assigned Locations & Codes, Add Block, Suspend/Restore, Remove -->
    <div class="col-lg-6">

        <!-- ========================================== -->
        <!-- SECTION 4: ASSIGNED LOCATIONS & CODES      -->
        <!-- ========================================== -->
        <div class="wb-card mb-4 shadow-sm border rounded-3">
            <div class="wb-card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6">
                    <span class="badge bg-primary rounded-circle me-2 px-2 py-1">4</span>
                    Assigned Locations & Codes
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1">
                    <?= count($assignedBlockRows) ?> Assigned
                </span>
            </div>
            <div class="card-body p-3">
                <?php if (empty($assignedBlockRows)): ?>
                    <div class="alert alert-light border text-center py-4 my-2">
                        <i class="bi bi-geo-alt fs-3 text-muted d-block mb-2"></i>
                        <div class="fw-semibold text-secondary">No Location Assignments Yet</div>
                        <div class="small text-muted">Use Section 5 below to assign District + Block + Application Codes.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr style="font-size: 0.76rem;" class="text-uppercase text-muted">
                                    <th>District</th>
                                    <th>Block</th>
                                    <th>Assigned Codes</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedBlockRows as $idx => $blk): ?>
                                    <tr>
                                        <td class="fw-bold text-dark small"><?= e($blk['district_name']) ?></td>
                                        <td class="small fw-semibold text-primary"><?= e($blk['block_name']) ?></td>
                                        <td>
                                            <?php if (!empty($blk['codes'])): ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($blk['codes'] as $c): ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.68rem;"><?= e($c) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle small" style="font-size: 0.68rem;">No Codes Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$blk['is_active'] === 1): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-1.5" style="font-size: 0.68rem;">ACTIVE</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0.5 px-1.5" style="font-size: 0.68rem;">INACTIVE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <!-- Edit Codes Modal Trigger -->
                                                <button type="button" class="btn btn-outline-primary btn-xs py-1 px-2"
                                                        onclick="openEditCodesModal(<?= (int)$blk['district_id'] ?>, '<?= e(addslashes($blk['district_name'])) ?>', <?= (int)$blk['block_id'] ?>, '<?= e(addslashes($blk['block_name'])) ?>', <?= htmlspecialchars(json_encode($blk['codes']), ENT_QUOTES) ?>)"
                                                        title="Edit Codes for this block">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>

                                                <!-- Toggle Active/Inactive -->
                                                <form method="POST" action="/admin/operators/toggle-block-assignment" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                                                    <input type="hidden" name="district_id" value="<?= (int)$blk['district_id'] ?>">
                                                    <input type="hidden" name="block_id" value="<?= (int)$blk['block_id'] ?>">
                                                    <input type="hidden" name="is_active" value="<?= (int)$blk['is_active'] === 1 ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-xs py-1 px-2" title="<?= (int)$blk['is_active'] === 1 ? 'Deactivate this block' : 'Activate this block' ?>">
                                                        <i class="bi <?= (int)$blk['is_active'] === 1 ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Remove Block Assignment -->
                                                <form method="POST" action="/admin/operators/remove-block-assignment" class="d-inline" onsubmit="return confirm('Remove block assignment for <?= htmlspecialchars($blk['block_name'], ENT_QUOTES) ?> (<?= htmlspecialchars($blk['district_name'], ENT_QUOTES) ?>)? Other block assignments will NOT be affected.');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                                                    <input type="hidden" name="district_id" value="<?= (int)$blk['district_id'] ?>">
                                                    <input type="hidden" name="block_id" value="<?= (int)$blk['block_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-xs py-1 px-2" title="Remove block assignment">
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

        <!-- ========================================== -->
        <!-- SECTION 5: ADD / UPDATE BLOCK ASSIGNMENT   -->
        <!-- ========================================== -->
        <div class="wb-card mb-4 shadow-sm border rounded-3">
            <div class="wb-card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6">
                    <span class="badge bg-primary rounded-circle me-2 px-2 py-1">5</span>
                    Add / Update Block Assignment
                </span>
                <span class="badge bg-success-subtle text-success small" style="font-size: 0.72rem;">Multi-Block Safe</span>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-warning border border-warning-subtle small py-2 px-3 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-warning fs-5"></i>
                    <div>
                        <strong>Non-Overwriting Isolation:</strong> Adding another District + Block does <u>NOT</u> overwrite or affect existing blocks. Each block combination maintains its own independent application codes.
                    </div>
                </div>

                <form method="POST" action="/admin/operators/save-block-assignment" id="addBlockAssignmentForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Select District <span class="text-danger">*</span></label>
                            <select name="district_id" id="add_blk_district_id" class="form-select form-select-sm" onchange="onAddDistrictChange(this.value)" required>
                                <option value="">-- Choose District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Select Block <span class="text-danger">*</span></label>
                            <select name="block_id" id="add_blk_block_id" class="form-select form-select-sm" onchange="onAddBlockChange()" required disabled>
                                <option value="">-- First Select District --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label small fw-bold mb-0">Select Application Codes to Handle <span class="text-danger">*</span></label>
                            <span id="add_blk_codes_badge" class="badge bg-secondary-subtle text-secondary small" style="font-size: 0.7rem;">Select Block</span>
                        </div>
                        <div id="add_blk_codes_container" class="p-3 bg-light border rounded-3 d-flex flex-wrap gap-2" style="min-height: 50px;">
                            <div class="text-muted small py-1"><i class="bi bi-geo-alt me-1"></i>Select District & Block above to load active service codes.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Initial Assignment Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="ACTIVE" selected>ACTIVE (Available immediately)</option>
                            <option value="INACTIVE">INACTIVE (Keep paused)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success fw-bold rounded-pill px-4">
                        <i class="bi bi-plus-circle me-1"></i> Save Block Assignment
                    </button>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 6: SUSPEND / RESTORE OPERATOR ACCESS-->
        <!-- ========================================== -->
        <div class="wb-card mb-4 shadow-sm border rounded-3">
            <div class="wb-card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6">
                    <span class="badge bg-primary rounded-circle me-2 px-2 py-1">6</span>
                    Suspend / Restore Operator Access
                </span>
                <span class="badge <?= $operatorAccessActive ? 'bg-success' : 'bg-secondary' ?>">
                    <?= $operatorAccessActive ? 'Currently Active' : 'Currently Suspended / Inactive' ?>
                </span>
            </div>
            <div class="card-body p-4">
                <?php if ($operatorAccessActive): ?>
                    <p class="small text-muted mb-3">
                        Temporarily suspend Operator Access. The user will immediately lose access to Operator Mode and be redirected to Retailer Mode. Retailer access, submissions, and wallet balance remain 100% active.
                    </p>
                    <form method="POST" action="/admin/operators/suspend-access" onsubmit="return confirm('Suspend operator access for <?= htmlspecialchars($targetUser['name'], ENT_QUOTES) ?>? Retailer account and balance remain active.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                        <button type="submit" class="btn btn-outline-warning fw-semibold rounded-pill px-4">
                            <i class="bi bi-pause-circle me-1"></i> Suspend Operator Access
                        </button>
                    </form>
                <?php else: ?>
                    <p class="small text-muted mb-3">
                        Operator Access is currently suspended or disabled. You can restore access with a single click.
                    </p>
                    <form method="POST" action="/admin/operators/restore-access" onsubmit="return confirm('Restore operator access for <?= htmlspecialchars($targetUser['name'], ENT_QUOTES) ?>?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                        <button type="submit" class="btn btn-success fw-semibold rounded-pill px-4">
                            <i class="bi bi-play-circle me-1"></i> Restore Operator Access
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 7: REMOVE OPERATOR ACCESS          -->
        <!-- ========================================== -->
        <div class="wb-card mb-4 shadow-sm border border-danger-subtle rounded-3">
            <div class="wb-card-header bg-danger-subtle border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-danger fs-6">
                    <span class="badge bg-danger rounded-circle me-2 px-2 py-1">7</span>
                    Remove Operator Access
                </span>
                <span class="badge bg-danger text-white">Irreversible Action</span>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-danger border-danger-subtle small py-2 px-3 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                    <div>
                        <strong>Zero Data Loss Guarantee:</strong> Completely removes Operator Access and all location assignments. 
                        The user's <strong>Retailer account, submitted applications, wallet balance, and passbook history remain 100% safe and intact</strong>.
                    </div>
                </div>

                <form method="POST" action="/admin/operators/remove-access" onsubmit="return confirm('Are you completely sure? This will remove all operator privileges from <?= htmlspecialchars($targetUser['name'], ENT_QUOTES) ?>. Retailer account and wallet balance will NOT be touched.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger fw-semibold rounded-pill px-4">
                        <i class="bi bi-trash me-1"></i> Remove Operator Access
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT APPLICATION CODES FOR A BLOCK  -->
<!-- ========================================== -->
<div class="modal fade" id="modalEditBlockCodes" tabindex="-1" aria-labelledby="modalEditBlockCodesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 shadow border-0">
            <form method="POST" action="/admin/operators/save-block-assignment" id="editCodesForm">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                <input type="hidden" name="district_id" id="edit_modal_dist_id" value="">
                <input type="hidden" name="block_id" id="edit_modal_blk_id" value="">
                <input type="hidden" name="status" value="ACTIVE">

                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title fw-bold" id="modalEditBlockCodesLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Block Application Codes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-muted small" style="font-size: 0.72rem;">DISTRICT</div>
                                <div class="fw-bold text-dark" id="edit_modal_dist_name">--</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small" style="font-size: 0.72rem;">BLOCK</div>
                                <div class="fw-bold text-primary" id="edit_modal_blk_name">--</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label small fw-bold mb-0">Assigned Application Codes</label>
                            <button type="button" class="btn btn-xs btn-link p-0 text-decoration-none small" onclick="toggleAllEditCodes(true)">Select All</button>
                        </div>
                        <div id="edit_modal_codes_container" class="p-3 bg-light border rounded-3 d-flex flex-wrap gap-2" style="max-height: 220px; overflow-y: auto;">
                            <!-- Populated dynamically via JS -->
                        </div>
                        <div class="form-text small text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i> Updating codes for this block will NOT affect other assigned blocks.
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top py-2 px-4">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold rounded-pill px-4">
                        <i class="bi bi-check-circle me-1"></i> Update Codes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Section 5: Dynamic Block Cascading
function onAddDistrictChange(districtId) {
    const blockSelect = document.getElementById('add_blk_block_id');
    const container = document.getElementById('add_blk_codes_container');
    const badge = document.getElementById('add_blk_codes_badge');

    if (!blockSelect) return;
    blockSelect.innerHTML = '<option value="">-- Loading blocks... --</option>';
    blockSelect.disabled = true;

    if (container) {
        container.innerHTML = '<div class="text-muted small py-1"><i class="bi bi-geo-alt me-1"></i>Select District & Block to load active service codes.</div>';
    }
    if (badge) {
        badge.textContent = 'Select Block';
        badge.className = 'badge bg-secondary-subtle text-secondary small';
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

// Section 5: Load Active Codes with Rates for Selected Block
async function onAddBlockChange() {
    const distId = document.getElementById('add_blk_district_id')?.value;
    const blkId = document.getElementById('add_blk_block_id')?.value;
    const container = document.getElementById('add_blk_codes_container');
    const badge = document.getElementById('add_blk_codes_badge');

    if (!container) return;
    if (!distId || !blkId) {
        container.innerHTML = '<div class="text-muted small py-1"><i class="bi bi-geo-alt me-1"></i>Select District & Block to load active service codes.</div>';
        if (badge) { badge.textContent = 'Select Block'; badge.className = 'badge bg-secondary-subtle text-secondary small'; }
        return;
    }

    container.innerHTML = '<div class="text-muted small py-1"><span class="spinner-border spinner-border-sm me-1"></span>Loading active rates for this block...</div>';
    if (badge) { badge.textContent = 'Loading...'; badge.className = 'badge bg-info-subtle text-info small'; }

    try {
        const res = await fetch(`/admin/staff-routing/api-block-data?district_id=${encodeURIComponent(distId)}&block_id=${encodeURIComponent(blkId)}`);
        const data = await res.json();
        const codes = (data.rate_codes && data.rate_codes.length > 0) ? data.rate_codes : (data.active_codes || []);

        if (!codes || codes.length === 0) {
            // Fallback to all configured system codes
            const fallbackCodes = <?= json_encode($allApplicationCodes) ?>;
            renderCodeCheckboxes(container, fallbackCodes, true);
            if (badge) { badge.textContent = `${fallbackCodes.length} Available`; badge.className = 'badge bg-warning-subtle text-warning small'; }
            return;
        }

        renderCodeCheckboxes(container, codes, true);
        if (badge) { badge.textContent = `${codes.length} Active Code(s)`; badge.className = 'badge bg-success-subtle text-success small'; }
    } catch (e) {
        const fallbackCodes = <?= json_encode($allApplicationCodes) ?>;
        renderCodeCheckboxes(container, fallbackCodes, true);
        if (badge) { badge.textContent = 'Default Codes'; badge.className = 'badge bg-secondary-subtle text-secondary small'; }
    }
}

function renderCodeCheckboxes(container, codesList, defaultChecked = false, checkedSet = []) {
    let html = '';
    const upperChecked = checkedSet.map(c => String(c).toUpperCase());

    codesList.forEach(item => {
        const code = (typeof item === 'object' ? item.code : item).toUpperCase();
        const fee = (typeof item === 'object' && item.fee_amount) ? ('₹' + parseFloat(item.fee_amount).toFixed(0)) : '';
        const isChecked = upperChecked.length > 0 ? upperChecked.includes(code) : defaultChecked;

        html += `
            <div class="form-check d-flex align-items-center gap-1.5 p-1 bg-white rounded border px-2 shadow-2xs">
                <input class="form-check-input mt-0" type="checkbox" name="application_codes[]" value="${code}" id="code_chk_${code}" ${isChecked ? 'checked' : ''}>
                <label class="form-check-label small fw-semibold cursor-pointer text-dark" for="code_chk_${code}">
                    ${code} ${fee ? `<span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">${fee}</span>` : ''}
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
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
