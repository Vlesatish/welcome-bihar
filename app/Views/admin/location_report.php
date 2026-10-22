<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Breadcrumb / Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <a href="/admin/users" class="btn btn-sm btn-outline-secondary mb-2 rounded-pill px-3 shadow-2xs">
            <i class="bi bi-arrow-left me-1"></i> Back to Users Directory
        </a>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <i class="bi bi-geo-alt-fill text-warning"></i>
            <span>District & Block Location Directory Report</span>
        </h1>
        <p class="text-muted mb-0">Geographic breakdown of registered users, active kiosks, and citizen service application volume across Bihar</p>
    </div>
    <div class="text-end d-flex align-items-center gap-2">
        <a href="/admin/users/location-report/export-csv?<?= e($queryString) ?>" class="btn btn-outline-success fw-bold shadow-sm bg-white">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Report CSV
        </a>
        <a href="/admin/users" class="btn btn-primary fw-bold shadow-sm">
            <i class="bi bi-people-fill me-1"></i> Users Directory
        </a>
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
                <div class="small text-muted">Portal Accounts</div>
            </div>
        </a>
    </div>

    <!-- Location Configured -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?location_status=SET" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-success small fw-semibold">Location Configured</span>
                    <span class="badge bg-success-subtle text-success rounded-circle p-1"><i class="bi bi-check2-circle fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-success"><?= number_format($statLocationSet) ?></div>
                <div class="small text-muted">Dist & Block Set</div>
            </div>
        </a>
    </div>

    <!-- Location Missing -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?location_status=NOT_SET" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition <?= $statLocationNotSet > 0 ? 'border-warning' : '' ?>">
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
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-primary small fw-semibold">Active Users</span>
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-1"><i class="bi bi-shield-check fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-primary"><?= number_format($statActive) ?></div>
                <div class="small text-muted">Active Kiosks</div>
            </div>
        </a>
    </div>

    <!-- Inactive Users -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?status=INACTIVE" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-danger small fw-semibold">Inactive Users</span>
                    <span class="badge bg-danger-subtle text-danger rounded-circle p-1"><i class="bi bi-slash-circle fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-danger"><?= number_format($statInactive) ?></div>
                <div class="small text-muted">Suspended / Pending</div>
            </div>
        </a>
    </div>

    <!-- Invalid Cross-District Pairings -->
    <div class="col-6 col-md-2">
        <a href="/admin/users?location_status=INVALID" class="text-decoration-none">
            <div class="wb-card h-100 p-3 border rounded-3 bg-white shadow-2xs hover-shadow transition <?= $statInvalid > 0 ? 'border-danger' : '' ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-danger small fw-semibold">Invalid Pairings</span>
                    <span class="badge bg-danger-subtle text-danger rounded-circle p-1"><i class="bi bi-shuffle fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold <?= $statInvalid > 0 ? 'text-danger' : 'text-secondary' ?>"><?= number_format($statInvalid) ?></div>
                <div class="small text-muted">Cross-Dist Mismatch</div>
            </div>
        </a>
    </div>
</div>

<!-- Filter Section -->
<div class="wb-card mb-4 border rounded-3 bg-white shadow-2xs">
    <div class="card-body p-3">
        <form method="GET" action="/admin/users/location-report" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search District or Block Name..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="district_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Districts of Bihar (38) --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)$selectedDistrictId === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?> (<?= e($d['name_hi'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                    <i class="bi bi-filter"></i> Filter Report
                </button>
                <a href="/admin/users/location-report" class="btn btn-outline-secondary btn-sm px-3" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- District & Block Breakdown Table -->
<div class="wb-card border rounded-3 bg-white shadow-2xs mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-table text-primary"></i>
            <span class="fw-bold text-dark">District & Block Directory Breakdown</span>
        </div>
        <span class="badge bg-light text-secondary border">
            Showing <?= count($reportRows) ?> of <?= number_format($totalRecords) ?> Blocks
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr class="small text-uppercase text-secondary">
                        <th>District</th>
                        <th>Block</th>
                        <th class="text-center">Registered Users</th>
                        <th class="text-center">Active Users</th>
                        <th class="text-center">Total Applications</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportRows)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-geo fs-2 d-block mb-2 text-secondary"></i>
                                No location rows matching the criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportRows as $row): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark">
                                        <i class="bi bi-geo-alt text-danger me-1"></i><?= e($row['district_name']) ?>
                                    </span>
                                    <?php if (!empty($row['district_name_hi'])): ?>
                                        <span class="text-muted small ms-1">(<?= e($row['district_name_hi']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">
                                        <?= e($row['block_name']) ?>
                                    </span>
                                    <?php if (!empty($row['block_name_hi'])): ?>
                                        <span class="text-muted small ms-1">(<?= e($row['block_name_hi']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$row['registered_users'] > 0): ?>
                                        <a href="/admin/users?district_id=<?= (int)$row['district_id'] ?>&block_id=<?= (int)$row['block_id'] ?>" 
                                           class="badge bg-primary text-white text-decoration-none px-2.5 py-1.5 fs-6 shadow-2xs hover-scale" 
                                           title="Click to view all registered users in <?= e($row['district_name']) ?> > <?= e($row['block_name']) ?>">
                                            <?= number_format((int)$row['registered_users']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$row['active_users'] > 0): ?>
                                        <a href="/admin/users?district_id=<?= (int)$row['district_id'] ?>&block_id=<?= (int)$row['block_id'] ?>&status=ACTIVE" 
                                           class="badge bg-success-subtle text-success border border-success text-decoration-none px-2 py-1 fw-bold"
                                           title="Click to view active users in <?= e($row['district_name']) ?> > <?= e($row['block_name']) ?>">
                                            <?= number_format((int)$row['active_users']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$row['total_applications'] > 0): ?>
                                        <a href="/admin/applications?district_id=<?= (int)$row['district_id'] ?>&block_id=<?= (int)$row['block_id'] ?>" 
                                           class="badge bg-info-subtle text-info border border-info text-decoration-none px-2 py-1 fw-bold"
                                           title="Click to view applications submitted for <?= e($row['district_name']) ?> > <?= e($row['block_name']) ?>">
                                            <?= number_format((int)$row['total_applications']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/users?district_id=<?= (int)$row['district_id'] ?>&block_id=<?= (int)$row['block_id'] ?>" 
                                       class="btn btn-sm btn-outline-primary fw-semibold rounded-pill px-3">
                                        <i class="bi bi-people me-1"></i> View Users
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="small text-muted">
                    Showing Block <strong><?= ($offset + 1) ?></strong> to <strong><?= min($totalRecords, $offset + $limit) ?></strong> of <strong><?= number_format($totalRecords) ?></strong>
                </div>
                <nav aria-label="Location report pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="/admin/users/location-report?<?= $queryString ?>&page=<?= ($page - 1) ?>">&laquo; Prev</a>
                        </li>
                        <?php
                        $startPage = max(1, $page - 3);
                        $endPage = min($totalPages, $page + 3);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/users/location-report?<?= $queryString ?>&page=<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="/admin/users/location-report?<?= $queryString ?>&page=<?= ($page + 1) ?>">Next &raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Existing Users Audit: Location Missing Section -->
<?php if (!empty($unassignedUsers)): ?>
    <div class="wb-card border rounded-3 bg-white shadow-2xs mb-4">
        <div class="card-header bg-warning-subtle py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                <div>
                    <span class="fw-bold text-dark">Existing Users Requiring Location Setup</span>
                    <div class="small text-muted">Users registered without District/Block or with incomplete profile location. Admin can update them manually.</div>
                </div>
            </div>
            <a href="/admin/users?location_status=NOT_SET" class="btn btn-sm btn-warning fw-bold text-dark shadow-2xs">
                View All in Directory (<?= count($unassignedUsers) ?>)
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-secondary">
                            <th>User Name</th>
                            <th>Mobile</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Current Profile Location</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($unassignedUsers, 0, 10) as $uu): ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <a href="/admin/users/detail?id=<?= $uu['id'] ?>" class="text-decoration-none">
                                        <?= e($uu['name']) ?>
                                    </a>
                                </td>
                                <td class="font-monospace small"><?= e($uu['mobile']) ?></td>
                                <td><span class="badge bg-light text-secondary border"><?= e($uu['role']) ?></span></td>
                                <td>
                                    <span class="badge <?= $uu['status'] === 'ACTIVE' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                        <?= e($uu['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($uu['profile_district_name'])): ?>
                                        <span class="text-dark small fw-semibold"><?= e($uu['profile_district_name']) ?> &bull; <span class="text-danger">Block Missing</span></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Location Not Set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/users/detail?id=<?= $uu['id'] ?>" class="btn btn-sm btn-outline-primary fw-semibold rounded-pill px-3">
                                        <i class="bi bi-pencil-square me-1"></i> Set Location
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Existing Users Audit: Invalid District/Block Pairings -->
<?php if (!empty($invalidUsers)): ?>
    <div class="wb-card border rounded-3 bg-white shadow-2xs mb-4">
        <div class="card-header bg-danger-subtle py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-exclamation text-danger fs-5"></i>
                <div>
                    <span class="fw-bold text-dark">Invalid District & Block Combinations Detected</span>
                    <div class="small text-danger">Users whose registered Block does not belong to their registered District.</div>
                </div>
            </div>
            <span class="badge bg-danger text-white fs-6"><?= count($invalidUsers) ?> Issue(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-secondary">
                            <th>User Name</th>
                            <th>Mobile</th>
                            <th>Selected District</th>
                            <th>Selected Block</th>
                            <th>Actual District of Block</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invalidUsers as $iu): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($iu['name']) ?></td>
                                <td class="font-monospace small"><?= e($iu['mobile']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($iu['selected_district_name']) ?></span></td>
                                <td><span class="badge bg-danger-subtle text-danger border"><?= e($iu['selected_block_name']) ?></span></td>
                                <td><span class="badge bg-success-subtle text-success border"><?= e($iu['actual_block_district_name']) ?></span></td>
                                <td class="text-end">
                                    <a href="/admin/users/detail?id=<?= $iu['id'] ?>" class="btn btn-sm btn-outline-danger fw-semibold rounded-pill px-3">
                                        <i class="bi bi-tools me-1"></i> Fix Location
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
