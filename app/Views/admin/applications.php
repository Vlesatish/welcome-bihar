<?php require dirname(__DIR__) . '/layouts/header.php'; ?>
<?php
    $currentAdminId = (int)$currentUser['id'];
?>

<style>
/* Clean & Unified Table Styles */
.filters-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.65rem;
}
.filter-col {
    display: flex;
    flex-direction: column;
}
.filter-col-district { flex: 0 0 140px; }
.filter-col-block    { flex: 0 0 140px; }
.filter-col-status   { flex: 0 0 125px; }
.filter-col-code     { flex: 0 0 115px; }
.filter-col-date     { flex: 0 0 115px; }
.filter-col-search   { flex: 1 1 200px; min-width: 170px; }
.filter-col-btn      { flex: 0 0 auto; }

.filter-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.filter-ctrl {
    height: 36px !important;
    font-size: 0.82rem !important;
}
.filter-select {
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
    padding-left: 0.55rem !important;
    padding-right: 1.5rem !important;
    background-position: right 0.45rem center !important;
    font-weight: 500;
    border-radius: 6px;
    border-color: #cbd5e1;
}

.table-admin th {
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    background-color: #f8fafc;
    border-bottom: 1.5px solid #cbd5e1;
    white-space: nowrap;
}
.table-admin td {
    font-size: 0.82rem;
    vertical-align: middle;
}
.btn-xs {
    padding: 0.25rem 0.55rem;
    font-size: 0.72rem;
    line-height: 1.25;
}
.tab-pill {
    padding: 0.4rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 0.45rem;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.15s ease-in-out;
}
.tab-pill-all { background-color: #f1f5f9; color: #334155; border-color: #cbd5e1; }
.tab-pill-all.active { background-color: #0f172a; color: #ffffff; border-color: #0f172a; }
.tab-pill-pending { background-color: #fef3c7; color: #92400e; border-color: #fde68a; }
.tab-pill-pending.active { background-color: #d97706; color: #ffffff; border-color: #d97706; }
.tab-pill-in-process { background-color: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
.tab-pill-in-process.active { background-color: #2563eb; color: #ffffff; border-color: #2563eb; }
.tab-pill-approved { background-color: #d1fae5; color: #065f46; border-color: #a7f3d0; }
.tab-pill-approved.active { background-color: #059669; color: #ffffff; border-color: #059669; }
.tab-pill-rejected { background-color: #fee2e2; color: #991b1b; border-color: #fecaca; }
.tab-pill-rejected.active { background-color: #dc2626; color: #ffffff; border-color: #dc2626; }
</style>

<!-- TOAST CONTAINER -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1085;"></div>

<div class="container-fluid px-2 px-md-4 py-2 mb-5" style="max-width: 1400px;">

    <?php if (!empty($unassignedOperator)): ?>
        <div class="alert alert-warning border border-warning shadow-xs rounded-3 p-3 mb-3 d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
            <div>
                <h6 class="fw-bold mb-0 text-dark">Zero Access: Location Assignment Required</h6>
                <div class="small text-secondary">Your block operator account has not been assigned to any District and Block yet. Please contact Super Admin.</div>
            </div>
        </div>
    <?php endif; ?>

    <!-- TOP HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-folder2-open text-primary"></i>
                <span>Applications</span>
            </h1>
            <div class="small text-muted" style="font-size: 0.74rem;">
                Total <strong><?= number_format($totalRecords) ?></strong> records in authorized view
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if (\App\Services\AuthService::isAdmin()): ?>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold shadow-sm d-flex align-items-center gap-1.5" id="btnAdminTriggerAutoCheck" onclick="runAdminBatchRtpsCheck(this)" title="Bihar RTPS portal se sabhi pending certificates download aur auto attach karein">
                    <i class="bi bi-magic"></i> <span>Auto Check &amp; Attach (RTPS)</span>
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold shadow-2xs" onclick="window.location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
            <a href="/applications/export-csv<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold shadow-2xs">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
            <?php if (!$isOperator && (($assignmentStatus ?? '') === 'UNASSIGNED' || !empty($_GET['assignment_status']))): ?>
                <form method="POST" action="/admin/applications/route-unassigned" class="d-inline" onsubmit="return confirm('Route all pending unassigned applications to matching active operators now?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="district_id" value="<?= e($districtId) ?>">
                    <input type="hidden" name="block_id" value="<?= e($blockId) ?>">
                    <input type="hidden" name="redirect_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/applications?assignment_status=UNASSIGNED') ?>">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-semibold shadow-2xs">
                        <i class="bi bi-person-check me-1"></i> Reassign Matching Applications
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($currentUser['role'] === 'ADMIN'): ?>
                <a href="/admin/data-types" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold shadow-2xs">
                    <i class="bi bi-sliders me-1"></i> Service Settings
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABS: ALL | PENDING | IN PROCESS | APPROVED | REJECTED -->
    <div class="d-flex gap-2 flex-wrap mb-3">
        <?php
            if (!function_exists('buildTabUrl')) {
                function buildTabUrl($tab) {
                    $q = $_GET;
                    $q['status'] = $tab;
                    $q['page'] = 1;
                    return '/admin/applications?' . http_build_query($q);
                }
            }
        ?>
        <a href="<?= buildTabUrl('ALL') ?>" class="tab-pill tab-pill-all <?= $currentTab === 'ALL' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i> All (<?= number_format((int)($tabCounts['ALL'] ?? 0)) ?>)
        </a>
        <a href="<?= buildTabUrl('PENDING') ?>" class="tab-pill tab-pill-pending <?= $currentTab === 'PENDING' ? 'active' : '' ?>">
            <i class="bi bi-hourglass-split"></i> Pending (<?= number_format((int)($tabCounts['PENDING'] ?? 0)) ?>)
        </a>
        <a href="<?= buildTabUrl('IN_PROCESS') ?>" class="tab-pill tab-pill-in-process <?= $currentTab === 'IN_PROCESS' ? 'active' : '' ?>">
            <i class="bi bi-gear-fill"></i> Processing (<?= number_format((int)($tabCounts['IN_PROCESS'] ?? 0)) ?>)
        </a>
        <a href="<?= buildTabUrl('APPROVED') ?>" class="tab-pill tab-pill-approved <?= $currentTab === 'APPROVED' ? 'active' : '' ?>">
            <i class="bi bi-check-circle-fill"></i> Approved (<?= number_format((int)($tabCounts['APPROVED'] ?? 0)) ?>)
        </a>
        <a href="<?= buildTabUrl('REJECTED') ?>" class="tab-pill tab-pill-rejected <?= $currentTab === 'REJECTED' ? 'active' : '' ?>">
            <i class="bi bi-x-circle-fill"></i> Rejected (<?= number_format((int)($tabCounts['REJECTED'] ?? 0)) ?>)
        </a>
    </div>

    <!-- FILTERS CARD: Single-Row 36px Flex Grid -->
    <div class="card shadow-sm border rounded-3 bg-white mb-4 p-3" style="border: 1px solid #e2e8f0;">
        <form method="GET" action="/admin/applications" id="adminFilterForm">
            <input type="hidden" name="status" value="<?= e($currentTab) ?>">

            <div class="filters-grid pt-1">
                <!-- 1. District -->
                <div class="filter-col filter-col-district">
                    <label for="filter_district_id" class="filter-label">District</label>
                    <select name="district_id" id="filter_district_id" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value=""><?= $isOperator ? 'All Assigned Districts' : 'All Districts' ?></option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Block -->
                <div class="filter-col filter-col-block">
                    <label for="filter_block_id" class="filter-label">Block</label>
                    <select name="block_id" id="filter_block_id" class="form-select form-select-sm filter-select filter-ctrl w-100" <?= empty($blocks) && empty($districtId) && !$isOperator ? 'disabled' : '' ?>>
                        <option value=""><?= $isOperator ? 'All Assigned Blocks' : 'All Blocks' ?></option>
                        <?php foreach ($blocks as $b): ?>
                            <?php $bId = (int)($b['id'] ?? $b['block_id']); ?>
                            <option value="<?= $bId ?>" <?= $blockId === $bId ? 'selected' : '' ?>><?= e($b['name'] ?? $b['block_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Status Filter (if Tab is ALL) -->
                <div class="filter-col filter-col-status">
                    <label for="filter_status_select" class="filter-label">Status</label>
                    <select name="status_filter" id="filter_status_select" class="form-select form-select-sm filter-select filter-ctrl w-100" onchange="this.form.status.value = this.value; this.form.submit();">
                        <option value="ALL" <?= $currentTab === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="PENDING" <?= $currentTab === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                        <option value="IN_PROCESS" <?= $currentTab === 'IN_PROCESS' ? 'selected' : '' ?>>Processing</option>
                        <option value="APPROVED" <?= $currentTab === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                        <option value="REJECTED" <?= $currentTab === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <!-- 4. App Code -->
                <div class="filter-col filter-col-code">
                    <label for="filter_app_code" class="filter-label">App Code</label>
                    <select name="app_code" id="filter_app_code" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="">All Codes</option>
                        <?php foreach ($appCodes as $cVal => $cLabel): ?>
                            <option value="<?= e($cVal) ?>" <?= $appCode === $cVal ? 'selected' : '' ?>><?= e($cVal) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 5. Retailer Filter -->
                <div class="filter-col" style="flex: 0 0 140px;">
                    <label for="filter_retailer_id" class="filter-label">Retailer</label>
                    <select name="retailer_id" id="filter_retailer_id" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="">All Retailers</option>
                        <?php foreach ($retailers as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $retailerId === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 6. Date -->
                <div class="filter-col filter-col-date">
                    <label for="filter_date" class="filter-label">Date</label>
                    <select name="date_filter" id="filter_date" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="ALL" <?= ($dateFilter === 'ALL' || empty($dateFilter)) ? 'selected' : '' ?>>All Dates</option>
                        <option value="TODAY" <?= $dateFilter === 'TODAY' ? 'selected' : '' ?>>Today</option>
                        <option value="WEEK" <?= $dateFilter === 'WEEK' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="MONTH" <?= $dateFilter === 'MONTH' ? 'selected' : '' ?>>Last 30 Days</option>
                    </select>
                </div>

                <!-- 6b. Routing Status Filter (Admin only) -->
                <?php if (!$isOperator): ?>
                <div class="filter-col" style="flex: 0 0 130px;">
                    <label for="filter_assignment_status" class="filter-label">Staff Routing</label>
                    <select name="assignment_status" id="filter_assignment_status" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="ALL" <?= (($assignmentStatus ?? 'ALL') === 'ALL') ? 'selected' : '' ?>>All Staff</option>
                        <option value="ASSIGNED" <?= (($assignmentStatus ?? '') === 'ASSIGNED') ? 'selected' : '' ?>>Assigned</option>
                        <option value="UNASSIGNED" <?= (($assignmentStatus ?? '') === 'UNASSIGNED') ? 'selected' : '' ?>>Unassigned</option>
                    </select>
                </div>
                <?php endif; ?>

                <!-- 7. Search Bar -->
                <div class="filter-col filter-col-search">
                    <label for="filter_search" class="filter-label">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted filter-ctrl" style="border-radius: 6px 0 0 6px !important;"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" id="filter_search" value="<?= e($search) ?>" class="form-control border-start-0 ps-0 bg-white filter-ctrl" style="border-radius: 0 6px 6px 0 !important;" placeholder="Search App No, Applicant, Mobile...">
                    </div>
                </div>

                <!-- 8. Filter & Reset Buttons -->
                <div class="filter-col filter-col-btn">
                    <label class="filter-label invisible d-none d-lg-block">&nbsp;</label>
                    <div class="d-flex gap-1.5">
                        <button type="submit" class="btn btn-sm btn-primary filter-ctrl px-3 rounded-2 fw-semibold d-inline-flex align-items-center gap-1">
                            <i class="bi bi-funnel"></i><span>Filter</span>
                        </button>
                        <a href="/admin/applications?status=<?= e($currentTab) ?>" class="btn btn-sm btn-outline-danger filter-ctrl px-2.5 rounded-2 d-inline-flex align-items-center gap-1" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i><span>Reset</span>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- APPLICATIONS TABLE CARD: 7-Column Layout: Application No. | Applicant | Retailer | District/Block | Fee | Status | Actions -->
    <div class="card shadow-sm border rounded-3 bg-white mb-4" style="border: 1px solid #e2e8f0;">
        <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-primary"></i>
                <h2 class="h6 fw-bold mb-0 text-dark">Applications List</h2>
            </div>
            <span class="badge bg-light text-secondary border font-monospace"><?= number_format($totalRecords) ?> Records</span>
        </div>

        <div class="card-body p-0">
            <?php if (empty($applications)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 opacity-50 d-block mb-1"></i>
                    <h5 class="h6 fw-bold text-dark mb-1">No Applications Found</h5>
                    <p class="small text-muted mb-2">No records match the selected tab (<?= e($currentTab) ?>) or applied search filters.</p>
                    <a href="/admin/applications?status=ALL" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                        Reset Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-admin">
                        <thead class="table-light text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">
                            <tr>
                                <th class="ps-3 py-2.5">Application No.</th>
                                <th class="py-2.5">App Code</th>
                                <th class="py-2.5">Applicant</th>
                                <th class="py-2.5">Retailer</th>
                                <th class="py-2.5">District/Block</th>
                                <?php if (!$isOperator): ?>
                                    <th class="py-2.5">Assigned Staff</th>
                                <?php endif; ?>
                                <th class="py-2.5">Fee</th>
                                <th class="py-2.5">Status</th>
                                <th class="text-end pe-3 py-2.5" style="min-width: 240px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <?php
                                    $status = strtoupper($app['status'] ?? 'PENDING');
                                    $appNum = $app['applicant_identifier'] ?: '#' . $app['app_ref'];
                                    $safeApp = $app;
                                    if (!empty($safeApp['fields']) && is_array($safeApp['fields'])) {
                                        foreach ($safeApp['fields'] as $fKey => &$fObj) {
                                            $fnLower = strtolower((string)$fKey);
                                            if (str_contains($fnLower, 'aadhaar') || $fnLower === 'uid') {
                                                $fObj['value'] = \App\Services\ApplicationCodeFieldService::maskAadhaar((string)($fObj['value'] ?? ''));
                                            }
                                        }
                                        unset($fObj);
                                    }
                                    $appJson = htmlspecialchars(json_encode($safeApp), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr id="row-app-<?= $app['id'] ?>" data-app='<?= $appJson ?>'>
                                    <!-- 1. Application No. (bold and selectable/copyable) -->
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-1 font-monospace fw-bold text-dark" style="font-size: 0.85rem;">
                                            <span class="user-select-all"><?= e($app['applicant_identifier'] ?: '#' . $app['app_ref']) ?></span>
                                            <?php if (!empty($app['applicant_identifier'])): ?>
                                                <button type="button" class="btn btn-link p-0 text-muted" title="Copy Number" onclick="copyText('<?= e($app['applicant_identifier']) ?>', this)">
                                                    <i class="bi bi-copy" style="font-size: 0.75rem;"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 1b. App Code -->
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold font-monospace" style="font-size: 0.76rem;">
                                            <?= e($app['application_code'] ?: 'N/A') ?>
                                        </span>
                                    </td>

                                    <!-- 2. Applicant (normal readable text) -->
                                    <td>
                                        <div class="text-dark fw-medium" style="font-size: 0.85rem;">
                                            <?= e($app['applicant_name']) ?>
                                        </div>
                                    </td>

                                    <!-- 3. Retailer -->
                                    <td>
                                        <div class="text-dark fw-medium" style="font-size: 0.82rem;">
                                            <?= e($app['retailer_name'] ?: 'Retailer #' . $app['retailer_id']) ?>
                                        </div>
                                        <?php if (!empty($app['center_name'])): ?>
                                            <div class="text-muted small" style="font-size: 0.72rem;"><?= e($app['center_name']) ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 4. District / Block -->
                                    <td>
                                        <div class="text-dark small" style="font-size: 0.8rem;">
                                            <span><?= e($app['district_name']) ?></span> &gt; <span class="text-secondary"><?= e($app['block_name']) ?></span>
                                        </div>
                                    </td>

                                    <!-- 4b. Assigned Staff (Admin Only) -->
                                    <?php if (!$isOperator): ?>
                                        <td>
                                            <?php if (!empty($app['assigned_operator_id'])): ?>
                                                <div class="fw-semibold text-dark" style="font-size: 0.8rem;">
                                                    <i class="bi bi-person-check-fill text-success me-1"></i><?= e($app['assigned_operator_name'] ?: 'Staff #' . $app['assigned_operator_id']) ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.7rem;">
                                                    <?= e($app['assigned_operator_mobile'] ?? '') ?>
                                                    <?php if (!empty($app['assignment_method'])): ?>
                                                        &bull; <span class="badge bg-light text-secondary border py-0 px-1"><?= e($app['assignment_method']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5" style="font-size: 0.72rem;">
                                                    <i class="bi bi-exclamation-circle me-1"></i>Unassigned
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <!-- 5. Fee -->
                                    <td class="fw-bold text-dark font-monospace">
                                        ₹<?= number_format($app['fee_debited'], 2) ?>
                                    </td>

                                    <!-- 6. Status (Pending amber, In Process blue, Approved green, Rejected red) -->
                                    <td>
                                        <div>
                                            <?php if ($status === 'COMPLETED' || $status === 'APPROVED'): ?>
                                                <span class="badge bg-success text-white px-2 py-1 small fw-bold" style="font-size: 0.72rem;">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Approved
                                                </span>
                                            <?php elseif ($status === 'PENDING'): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1 small fw-bold" style="font-size: 0.72rem; background-color: #f59e0b !important; color: #ffffff !important;">
                                                    <span class="spinner-grow spinner-grow-sm me-1" style="width: 6px; height: 6px;"></span>Pending
                                                </span>
                                            <?php elseif ($status === 'IN_PROCESS' || $status === 'UNDER_REVIEW'): ?>
                                                <span class="badge bg-primary text-white px-2 py-1 small fw-bold" style="font-size: 0.72rem;">
                                                    <i class="bi bi-gear-wide-connected me-1"></i>In Process
                                                </span>
                                            <?php elseif ($status === 'REJECTED'): ?>
                                                <span class="badge bg-danger text-white px-2 py-1 small fw-bold" style="font-size: 0.72rem;">
                                                    <i class="bi bi-x-circle-fill me-1"></i>Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary text-white px-2 py-1 small fw-bold" style="font-size: 0.72rem;">
                                                    <?= e($status) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-1">
                                            <?= render_status_stepper_dots($app) ?>
                                        </div>
                                    </td>

                                    <!-- 7. Actions -->
                                    <td class="text-end pe-3">
                                        <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">

                                            <?php if ($status === 'PENDING'): ?>
                                                <button type="button" class="btn btn-xs btn-primary fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(<?= $app['id'] ?>, 'IN_PROCESS', this)" title="Start Process">
                                                    <i class="bi bi-lightning-charge-fill me-0.5"></i> Process
                                                </button>
                                                <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(<?= $app['id'] ?>, 'APPROVED', this)" title="Approve Application">
                                                    <i class="bi bi-check-circle-fill me-0.5"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-1.5 py-1" onclick="openRejectModal(<?= $app['id'] ?>)" title="Reject Application">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>

                                            <?php elseif ($status === 'IN_PROCESS' || $status === 'UNDER_REVIEW'): ?>
                                                <button type="button" class="btn btn-xs btn-primary fw-bold rounded-2 px-2 py-1 shadow-2xs text-nowrap" onclick="autoFetchAndAttachRtps(<?= $app['id'] ?>, this)" title="Bihar RTPS Portal se 1-Click Download & Attach PDF">
                                                    <i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Attach
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadApproveModal(<?= $app['id'] ?>)" title="Upload Certificate Manually">
                                                    <i class="bi bi-cloud-arrow-up"></i>
                                                </button>

                                                <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(<?= $app['id'] ?>, 'APPROVED', this)" title="Direct Approve Application">
                                                    <i class="bi bi-check-circle-fill me-0.5"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-1.5 py-1" onclick="openRejectModal(<?= $app['id'] ?>)" title="Reject Application">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>

                                            <?php elseif ($status === 'APPROVED' || $status === 'COMPLETED'): ?>
                                                <?php if (!empty($app['result_file'])): ?>
                                                    <button type="button" class="btn btn-xs btn-outline-success fw-bold rounded-2 px-2 py-1" onclick="openResultLightbox('<?= e($app['result_file']) ?>')" title="View Output File">
                                                        <i class="bi bi-file-earmark-check me-0.5"></i> Result
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-xs btn-light border fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadApproveModal(<?= $app['id'] ?>)" title="Upload or Replace Certificate">
                                                    <i class="bi bi-cloud-arrow-up text-primary"></i>
                                                </button>

                                            <?php elseif ($status === 'REJECTED'): ?>
                                                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-2 py-1" onclick="openRejectModal(<?= $app['id'] ?>)" title="View Rejection Reason">
                                                    Reason
                                                </button>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-xs btn-outline-warning fw-semibold rounded-2 px-2.5 py-1 text-decoration-none" style="font-size: 0.72rem; color: #b45309; border-color: #fde68a; background: #fffdf7;" onclick="openDetailsModal(<?= $app['id'] ?>)" title="More Details">
                                                Details
                                            </button>
                                            <?php if (!$isOperator): ?>
                                                <button type="button" class="btn btn-xs btn-outline-secondary fw-semibold rounded-2 px-1.5 py-1" onclick="openReassignModal(<?= (int)$app['id'] ?>, <?= (int)($app['district_id'] ?? 0) ?>, <?= (int)($app['block_id'] ?? 0) ?>, '<?= e(addslashes($app['applicant_identifier'] ?: $app['app_ref'])) ?>', <?= (int)($app['assigned_operator_id'] ?? 0) ?>)" title="Reassign Staff Operator">
                                                    <i class="bi bi-person-gear"></i> Reassign
                                                </button>
                                            <?php endif; ?>

                                            <!-- Chat Trigger -->
                                            <a href="/application/view?id=<?= $app['id'] ?>#chat" class="btn btn-xs btn-outline-info rounded-2 px-1.5 py-1 text-decoration-none" title="Open Chat">
                                                <i class="bi bi-chat-dots"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION FOOTER -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-white py-2 px-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="small text-muted" style="font-size: 0.75rem;">
                            Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= number_format($totalRecords) ?> total records)
                        </div>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                                function buildPageUrl($p) {
                                    $q = $_GET;
                                    $q['page'] = $p;
                                    return '/admin/applications?' . http_build_query($q);
                                }
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildPageUrl(1) ?>">&laquo; First</a>
                            </li>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildPageUrl($page - 1) ?>">Prev</a>
                            </li>
                            <li class="page-item active">
                                <span class="page-link"><?= $page ?></span>
                            </li>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildPageUrl($page + 1) ?>">Next</a>
                            </li>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildPageUrl($totalPages) ?>">Last &raquo;</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php require __DIR__ . '/applications_modals.php'; ?>
