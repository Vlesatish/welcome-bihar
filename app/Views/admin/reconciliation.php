<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-arrow-left-right text-primary me-2"></i><?= e(__('reconciliation')) ?> &amp; Payment Orders
        </h1>
        <p class="text-muted mb-0">Audit &amp; reconcile top-up orders with ALL API UPI Scan &amp; Pay records</p>
    </div>
    <div class="col-md-6 text-md-end">
        <span class="badge bg-light text-dark border p-2">
            <i class="bi bi-shield-check text-success me-1"></i> Provider: <strong><?= e($providerInfo['provider_name']) ?></strong> (<?= e($providerInfo['mode']) ?>)
        </span>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat-card bg-gradient-dark shadow-sm">
            <i class="bi bi-list-ol stat-icon"></i>
            <div class="stat-title">Total Orders</div>
            <div class="stat-val"><?= (int)($summary['total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card bg-gradient-green shadow-sm">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-title">Paid &amp; Credited</div>
            <div class="stat-val"><?= (int)($summary['paid_cnt'] ?? 0) ?></div>
            <div class="mt-2 small opacity-75">
                Vol: ₹<?= number_format((float)($summary['paid_amount'] ?? 0), 2) ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card bg-gradient-orange shadow-sm">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div class="stat-title">Pending / In Process</div>
            <div class="stat-val"><?= (int)($summary['pending_cnt'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card bg-gradient-danger shadow-sm">
            <i class="bi bi-x-circle stat-icon"></i>
            <div class="stat-title">Failed / Expired</div>
            <div class="stat-val"><?= (int)($summary['failed_cnt'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="wb-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/reconciliation" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search Order ID / Provider Txn / UTR / User Name..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- All Order Statuses --</option>
                    <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                    <option value="PAID" <?= $status === 'PAID' ? 'selected' : '' ?>>PAID</option>
                    <option value="FAILED" <?= $status === 'FAILED' ? 'selected' : '' ?>>FAILED</option>
                    <option value="EXPIRED" <?= $status === 'EXPIRED' ? 'selected' : '' ?>>EXPIRED</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="/admin/reconciliation" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="wb-card">
    <div class="card-body p-0">
        <?php if (empty($orders)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-receipt fs-1 opacity-50"></i>
                <h5 class="fw-bold mt-2">No Payment Orders Found</h5>
                <p class="small text-muted mb-0">No records found matching current search filter.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>User (Operator)</th>
                            <th>Amount</th>
                            <th>Provider Reference / UTR</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Credited At</th>
                            <th class="text-end">Reconciliation Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord): ?>
                            <tr>
                                <td class="font-monospace fw-bold text-primary small">
                                    <?= e($ord['order_id']) ?>
                                </td>
                                <td>
                                    <div class="fw-semibold small"><?= e($ord['user_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= e($ord['user_mobile']) ?> &bull; Bal: ₹<?= number_format((float)$ord['current_wallet_balance'], 2) ?></div>
                                </td>
                                <td class="fw-bold text-success fs-6">
                                    ₹<?= number_format($ord['amount'], 2) ?>
                                </td>
                                <td class="font-monospace small">
                                    <?php if (!empty($ord['provider_txn_id'])): ?>
                                        <div><span class="text-muted">Txn:</span> <?= e($ord['provider_txn_id']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($ord['provider_utr'])): ?>
                                        <div><span class="text-muted">UTR:</span> <?= e($ord['provider_utr']) ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($ord['provider_txn_id']) && empty($ord['provider_utr'])): ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status badge-<?= strtolower($ord['status']) ?>">
                                        <?= e($ord['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M, h:i A', strtotime($ord['created_at'])) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= $ord['credited_at'] ? date('d M, h:i A', strtotime($ord['credited_at'])) : '-' ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($ord['status'] === 'PENDING'): ?>
                                            <!-- Provider Live Recheck Button -->
                                            <form method="POST" action="/admin/reconciliation/recheck" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="order_id" value="<?= e($ord['order_id']) ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-primary py-1 px-2" title="Query ALL API live status">
                                                    <i class="bi bi-arrow-clockwise me-1"></i> Live Check
                                                </button>
                                            </form>

                                            <!-- Manual Review Modal Trigger -->
                                            <button type="button" class="btn btn-xs btn-outline-warning py-1 px-2"
                                                    onclick='openManualModal(<?= json_encode($ord) ?>)'>
                                                <i class="bi bi-check2-circle me-1"></i> Manual Verify
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Audited</span>
                                        <?php endif; ?>
                                    </div>
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
                        Page <?= $page ?> of <?= $totalPages ?> (Total <?= $totalRecords ?> orders)
                    </div>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a></li>
                        <?php endif; ?>
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Authorized Manual Verification -->
<div class="modal fade" id="manualVerifyModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/admin/reconciliation/verify" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" id="mv_order_id">

            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title fw-bold text-warning-emphasis">
                    <i class="bi bi-shield-exclamation me-1"></i> Authorized Manual Payment Review
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="row g-2 small">
                        <div class="col-6"><strong>Order ID:</strong> <span id="mv_lbl_order_id" class="font-monospace"></span></div>
                        <div class="col-6"><strong>Amount:</strong> <span id="mv_lbl_amount" class="text-success fw-bold"></span></div>
                        <div class="col-12"><strong>Operator:</strong> <span id="mv_lbl_user"></span></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Action <span class="text-danger">*</span></label>
                    <select name="action" class="form-select" required>
                        <option value="APPROVE_CREDIT">APPROVE &amp; Credit Wallet (Confirm Received in Merchant Bank)</option>
                        <option value="MARK_FAILED">MARK FAILED (Invalid/Expired Payment)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Bank UTR / Transaction Reference</label>
                    <input type="text" name="utr" class="form-control font-monospace" placeholder="12-digit UPI Bank UTR">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Mandatory Audit Justification <span class="text-danger">*</span></label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Provide reason and proof verification details for manual reconciliation..." required></textarea>
                    <div class="form-text small text-danger">Recorded in immutable audit trail.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning fw-bold">Submit Reconciliation Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function openManualModal(ord) {
    document.getElementById('mv_order_id').value = ord.order_id;
    document.getElementById('mv_lbl_order_id').textContent = ord.order_id;
    document.getElementById('mv_lbl_amount').textContent = '₹' + parseFloat(ord.amount).toFixed(2);
    document.getElementById('mv_lbl_user').textContent = ord.user_name + ' (' + ord.user_mobile + ')';
    const modal = new bootstrap.Modal(document.getElementById('manualVerifyModal'));
    modal.show();
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
