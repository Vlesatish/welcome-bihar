<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-people text-primary me-2"></i><?= e(__('team_management')) ?>
        </h1>
        <p class="text-muted mb-0">Assigned Kiosk Operators and Field Personnel</p>
    </div>
</div>

<div class="wb-card">
    <div class="card-body p-0">
        <?php if (empty($team)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1 opacity-50"></i>
                <h5 class="fw-bold mt-2">No Operators Assigned</h5>
                <p class="small text-muted mb-0">No operators are currently assigned to your distributor account.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Operator Name</th>
                            <th>Contact Mobile &amp; Email</th>
                            <th>Center / Shop Name</th>
                            <th>Wallet Balance</th>
                            <th>Total Applications</th>
                            <th>Status</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team as $member): ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <i class="bi bi-person-circle text-primary me-1"></i>
                                    <?= e($member['name']) ?>
                                </td>
                                <td>
                                    <div><i class="bi bi-telephone text-muted me-1"></i><?= e($member['mobile']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-envelope text-muted me-1"></i><?= e($member['email']) ?></div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-secondary"><?= e($member['center_name'] ?: 'N/A') ?></span>
                                </td>
                                <td class="fw-bold text-primary">
                                    ₹<?= number_format((float)($member['balance'] ?? 0), 2) ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1"><?= (int)$member['app_count'] ?> Applications</span>
                                </td>
                                <td>
                                    <span class="badge <?= $member['status'] === 'ACTIVE' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                        <?= e($member['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M Y', strtotime($member['created_at'])) ?>
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
