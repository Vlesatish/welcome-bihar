<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-folder2-open text-primary me-2"></i>Team Applications
        </h1>
        <p class="text-muted mb-0">Applications submitted by your assigned kiosk operators</p>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if ($reviewEnabled): ?>
            <span class="badge bg-success-subtle text-success border border-success p-2">
                <i class="bi bi-check-circle-fill me-1"></i> Distributor Review Enabled
            </span>
        <?php else: ?>
            <span class="badge bg-secondary-subtle text-secondary border p-2">
                <i class="bi bi-info-circle me-1"></i> Distributor Review Disabled by Admin
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="wb-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/distributor/applications" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search Ref / Name / Mobile..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- All Statuses --</option>
                    <option value="PENDING" <?= ($status ?? '') === 'PENDING' ? 'selected' : '' ?>><?= e(__('status_pending')) ?></option>
                    <option value="UNDER_REVIEW" <?= ($status ?? '') === 'UNDER_REVIEW' ? 'selected' : '' ?>><?= e(__('status_under_review')) ?></option>
                    <option value="APPROVED" <?= ($status ?? '') === 'APPROVED' ? 'selected' : '' ?>><?= e(__('status_approved')) ?></option>
                    <option value="REJECTED" <?= ($status ?? '') === 'REJECTED' ? 'selected' : '' ?>><?= e(__('status_rejected')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="data_type_id" class="form-select">
                    <option value="">-- All Services --</option>
                    <?php foreach ($dataTypes as $dt): ?>
                        <option value="<?= $dt['id'] ?>" <?= ($dataTypeId ?? 0) === (int)$dt['id'] ? 'selected' : '' ?>>
                            <?= e($dt['name_en'] ?: 'Service') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="/distributor/applications" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="wb-card">
    <div class="card-body p-0">
        <?php if (empty($applications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-folder-x fs-1 opacity-50"></i>
                <h5 class="fw-bold mt-2">No Team Applications Found</h5>
                <p class="small text-muted mb-0">No records found matching current filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Service</th>
                            <th>Operator</th>
                            <th>Applicant</th>
                            <th>Location</th>
                            <th>Commission</th>
                            <th>Status</th>
                            <th>Submission Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <a href="/applications/view?id=<?= $app['id'] ?>" class="fw-bold text-decoration-none text-primary">
                                        <?= e($app['app_ref']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= e($app['dt_name_en'] ?: 'Service') ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold small"><?= e($app['operator_name']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($app['applicant_name']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($app['applicant_mobile']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($app['district_name']) ?></span>
                                    <div class="small text-muted"><?= e($app['block_name']) ?></div>
                                </td>
                                <td class="fw-bold text-success">+₹<?= number_format($app['commission_credited'], 2) ?></td>
                                <td>
                                    <span class="badge-status badge-<?= strtolower($app['status']) ?>">
                                        <?= e(__('status_' . strtolower($app['status']))) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($app['created_at'])) ?></td>
                                <td class="text-end">
                                    <a href="/applications/view?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i> Review
                                    </a>
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
