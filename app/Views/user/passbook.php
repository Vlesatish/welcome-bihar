<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Title & Actions -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-wallet2 text-primary me-2"></i><?= e(__('wallet_passbook')) ?>
        </h1>
        <p class="text-muted mb-0">Immutable Double-Entry Ledger Passbook &amp; Transaction History</p>
    </div>
    <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/wallet/passbook/export-csv?<?= http_build_query($_GET) ?>" class="btn btn-outline-success fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> <?= e(__('export_csv')) ?>
        </a>
        <a href="/wallet/topup" class="btn btn-warning fw-bold shadow-sm">
            <i class="bi bi-qr-code-scan me-1"></i> <?= e(__('topup_wallet')) ?>
        </a>
    </div>
</div>

<!-- Balance Summary Card -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card bg-gradient-blue shadow-sm">
            <i class="bi bi-cash-stack stat-icon"></i>
            <div class="stat-title"><?= e(__('current_balance')) ?></div>
            <div class="stat-val">₹<?= number_format($balance, 2) ?></div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="wb-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/wallet/passbook" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">-- All Transaction Types --</option>
                    <option value="TOPUP_CREDIT" <?= ($type ?? '') === 'TOPUP_CREDIT' ? 'selected' : '' ?>><?= e(__('type_topup_credit')) ?></option>
                    <option value="APPLICATION_FEE_DEBIT" <?= ($type ?? '') === 'APPLICATION_FEE_DEBIT' ? 'selected' : '' ?>><?= e(__('type_application_fee_debit')) ?></option>
                    <option value="COMMISSION_CREDIT" <?= ($type ?? '') === 'COMMISSION_CREDIT' ? 'selected' : '' ?>><?= e(__('type_commission_credit')) ?></option>
                    <option value="REFUND_CREDIT" <?= ($type ?? '') === 'REFUND_CREDIT' ? 'selected' : '' ?>><?= e(__('type_refund_credit')) ?></option>
                    <option value="ADMIN_ADJUSTMENT" <?= ($type ?? '') === 'ADMIN_ADJUSTMENT' ? 'selected' : '' ?>><?= e(__('type_admin_adjustment')) ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="direction" class="form-select">
                    <option value="">-- Direction --</option>
                    <option value="CREDIT" <?= ($direction ?? '') === 'CREDIT' ? 'selected' : '' ?>>Credit (+)</option>
                    <option value="DEBIT" <?= ($direction ?? '') === 'DEBIT' ? 'selected' : '' ?>>Debit (-)</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate ?? '') ?>" placeholder="Start Date">
            </div>
            <div class="col-md-2">
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate ?? '') ?>" placeholder="End Date">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-filter me-1"></i> <?= e(__('filter')) ?>
                </button>
                <a href="/wallet/passbook" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Ledger Table -->
<div class="wb-card">
    <div class="card-body p-0">
        <?php if (empty($transactions)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-receipt-cutoff fs-1 opacity-50"></i>
                <h5 class="fw-bold mt-2">No Transactions Found</h5>
                <p class="small text-muted mb-0">No ledger records match the selected filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Txn Reference</th>
                            <th>Date &amp; Time</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Direction</th>
                            <th class="text-end">Amount (INR)</th>
                            <th class="text-end">Prev Balance</th>
                            <th class="text-end">New Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="font-monospace fw-semibold small text-primary">
                                    <?= e($tx['txn_ref']) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M Y, h:i:s A', strtotime($tx['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($tx['description_en'] ?: $tx['description_hi']) ?></div>
                                    <?php if (!empty($tx['remarks'])): ?>
                                        <div class="small text-muted fst-italic"><?= e($tx['remarks']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border small">
                                        <?= e($tx['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $tx['direction'] === 'CREDIT' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                        <?= $tx['direction'] === 'CREDIT' ? 'CREDIT' : 'DEBIT' ?>
                                    </span>
                                </td>
                                <td class="text-end <?= $tx['direction'] === 'CREDIT' ? 'text-credit' : 'text-debit' ?> fs-6">
                                    <?= $tx['direction'] === 'CREDIT' ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                                </td>
                                <td class="text-end small text-muted">
                                    ₹<?= number_format($tx['balance_before'], 2) ?>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    ₹<?= number_format($tx['balance_after'], 2) ?>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        <?= e($tx['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Page <?= $page ?> of <?= $totalPages ?> (Total <?= $totalRecords ?> transactions)
                    </div>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
