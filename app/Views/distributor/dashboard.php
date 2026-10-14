<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Distributor Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-speedometer2 text-primary me-2"></i><?= e(__('dashboard')) ?> (Distributor Hub)
        </h1>
        <p class="text-muted mb-0">
            Welcome, <strong><?= e($currentUser['name']) ?></strong> &bull; <?= e($currentUser['center_name'] ?: 'Regional Distributor') ?>
        </p>
    </div>
    <div class="col-md-5 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/distributor/retailers/new" class="btn btn-success fw-bold shadow-sm d-flex align-items-center gap-1.5" style="background: linear-gradient(135deg, #10b981, #059669); border: none;">
            <i class="bi bi-person-plus-fill"></i> Add Retailer
        </a>
        <a href="/distributor/team" class="btn btn-outline-primary fw-semibold">
            <i class="bi bi-people me-1"></i> <?= e(__('team_management')) ?>
        </a>
        <a href="/distributor/applications" class="btn btn-primary fw-bold shadow-sm">
            <i class="bi bi-folder2-open me-1"></i> Team Applications
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <!-- Wallet Balance -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-blue shadow-sm">
            <i class="bi bi-wallet2 stat-icon"></i>
            <div class="stat-title"><?= e(__('wallet_balance')) ?></div>
            <div class="stat-val">₹<?= number_format($balance, 2) ?></div>
            <div class="mt-2 small opacity-75">
                <a href="/wallet/passbook" class="text-white text-decoration-none fw-semibold">
                    <?= e(__('wallet_passbook')) ?> &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Total Team Members -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-purple shadow-sm">
            <i class="bi bi-people stat-icon"></i>
            <div class="stat-title">Active Team Operators / Retailers</div>
            <div class="stat-val"><?= $totalTeamMembers ?></div>
            <div class="mt-2 small opacity-75 d-flex justify-content-between align-items-center">
                <a href="/distributor/team" class="text-white text-decoration-none fw-semibold">
                    Manage Team &rarr;
                </a>
                <a href="/distributor/retailers/new" class="badge bg-white text-primary text-decoration-none px-2 py-1">
                    + Add Retailer
                </a>
            </div>
        </div>
    </div>

    <!-- Total Commission Earned -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-green shadow-sm">
            <i class="bi bi-cash-coin stat-icon"></i>
            <div class="stat-title">Commissions Earned</div>
            <div class="stat-val">₹<?= number_format((float)($stats['total_commission_earned'] ?? 0), 2) ?></div>
            <div class="mt-2 small opacity-75">
                Total operator applications: <?= (int)($stats['total'] ?? 0) ?>
            </div>
        </div>
    </div>

    <!-- Pending Reviews -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card bg-gradient-orange shadow-sm">
            <i class="bi bi-clipboard-check stat-icon"></i>
            <div class="stat-title">Team Review Queue</div>
            <div class="stat-val"><?= (int)($stats['pending'] ?? 0) ?></div>
            <div class="mt-2 small opacity-75">
                Review Status: <?= $reviewEnabled ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-secondary">Disabled by Admin</span>' ?>
            </div>
        </div>
    </div>
</div>

<!-- Permitted Location Badges -->
<div class="wb-card mb-4 p-3 bg-light border">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-bold small text-muted text-uppercase me-2"><i class="bi bi-geo-alt-fill text-danger"></i> Permitted Districts:</span>
        <?php if (!empty($permittedLocations)): ?>
            <?php foreach ($permittedLocations as $pl): ?>
                <span class="badge bg-white text-dark border p-2">
                    <?= e($pl['district_name']) ?> <?= !empty($pl['block_name']) ? ' &bull; ' . e($pl['block_name']) : ' (All Blocks)' ?>
                </span>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="small text-muted">No specific geographic constraint assigned.</span>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Applications from Team -->
<div class="wb-card">
    <div class="wb-card-header">
        <span class="fw-bold"><i class="bi bi-folder2-open me-2 text-primary"></i>Recent Team Submissions</span>
        <a href="/distributor/applications" class="btn btn-sm btn-outline-primary fw-semibold">View All Team Records</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentApplications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-folder-x fs-1 opacity-50"></i>
                <p class="mt-2 mb-0">No applications received from assigned operators yet.</p>
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
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentApplications as $app): ?>
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
                                    <div class="fw-semibold small"><?= e($app['operator_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= e($app['center_name']) ?></div>
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
                                <td class="small text-muted"><?= date('d M, h:i A', strtotime($app['created_at'])) ?></td>
                                <td class="text-end">
                                    <a href="/applications/view?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary">
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

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
