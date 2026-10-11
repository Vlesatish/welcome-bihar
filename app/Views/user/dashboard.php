<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- User Greeting Banner -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-speedometer2 text-primary me-2"></i><?= e(__('dashboard')) ?>
        </h1>
        <p class="text-muted mb-0">
            Welcome back, <strong><?= e($currentUser['name']) ?></strong> &bull; <?= e($currentUser['center_name'] ?: 'Kiosk Operator') ?>
        </p>
    </div>
    <div class="col-md-5 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/wallet/topup" class="btn btn-warning fw-bold px-3 shadow-sm">
            <i class="bi bi-qr-code-scan me-1"></i> <?= e(__('topup_wallet')) ?>
        </a>
        <a href="/applications/new" class="btn btn-primary fw-bold px-3 shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> <?= e(__('new_application')) ?>
        </a>
    </div>
</div>

<!-- Quick Stat Cards -->
<div class="row g-3 mb-4">
    <!-- Wallet Balance -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-blue shadow-sm">
            <i class="bi bi-wallet2 stat-icon"></i>
            <div class="stat-title"><?= e(__('wallet_balance')) ?></div>
            <div class="stat-val">₹<?= number_format($balance, 2) ?></div>
            <div class="mt-2 small opacity-75">
                <a href="/wallet/passbook" class="text-white text-decoration-none fw-semibold">
                    <?= e(__('view_details')) ?> &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Total Applications -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-dark shadow-sm">
            <i class="bi bi-files stat-icon"></i>
            <div class="stat-title"><?= e(__('applications')) ?></div>
            <div class="stat-val"><?= (int)($stats['total'] ?? 0) ?></div>
            <div class="mt-2 small opacity-75">
                <a href="/applications" class="text-white text-decoration-none fw-semibold">
                    <?= e(__('application_list')) ?> &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Approved Applications -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-green shadow-sm">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-title"><?= e(__('status_approved')) ?></div>
            <div class="stat-val"><?= (int)($stats['approved'] ?? 0) ?></div>
            <div class="mt-2 small opacity-75">
                <a href="/applications?status=APPROVED" class="text-white text-decoration-none fw-semibold">
                    View Approved &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Pending & Under Review -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-orange shadow-sm">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div class="stat-title"><?= e(__('status_pending')) ?> / Review</div>
            <div class="stat-val"><?= (int)($stats['pending'] ?? 0) + (int)($stats['under_review'] ?? 0) ?></div>
            <div class="mt-2 small opacity-75">
                <span class="badge bg-light text-dark me-1"><?= (int)($stats['pending'] ?? 0) ?> Pending</span>
                <span class="badge bg-light text-dark"><?= (int)($stats['under_review'] ?? 0) ?> Review</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Applications -->
    <div class="col-lg-8">
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Applications</span>
                <a href="/applications" class="btn btn-sm btn-outline-primary fw-semibold"><?= e(__('application_list')) ?></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentApplications)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-folder-x fs-1 opacity-50"></i>
                        <p class="mt-2 mb-3">No applications submitted yet.</p>
                        <a href="/applications/new" class="btn btn-sm btn-primary rounded-pill px-3">
                            <i class="bi bi-plus-circle me-1"></i> Start New Application
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref</th>
                                    <th>Service</th>
                                    <th>Applicant</th>
                                    <th>Location</th>
                                    <th>Fee</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentApplications as $app): ?>
                                    <tr>
                                        <td>
                                            <a href="/applications/view?id=<?= $app['id'] ?>" class="fw-bold text-decoration-none text-primary">
                                                <?= e($app['app_ref']) ?>
                                            </a>
                                            <div class="small text-muted"><?= date('d M Y, h:i A', strtotime($app['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold"><?= e($app['dt_name_en'] ?: $app['dt_name_hi']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= e($app['applicant_name']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($app['applicant_mobile']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= e($app['district_name']) ?></span>
                                            <div class="small text-muted"><?= e($app['block_name']) ?></div>
                                        </td>
                                        <td class="fw-bold text-danger">₹<?= number_format($app['fee_debited'], 2) ?></td>
                                        <td>
                                            <span class="badge-status badge-<?= strtolower($app['status']) ?>">
                                                <?= e(__('status_' . strtolower($app['status']))) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/applications/view?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-eye"></i>
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
    </div>

    <!-- Recent Passbook Transactions -->
    <div class="col-lg-4">
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-receipt me-2 text-primary"></i>Wallet Passbook</span>
                <a href="/wallet/passbook" class="btn btn-sm btn-outline-primary fw-semibold"><?= e(__('view_details')) ?></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($passbook['transactions'])): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-receipt-cutoff fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">No transactions recorded yet</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($passbook['transactions'] as $tx): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <div class="fw-semibold small"><?= e($tx['description_en'] ?: $tx['description_hi']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <?= e($tx['txn_ref']) ?> &bull; <?= date('d M, h:i A', strtotime($tx['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="<?= $tx['direction'] === 'CREDIT' ? 'text-credit' : 'text-debit' ?>">
                                        <?= $tx['direction'] === 'CREDIT' ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        Bal: ₹<?= number_format($tx['balance_after'], 2) ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light p-3 text-center">
                <a href="/wallet/topup" class="btn btn-sm btn-warning w-100 fw-bold">
                    <i class="bi bi-plus-circle me-1"></i> <?= e(__('topup_wallet')) ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
