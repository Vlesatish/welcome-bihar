<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Light Colorful Glassmorphism Dashboard Styles -->
<style>
.wb-glass-dashboard {
    position: relative;
    border-radius: 18px;
    padding: 0.25rem 0;
}
/* Subtle ambient background glow mesh to enhance glass refraction */
.wb-glass-dashboard::before {
    content: '';
    position: absolute;
    top: -10px;
    left: -10px;
    right: -10px;
    bottom: -10px;
    background: 
        radial-gradient(circle at 5% 10%, rgba(59, 130, 246, 0.12) 0%, transparent 35%),
        radial-gradient(circle at 35% 15%, rgba(139, 92, 246, 0.10) 0%, transparent 35%),
        radial-gradient(circle at 65% 15%, rgba(16, 185, 129, 0.10) 0%, transparent 35%),
        radial-gradient(circle at 95% 10%, rgba(245, 158, 11, 0.12) 0%, transparent 35%),
        radial-gradient(circle at 50% 85%, rgba(59, 130, 246, 0.05) 0%, transparent 50%);
    pointer-events: none;
    z-index: 0;
}
.wb-glass-dashboard > * {
    position: relative;
    z-index: 1;
}

/* Greeting Glass Header */
.dash-glass-banner {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.88) 0%, rgba(248, 250, 252, 0.72) 100%);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 16px;
    padding: 1.15rem 1.4rem;
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

/* Base Glass Stat Card */
.dash-glass-card {
    border-radius: 16px;
    padding: 1.15rem 1.25rem;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}
.dash-glass-card:hover {
    transform: translateY(-3px);
}

/* 1. Wallet Balance - Soft Cyan/Blue Glass */
.card-glass-blue {
    background: linear-gradient(135deg, rgba(239, 246, 255, 0.88) 0%, rgba(219, 234, 254, 0.6) 100%);
    border: 1px solid rgba(191, 219, 254, 0.85);
    box-shadow: 0 10px 24px -4px rgba(37, 99, 235, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}
.card-glass-blue:hover {
    border-color: rgba(147, 197, 253, 0.95);
    box-shadow: 0 14px 30px -4px rgba(37, 99, 235, 0.2), inset 0 1px 0 rgba(255, 255, 255, 1);
}
.card-glass-blue .stat-icon-glass {
    background: rgba(37, 99, 235, 0.14);
    color: #1d4ed8;
    border: 1px solid rgba(191, 219, 254, 0.9);
}

/* 2. Total Applications - Soft Violet/Purple Glass */
.card-glass-purple {
    background: linear-gradient(135deg, rgba(245, 243, 255, 0.88) 0%, rgba(237, 233, 254, 0.6) 100%);
    border: 1px solid rgba(221, 214, 254, 0.85);
    box-shadow: 0 10px 24px -4px rgba(124, 58, 237, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}
.card-glass-purple:hover {
    border-color: rgba(196, 181, 253, 0.95);
    box-shadow: 0 14px 30px -4px rgba(124, 58, 237, 0.2), inset 0 1px 0 rgba(255, 255, 255, 1);
}
.card-glass-purple .stat-icon-glass {
    background: rgba(124, 58, 237, 0.14);
    color: #6d28d9;
    border: 1px solid rgba(221, 214, 254, 0.9);
}

/* 3. Approved - Soft Emerald/Mint Glass */
.card-glass-emerald {
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.88) 0%, rgba(209, 250, 229, 0.6) 100%);
    border: 1px solid rgba(167, 243, 208, 0.85);
    box-shadow: 0 10px 24px -4px rgba(16, 185, 129, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}
.card-glass-emerald:hover {
    border-color: rgba(110, 231, 183, 0.95);
    box-shadow: 0 14px 30px -4px rgba(16, 185, 129, 0.2), inset 0 1px 0 rgba(255, 255, 255, 1);
}
.card-glass-emerald .stat-icon-glass {
    background: rgba(16, 185, 129, 0.14);
    color: #047857;
    border: 1px solid rgba(167, 243, 208, 0.9);
}

/* 4. Pending / Review - Soft Amber/Gold Glass */
.card-glass-amber {
    background: linear-gradient(135deg, rgba(255, 251, 235, 0.88) 0%, rgba(254, 243, 199, 0.6) 100%);
    border: 1px solid rgba(253, 230, 138, 0.85);
    box-shadow: 0 10px 24px -4px rgba(217, 119, 6, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}
.card-glass-amber:hover {
    border-color: rgba(252, 211, 77, 0.95);
    box-shadow: 0 14px 30px -4px rgba(217, 119, 6, 0.2), inset 0 1px 0 rgba(255, 255, 255, 1);
}
.card-glass-amber .stat-icon-glass {
    background: rgba(217, 119, 6, 0.14);
    color: #b45309;
    border: 1px solid rgba(253, 230, 138, 0.9);
}

.stat-icon-glass {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.stat-label-text {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #475569;
}
.stat-val-text {
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.5px;
}

/* Glass Main Container Panels */
.dash-glass-panel {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 18px;
    box-shadow: 0 12px 32px -4px rgba(15, 23, 42, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.95);
    overflow: hidden;
}
.dash-glass-panel-header {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.75) 100%);
    border-bottom: 1px solid rgba(226, 232, 240, 0.7);
    padding: 1rem 1.35rem;
}
.dash-glass-panel-footer {
    background: rgba(248, 250, 252, 0.75);
    border-top: 1px solid rgba(226, 232, 240, 0.7);
}

.table-glass-row:hover td {
    background-color: rgba(239, 246, 255, 0.5) !important;
}

.tx-glass-item {
    transition: all 0.15s ease;
    background: transparent;
}
.tx-glass-item:hover {
    background: rgba(241, 245, 249, 0.6) !important;
}

.btn-xs {
    padding: 0.25rem 0.6rem;
    font-size: 0.72rem;
    line-height: 1.25;
}
</style>

<div class="wb-glass-dashboard">
    <!-- User Greeting Banner -->
    <div class="dash-glass-banner mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 34px; height: 34px; background: rgba(37, 99, 235, 0.12); color: #2563eb;">
                    <i class="bi bi-speedometer2"></i>
                </span>
                <?= e(__('dashboard')) ?>
            </h1>
            <p class="text-muted small mb-0 ms-1">
                Welcome back, <strong class="text-dark"><?= e($currentUser['name']) ?></strong> &bull; <span class="text-secondary"><?= e($currentUser['center_name'] ?: 'Kiosk Operator') ?></span>
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="/wallet/topup" class="btn btn-warning fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-1.5" style="font-size: 0.82rem; background: linear-gradient(135deg, #f59e0b, #d97706); border: none; color: #ffffff;">
                <i class="bi bi-qr-code-scan"></i> <?= e(__('topup_wallet')) ?>
            </a>
            <a href="/applications/new" class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-1.5" style="font-size: 0.82rem; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;">
                <i class="bi bi-plus-lg"></i> <?= e(__('new_application')) ?>
            </a>
        </div>
    </div>

    <!-- Quick Light Colorful Glass Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Wallet Balance (Blue Glass) -->
        <div class="col-sm-6 col-lg-3">
            <div class="dash-glass-card card-glass-blue h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-label-text"><?= e(__('wallet_balance')) ?></span>
                    <div class="stat-icon-glass">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-text font-monospace" style="color: #1d4ed8;">₹<?= number_format($balance, 2) ?></div>
                </div>
                <div class="mt-3 pt-2 d-flex align-items-center justify-content-between" style="border-top: 1px solid rgba(191, 219, 254, 0.7);">
                    <a href="/wallet/passbook" class="small fw-bold text-decoration-none" style="font-size: 0.76rem; color: #1d4ed8;">
                        <?= e(__('view_details')) ?> &rarr;
                    </a>
                    <span class="badge rounded-pill px-2.5 py-1" style="font-size: 0.68rem; background: rgba(37, 99, 235, 0.15); color: #1e40af; border: 1px solid rgba(191, 219, 254, 0.8);">Active</span>
                </div>
            </div>
        </div>

        <!-- 2. Total Applications (Purple Glass) -->
        <div class="col-sm-6 col-lg-3">
            <div class="dash-glass-card card-glass-purple h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-label-text"><?= e(__('applications')) ?></span>
                    <div class="stat-icon-glass">
                        <i class="bi bi-files"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-text" style="color: #6d28d9;"><?= number_format((int)($stats['total'] ?? 0)) ?></div>
                </div>
                <div class="mt-3 pt-2 d-flex align-items-center justify-content-between" style="border-top: 1px solid rgba(221, 214, 254, 0.7);">
                    <a href="/applications" class="small fw-bold text-decoration-none" style="font-size: 0.76rem; color: #6d28d9;">
                        <?= e(__('application_list')) ?> &rarr;
                    </a>
                    <span class="badge rounded-pill px-2.5 py-1" style="font-size: 0.68rem; background: rgba(124, 58, 237, 0.12); color: #5b21b6; border: 1px solid rgba(221, 214, 254, 0.8);">Lifetime</span>
                </div>
            </div>
        </div>

        <!-- 3. Approved Applications (Emerald Glass) -->
        <div class="col-sm-6 col-lg-3">
            <div class="dash-glass-card card-glass-emerald h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-label-text"><?= e(__('status_approved')) ?></span>
                    <div class="stat-icon-glass">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-text" style="color: #047857;"><?= number_format((int)($stats['approved'] ?? 0)) ?></div>
                </div>
                <div class="mt-3 pt-2 d-flex align-items-center justify-content-between" style="border-top: 1px solid rgba(167, 243, 208, 0.7);">
                    <a href="/applications?status=COMPLETED" class="small fw-bold text-decoration-none" style="font-size: 0.76rem; color: #047857;">
                        View Approved &rarr;
                    </a>
                    <span class="badge rounded-pill px-2.5 py-1" style="font-size: 0.68rem; background: rgba(16, 185, 129, 0.15); color: #065f46; border: 1px solid rgba(167, 243, 208, 0.8);">Ready</span>
                </div>
            </div>
        </div>

        <!-- 4. Pending & Under Review (Amber Glass) -->
        <div class="col-sm-6 col-lg-3">
            <div class="dash-glass-card card-glass-amber h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-label-text"><?= e(__('status_pending')) ?> / Review</span>
                    <div class="stat-icon-glass">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-text" style="color: #b45309;"><?= number_format((int)($stats['pending'] ?? 0) + (int)($stats['under_review'] ?? 0)) ?></div>
                </div>
                <div class="mt-3 pt-2 d-flex align-items-center justify-content-between" style="border-top: 1px solid rgba(253, 230, 138, 0.7);">
                    <div class="d-flex gap-1">
                        <span class="badge rounded-pill px-2 py-0.5" style="font-size: 0.68rem; background: rgba(245, 158, 11, 0.15); color: #92400e; border: 1px solid rgba(253, 230, 138, 0.8);"><?= (int)($stats['pending'] ?? 0) ?> Pending</span>
                        <span class="badge rounded-pill px-2 py-0.5" style="font-size: 0.68rem; background: rgba(14, 165, 233, 0.15); color: #0369a1; border: 1px solid rgba(186, 230, 253, 0.8);"><?= (int)($stats['under_review'] ?? 0) ?> Review</span>
                    </div>
                    <a href="/applications?status=PENDING" class="small fw-bold text-decoration-none" style="font-size: 0.76rem; color: #b45309;">
                        &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

<!-- Main Content: Recent Applications (Left) + Wallet Passbook (Right) -->
<div class="row g-4">
    <!-- Recent Applications Table Card -->
    <div class="col-lg-8">
        <div class="dash-glass-panel mb-4">
            <div class="dash-glass-panel-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 30px; height: 30px; background: rgba(37, 99, 235, 0.12); color: #2563eb;">
                        <i class="bi bi-clock-history"></i>
                    </span>
                    <h2 class="h6 fw-bold mb-0 text-dark">Recent Applications</h2>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill font-monospace" style="font-size: 0.72rem; background: rgba(148, 163, 184, 0.15); color: #475569; border: 1px solid rgba(203, 213, 225, 0.7);"><?= count($recentApplications) ?> Recent</span>
                    <a href="/applications" class="btn btn-sm rounded-pill px-3 py-1 fw-semibold text-decoration-none" style="font-size: 0.75rem; background: rgba(37, 99, 235, 0.1); color: #1d4ed8; border: 1px solid rgba(191, 219, 254, 0.8);">
                        <?= e(__('application_list')) ?>
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentApplications)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 opacity-50 d-block mb-1"></i>
                        <h5 class="h6 fw-bold text-dark mb-1">No Applications Submitted</h5>
                        <p class="small text-muted mb-3">You have not submitted any applications yet.</p>
                        <a href="/applications/new" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;">
                            <i class="bi bi-plus-lg me-1"></i> Start New Application
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" style="background: transparent;">
                            <thead style="background: rgba(241, 245, 249, 0.75); border-bottom: 1px solid rgba(226, 232, 240, 0.8);" class="text-muted small text-uppercase fw-bold">
                                <tr>
                                    <th class="ps-3.5 py-2.5" style="font-size: 0.72rem; color: #475569;">Order Reference</th>
                                    <th class="py-2.5" style="font-size: 0.72rem; color: #475569;">Applicant</th>
                                    <th class="py-2.5" style="font-size: 0.72rem; color: #475569;">Application No.</th>
                                    <th class="py-2.5" style="font-size: 0.72rem; color: #475569;">Amount</th>
                                    <th class="py-2.5" style="font-size: 0.72rem; color: #475569;">Status</th>
                                    <th class="py-2.5" style="font-size: 0.72rem; color: #475569;">Date</th>
                                    <th class="text-end pe-3.5 py-2.5" style="font-size: 0.72rem; color: #475569;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentApplications as $app): ?>
                                    <?php
                                        $status = strtoupper($app['status'] ?? 'PENDING');
                                    ?>
                                    <tr class="table-glass-row" style="border-bottom: 1px solid rgba(226, 232, 240, 0.6); transition: background 0.15s ease;">
                                        <td class="ps-3.5 font-monospace small fw-bold text-dark">
                                            #<?= e($app['app_ref']) ?>
                                        </td>

                                        <td class="fw-bold text-dark">
                                            <?= e($app['applicant_name']) ?>
                                        </td>

                                        <td class="font-monospace small fw-semibold text-secondary">
                                            <?= e($app['applicant_identifier'] ?: '—') ?>
                                        </td>

                                        <td class="fw-bold text-dark">
                                            ₹<?= number_format($app['fee_debited'], 2) ?>
                                        </td>

                                        <td>
                                            <?php if ($status === 'COMPLETED' || $status === 'APPROVED'): ?>
                                                <span class="badge rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem; background: rgba(16, 185, 129, 0.15); color: #065f46; border: 1px solid rgba(167, 243, 208, 0.9);">
                                                    <i class="bi bi-check-circle-fill me-1 text-success"></i>COMPLETED
                                                </span>
                                            <?php elseif ($status === 'PENDING'): ?>
                                                <span class="badge rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem; background: rgba(245, 158, 11, 0.15); color: #92400e; border: 1px solid rgba(253, 230, 138, 0.9);">
                                                    <span class="spinner-grow spinner-grow-sm me-1" style="width: 6px; height: 6px;"></span>PENDING
                                                </span>
                                            <?php elseif ($status === 'IN_PROCESS' || $status === 'UNDER_REVIEW'): ?>
                                                <span class="badge rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem; background: rgba(14, 165, 233, 0.15); color: #0369a1; border: 1px solid rgba(186, 230, 253, 0.9);">
                                                    <i class="bi bi-gear-wide-connected me-1"></i>PROCESSING
                                                </span>
                                            <?php elseif ($status === 'REJECTED'): ?>
                                                <span class="badge rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem; background: rgba(239, 68, 68, 0.15); color: #991b1b; border: 1px solid rgba(254, 202, 202, 0.9);">
                                                    <i class="bi bi-x-circle-fill me-1 text-danger"></i>REJECTED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem; background: rgba(100, 116, 139, 0.12); color: #334155; border: 1px solid rgba(203, 213, 225, 0.8);">
                                                    <?= e($status) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="small text-muted" style="font-size: 0.75rem;">
                                            <?= date('d M, h:i A', strtotime($app['created_at'])) ?>
                                        </td>

                                        <td class="text-end pe-3.5">
                                            <a href="/applications" class="btn btn-xs fw-semibold rounded-pill px-2.5 py-1 text-decoration-none" style="font-size: 0.72rem; color: #b45309; border: 1px solid rgba(253, 230, 138, 0.9); background: rgba(254, 243, 199, 0.55);">
                                                More Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="dash-glass-panel-footer py-2.5 px-3 text-center">
                <a href="/applications" class="small fw-semibold text-decoration-none" style="color: #1d4ed8;">
                    View Complete Applications History &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Passbook Transactions Card -->
    <div class="col-lg-4">
        <div class="dash-glass-panel mb-4">
            <div class="dash-glass-panel-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 30px; height: 30px; background: rgba(16, 185, 129, 0.12); color: #059669;">
                        <i class="bi bi-receipt"></i>
                    </span>
                    <h2 class="h6 fw-bold mb-0 text-dark">Wallet Passbook</h2>
                </div>
                <a href="/wallet/passbook" class="btn btn-sm rounded-pill px-2.5 py-0.5 fw-semibold" style="font-size: 0.72rem; background: rgba(241, 245, 249, 0.9); border: 1px solid rgba(203, 213, 225, 0.8); color: #475569;">
                    <?= e(__('view_details')) ?>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($passbook['transactions'])): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-receipt-cutoff fs-2 opacity-50 d-block mb-1"></i>
                        <p class="small text-muted mb-0">No transactions recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($passbook['transactions'] as $tx): ?>
                            <?php
                                $isCredit = ($tx['direction'] === 'CREDIT');
                                $rawDesc = $tx['description_en'] ?: ($tx['description_hi'] ?: 'Wallet Transaction');
                                
                                // Clean human-friendly label
                                if (stripos($rawDesc, 'recharge') !== false || stripos($rawDesc, 'topup') !== false) {
                                    $title = 'UPI Wallet Recharge';
                                } elseif (stripos($rawDesc, 'application') !== false || stripos($rawDesc, 'service fee') !== false) {
                                    $title = 'Application Pass Fee';
                                } elseif (stripos($rawDesc, 'refund') !== false) {
                                    $title = 'Reversal / Refund';
                                } else {
                                    $title = $rawDesc;
                                }
                            ?>
                            <div class="list-group-item d-flex align-items-center justify-content-between py-2.5 px-3 tx-glass-item" style="border-bottom: 1px solid rgba(226, 232, 240, 0.6);">
                                <div class="d-flex align-items-center gap-2.5 overflow-hidden me-2">
                                    <div class="flex-shrink-0">
                                        <?php if ($isCredit): ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(167, 243, 208, 0.8);">
                                                <i class="bi bi-arrow-down-left" style="font-size: 0.85rem;"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(239, 68, 68, 0.12); color: #dc2626; border: 1px solid rgba(254, 202, 202, 0.8);">
                                                <i class="bi bi-arrow-up-right" style="font-size: 0.85rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-semibold text-dark text-truncate" style="font-size: 0.8rem;"><?= e($title) ?></div>
                                        <div class="text-muted text-truncate" style="font-size: 0.7rem;">
                                            <?= date('d M, h:i A', strtotime($tx['created_at'])) ?> &bull; <span class="font-monospace"><?= e($tx['txn_ref'] ?? '') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <div class="fw-bold font-monospace <?= $isCredit ? 'text-success' : 'text-danger' ?>" style="font-size: 0.85rem;">
                                        <?= $isCredit ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                                    </div>
                                    <div class="text-muted font-monospace" style="font-size: 0.68rem;">
                                        Bal: ₹<?= number_format($tx['balance_after'], 2) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="dash-glass-panel-footer p-2.5 text-center">
                <a href="/wallet/topup" class="btn btn-sm w-100 fw-bold rounded-3 py-2 d-flex align-items-center justify-content-center gap-1.5 shadow-sm" style="font-size: 0.8rem; background: linear-gradient(135deg, rgba(254, 243, 199, 0.9) 0%, rgba(253, 230, 138, 0.75) 100%); color: #92400e; border: 1px solid rgba(245, 158, 11, 0.35);">
                    <i class="bi bi-plus-circle"></i> Quick Top-Up Wallet
                </a>
            </div>
        </div>
    </div>
</div>
</div><!-- /wb-glass-dashboard -->

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
