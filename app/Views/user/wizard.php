<?php require dirname(__DIR__) . '/layouts/header.php'; ?>
<?php
    $finalFee = (float)($pricingInfo['final_fee'] ?? ($activeDataType['fee_amount'] ?? 50.00));
    $currentUserId = (int)$currentUser['id'];
?>

<style>
/* Clean Full-Width Editable Table Styles (Labour Card 2.0 Row-Entry Flow) */
.table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.entry-table th {
    font-size: 0.74rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    background-color: #f8fafc;
    border-bottom: 1.5px solid #cbd5e1;
    white-space: nowrap;
    padding: 0.6rem 0.5rem;
}
.entry-table td {
    font-size: 0.82rem;
    vertical-align: top;
    padding: 0.5rem 0.45rem;
    border-color: #f1f5f9;
}
.entry-table tbody tr:hover {
    background-color: #f8fafc;
}
.row-serial-badge {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background-color: #0f172a;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
}
.bottom-bar {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    border-bottom-left-radius: 0.65rem;
    border-bottom-right-radius: 0.65rem;
    padding: 0.75rem 1rem;
}
.metric-badge {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 0.35rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.8rem;
}
</style>

<!-- TOAST CONTAINER -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

<div class="container-fluid px-2 px-md-4 py-2 mb-5" style="max-width: 1400px;">

    <!-- TOP BAR: CLEAN SERVICE HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
        <div class="d-flex align-items-center gap-2">
            <a href="/applications" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-2xs">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
            <div class="ms-1">
                <h1 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span><?= e($activeDataType['name_en'] ?? 'New Application Entry') ?></span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 rounded text-uppercase" style="font-size: 0.68rem;">
                        <?= e($activeDataType['badge'] ?? 'FAST TRACK') ?>
                    </span>
                </h1>
                <span class="text-muted small" style="font-size: 0.72rem;">
                    Batch Entry &bull; Processing Time: <?= e($activeDataType['processing_time'] ?? '10-30 MIN') ?>
                </span>
            </div>
        </div>

        <a href="/applications" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold shadow-2xs">
            <i class="bi bi-folder2-open me-1"></i> Application List
        </a>
    </div>

    <?php if (empty($districts)): ?>
        <div class="alert alert-warning py-3 px-3.5 mb-3 rounded-3 shadow-2xs d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill text-warning fs-5"></i>
            <div>
                <strong>No Rate-Configured Locations Available:</strong>
                Applications are currently unavailable because no block-specific rates have been configured by administration. Please check back later.
            </div>
        </div>
    <?php endif; ?>

    <!-- FULL-WIDTH EDITABLE APPLICATIONS TABLE CARD -->

    <div class="card shadow-sm border rounded-3 bg-white mb-3" style="border: 1px solid #e2e8f0;">
        <!-- Card Header -->
        <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-table text-primary"></i>
                <h2 class="h6 fw-bold mb-0 text-dark" style="font-size: 0.85rem;">Application Entries</h2>
                <span class="badge bg-light text-secondary border font-monospace" id="headerCountBadge">1 Row</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0.5 px-2.5 rounded-2 shadow-2xs" onclick="resetAllRows()" style="font-size: 0.75rem;">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Table
            </button>
        </div>

        <!-- Horizontal Scrollable Table Wrapper -->
        <div class="table-responsive" style="min-height: 220px;">
            <table class="table table-sm table-bordered align-middle mb-0 entry-table" style="min-width: 950px;">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th style="width: 175px;">District *</th>
                        <th style="width: 175px;">Block *</th>
                        <th style="width: 165px;">Application Code *</th>
                        <th style="width: 220px;">Application Number *</th>
                        <th style="min-width: 210px;">Applicant Name *</th>
                        <th style="width: 200px;">Additional Fields</th>
                        <th style="width: 95px;" class="text-end">Rate</th>
                        <th style="width: 50px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="entriesTableBody">
                    <!-- Dynamic Rows populated via JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Bottom Action Bar (Left: + Add Row | Right: Total Fee & Submit Button) -->
        <div class="card-footer bg-white border-top p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <!-- Left: + Add Row Button -->
            <button type="button" id="btnAddRow" class="btn btn-primary btn-sm px-3.5 py-2 fw-bold rounded-2 shadow-xs d-flex align-items-center gap-1.5" onclick="addNewRow()">
                <i class="bi bi-plus-lg"></i>
                <span id="btnAddRowText">+ Add Row</span>
            </button>

            <!-- Right: Total Fee and Submit All Button -->
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <div class="d-flex align-items-center gap-1 px-3 py-1.5 bg-light border rounded-2 shadow-2xs">
                    <span class="text-muted small">Total Fee:</span>
                    <strong class="text-primary font-monospace" id="metricTotalFee">₹0.00</strong>
                </div>

                <!-- Submit All Button (Green) -->
                <button type="button" id="btnSubmitAll" class="btn btn-success btn-sm px-4 py-2 fw-bold rounded-2 shadow-sm d-flex align-items-center gap-2" onclick="handleSubmitAll()">
                    <i class="bi bi-send-check" id="submitIcon"></i>
                    <span id="submitBtnText">Submit All (1 Row — ₹0.00)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Shortfall Alert -->
    <div id="walletShortfallAlert" class="alert alert-danger py-2 px-3 rounded-2 shadow-2xs d-flex align-items-center justify-content-between small d-none mb-3">
        <div>
            <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
            <span>Insufficient Wallet Balance! Shortfall: <strong id="shortfallAmount" class="font-monospace">₹0.00</strong></span>
        </div>
        <a href="/wallet/topup" class="btn btn-xs btn-danger py-0.5 px-3 fw-bold rounded-pill">Recharge Wallet</a>
    </div>

</div>

<!-- UPDATED RATES CONFIRMATION POPUP MODAL -->
<div class="modal fade" id="modalPriceChange" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-warning-subtle border-0 px-4 pt-4 pb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Rate Update Detected</h5>
                        <p class="text-muted small mb-0" style="font-size: 0.76rem;">Rate rules were updated on the server</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <p class="text-secondary small mb-3" id="priceChangeDescription">
                    The rates for your batch have changed. Please review the updated rates below:
                </p>
                <div class="table-responsive rounded-3 border mb-3">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Applicant</th>
                                <th class="text-end">Previous</th>
                                <th class="text-end text-primary">New Rate</th>
                            </tr>
                        </thead>
                        <tbody id="priceChangeTableBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center border">
                    <span class="fw-semibold text-secondary small">New Total Batch Fee:</span>
                    <strong class="fs-5 text-primary font-monospace" id="modalNewTotalFee">₹0.00</strong>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" id="btnConfirmPriceChange" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-check2-circle"></i>
                    <span>Accept &amp; Submit</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- GENERAL ALERT / NOTICE MODAL -->
<div class="modal fade" id="modalGeneralAlert" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content rounded-4 border-0 shadow-lg text-center p-4">
            <div class="my-2">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle p-3 mb-2" id="alertModalIconWrapper" style="width: 70px; height: 70px; background-color: #fee2e2; color: #dc2626;">
                    <i id="alertModalIcon" class="bi bi-exclamation-triangle-fill fs-2"></i>
                </div>
            </div>
            <h5 class="fw-bold text-dark mb-2" id="alertModalTitle">Notice</h5>
            <p class="text-secondary small mb-4" id="alertModalBody"></p>
            <div class="d-flex justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4 fw-bold" data-bs-dismiss="modal">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SUCCESS CONFIRMATION MODAL -->
<div class="modal fade" id="modalBatchSuccess" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content rounded-4 border-0 shadow-lg text-center p-4">
            <div class="my-3">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            </div>
            <h4 class="h5 fw-bold text-dark mb-4">Your Application Submitted</h4>
            <div class="d-flex justify-content-center">
                <a href="/applications" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold">
                    <i class="bi bi-folder2-open me-1"></i> View Applications
                </a>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTBOX ZOOM MODAL -->
<div class="modal fade" id="modalImageZoom" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0 text-center">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3" data-bs-dismiss="modal"></button>
            <img id="modalZoomImg" src="" alt="Zoomed Preview" class="img-fluid rounded-4 shadow-lg" style="max-height: 85vh;">
        </div>
    </div>
</div>

<script>
// Master Districts data passed from server
window.DISTRICTS = window.DISTRICTS_DATA = <?= json_encode(array_map(function($d) {
    return [
        'id' => (int)$d['id'],
        'name' => $d['name'],
        'name_hi' => $d['name_hi'] ?? '',
        'state' => $d['state'] ?? 'Bihar'
    ];
}, $districts)) ?>;

// Copied Data if redirected from Application List
window.COPIED_DATA = <?= !empty($copiedData) ? json_encode($copiedData) : 'null' ?>;

</script>

<?php require __DIR__ . '/wizard_script.php'; ?>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
