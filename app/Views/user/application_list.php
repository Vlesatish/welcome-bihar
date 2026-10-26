<?php require dirname(__DIR__) . '/layouts/header.php'; ?>
<?php
    $dateFilter = trim($_GET['date_filter'] ?? $_GET['dateFilter'] ?? '');
?>

<style>
.table-app-list th {
    font-size: 0.74rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    background-color: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    padding: 0.65rem 0.75rem;
}
.table-app-list td {
    font-size: 0.82rem;
    vertical-align: middle;
    padding: 0.6rem 0.75rem;
    border-color: #f1f5f9;
}
.table-app-list tbody tr:hover {
    background-color: #f8fafc;
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
.filters-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.65rem;
    width: 100%;
}
.filter-col-district {
    flex: 1 1 145px;
    min-width: 135px;
}
.filter-col-block {
    flex: 1 1 145px;
    min-width: 135px;
}
.filter-col-code {
    flex: 0 1 115px;
    min-width: 105px;
}
.filter-col-status {
    flex: 0 1 130px;
    min-width: 120px;
}
.filter-col-date {
    flex: 0 1 120px;
    min-width: 110px;
}
.filter-col-search {
    flex: 2 1 190px;
    min-width: 160px;
}
.filter-col-btn {
    flex: 0 0 auto;
}
.filter-ctrl, .filter-select, .filter-btn {
    height: 36px !important;
    font-size: 13px !important;
    border-radius: 6px !important;
}
.filter-ctrl:focus, .filter-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}
.filter-select {
    padding: 0.25rem 1.6rem 0.25rem 0.65rem !important;
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
.chat-window {
    max-height: 340px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.5rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
}
.chat-msg {
    max-width: 80%;
    padding: 0.5rem 0.75rem;
    border-radius: 0.75rem;
    font-size: 0.82rem;
}
.chat-msg-sent {
    align-self: flex-end;
    background: #2563eb;
    color: #fff;
    border-bottom-right-radius: 0.2rem;
}
.chat-msg-recv {
    align-self: flex-start;
    background: #e2e8f0;
    color: #1e293b;
    border-bottom-left-radius: 0.2rem;
}
.chat-meta {
    font-size: 0.65rem;
    margin-top: 0.25rem;
}
</style>

<!-- TOAST CONTAINER -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

<!-- Page Title & Actions -->
<div class="row align-items-center mb-3 g-2">
    <div class="col-md-6">
        <h1 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-folder2-open text-primary"></i>
            <span>Application List</span>
            <span class="badge bg-primary-subtle text-primary border rounded-pill px-2.5 small"><?= number_format($totalRecords) ?></span>
        </h1>
        <p class="text-muted small mb-0" style="font-size: 0.75rem;">
            Search, track live progress, download receipts and output documents
        </p>
    </div>
    <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/applications/export-csv?<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-success fw-semibold rounded-pill px-3 shadow-2xs">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
        <a href="/applications/new" class="btn btn-sm btn-primary fw-bold rounded-pill px-3 shadow-2xs">
            <i class="bi bi-plus-circle me-1"></i> New Application
        </a>
    </div>
</div>

<!-- Search & Filters Card -->
<div class="card mb-3 shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
    <div class="card-body p-3">
        <!-- Card Header: Clean small Filters heading + active filter indicators -->
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2 pb-2 border-bottom">
            <div class="d-flex align-items-center flex-wrap gap-1.5">
                <i class="bi bi-funnel-fill text-primary" style="font-size: 0.82rem;"></i>
                <h2 class="h6 fw-bold mb-0 text-dark" style="font-size: 0.85rem; letter-spacing: 0.2px;">Filters</h2>
                <?php if (!empty($districtId)): ?>
                    <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">
                        District: <strong><?= e($districtId) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($blockId)): ?>
                    <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">
                        Block: <strong><?= e($blockId) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($status)): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.72rem;">
                        Status: <strong><?= e($status) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($appCode)): ?>
                    <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">
                        Code: <strong><?= e($appCode) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if ($dateFilter !== '' && $dateFilter !== 'ALL'): ?>
                    <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">
                        Date: <strong><?= e($dateFilter) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">
                        Search: <strong>"<?= e($search) ?>"</strong>
                    </span>
                <?php endif; ?>
            </div>
            <div class="small text-muted" style="font-size: 0.74rem;">
                Showing <strong><?= number_format($totalRecords) ?></strong> applications
            </div>
        </div>

        <form method="GET" action="/applications" id="appFilterForm">
            <div class="filters-grid pt-1">
                <!-- 1. District -->
                <div class="filter-col filter-col-district">
                    <label for="filter_district_id" class="filter-label">District</label>
                    <select name="district_id" id="filter_district_id" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="">All Districts</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Block -->
                <div class="filter-col filter-col-block">
                    <label for="filter_block_id" class="filter-label">Block</label>
                    <select name="block_id" id="filter_block_id" class="form-select form-select-sm filter-select filter-ctrl w-100" <?= empty($blocks) && empty($districtId) ? 'disabled' : '' ?>>
                        <option value="">All Blocks</option>
                        <?php foreach ($blocks as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $blockId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Status -->
                <div class="filter-col filter-col-status">
                    <label for="filter_status" class="filter-label">Status</label>
                    <select name="status" id="filter_status" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="">All Statuses</option>
                        <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                        <option value="IN_PROCESS" <?= in_array($status, ['IN_PROCESS', 'UNDER_REVIEW'], true) ? 'selected' : '' ?>>Processing</option>
                        <option value="COMPLETED" <?= in_array($status, ['COMPLETED', 'APPROVED'], true) ? 'selected' : '' ?>>Completed</option>
                        <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
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

                <!-- 5. Date -->
                <div class="filter-col filter-col-date">
                    <label for="filter_date" class="filter-label">Date</label>
                    <select name="date_filter" id="filter_date" class="form-select form-select-sm filter-select filter-ctrl w-100">
                        <option value="">All Dates</option>
                        <option value="TODAY" <?= $dateFilter === 'TODAY' ? 'selected' : '' ?>>Today</option>
                        <option value="WEEK" <?= $dateFilter === 'WEEK' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="MONTH" <?= $dateFilter === 'MONTH' ? 'selected' : '' ?>>Last 30 Days</option>
                    </select>
                </div>

                <!-- 6. Search Bar (Takes available space) -->
                <div class="filter-col filter-col-search">
                    <label for="filter_search" class="filter-label">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted filter-ctrl" style="border-radius: 6px 0 0 6px !important;"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" id="filter_search" value="<?= e($search) ?>" class="form-control border-start-0 ps-0 bg-white filter-ctrl" style="border-radius: 0 6px 6px 0 !important;" placeholder="Request #, applicant, ID...">
                    </div>
                </div>

                <!-- 7. Filter Button -->
                <div class="filter-col filter-col-btn">
                    <button type="submit" class="btn btn-sm btn-primary filter-btn filter-ctrl shadow-2xs">
                        <i class="bi bi-filter me-1"></i> Filter
                    </button>
                </div>

                <!-- 8. Reset Button -->
                <div class="filter-col filter-col-btn">
                    <a href="/applications" class="btn btn-sm btn-outline-danger filter-btn filter-ctrl" title="Reset all filters">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const distSelect = document.getElementById('filter_district_id');
    const blkSelect = document.getElementById('filter_block_id');
    if (distSelect && blkSelect) {
        distSelect.addEventListener('change', function () {
            const distId = this.value;
            if (!distId) {
                blkSelect.innerHTML = '<option value="">-- All Blocks --</option>';
                blkSelect.disabled = true;
                return;
            }
            blkSelect.innerHTML = '<option value="">Loading blocks...</option>';
            blkSelect.disabled = true;
            fetch('/api/blocks?district_id=' + encodeURIComponent(distId))
                .then(res => res.json())
                .then(data => {
                    blkSelect.innerHTML = '<option value="">-- All Blocks --</option>';
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
                    blkSelect.innerHTML = '<option value="">-- All Blocks --</option>';
                    blkSelect.disabled = false;
                });
        });
    }
});
</script>

<!-- Applications Table Card -->
<div class="card shadow-sm border rounded-3 bg-white mb-4" style="border: 1px solid #e2e8f0;">
    <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary"></i>
            <h2 class="h6 fw-bold mb-0 text-dark">Recent Applications</h2>
        </div>
        <span class="badge bg-light text-secondary border font-monospace"><?= number_format($totalRecords) ?> Applications</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($applications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 opacity-50 d-block mb-1"></i>
                <h5 class="h6 fw-bold text-dark mb-1">No Applications Found</h5>
                <p class="small text-muted mb-3">No matching records found for the applied search criteria.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="/applications" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Reset Filters</a>
                    <a href="/applications/new" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">New Application</a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">
                        <tr>
                            <th class="ps-3 py-2.5">Order Reference</th>
                            <th class="py-2.5">Applicant</th>
                            <th class="py-2.5">Application No.</th>
                            <th class="py-2.5">Amount</th>
                            <th class="py-2.5">Status</th>
                            <th class="py-2.5">Date</th>
                            <th class="text-end pe-3 py-2.5">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <?php
                                $status = strtoupper($app['status'] ?? 'PENDING');
                                $jsonEscaped = htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr id="app-row-<?= $app['id'] ?>" data-app='<?= $jsonEscaped ?>'>
                                <td class="ps-3 font-monospace small fw-bold text-dark">
                                    #<?= e($app['app_ref']) ?>
                                </td>

                                <td class="fw-bold text-dark">
                                    <?= e($app['applicant_name']) ?>
                                </td>

                                <td class="font-monospace small fw-semibold text-dark">
                                    <?= e($app['applicant_identifier'] ?: '—') ?>
                                </td>

                                <td class="fw-bold text-dark">
                                    ₹<?= number_format($app['fee_debited'], 2) ?>
                                </td>

                                <td>
                                    <div>
                                        <?php if ($status === 'COMPLETED' || $status === 'APPROVED'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small fw-bold">
                                                <i class="bi bi-check-circle-fill me-1"></i>COMPLETED
                                            </span>
                                        <?php elseif ($status === 'PENDING'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 small fw-bold">
                                                <span class="spinner-grow spinner-grow-sm me-1" style="width: 7px; height: 7px;"></span>PENDING
                                            </span>
                                        <?php elseif ($status === 'IN_PROCESS' || $status === 'UNDER_REVIEW'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small fw-bold">
                                                <span class="spinner-border spinner-border-sm me-1" style="width: 8px; height: 8px;"></span>PROCESSING
                                            </span>
                                        <?php elseif ($status === 'REJECTED'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 small fw-bold">
                                                <i class="bi bi-x-circle-fill me-1"></i>REJECTED
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border px-2 py-1 small fw-bold">
                                                <?= e($status) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-1">
                                        <?= render_status_stepper_dots($app) ?>
                                    </div>
                                </td>

                                <td class="small text-muted" style="white-space: nowrap;">
                                    <?= date('d M, h:i A', strtotime($app['created_at'])) ?>
                                </td>

                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                        <?php if (!empty($app['result_file'])): ?>
                                            <a href="<?= e($app['result_file']) ?>" target="_blank" download
                                               class="btn btn-sm btn-success fw-bold py-1 px-2.5 rounded-2 shadow-2xs text-nowrap"
                                               style="font-size: 0.75rem;" title="Download RTPS Certificate">
                                                <i class="bi bi-file-earmark-pdf-fill me-1"></i>Download Certificate
                                            </a>
                                        <?php elseif ($status === 'IN_PROCESS'): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-1 px-1.5 text-nowrap" style="font-size: 0.68rem;" title="Automatic RTPS Check Scheduled (Every 5 Hours / 7 AM)">
                                                <i class="bi bi-clock-history me-1"></i>RTPS Check (5-Hr)
                                            </span>
                                        <?php endif; ?>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-warning fw-bold py-0.5 px-2.5 rounded-2 shadow-2xs"
                                                style="font-size: 0.75rem;"
                                                onclick="openDetailsModal(<?= $app['id'] ?>)">
                                            More Details
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
        <?php endif; ?>
    </div>
</div>

<!-- ================= MODALS: DETAILS, RECEIPT, CHAT ================= -->

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
                ${app.applicant_identifier ? `<button type="button" class="btn btn-xs btn-link p-0 text-muted ms-1" onclick="copyText('${app.applicant_identifier}', this)"><i class="bi bi-copy"></i> Copy</button>` : ''}
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

    window.openReceiptModal = function (appId) {
        const row = document.getElementById('app-row-' + appId);
        if (!row) return;
        const app = JSON.parse(row.getAttribute('data-app'));
        const f = app.formData || {};

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
