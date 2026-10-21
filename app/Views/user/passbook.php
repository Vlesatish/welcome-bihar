<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Modern Clean Styles for Passbook -->
<style>
.dash-glass-card {
    border-radius: 16px;
    padding: 1.15rem 1.35rem;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}
.card-glass-blue {
    background: linear-gradient(135deg, rgba(239, 246, 255, 0.88) 0%, rgba(219, 234, 254, 0.6) 100%);
    border: 1px solid rgba(191, 219, 254, 0.85);
    box-shadow: 0 10px 24px -4px rgba(37, 99, 235, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}
.stat-icon-glass {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    background: rgba(37, 99, 235, 0.14);
    color: #1d4ed8;
    border: 1px solid rgba(191, 219, 254, 0.9);
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
.filter-ctrl, .filter-select, .filter-btn {
    height: 36px !important;
    font-size: 13px !important;
    border-radius: 6px !important;
}
.filter-ctrl:focus, .filter-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}
.table-passbook th {
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    background-color: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    padding: 0.65rem 0.75rem;
}
.table-passbook td {
    font-size: 0.82rem;
    vertical-align: middle;
    padding: 0.65rem 0.75rem;
    border-color: #f1f5f9;
}
.table-passbook tbody tr:hover {
    background-color: #f8fafc;
}
</style>

<!-- Title & Quick Actions -->
<div class="row align-items-center mb-3 g-2">
    <div class="col-md-6">
        <h1 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-wallet2 text-primary"></i>
            <span>Wallet Passbook</span>
            <span class="badge bg-primary-subtle text-primary border rounded-pill px-2.5 small"><?= number_format($totalRecords ?? count($transactions)) ?></span>
        </h1>
    </div>
    <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/wallet/passbook/export-csv?<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-success fw-semibold rounded-pill px-3 shadow-2xs">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
        <a href="/wallet/topup" class="btn btn-sm btn-warning fw-bold rounded-pill px-3 shadow-2xs text-dark" style="background: linear-gradient(135deg, #f59e0b, #d97706); border: none; color: #fff !important;">
            <i class="bi bi-qr-code-scan me-1"></i> Top Up
        </a>
    </div>
</div>

<!-- Compact Fresh Balance Card -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="dash-glass-card card-glass-blue">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="stat-label-text">Available Balance</span>
                <div class="stat-icon-glass">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
            <div class="stat-val-text font-monospace" style="color: #1d4ed8;">₹<?= number_format($balance, 2) ?></div>
        </div>
    </div>
</div>

<!-- Compact Filters Card -->
<div class="card mb-3 shadow-sm border rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
    <div class="card-body p-2.5">
        <form method="GET" action="/wallet/passbook" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm filter-ctrl">
                    <option value="">All Types</option>
                    <option value="TOPUP_CREDIT" <?= ($type ?? '') === 'TOPUP_CREDIT' ? 'selected' : '' ?>>Recharge</option>
                    <option value="APPLICATION_FEE_DEBIT" <?= ($type ?? '') === 'APPLICATION_FEE_DEBIT' ? 'selected' : '' ?>>Application Fee</option>
                    <option value="COMMISSION_CREDIT" <?= ($type ?? '') === 'COMMISSION_CREDIT' ? 'selected' : '' ?>>Commission</option>
                    <option value="REFUND_CREDIT" <?= ($type ?? '') === 'REFUND_CREDIT' ? 'selected' : '' ?>>Refund</option>
                    <option value="ADMIN_ADJUSTMENT" <?= ($type ?? '') === 'ADMIN_ADJUSTMENT' ? 'selected' : '' ?>>Adjustment</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="direction" class="form-select form-select-sm filter-ctrl">
                    <option value="">All Flow</option>
                    <option value="CREDIT" <?= ($direction ?? '') === 'CREDIT' ? 'selected' : '' ?>>Credit (+)</option>
                    <option value="DEBIT" <?= ($direction ?? '') === 'DEBIT' ? 'selected' : '' ?>>Debit (-)</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control form-control-sm filter-ctrl" value="<?= e($startDate ?? '') ?>" title="From Date">
            </div>
            <div class="col-md-2">
                <input type="date" name="end_date" class="form-control form-control-sm filter-ctrl" value="<?= e($endDate ?? '') ?>" title="To Date">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary filter-ctrl fw-semibold flex-grow-1 shadow-2xs">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="/wallet/passbook" class="btn btn-sm btn-outline-danger filter-ctrl" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Ledger Table (Minimal & Clean) -->
<div class="card shadow-sm border rounded-3 bg-white mb-4 overflow-hidden" style="border: 1px solid #e2e8f0;">
    <div class="card-body p-0">
        <?php if (empty($transactions)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-receipt-cutoff fs-2 opacity-50 d-block mb-1"></i>
                <h5 class="h6 fw-bold text-dark mb-1">No Transactions Found</h5>
                <p class="small text-muted mb-0">No records match the selected filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-passbook align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Txn Reference</th>
                            <th>Transaction</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end pe-3">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                                $isCredit = ($tx['direction'] === 'CREDIT');
                                $rawDesc = $tx['description_en'] ?: ($tx['description_hi'] ?: '');
                                $rawType = $tx['type'] ?? '';

                                if (stripos($rawDesc, 'recharge') !== false || stripos($rawDesc, 'topup') !== false || $rawType === 'TOPUP_CREDIT') {
                                    $title = 'Wallet Recharge';
                                } elseif (stripos($rawDesc, 'application') !== false || stripos($rawDesc, 'service fee') !== false || $rawType === 'APPLICATION_FEE_DEBIT') {
                                    $title = 'Application Fee';
                                } elseif (stripos($rawDesc, 'commission') !== false || $rawType === 'COMMISSION_CREDIT') {
                                    $title = 'Commission';
                                } elseif (stripos($rawDesc, 'refund') !== false || $rawType === 'REFUND_CREDIT') {
                                    $title = 'Refund';
                                } else {
                                    $title = 'Adjustment';
                                }

                                // Extract short reference or batch if available
                                preg_match('/(BATCH-[A-Za-z0-9\-]+|WB-[A-Za-z0-9\-]+|TXN-[A-Za-z0-9\-]+)/', $rawDesc, $match);
                                $refTag = $match[1] ?? '';
                            ?>
                            <tr>
                                <td class="ps-3 font-monospace small fw-bold text-dark">
                                    <?= e($tx['txn_ref']) ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 26px; height: 26px; font-size: 0.75rem; background: <?= $isCredit ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.12)' ?>; color: <?= $isCredit ? '#059669' : '#dc2626' ?>;">
                                            <i class="bi bi-arrow-<?= $isCredit ? 'down-left' : 'up-right' ?>"></i>
                                        </span>
                                        <div>
                                            <span class="fw-semibold text-dark"><?= e($title) ?></span>
                                            <?php if ($refTag && $refTag !== $tx['txn_ref']): ?>
                                                <span class="font-monospace text-muted small ms-1" style="font-size: 0.7rem;">#<?= e($refTag) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="small text-muted" style="white-space: nowrap;">
                                    <?= date('d M, h:i A', strtotime($tx['created_at'])) ?>
                                </td>
                                <td class="text-end font-monospace fw-bold <?= $isCredit ? 'text-success' : 'text-danger' ?>">
                                    <?= $isCredit ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                                </td>
                                <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                    ₹<?= number_format($tx['balance_after'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (($totalPages ?? 1) > 1): ?>
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

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
