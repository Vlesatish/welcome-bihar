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
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold shadow-2xs" onclick="window.location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
            <a href="/applications/export-csv<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold shadow-2xs">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
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
            function buildTabUrl($tab) {
                $q = $_GET;
                $q['status'] = $tab;
                $q['page'] = 1;
                return '/admin/applications?' . http_build_query($q);
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
                <?php if ($isOperator): ?>
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
                            <input type="text" class="form-control bg-light filter-ctrl fw-semibold text-secondary" style="border-radius: 0 6px 6px 0 !important;" value="<?= e($blocks[0]['name'] ?? 'Assigned') ?>" readonly disabled title="Locked to assigned block">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="filter-col filter-col-district">
                        <label for="filter_district_id" class="filter-label">District</label>
                        <select name="district_id" id="filter_district_id" class="form-select form-select-sm filter-select filter-ctrl w-100">
                            <option value="">All Districts</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-col filter-col-block">
                        <label for="filter_block_id" class="filter-label">Block</label>
                        <select name="block_id" id="filter_block_id" class="form-select form-select-sm filter-select filter-ctrl w-100" <?= empty($blocks) && empty($districtId) ? 'disabled' : '' ?>>
                            <option value="">All Blocks</option>
                            <?php foreach ($blocks as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $blockId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

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
                                <th class="py-2.5">Applicant</th>
                                <th class="py-2.5">Retailer</th>
                                <th class="py-2.5">District/Block</th>
                                <th class="py-2.5">Fee</th>
                                <th class="py-2.5">Status</th>
                                <th class="text-end pe-3 py-2.5" style="min-width: 210px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <?php
                                    $status = strtoupper($app['status'] ?? 'PENDING');
                                    $appNum = $app['applicant_identifier'] ?: '#' . $app['app_ref'];
                                    $appJson = htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8');
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

                                    <!-- 5. Fee -->
                                    <td class="fw-bold text-dark font-monospace">
                                        ₹<?= number_format($app['fee_debited'], 2) ?>
                                    </td>

                                    <!-- 6. Status (Pending amber, In Process blue, Approved green, Rejected red) -->
                                    <td>
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
                                                <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2.5 py-1 shadow-2xs" onclick="quickUpdateStatus(<?= $app['id'] ?>, 'APPROVED', this)" title="Approve Application">
                                                    <i class="bi bi-check-circle-fill me-0.5"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadApproveModal(<?= $app['id'] ?>)" title="Upload Certificate & Approve">
                                                    <i class="bi bi-cloud-arrow-up"></i>
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

<!-- =========================================================================
     MODAL 1: COMPLETE APPLICATION DETAILS (Matching Operator View + Full Admin Controls)
     ========================================================================= -->
<div class="modal fade" id="modalAdminAppDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom p-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-folder-check text-primary fs-5"></i>
                    <div>
                        <h5 class="h6 fw-bold text-dark mb-0" id="detailModalTitle">Application Details</h5>
                        <span class="text-muted small" id="detailModalSubtitle">#WB-...</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Status & Action Banner -->
                <div class="p-2.5 rounded-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border bg-light" id="detailStatusBanner">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small fw-semibold">Current Status:</span>
                        <span class="badge px-2.5 py-1 rounded-pill fw-bold" id="detailStatusBadge">--</span>
                    </div>
                    <div id="detailActionButtons" class="d-flex gap-1.5"></div>
                </div>

                <!-- Rejection Banner if rejected -->
                <div class="alert alert-danger p-2.5 rounded-3 mb-3 small d-none" id="detailRejectionAlert">
                    <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                    <strong>Rejection Reason:</strong> <span id="detailRejectionReasonText">--</span>
                    <div class="mt-1 text-danger fw-semibold" id="detailRefundInfoText"></div>
                </div>

                <div class="row g-3 mb-3">
                    <!-- Applicant Info -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <h6 class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-person text-primary me-1"></i> Applicant Information</h6>
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr>
                                    <td class="text-muted ps-0" style="width: 130px;">Applicant Name:</td>
                                    <td class="fw-bold text-dark" id="detailApplicantName">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Mobile Number:</td>
                                    <td class="fw-bold text-dark" id="detailApplicantMobile">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Application No:</td>
                                    <td class="font-monospace fw-bold text-primary" id="detailAppNumber">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Application Code:</td>
                                    <td><span class="badge bg-primary-subtle text-primary border" id="detailAppCode">--</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Location & Retailer Info -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <h6 class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-geo-alt text-primary me-1"></i> Location & Retailer</h6>
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr>
                                    <td class="text-muted ps-0" style="width: 120px;">District:</td>
                                    <td class="fw-bold text-dark" id="detailDistrict">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Block:</td>
                                    <td class="fw-bold text-dark" id="detailBlock">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Submitted By:</td>
                                    <td class="fw-bold text-dark" id="detailRetailer">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Service Fee:</td>
                                    <td class="fw-bold text-success font-monospace" id="detailFee">--</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Attached Citizen Documents & Extra Fields -->
                <div class="mb-3">
                    <h6 class="small fw-bold text-dark text-uppercase border-bottom pb-1 mb-2">
                        <i class="bi bi-paperclip text-primary me-1"></i> Submitted Fields & Documents
                    </h6>
                    <div id="detailFieldsContainer" class="p-2 bg-light rounded-3 border small">
                        <!-- Dynamic fields list -->
                    </div>
                </div>

                <!-- Result Certificate Attachment -->
                <div class="mb-3 d-none" id="detailResultContainer">
                    <h6 class="small fw-bold text-dark text-uppercase border-bottom pb-1 mb-2">
                        <i class="bi bi-file-earmark-check text-success me-1"></i> Approved Certificate / Result Document
                    </h6>
                    <div class="p-2.5 bg-success-subtle border border-success-subtle rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-4"></i>
                            <div>
                                <strong class="small text-dark d-block" id="detailResultFileName">Certificate.pdf</strong>
                                <span class="text-muted" style="font-size: 0.72rem;" id="detailResultRemarks">Official output delivered</span>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-xs btn-outline-success py-1 px-2.5 rounded-pill fw-bold" id="btnPreviewResultModal">
                                <i class="bi bi-eye me-1"></i> Preview
                            </button>
                            <a href="#" download id="btnDownloadResultModal" class="btn btn-xs btn-success py-1 px-3 rounded-pill fw-bold">
                                <i class="bi bi-download me-1"></i> Download
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Status History Timeline -->
                <div>
                    <h6 class="small fw-bold text-dark text-uppercase border-bottom pb-1 mb-2">
                        <i class="bi bi-clock-history text-primary me-1"></i> Status History Trail
                    </h6>
                    <div id="detailHistoryContainer" class="p-2 bg-light rounded-3 border small" style="max-height: 160px; overflow-y: auto;">
                        <!-- Status history items -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-2.5 d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2" id="detailFooterActions">
                    <!-- Dynamic footer actions -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: UPLOAD & APPROVE (Optional File Upload Flow)
     ========================================================================= -->
<div class="modal fade" id="modalUploadApprove" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-4 border-0 shadow-lg p-3">
            <div class="modal-header border-bottom pb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-arrow-up-fill text-success fs-5"></i>
                    <h5 class="h6 fw-bold text-dark mb-0">Upload Result & Approve Application</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUploadApprove" enctype="multipart/form-data">
                <input type="hidden" name="application_id" id="upload_app_id" value="">
                <input type="hidden" name="status" value="APPROVED">

                <div class="modal-body py-3">
                    <div class="alert alert-light border small mb-3 p-2">
                        <div>Applicant: <strong id="uploadApplicantName">--</strong></div>
                        <div>Application No: <span class="font-monospace fw-bold text-primary" id="uploadAppNumber">--</span></div>
                        <div class="text-muted" style="font-size: 0.72rem;" id="uploadLocation">--</div>
                    </div>

                    <div class="mb-3">
                        <label for="uploadResultFile" class="form-label small fw-bold text-dark">Result Certificate Document (PDF / JPG / PNG)</label>
                        <input type="file" class="form-control form-control-sm" id="uploadResultFile" name="result_file" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text" style="font-size: 0.7rem;">Upload official certificate generated from RTPS/Government Portal (Max 15MB).</div>
                    </div>

                    <!-- File preview card -->
                    <div id="uploadFilePreviewBox" class="p-2 border rounded-3 bg-light mb-3 d-none d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-pdf text-danger fs-4" id="uploadPreviewIcon"></i>
                        <div class="flex-grow-1 overflow-hidden">
                            <strong class="d-block text-truncate small" id="uploadPreviewName">filename.pdf</strong>
                            <span class="text-muted" style="font-size: 0.7rem;" id="uploadPreviewMeta">0 KB</span>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="uploadRemarks" class="form-label small fw-bold text-dark">Admin Remarks (Optional)</label>
                        <input type="text" class="form-control form-control-sm" id="uploadRemarks" name="admin_remarks" placeholder="e.g. Certificate issued successfully">
                    </div>
                </div>

                <div class="modal-footer border-top pt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-4 fw-bold" id="btnSubmitUploadApprove">
                        <i class="bi bi-check-circle-fill me-1"></i> Save & Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: REJECT APPLICATION (Quick & Reliable)
     ========================================================================= -->
<div class="modal fade" id="modalRejectApp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 shadow-lg p-2">
            <div class="modal-header border-bottom pb-2">
                <div class="d-flex align-items-center gap-1.5 text-danger">
                    <i class="bi bi-x-circle-fill fs-5"></i>
                    <h5 class="h6 fw-bold mb-0">Reject Application</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRejectApp">
                <input type="hidden" name="application_id" id="reject_app_id" value="">
                <input type="hidden" name="status" value="REJECTED">

                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">Select or enter rejection reason. Full fee will be automatically refunded to retailer wallet.</p>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1">Common Reasons</label>
                        <select class="form-select form-select-sm" id="rejectReasonPreset" onchange="if(this.value) document.getElementById('rejectReasonText').value = this.value;">
                            <option value="">-- Choose Reason Preset --</option>
                            <option value="Application Number not found on RTPS portal">Application Number not found on RTPS portal</option>
                            <option value="Applicant details / Name mismatch">Applicant details / Name mismatch</option>
                            <option value="Attached documents are blurry or incomplete">Attached documents are blurry or incomplete</option>
                            <option value="Duplicate submission already processed">Duplicate submission already processed</option>
                            <option value="Application already rejected at Government Block level">Application already rejected at Government Block level</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="rejectReasonText" class="form-label small fw-bold text-dark mb-1">Specific Reason *</label>
                        <textarea class="form-control form-control-sm" id="rejectReasonText" name="rejection_reason" rows="2" placeholder="Explain rejection reason..." required>Application details mismatch or incomplete documentation.</textarea>
                    </div>
                </div>

                <div class="modal-footer border-top pt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold" id="btnConfirmReject">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: RESULT DOCUMENT LIGHTBOX PREVIEW
     ========================================================================= -->
<div class="modal fade" id="modalResultLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white p-3 border-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check-fill text-success fs-5"></i>
                    <h5 class="h6 fw-bold mb-0 text-white" id="lightboxTitle">Result Document</h5>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <a href="#" download id="lightboxDownloadBtn" class="btn btn-xs btn-outline-light rounded-pill px-3 fw-bold">
                        <i class="bi bi-download me-1"></i> Download File
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary bg-opacity-10 text-center" id="lightboxBody" style="min-height: 500px;">
                <!-- Iframe or Image dynamically placed -->
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT: ONE-CLICK STATUS UPDATES & MODALS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalDetails = new bootstrap.Modal(document.getElementById('modalAdminAppDetails'));
    const modalUpload = new bootstrap.Modal(document.getElementById('modalUploadApprove'));
    const modalReject = new bootstrap.Modal(document.getElementById('modalRejectApp'));
    const modalLightbox = new bootstrap.Modal(document.getElementById('modalResultLightbox'));

    // Dynamic Block Cascading in Filter Form
    const distSelect = document.getElementById('filter_district_id');
    const blkSelect = document.getElementById('filter_block_id');
    if (distSelect && blkSelect) {
        distSelect.addEventListener('change', function () {
            const distId = this.value;
            if (!distId) {
                blkSelect.innerHTML = '<option value="">All Blocks</option>';
                blkSelect.disabled = true;
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
                    } else {
                        blkSelect.disabled = false;
                    }
                })
                .catch(() => {
                    blkSelect.innerHTML = '<option value="">All Blocks</option>';
                    blkSelect.disabled = false;
                });
        });
    }

    // 1. ONE-CLICK INSTANT STATUS UPDATE
    window.quickUpdateStatus = function (appId, targetStatus, btn = null) {
        let origHtml = '';
        if (btn) {
            origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';
        }

        fetch('/api/requests/update-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                application_id: appId,
                status: targetStatus,
                adminRemarks: targetStatus === 'APPROVED' ? 'Approved by administrator' : 'Processing started by administrator'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const readableTarget = targetStatus === 'APPROVED' ? 'Approved' : (targetStatus === 'IN_PROCESS' ? 'In Process' : targetStatus);
                showToast('success', `Application #${appId} marked as ${readableTarget}!`);
                setTimeout(() => window.location.reload(), 400);
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
            showToast('danger', 'Network error while updating status.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        });
    };

    // 2. OPEN REJECT MODAL
    window.openRejectModal = function (appId) {
        document.getElementById('reject_app_id').value = appId;
        modalReject.show();
    };

    // Reject Form Submit
    document.getElementById('formRejectApp').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnConfirmReject');
        const appId = document.getElementById('reject_app_id').value;
        const reason = document.getElementById('rejectReasonText').value.trim();

        if (!reason) {
            showToast('danger', 'Rejection reason is required.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';

        fetch('/api/requests/update-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                application_id: appId,
                status: 'REJECTED',
                rejectionReason: reason,
                adminRemarks: reason
            })
        })
        .then(res => res.json())
        .then(data => {
            modalReject.hide();
            if (data.success) {
                showToast('success', 'Application marked as REJECTED & fee refunded.');
                setTimeout(() => window.location.reload(), 450);
            } else {
                showToast('danger', data.error || 'Failed to reject application.');
                btn.disabled = false;
                btn.textContent = 'Confirm Rejection';
            }
        })
        .catch(() => {
            showToast('danger', 'Network error.');
            btn.disabled = false;
            btn.textContent = 'Confirm Rejection';
        });
    });

    // 3. OPEN UPLOAD & APPROVE MODAL
    window.openUploadApproveModal = function (appId) {
        const row = document.getElementById('row-app-' + appId);
        if (!row) return;
        const app = JSON.parse(row.dataset.app);

        document.getElementById('upload_app_id').value = appId;
        document.getElementById('uploadApplicantName').textContent = app.applicant_name || '--';
        document.getElementById('uploadAppNumber').textContent = app.applicant_identifier || '#' + app.app_ref;
        document.getElementById('uploadLocation').textContent = `${app.district_name} > ${app.block_name}`;
        document.getElementById('uploadResultFile').value = '';
        document.getElementById('uploadFilePreviewBox').classList.add('d-none');

        modalUpload.show();
    };

    // File Preview Handler in Upload Modal
    const uploadInput = document.getElementById('uploadResultFile');
    if (uploadInput) {
        uploadInput.addEventListener('change', function () {
            const file = this.files[0];
            const pBox = document.getElementById('uploadFilePreviewBox');
            if (file) {
                document.getElementById('uploadPreviewName').textContent = file.name;
                document.getElementById('uploadPreviewMeta').textContent = `${(file.size / 1024).toFixed(1)} KB • ${file.type || 'file'}`;
                pBox.classList.remove('d-none');
            } else {
                pBox.classList.add('d-none');
            }
        });
    }

    // Submit Upload & Approve Form
    document.getElementById('formUploadApprove').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitUploadApprove');
        const formData = new FormData(this);

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading & Approving...';

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            modalUpload.hide();
            if (data.success) {
                showToast('success', 'Application approved and certificate saved!');
                setTimeout(() => window.location.reload(), 450);
            } else {
                showToast('danger', data.error || 'Failed to approve application.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Save & Approve';
            }
        })
        .catch(() => {
            showToast('danger', 'Network error during upload.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Save & Approve';
        });
    });

    // 4. OPEN FULL DETAILS MODAL (Matching Operator View + Full Admin Controls)
    window.openDetailsModal = function (appId) {
        const row = document.getElementById('row-app-' + appId);
        if (!row) return;
        const app = JSON.parse(row.dataset.app);

        document.getElementById('detailModalTitle').textContent = `Application #${app.app_ref}`;
        document.getElementById('detailModalSubtitle').textContent = app.applicant_identifier ? `${app.applicant_identifier} • Ref: #${app.app_ref}` : `#${app.app_ref}`;

        document.getElementById('detailApplicantName').textContent = app.applicant_name || '--';
        document.getElementById('detailApplicantMobile').textContent = app.applicant_mobile || '--';
        document.getElementById('detailAppNumber').textContent = app.applicant_identifier || '--';
        document.getElementById('detailAppCode').textContent = app.application_code || (app.applicant_identifier ? app.applicant_identifier.split('/')[0] : '--');

        document.getElementById('detailDistrict').textContent = app.district_name || '--';
        document.getElementById('detailBlock').textContent = app.block_name || '--';
        document.getElementById('detailRetailer').textContent = app.retailer_name ? `${app.retailer_name} (${app.retailer_mobile || ''})` : '--';
        document.getElementById('detailFee').textContent = '₹' + Number(app.fee_debited || 0).toFixed(2);

        // Status badge
        const badgeEl = document.getElementById('detailStatusBadge');
        badgeEl.textContent = app.status;
        if (app.status === 'PENDING') {
            badgeEl.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill fw-bold';
        } else if (app.status === 'IN_PROCESS' || app.status === 'UNDER_REVIEW') {
            badgeEl.className = 'badge bg-info-subtle text-info-emphasis border border-info-subtle px-2.5 py-1 rounded-pill fw-bold';
        } else if (app.status === 'APPROVED' || app.status === 'COMPLETED') {
            badgeEl.className = 'badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-bold';
        } else if (app.status === 'REJECTED') {
            badgeEl.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill fw-bold';
        }

        // Rejection banner
        const rejAlert = document.getElementById('detailRejectionAlert');
        if (app.status === 'REJECTED') {
            document.getElementById('detailRejectionReasonText').textContent = app.rejection_reason || app.admin_remarks || 'Rejected by administrator';
            document.getElementById('detailRefundInfoText').textContent = app.is_refunded ? `✓ Full fee of ₹${Number(app.fee_debited).toFixed(2)} refunded to retailer.` : 'Refund recorded.';
            rejAlert.classList.remove('d-none');
        } else {
            rejAlert.classList.add('d-none');
        }

        // Action Buttons inside banner
        const actionBox = document.getElementById('detailActionButtons');
        actionBox.innerHTML = '';
        if (app.status === 'PENDING') {
            actionBox.innerHTML = `
                <button type="button" class="btn btn-xs btn-primary py-1 px-3 rounded-pill fw-bold shadow-2xs" onclick="modalDetails.hide(); quickUpdateStatus(${app.id}, 'IN_PROCESS')">
                    <i class="bi bi-lightning-fill me-1"></i> 1-Click Process
                </button>
                <button type="button" class="btn btn-xs btn-success py-1 px-3 rounded-pill fw-bold shadow-2xs" onclick="modalDetails.hide(); quickUpdateStatus(${app.id}, 'APPROVED')">
                    <i class="bi bi-check-circle-fill me-1"></i> 1-Click Approve
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2.5 rounded-pill fw-semibold" onclick="modalDetails.hide(); openRejectModal(${app.id})">
                    <i class="bi bi-x-circle me-1"></i> Reject
                </button>
            `;
        } else if (app.status === 'IN_PROCESS' || app.status === 'UNDER_REVIEW') {
            actionBox.innerHTML = `
                <button type="button" class="btn btn-xs btn-success py-1 px-3 rounded-pill fw-bold shadow-2xs" onclick="modalDetails.hide(); quickUpdateStatus(${app.id}, 'APPROVED')">
                    <i class="bi bi-check-circle-fill me-1"></i> 1-Click Approve
                </button>
                <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2.5 rounded-pill fw-semibold" onclick="modalDetails.hide(); openUploadApproveModal(${app.id})">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2.5 rounded-pill fw-semibold" onclick="modalDetails.hide(); openRejectModal(${app.id})">
                    <i class="bi bi-x-circle me-1"></i> Reject
                </button>
            `;
        }

        // Dynamic Submitted Fields
        const fBox = document.getElementById('detailFieldsContainer');
        if (app.fields && Object.keys(app.fields).length > 0) {
            let html = '<div class="row g-2">';
            Object.entries(app.fields).forEach(([k, f]) => {
                const isUrl = String(f.value).startsWith('/uploads/') || String(f.value).startsWith('http');
                html += `
                    <div class="col-6 col-md-4">
                        <span class="text-muted d-block" style="font-size: 0.72rem;">${escapeHtml(f.label || k)}:</span>
                        ${isUrl
                            ? `<a href="${escapeHtml(f.value)}" target="_blank" class="fw-bold text-primary text-truncate d-inline-block" style="max-width: 100%;"><i class="bi bi-file-earmark me-1"></i>View Document</a>`
                            : `<strong class="text-dark text-truncate d-block">${escapeHtml(f.value)}</strong>`
                        }
                    </div>
                `;
            });
            html += '</div>';
            fBox.innerHTML = html;
        } else {
            fBox.innerHTML = '<span class="text-muted">Standard form fields recorded.</span>';
        }

        // Result Container
        const resContainer = document.getElementById('detailResultContainer');
        if (app.result_file) {
            document.getElementById('detailResultFileName').textContent = app.result_file.split('/').pop();
            document.getElementById('detailResultRemarks').textContent = app.admin_remarks || 'Official pass certificate delivered';
            document.getElementById('btnDownloadResultModal').href = app.result_file;
            document.getElementById('btnPreviewResultModal').onclick = () => openResultLightbox(app.result_file);
            resContainer.classList.remove('d-none');
        } else {
            resContainer.classList.add('d-none');
        }

        // History Trail
        const histBox = document.getElementById('detailHistoryContainer');
        if (app.history && app.history.length > 0) {
            histBox.innerHTML = app.history.map(h => `
                <div class="border-bottom py-1 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.65rem;">${h.from_status} &rarr; ${h.to_status}</span>
                        <span class="ms-1 fw-bold text-dark">${escapeHtml(h.actor_name)} (${h.actor_role})</span>
                        ${h.remarks ? `<span class="text-muted ms-1">&bull; ${escapeHtml(h.remarks)}</span>` : ''}
                        ${h.rejection_reason ? `<span class="text-danger ms-1 fw-semibold">&bull; Reason: ${escapeHtml(h.rejection_reason)}</span>` : ''}
                    </div>
                    <span class="text-muted font-monospace" style="font-size: 0.68rem;">${h.created_at}</span>
                </div>
            `).join('');
        } else {
            histBox.innerHTML = `<span class="text-muted">Submitted on: ${app.created_at}</span>`;
        }

        modalDetails.show();
    };

    // 5. LIGHTBOX PREVIEW
    window.openResultLightbox = function (url) {
        if (!url) return;
        document.getElementById('lightboxDownloadBtn').href = url;
        const body = document.getElementById('lightboxBody');
        const cleanUrl = url.split('?')[0].toLowerCase();

        if (cleanUrl.endsWith('.pdf')) {
            body.innerHTML = `<iframe src="${url}#toolbar=1" class="w-100 border-0 rounded-bottom-4" style="height: 75vh;"></iframe>`;
        } else {
            body.innerHTML = `<div class="p-4 text-center"><img src="${url}" class="img-fluid rounded shadow-sm" style="max-height: 75vh; object-fit: contain;"></div>`;
        }

        modalLightbox.show();
    };

    // 6. COPY UTILITY
    window.copyText = function (text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check text-success" style="font-size: 0.75rem;"></i>';
            setTimeout(() => { btn.innerHTML = orig; }, 1500);
        });
    };

    // 7. TOAST NOTIFICATION HELPER
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
