<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
    <div>
        <h1 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-whatsapp text-success"></i>
            <span>WhatsApp Delivery Log</span>
        </h1>
        <div class="small text-muted" style="font-size: 0.74rem;">
            Real-time delivery status, WBAPI provider responses, and automated operator alerts
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="/admin/settings#whatsapp-settings" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.76rem;">
            <i class="bi bi-gear-fill me-1"></i>WhatsApp Settings
        </a>
        <a href="/admin/whatsapp/logs" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold" style="font-size: 0.76rem;">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-2">
        <div class="card shadow-2xs border rounded-3 p-2 bg-white">
            <div class="text-muted small fw-semibold" style="font-size: 0.7rem;">TOTAL LOGS</div>
            <div class="h5 fw-bold mb-0 text-dark"><?= number_format((int)($stats['total'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-2xs border rounded-3 p-2 bg-white border-start border-success border-3">
            <div class="text-success small fw-semibold" style="font-size: 0.7rem;">SENT</div>
            <div class="h5 fw-bold mb-0 text-success"><?= number_format((int)($stats['sent_count'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-2xs border rounded-3 p-2 bg-white border-start border-warning border-3">
            <div class="text-warning-emphasis small fw-semibold" style="font-size: 0.7rem;">PENDING</div>
            <div class="h5 fw-bold mb-0 text-warning-emphasis"><?= number_format((int)($stats['pending_count'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-2xs border rounded-3 p-2 bg-white border-start border-danger border-3">
            <div class="text-danger small fw-semibold" style="font-size: 0.7rem;">FAILED</div>
            <div class="h5 fw-bold mb-0 text-danger"><?= number_format((int)($stats['failed_count'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-2xs border rounded-3 p-2 bg-white border-start border-secondary border-3">
            <div class="text-secondary small fw-semibold" style="font-size: 0.7rem;">SKIPPED</div>
            <div class="h5 fw-bold mb-0 text-secondary"><?= number_format((int)($stats['skipped_count'] ?? 0)) ?></div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow-sm border rounded-3 mb-3 bg-white">
    <div class="card-body p-3">
        <form method="GET" action="/admin/whatsapp/logs" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold text-dark mb-1" style="font-size: 0.72rem;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="SENT" <?= ($status === 'SENT') ? 'selected' : '' ?>>SENT</option>
                    <option value="PENDING" <?= ($status === 'PENDING') ? 'selected' : '' ?>>PENDING</option>
                    <option value="PROCESSING" <?= ($status === 'PROCESSING') ? 'selected' : '' ?>>PROCESSING</option>
                    <option value="FAILED" <?= ($status === 'FAILED') ? 'selected' : '' ?>>FAILED</option>
                    <option value="SKIPPED" <?= ($status === 'SKIPPED') ? 'selected' : '' ?>>SKIPPED</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-dark mb-1" style="font-size: 0.72rem;">District</label>
                <select name="district_id" id="filter_district_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Districts</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($districtId === (int)$d['id']) ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-dark mb-1" style="font-size: 0.72rem;">Block</label>
                <select name="block_id" class="form-select form-select-sm">
                    <option value="">All Blocks</option>
                    <?php foreach ($blocks as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($blockId === (int)$b['id']) ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-dark mb-1" style="font-size: 0.72rem;">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-dark mb-1" style="font-size: 0.72rem;">Ref / Identifier</label>
                <input type="text" name="reference" class="form-control form-control-sm" value="<?= e($ref) ?>" placeholder="Ref / App No...">
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                <a href="/admin/whatsapp/logs" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm border rounded-3 bg-white">
    <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
        <span class="small fw-bold text-dark">
            <i class="bi bi-list-check me-1 text-primary"></i>Delivery Records (Showing <?= count($logs) ?> entries)
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.78rem;">
            <thead class="table-light text-muted">
                <tr>
                    <th class="ps-3" style="width: 140px;">Application</th>
                    <th style="width: 150px;">Location</th>
                    <th style="width: 160px;">Operator / Recipient</th>
                    <th style="width: 110px;">Status</th>
                    <th style="width: 70px;">Attempts</th>
                    <th style="width: 130px;">Sent / Created</th>
                    <th>Provider Note / Error</th>
                    <th class="text-end pe-3" style="width: 90px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                            No WhatsApp delivery records matching the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $row): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-primary font-monospace"><?= e($row['app_ref']) ?></div>
                                <div class="text-muted small" style="font-size: 0.7rem;">
                                    App No: <?= e($row['applicant_identifier'] ?: 'N/A') ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($row['district_name'] ?: 'N/A') ?></div>
                                <div class="text-muted small" style="font-size: 0.7rem;"><?= e($row['block_name'] ?: 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($row['operator_name'] ?: ($row['status'] === 'SKIPPED' ? 'No Assigned Operator' : 'Operator')) ?></div>
                                <div class="font-monospace text-muted small" style="font-size: 0.7rem;">
                                    <i class="bi bi-phone me-1"></i><?= e(\App\Services\WhatsAppNotificationService::maskPhone($row['recipient_mobile'])) ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $statusBadge = match($row['status']) {
                                    'SENT' => 'bg-success-subtle text-success border-success',
                                    'PENDING' => 'bg-warning-subtle text-warning-emphasis border-warning',
                                    'PROCESSING' => 'bg-info-subtle text-info border-info',
                                    'FAILED' => 'bg-danger-subtle text-danger border-danger',
                                    'SKIPPED' => 'bg-secondary-subtle text-secondary border-secondary',
                                    default => 'bg-light text-dark'
                                };
                                ?>
                                <span class="badge border <?= $statusBadge ?> fw-bold px-2 py-0.5" style="font-size: 0.68rem;">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold <?= $row['attempts'] >= 4 ? 'text-danger' : 'text-dark' ?>">
                                    <?= (int)$row['attempts'] ?>/4
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['sent_at'])): ?>
                                    <div class="fw-semibold text-success small" style="font-size: 0.7rem;">
                                        <i class="bi bi-check-all me-1"></i><?= date('d M, H:i', strtotime($row['sent_at'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small" style="font-size: 0.7rem;">
                                        <?= date('d M, H:i', strtotime($row['created_at'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['last_error'])): ?>
                                    <span class="text-danger small" title="<?= e($row['last_error']) ?>">
                                        <i class="bi bi-exclamation-triangle me-1"></i><?= e(substr($row['last_error'], 0, 70)) ?><?= strlen($row['last_error']) > 70 ? '...' : '' ?>
                                    </span>
                                <?php elseif ($row['status'] === 'SENT'): ?>
                                    <?php
                                    $pStatus = strtolower(trim((string)($row['provider_status'] ?? 'sent')));
                                    $displayStatus = ($pStatus === 'pending' || $pStatus === '') ? 'sent' : $pStatus;
                                    ?>
                                    <span class="text-success small">
                                        <i class="bi bi-check2-circle me-1"></i>Delivered via WBAPI (<?= e($displayStatus) ?>)
                                    </span>
                                <?php elseif ($row['status'] === 'PENDING'): ?>
                                    <span class="text-muted small">
                                        Queued for delivery
                                        <?php if (!empty($row['next_attempt_at'])): ?>
                                            (next: <?= date('H:i:s', strtotime($row['next_attempt_at'])) ?>)
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if ($row['status'] === 'FAILED'): ?>
                                    <form method="POST" action="/admin/whatsapp/retry" class="d-inline" onsubmit="return confirm('Retry sending this WhatsApp notification?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="btn btn-xs btn-outline-danger py-0.5 px-2 rounded-pill fw-bold" style="font-size: 0.68rem;" title="Retry delivery now">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
