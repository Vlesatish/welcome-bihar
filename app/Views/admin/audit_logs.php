<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-shield-check text-primary me-2"></i><?= e(__('audit_logs')) ?>
        </h1>
        <p class="text-muted mb-0">Immutable Security &amp; Administrative Event Audit Trail</p>
    </div>
</div>

<!-- Filters Card -->
<div class="wb-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/audit-logs" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="action" class="form-control" placeholder="Filter by Action (e.g. WALLET_CREDIT, APPLICATION_STATUS_UPDATE)..." value="<?= e($action ?? '') ?>">
            </div>
            <div class="col-md-4">
                <select name="target_type" class="form-select">
                    <option value="">-- All Target Types --</option>
                    <option value="APPLICATION" <?= ($targetType ?? '') === 'APPLICATION' ? 'selected' : '' ?>>APPLICATION</option>
                    <option value="WALLET" <?= ($targetType ?? '') === 'WALLET' ? 'selected' : '' ?>>WALLET</option>
                    <option value="TOPUP_ORDER" <?= ($targetType ?? '') === 'TOPUP_ORDER' ? 'selected' : '' ?>>TOPUP_ORDER</option>
                    <option value="USER" <?= ($targetType ?? '') === 'USER' ? 'selected' : '' ?>>USER</option>
                    <option value="SETTINGS" <?= ($targetType ?? '') === 'SETTINGS' ? 'selected' : '' ?>>SETTINGS</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="/admin/audit-logs" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="wb-card">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-shield-slash fs-1 opacity-50"></i>
                <h5 class="fw-bold mt-2">No Audit Records Found</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Action / Event</th>
                            <th>Target</th>
                            <th>Actor / Role</th>
                            <th>IP Address</th>
                            <th>Details</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="small text-muted font-monospace"><?= $log['id'] ?></td>
                                <td>
                                    <span class="badge bg-dark font-monospace py-1 px-2">
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold small text-secondary"><?= e($log['target_type']) ?></span>
                                    <?php if (!empty($log['target_id'])): ?>
                                        <div class="font-monospace small text-muted">#<?= e($log['target_id']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold small text-dark"><?= e($log['actor_name'] ?: 'System / Guest') ?></div>
                                    <?php if (!empty($log['actor_role'])): ?>
                                        <span class="badge bg-light text-muted border" style="font-size: 0.7rem;"><?= e($log['actor_role']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small font-monospace text-muted"><?= e($log['ip_address']) ?></td>
                                <td>
                                    <?php if (!empty($log['details_json'])): ?>
                                        <pre class="mb-0 bg-light p-1 rounded font-monospace" style="font-size: 0.72rem; max-width: 250px; white-space: pre-wrap;"><?= e($log['details_json']) ?></pre>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M Y, h:i:s A', strtotime($log['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
