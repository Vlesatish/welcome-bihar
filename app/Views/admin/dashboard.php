<?php require dirname(__DIR__) . '/layouts/header.php'; ?>
<?php
    $currentAdminId = (int)$currentUser['id'];
    $cntPending = (int)($reqStats['pending'] ?? 0);
    $cntInProcess = (int)($reqStats['in_process'] ?? 0);
    $cntApproved = (int)($reqStats['approved'] ?? $reqStats['completed'] ?? 0);
    $cntRejected = (int)($reqStats['rejected'] ?? 0);
    $cntAll = (int)($reqStats['total'] ?? 0);

    $selectedDistrictName = '';
    if (!empty($districtId)) {
        foreach ($districts as $d) {
            if ((int)$d['id'] === (int)$districtId) {
                $selectedDistrictName = $d['name'];
                break;
            }
        }
    }
    $selectedBlockName = '';
    if (!empty($blockId)) {
        foreach ($availableBlocks as $b) {
            if ((int)$b['id'] === (int)$blockId) {
                $selectedBlockName = $b['name'];
                break;
            }
        }
    }
    $hasActiveFilters = ($currentTab !== 'ALL' && !empty($currentTab)) || !empty($districtId) || !empty($blockId) || !empty($appCode) || ($dateFilter !== 'ALL' && !empty($dateFilter)) || !empty($search);
?>

<!-- TOAST CONTAINER -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1085;"></div>

<?php require __DIR__ . '/dashboard_styles.php'; ?>


<!-- 1. Header: Clean Dashboard Heading & Actions -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h1 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-speedometer2 text-primary"></i>
                <span><?= ($currentUser['role'] === 'OPERATOR') ? 'Operator Dashboard' : 'Admin Dashboard' ?></span>
            </h1>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1" style="font-size: 0.7rem;">
                <span class="pulse-dot" style="background-color: #10b981;"></span> Live
            </span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold rounded-2 px-3 shadow-2xs" onclick="window.location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
        <?php if ($currentUser['role'] === 'ADMIN'): ?>
            <form method="POST" action="/admin/system-update" class="d-inline" onsubmit="return confirm('Do you want to run 1-Click System & Database Update? This will synchronize all database tables, columns and migrations.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-dark fw-bold rounded-2 px-3 shadow-2xs" title="Synchronize database schema, columns, and system updates in 1 click">
                    <i class="bi bi-cloud-arrow-down-fill text-warning me-1"></i> 1-Click Update
                </button>
            </form>
            <a href="/admin/service-availability" class="btn btn-sm btn-outline-success fw-bold rounded-2 px-3 shadow-2xs">
                <i class="bi bi-toggle-on me-1"></i> Service Availability
            </a>
            <a href="/admin/block-rates" class="btn btn-sm btn-warning fw-bold rounded-2 px-3 text-dark shadow-2xs" style="background-color: #f59e0b; border-color: #d97706;">
                <i class="bi bi-currency-rupee me-1"></i> Block Rates
            </a>
            <a href="/admin/data-types" class="btn btn-sm btn-primary fw-bold rounded-2 px-3 shadow-2xs">
                <i class="bi bi-sliders me-1"></i> Services
            </a>
        <?php endif; ?>
    </div>
</div>


<?php if ($currentUser['role'] === 'ADMIN' && !empty($routingDashboardStats)): ?>
<!-- Compact Staff Routing KPI Indicator Row -->
<div class="card shadow-2xs border bg-white rounded-3 p-2.5 mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-diagram-3-fill text-primary fs-5"></i>
            <span class="small fw-bold text-dark">Staff Routing Status:</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="/admin/applications?assignment_status=ASSIGNED" class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 text-decoration-none d-flex align-items-center gap-1.5">
                <i class="bi bi-person-check-fill"></i>
                <span>Assigned Applications:</span>
                <strong class="font-monospace"><?= number_format($routingDashboardStats['assigned']) ?></strong>
            </a>
            <a href="/admin/applications?assignment_status=UNASSIGNED" class="badge <?= ($routingDashboardStats['unassigned'] > 0) ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-light text-secondary border' ?> px-2.5 py-1.5 text-decoration-none d-flex align-items-center gap-1.5">
                <i class="bi bi-person-x-fill"></i>
                <span>Unassigned Applications:</span>
                <strong class="font-monospace"><?= number_format($routingDashboardStats['unassigned']) ?></strong>
            </a>
            <a href="/admin/staff-routing" class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1.5 text-decoration-none d-flex align-items-center gap-1.5">
                <i class="bi bi-shuffle"></i>
                <span>Active Routing Rules:</span>
                <strong class="font-monospace"><?= number_format($routingDashboardStats['active_rules']) ?></strong>
            </a>
            <a href="/admin/staff-routing" class="badge <?= ($routingDashboardStats['codes_without_staff'] > 0) ? 'bg-warning-subtle text-dark border border-warning' : 'bg-light text-secondary border' ?> px-2.5 py-1.5 text-decoration-none d-flex align-items-center gap-1.5">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                <span>Codes Without Staff:</span>
                <strong class="font-monospace"><?= number_format($routingDashboardStats['codes_without_staff']) ?></strong>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($currentUser['role'] === 'ADMIN' && !empty($serviceAvailabilityStats)): ?>
<!-- Compact Service Availability Indicator Row -->
<div class="card shadow-2xs border bg-white rounded-3 p-2.5 mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-toggle-on text-success fs-5"></i>
            <span class="small fw-bold text-dark">Service Availability (srv_block_app):</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="/admin/service-availability?status=LIVE" class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 text-decoration-none d-flex align-items-center gap-1.5">
                <i class="bi bi-broadcast"></i>
                <span>Live Service Blocks:</span>
                <strong class="font-monospace"><?= number_format($serviceAvailabilityStats['live_blocks']) ?></strong>
            </a>
            <a href="/admin/service-availability?status=INACTIVE" class="badge bg-secondary-subtle text-secondary border px-2.5 py-1.5 text-decoration-none d-flex align-items-center gap-1.5">
                <i class="bi bi-dash-circle"></i>
                <span>Inactive Service Blocks:</span>
                <strong class="font-monospace"><?= number_format($serviceAvailabilityStats['inactive_blocks']) ?></strong>
            </a>
            <a href="/admin/service-availability?status=LIVE&operator_filter=MISSING_OPERATOR" class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1.5 text-decoration-none d-flex align-items-center gap-1.5">
                <i class="bi bi-person-exclamation"></i>
                <span>Live Blocks Missing Operator:</span>
                <strong class="font-monospace"><?= number_format($serviceAvailabilityStats['live_missing_operator']) ?></strong>
            </a>
            <a href="/admin/service-availability?status=LIVE&rate_filter=MISSING_RATE" class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1.5 text-decoration-none d-flex align-items-center gap-1.5">
                <i class="bi bi-tag"></i>
                <span>Live Blocks Missing Rate:</span>
                <strong class="font-monospace"><?= number_format($serviceAvailabilityStats['live_missing_rate']) ?></strong>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 2. Modern Executive Clickable Status Cards (Click filters the table) -->
<div class="row g-3 mb-3" id="statusCardsRow">

    <!-- Pending -->
    <div class="col-6 col-md-3">
        <div class="dashboard-stat-card card-pending <?= $currentTab === 'PENDING' ? 'active-card' : '' ?>" onclick="filterByStatus('PENDING')" id="card-status-pending" title="Click to filter by Pending">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-card-label" style="color: #b45309;">Pending</span>
                    <div class="stat-card-count" style="color: #b45309;" id="stat-pending"><?= number_format($cntPending) ?></div>
                    <div class="stat-card-sub" style="color: #b45309;">
                        <span class="pulse-dot"></span> Awaiting Action
                    </div>
                </div>
                <div class="kpi-icon-pill" style="background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a;">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- In Process -->
    <div class="col-6 col-md-3">
        <div class="dashboard-stat-card card-in-process <?= $currentTab === 'IN_PROCESS' ? 'active-card' : '' ?>" onclick="filterByStatus('IN_PROCESS')" id="card-status-in-process" title="Click to filter by In Process">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-card-label" style="color: #0369a1;">In Process</span>
                    <div class="stat-card-count" style="color: #0284c7;" id="stat-in-process"><?= number_format($cntInProcess) ?></div>
                    <div class="stat-card-sub text-info">
                        <i class="bi bi-arrow-repeat spin-icon"></i> Under Verification
                    </div>
                </div>
                <div class="kpi-icon-pill" style="background-color: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Approved -->
    <div class="col-6 col-md-3">
        <div class="dashboard-stat-card card-approved <?= $currentTab === 'APPROVED' ? 'active-card' : '' ?>" onclick="filterByStatus('APPROVED')" id="card-status-approved" title="Click to filter by Approved">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-card-label" style="color: #047857;">Approved</span>
                    <div class="stat-card-count" style="color: #059669;" id="stat-approved"><?= number_format($cntApproved) ?></div>
                    <div class="stat-card-sub text-success">
                        <i class="bi bi-check2-circle"></i> Ready & Issued
                    </div>
                </div>
                <div class="kpi-icon-pill" style="background-color: #d1fae5; color: #059669; border: 1px solid #a7f3d0;">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejected -->
    <div class="col-6 col-md-3">
        <div class="dashboard-stat-card card-rejected <?= $currentTab === 'REJECTED' ? 'active-card' : '' ?>" onclick="filterByStatus('REJECTED')" id="card-status-rejected" title="Click to filter by Rejected">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-card-label" style="color: #b91c1c;">Rejected</span>
                    <div class="stat-card-count" style="color: #dc2626;" id="stat-rejected"><?= number_format($cntRejected) ?></div>
                    <div class="stat-card-sub text-danger">
                        <i class="bi bi-x-circle"></i> Returned / Refunded
                    </div>
                </div>
                <div class="kpi-icon-pill" style="background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca;">
                    <i class="bi bi-x-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. Standalone Filters Card (Between Status Cards and Applications Table) -->
<div class="card shadow-sm border rounded-3 bg-white" id="adminFiltersCard" style="border: 1px solid #e2e8f0; margin-bottom: 18px !important;">
    <div class="card-body p-3">
        <!-- Card Header: Clean small Filters heading + active filter indicators -->
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2 pb-2 border-bottom">
            <div class="d-flex align-items-center flex-wrap gap-1.5">
                <i class="bi bi-funnel-fill text-primary" style="font-size: 0.82rem;"></i>
                <h2 class="h6 fw-bold mb-0 text-dark" style="font-size: 0.85rem; letter-spacing: 0.2px;">Filters</h2>
                <?php if ($currentTab !== 'ALL' && !empty($currentTab)): ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle ms-1" style="font-size: 0.72rem;">
                        Status: <strong><?= e($currentTab) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($selectedDistrictName)): ?>
                    <span class="badge bg-light text-dark border ms-1" style="font-size: 0.72rem;">
                        District: <strong><?= e($selectedDistrictName) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($selectedBlockName)): ?>
                    <span class="badge bg-light text-dark border ms-1" style="font-size: 0.72rem;">
                        Block: <strong><?= e($selectedBlockName) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($appCode)): ?>
                    <span class="badge bg-light text-dark border ms-1" style="font-size: 0.72rem;">
                        Code: <strong><?= e($appCode) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if ($dateFilter !== 'ALL' && !empty($dateFilter)): ?>
                    <span class="badge bg-light text-dark border ms-1" style="font-size: 0.72rem;">
                        Date: <strong><?= e($dateFilter) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <span class="badge bg-light text-dark border ms-1" style="font-size: 0.72rem;">
                        Search: <strong>"<?= e($search) ?>"</strong>
                    </span>
                <?php endif; ?>
            </div>
            <div class="small text-muted" style="font-size: 0.74rem;">
                Showing <strong><?= (int)$totalRecords ?></strong> applications
            </div>
        </div>

        <!-- Filter Controls Form with Short Labels -->
        <form method="GET" action="/admin/dashboard" id="filterForm">
            <input type="hidden" name="status" id="filterStatusInput" value="<?= e($currentTab) ?>">
            <input type="hidden" name="page" id="filterPageInput" value="1">

            <div class="admin-filters-grid pt-1">
                <!-- 1. District -->
                <?php if ($currentUser['role'] === 'OPERATOR'): ?>
                    <div class="filter-col filter-col-district">
                        <label class="filter-label">District</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-secondary filter-ctrl" style="border-radius: 6px 0 0 6px !important;"><i class="bi bi-lock-fill"></i></span>
                            <input type="text" class="form-control bg-light filter-ctrl fw-semibold text-secondary" style="border-radius: 0 6px 6px 0 !important;" value="<?= e($districts[0]['name'] ?? 'Assigned') ?>" readonly disabled title="Locked to assigned district">
                        </div>
                    </div>

                    <!-- 2. Block -->
                    <div class="filter-col filter-col-block">
                        <label class="filter-label">Block</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-secondary filter-ctrl" style="border-radius: 6px 0 0 6px !important;"><i class="bi bi-lock-fill"></i></span>
                            <input type="text" class="form-control bg-light filter-ctrl fw-semibold text-secondary" style="border-radius: 0 6px 6px 0 !important;" value="<?= e($availableBlocks[0]['name'] ?? 'Assigned') ?>" readonly disabled title="Locked to assigned block">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="filter-col filter-col-district">
                        <label for="filterDistrict" class="filter-label">District</label>
                        <select name="district_id" id="filterDistrict" class="form-select form-select-sm filter-select filter-ctrl w-100">
                            <option value="">All Districts</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-col filter-col-block">
                        <label for="filterBlock" class="filter-label">Block</label>
                        <select name="block_id" id="filterBlock" class="form-select form-select-sm filter-select filter-ctrl w-100" <?= empty($districtId) ? 'disabled' : '' ?>>
                            <option value="">All Blocks</option>
                            <?php foreach ($availableBlocks as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $blockId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- 3. Status -->
                <div class="filter-col filter-col-status">
                    <label for="filterStatusSelect" class="filter-label">Status</label>
                    <select name="status" id="filterStatusSelect" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="ALL" <?= ($currentTab === 'ALL' || empty($currentTab)) ? 'selected' : '' ?>>All (<?= $cntAll ?>)</option>
                        <option value="PENDING" <?= $currentTab === 'PENDING' ? 'selected' : '' ?>>Pending (<?= $cntPending ?>)</option>
                        <option value="IN_PROCESS" <?= $currentTab === 'IN_PROCESS' ? 'selected' : '' ?>>In Process (<?= $cntInProcess ?>)</option>
                        <option value="APPROVED" <?= $currentTab === 'APPROVED' ? 'selected' : '' ?>>Approved (<?= $cntApproved ?>)</option>
                        <option value="REJECTED" <?= $currentTab === 'REJECTED' ? 'selected' : '' ?>>Rejected (<?= $cntRejected ?>)</option>
                    </select>
                </div>

                <!-- 4. App Code -->
                <div class="filter-col filter-col-code">
                    <label for="filterCode" class="filter-label">App Code</label>
                    <select name="app_code" id="filterCode" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="">All Codes</option>
                        <?php foreach ($codeOptions as $cCode): ?>
                            <option value="<?= e($cCode) ?>" <?= $appCode === $cCode ? 'selected' : '' ?>><?= e($cCode) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 5. Date -->
                <div class="filter-col filter-col-date">
                    <label for="filterDate" class="filter-label">Date</label>
                    <select name="date_filter" id="filterDate" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="ALL" <?= $dateFilter === 'ALL' ? 'selected' : '' ?>>All Dates</option>
                        <option value="TODAY" <?= $dateFilter === 'TODAY' ? 'selected' : '' ?>>Today</option>
                        <option value="WEEK" <?= $dateFilter === 'WEEK' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="MONTH" <?= $dateFilter === 'MONTH' ? 'selected' : '' ?>>Last 30 Days</option>
                    </select>
                </div>

                <!-- 6. Search Bar -->
                <div class="filter-col filter-col-search">
                    <label for="admin-search-input" class="filter-label">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted filter-ctrl" style="border-radius: 6px 0 0 6px !important;"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" id="admin-search-input" value="<?= e($search) ?>" class="form-control border-start-0 ps-0 bg-white filter-ctrl" style="border-radius: 0 6px 6px 0 !important;" placeholder="Search applications…">
                    </div>
                </div>

                <!-- 7. Reset Button -->
                <div class="filter-col filter-col-btn">
                    <label class="filter-label invisible d-none d-lg-block">&nbsp;</label>
                    <a href="/admin/dashboard" class="btn btn-sm btn-outline-danger filter-btn filter-ctrl w-100 d-inline-flex align-items-center justify-content-center gap-1" title="Reset all filters">
                        <i class="bi bi-arrow-counterclockwise"></i><span>Reset</span>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 4. Applications Table Card -->
<div class="card shadow-sm border rounded-3 mb-5 bg-white" id="adminTableCard" style="border: 1px solid #e2e8f0;">
    <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary"></i>
            <h2 class="h6 fw-bold mb-0 text-dark">Applications Stream</h2>
        </div>
        <span class="badge bg-light text-secondary border font-monospace"><?= number_format($totalRecords) ?> Records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="adminRequestsTable">
            <thead class="table-light text-muted small text-uppercase fw-bold border-bottom" style="font-size: 0.72rem;">
                <tr>
                    <th class="ps-3 py-2.5">Order Reference</th>
                    <th class="py-2.5">Applicant</th>
                    <th class="py-2.5">Application No.</th>
                    <th class="py-2.5">Amount</th>
                    <th class="py-2.5">Status</th>
                    <th class="py-2.5">Date</th>
                    <th class="text-end pe-3 py-2.5" style="min-width: 230px;">Action / 1-Click Update</th>
                </tr>
            </thead>
            <tbody id="adminRequestsTbody">
                <?php if (empty($allRequests)): ?>
                    <tr id="adminNoRequestsRow">
                        <td colspan="7" class="text-center py-5 text-muted small">
                            <i class="bi bi-inbox fs-2 d-block opacity-50 mb-1"></i>
                            No applications found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allRequests as $r): ?>
                        <?php
                            $st = $r['display_status'];
                            $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                            $statusLabel = 'PENDING';
                            if ($st === 'IN_PROCESS' || $st === 'UNDER_REVIEW') {
                                $badgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                                $statusLabel = 'PROCESSING';
                            } elseif ($st === 'APPROVED' || $st === 'COMPLETED') {
                                $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                $statusLabel = 'COMPLETED';
                            } elseif ($st === 'REJECTED') {
                                $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                $statusLabel = 'REJECTED';
                            }

                            $f = $r['formData'] ?? [];
                            $appName = $f['applicant_name'] ?? $r['applicant_name'] ?? 'Applicant';
                            $appNum = $f['application_number'] ?? $r['applicant_identifier'] ?? 'N/A';
                            $reqJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="admin-req-row"
                            id="admin-row-<?= $r['id'] ?>"
                            data-id="<?= $r['id'] ?>"
                            data-status="<?= $st ?>"
                            data-req='<?= $reqJson ?>'>

                            <!-- 1. Order Reference -->
                            <td class="ps-3">
                                <div class="d-inline-flex align-items-center gap-1 px-2 py-0.5 rounded-2 bg-light border font-monospace small fw-bold text-dark">
                                    <span>#<?= e($r['app_ref'] ?: ('WB-' . $r['id'])) ?></span>
                                </div>
                            </td>

                            <!-- 2. Applicant Name + Avatar + Location / Retailer below -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle flex-shrink-0">
                                        <?= strtoupper(mb_substr($appName, 0, 1)) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.86rem; max-width: 220px;" title="<?= e($appName) ?>">
                                            <?= e($appName) ?>
                                        </div>
                                        <div class="text-muted small d-flex align-items-center gap-1 text-truncate" style="font-size: 0.72rem;">
                                            <span class="text-truncate"><i class="bi bi-geo-alt text-primary opacity-75 me-0.5"></i><?= e($r['district_name'] ?: 'District') ?> &gt; <?= e($r['block_name'] ?: 'Block') ?></span>
                                            <?php if (!empty($r['retailer_name'])): ?>
                                                <span class="text-muted">&bull;</span>
                                                <span class="text-secondary text-truncate" title="Retailer: <?= e($r['retailer_name']) ?>">Ret: <?= e($r['retailer_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- 3. Application No. with Copy Button -->
                            <td>
                                <div class="d-flex align-items-center gap-1.5 font-monospace small fw-semibold text-dark">
                                    <span class="user-select-all"><?= e($appNum !== 'N/A' ? $appNum : '—') ?></span>
                                    <?php if ($appNum !== 'N/A'): ?>
                                        <button type="button" class="btn btn-link p-0 text-muted" title="Copy Number" onclick="copyToClipboard('<?= e($appNum) ?>', this)">
                                            <i class="bi bi-copy" style="font-size: 0.75rem;"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- 4. Fee / Amount -->
                            <td class="font-monospace">
                                <span class="fw-bold text-dark" style="font-size: 0.88rem;">₹<?= number_format($r['fee_debited'], 2) ?></span>
                            </td>

                            <!-- 5. Status Badge -->
                            <td id="row-status-col-<?= $r['id'] ?>">
                                <span class="badge status-pill <?= $badgeClass ?> px-2.5 py-1 fw-bold">
                                    <?php if ($st === 'APPROVED' || $st === 'COMPLETED'): ?>
                                        <i class="bi bi-check2-circle me-1"></i>
                                    <?php elseif ($st === 'PENDING'): ?>
                                        <span class="pulse-dot me-1"></span>
                                    <?php elseif ($st === 'IN_PROCESS' || $st === 'UNDER_REVIEW'): ?>
                                        <i class="bi bi-arrow-repeat spin-icon me-1"></i>
                                    <?php elseif ($st === 'REJECTED'): ?>
                                        <i class="bi bi-x-circle me-1"></i>
                                    <?php endif; ?>
                                    <?= $statusLabel ?>
                                </span>
                                <?php if (!empty($r['is_refunded'])): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block mt-1 font-monospace" style="font-size: 0.62rem; width: fit-content;">
                                        Refunded
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- 6. Date -->
                            <td class="small text-muted" style="font-size: 0.75rem; white-space: nowrap;">
                                <div class="text-dark fw-semibold"><?= date('d M Y', strtotime($r['created_at'])) ?></div>
                                <div class="text-muted opacity-75" style="font-size: 0.7rem;"><?= date('h:i A', strtotime($r['created_at'])) ?></div>
                            </td>

                            <!-- 7. Actions: 1-Click Update, Upload, Details, Chat -->
                            <td class="text-end pe-3" id="row-action-col-<?= $r['id'] ?>">
                                <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                                    <?php if ($st === 'PENDING'): ?>
                                        <button type="button" class="btn btn-xs btn-primary fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(<?= $r['id'] ?>, 'IN_PROCESS', this)" title="1-Click: Mark In Process">
                                            <i class="bi bi-lightning-charge-fill me-0.5"></i>Process
                                        </button>
                                        <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(<?= $r['id'] ?>, 'APPROVED', this)" title="1-Click: Approve">
                                            <i class="bi bi-check-circle-fill me-0.5"></i>Approve
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-1.5 py-1" onclick="openRejectModal(<?= $r['id'] ?>)" title="Reject Application">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    <?php elseif ($st === 'IN_PROCESS'): ?>
                                        <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2.5 py-1 shadow-2xs" onclick="quickUpdateStatus(<?= $r['id'] ?>, 'APPROVED', this)" title="1-Click: Approve">
                                            <i class="bi bi-check-circle-fill me-0.5"></i>Approve
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-primary fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadResultModal(<?= $r['id'] ?>)" title="Upload File & Approve">
                                            <i class="bi bi-cloud-arrow-up"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-2 py-1" onclick="openRejectModal(<?= $r['id'] ?>)" title="Reject Application">
                                            Reject
                                        </button>
                                    <?php elseif ($st === 'APPROVED' || $st === 'COMPLETED'): ?>
                                        <?php if (!empty($r['result_file'])): ?>
                                            <button type="button" class="btn btn-xs btn-outline-success fw-bold rounded-2 px-2 py-1" onclick="viewResultFile('<?= e($r['result_file']) ?>', '<?= e($r['app_ref'] ?: ('WB-' . $r['id'])) ?>')" title="View Result Document">
                                                <i class="bi bi-file-earmark-check me-0.5"></i>Result
                                            </button>
                                            <a href="<?= e($r['result_file']) ?>" target="_blank" download class="btn btn-xs btn-success fw-semibold rounded-2 px-1.5 py-1" title="Download Result">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-xs btn-light border fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadResultModal(<?= $r['id'] ?>)" title="Upload or Replace Certificate">
                                            <i class="bi bi-cloud-arrow-up text-primary"></i>
                                        </button>
                                    <?php elseif ($st === 'REJECTED'): ?>
                                        <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-2 py-1" onclick="viewRejectionReason(<?= $r['id'] ?>)" title="View Rejection Reason">
                                            Reason
                                        </button>
                                    <?php endif; ?>

                                    <!-- Details Button (Matching Operator View) -->
                                    <button type="button" class="btn btn-xs btn-outline-warning fw-semibold rounded-2 px-2.5 py-1 text-decoration-none btn-more-details" onclick="openDetailsModal(<?= $r['id'] ?>)">
                                        More Details
                                    </button>

                                    <!-- Chat Button -->
                                    <button type="button" class="btn btn-xs btn-light border py-1 px-1.5 rounded-2 text-primary btn-chat-icon" title="Retailer Chat" onclick="adminOpenChat(<?= $r['id'] ?>)">
                                        <i class="bi bi-chat-dots-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 5. Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-light p-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-muted" style="font-size: 0.75rem;">
                Showing <?= min($totalRecords, $offset + 1) ?> to <?= min($totalRecords, $offset + count($allRequests)) ?> of <?= $totalRecords ?> applications
            </div>
            <ul class="pagination pagination-sm mb-0">
                <?php $queryBase = $_GET; ?>
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => $page - 1])) ?>">&laquo;</a>
                    </li>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => $page + 1])) ?>">&raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- =========================================================================
     MODAL 1: APPLICATION COMPLETE DETAILS (All shifted info is accessible here)
     ========================================================================= -->

<?php require __DIR__ . '/dashboard_modals.php'; ?>
<?php require __DIR__ . '/dashboard_scripts.php'; ?>
