<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Title & Primary Actions -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <i class="bi bi-people-fill text-primary"></i>
            <span>Users & Location Directory</span>
        </h1>
        <p class="text-muted mb-0">District & Block Directory of Kiosks, Retailers, Operators & Administrators</p>
    </div>
    <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/admin/users/location-report" class="btn btn-warning fw-bold shadow-sm text-dark">
            <i class="bi bi-geo-alt-fill me-1"></i> Location Report
        </a>
        <a href="/admin/users/export-csv?<?= e($queryString) ?>" class="btn btn-outline-success fw-bold shadow-sm bg-white">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
        <button type="button" class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#grantOperatorModal">
            <i class="bi bi-person-gear me-1"></i> Grant Operator
        </button>
        <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addOperatorModal">
            <i class="bi bi-person-plus-fill me-1"></i> Add Operator
        </button>
    </div>
</div>

<!-- Summary KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Total Registered Users -->
    <div class="col-6 col-md-2">
        <a href="/admin/users" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-semibold">Total Users</span>
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-1"><i class="bi bi-people fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-dark"><?= number_format($statTotal) ?></div>
                <div class="small text-muted">Registered in Portal</div>
            </div>
        </a>
    </div>

    <!-- Location Set -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?location_status=SET" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition <?= $locationStatus === 'SET' ? 'border-success border-2' : '' ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-success small fw-semibold">Location Set</span>
                    <span class="badge bg-success-subtle text-success rounded-circle p-1"><i class="bi bi-geo-alt-fill fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-success"><?= number_format($statLocationSet) ?></div>
                <div class="small text-muted">Dist & Block Configured</div>
            </div>
        </a>
    </div>

    <!-- Location Not Set -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?location_status=NOT_SET" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition <?= $locationStatus === 'NOT_SET' ? 'border-warning border-2' : '' ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-warning-emphasis small fw-semibold">Location Missing</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-circle p-1"><i class="bi bi-exclamation-triangle-fill fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-warning-emphasis"><?= number_format($statLocationNotSet) ?></div>
                <div class="small text-muted">Needs Location Setup</div>
            </div>
        </a>
    </div>

    <!-- Active Users -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?status=ACTIVE" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition <?= $status === 'ACTIVE' ? 'border-primary border-2' : '' ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-primary small fw-semibold">Active Users</span>
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-1"><i class="bi bi-check2-circle fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-primary"><?= number_format($statActive) ?></div>
                <div class="small text-muted">Enabled Accounts</div>
            </div>
        </a>
    </div>

    <!-- Inactive Users -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?status=INACTIVE" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition <?= $status === 'INACTIVE' ? 'border-danger border-2' : '' ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-danger small fw-semibold">Inactive / Blocked</span>
                    <span class="badge bg-danger-subtle text-danger rounded-circle p-1"><i class="bi bi-slash-circle fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-danger"><?= number_format($statInactive) ?></div>
                <div class="small text-muted">Suspended Accounts</div>
            </div>
        </a>
    </div>

    <!-- Invalid Location Flag (if any exists) -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?location_status=INVALID" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition <?= $locationStatus === 'INVALID' ? 'border-danger border-2' : '' ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-danger small fw-semibold">Invalid Pairs</span>
                    <span class="badge bg-danger-subtle text-danger rounded-circle p-1"><i class="bi bi-shuffle fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold <?= $statInvalid > 0 ? 'text-danger' : 'text-secondary' ?>"><?= number_format($statInvalid) ?></div>
                <div class="small text-muted">Cross-Dist Blocks</div>
            </div>
        </a>
    </div>
</div>

<?php if (!empty($locationMismatch)): ?>
    <div class="alert alert-warning border shadow-xs d-flex align-items-center py-2 px-3 mb-4 rounded-3">
        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning me-2"></i>
        <div>The selected Block does not belong to the selected District. The Block filter was automatically cleared to prevent invalid results.</div>
    </div>
<?php endif; ?>

<!-- Compact Separate Filter Section -->
<div class="wb-card mb-4 border rounded-3 bg-white shadow-2xs">
    <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-funnel-fill text-primary"></i>
            <span class="fw-bold small text-dark">Directory Filters & Search</span>
        </div>
        <div class="small text-muted">
            Found <strong><?= number_format($totalRecords) ?></strong> matching user(s)
        </div>
    </div>
    <div class="card-body p-3">
        <form method="GET" action="/admin/users" id="userDirectoryFilterForm" class="row g-2 align-items-end">
            <!-- Search -->
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary mb-1">Search Keywords</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Name / Mobile / Email / Center..." value="<?= e($search) ?>">
                </div>
            </div>

            <!-- Role Filter -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary mb-1">User Role</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">-- All Roles --</option>
                    <option value="USER" <?= $role === 'USER' ? 'selected' : '' ?>>Retailer / Kiosk</option>
                    <option value="OPERATOR" <?= $role === 'OPERATOR' ? 'selected' : '' ?>>Block Operator</option>
                    <option value="DISTRIBUTOR" <?= $role === 'DISTRIBUTOR' ? 'selected' : '' ?>>Distributor</option>
                    <option value="ADMIN" <?= $role === 'ADMIN' ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                    <option value="BLOCKED" <?= $status === 'BLOCKED' ? 'selected' : '' ?>>BLOCKED</option>
                </select>
            </div>

            <!-- Location Status Filter -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary mb-1">Location Status</label>
                <select name="location_status" class="form-select form-select-sm">
                    <option value="">-- All Locations --</option>
                    <option value="SET" <?= $locationStatus === 'SET' ? 'selected' : '' ?>>Configured (Location Set)</option>
                    <option value="NOT_SET" <?= $locationStatus === 'NOT_SET' ? 'selected' : '' ?>>Location Not Set</option>
                    <option value="INVALID" <?= $locationStatus === 'INVALID' ? 'selected' : '' ?>>Invalid District/Block</option>
                </select>
            </div>

            <!-- District Dropdown -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary mb-1">Profile District</label>
                <select name="district_id" id="filter_district_id" class="form-select form-select-sm" onchange="onFilterDistrictChange(this.value)">
                    <option value="">-- All Districts --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)$districtId === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Block Dropdown (Cascading) -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary mb-1">Profile Block</label>
                <select name="block_id" id="filter_block_id" class="form-select form-select-sm" <?= empty($districtId) ? 'disabled' : '' ?>>
                    <option value="">-- All Blocks --</option>
                    <?php foreach ($availableFilterBlocks as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$blockId === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Registration Date Filter -->
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold text-secondary mb-1">Per Page</label>
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="25" <?= ($limit ?? 25) === 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= ($limit ?? 25) === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= ($limit ?? 25) === 100 ? 'selected' : '' ?>>100</option>
                    <option value="200" <?= ($limit ?? 25) === 200 ? 'selected' : '' ?>>200</option>
                    <option value="500" <?= ($limit ?? 25) === 500 ? 'selected' : '' ?>>500</option>
                    <option value="ALL" <?= ($limit ?? 25) >= 5000 ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary mb-1">Registered Date</label>
                <select name="date_filter" class="form-select form-select-sm">
                    <option value="ALL" <?= $dateFilter === 'ALL' ? 'selected' : '' ?>>All Time</option>
                    <option value="TODAY" <?= $dateFilter === 'TODAY' ? 'selected' : '' ?>>Registered Today</option>
                    <option value="WEEK" <?= $dateFilter === 'WEEK' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="MONTH" <?= $dateFilter === 'MONTH' ? 'selected' : '' ?>>Last 30 Days</option>
                </select>
            </div>

            <!-- Filter & Reset Buttons -->
            <div class="col-12 col-md-3 d-flex gap-2 ms-auto">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                    <i class="bi bi-filter"></i> Apply Filter
                </button>
                <a href="/admin/users" class="btn btn-outline-secondary btn-sm px-3" title="Clear All Filters">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table Card -->
<div class="wb-card border rounded-3 bg-white shadow-2xs mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr class="small text-uppercase text-secondary">
                        <th>User Name</th>
                        <th>Mobile</th>
                        <th>Center / Kiosk</th>
                        <th>Role</th>
                        <th>Profile District</th>
                        <th>Profile Block</th>
                        <th>Status</th>
                        <th>Registered Date</th>
                        <th class="text-center">Applications</th>
                        <th class="text-end">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                <span class="fw-semibold">No users or operators found matching the criteria.</span>
                                <div class="mt-2">
                                    <a href="/admin/users" class="btn btn-sm btn-outline-primary">Reset Filters</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <!-- User Name -->
                                <td>
                                    <div class="fw-bold text-dark">
                                        <a href="/admin/users/detail?id=<?= $u['id'] ?>" class="text-decoration-none">
                                            <?= e($u['name']) ?>
                                        </a>
                                    </div>
                                    <div class="small text-muted">
                                        <?php if (!empty($u['email'])): ?>
                                            <span><?= e($u['email']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Mobile -->
                                <td>
                                    <span class="font-monospace small fw-semibold text-dark">
                                        <?= e($u['mobile']) ?>
                                    </span>
                                </td>

                                <!-- Center / Kiosk Name -->
                                <td>
                                    <?php if (!empty($u['center_name'])): ?>
                                        <span class="fw-semibold text-dark small">
                                            <i class="bi bi-shop me-1 text-secondary"></i><?= e($u['center_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Role -->
                                <td>
                                    <?php if ($u['role'] === 'OPERATOR'): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold px-2 py-1">
                                            <i class="bi bi-shield-lock me-1"></i>Operator
                                        </span>
                                    <?php elseif ($u['role'] === 'USER' && (int)($u['operator_access_active'] ?? 0) === 1): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning fw-bold px-2 py-1" title="Dual Role: Retailer with active Operator permissions">
                                            <i class="bi bi-person-gear me-1"></i>Retailer + Op
                                        </span>
                                    <?php elseif ($u['role'] === 'USER' && (int)($u['has_operator_assignment'] ?? 0) > 0): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1" title="Operator permissions suspended">
                                            <i class="bi bi-shop me-1"></i>Retailer <span class="text-danger small">(Op Off)</span>
                                        </span>
                                    <?php elseif ($u['role'] === 'USER'): ?>
                                        <span class="badge bg-light text-secondary border px-2 py-1">
                                            <i class="bi bi-shop me-1"></i>Retailer
                                        </span>
                                    <?php elseif ($u['role'] === 'ADMIN'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold px-2 py-1">Admin</span>
                                    <?php elseif ($u['role'] === 'DISTRIBUTOR'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 fw-bold">Distributor</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border px-2 py-1"><?= e($u['role']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Profile District -->
                                <td>
                                    <?php if (!empty($u['profile_district_name'])): ?>
                                        <span class="fw-semibold text-dark">
                                            <i class="bi bi-geo-alt-fill text-danger me-1 small"></i><?= e($u['profile_district_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Location Not Set</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Profile Block -->
                                <td>
                                    <?php if (!empty($u['profile_block_name'])): ?>
                                        <span class="badge bg-light text-dark border fw-bold"><?= e($u['profile_block_name']) ?></span>
                                        <?php if (!empty($u['profile_district_id']) && !empty($u['block_actual_district_id']) && (int)$u['block_actual_district_id'] !== (int)$u['profile_district_id']): ?>
                                            <span class="badge bg-danger-subtle text-danger border small" title="Block belongs to another district">Cross-Dist Mismatch</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Location Not Set</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="badge <?= $u['status'] === 'ACTIVE' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                        <?= e($u['status']) ?>
                                    </span>
                                </td>

                                <!-- Registered Date -->
                                <td>
                                    <span class="small text-muted font-monospace">
                                        <?= date('d M Y', strtotime($u['created_at'])) ?>
                                    </span>
                                </td>

                                <!-- Applications Count -->
                                <td class="text-center">
                                    <?php if ((int)$u['app_count'] > 0): ?>
                                        <a href="/admin/applications?user_id=<?= (int)$u['id'] ?>" class="badge bg-primary-subtle text-primary border text-decoration-none px-2 py-1 fw-bold">
                                            <?= (int)$u['app_count'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">0</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Manage Actions -->
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Quick Edit Profile Location Button -->
                                        <button type="button" class="btn btn-outline-info btn-sm"
                                                onclick="openEditLocationModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['center_name'] ?? '', ENT_QUOTES) ?>', <?= (int)($u['profile_district_id'] ?? 0) ?>, <?= (int)($u['profile_block_id'] ?? 0) ?>, '<?= htmlspecialchars($u['center_address'] ?? '', ENT_QUOTES) ?>')"
                                                title="Edit User Profile Location">
                                            <i class="bi bi-geo-alt"></i> Location
                                        </button>

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
                                                </button>
                                            </form>

                                            <!-- Manage Multi-Block Assignments -->
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                    onclick="openManageOperatorModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')"
                                                    title="Manage Multi-Block & Code Assignments">
                                                <i class="bi bi-diagram-3"></i>
                                            </button>

                                            <!-- Password Reset Button -->
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="openResetPasswordModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')"
                                                    title="Reset Password">
                                                <i class="bi bi-key-fill"></i>
                                            </button>

                                        <?php elseif ($u['role'] === 'USER'): ?>
                                            <?php if ((int)($u['operator_access_active'] ?? 0) === 1): ?>
                                                <button type="button" class="btn btn-outline-warning btn-sm"
                                                        onclick="openManageOperatorModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')"
                                                        title="Manage Operator Access & Multi-Block Assignments">
                                                    <i class="bi bi-person-gear"></i> Op
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Full Details Button -->
                                        <a href="/admin/users/detail?id=<?= $u['id'] ?>" class="btn btn-outline-primary btn-sm fw-semibold" title="Full User Details">
                                            <i class="bi bi-gear-fill"></i> Detail
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar & Per-Page Controls -->
        <div class="card-footer bg-white border-top py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="small text-muted">
                    Showing <strong><?= $totalRecords > 0 ? ($offset + 1) : 0 ?></strong> to <strong><?= min($totalRecords, $offset + $limit) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> users
                </div>
                <div class="d-inline-flex align-items-center gap-2 small text-muted">
                    <span>Per Page:</span>
                    <select class="form-select form-select-sm d-inline-block w-auto py-1 px-2.5 fw-semibold border-secondary-subtle" style="font-size: 0.82rem;" onchange="changeUsersPerPage(this.value)">
                        <option value="25" <?= ($limit ?? 25) === 25 ? 'selected' : '' ?>>25 records</option>
                        <option value="50" <?= ($limit ?? 25) === 50 ? 'selected' : '' ?>>50 records</option>
                        <option value="100" <?= ($limit ?? 25) === 100 ? 'selected' : '' ?>>100 records</option>
                        <option value="200" <?= ($limit ?? 25) === 200 ? 'selected' : '' ?>>200 records</option>
                        <option value="500" <?= ($limit ?? 25) === 500 ? 'selected' : '' ?>>500 records</option>
                        <option value="ALL" <?= ($limit ?? 25) >= 5000 ? 'selected' : '' ?>>Show All</option>
                    </select>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Users pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="/admin/users?<?= $queryString ?>&page=<?= ($page - 1) ?>">&laquo; Prev</a>
                        </li>
                        <?php
                        $startPage = max(1, $page - 3);
                        $endPage = min($totalPages, $page + 3);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/users?<?= $queryString ?>&page=<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="/admin/users?<?= $queryString ?>&page=<?= ($page + 1) ?>">Next &raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Edit Profile Location (Center Home) -->
<div class="modal fade" id="editLocationModal" tabindex="-1" aria-labelledby="editLocationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header bg-dark text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold" id="editLocationModalLabel">
                    <i class="bi bi-geo-alt-fill text-warning me-2"></i>Update User Profile Location
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/admin/users/update-profile-location">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" id="edit_loc_user_id" value="">
                
                <div class="modal-body p-4">
                    <div class="alert alert-light border small text-muted py-2 px-3 mb-3 rounded-3">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        Sets the registered Center / Kiosk permanent location. This is separate from Operator work assignments.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">User Account</label>
                        <input type="text" id="edit_loc_user_name" class="form-control bg-light" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Center / Kiosk Name</label>
                        <input type="text" name="center_name" id="edit_loc_center_name" class="form-control" placeholder="e.g. Maa Sharda Digital Kendra">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Profile District <span class="text-danger">*</span></label>
                            <select name="profile_district_id" id="edit_loc_district_id" class="form-select" onchange="onEditLocDistrictChange(this.value)" required>
                                <option value="">-- Choose District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Profile Block <span class="text-danger">*</span></label>
                            <select name="profile_block_id" id="edit_loc_block_id" class="form-select" required>
                                <option value="">-- Select District First --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small">Center / Shop Full Address</label>
                        <textarea name="center_address" id="edit_loc_center_address" class="form-control" rows="2" placeholder="Market, Shop No., Landmark, Pincode"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="bi bi-save me-1"></i> Save Profile Location
                    </button>
                </div>
            </form>
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

<!-- Modal: Grant Operator Access -->
<div class="modal fade" id="grantOperatorModal" tabindex="-1" aria-labelledby="grantOperatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header bg-success text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold" id="grantOperatorModalLabel">
                    <i class="bi bi-person-gear me-2"></i>Grant Operator Access (Dual Role)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin/operators/grant-access">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="alert alert-info border small py-2 px-3 mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        The selected Retailer will receive Operator capabilities to process applications in their assigned location, while completely retaining their Retailer account, wallet, and submissions.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select Retailer (USER) Account <span class="text-danger">*</span></label>
                        <select name="user_id" id="grant_user_id" class="form-select" required>
                            <option value="">-- Choose Existing Retailer --</option>
                            <?php foreach ($users as $cand): ?>
                                <?php if ($cand['role'] === 'USER'): ?>
                                    <option value="<?= $cand['id'] ?>">
                                        <?= e($cand['name']) ?> (<?= e($cand['mobile']) ?>) <?= !empty($cand['center_name']) ? ' - ' . e($cand['center_name']) : '' ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assigned District <span class="text-danger">*</span></label>
                            <select name="district_id" id="grant_district_id" class="form-select" onchange="onModalDistrictChange(this.value, 'grant_block_id'); onGrantBlockChange();" required>
                                <option value="">-- Choose District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assigned Block <span class="text-danger">*</span></label>
                            <select name="block_id" id="grant_block_id" class="form-select" onchange="onGrantBlockChange()" required>
                                <option value="">-- Choose Block --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small d-flex justify-content-between align-items-center">
                            <span>Application Codes to Handle (Staff Routing)</span>
                            <span id="grant_codes_badge" class="badge bg-secondary-subtle text-secondary small">Select Block</span>
                        </label>
                        <div id="grant_codes_container" class="p-3 bg-light border rounded-3 d-flex flex-wrap gap-3">
                            <div class="text-muted small py-1"><i class="bi bi-geo-alt me-1"></i>Please select a District and Block above to load active application codes.</div>
                        </div>
                        <div class="form-text small text-muted">Checked codes will route automatically to this staff member when eligible.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Operator Access Status</label>
                        <select name="status" class="form-select">
                            <option value="ACTIVE" selected>ACTIVE (Immediately Enabled)</option>
                            <option value="SUSPENDED">SUSPENDED (Draft / Inactive)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="bi bi-shield-check me-1"></i> Grant Operator Access
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Manage Operator Access (Multi-Block & Routing) -->
<div class="modal fade" id="manageOperatorModal" tabindex="-1" aria-labelledby="manageOperatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header bg-dark text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold" id="manageOperatorModalLabel">
                    <i class="bi bi-person-gear me-2 text-warning"></i>Manage Operator Access & Multi-Block Assignments
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- User Overview Banner -->
                <div class="card border-0 bg-light rounded-3 p-3 mb-4 shadow-sm">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="small text-muted text-uppercase fw-bold">Operator Account</div>
                            <h5 class="fw-bold mb-1 text-dark" id="mng_user_name">-</h5>
                            <div class="text-secondary small">
                                <span id="mng_user_mobile"></span> &bull; <span id="mng_user_role" class="badge bg-secondary-subtle text-secondary"></span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span id="mng_status_badge" class="badge bg-secondary px-3 py-2 fs-6">Loading...</span>

                            <!-- Suspend Operator Access Form -->
                            <form id="mng_suspend_form" method="POST" action="/admin/operators/suspend-access" class="d-inline" onsubmit="return confirm('Suspend operator access for this user? Retailer access remains active.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" id="mng_suspend_uid">
                                <button type="submit" id="mng_suspend_btn" class="btn btn-outline-warning btn-sm fw-semibold" title="Suspend Operator Access">
                                    <i class="bi bi-pause-circle me-1"></i> Suspend Access
                                </button>
                            </form>

                            <!-- Remove Operator Access Form -->
                            <form id="mng_remove_form" method="POST" action="/admin/operators/remove-access" class="d-inline" onsubmit="return confirm('Completely remove operator access for this user? Retailer wallet, applications, and history remain intact.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" id="mng_remove_uid">
                                <button type="submit" class="btn btn-outline-danger btn-sm fw-semibold" title="Remove Operator Access">
                                    <i class="bi bi-trash me-1"></i> Remove Access
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Left Column: Current Block Assignments -->
                    <div class="col-lg-7">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-geo-alt-fill text-primary me-1"></i>Assigned Locations & Codes (<span id="mng_block_count">0</span>)
                            </h6>
                            <span class="badge bg-primary-subtle text-primary">Multi-Block Enabled</span>
                        </div>
                        <p class="text-muted small mb-3">Operator processes applications matching these active District + Block + Application Code combinations.</p>

                        <div id="mng_assignments_list" class="d-flex flex-column gap-2" style="max-height: 420px; overflow-y: auto;">
                            <div class="text-center py-4 text-muted">
                                <span class="spinner-border spinner-border-sm me-1"></span> Loading assignments...
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Add / Update Block Assignment -->
                    <div class="col-lg-5">
                        <div class="border rounded-4 p-3 bg-white shadow-sm">
                            <h6 class="fw-bold mb-2 text-dark">
                                <i class="bi bi-plus-circle text-success me-1"></i>Add / Update Block Assignment
                            </h6>
                            <p class="text-muted small mb-3">Assign another District and Block without overwriting existing assignments.</p>

                            <form id="mng_add_block_form" method="POST" action="/admin/operators/save-block-assignment" onsubmit="handleSaveBlockAssignment(event)">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" id="mng_add_uid">

                                <div class="mb-3">
                                    <label class="form-label fw-bold small">District <span class="text-danger">*</span></label>
                                    <select name="district_id" id="mng_district_id" class="form-select form-select-sm" onchange="onModalDistrictChange(this.value, 'mng_block_id'); onMngBlockChange();" required>
                                        <option value="">-- Choose District --</option>
                                        <?php foreach ($districts as $d): ?>
                                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Block <span class="text-danger">*</span></label>
                                    <select name="block_id" id="mng_block_id" class="form-select form-select-sm" onchange="onMngBlockChange()" required>
                                        <option value="">-- Choose Block --</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold small d-flex justify-content-between align-items-center">
                                        <span>Application Codes</span>
                                        <span id="mng_codes_badge" class="badge bg-secondary-subtle text-secondary small">Select Block</span>
                                    </label>
                                    <div id="mng_codes_container" class="p-2.5 bg-light border rounded-3 d-flex flex-wrap gap-2" style="min-height: 50px;">
                                        <div class="text-muted small py-1"><i class="bi bi-geo-alt me-1"></i>Select District & Block above.</div>
                                    </div>
                                    <div class="form-text small text-muted">Selected codes route to this operator for this block.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Assignment Status</label>
                                    <select name="status" id="mng_block_status" class="form-select form-select-sm">
                                        <option value="ACTIVE" selected>ACTIVE</option>
                                        <option value="SUSPENDED">SUSPENDED</option>
                                    </select>
                                </div>

                                <div id="mng_form_alert" class="alert small py-2 px-3 d-none mb-3"></div>

                                <button type="submit" id="mng_save_block_btn" class="btn btn-success btn-sm w-100 fw-bold">
                                    <i class="bi bi-save me-1"></i> Save Block Assignment
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-2.5 rounded-bottom-4">
                <button type="button" class="btn btn-secondary btn-sm fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
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

function openGrantModalForUser(userId, userName) {
    openManageOperatorModal(userId, userName);
}

function openEditOperatorModal(userId, userName, currentDistId, currentBlockId, codesStr, status) {
    openManageOperatorModal(userId, userName);
}

let currentMngUserId = 0;

async function openManageOperatorModal(userId, userName) {
    currentMngUserId = userId;
    document.getElementById('mng_add_uid').value = userId;
    document.getElementById('mng_suspend_uid').value = userId;
    document.getElementById('mng_remove_uid').value = userId;
    document.getElementById('mng_user_name').textContent = userName;
    document.getElementById('mng_user_mobile').textContent = 'Loading...';
    document.getElementById('mng_user_role').textContent = 'USER';
    document.getElementById('mng_status_badge').className = 'badge bg-secondary px-3 py-2 fs-6';
    document.getElementById('mng_status_badge').textContent = 'Loading...';

    const listContainer = document.getElementById('mng_assignments_list');
    listContainer.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Loading assignments...</div>';

    // Reset right form
    document.getElementById('mng_district_id').value = '';
    document.getElementById('mng_block_id').innerHTML = '<option value="">-- Choose Block --</option>';
    document.getElementById('mng_codes_container').innerHTML = '<div class="text-muted small py-1"><i class="bi bi-geo-alt me-1"></i>Select District & Block above.</div>';
    document.getElementById('mng_codes_badge').textContent = 'Select Block';
    document.getElementById('mng_codes_badge').className = 'badge bg-secondary-subtle text-secondary small';
    const formAlert = document.getElementById('mng_form_alert');
    formAlert.className = 'alert small py-2 px-3 d-none mb-3';

    const modalEl = document.getElementById('manageOperatorModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    await loadOperatorAssignments(userId);
}

async function loadOperatorAssignments(userId) {
    try {
        const res = await fetch(`/admin/operators/api-assignments?user_id=${encodeURIComponent(userId)}`);
        const data = await res.json();
        if (!data.success) {
            document.getElementById('mng_assignments_list').innerHTML = `<div class="alert alert-danger small">${data.error || 'Failed to load assignments.'}</div>`;
            return;
        }

        const u = data.user;
        document.getElementById('mng_user_name').textContent = u.name;
        document.getElementById('mng_user_mobile').textContent = u.mobile;
        document.getElementById('mng_user_role').textContent = u.role;

        const badge = document.getElementById('mng_status_badge');
        if (u.operator_access_active) {
            badge.className = 'badge bg-success px-3 py-2 fs-6';
            badge.textContent = 'OPERATOR ACCESS ACTIVE';
        } else {
            badge.className = 'badge bg-warning text-dark px-3 py-2 fs-6';
            badge.textContent = 'OPERATOR ACCESS SUSPENDED';
        }

        renderAssignmentsList(data.assignments);
    } catch (e) {
        document.getElementById('mng_assignments_list').innerHTML = '<div class="alert alert-danger small">Network error while fetching assignments.</div>';
    }
}

function renderAssignmentsList(assignments) {
    const container = document.getElementById('mng_assignments_list');
    document.getElementById('mng_block_count').textContent = assignments.length;

    if (!assignments || assignments.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 bg-light rounded-3 border text-muted">
                <i class="bi bi-geo-alt fs-3 d-block text-secondary mb-1"></i>
                <div>No District/Block assigned yet.</div>
                <div class="small">Use the form on the right to assign a block to this operator.</div>
            </div>
        `;
        return;
    }

    let html = '';
    assignments.forEach(asgn => {
        const isActive = parseInt(asgn.is_active) === 1;
        const codes = asgn.codes || [];
        const codeBadges = codes.length > 0
            ? codes.map(c => `<span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">${c}</span>`).join('')
            : '<span class="text-muted small">All block codes / None</span>';

        html += `
            <div class="border rounded-3 p-3 bg-white shadow-2xs ${isActive ? '' : 'opacity-75'}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="fw-bold text-dark fs-6">${asgn.district_name} &bull; ${asgn.block_name}</span>
                        <div class="mt-1">
                            ${codeBadges}
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1.5">
                        <button type="button" class="btn btn-sm ${isActive ? 'btn-outline-warning' : 'btn-outline-success'} py-0.5 px-2"
                                onclick="toggleBlockAssignment(${currentMngUserId}, ${asgn.district_id}, ${asgn.block_id}, ${isActive ? 0 : 1})"
                                title="${isActive ? 'Deactivate Block Assignment' : 'Activate Block Assignment'}">
                            <i class="bi ${isActive ? 'bi-pause-fill' : 'bi-play-fill'}"></i> ${isActive ? 'Active' : 'Off'}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0.5 px-2"
                                onclick="removeBlockAssignment(${currentMngUserId}, ${asgn.district_id}, ${asgn.block_id}, '${asgn.block_name}')"
                                title="Remove this Block Assignment">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

async function toggleBlockAssignment(userId, distId, blkId, newStatus) {
    const token = document.querySelector('input[name="csrf_token"]').value;
    try {
        const form = new FormData();
        form.append('csrf_token', token);
        form.append('user_id', userId);
        form.append('district_id', distId);
        form.append('block_id', blkId);
        form.append('is_active', newStatus);

        const res = await fetch('/admin/operators/toggle-block-assignment', {
            method: 'POST',
            body: form
        });
        const data = await res.json();
        if (data.success) {
            await loadOperatorAssignments(userId);
        } else {
            alert('Failed to update assignment: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error while toggling assignment.');
    }
}

async function removeBlockAssignment(userId, distId, blkId, blkName) {
    if (!confirm(`Remove block '${blkName}' assignment for this operator? Other assigned blocks will remain active.`)) {
        return;
    }
    const token = document.querySelector('input[name="csrf_token"]').value;
    try {
        const form = new FormData();
        form.append('csrf_token', token);
        form.append('user_id', userId);
        form.append('district_id', distId);
        form.append('block_id', blkId);

        const res = await fetch('/admin/operators/remove-block-assignment', {
            method: 'POST',
            body: form
        });
        const data = await res.json();
        if (data.success) {
            await loadOperatorAssignments(userId);
        } else {
            alert('Failed to remove block: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error while removing block.');
    }
}

async function onMngBlockChange() {
    const distId = document.getElementById('mng_district_id').value;
    const blkId = document.getElementById('mng_block_id').value;
    const container = document.getElementById('mng_codes_container');
    const badge = document.getElementById('mng_codes_badge');

    if (!distId || !blkId) {
        container.innerHTML = '<div class="text-muted small py-1"><i class="bi bi-geo-alt me-1"></i>Select District & Block above.</div>';
        if (badge) { badge.textContent = 'Select Block'; badge.className = 'badge bg-secondary-subtle text-secondary small'; }
        return;
    }

    container.innerHTML = '<div class="text-muted small py-1"><span class="spinner-border spinner-border-sm me-1"></span>Loading codes...</div>';
    if (badge) { badge.textContent = 'Loading...'; badge.className = 'badge bg-info-subtle text-info small'; }

    try {
        const res = await fetch(`/admin/staff-routing/api-block-data?district_id=${encodeURIComponent(distId)}&block_id=${encodeURIComponent(blkId)}`);
        const data = await res.json();
        const codes = (data.rate_codes && data.rate_codes.length > 0) ? data.rate_codes : (data.active_codes || []);

        if (!codes || codes.length === 0) {
            container.innerHTML = '<div class="text-muted small py-1"><i class="bi bi-info-circle me-1 text-primary"></i>No custom rates configured for this block.</div>';
            if (badge) { badge.textContent = 'No Rates'; badge.className = 'badge bg-warning-subtle text-warning small'; }
            return;
        }

        let html = '';
        codes.forEach(item => {
            const code = (typeof item === 'object' ? item.code : item).toUpperCase();
            const fee = (typeof item === 'object' && item.fee_amount) ? ('₹' + parseFloat(item.fee_amount).toFixed(0)) : '';
            html += `
                <div class="form-check d-flex align-items-center gap-1 mb-1">
                    <input class="form-check-input mt-0" type="checkbox" name="application_codes[]" value="${code}" id="mng_code_${code}" checked>
                    <label class="form-check-label small fw-semibold cursor-pointer" for="mng_code_${code}">
                        ${code} ${fee ? `<span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">${fee}</span>` : ''}
                    </label>
                </div>
            `;
        });
        container.innerHTML = html;
        if (badge) { badge.textContent = `${codes.length} Active Code(s)`; badge.className = 'badge bg-success-subtle text-success small'; }
    } catch (e) {
        container.innerHTML = '<div class="text-danger small py-1"><i class="bi bi-exclamation-triangle me-1"></i>Could not load block codes.</div>';
        if (badge) { badge.textContent = 'Error'; badge.className = 'badge bg-danger-subtle text-danger small'; }
    }
}

async function handleSaveBlockAssignment(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('mng_save_block_btn');
    const alertBox = document.getElementById('mng_form_alert');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    alertBox.className = 'alert small py-2 px-3 d-none mb-3';

    try {
        const formData = new FormData(form);
        const res = await fetch('/admin/operators/save-block-assignment', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (data.success) {
            alertBox.className = 'alert alert-success small py-2 px-3 mb-3';
            alertBox.textContent = data.message;
            alertBox.classList.remove('d-none');
            await loadOperatorAssignments(currentMngUserId);
        } else {
            alertBox.className = 'alert alert-danger small py-2 px-3 mb-3';
            alertBox.textContent = data.error || 'Failed to save assignment.';
            alertBox.classList.remove('d-none');
        }
    } catch (err) {
        alertBox.className = 'alert alert-danger small py-2 px-3 mb-3';
        alertBox.textContent = 'Network error while saving assignment.';
        alertBox.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i> Save Block Assignment';
    }
}

// Directory Cascading Dropdown
function onFilterDistrictChange(distId) {
    const blkSelect = document.getElementById('filter_block_id');
    if (!blkSelect) return;
    blkSelect.innerHTML = '<option value="">-- All Blocks --</option>';
    if (!distId || !window.allBlocksGrouped || !window.allBlocksGrouped[distId]) {
        blkSelect.disabled = true;
        return;
    }
    const blocks = window.allBlocksGrouped[distId];
    blocks.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = b.name + (b.name_hi ? ' (' + b.name_hi + ')' : '');
        blkSelect.appendChild(opt);
    });
    blkSelect.disabled = false;
}

// Edit Location Modal Helper
function openEditLocationModal(userId, userName, centerName, distId, blockId, address) {
    document.getElementById('edit_loc_user_id').value = userId;
    document.getElementById('edit_loc_user_name').value = userName;
    document.getElementById('edit_loc_center_name').value = centerName || '';
    document.getElementById('edit_loc_center_address').value = address || '';

    const distSelect = document.getElementById('edit_loc_district_id');
    distSelect.value = distId || '';

    onEditLocDistrictChange(distId, blockId);

    const modal = new bootstrap.Modal(document.getElementById('editLocationModal'));
    modal.show();
}

function onEditLocDistrictChange(distId, selectedBlockId = 0) {
    const blkSelect = document.getElementById('edit_loc_block_id');
    if (!blkSelect) return;
    blkSelect.innerHTML = '<option value="">-- Select Block --</option>';
    if (!distId || !window.allBlocksGrouped || !window.allBlocksGrouped[distId]) {
        blkSelect.disabled = true;
        return;
    }
    const blocks = window.allBlocksGrouped[distId];
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

</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>

<script>
function changeUsersPerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('limit', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>
