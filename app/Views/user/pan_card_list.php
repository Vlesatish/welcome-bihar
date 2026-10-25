<?php
declare(strict_types=1);

/**
 * View: New Pan Card Apply (Sirf Aadhaar Card Se)
 * New Pan Card Applications List (Track, UTI Crop, Slips & Actions)
 */
require dirname(__DIR__) . '/layouts/header.php';

$currentUser = \App\Services\AuthService::user();
$currentRole = $currentUser['role'] ?? 'USER';
$activeMode = \App\Services\AuthService::activePortalMode();
$isAdmin = in_array($currentRole, ['ADMIN', 'SUPER_ADMIN'], true);
$isOperator = ($currentRole === 'OPERATOR' || $currentRole === 'STAFF' || (\App\Services\AuthService::canAccessOperatorMode((int)($currentUser['id'] ?? 0)) && $activeMode === 'OPERATOR'));
$isStaffOrAdmin = $isAdmin || $isOperator;

$panFee = 107.00;
// Fetch fresh wallet balance directly from DB for accurate display
$userBal = $currentUser ? (float)\App\Services\WalletService::getBalance((int)$currentUser['id']) : 0.00;
$hasEnoughBalance = $isAdmin || ($userBal >= $panFee);
$csrfToken = \App\Services\AuthService::generateCsrfToken();
?>

<style>
/* Modern Pan Card Service Styles */
:root {
    --pan-primary: #1e40af;
    --pan-primary-hover: #1d4ed8;
    --pan-accent: #f59e0b;
    --pan-success: #059669;
    --pan-danger: #dc2626;
    --pan-card-bg: #ffffff;
    --pan-border: #e2e8f0;
}

.pan-page-header {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 6px -1px rgba(0, 0, 0, 0.04);
}

.pan-title-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #ef4444 0%, #f97316 100%);
    color: #ffffff;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.25);
}

.pan-notice-card {
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
    border: 1.5px solid #fecdd3;
    border-radius: 12px;
    padding: 0.9rem 1.25rem;
    box-shadow: 0 2px 4px rgba(244, 63, 94, 0.05);
}

.pan-form-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 14px -2px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.pan-form-group {
    margin-bottom: 1.15rem;
}

.pan-label {
    display: block;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: #1e293b;
    margin-bottom: 0.35rem;
    text-transform: uppercase;
}

.pan-label .req-star {
    color: #dc2626;
    font-size: 0.85rem;
    margin-left: 2px;
}

.pan-input, .pan-select {
    width: 100%;
    padding: 0.65rem 0.9rem;
    font-size: 0.88rem;
    font-weight: 500;
    color: #0f172a;
    background-color: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    transition: all 0.2s ease;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
}

.pan-input:focus, .pan-select:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    background-color: #fbfdff;
}

.pan-helper {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 0.25rem;
}

/* Upload Dropzone Styles */
.pan-upload-zone {
    border: 2px dashed #cbd5e1;
    background: #f8fafc;
    border-radius: 10px;
    padding: 1.25rem 1rem;
    text-align: center;
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}

.pan-upload-zone:hover, .pan-upload-zone.dragover {
    border-color: #2563eb;
    background: #eff6ff;
}

.pan-upload-icon {
    font-size: 1.8rem;
    color: #3b82f6;
    margin-bottom: 0.35rem;
}

.pan-upload-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.2rem;
}

.pan-upload-subtitle {
    font-size: 0.72rem;
    color: #64748b;
    margin-bottom: 0.6rem;
}

.pan-snap-btn {
    font-size: 0.76rem;
    font-weight: 600;
    padding: 0.35rem 0.85rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.pan-snap-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}

.pan-file-preview {
    margin-top: 0.75rem;
    padding: 0.5rem 0.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pan-file-preview img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
}

/* Submit Action Button */
.pan-submit-btn {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border: none;
    color: #ffffff;
    font-size: 1rem;
    font-weight: 700;
    padding: 0.85rem 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.28);
    transition: all 0.25s ease;
    letter-spacing: 0.02em;
}

.pan-submit-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.38);
    transform: translateY(-1px);
}

.pan-submit-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

/* Previous Requests List & Cards */
.req-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
}

.req-filter-pill {
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.req-filter-pill:hover, .req-filter-pill.active {
    background: #1e40af;
    color: #ffffff;
    border-color: #1e40af;
}

.req-card {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    transition: all 0.25s ease;
}

/* Dynamic status-based border colors (state ke hisab se border color) */
.req-card.status-completed, .req-card.status-approved {
    border: 2px solid #22c55e !important;
    border-top: 5px solid #22c55e !important;
}

.req-card.status-pending {
    border: 2px solid #f59e0b !important;
    border-top: 5px solid #f59e0b !important;
}

.req-card.status-in-process, .req-card.status-under-review {
    border: 2px solid #3b82f6 !important;
    border-top: 5px solid #3b82f6 !important;
}

.req-card.status-rejected {
    border: 2px solid #ef4444 !important;
    border-top: 5px solid #ef4444 !important;
}

.req-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.req-card-header {
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    padding: 0.85rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.req-card-body {
    padding: 1.15rem 1.25rem;
}

.req-grid-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    height: 100%;
    transition: all 0.2s ease;
}

.req-grid-box:hover {
    background: #ffffff;
    border-color: #cbd5e1;
}

.req-grid-box .req-field-label {
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 0.15rem;
    letter-spacing: 0.03em;
}

.req-grid-box .req-field-value {
    font-size: 0.84rem;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
}

.req-field-row {
    margin-bottom: 0.6rem;
}

.req-field-label {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 0.1rem;
    letter-spacing: 0.03em;
}

.req-field-value {
    font-size: 0.85rem;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
}

.req-thumb-box {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.4rem;
    background: #f8fafc;
    text-align: center;
    position: relative;
}

.req-thumb-img {
    width: 100%;
    height: 110px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.req-thumb-img:hover {
    transform: scale(1.03);
}

.req-output-box {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1.5px solid #10b981;
    border-radius: 10px;
    padding: 0.9rem 1.25rem;
    margin-top: 1rem;
}

.req-reject-box {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border: 1.5px solid #ef4444;
    border-radius: 10px;
    padding: 0.9rem 1.25rem;
    margin-top: 1rem;
}

/* Blinking Hand & Pulsing Receipt Button */
@keyframes handPointBlink {
    0%, 100% {
        transform: translateX(0);
        opacity: 1;
    }
    50% {
        transform: translateX(-4px);
        opacity: 0.35;
    }
}

@keyframes receiptBlinkGlow {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
        border-color: #16a34a;
    }
    50% {
        box-shadow: 0 0 0 7px rgba(34, 197, 94, 0);
        border-color: #4ade80;
    }
}

.btn-receipt-pulse {
    animation: receiptBlinkGlow 1.8s infinite ease-in-out;
    background: #16a34a !important;
    border: 1.5px solid #16a34a !important;
    color: #ffffff !important;
    transition: all 0.2s ease;
}

.btn-receipt-pulse:hover {
    background: #15803d !important;
    transform: scale(1.04);
}

.hand-blink-icon {
    display: inline-block;
    animation: handPointBlink 0.85s infinite ease-in-out;
    font-size: 1.05rem;
    line-height: 1;
}

/* Webcam Modal Styles */
#cameraVideo {
    width: 100%;
    max-height: 380px;
    object-fit: cover;
    border-radius: 8px;
    background: #000;
}
</style>

<div class="container-fluid px-2 px-md-4 py-3">
    <!-- ========================================================
         PREVIOUS REQUESTS SECTION
         ======================================================== -->
    <div class="req-section-header">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-bold text-dark"><?= $isStaffOrAdmin ? 'PAN Card Application Requests' : 'New Pan Card List' ?></h5>
            <span class="badge bg-primary rounded-pill px-2.5 py-1" style="font-size: 0.78rem;"><?= $totalRequests ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (!$isStaffOrAdmin): ?>
                <a href="/services/pan-card" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-2xs">
                    <i class="bi bi-plus-circle me-1"></i> New Pan Card Apply
                </a>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-calendar3 me-1"></i> All Date
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="?status=<?= urlencode($statusFilter) ?>">All Time</a></li>
                    <li><a class="dropdown-item" href="?status=<?= urlencode($statusFilter) ?>&date=today">Today</a></li>
                    <li><a class="dropdown-item" href="?status=<?= urlencode($statusFilter) ?>&date=this_month">This Month</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Search Box and Status Pills -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <!-- Live Search Input -->
        <div class="position-relative flex-grow-1" style="max-width: 480px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="liveSearchInput" class="form-control rounded-pill ps-5 py-2 shadow-2xs" 
                   placeholder="Search Name & Aadhar number, PAN number..." 
                   value="<?= e($search) ?>" onkeyup="handleFilterSearch(this.value)">
        </div>

        <!-- Filter Tabs -->
        <div class="d-flex flex-wrap gap-2">
            <a href="?status=ALL<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
               class="req-filter-pill <?= ($statusFilter === 'ALL' || empty($statusFilter)) ? 'active' : '' ?>">
                <span>All Requests</span>
                <span class="badge bg-light text-dark rounded-pill"><?= $totalRequests ?></span>
            </a>
            <a href="?status=PENDING<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
               class="req-filter-pill <?= $statusFilter === 'PENDING' ? 'active' : '' ?>">
                <span>Pending</span>
                <span class="badge bg-warning text-dark rounded-pill"><?= $pendingCount ?></span>
            </a>
            <a href="?status=COMPLETED<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
               class="req-filter-pill <?= $statusFilter === 'COMPLETED' ? 'active' : '' ?>">
                <span>Completed</span>
                <span class="badge bg-success text-white rounded-pill"><?= $completedCount ?></span>
            </a>
            <a href="?status=REJECTED<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
               class="req-filter-pill <?= $statusFilter === 'REJECTED' ? 'active' : '' ?>">
                <span>Rejected</span>
                <span class="badge bg-danger text-white rounded-pill"><?= $rejectedCount ?></span>
            </a>
        </div>
    </div>

    <!-- Request Cards Container -->
    <div id="requestCardsContainer">
        <?php if (empty($requests)): ?>
            <div class="pan-form-card p-5 text-center text-muted">
                <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6 class="fw-bold">No Pan Card requests found</h6>
                <p class="small mb-0">Submit a new request above using Aadhaar card details to see it listed here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <?php
                    $fields = $req['fields'] ?? [];
                    $st = strtoupper($req['status']);
                    $badgeClass = 'bg-warning text-dark';
                    $badgeIcon = 'bi-hourglass-split';
                    if ($st === 'COMPLETED' || $st === 'APPROVED') {
                        $badgeClass = 'bg-success text-white';
                        $badgeIcon = 'bi-check-circle-fill';
                    } elseif ($st === 'REJECTED') {
                        $badgeClass = 'bg-danger text-white';
                        $badgeIcon = 'bi-x-circle-fill';
                    }

                    $reqDate = date('d-M-Y, h:i:s a', strtotime($req['created_at']));
                    $isToday = date('Y-m-d', strtotime($req['created_at'])) === date('Y-m-d');
                    $datePrefix = $isToday ? 'Today | ' : '';

                    $photoUrl = $fields['photo'] ?? null;
                    $sigUrl = $fields['signature'] ?? null;
                    $aadharUrl = $fields['aadhar_card'] ?? null;
                    $panFormUrl = $fields['pan_form'] ?? null;
                    $resultFile = $req['result_file'] ?? null;
                ?>
                <?php
                    $statusBorderClass = 'status-pending';
                    if ($st === 'COMPLETED' || $st === 'APPROVED') {
                        $statusBorderClass = 'status-completed';
                    } elseif ($st === 'IN_PROCESS' || $st === 'UNDER_REVIEW') {
                        $statusBorderClass = 'status-in-process';
                    } elseif ($st === 'REJECTED') {
                        $statusBorderClass = 'status-rejected';
                    }
                ?>
                <div class="req-card <?= $statusBorderClass ?>" id="row-app-<?= (int)$req['id'] ?>" data-app="<?= htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8') ?>" data-ref="<?= e($req['app_ref']) ?>" data-name="<?= e(strtolower($req['applicant_name'] ?? '')) ?>" data-aadhar="<?= e($req['applicant_identifier'] ?? '') ?>">
                    <!-- Card Header (matching Image 1) -->
                    <div class="req-card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 px-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-dark text-white fw-bold px-2 py-1 font-monospace" style="font-size: 0.82rem;">
                                #<?= e($req['app_ref']) ?>
                            </span>
                            <strong class="text-dark fs-6">New Pan Card Apply</strong>
                            <span class="badge <?= $badgeClass ?> px-2.5 py-1 fw-bold text-uppercase" style="font-size: 0.72rem;">
                                <?= e($st) ?>
                            </span>
                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-0.5" style="font-size: 0.70rem;">
                                PAN
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if (!empty($resultFile)): ?>
                                <button type="button" class="btn btn-sm btn-receipt-pulse fw-bold px-3 py-1.5 shadow-2xs rounded-pill d-inline-flex align-items-center gap-1.5" style="font-size: 0.78rem;" onclick="openReceivingModal('<?= e($resultFile) ?>', '<?= e($req['app_ref']) ?>', '<?= e(addslashes($req['applicant_name'] ?? 'Applicant')) ?>')" title="रसीद तैयार है! क्लिक करके देखें, प्रिंट करें या डाउनलोड करें">
                                    <span class="hand-blink-icon">👉</span>
                                    <span>रसीद देखें</span>
                                </button>
                            <?php endif; ?>
                            <?php if ($isStaffOrAdmin): ?>
                                <button type="button" class="btn btn-sm text-white fw-bold px-3 py-1.5 shadow-2xs rounded-pill" style="background:#ff7700; border-color:#ff7700; font-size: 0.78rem;" onclick="openProcessStatusModal(<?= (int)$req['id'] ?>)" title="Process Request, Update Status, Remarks and Upload Receiving Slip">
                                    <i class="bi bi-gear-wide-connected me-1"></i> Process / Status
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Meta Subline -->
                    <div class="px-3 py-1.5 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted" style="font-size: 0.74rem;">
                        <div>
                            <strong>Retailer:</strong> <?= e($req['retailer_name'] ?: 'Direct User') ?> (<?= e($req['retailer_mobile'] ?: '-') ?>) • <strong>Fee:</strong> <span class="text-success fw-bold font-monospace">₹<?= number_format((float)($req['fee_debited'] ?? 107), 2) ?></span>
                            <?php if (!empty($req['admin_remarks'])): ?>
                                • <span class="text-dark fw-semibold"><i class="bi bi-chat-left-dots text-primary me-1"></i><strong>Remark:</strong> <?= e($req['admin_remarks']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <i class="bi bi-clock me-1"></i> <strong>Entry Time / समय:</strong> <?= $datePrefix . $reqDate ?>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="req-card-body">
                        <div class="fw-bold text-muted small text-uppercase mb-2.5" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            <i class="bi bi-ui-checks-grid text-primary me-1"></i> SUBMITTED FORM DATA & ATTACHMENTS
                        </div>

                        <!-- 3-Column Clean Boxed Grid (matching Image 1) -->
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">FULL NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($req['applicant_name'] ?: ($fields['full_name'] ?? '-')) ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">GENDER</div>
                                    <div class="req-field-value"><?= e($fields['gender'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">DATE OF BIRTH</div>
                                    <div class="req-field-value font-monospace"><?= e($fields['dob'] ?? '-') ?></div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">SELECT STATE</div>
                                    <div class="req-field-value"><?= e($fields['state'] ?? 'Bihar') ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">AADHAR NO</div>
                                    <div class="req-field-value text-primary font-monospace"><?= e($req['applicant_identifier'] ?: ($fields['aadhaar_no'] ?? '-')) ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">मोबाईल नंबर</div>
                                    <div class="req-field-value font-monospace"><?= e($req['applicant_mobile'] ?: ($fields['mobile_no'] ?? '-')) ?></div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">EMAIL ID</div>
                                    <div class="req-field-value text-lowercase"><?= e($fields['email_id'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">FATHER NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['father_name'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">MOTHER NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['mother_name'] ?? '-') ?></div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">HUSBAND NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e(!empty($fields['husband_name']) ? $fields['husband_name'] : '-') ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">VILLAGE</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['village'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">POST OFFICE</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['post_office'] ?? '-') ?></div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">BLOCK NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['block_name'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">DISTRIC NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['district_name'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="req-grid-box">
                                    <div class="req-field-label">PINCODE</div>
                                    <div class="req-field-value font-monospace"><?= e($fields['pincode'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Preview & Actions Row -->
                        <div class="mt-3 pt-3 border-top">
                            <div class="row g-2">
                                <!-- Photo Preview -->
                                <div class="col-6 col-sm-3">
                                    <div class="req-thumb-box">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="req-field-label mb-0">PHOTO</span>
                                            <?php if ($photoUrl): ?>
                                                <a href="<?= e($photoUrl) ?>" target="_blank" class="badge bg-light text-secondary border" style="font-size: 0.60rem;">IMAGE <i class="bi bi-box-arrow-up-right"></i></a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($photoUrl): ?>
                                            <img src="<?= e($photoUrl) ?>" class="req-thumb-img" alt="Photo" onclick="openImageModal('<?= e($photoUrl) ?>', 'Photo Preview')">
                                            <div class="d-flex gap-1 mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary w-50 py-0.5" style="font-size: 0.68rem;" onclick="openImageModal('<?= e($photoUrl) ?>', 'Photo Preview')">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary w-50 py-0.5 fw-bold" style="font-size: 0.68rem;" onclick="downloadUtiPhoto('<?= e($photoUrl) ?>', '<?= e(addslashes($req['applicant_name'])) ?>', this)" title="Download UTI 213x213 @ 300 DPI">
                                                    <i class="bi bi-download"></i> UTI
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted small py-4">No Photo</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Signature Preview -->
                                <div class="col-6 col-sm-3">
                                    <div class="req-thumb-box">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="req-field-label mb-0">SIGNATURE</span>
                                            <?php if ($sigUrl): ?>
                                                <a href="<?= e($sigUrl) ?>" target="_blank" class="badge bg-light text-secondary border" style="font-size: 0.60rem;">IMAGE <i class="bi bi-box-arrow-up-right"></i></a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($sigUrl): ?>
                                            <!-- Dynamic Fresh Signature Canvas (shown when sliders moved/rendered) -->
                                            <canvas id="card_sign_canvas_<?= (int)$req['id'] ?>" class="req-thumb-img d-none" style="background:#fff; width:100%; height:84px; object-fit:contain; cursor:pointer;" onclick="viewCardSignPreview(<?= (int)$req['id'] ?>)"></canvas>
                                            <img src="<?= e($sigUrl) ?>" id="card_sign_img_<?= (int)$req['id'] ?>" class="req-thumb-img" alt="Signature" onclick="viewCardSignPreview(<?= (int)$req['id'] ?>)">
                                            
                                            <div class="d-flex gap-1 mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary w-50 py-0.5" style="font-size: 0.68rem;" onclick="viewCardSignPreview(<?= (int)$req['id'] ?>)">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger w-50 py-0.5 fw-bold" style="font-size: 0.68rem;" id="card_fresh_btn_<?= (int)$req['id'] ?>" onclick="downloadCardSignature(<?= (int)$req['id'] ?>, '<?= e(addslashes($req['applicant_name'])) ?>', this)" title="Download Fresh 400x200 @ 600 DPI">
                                                    <i class="bi bi-download"></i> Fresh
                                                </button>
                                            </div>

                                            <!-- Half-and-Half Sliders in place of PSA Crop Tool button -->
                                            <div class="pan-sign-slider-box p-1.5 bg-light rounded-2 border mt-1 text-start shadow-2xs">
                                                <div class="row g-1 align-items-center">
                                                    <!-- Left Half (50%): Paper Whiteness -->
                                                    <div class="col-6 pe-1 border-end">
                                                        <div class="d-flex justify-content-between align-items-center text-muted mb-0.5" style="font-size: 0.62rem; line-height: 1;">
                                                            <span class="text-truncate fw-semibold" title="कागज का बैकग्राउंड साफ करें">कागज साफ</span>
                                                            <span id="card_white_val_<?= (int)$req['id'] ?>" class="fw-bold text-dark font-monospace" style="font-size: 0.65rem;">195</span>
                                                        </div>
                                                        <input type="range" class="form-range p-0 m-0 w-100" id="card_white_slider_<?= (int)$req['id'] ?>" min="130" max="245" value="195" style="height: 14px;" oninput="onCardSignSliderInput(<?= (int)$req['id'] ?>, '<?= e($sigUrl) ?>')">
                                                    </div>

                                                    <!-- Right Half (50%): Ink Darkness -->
                                                    <div class="col-6 ps-1">
                                                        <div class="d-flex justify-content-between align-items-center text-muted mb-0.5" style="font-size: 0.62rem; line-height: 1;">
                                                            <span class="text-truncate fw-semibold" title="हस्ताक्षर स्याही डार्क करें">स्याही डार्क</span>
                                                            <span id="card_dark_val_<?= (int)$req['id'] ?>" class="fw-bold text-dark font-monospace" style="font-size: 0.65rem;">80</span>
                                                        </div>
                                                        <input type="range" class="form-range p-0 m-0 w-100" id="card_dark_slider_<?= (int)$req['id'] ?>" min="20" max="140" value="80" style="height: 14px;" oninput="onCardSignSliderInput(<?= (int)$req['id'] ?>, '<?= e($sigUrl) ?>')">
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center justify-content-between mt-1 pt-1 border-top" style="font-size: 0.60rem; line-height: 1;">
                                                    <div class="form-check form-check-inline m-0 p-0 d-flex align-items-center gap-1">
                                                        <input class="form-check-input m-0" type="checkbox" id="card_mode_color_<?= (int)$req['id'] ?>" value="color" onchange="onCardSignSliderInput(<?= (int)$req['id'] ?>, '<?= e($sigUrl) ?>')">
                                                        <label class="form-check-label text-muted" for="card_mode_color_<?= (int)$req['id'] ?>" style="font-size: 0.60rem;">Color</label>
                                                    </div>
                                                    <button type="button" class="btn btn-link btn-xs p-0 text-muted" style="font-size: 0.60rem; text-decoration: none;" onclick="resetCardSignSliders(<?= (int)$req['id'] ?>, '<?= e($sigUrl) ?>')" title="Reset to default 195 / 80">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted small py-4">No Signature</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Aadhar Card Preview -->
                                <div class="col-6 col-sm-3">
                                    <div class="req-thumb-box h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="req-field-label mb-0">AADHAR CARD</span>
                                                <?php if ($aadharUrl): ?>
                                                    <a href="<?= e($aadharUrl) ?>" target="_blank" class="badge bg-light text-secondary border" style="font-size: 0.60rem;">DOC <i class="bi bi-box-arrow-up-right"></i></a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="py-2 text-danger">
                                                <i class="bi bi-file-earmark-pdf-fill fs-1"></i>
                                            </div>
                                        </div>
                                        <?php if ($aadharUrl): ?>
                                            <a href="<?= e($aadharUrl) ?>" target="_blank" class="btn btn-sm btn-outline-dark w-100 py-1" style="font-size: 0.72rem;">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> View PDF / New Tab
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Not Uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Pan Form Preview -->
                                <div class="col-6 col-sm-3">
                                    <div class="req-thumb-box h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="req-field-label mb-0">PAN FORM</span>
                                                <?php if ($panFormUrl): ?>
                                                    <a href="<?= e($panFormUrl) ?>" target="_blank" class="badge bg-light text-secondary border" style="font-size: 0.60rem;">DOC <i class="bi bi-box-arrow-up-right"></i></a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="py-2 text-primary">
                                                <i class="bi bi-file-earmark-text-fill fs-1"></i>
                                            </div>
                                        </div>
                                        <?php if ($panFormUrl): ?>
                                            <a href="<?= e($panFormUrl) ?>" target="_blank" class="btn btn-sm btn-outline-dark w-100 py-1" style="font-size: 0.72rem;">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> View PDF / New Tab
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Not Uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($st === 'REJECTED'): ?>
                            <div class="req-reject-box mt-3">
                                <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-1">
                                    <i class="bi bi-x-circle-fill fs-5"></i>
                                    <span>Application Rejected (आवेदन अस्वीकृत कर दिया गया है)</span>
                                </div>
                                <div class="text-danger small">
                                    <strong>Rejection Reason:</strong> <?= e($req['rejection_reason'] ?: 'Aadhaar data mismatch or unclear document upload.') ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="processStatusModal" tabindex="-1" aria-labelledby="processStatusModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" id="processModalContent" style="border: 2px solid #f59e0b; border-top: 6px solid #f59e0b; transition: all 0.25s ease;">
            <!-- Modal Header -->
            <div class="modal-header py-3 px-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="p-2 rounded-circle bg-warning-subtle text-warning">
                        <i class="bi bi-gear-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0 d-flex align-items-center gap-2" id="processStatusModalTitle">
                            <span>Process Request</span>
                            <span id="modalProcTitleRef" class="badge bg-dark font-monospace rounded-pill px-2.5 py-1">#...</span>
                        </h6>
                        <span class="text-muted small" style="font-size:0.75rem;">New Pan Card Apply</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="modalProcessForm" action="/applications/update-status" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="application_id" id="modalProcAppId" value="">
                <input type="hidden" name="redirect_to" value="/services/pan-card/list">
                <input type="hidden" name="result_file_base64" id="modalResultFileBase64" value="">

                <div class="modal-body p-4">
                    <!-- Status Selection -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">
                            <i class="bi bi-tag-fill text-primary me-1"></i> New Status
                        </label>
                        <select name="status" id="modalProcStatus" class="form-select rounded-3 py-2 px-3 fw-semibold" onchange="onModalStatusChange(this.value)">
                            <option value="COMPLETED">COMPLETED (Completed / Approved)</option>
                            <option value="IN_PROCESS">IN_PROCESS (In Process / Working)</option>
                            <option value="PENDING">PENDING (Pending / In Queue)</option>
                            <option value="REJECTED">REJECTED (Rejected / Error)</option>
                        </select>
                    </div>

                    <!-- Operator Remarks -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">
                            <i class="bi bi-chat-left-text-fill text-primary me-1"></i> Operator Remarks
                        </label>
                        <input type="text" name="admin_remarks" id="modalProcRemarks" class="form-control rounded-3 py-2 px-3" placeholder="e.g. Pan card processed, Ack slip generated...">
                    </div>

                    <!-- Output Document Upload & Paste Zone (Clean, Fresh, Rounded, Minimal text) -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1.5 flex-wrap gap-1">
                            <label class="form-label fw-bold text-dark small mb-0">
                                <i class="bi bi-file-earmark-check-fill text-success me-1"></i> Generated Output / Receiving Slip *
                            </label>
                            <span class="badge text-white px-2.5 py-1 rounded-pill" style="background: #7c3aed; font-size: 0.70rem; letter-spacing: 0.3px;">
                                <i class="bi bi-clipboard2-check me-1"></i> Supports Ctrl + V Paste
                            </span>
                        </div>

                        <!-- Dropzone / Paste container -->
                        <div class="p-3.5 rounded-4 text-center border-2 border-dashed position-relative" id="modalPasteDropZone" style="border: 2px dashed #c084fc; background: #faf5ff; transition: all 0.2s ease;">
                            <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap mb-2">
                                <button type="button" class="btn btn-sm text-white fw-bold px-3.5 py-2 rounded-pill shadow-xs" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); border:none;" onclick="triggerModalFileInput()">
                                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Select File (PDF / Image)
                                </button>
                                <button type="button" class="btn btn-sm btn-white border fw-semibold px-3.5 py-2 rounded-pill shadow-xs bg-white text-dark" onclick="triggerClipboardRead()">
                                    <i class="bi bi-clipboard-plus me-1 text-primary"></i> Paste (Ctrl + V)
                                </button>
                            </div>

                            <input type="file" id="modalResultFileInput" name="result_file" class="d-none" accept=".pdf,.png,.jpg,.jpeg,.webp" onchange="onModalFileSelected(this)">

                            <div class="text-muted small mb-2" style="font-size: 0.74rem;">
                                Copy screenshot and press <kbd class="bg-white text-dark border px-1.5 py-0.5 rounded font-monospace">Ctrl + V</kbd> or drag file here
                            </div>

                            <!-- Subtle direct link toggle -->
                            <div class="d-flex align-items-center justify-content-center">
                                <a class="small text-decoration-none text-muted" data-bs-toggle="collapse" href="#modalDirectUrlBox" role="button" aria-expanded="false" style="font-size: 0.72rem;">
                                    <i class="bi bi-link-45deg me-0.5"></i> Direct file link (Optional)
                                </a>
                            </div>
                            <div class="collapse mt-1" id="modalDirectUrlBox">
                                <input type="text" name="result_file_url" id="modalResultFileUrl" class="form-control form-control-sm rounded-pill px-3" placeholder="https://... or /uploads/..." oninput="onModalUrlTyped(this.value)">
                            </div>

                            <!-- Attached File Display Pill (Clean Rounded-Pill) -->
                            <div id="modalAttachedDisplay" class="d-none mt-2.5 p-2 px-3 rounded-pill bg-white border border-success d-flex align-items-center justify-content-between gap-2 shadow-xs text-start">
                                <div class="d-flex align-items-center gap-2 text-truncate">
                                    <span class="badge bg-success text-white rounded-pill px-2.5 py-1"><i class="bi bi-check-lg me-1"></i> Attached</span>
                                    <span id="modalAttachedFilename" class="small fw-bold text-dark text-truncate" style="max-width: 280px;">slip.png</span>
                                    <span id="modalAttachedFilesize" class="badge bg-light text-muted border rounded-pill font-monospace">150 KB</span>
                                </div>
                                <div class="d-flex align-items-center gap-1.5">
                                    <button type="button" id="modalViewAttachedBtn" class="btn btn-xs btn-outline-success rounded-pill px-2.5 py-1 fw-semibold d-none" style="font-size: 0.72rem;" onclick="viewModalAttachedFile()"><i class="bi bi-eye me-1"></i> View</button>
                                    <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;" onclick="clearModalAttachedFile()"><i class="bi bi-trash3 me-1"></i> Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rejection Reason (Mandatory when Rejected) -->
                    <div class="mb-3 d-none" id="modalRejectionBox">
                        <label class="form-label fw-bold text-danger small mb-1">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Rejection Reason *
                        </label>
                        <input type="text" name="rejection_reason" id="modalProcRejectionReason" class="form-control border-danger rounded-3 py-2 px-3" placeholder="Please state clear reason for rejection...">
                        <div class="form-text text-danger" style="font-size: 0.72rem;">Mandatory when status is marked as REJECTED.</div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer bg-light py-2.5 px-4 d-flex align-items-center justify-content-between">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-4 py-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="modalProcSubmitBtn" class="btn btn-sm btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm" style="background:#2563eb; border-color:#2563eb;">
                        <i class="bi bi-check-circle-fill me-1"></i> Update & Notify Retailer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="receivingViewerModal" tabindex="-1" aria-labelledby="receivingViewerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header py-2.5 px-3 px-md-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="p-2 rounded-circle bg-success-subtle text-success">
                        <i class="bi bi-file-earmark-check-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0 d-flex align-items-center gap-2" id="receivingViewerModalTitle">
                            <span>रसीद / Receiving Slip</span>
                            <span id="recViewerAppRefBadge" class="badge bg-dark font-monospace rounded-pill" style="font-size:0.75rem;">#REF</span>
                        </h6>
                        <div class="text-muted small" style="font-size:0.75rem;">
                            Applicant: <strong id="recViewerApplicantName" class="text-dark">Applicant</strong>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <!-- Print Button -->
                    <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 py-1.5 fw-bold shadow-2xs d-inline-flex align-items-center gap-1.5" onclick="printReceivingSlip()">
                        <i class="bi bi-printer-fill text-primary"></i> <span>Print / प्रिंट</span>
                    </button>
                    <!-- Download Button -->
                    <a id="recViewerDownloadBtn" href="" download class="btn btn-sm btn-success rounded-pill px-3 py-1.5 fw-bold text-white shadow-2xs d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-download"></i> <span>Download</span>
                    </a>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Modal Body (Preview) -->
            <div class="modal-body p-2 p-md-3 bg-light text-center position-relative" style="min-height: 60vh;">
                <!-- PDF Viewer (iframe) -->
                <iframe id="recViewerIframe" src="" class="d-none w-100 rounded-3 shadow-xs" style="height: 75vh; border: 1px solid #cbd5e1; background: #ffffff;"></iframe>

                <!-- Image Viewer -->
                <div id="recViewerImageContainer" class="d-none py-2 text-center" style="max-height: 78vh; overflow-y: auto;">
                    <img id="recViewerImage" src="" class="img-fluid rounded-3 shadow-sm border" style="max-height: 74vh; object-fit: contain; background: #ffffff;" alt="Receiving Slip">
                </div>

                <!-- Fallback / Loading -->
                <div id="recViewerLoading" class="text-center py-5 text-muted">
                    <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                    <span>Loading document...</span>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer py-2 px-3 px-md-4 bg-white border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="text-muted small" style="font-size: 0.76rem;">
                    <i class="bi bi-patch-check-fill text-success me-1"></i> Print या Download करके ग्राहक (Customer) को रसीद दे सकते हैं।
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3.5 py-1.5 fw-semibold" onclick="printReceivingSlip()">
                        <i class="bi bi-printer me-1"></i> Print Slip
                    </button>
                    <a id="recViewerDownloadBtnFooter" href="" download class="btn btn-sm btn-success rounded-pill px-3.5 py-1.5 fw-bold text-white shadow-xs">
                        <i class="bi bi-download me-1"></i> Download File
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3 py-1.5" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox Modal for Image Inspection -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="imageViewerTitle">Image Preview</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 text-center bg-black">
                <img id="imageViewerTarget" src="" class="img-fluid" style="max-height: 80vh;" alt="Preview">
            </div>
            <div class="modal-footer py-2 bg-dark border-0 justify-content-between">
                <a id="imageViewerDownload" href="" download class="btn btn-sm btn-outline-light">
                    <i class="bi bi-download me-1"></i> Download
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- UTI Photo & Signature Crop Modal Component -->
<?php require dirname(__DIR__) . '/layouts/uti_crop_modal.php'; ?>

<!-- Client-side Scripts for Pan Card List -->
<script>
function openImageModal(imgUrl, title) {
    document.getElementById('imageViewerTarget').src = imgUrl;
    document.getElementById('imageViewerTitle').textContent = title || 'Image Viewer';
    document.getElementById('imageViewerDownload').href = imgUrl;
    const modal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
    modal.show();
}

// Live Filter Search in Previous Requests
function handleFilterSearch(query) {
    query = (query || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.req-card');
    cards.forEach(card => {
        const ref = (card.getAttribute('data-ref') || '').toLowerCase();
        const name = (card.getAttribute('data-name') || '').toLowerCase();
        const aadhar = (card.getAttribute('data-aadhar') || '').toLowerCase();
        if (query === '' || ref.includes(query) || name.includes(query) || aadhar.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// ==========================================
// CARD SIGNATURE LIVE CONTROLS & 600 DPI DOWNLOAD
// ==========================================
window.cardSignDebounce = {};

function toggleCardSignControls(appId) {
    const box = document.getElementById('card_sign_controls_' + appId);
    if (!box) return;
    if (box.classList.contains('d-none')) {
        box.classList.remove('d-none');
        // Render initial canvas preview if not rendered yet
        const canvas = document.getElementById('card_sign_canvas_' + appId);
        if (canvas && canvas.classList.contains('d-none')) {
            const origImg = document.getElementById('card_sign_img_' + appId);
            if (origImg && origImg.src) {
                renderCardSignPreview(appId, origImg.src);
            }
        }
    } else {
        box.classList.add('d-none');
    }
}

function onCardSignSliderInput(appId, origUrl) {
    const whiteVal = document.getElementById('card_white_slider_' + appId)?.value || 195;
    const darkVal = document.getElementById('card_dark_slider_' + appId)?.value || 80;
    
    const wText = document.getElementById('card_white_val_' + appId);
    if (wText) wText.textContent = whiteVal;
    const dText = document.getElementById('card_dark_val_' + appId);
    if (dText) dText.textContent = darkVal;

    clearTimeout(window.cardSignDebounce[appId]);
    window.cardSignDebounce[appId] = setTimeout(() => {
        renderCardSignPreview(appId, origUrl);
    }, 100);
}

function resetCardSignSliders(appId, origUrl) {
    const wSlider = document.getElementById('card_white_slider_' + appId);
    const dSlider = document.getElementById('card_dark_slider_' + appId);
    const modeColor = document.getElementById('card_mode_color_' + appId);

    if (wSlider) wSlider.value = 195;
    if (dSlider) dSlider.value = 80;
    if (modeColor) modeColor.checked = false;

    const wText = document.getElementById('card_white_val_' + appId);
    if (wText) wText.textContent = '195';
    const dText = document.getElementById('card_dark_val_' + appId);
    if (dText) dText.textContent = '80';

    renderCardSignPreview(appId, origUrl);
}

async function renderCardSignPreview(appId, origUrl) {
    if (!origUrl || !window.UtiCropEngine) return;
    const whiteVal = Number(document.getElementById('card_white_slider_' + appId)?.value || 195);
    const darkVal = Number(document.getElementById('card_dark_slider_' + appId)?.value || 80);
    const modeColor = document.getElementById('card_mode_color_' + appId)?.checked;
    const mode = modeColor ? 'color' : 'bw';

    try {
        const res = await window.UtiCropEngine.processSignature(origUrl, {
            whiteLevel: whiteVal,
            darkLevel: darkVal,
            contrast: 1.45,
            mode: mode
        });

        window['card_sign_blob_' + appId] = res.blob;
        window['card_sign_dataurl_' + appId] = res.dataUrl;

        const canvas = document.getElementById('card_sign_canvas_' + appId);
        const origImg = document.getElementById('card_sign_img_' + appId);

        if (canvas) {
            canvas.width = 400;
            canvas.height = 200;
            const ctx = canvas.getContext('2d');
            const img = new Image();
            img.onload = function() {
                ctx.clearRect(0, 0, 400, 200);
                ctx.drawImage(img, 0, 0);
                canvas.classList.remove('d-none');
                if (origImg) origImg.classList.add('d-none');
            };
            img.src = res.dataUrl;
        }
    } catch (e) {
        console.error('Error rendering card signature:', e);
    }
}

async function downloadCardSignature(appId, applicantName, btn) {
    const origImg = document.getElementById('card_sign_img_' + appId);
    const origUrl = origImg ? origImg.src : null;
    const safeName = (applicantName || 'Applicant').replace(/[^a-zA-Z0-9_-]/g, '_');
    const filename = `UTI_Signature_${safeName}_400x200_600dpi_Fresh.jpg`;

    let origHtml = '';
    if (btn) {
        origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Fresh...';
    }

    try {
        // If modified blob is already in memory from slider adjustment, download it!
        if (window['card_sign_blob_' + appId]) {
            window.UtiCropEngine.downloadBlob(window['card_sign_blob_' + appId], filename);
            if (window.showToast) {
                window.showToast('✓ Fresh 600 DPI Signature downloaded!', 'success');
            }
            return;
        }

        // Otherwise generate with current slider values or optimal defaults
        if (!origUrl) throw new Error('Signature image URL missing');
        const whiteVal = Number(document.getElementById('card_white_slider_' + appId)?.value || 195);
        const darkVal = Number(document.getElementById('card_dark_slider_' + appId)?.value || 80);
        const modeColor = document.getElementById('card_mode_color_' + appId)?.checked;
        const mode = modeColor ? 'color' : 'bw';

        const res = await window.UtiCropEngine.processSignature(origUrl, {
            whiteLevel: whiteVal,
            darkLevel: darkVal,
            contrast: 1.45,
            mode: mode
        });

        window['card_sign_blob_' + appId] = res.blob;
        window['card_sign_dataurl_' + appId] = res.dataUrl;
        window.UtiCropEngine.downloadBlob(res.blob, filename);

        if (window.showToast) {
            window.showToast(`✓ UTI Signature downloaded (${res.width}x${res.height} @ ${res.dpi} DPI, Fresh, ${res.sizeKb} KB)`, 'success');
        }
    } catch (err) {
        console.warn('Fallback to standard signature download:', err);
        if (origUrl) {
            window.downloadUtiSignature(origUrl, applicantName, btn);
        } else {
            alert('Unable to download signature: ' + err.message);
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
}

function viewCardSignPreview(appId) {
    const dataUrl = window['card_sign_dataurl_' + appId];
    if (dataUrl) {
        openImageModal(dataUrl, 'Fresh Signature Preview (400x200 @ 600 DPI)');
    } else {
        const origImg = document.getElementById('card_sign_img_' + appId);
        if (origImg) {
            openImageModal(origImg.src, 'Signature Preview');
        }
    }
}

function scrollToAdminPanel(appId) {
    const panel = document.getElementById('admin_panel_' + appId);
    if (panel) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        panel.classList.add('border-primary', 'shadow-sm');
        setTimeout(() => {
            panel.classList.remove('border-primary', 'shadow-sm');
        }, 1500);
    } else if (typeof openDetailsModal === 'function') {
        openDetailsModal(appId);
    }
}

function toggleRejectionBox(appId, status) {
    const box = document.getElementById('rej_reason_box_' + appId);
    if (!box) return;
    if (status === 'REJECTED') {
        box.classList.remove('d-none');
        const input = box.querySelector('input[name="rejection_reason"]');
        if (input) input.focus();
    } else {
        box.classList.add('d-none');
    }
}

// ========================================================
// Process / Status Modal & Ctrl + V Paste Handlers (Image 2)
// ========================================================

function openProcessStatusModal(appId) {
    const card = document.getElementById('row-app-' + appId);
    if (!card) {
        console.error('Card not found for app ID:', appId);
        return;
    }

    let appData = {};
    try {
        const rawJson = card.getAttribute('data-app');
        if (rawJson) {
            appData = JSON.parse(rawJson);
        }
    } catch (e) {
        console.warn('Failed to parse data-app JSON:', e);
    }

    document.getElementById('modalProcAppId').value = appId;
    document.getElementById('modalProcTitleRef').textContent = '#' + (appData.app_ref || card.getAttribute('data-ref') || appId);

    const st = (appData.status || 'PENDING').toUpperCase();
    const statusSelect = document.getElementById('modalProcStatus');
    if (statusSelect) {
        statusSelect.value = st;
        onModalStatusChange(st);
    }

    document.getElementById('modalProcRemarks').value = appData.admin_remarks || appData.remarks || '';
    document.getElementById('modalProcRejectionReason').value = appData.rejection_reason || '';

    // Handle initial result file if already attached
    clearModalAttachedFile();
    if (appData.result_file) {
        document.getElementById('modalResultFileUrl').value = appData.result_file;
        showModalAttachedPill(appData.result_file, 'Existing Output File', 'url');
    }

    const modalEl = document.getElementById('processStatusModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function onModalStatusChange(statusVal) {
    statusVal = (statusVal || '').toUpperCase();
    const rejBox = document.getElementById('modalRejectionBox');
    if (rejBox) {
        if (statusVal === 'REJECTED') {
            rejBox.classList.remove('d-none');
            setTimeout(() => {
                document.getElementById('modalProcRejectionReason')?.focus();
            }, 100);
        } else {
            rejBox.classList.add('d-none');
        }
    }

    // Dynamic border color based on state (state ke hisab se form ka border color)
    const modalContent = document.getElementById('processModalContent');
    if (modalContent) {
        let borderColor = '#f59e0b'; // Amber for PENDING
        if (statusVal === 'COMPLETED' || statusVal === 'APPROVED') {
            borderColor = '#22c55e'; // Green for COMPLETED
        } else if (statusVal === 'IN_PROCESS' || statusVal === 'UNDER_REVIEW') {
            borderColor = '#3b82f6'; // Blue for IN_PROCESS
        } else if (statusVal === 'REJECTED') {
            borderColor = '#ef4444'; // Red for REJECTED
        }
        modalContent.style.border = '2px solid ' + borderColor;
        modalContent.style.borderTop = '6px solid ' + borderColor;
    }
}

function triggerModalFileInput() {
    document.getElementById('modalResultFileInput')?.click();
}

function onModalFileSelected(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];

    if (file.size > 10 * 1024 * 1024) {
        alert('Result file size exceeds 10MB limit. Please choose a smaller file.');
        input.value = '';
        return;
    }

    // Reset base64 and url input
    document.getElementById('modalResultFileBase64').value = '';
    document.getElementById('modalResultFileUrl').value = '';

    showModalAttachedPill(file.name, (file.size / 1024).toFixed(1) + ' KB', 'file');
}

function onModalUrlTyped(url) {
    const trimmed = (url || '').trim();
    if (trimmed.length > 0) {
        const fileInput = document.getElementById('modalResultFileInput');
        if (fileInput) fileInput.value = '';
        document.getElementById('modalResultFileBase64').value = '';
        showModalAttachedPill(trimmed, 'Direct URL / File', 'url');
    } else {
        clearModalAttachedFile();
    }
}

let currentAttachedResultUrl = null;

function clearModalAttachedFile() {
    const fileInput = document.getElementById('modalResultFileInput');
    if (fileInput) fileInput.value = '';
    const b64Input = document.getElementById('modalResultFileBase64');
    if (b64Input) b64Input.value = '';
    const urlInput = document.getElementById('modalResultFileUrl');
    if (urlInput) urlInput.value = '';

    currentAttachedResultUrl = null;
    const viewBtn = document.getElementById('modalViewAttachedBtn');
    if (viewBtn) viewBtn.classList.add('d-none');

    const pill = document.getElementById('modalAttachedDisplay');
    if (pill) pill.classList.add('d-none');
}

function showModalAttachedPill(name, sizeStr, type, directUrl = null) {
    const pill = document.getElementById('modalAttachedDisplay');
    const nameEl = document.getElementById('modalAttachedFilename');
    const sizeEl = document.getElementById('modalAttachedFilesize');
    const viewBtn = document.getElementById('modalViewAttachedBtn');

    if (nameEl) nameEl.textContent = name;
    if (sizeEl) sizeEl.textContent = sizeStr;

    currentAttachedResultUrl = directUrl || (type === 'url' ? name : null);
    if (viewBtn) {
        if (currentAttachedResultUrl) {
            viewBtn.classList.remove('d-none');
        } else {
            viewBtn.classList.add('d-none');
        }
    }
    if (pill) pill.classList.remove('d-none');
}

function viewModalAttachedFile() {
    if (currentAttachedResultUrl) {
        const ref = (document.getElementById('modalProcTitleRef')?.textContent || '').replace('#', '').trim();
        openReceivingModal(currentAttachedResultUrl, ref, 'Attached Output');
    }
}

function applyPastedResultImage(dataUrl, fileSize, defaultName) {
    document.getElementById('modalResultFileBase64').value = dataUrl;
    const fileInput = document.getElementById('modalResultFileInput');
    if (fileInput) fileInput.value = '';
    document.getElementById('modalResultFileUrl').value = '';

    const safeName = defaultName || ('Pasted_Screenshot_' + Date.now() + '.png');
    showModalAttachedPill(safeName, (fileSize / 1024).toFixed(1) + ' KB', 'paste');

    const zone = document.getElementById('modalPasteDropZone');
    if (zone) {
        zone.style.borderColor = '#22c55e';
        zone.style.backgroundColor = '#ecfdf5';
        setTimeout(() => {
            zone.style.borderColor = '#a855f7';
            zone.style.backgroundColor = '#faf5ff';
        }, 1200);
    }

    if (window.showToast) {
        window.showToast('✓ Screenshot pasted successfully from clipboard!', 'success');
    }
}

async function triggerClipboardRead() {
    try {
        if (!navigator.clipboard || !navigator.clipboard.read) {
            alert('Please press Ctrl + V on your keyboard anywhere to paste the screenshot directly!');
            return;
        }
        const clipboardItems = await navigator.clipboard.read();
        let found = false;
        for (const item of clipboardItems) {
            for (const type of item.types) {
                if (type.startsWith('image/')) {
                    const blob = await item.getType(type);
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        applyPastedResultImage(evt.target.result, blob.size, 'Pasted_Screenshot_' + Date.now() + '.png');
                    };
                    reader.readAsDataURL(blob);
                    found = true;
                    break;
                }
            }
            if (found) break;
        }
        if (!found) {
            alert('No image found on clipboard. Please copy an image or take a screenshot first, then press Ctrl + V or click Paste!');
        }
    } catch (err) {
        console.warn('Clipboard read failed, asking user to use Ctrl+V:', err);
        alert('Clipboard permission denied or not supported. Please simply press Ctrl + V on your keyboard to paste the copied screenshot!');
    }
}

// Global Ctrl + V event listener for pasting screenshots into modal
document.addEventListener('paste', function(e) {
    const modalEl = document.getElementById('processStatusModal');
    if (!modalEl || !modalEl.classList.contains('show')) return;

    const items = (e.clipboardData || window.clipboardData)?.items;
    if (!items) return;

    for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            e.preventDefault();
            const blob = items[i].getAsFile();
            if (!blob) continue;

            const reader = new FileReader();
            reader.onload = function(evt) {
                applyPastedResultImage(evt.target.result, blob.size, 'Pasted_Screenshot_' + Date.now() + '.png');
            };
            reader.readAsDataURL(blob);
            return;
        }
    }
});

// Drag & drop support on modal paste dropzone
(function initModalDragAndDrop() {
    const zone = document.getElementById('modalPasteDropZone');
    if (!zone) return;

    ['dragenter', 'dragover'].forEach(evt => {
        zone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.style.borderColor = '#22c55e';
            zone.style.backgroundColor = '#ecfdf5';
        }, false);
    });

    ['dragleave', 'drop'].forEach(evt => {
        zone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.style.borderColor = '#a855f7';
            zone.style.backgroundColor = '#faf5ff';
        }, false);
    });

    zone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt?.files;
        if (files && files.length > 0) {
            const fileInput = document.getElementById('modalResultFileInput');
            if (fileInput) {
                fileInput.files = files;
                onModalFileSelected(fileInput);
            }
        }
    }, false);
})();

// Process Form Submit validation
document.getElementById('modalProcessForm')?.addEventListener('submit', function(e) {
    const statusVal = document.getElementById('modalProcStatus')?.value;
    const rejReason = document.getElementById('modalProcRejectionReason')?.value?.trim();
    if (statusVal === 'REJECTED' && !rejReason) {
        e.preventDefault();
        alert('Please enter a rejection reason so the retailer understands why the request was rejected.');
        document.getElementById('modalProcRejectionReason')?.focus();
        return false;
    }
    const btn = document.getElementById('modalProcSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Updating & Notifying...';
    }
});

// ========================================================
// Receiving Slip Viewer Modal Handlers (View, Print & Download for PDF & Image)
// ========================================================

function openReceivingModal(fileUrl, appRef, applicantName) {
    if (!fileUrl) {
        alert('रसीद उपलब्ध नहीं है (No receiving slip found).');
        return;
    }

    const refBadge = document.getElementById('recViewerAppRefBadge');
    if (refBadge) refBadge.textContent = appRef ? ('#' + appRef) : '#REF';

    const nameEl = document.getElementById('recViewerApplicantName');
    if (nameEl) nameEl.textContent = applicantName || 'Applicant';

    const safeRef = (appRef || 'Slip').replace(/[^a-zA-Z0-9_-]/g, '_');
    const cleanUrl = fileUrl.split('?')[0].toLowerCase();
    const isPdf = cleanUrl.endsWith('.pdf');
    const downloadFilename = isPdf ? `Receiving_${safeRef}.pdf` : `Receiving_${safeRef}.png`;

    const downloadBtnHeader = document.getElementById('recViewerDownloadBtn');
    if (downloadBtnHeader) {
        downloadBtnHeader.href = fileUrl;
        downloadBtnHeader.download = downloadFilename;
    }

    const downloadBtnFooter = document.getElementById('recViewerDownloadBtnFooter');
    if (downloadBtnFooter) {
        downloadBtnFooter.href = fileUrl;
        downloadBtnFooter.download = downloadFilename;
    }

    const iframe = document.getElementById('recViewerIframe');
    const imgContainer = document.getElementById('recViewerImageContainer');
    const img = document.getElementById('recViewerImage');
    const loading = document.getElementById('recViewerLoading');

    if (loading) {
        loading.classList.remove('d-none');
        loading.innerHTML = '<div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div><span>दस्तावेज लोड हो रहा है...</span>';
    }
    if (iframe) iframe.classList.add('d-none');
    if (imgContainer) imgContainer.classList.add('d-none');

    if (isPdf) {
        if (iframe) {
            iframe.src = fileUrl + '#toolbar=1';
            iframe.onload = function() {
                if (loading) loading.classList.add('d-none');
                iframe.classList.remove('d-none');
            };
        }
    } else {
        if (img && imgContainer) {
            img.src = fileUrl;
            img.onload = function() {
                if (loading) loading.classList.add('d-none');
                imgContainer.classList.remove('d-none');
            };
            img.onerror = function() {
                if (loading) loading.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> रसीद इमेज लोड नहीं हो सकी।</span>';
            };
        }
    }

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('receivingViewerModal'));
    modal.show();
}

function printReceivingSlip() {
    const fileUrl = document.getElementById('recViewerDownloadBtn')?.href;
    if (!fileUrl) {
        alert('Print करने के लिए रसीद उपलब्ध नहीं है।');
        return;
    }

    const cleanUrl = fileUrl.split('?')[0].toLowerCase();
    const isPdf = cleanUrl.endsWith('.pdf');

    if (isPdf) {
        const iframe = document.getElementById('recViewerIframe');
        if (iframe && iframe.contentWindow) {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                return;
            } catch (e) {
                console.warn('Iframe print access restricted, opening direct print tab:', e);
            }
        }
        const win = window.open(fileUrl, '_blank');
        if (win) {
            win.focus();
            setTimeout(() => { try { win.print(); } catch(e){} }, 1000);
        }
    } else {
        // Image print in clean pop-up window
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            alert('Pop-up ब्लॉक है। प्रिंट करने के लिए ब्राउज़र में Pop-ups को allow करें।');
            return;
        }
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Receiving Slip Print</title>
                <style>
                    @page { margin: 8mm; size: auto; }
                    body {
                        margin: 0;
                        padding: 10px;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 95vh;
                        background: #fff;
                        font-family: system-ui, -apple-system, sans-serif;
                    }
                    img {
                        max-width: 100%;
                        max-height: 98vh;
                        object-fit: contain;
                        display: block;
                        margin: 0 auto;
                    }
                </style>
            </head>
            <body>
                <img src="${fileUrl}" onload="window.print(); setTimeout(() => window.close(), 1200);" onerror="alert('Failed to load image for printing.'); window.close();" />
            </body>
            </html>
        `);
        printWindow.document.close();
    }
}
</script>

<?php if ($isStaffOrAdmin): ?>
    <?php require dirname(__DIR__) . '/admin/applications_modals.php'; ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
