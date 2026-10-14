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

<style>
/* Executive KPI Cards with Ambient Glow */
.dashboard-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    user-select: none;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.04), 0 1px 3px -1px rgba(15, 23, 42, 0.02);
}
.dashboard-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.08), 0 4px 8px -2px rgba(15, 23, 42, 0.04);
}
.dashboard-stat-card::after {
    content: '';
    position: absolute;
    top: -20px;
    right: -20px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    filter: blur(28px);
    opacity: 0.22;
    pointer-events: none;
}
.dashboard-stat-card.card-pending {
    background: linear-gradient(135deg, #ffffff 0%, #fffdf8 100%);
    border-left: 4px solid #f59e0b;
}
.dashboard-stat-card.card-pending::after {
    background: #f59e0b;
}
.dashboard-stat-card.card-in-process {
    background: linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
    border-left: 4px solid #0284c7;
}
.dashboard-stat-card.card-in-process::after {
    background: #0284c7;
}
.dashboard-stat-card.card-approved {
    background: linear-gradient(135deg, #ffffff 0%, #f6fdf9 100%);
    border-left: 4px solid #10b981;
}
.dashboard-stat-card.card-approved::after {
    background: #10b981;
}
.dashboard-stat-card.card-rejected {
    background: linear-gradient(135deg, #ffffff 0%, #fff8f8 100%);
    border-left: 4px solid #ef4444;
}
.dashboard-stat-card.card-rejected::after {
    background: #ef4444;
}

.dashboard-stat-card.active-card {
    box-shadow: 0 0 0 2px #3b82f6, 0 12px 28px rgba(59, 130, 246, 0.18) !important;
}
.dashboard-stat-card.card-pending.active-card {
    box-shadow: 0 0 0 2px #f59e0b, 0 12px 28px rgba(245, 158, 11, 0.2) !important;
}
.dashboard-stat-card.card-in-process.active-card {
    box-shadow: 0 0 0 2px #0284c7, 0 12px 28px rgba(2, 132, 199, 0.2) !important;
}
.dashboard-stat-card.card-approved.active-card {
    box-shadow: 0 0 0 2px #10b981, 0 12px 28px rgba(16, 185, 129, 0.2) !important;
}
.dashboard-stat-card.card-rejected.active-card {
    box-shadow: 0 0 0 2px #ef4444, 0 12px 28px rgba(239, 68, 68, 0.2) !important;
}

.kpi-icon-pill {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
.stat-card-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.stat-card-count {
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 0.2rem;
    letter-spacing: -0.02em;
}
.stat-card-sub {
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748b;
    margin-top: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

/* Pulsing dot */
.pulse-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background-color: #f59e0b;
    box-shadow: 0 0 0 rgba(245, 158, 11, 0.5);
    animation: pulse-dot-anim 1.6s infinite;
}
@keyframes pulse-dot-anim {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

/* Spin Icon */
.spin-icon {
    display: inline-block;
    animation: spin-anim 2s linear infinite;
}
@keyframes spin-anim {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Status Badges & Pills */
.status-pill {
    font-size: 0.72rem;
    letter-spacing: 0.3px;
    border-radius: 20px;
    padding: 0.28rem 0.65rem !important;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}
.badge-status-pending { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.badge-status-in-process { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.badge-status-approved { background-color: #d1fae5; color: #047857; border: 1px solid #a7f3d0; }
.badge-status-rejected { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

/* Applicant Avatar */
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #4338ca;
    font-weight: 700;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.btn-xs {
    padding: 0.22rem 0.55rem;
    font-size: 0.72rem;
    line-height: 1.25;
}
.font-monospace {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace !important;
}
.nowrap-cell {
    white-space: nowrap !important;
}

/* More Details Button */
.btn-more-details {
    font-size: 0.72rem !important;
    color: #b45309 !important;
    border-color: #fde68a !important;
    background: #fffdf7 !important;
    transition: all 0.15s ease-in-out;
}
.btn-more-details:hover {
    background: #fef3c7 !important;
    color: #92400e !important;
    border-color: #f59e0b !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);
}

/* Chat icon button */
.btn-chat-icon:hover {
    background: #eff6ff !important;
    border-color: #93c5fd !important;
    color: #2563eb !important;
    transform: translateY(-1px);
}

.upload-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    background-color: #f8fafc;
    transition: all 0.2s ease;
    cursor: pointer;
}
.upload-dropzone:hover {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

/* Standalone Filters Card & Controls */
.filter-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    margin-bottom: 0.25rem;
    white-space: nowrap;
}
.admin-filters-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.65rem;
    width: 100%;
}
.filter-col-district {
    flex: 0 0 150px;
    width: 150px;
}
.filter-col-block {
    flex: 0 0 150px;
    width: 150px;
}
.filter-col-status {
    flex: 0 0 145px;
    width: 145px;
}
.filter-col-code {
    flex: 0 0 115px;
    width: 115px;
}
.filter-col-date {
    flex: 0 0 120px;
    width: 120px;
}
.filter-col-search {
    flex: 1 1 180px;
    min-width: 160px;
}
.filter-col-btn {
    flex: 0 0 auto;
}
.filter-ctrl, .filter-select, .filter-btn {
    height: 36px !important;
    font-size: 13.5px !important;
    border-radius: 6px !important;
}
.filter-ctrl:focus, .filter-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}
.filter-select {
    padding: 0.25rem 1.6rem 0.25rem 0.65rem !important;
    background-position: right 0.5rem center !important;
    font-weight: 500;
    color: #1e293b;
    border: 1px solid #cbd5e1;
    background-color: #ffffff;
}
.filter-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.85rem !important;
    font-weight: 600;
    white-space: nowrap;
}

@media (min-width: 1200px) {
    .admin-filters-grid {
        flex-wrap: nowrap !important;
    }
}

@media (max-width: 1199.98px) and (min-width: 768px) {
    .filter-col-district { flex: 1 1 140px; }
    .filter-col-block { flex: 1 1 140px; }
    .filter-col-status { flex: 1 1 135px; }
    .filter-col-code { flex: 1 1 115px; }
    .filter-col-date { flex: 1 1 115px; }
    .filter-col-search { flex: 1 1 180px; }
    .filter-col-btn { flex: 0 0 auto; }
}

@media (max-width: 767.98px) {
    .filter-col-district,
    .filter-col-block {
        flex: 1 1 calc(50% - 0.35rem);
        width: auto !important;
        min-width: 120px;
    }
    .filter-col-code,
    .filter-col-date {
        flex: 1 1 calc(50% - 0.35rem);
        width: auto !important;
        min-width: 110px;
    }
    .filter-col-search {
        flex: 1 1 100%;
        min-width: 100%;
    }
    .filter-col-btn {
        flex: 1 1 calc(50% - 0.35rem);
    }
}
</style>

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
<div class="modal fade" id="modalAdminAppDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2.5 px-4 bg-light rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0">
                    <i class="bi bi-card-text text-primary me-2"></i>Application Details #<span id="detailsModalReqNum"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-2 mb-3 d-flex flex-wrap justify-content-between gap-2 small">
                    <div><strong>Service:</strong> <span id="detailsModalService">--</span></div>
                    <div><strong>Fee Charged:</strong> <span id="detailsModalFee" class="fw-bold text-primary">₹0.00</span></div>
                    <div><strong>Status:</strong> <span id="detailsModalStatus" class="badge">--</span></div>
                </div>
                <div class="table-responsive border rounded-2">
                    <table class="table table-sm table-striped mb-0 small">
                        <tbody id="detailsModalTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-4 bg-light rounded-bottom-3">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: APPROVE APPLICATION / UPLOAD RESULT
     ========================================================================= -->
<div class="modal fade" id="modalUploadApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2 px-3 bg-light rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0 text-success d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill" id="approveModalIcon"></i>
                    <span id="approveModalTitle">Approve Application</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="uploadApproveForm" enctype="multipart/form-data">
                <div class="modal-body p-3 space-y-3">
                    <input type="hidden" name="application_id" id="uploadModalAppId" value="">
                    <input type="hidden" name="status" id="uploadModalStatusInput" value="APPROVED">

                    <!-- Compact Application Details -->
                    <div class="p-2.5 bg-light rounded-2 small mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Application Ref:</span>
                            <strong class="font-monospace text-dark" id="uploadModalAppRef">--</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Applicant Name:</span>
                            <strong class="text-dark" id="uploadModalApplicant">--</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Application Number:</span>
                            <span class="font-monospace fw-bold text-dark" id="uploadModalAppNo">--</span>
                        </div>
                    </div>

                    <!-- Small File Picker with Label "Result File (Optional)" and no asterisk -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1 d-flex justify-content-between align-items-center">
                            <span id="uploadFileLabel">Result File (Optional)</span>
                            <span class="text-muted fw-normal" style="font-size: 0.72rem;">PDF, JPG, PNG (Max 15MB)</span>
                        </label>
                        <input type="file" name="result_file" id="uploadResultFileInput" class="form-control form-control-sm rounded-2" accept=".pdf,.jpg,.jpeg,.png">
                    </div>

                    <!-- Live Preview Container -->
                    <div id="uploadPreviewContainer" class="d-none border rounded-2 p-2 bg-light mb-3 text-center">
                        <div class="small text-muted mb-1 fw-semibold d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-file-earmark-check text-success me-1"></i> Preview: <span id="uploadFileNameLabel"></span></span>
                            <button type="button" class="btn btn-xs btn-link text-danger p-0" onclick="clearUploadFile()">Remove File</button>
                        </div>
                        <div id="uploadPreviewBody" style="max-height: 180px; overflow: hidden;"></div>
                    </div>

                    <!-- Optional Remarks -->
                    <div class="mb-1">
                        <label class="form-label fw-bold small text-dark mb-1">Remarks for Retailer (Optional)</label>
                        <input type="text" name="admin_remarks" id="uploadModalRemarks" class="form-control form-control-sm rounded-2" placeholder="e.g. Approved and certificate issued.">
                    </div>
                </div>

                <div class="modal-footer border-top py-2 px-3 bg-light rounded-bottom-3">
                    <button type="button" class="btn btn-secondary btn-sm rounded-2 px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitApprove" class="btn btn-success btn-sm rounded-2 px-4 fw-bold shadow-2xs">
                        <i class="bi bi-check-circle-fill me-1"></i> <span id="btnSubmitApproveText">Approve</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: REJECT APPLICATION (MANDATORY REASON + REFUND)
     ========================================================================= -->
<div class="modal fade" id="modalRejectApp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2.5 px-4 bg-light rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0 text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-x-circle-fill"></i>
                    <span>Reject Application</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectAppForm">
                <div class="modal-body p-4 space-y-3">
                    <input type="hidden" name="application_id" id="rejectModalAppId" value="">
                    <input type="hidden" name="status" value="REJECTED">

                    <!-- Summary Box -->
                    <div class="p-2.5 bg-light rounded-2 small space-y-1 mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Application Ref:</span>
                            <strong class="font-monospace" id="rejectModalAppRef">--</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Applicant:</span>
                            <strong id="rejectModalApplicant">--</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Refund Amount:</span>
                            <strong class="text-success small font-monospace" id="rejectModalFee">₹0.00</strong>
                        </div>
                    </div>

                    <!-- Preset Reason Chips -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-dark mb-1">Quick Reasons:</label>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill" onclick="setRejectReason('Documents incomplete / blurred')">Incomplete Docs</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill" onclick="setRejectReason('Invalid application number on government portal')">Invalid App No.</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill" onclick="setRejectReason('Applicant name or details mismatch')">Details Mismatch</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill" onclick="setRejectReason('Duplicate submission / already generated')">Duplicate</button>
                        </div>
                    </div>

                    <!-- Mandatory Reason Textarea -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger mb-1">
                            Rejection Reason (Mandatory) <span class="text-danger">*</span>
                        </label>
                        <textarea name="rejection_reason" id="rejectModalReasonInput" class="form-control form-control-sm rounded-2" rows="3" placeholder="Enter reason for rejection..." required></textarea>
                    </div>

                    <!-- Refund Notice -->
                    <div class="alert alert-warning py-1.5 px-3 small rounded-2 mb-0 d-flex align-items-center gap-2" style="font-size: 0.72rem;">
                        <i class="bi bi-wallet2 text-warning fs-6"></i>
                        <span>Fee debited will be <strong>automatically refunded</strong> to retailer wallet.</span>
                    </div>
                </div>

                <div class="modal-footer border-top py-2.5 px-4 bg-light rounded-bottom-3">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitReject" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-2xs">
                        <i class="bi bi-x-circle me-1"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: VIEW REJECTION REASON MODAL
     ========================================================================= -->
<div class="modal fade" id="modalRejectionReason" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2 px-3 bg-danger text-white rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Rejection Reason</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 text-center">
                <div class="p-2.5 bg-danger-subtle text-danger border border-danger-subtle rounded-2 small fw-semibold text-start mb-2.5" id="viewReasonText">
                    --
                </div>
                <div class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2.5 rounded-pill small" style="font-size: 0.72rem;">
                    <i class="bi bi-check-circle me-1"></i> Fee Refunded to Retailer
                </div>
            </div>
            <div class="modal-footer border-top py-1.5 px-3 bg-light rounded-bottom-3 justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 5: RESULT DOCUMENT LIGHTBOX
     ========================================================================= -->
<div class="modal fade" id="modalResultLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-3 border-0 shadow overflow-hidden">
            <div class="modal-header border-bottom py-2.5 px-4 bg-dark text-white">
                <h5 class="modal-title h6 fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check-fill text-success"></i>
                    <span id="resultLightboxTitle">Result Document</span>
                </h5>
                <div class="d-flex gap-2">
                    <a href="#" id="resultDownloadDirect" download target="_blank" class="btn btn-xs btn-success rounded-pill px-3 fw-bold">
                        <i class="bi bi-download me-1"></i> Download
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary-subtle" id="resultLightboxBody" style="min-height: 480px; max-height: 80vh; overflow: auto;">
                <!-- Injected dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 6: RETAILER CHAT
     ========================================================================= -->
<div class="modal fade" id="modalAdminChat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2.5 px-4 bg-primary text-white rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span>Retailer Chat (#<span id="adminChatModalReqNum"></span>)</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="adminChatMessagesWindow" class="chat-window mb-3" style="max-height: 320px; overflow-y: auto;"></div>
                <form id="adminChatSendForm" class="d-flex gap-2">
                    <input type="hidden" id="adminChatAppId" value="">
                    <input type="text" id="adminChatInputMessage" class="form-control form-control-sm rounded-pill" placeholder="Type reply to retailer..." required autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT: REALTIME CLIENT & FILTER CONTROLLER -->
<script>
// Global filter helpers (immediately callable by onclick handlers)
window.filterByStatus = function (st) {
    const form = document.getElementById('filterForm');
    const statusSelect = document.getElementById('filterStatusSelect');
    const statusInput = document.getElementById('filterStatusInput');
    const pageInput = document.getElementById('filterPageInput');
    if (pageInput) pageInput.value = '1';

    const currentSt = (statusSelect ? statusSelect.value : (statusInput ? statusInput.value : 'ALL'));
    const targetSt = (currentSt === st) ? 'ALL' : st;

    if (statusSelect) statusSelect.value = targetSt;
    if (statusInput) statusInput.value = targetSt;
    if (form) form.submit();
};

window.setStatusFilter = function (st) {
    const form = document.getElementById('filterForm');
    const statusSelect = document.getElementById('filterStatusSelect');
    const statusInput = document.getElementById('filterStatusInput');
    const pageInput = document.getElementById('filterPageInput');
    if (pageInput) pageInput.value = '1';

    if (statusSelect) statusSelect.value = st;
    if (statusInput) statusInput.value = st;
    if (form) form.submit();
};

document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('filterForm');
    const filterDistrict = document.getElementById('filterDistrict');
    const filterBlock = document.getElementById('filterBlock');
    const filterStatusSelect = document.getElementById('filterStatusSelect');
    const filterCode = document.getElementById('filterCode');
    const filterDate = document.getElementById('filterDate');
    const searchInput = document.getElementById('admin-search-input');
    const filterPageInput = document.getElementById('filterPageInput');
    const filterStatusInput = document.getElementById('filterStatusInput');

    const allBlocksByDistrict = <?= json_encode($allBlocksGrouped ?? []) ?>;

    // 1. Status dropdown change listener -> resets page and auto-submits
    if (filterStatusSelect) {
        filterStatusSelect.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            if (filterStatusInput) filterStatusInput.value = this.value;
            filterForm.submit();
        });
    }

    // 2. District change listener (only for admin where filterDistrict is a select)
    if (filterDistrict && filterDistrict.tagName === 'SELECT') {
        filterDistrict.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            const distId = this.value;
            if (filterBlock) {
                filterBlock.innerHTML = '<option value="">All Blocks</option>';
                if (distId && allBlocksByDistrict[distId]) {
                    filterBlock.disabled = false;
                    allBlocksByDistrict[distId].forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        opt.textContent = b.name;
                        filterBlock.appendChild(opt);
                    });
                } else {
                    filterBlock.disabled = true;
                }
                filterBlock.value = '';
            }
            filterForm.submit();
        });
    }

    // 3. Block change listener
    if (filterBlock && filterBlock.tagName === 'SELECT') {
        filterBlock.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            filterForm.submit();
        });
    }

    // 4. Application Code change listener -> resets page and auto-submits
    if (filterCode) {
        filterCode.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            filterForm.submit();
        });
    }

    // 5. Date filter change listener -> resets page and auto-submits
    if (filterDate) {
        filterDate.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            filterForm.submit();
        });
    }

    // 6. Search Enter submit
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (filterPageInput) filterPageInput.value = '1';
                filterForm.submit();
            }
        });
    }

    // Copy to clipboard helper
    window.copyToClipboard = function (text, btn) {
        if (!text || text === 'N/A') return;
        navigator.clipboard.writeText(text).then(() => {
            showToast('success', 'Application Number copied: ' + text);
            if (btn) {
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check text-success" style="font-size: 0.82rem;"></i>';
                setTimeout(() => { btn.innerHTML = origHtml; }, 1500);
            }
        }).catch(() => {
            showToast('info', 'Number: ' + text);
        });
    };

    // -------------------------------------------------------------
    // KPI COUNTER HELPER & SEAMLESS ROW TRANSITION (No forced redirect)
    // -------------------------------------------------------------
    function adjustKpiCount(fromStatus, toStatus) {
        const map = {
            'PENDING': 'stat-pending',
            'IN_PROCESS': 'stat-in-process',
            'UNDER_REVIEW': 'stat-in-process',
            'APPROVED': 'stat-approved',
            'COMPLETED': 'stat-approved',
            'REJECTED': 'stat-rejected'
        };
        const fromId = map[fromStatus];
        const toId = map[toStatus];
        if (fromId && fromId !== toId) {
            const el = document.getElementById(fromId);
            if (el) {
                let val = parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
                if (val > 0) el.textContent = (val - 1).toLocaleString();
            }
        }
        if (toId && fromId !== toId) {
            const el = document.getElementById(toId);
            if (el) {
                let val = parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
                el.textContent = (val + 1).toLocaleString();
            }
        }
    }

    function removeRowWithAnimation(row) {
        row.style.transition = 'all 0.35s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateX(25px)';
        setTimeout(() => {
            row.remove();
            const tbody = document.getElementById('adminRequestsTbody');
            if (tbody && tbody.querySelectorAll('.admin-req-row').length === 0) {
                tbody.innerHTML = `
                    <tr id="adminNoRequestsRow">
                        <td colspan="7" class="text-center py-5 text-muted small">
                            <i class="bi bi-inbox fs-2 d-block opacity-50 mb-1"></i>
                            No applications found.
                        </td>
                    </tr>
                `;
            }
        }, 350);
    }

    function handleStatusTransition(appId, newStatus, resultFileUrl = null, rejectionReason = null) {
        const row = document.getElementById('admin-row-' + appId);
        const oldStatus = row ? (row.getAttribute('data-status') || 'PENDING') : 'PENDING';

        adjustKpiCount(oldStatus, newStatus);

        const currentTab = (document.getElementById('filterStatusInput')?.value || 'ALL').toUpperCase();

        if (currentTab === 'PENDING' && newStatus !== 'PENDING') {
            if (row) removeRowWithAnimation(row);
        } else if (currentTab === 'IN_PROCESS' && newStatus !== 'IN_PROCESS' && newStatus !== 'UNDER_REVIEW') {
            if (row) removeRowWithAnimation(row);
        } else if (currentTab === 'APPROVED' && newStatus !== 'APPROVED' && newStatus !== 'COMPLETED') {
            if (row) removeRowWithAnimation(row);
        } else if (currentTab === 'REJECTED' && newStatus !== 'REJECTED') {
            if (row) removeRowWithAnimation(row);
        } else {
            updateRowState(appId, newStatus, resultFileUrl, rejectionReason);
        }
    }

    // -------------------------------------------------------------
    // ACTION: 1-CLICK QUICK STATUS UPDATE
    // -------------------------------------------------------------
    window.quickUpdateStatus = function (appId, targetStatus, btn = null) {
        let origHtml = '';
        if (btn) {
            origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }

        const fd = new FormData();
        fd.append('application_id', appId);
        fd.append('status', targetStatus);
        fd.append('admin_remarks', targetStatus === 'APPROVED' ? 'Approved by administrator' : 'Processing started by administrator');

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const readableTarget = targetStatus === 'APPROVED' ? 'Approved' : (targetStatus === 'IN_PROCESS' ? 'In Process' : targetStatus);
                showToast('success', `Application #${appId} marked as ${readableTarget}!`);
                handleStatusTransition(appId, targetStatus);
            } else {
                showToast('danger', data.error || 'Failed to update status.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }
        })
        .catch(err => {
            console.error(err);
            showToast('danger', 'Network error.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        });
    };

    // -------------------------------------------------------------
    // ACTION 1: START PROCESSING (Pending -> In Process)
    // -------------------------------------------------------------
    window.startProcessing = function (appId, btn) {
        if (!confirm('Start processing application #' + appId + '?')) return;

        btn.disabled = true;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        const fd = new FormData();
        fd.append('application_id', appId);
        fd.append('status', 'IN_PROCESS');
        fd.append('admin_remarks', 'Admin started processing');

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Application #' + appId + ' marked as In Process.');
                handleStatusTransition(appId, 'IN_PROCESS');
            } else {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                showToast('danger', data.error || 'Failed to update status.');
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = origHtml;
            showToast('danger', 'Network error.');
        });
    };

    // -------------------------------------------------------------
    // ACTION 2: APPROVE APPLICATION (with optional file) & POST-APPROVAL UPLOAD
    // -------------------------------------------------------------
    window.openUploadApproveModal = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        const f = req.formData || {};

        document.getElementById('uploadModalAppId').value = req.id;
        document.getElementById('uploadModalStatusInput').value = 'APPROVED';
        document.getElementById('uploadModalAppRef').textContent = req.app_ref || ('WB-' + req.id);
        document.getElementById('uploadModalApplicant').textContent = f.applicant_name || req.applicant_name;
        document.getElementById('uploadModalAppNo').textContent = f.application_number || req.applicant_identifier;
        document.getElementById('uploadModalRemarks').value = req.admin_remarks || '';

        document.getElementById('approveModalTitle').textContent = 'Approve Application';
        document.getElementById('approveModalIcon').className = 'bi bi-check-circle-fill';
        document.getElementById('btnSubmitApproveText').textContent = 'Approve';
        document.getElementById('uploadFileLabel').textContent = 'Result File (Optional)';

        clearUploadFile();
        new bootstrap.Modal(document.getElementById('modalUploadApprove')).show();
    };

    window.openUploadResultModal = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        const f = req.formData || {};

        document.getElementById('uploadModalAppId').value = req.id;
        document.getElementById('uploadModalStatusInput').value = 'APPROVED';
        document.getElementById('uploadModalAppRef').textContent = req.app_ref || ('WB-' + req.id);
        document.getElementById('uploadModalApplicant').textContent = f.applicant_name || req.applicant_name;
        document.getElementById('uploadModalAppNo').textContent = f.application_number || req.applicant_identifier;
        document.getElementById('uploadModalRemarks').value = req.admin_remarks || '';

        document.getElementById('approveModalTitle').textContent = 'Upload Result File';
        document.getElementById('approveModalIcon').className = 'bi bi-cloud-arrow-up-fill';
        document.getElementById('btnSubmitApproveText').textContent = 'Save Result';
        document.getElementById('uploadFileLabel').textContent = req.result_file ? 'Replace Result File (Optional)' : 'Select Result File';

        clearUploadFile();
        new bootstrap.Modal(document.getElementById('modalUploadApprove')).show();
    };

    const fileInput = document.getElementById('uploadResultFileInput');
    fileInput.addEventListener('change', function () {
        if (!this.files || !this.files[0]) {
            clearUploadFile();
            return;
        }
        const file = this.files[0];
        const ext = file.name.split('.').pop().toLowerCase();
        const allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!allowed.includes(ext)) {
            showToast('danger', 'Invalid file type (. ' + ext + '). Only PDF, JPG, and PNG are allowed.');
            clearUploadFile();
            return;
        }
        if (file.size > 15 * 1024 * 1024) {
            showToast('danger', 'File size exceeds maximum 15MB limit.');
            clearUploadFile();
            return;
        }

        const nameLabel = document.getElementById('uploadFileNameLabel');
        if (nameLabel) nameLabel.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        const previewContainer = document.getElementById('uploadPreviewContainer');
        const previewBody = document.getElementById('uploadPreviewBody');
        if (previewContainer) previewContainer.classList.remove('d-none');

        const objUrl = URL.createObjectURL(file);
        if (previewBody) {
            if (ext === 'pdf') {
                previewBody.innerHTML = `<iframe src="${objUrl}" class="w-100 border rounded" style="height: 180px;"></iframe>`;
            } else {
                previewBody.innerHTML = `<img src="${objUrl}" class="img-fluid rounded border shadow-2xs" style="max-height: 180px; object-fit: contain;">`;
            }
        }
    });

    window.clearUploadFile = function () {
        fileInput.value = '';
        const nameLabel = document.getElementById('uploadFileNameLabel');
        if (nameLabel) nameLabel.textContent = '';
        const previewContainer = document.getElementById('uploadPreviewContainer');
        if (previewContainer) previewContainer.classList.add('d-none');
        const previewBody = document.getElementById('uploadPreviewBody');
        if (previewBody) previewBody.innerHTML = '';
    };

    document.getElementById('uploadApproveForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitApprove');
        const isPostUpload = document.getElementById('approveModalTitle').textContent.includes('Upload Result');

        // If file selected, validate type and size
        if (fileInput.files && fileInput.files[0]) {
            const file = fileInput.files[0];
            const ext = file.name.split('.').pop().toLowerCase();
            const allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!allowed.includes(ext)) {
                showToast('danger', 'Invalid file type (. ' + ext + '). Only PDF, JPG, and PNG are allowed.');
                return;
            }
            if (file.size > 15 * 1024 * 1024) {
                showToast('danger', 'File size exceeds maximum 15MB limit.');
                return;
            }
        }

        btn.disabled = true;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        const fd = new FormData(this);

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = origHtml;

            if (data.success) {
                const appId = document.getElementById('uploadModalAppId').value;
                const successMsg = isPostUpload
                    ? (data.outputAttachmentUrl ? 'Result document uploaded successfully!' : 'Remarks updated successfully!')
                    : (data.outputAttachmentUrl ? 'Application approved with result file!' : 'Application approved successfully!');
                showToast('success', successMsg);

                const modalEl = document.getElementById('modalUploadApprove');
                const modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) modalInst.hide();

                handleStatusTransition(appId, 'APPROVED', data.outputAttachmentUrl);
            } else {
                showToast('danger', data.error || 'Failed to update application.');
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = origHtml;
            showToast('danger', 'Network error during request.');
        });
    });

    // -------------------------------------------------------------
    // ACTION 3: REJECT & REFUND
    // -------------------------------------------------------------
    window.openRejectModal = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        const f = req.formData || {};

        document.getElementById('rejectModalAppId').value = req.id;
        document.getElementById('rejectModalAppRef').textContent = req.app_ref || ('WB-' + req.id);
        document.getElementById('rejectModalApplicant').textContent = f.applicant_name || req.applicant_name;
        document.getElementById('rejectModalFee').textContent = '₹' + Number(req.fee_debited || 0).toFixed(2);
        document.getElementById('rejectModalReasonInput').value = '';

        new bootstrap.Modal(document.getElementById('modalRejectApp')).show();
    };

    window.setRejectReason = function (str) {
        document.getElementById('rejectModalReasonInput').value = str;
    };

    document.getElementById('rejectAppForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const reason = document.getElementById('rejectModalReasonInput').value.trim();
        if (!reason) {
            showToast('danger', 'Rejection reason is mandatory.');
            return;
        }

        const btn = document.getElementById('btnSubmitReject');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';

        const fd = new FormData(this);

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Reject';

            if (data.success) {
                showToast('success', 'Application rejected and fee refunded to retailer.');
                const modalEl = document.getElementById('modalRejectApp');
                const modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) modalInst.hide();
                const appId = document.getElementById('rejectModalAppId').value;
                handleStatusTransition(appId, 'REJECTED', null, reason);
            } else {
                showToast('danger', data.error || 'Failed to reject application.');
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Reject';
            showToast('danger', 'Network error during rejection.');
        });
    });

    // -------------------------------------------------------------
    // ACTION 4: VIEW REJECTION REASON MODAL
    // -------------------------------------------------------------
    window.viewRejectionReason = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        document.getElementById('viewReasonText').textContent = req.rejection_reason || req.admin_remarks || req.remarks || 'Reason not specified.';
        new bootstrap.Modal(document.getElementById('modalRejectionReason')).show();
    };

    // -------------------------------------------------------------
    // ACTION 5: VIEW RESULT FILE (LIGHTBOX)
    // -------------------------------------------------------------
    window.viewResultFile = function (url, ref) {
        if (!url) return;
        document.getElementById('resultLightboxTitle').textContent = 'Result Document - ' + (ref || '');
        document.getElementById('resultDownloadDirect').href = url;
        const body = document.getElementById('resultLightboxBody');

        const cleanUrl = url.split('?')[0].toLowerCase();
        if (cleanUrl.endsWith('.pdf')) {
            body.innerHTML = `<iframe src="${url}#toolbar=1" class="w-100 border-0 rounded-bottom-3" style="height: 75vh;"></iframe>`;
        } else {
            body.innerHTML = `<div class="text-center p-4"><img src="${url}" class="img-fluid rounded shadow-sm" style="max-height: 75vh; object-fit: contain;"></div>`;
        }

        new bootstrap.Modal(document.getElementById('modalResultLightbox')).show();
    };

    // -------------------------------------------------------------
    // ACTION 6: APPLICATION FULL DETAILS MODAL
    // -------------------------------------------------------------
    window.openDetailsModal = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        const f = req.formData || {};

        document.getElementById('detailsModalReqNum').textContent = req.id;
        document.getElementById('detailsModalService').textContent = req.service_title || 'Block & District Application Pass Service';
        document.getElementById('detailsModalFee').textContent = '₹' + Number(req.fee_debited || 0).toFixed(2);

        const statusEl = document.getElementById('detailsModalStatus');
        statusEl.textContent = req.status;
        statusEl.className = 'badge ' + (req.status === 'APPROVED' ? 'badge-status-approved' : (req.status === 'REJECTED' ? 'badge-status-rejected' : (req.status === 'IN_PROCESS' ? 'badge-status-in-process' : 'badge-status-pending')));

        const tbody = document.getElementById('detailsModalTableBody');
        tbody.innerHTML = '';

        addDetailRow(tbody, 'APPLICATION REFERENCE', req.app_ref);
        if (req.batch_ref) addDetailRow(tbody, 'BATCH REFERENCE', req.batch_ref);
        addDetailRow(tbody, 'INTERNAL ID', '#' + req.id);
        addDetailRow(tbody, 'SUBMITTED AT', new Date(req.created_at).toLocaleString('en-IN', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12:true}));
        addDetailRow(tbody, 'SERVICE NAME', req.service_title || 'Block & District Application Pass Service');
        addDetailRow(tbody, 'RETAILER NAME', req.retailer_name);
        if (req.retailer_mobile) addDetailRow(tbody, 'RETAILER MOBILE', req.retailer_mobile);
        if (req.center_name) addDetailRow(tbody, 'SHOP / CENTER', req.center_name);
        addDetailRow(tbody, 'APPLICANT NAME', f.applicant_name || req.applicant_name);
        addDetailRow(tbody, 'APPLICATION NUMBER', f.application_number || req.applicant_identifier);
        if (f.application_code) addDetailRow(tbody, 'APPLICATION CODE', f.application_code);
        addDetailRow(tbody, 'DISTRICT', req.district_name);
        addDetailRow(tbody, 'BLOCK', req.block_name);

        if (req.rejection_reason) {
            addDetailRow(tbody, 'REJECTION REASON', `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>${escapeHtml(req.rejection_reason)}</span>`);
        }
        if (req.admin_remarks || req.remarks) {
            addDetailRow(tbody, 'ADMIN REMARKS', req.admin_remarks || req.remarks);
        }
        if (req.result_file) {
            addDetailRow(tbody, 'RESULT DOCUMENT', `<div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-xs btn-outline-success py-1 px-2.5 rounded-pill fw-bold" onclick="viewResultFile('${req.result_file}', '${req.app_ref || req.id}')"><i class="bi bi-eye"></i> View Result</button>
                <a href="${req.result_file}" target="_blank" download class="btn btn-xs btn-success py-1 px-2.5 rounded-pill fw-bold"><i class="bi bi-download"></i> Download</a>
            </div>`);
        }

        // Dynamic Extra Fields
        Object.keys(f).forEach(key => {
            if (['applicant_name', 'application_number', 'district_name', 'block_name', 'mobile', 'application_code'].includes(key)) return;
            addDetailRow(tbody, key.replace(/_/g, ' ').toUpperCase(), f[key]);
        });

        new bootstrap.Modal(document.getElementById('modalAdminAppDetails')).show();
    };

    function addDetailRow(tbody, label, val) {
        const tr = document.createElement('tr');
        const th = document.createElement('th');
        th.style.width = '35%';
        th.textContent = label;
        const td = document.createElement('td');

        const strVal = String(val || '');
        if (strVal.startsWith('/uploads/') || strVal.endsWith('.pdf') || strVal.match(/\.(jpg|jpeg|png)$/i)) {
            td.innerHTML = `<a href="${strVal}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-file-earmark-arrow-down"></i> View File</a>`;
        } else if (strVal.startsWith('<span') || strVal.startsWith('<div')) {
            td.innerHTML = strVal;
        } else {
            td.textContent = strVal || '--';
        }

        tr.appendChild(th);
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    // -------------------------------------------------------------
    // ACTION 7: UPDATE DOM ROW ON STATUS CHANGE
    // -------------------------------------------------------------
    function updateRowState(appId, newStatus, resultFileUrl, rejectionReason) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;

        row.setAttribute('data-status', newStatus);
        const req = JSON.parse(row.getAttribute('data-req'));
        req.status = newStatus;
        req.display_status = newStatus;
        if (resultFileUrl) req.result_file = resultFileUrl;
        if (rejectionReason) req.rejection_reason = rejectionReason;
        if (newStatus === 'REJECTED') req.is_refunded = 1;
        row.setAttribute('data-req', JSON.stringify(req));

        // Update Status Column
        const statusCol = document.getElementById('row-status-col-' + appId);
        let badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        let statusLabel = 'PENDING';
        let iconHtml = '<span class="pulse-dot me-1"></span>';
        if (newStatus === 'IN_PROCESS' || newStatus === 'UNDER_REVIEW') {
            badgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
            statusLabel = 'PROCESSING';
            iconHtml = '<i class="bi bi-arrow-repeat spin-icon me-1"></i>';
        } else if (newStatus === 'APPROVED' || newStatus === 'COMPLETED') {
            badgeClass = 'bg-success-subtle text-success border border-success-subtle';
            statusLabel = 'COMPLETED';
            iconHtml = '<i class="bi bi-check2-circle me-1"></i>';
        } else if (newStatus === 'REJECTED') {
            badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
            statusLabel = 'REJECTED';
            iconHtml = '<i class="bi bi-x-circle me-1"></i>';
        }

        statusCol.innerHTML = `
            <span class="badge status-pill ${badgeClass} px-2.5 py-1 fw-bold">
                ${iconHtml}${statusLabel}
            </span>
            ${newStatus === 'REJECTED' ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block mt-1 font-monospace" style="font-size: 0.62rem; width: fit-content;">Refunded</span>' : ''}
        `;

        // Update Actions Column
        const actionCol = document.getElementById('row-action-col-' + appId);
        let actionButtons = '';
        if (newStatus === 'PENDING') {
            actionButtons = `
                <button type="button" class="btn btn-xs btn-primary fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(${appId}, 'IN_PROCESS', this)" title="1-Click: Mark In Process">
                    <i class="bi bi-lightning-charge-fill me-0.5"></i>Process
                </button>
                <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(${appId}, 'APPROVED', this)" title="1-Click: Approve">
                    <i class="bi bi-check-circle-fill me-0.5"></i>Approve
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-1.5 py-1" onclick="openRejectModal(${appId})" title="Reject Application">
                    <i class="bi bi-x-circle"></i>
                </button>
            `;
        } else if (newStatus === 'IN_PROCESS' || newStatus === 'UNDER_REVIEW') {
            actionButtons = `
                <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2.5 py-1 shadow-2xs" onclick="quickUpdateStatus(${appId}, 'APPROVED', this)" title="1-Click: Approve">
                    <i class="bi bi-check-circle-fill me-0.5"></i>Approve
                </button>
                <button type="button" class="btn btn-xs btn-outline-primary fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadResultModal(${appId})" title="Upload File & Approve">
                    <i class="bi bi-cloud-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-2 py-1" onclick="openRejectModal(${appId})" title="Reject Application">
                    Reject
                </button>
            `;
        } else if (newStatus === 'APPROVED' || newStatus === 'COMPLETED') {
            const finalFile = resultFileUrl || req.result_file;
            if (finalFile) {
                actionButtons = `
                    <button type="button" class="btn btn-xs btn-outline-success fw-bold rounded-2 px-2 py-1" onclick="viewResultFile('${finalFile}', '${req.app_ref || ('WB-' + appId)}')">
                        <i class="bi bi-file-earmark-check me-0.5"></i>Result
                    </button>
                    <a href="${finalFile}" target="_blank" download class="btn btn-xs btn-success fw-semibold rounded-2 px-1.5 py-1" title="Download Result">
                        <i class="bi bi-download"></i>
                    </a>
                `;
            }
            actionButtons += `
                <button type="button" class="btn btn-xs btn-light border fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadResultModal(${appId})" title="Upload or Replace Certificate">
                    <i class="bi bi-cloud-arrow-up text-primary"></i>
                </button>
            `;
        } else if (newStatus === 'REJECTED') {
            actionButtons = `
                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-2 py-1" onclick="viewRejectionReason(${appId})">
                    Reason
                </button>
            `;
        }

        actionCol.innerHTML = `
            <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                ${actionButtons}
                <button type="button" class="btn btn-xs btn-outline-warning fw-semibold rounded-2 px-2.5 py-1 text-decoration-none btn-more-details" onclick="openDetailsModal(${appId})">
                    More Details
                </button>
                <button type="button" class="btn btn-xs btn-light border py-1 px-1.5 rounded-2 text-primary btn-chat-icon" title="Retailer Chat" onclick="adminOpenChat(${appId})">
                    <i class="bi bi-chat-dots-fill"></i>
                </button>
            </div>
        `;
    }

    // -------------------------------------------------------------
    // ACTION 8: RETAILER CHAT MODAL
    // -------------------------------------------------------------
    let adminChatInterval = null;
    window.adminOpenChat = function (appId) {
        document.getElementById('adminChatModalReqNum').textContent = appId;
        document.getElementById('adminChatAppId').value = appId;
        const win = document.getElementById('adminChatMessagesWindow');
        win.innerHTML = '<div class="text-center text-muted small py-3"><div class="spinner-border spinner-border-sm me-1"></div> Loading...</div>';

        fetchAdminChat(appId);
        if (adminChatInterval) clearInterval(adminChatInterval);
        adminChatInterval = setInterval(() => fetchAdminChat(appId), 4000);

        new bootstrap.Modal(document.getElementById('modalAdminChat')).show();
    };

    document.getElementById('modalAdminChat').addEventListener('hidden.bs.modal', function () {
        if (adminChatInterval) {
            clearInterval(adminChatInterval);
            adminChatInterval = null;
        }
    });

    document.getElementById('adminChatSendForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const appId = document.getElementById('adminChatAppId').value;
        const input = document.getElementById('adminChatInputMessage');
        const text = input.value.trim();
        if (!text) return;

        input.value = '';
        fetch('/api/requests/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ application_id: appId, message: text })
        })
        .then(res => res.json())
        .then(() => fetchAdminChat(appId))
        .catch(err => console.error(err));
    });

    function fetchAdminChat(appId) {
        fetch('/api/requests/chat?application_id=' + encodeURIComponent(appId))
            .then(res => res.json())
            .then(data => {
                const win = document.getElementById('adminChatMessagesWindow');
                const messages = data.messages || [];
                if (messages.length === 0) {
                    win.innerHTML = '<div class="text-center text-muted small py-4">No messages yet. Send a message to communicate with the retailer.</div>';
                    return;
                }
                win.innerHTML = '';
                messages.forEach(m => {
                    const isSelf = (m.sender_role === 'ADMIN' || m.sender_role === 'OPERATOR');
                    const div = document.createElement('div');
                    div.className = `chat-msg ${isSelf ? 'chat-msg-sent' : 'chat-msg-recv'}`;
                    div.innerHTML = `
                        <div>${escapeHtml(m.message)}</div>
                        <div class="chat-meta text-${isSelf ? 'white-50' : 'muted'}">${escapeHtml(m.sender_name)} &bull; ${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                    `;
                    win.appendChild(div);
                });
                win.scrollTop = win.scrollHeight;
            })
            .catch(err => console.error(err));
    }

    function showToast(type, msg) {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : (type === 'danger' ? 'danger' : 'primary')} border-0 shadow-lg`;
        t.setAttribute('role', 'alert');
        t.innerHTML = `
            <div class="d-flex">
                <div class="toast-body fw-bold small">${escapeHtml(msg)}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        c.appendChild(t);
        const bsToast = new bootstrap.Toast(t, { delay: 4000 });
        bsToast.show();
        t.addEventListener('hidden.bs.toast', () => t.remove());
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
