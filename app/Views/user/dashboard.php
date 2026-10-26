<?php
$applications = isset($applications) && is_array($applications) ? $applications : [];
$recentApplications = isset($recentApplications) && is_array($recentApplications) ? $recentApplications : $applications;
$cntPending = $cntPending ?? 0;
$cntInProcess = $cntInProcess ?? 0;
$cntApproved = $cntApproved ?? 0;
$cntRejected = $cntRejected ?? 0;
$cntAll = $cntAll ?? 0;
$totalRecords = $totalRecords ?? count($applications);
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;
$currentTab = $currentTab ?? 'ALL';
$districts = isset($districts) && is_array($districts) ? $districts : [];
$blocks = isset($blocks) && is_array($blocks) ? $blocks : [];
$appCodes = isset($appCodes) && is_array($appCodes) ? $appCodes : [];
?>
<?php require dirname(__DIR__) . '/layouts/header.php'; ?>
<?php require dirname(__DIR__) . '/admin/dashboard_styles.php'; ?>

<!-- TOAST CONTAINER -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

<!-- Global filter helper (callable immediately on card click) -->
<script>
window.filterByStatus = function (st) {
    const form = document.getElementById('filterForm');
    const statusSelect = document.getElementById('filterStatusSelect');
    const pageInput = document.getElementById('filterPageInput');
    if (pageInput) pageInput.value = '1';

    const currentSt = statusSelect ? statusSelect.value : 'ALL';
    const targetSt = (currentSt === st) ? 'ALL' : st;

    if (statusSelect) statusSelect.value = targetSt;
    if (form) {
        form.submit();
    } else {
        window.location.href = '/user/dashboard' + (targetSt !== 'ALL' ? '?status=' + encodeURIComponent(targetSt) : '');
    }
};
</script>

<!-- 1. Executive Clickable Status Cards (Exact match to Admin & Operator Dashboard) -->
<div class="row g-3 mb-3" id="statusCardsRow">

    <!-- Pending -->
    <div class="col-6 col-md-3">
        <a href="/user/dashboard?status=<?= $currentTab === 'PENDING' ? 'ALL' : 'PENDING' ?>"
           class="dashboard-stat-card card-pending text-decoration-none d-block <?= $currentTab === 'PENDING' ? 'active-card' : '' ?>"
           onclick="filterByStatus('PENDING'); return false;"
           id="card-status-pending"
           title="Click to filter by Pending">
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
        </a>
    </div>

    <!-- In Process -->
    <div class="col-6 col-md-3">
        <a href="/user/dashboard?status=<?= $currentTab === 'IN_PROCESS' ? 'ALL' : 'IN_PROCESS' ?>"
           class="dashboard-stat-card card-in-process text-decoration-none d-block <?= $currentTab === 'IN_PROCESS' ? 'active-card' : '' ?>"
           onclick="filterByStatus('IN_PROCESS'); return false;"
           id="card-status-in-process"
           title="Click to filter by In Process">
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
        </a>
    </div>

    <!-- Approved -->
    <div class="col-6 col-md-3">
        <a href="/user/dashboard?status=<?= $currentTab === 'APPROVED' ? 'ALL' : 'APPROVED' ?>"
           class="dashboard-stat-card card-approved text-decoration-none d-block <?= $currentTab === 'APPROVED' ? 'active-card' : '' ?>"
           onclick="filterByStatus('APPROVED'); return false;"
           id="card-status-approved"
           title="Click to filter by Approved">
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
        </a>
    </div>

    <!-- Rejected -->
    <div class="col-6 col-md-3">
        <a href="/user/dashboard?status=<?= $currentTab === 'REJECTED' ? 'ALL' : 'REJECTED' ?>"
           class="dashboard-stat-card card-rejected text-decoration-none d-block <?= $currentTab === 'REJECTED' ? 'active-card' : '' ?>"
           onclick="filterByStatus('REJECTED'); return false;"
           id="card-status-rejected"
           title="Click to filter by Rejected">
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
        </a>
    </div>
</div>

<!-- 2. Standalone Filters Card (Exact match to Admin & Operator Dashboard) -->
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

        <!-- Filter Controls Form -->
        <form method="GET" action="/user/dashboard" id="filterForm">
            <input type="hidden" name="page" id="filterPageInput" value="1">

            <div class="admin-filters-grid pt-1">
                <!-- 1. District -->
                <div class="filter-col filter-col-district">
                    <label for="filterDistrict" class="filter-label">District</label>
                    <select name="district_id" id="filterDistrict" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="">All Districts</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Block -->
                <div class="filter-col filter-col-block">
                    <label for="filterBlock" class="filter-label">Block</label>
                    <select name="block_id" id="filterBlock" class="form-select form-select-sm filter-select filter-ctrl w-100" <?= empty($districtId) ? 'disabled' : '' ?>>
                        <option value="">All Blocks</option>
                        <?php foreach ($blocks as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $blockId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

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
                        <?php foreach ($appCodes as $cVal => $cLabel): ?>
                            <option value="<?= e($cVal) ?>" <?= $appCode === $cVal ? 'selected' : '' ?>><?= e($cVal) ?></option>
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
                    <label for="filterSearch" class="filter-label">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted filter-ctrl" style="border-radius: 6px 0 0 6px !important;"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" id="filterSearch" value="<?= e($search) ?>" class="form-control border-start-0 ps-0 bg-white filter-ctrl" style="border-radius: 0 6px 6px 0 !important;" placeholder="Search applications...">
                    </div>
                </div>

                <!-- 7. Reset Button -->
                <div class="filter-col filter-col-btn">
                    <a href="/user/dashboard" class="btn btn-sm btn-outline-danger filter-btn filter-ctrl" title="Reset all filters">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 3. Applications Stream Table Card (Exact match to Admin & Operator Dashboard) -->
<div class="card shadow-sm border rounded-3 mb-5 bg-white" id="adminTableCard" style="border: 1px solid #e2e8f0;">
    <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary"></i>
            <h2 class="h6 fw-bold mb-0 text-dark">Applications Stream</h2>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-light text-secondary border font-monospace"><?= number_format($totalRecords) ?> Records</span>
            <a href="/applications/export-csv?<?= http_build_query($_GET) ?>" class="btn btn-xs btn-outline-success fw-semibold rounded-pill px-2.5 shadow-2xs">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
            <a href="/applications/new" class="btn btn-xs btn-primary fw-bold rounded-pill px-3 shadow-2xs">
                <i class="bi bi-plus-circle me-1"></i> New Application
            </a>
        </div>
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
                    <th class="text-end pe-3 py-2.5" style="min-width: 170px;">Action</th>
                </tr>
            </thead>
            <tbody id="adminRequestsTbody">
                <?php if (empty($applications)): ?>
                    <tr id="adminNoRequestsRow">
                        <td colspan="7" class="text-center py-5 text-muted small">
                            <i class="bi bi-inbox fs-2 d-block opacity-50 mb-1"></i>
                            No applications found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <?php
                            $st = strtoupper($app['status'] ?? 'PENDING');
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

                            $f = $app['formData'] ?? [];
                            $appName = $f['applicant_name'] ?? $app['applicant_name'] ?? 'Applicant';
                            $appNum = $f['application_number'] ?? $app['applicant_identifier'] ?? 'N/A';
                            $appJson = htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="admin-req-row"
                            id="app-row-<?= $app['id'] ?>"
                            data-id="<?= $app['id'] ?>"
                            data-status="<?= $st ?>"
                            data-app='<?= $appJson ?>'>

                            <!-- 1. Order Reference -->
                            <td class="ps-3">
                                <div class="d-inline-flex align-items-center gap-1 px-2 py-0.5 rounded-2 bg-light border font-monospace small fw-bold text-dark">
                                    <span>#<?= e($app['app_ref'] ?: ('WB-' . $app['id'])) ?></span>
                                </div>
                            </td>

                            <!-- 2. Applicant Name + Avatar + Location below -->
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
                                            <span class="text-truncate"><i class="bi bi-geo-alt text-primary opacity-75 me-0.5"></i><?= e($app['district_name'] ?: 'District') ?> &gt; <?= e($app['block_name'] ?: 'Block') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- 3. Application No. with Copy Button -->
                            <td>
                                <div class="d-inline-flex align-items-center gap-1 font-monospace fw-bold text-dark small">
                                    <span><?= e($appNum) ?></span>
                                    <?php if ($appNum !== 'N/A' && !empty($appNum)): ?>
                                        <button type="button" class="btn btn-xs btn-link p-0 text-muted ms-0.5" onclick="copyText('<?= e($appNum) ?>', this)" title="Copy Application Number">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- 4. Amount -->
                            <td>
                                <span class="fw-bold text-dark font-monospace" style="font-size: 0.86rem;">₹<?= number_format($app['fee_debited'], 2) ?></span>
                            </td>

                            <!-- 5. Status Badge -->
                            <td>
                                <span class="status-pill badge <?= $badgeClass ?> fw-bold">
                                    <?php if ($statusLabel === 'PENDING'): ?>
                                        <span class="pulse-dot me-1"></span>
                                    <?php elseif ($statusLabel === 'PROCESSING'): ?>
                                        <i class="bi bi-arrow-repeat spin-icon me-1"></i>
                                    <?php elseif ($statusLabel === 'COMPLETED'): ?>
                                        <i class="bi bi-check2 me-1"></i>
                                    <?php elseif ($statusLabel === 'REJECTED'): ?>
                                        <i class="bi bi-x me-1"></i>
                                    <?php endif; ?>
                                    <?= $statusLabel ?>
                                </span>
                                <div class="mt-1">
                                    <?= render_status_stepper_dots($app) ?>
                                </div>
                            </td>

                            <!-- 6. Date -->
                            <td class="nowrap-cell small text-muted" style="font-size: 0.75rem;">
                                <?= date('d M Y', strtotime($app['created_at'])) ?>
                                <div class="text-muted" style="font-size: 0.68rem;"><?= date('h:i A', strtotime($app['created_at'])) ?></div>
                            </td>

                            <!-- 7. Action -->
                            <td class="text-end pe-3">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                    <?php if (!empty($app['result_file'])): ?>
                                        <a href="<?= e($app['result_file']) ?>" target="_blank" download
                                           class="btn btn-xs btn-success fw-bold rounded-2 px-2 shadow-2xs text-nowrap"
                                           title="Download RTPS Certificate">
                                            <i class="bi bi-file-earmark-pdf-fill me-1"></i>Download Certificate
                                        </a>
                                    <?php elseif ($app['status'] === 'IN_PROCESS'): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-1 px-1.5 text-nowrap" style="font-size: 0.68rem;" title="Automatic RTPS Check Scheduled (Every 5 Hours / 7 AM)">
                                            <i class="bi bi-clock-history me-1"></i>RTPS Check (5-Hr)
                                        </span>
                                    <?php endif; ?>

                                    <button type="button"
                                            class="btn btn-xs btn-more-details fw-bold rounded-2 px-2.5 shadow-2xs"
                                            onclick="openDetailsModal(<?= $app['id'] ?>)">
                                        More Details
                                    </button>

                                    <button type="button"
                                            class="btn btn-xs btn-outline-primary btn-chat-icon rounded-2 shadow-2xs"
                                            title="Chat with Operator"
                                            onclick="openChatModal(<?= $app['id'] ?>)">
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-muted" style="font-size: 0.75rem;">
                Page <?= $page ?> of <?= $totalPages ?> (Total <?= number_format($totalRecords) ?> records)
            </div>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a>
                    </li>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- ================= MODALS: DETAILS, RECEIPT, CHAT, LIGHTBOX ================= -->

<!-- 1. DETAILS MODAL -->
<div class="modal fade" id="modalAppDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom py-2.5 px-4">
                <h5 class="modal-title h6 fw-bold mb-0">
                    <i class="bi bi-card-text text-primary me-2"></i>Application Details #<span id="modalDetailsReqNum"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-2.5 rounded-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                    <div><strong>Service:</strong> <span id="modalDetailsService">Block & District Application Pass Service</span></div>
                    <div><strong>Fee Charged:</strong> <span id="modalDetailsFee" class="fw-bold text-primary">₹0.00</span></div>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <strong>Status:</strong>
                        <span id="modalDetailsStatus" class="badge">PENDING</span>
                        <span id="modalDetailsStepperDots"></span>
                    </div>
                </div>
                <!-- 4-Step Stepper Progress Bar -->
                <div id="modalDetailsStepperContainer" class="mb-3"></div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm table-striped mb-0 small">
                        <tbody id="modalDetailsTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2.5 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex gap-2 flex-wrap" id="modalDetailsActionButtons">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-2 shadow-2xs" id="btnModalPrintReceipt">
                        <i class="bi bi-printer me-1"></i> Print Receipt
                    </button>
                    <a href="#" class="btn btn-sm btn-outline-secondary rounded-2 shadow-2xs" id="btnModalCopyEntry">
                        <i class="bi bi-files me-1"></i> Copy to New
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-2 shadow-2xs" id="btnModalChat">
                        <i class="bi bi-chat-dots-fill me-1"></i> Operator Chat
                    </button>
                </div>
                <button type="button" class="btn btn-secondary btn-sm rounded-2 px-3 shadow-2xs" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. PRINT RECEIPT MODAL -->
<div class="modal fade" id="modalPrintReceipt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom py-2.5 px-4 no-print">
                <h5 class="modal-title h6 fw-bold mb-0">Customer Acknowledgement Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="printReceiptArea">
                <div class="text-center border-bottom pb-2 mb-3">
                    <h5 class="fw-black mb-0">WELCOME BIHAR SERVICES</h5>
                    <div class="small text-muted">Bihar District & Block Citizen Service Portal</div>
                    <div class="badge bg-light text-dark border mt-1">CUSTOMER ACKNOWLEDGEMENT SLIP</div>
                </div>
                <div class="small space-y-2 mb-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Receipt / Ref #:</span>
                        <strong class="font-monospace" id="receiptRefNo">WB-2026-XXXXX</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Date & Time:</span>
                        <span id="receiptDate">--</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Applicant Name:</span>
                        <strong id="receiptApplicant">--</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Application Number:</span>
                        <span id="receiptAppCodeNo" class="font-monospace fw-bold">--</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">District & Block:</span>
                        <span id="receiptLocation">--</span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2">
                        <span class="fw-bold">Amount Paid:</span>
                        <strong class="fs-6 text-primary" id="receiptAmount">₹0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Status:</span>
                        <span id="receiptStatus" class="fw-bold">COMPLETED</span>
                    </div>
                </div>
                <div class="alert alert-secondary py-1.5 px-2 text-center small mb-0" style="font-size: 0.68rem;">
                    Electronically generated receipt from Welcome Bihar Portal. No physical signature required.
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-4 no-print">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 3. OPERATOR CHAT MODAL -->
<div class="modal fade" id="modalOperatorChat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom py-2.5 px-4 bg-primary text-white rounded-top-4">
                <h5 class="modal-title h6 fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span>Operator Helpdesk Chat (#<span id="chatModalReqNum"></span>)</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="chatMessagesWindow" class="chat-window mb-3"></div>
                <form id="chatSendForm" class="d-flex gap-2">
                    <input type="hidden" id="chatApplicationId" value="">
                    <input type="text" id="chatInputMessage" class="form-control form-control-sm rounded-pill" placeholder="Type a message to operator..." required autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 4. RESULT DOCUMENT PREVIEW MODAL (LIGHTBOX) -->
<div class="modal fade" id="modalResultLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
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
                <!-- dynamic content: iframe or img -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Dynamic District -> Block cascade
    const distSelect = document.getElementById('filterDistrict');
    const blkSelect = document.getElementById('filterBlock');
    if (distSelect && blkSelect) {
        distSelect.addEventListener('change', function () {
            const distId = this.value;
            if (!distId) {
                blkSelect.innerHTML = '<option value="">All Blocks</option>';
                blkSelect.disabled = true;
                document.getElementById('filterForm').submit();
                return;
            }
            blkSelect.innerHTML = '<option value="">Loading blocks...</option>';
            blkSelect.disabled = true;
            fetch('/api/blocks?district_id=' + encodeURIComponent(distId))
                .then(res => res.json())
                .then(data => {
                    blkSelect.innerHTML = '<option value="">All Blocks</option>';
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.id;
                            opt.textContent = b.name;
                            blkSelect.appendChild(opt);
                        });
                        blkSelect.disabled = false;
                    }
                    document.getElementById('filterForm').submit();
                })
                .catch(() => {
                    blkSelect.innerHTML = '<option value="">All Blocks</option>';
                    blkSelect.disabled = false;
                    document.getElementById('filterForm').submit();
                });
        });
    }

    // Auto-submit dropdown filters on change
    ['filterBlock', 'filterStatusSelect', 'filterCode', 'filterDate'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => {
                document.getElementById('filterPageInput').value = '1';
                document.getElementById('filterForm').submit();
            });
        }
    });

    // Details Modal Handler
    window.openDetailsModal = function (appId) {
        const row = document.getElementById('app-row-' + appId);
        if (!row) return;
        const app = JSON.parse(row.getAttribute('data-app'));
        const f = app.formData || {};

        document.getElementById('modalDetailsReqNum').textContent = app.app_ref || app.id;
        document.getElementById('modalDetailsFee').textContent = '₹' + Number(app.fee_debited || 0).toFixed(2);
        
        const statusBadgeMap = {
            'PENDING': 'bg-warning text-dark',
            'IN_PROCESS': 'bg-primary text-white',
            'UNDER_REVIEW': 'bg-info text-dark',
            'APPROVED': 'bg-success text-white',
            'COMPLETED': 'bg-success text-white',
            'REJECTED': 'bg-danger text-white'
        };
        const statusEl = document.getElementById('modalDetailsStatus');
        if (statusEl) {
            statusEl.className = 'badge px-2.5 py-1 ' + (statusBadgeMap[app.status] || 'bg-secondary text-white');
            statusEl.textContent = app.status === 'IN_PROCESS' ? 'IN PROCESS' : app.status;
        }

        // Render 4-step progress dots & stepper inside modal
        if (typeof window.renderStepperHtml === 'function') {
            const stepper = window.renderStepperHtml(app);
            const dotsEl = document.getElementById('modalDetailsStepperDots');
            if (dotsEl) dotsEl.innerHTML = stepper.dotsHtml;
            const contEl = document.getElementById('modalDetailsStepperContainer');
            if (contEl) contEl.innerHTML = stepper.cardHtml;
        }

        const tbody = document.getElementById('modalDetailsTableBody');
        tbody.innerHTML = '';

        // Wire modal action buttons
        const printBtn = document.getElementById('btnModalPrintReceipt');
        if (printBtn) {
            printBtn.onclick = function () {
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalAppDetails'));
                if (modal) modal.hide();
                openReceiptModal(app.id);
            };
        }

        const copyBtn = document.getElementById('btnModalCopyEntry');
        if (copyBtn) {
            copyBtn.href = '/applications/new?copy_id=' + app.id;
        }

        const chatBtn = document.getElementById('btnModalChat');
        if (chatBtn) {
            if (Number(app.enable_chat || 1) === 1) {
                chatBtn.style.display = 'inline-flex';
                chatBtn.onclick = function () {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalAppDetails'));
                    if (modal) modal.hide();
                    openChatModal(app.id);
                };
            } else {
                chatBtn.style.display = 'none';
            }
        }

        // Standard details
        addDetailRow(tbody, 'APPLICATION REFERENCE', '#' + app.app_ref);
        if (app.batch_ref) addDetailRow(tbody, 'BATCH REFERENCE', app.batch_ref);
        addDetailRow(tbody, 'APPLICATION NUMBER', `
            <div class="d-inline-flex align-items-center gap-1 font-monospace fw-bold">
                <span>${app.applicant_identifier || '--'}</span>
                ${app.applicant_identifier ? `<button type="button" class="btn btn-xs btn-link p-0 text-muted ms-1" onclick="copyText('${app.applicant_identifier}', this)"><i class="bi bi-clipboard"></i> Copy</button>` : ''}
            </div>
        `);
        if (f.application_code) addDetailRow(tbody, 'SERVICE / APP CODE', `<span class="badge bg-light text-primary border font-monospace fw-bold">${f.application_code}</span>`);
        addDetailRow(tbody, 'APPLICANT NAME', app.applicant_name);
        if (app.applicant_mobile) addDetailRow(tbody, 'MOBILE NUMBER', `<i class="bi bi-telephone me-1 text-muted"></i>` + app.applicant_mobile);
        addDetailRow(tbody, 'DISTRICT & BLOCK', (app.district_name || '--') + ' > ' + (app.block_name || '--'));
        if (app.status === 'REJECTED' && app.rejection_reason) {
            addDetailRow(tbody, 'REJECTION REASON', `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>${escapeHtml(app.rejection_reason)}</span>`);
        }
        if (app.admin_remarks || app.remarks) addDetailRow(tbody, 'REMARKS / NOTE', app.admin_remarks || app.remarks);
        if (app.result_file) {
            const resFileUrl = (app.result_file.startsWith('http://') || app.result_file.startsWith('https://') || app.result_file.startsWith('/'))
                ? app.result_file
                : '/' + app.result_file;
            addDetailRow(tbody, 'CERTIFICATE (PDF)', `<div class="d-flex gap-2 align-items-center">
                <a href="${resFileUrl}" target="_blank" download class="btn btn-sm btn-success py-1 px-3 rounded-2 fw-bold shadow-sm"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Download Certificate (PDF)</a>
                <button type="button" class="btn btn-sm btn-outline-success py-1 px-2.5 rounded-2 fw-bold" onclick="viewResultFile('${resFileUrl}', '${app.app_ref || app.id}')"><i class="bi bi-eye me-1"></i> Preview</button>
            </div>`);
        } else if (app.status === 'IN_PROCESS') {
            addDetailRow(tbody, 'RTPS QUEUE STATUS', `<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1"><i class="bi bi-clock-history me-1"></i> Queued for automatic nightly check (Every night at 12:00 AM Asia/Kolkata)</span>`);
        }

        // Extra dynamic fields
        Object.keys(f).forEach(key => {
            if (['applicant_name', 'application_number', 'district_name', 'block_name', 'mobile', 'application_code'].includes(key)) return;
            addDetailRow(tbody, key.replace(/_/g, ' ').toUpperCase(), f[key]);
        });

        new bootstrap.Modal(document.getElementById('modalAppDetails')).show();
    };

    function addDetailRow(tbody, label, val) {
        const tr = document.createElement('tr');
        const th = document.createElement('th');
        th.style.width = '35%';
        th.className = 'text-secondary text-uppercase fw-semibold';
        th.style.fontSize = '0.78rem';
        th.textContent = label;
        const td = document.createElement('td');
        td.style.fontSize = '0.85rem';

        const strVal = String(val ?? '').trim();
        if (!strVal || strVal === '--' || strVal === 'null' || strVal === 'undefined') {
            td.innerHTML = '<span class="text-muted">--</span>';
        } else if (strVal.startsWith('/uploads/') || strVal.startsWith('uploads/') || strVal.endsWith('.pdf') || strVal.match(/\.(jpg|jpeg|png)$/i)) {
            const fileUrl = (strVal.startsWith('http://') || strVal.startsWith('https://') || strVal.startsWith('/')) ? strVal : ('/' + strVal);
            td.innerHTML = `<a href="${fileUrl}" target="_blank" class="btn btn-xs btn-outline-primary py-0.5 px-2 rounded-2 fw-semibold"><i class="bi bi-file-earmark-arrow-down me-1"></i> View File</a>`;
        } else if (strVal.includes('<') && strVal.includes('>')) {
            td.innerHTML = strVal;
        } else {
            td.textContent = strVal;
        }

        tr.appendChild(th);
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    // Receipt Modal
    window.openReceiptModal = function (appId) {
        const row = document.getElementById('app-row-' + appId);
        if (!row) return;
        const app = JSON.parse(row.getAttribute('data-app'));

        document.getElementById('receiptRefNo').textContent = app.app_ref || ('REQ-' + app.id);
        document.getElementById('receiptDate').textContent = new Date(app.created_at).toLocaleString('en-IN');
        document.getElementById('receiptApplicant').textContent = app.applicant_name || 'Applicant';
        document.getElementById('receiptAppCodeNo').textContent = app.applicant_identifier || 'N/A';
        document.getElementById('receiptLocation').textContent = (app.district_name || 'District') + ' / ' + (app.block_name || 'Block');
        document.getElementById('receiptAmount').textContent = '₹' + Number(app.fee_debited || 0).toFixed(2);
        document.getElementById('receiptStatus').textContent = app.status;

        new bootstrap.Modal(document.getElementById('modalPrintReceipt')).show();
    };

    // Chat Modal
    let activeChatPoll = null;
    window.openChatModal = function (appId) {
        document.getElementById('chatModalReqNum').textContent = appId;
        document.getElementById('chatApplicationId').value = appId;
        const win = document.getElementById('chatMessagesWindow');
        win.innerHTML = '<div class="text-center text-muted small py-3"><div class="spinner-border spinner-border-sm me-1"></div> Loading messages...</div>';

        fetchChat(appId);
        if (activeChatPoll) clearInterval(activeChatPoll);
        activeChatPoll = setInterval(() => fetchChat(appId), 4000);

        new bootstrap.Modal(document.getElementById('modalOperatorChat')).show();
    };

    document.getElementById('modalOperatorChat').addEventListener('hidden.bs.modal', function () {
        if (activeChatPoll) {
            clearInterval(activeChatPoll);
            activeChatPoll = null;
        }
    });

    document.getElementById('chatSendForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const appId = document.getElementById('chatApplicationId').value;
        const msgInput = document.getElementById('chatInputMessage');
        const text = msgInput.value.trim();
        if (!text) return;

        msgInput.value = '';
        fetch('/api/requests/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ application_id: appId, message: text })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) showToast('danger', data.error);
            fetchChat(appId);
        })
        .catch(err => console.error(err));
    });

    function fetchChat(appId) {
        fetch('/api/requests/chat?application_id=' + encodeURIComponent(appId))
            .then(res => res.json())
            .then(data => {
                const win = document.getElementById('chatMessagesWindow');
                if (data.chat_disabled) {
                    win.innerHTML = '<div class="alert alert-warning small py-2 mb-0">Chat is disabled for this service.</div>';
                    document.getElementById('chatSendForm').style.display = 'none';
                    return;
                }
                const messages = data.messages || [];
                if (messages.length === 0) {
                    win.innerHTML = '<div class="text-center text-muted small py-4">No messages yet. Send a message to start conversation with the operator/admin.</div>';
                    return;
                }
                win.innerHTML = '';
                messages.forEach(m => {
                    const isSelf = (m.sender_role === 'USER' || m.sender_role === 'RETAILER');
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

    // Lightbox Result Modal
    window.viewResultFile = function (url, ref) {
        if (!url) return;
        const modalEl = document.getElementById('modalResultLightbox');
        if (modalEl && typeof bootstrap !== 'undefined') {
            document.getElementById('resultLightboxTitle').textContent = 'Result Document - ' + (ref || '');
            document.getElementById('resultDownloadDirect').href = url;
            const body = document.getElementById('resultLightboxBody');
            const cleanUrl = url.split('?')[0].toLowerCase();
            if (cleanUrl.endsWith('.pdf')) {
                body.innerHTML = `<iframe src="${url}#toolbar=1" class="w-100 border-0 rounded-bottom-4" style="height: 75vh;"></iframe>`;
            } else {
                body.innerHTML = `<div class="text-center p-4"><img src="${url}" class="img-fluid rounded shadow-sm" style="max-height: 75vh; object-fit: contain;"></div>`;
            }
            new bootstrap.Modal(modalEl).show();
        } else {
            window.open(url, '_blank');
        }
    };

    window.copyText = function (text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check text-success"></i>';
            setTimeout(() => { btn.innerHTML = orig; }, 1500);
        });
    };

    function showToast(type, msg) {
        const c = document.getElementById('toast-container');
        if (!c) return;
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
