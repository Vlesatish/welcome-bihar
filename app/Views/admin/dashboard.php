<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Admin Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-shield-lock-fill text-primary me-2"></i>Administration Command Center
        </h1>
        <p class="text-muted mb-0">System Overview &bull; Bihar 38 Districts &bull; 3 Core Data Types</p>
    </div>
    <div class="col-md-5 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/admin/reconciliation" class="btn btn-outline-primary fw-semibold">
            <i class="bi bi-arrow-left-right me-1"></i> <?= e(__('reconciliation')) ?>
        </a>
        <a href="/admin/settings" class="btn btn-primary fw-bold shadow-sm">
            <i class="bi bi-gear-fill me-1"></i> <?= e(__('system_settings')) ?>
        </a>
    </div>
</div>

<!-- Primary Metric Cards -->
<div class="row g-3 mb-4">
    <!-- Total Operators -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-blue shadow-sm">
            <i class="bi bi-people-fill stat-icon"></i>
            <div class="stat-title"><?= e(__('users_manager')) ?></div>
            <div class="stat-val"><?= $userCount ?></div>
            <div class="mt-2 small opacity-75">
                <a href="/admin/users?role=USER" class="text-white text-decoration-none fw-semibold">
                    View All Operators &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Total Distributors -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-purple shadow-sm">
            <i class="bi bi-diagram-3-fill stat-icon"></i>
            <div class="stat-title">Distributors</div>
            <div class="stat-val"><?= $distCount ?></div>
            <div class="mt-2 small opacity-75">
                <a href="/admin/users?role=DISTRIBUTOR" class="text-white text-decoration-none fw-semibold">
                    View Regional Hubs &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Total Applications -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-dark shadow-sm">
            <i class="bi bi-folder-fill stat-icon"></i>
            <div class="stat-title">Total Applications</div>
            <div class="stat-val"><?= (int)($appStats['total'] ?? 0) ?></div>
            <div class="mt-2 small opacity-75">
                Approved: <?= (int)($appStats['approved'] ?? 0) ?> &bull; Rejected: <?= (int)($appStats['rejected'] ?? 0) ?>
            </div>
        </div>
    </div>

    <!-- Review Queue -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-orange shadow-sm">
            <i class="bi bi-hourglass-top stat-icon"></i>
            <div class="stat-title">Awaiting Approval</div>
            <div class="stat-val"><?= (int)($appStats['pending'] ?? 0) + (int)($appStats['under_review'] ?? 0) ?></div>
            <div class="mt-2 small opacity-75">
                <a href="/applications?status=PENDING" class="text-white text-decoration-none fw-semibold">
                    Open Review Queue &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Financial & Topup Order Metrics -->
<div class="row g-3 mb-4">
    <!-- System Wallet Liability -->
    <div class="col-sm-6 col-md-3">
        <div class="wb-card p-3 border-start border-4 border-primary">
            <div class="text-muted small text-uppercase fw-semibold">Active User Balances</div>
            <div class="fs-4 fw-bold text-primary mt-1">₹<?= number_format((float)($walletStats['total_system_balance'] ?? 0), 2) ?></div>
            <div class="small text-muted mt-1">Total Credited: ₹<?= number_format((float)($walletStats['total_credited'] ?? 0), 2) ?></div>
        </div>
    </div>

    <!-- Fees Debited -->
    <div class="col-sm-6 col-md-3">
        <div class="wb-card p-3 border-start border-4 border-success">
            <div class="text-muted small text-uppercase fw-semibold">Application Fees Collected</div>
            <div class="fs-4 fw-bold text-success mt-1">₹<?= number_format((float)($appStats['total_fees'] ?? 0), 2) ?></div>
            <div class="small text-muted mt-1">System Revenue</div>
        </div>
    </div>

    <!-- Commissions Disbursed -->
    <div class="col-sm-6 col-md-3">
        <div class="wb-card p-3 border-start border-4 border-info">
            <div class="text-muted small text-uppercase fw-semibold">Distributor Commissions</div>
            <div class="fs-4 fw-bold text-info mt-1">₹<?= number_format((float)($appStats['total_commissions'] ?? 0), 2) ?></div>
            <div class="small text-muted mt-1">Disbursed to distributors</div>
        </div>
    </div>

    <!-- Topup Orders Paid -->
    <div class="col-sm-6 col-md-3">
        <div class="wb-card p-3 border-start border-4 border-warning">
            <div class="text-muted small text-uppercase fw-semibold">UPI Top-Ups Recharged</div>
            <div class="fs-4 fw-bold text-warning-emphasis mt-1">₹<?= number_format((float)($topupStats['total_topup_amount'] ?? 0), 2) ?></div>
            <div class="small text-muted mt-1">Paid: <?= (int)($topupStats['paid_count'] ?? 0) ?> | Pending: <?= (int)($topupStats['pending_count'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Pending Application Queue -->
    <div class="col-lg-8">
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Applications Review Queue</span>
                <a href="/applications" class="btn btn-sm btn-outline-primary fw-semibold"><?= e(__('application_list')) ?></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingApps)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle-fill fs-1 text-success opacity-50"></i>
                        <p class="mt-2 mb-0">No pending applications requiring immediate review.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference</th>
                                    <th>Service</th>
                                    <th>Applicant</th>
                                    <th>District &amp; Block</th>
                                    <th>Operator</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingApps as $app): ?>
                                    <tr>
                                        <td>
                                            <a href="/applications/view?id=<?= $app['id'] ?>" class="fw-bold text-decoration-none text-primary">
                                                <?= e($app['app_ref']) ?>
                                            </a>
                                            <div class="small text-muted"><?= date('d M, h:i A', strtotime($app['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold small"><?= e($app['dt_name_en'] ?: $app['dt_name_hi']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small"><?= e($app['applicant_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?= e($app['applicant_mobile']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= e($app['district_name']) ?></span>
                                            <div class="small text-muted"><?= e($app['block_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold"><?= e($app['operator_name']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge-status badge-<?= strtolower($app['status']) ?>">
                                                <?= e(__('status_' . strtolower($app['status']))) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/applications/view?id=<?= $app['id'] ?>" class="btn btn-sm btn-primary py-1 px-2 fw-semibold">
                                                Review &rarr;
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

    <!-- Right Column: Audit Logs Snippet -->
    <div class="col-lg-4">
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i><?= e(__('audit_logs')) ?></span>
                <a href="/admin/audit-logs" class="btn btn-sm btn-outline-primary fw-semibold">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentAudits)): ?>
                    <div class="text-center py-4 text-muted">No audit events recorded yet.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentAudits as $log): ?>
                            <li class="list-group-item py-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-dark small font-monospace"><?= e($log['action']) ?></span>
                                    <span class="small text-muted" style="font-size: 0.72rem;">
                                        <?= date('h:i A', strtotime($log['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="small text-secondary">
                                    Target: <strong><?= e($log['target_type']) ?> #<?= e($log['target_id'] ?: '-') ?></strong>
                                    by <?= e($log['actor_name'] ?: 'System') ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
