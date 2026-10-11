<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-people-fill text-primary me-2"></i><?= e(__('users_manager')) ?>
        </h1>
        <p class="text-muted mb-0">Operators, Regional Distributors, and Administrator Accounts</p>
    </div>
</div>

<!-- Filters Card -->
<div class="wb-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/users" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search Name / Mobile / Email / Center..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">-- All Roles --</option>
                    <option value="USER" <?= $role === 'USER' ? 'selected' : '' ?>><?= e(__('role_user')) ?></option>
                    <option value="DISTRIBUTOR" <?= $role === 'DISTRIBUTOR' ? 'selected' : '' ?>><?= e(__('role_distributor')) ?></option>
                    <option value="ADMIN" <?= $role === 'ADMIN' ? 'selected' : '' ?>><?= e(__('role_admin')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- All Statuses --</option>
                    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                    <option value="BLOCKED" <?= $status === 'BLOCKED' ? 'selected' : '' ?>>BLOCKED</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="/admin/users" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="wb-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User / Contact</th>
                        <th>Role</th>
                        <th>Center / Kiosk Name</th>
                        <th>Assigned Distributor</th>
                        <th>Wallet Balance</th>
                        <th>Applications</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th class="text-end">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">
                                    <a href="/admin/users/detail?id=<?= $u['id'] ?>" class="text-decoration-none">
                                        <?= e($u['name']) ?>
                                    </a>
                                </div>
                                <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($u['mobile']) ?> &bull; <?= e($u['email']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'ADMIN' ? 'primary' : ($u['role'] === 'DISTRIBUTOR' ? 'info text-dark' : 'secondary') ?>">
                                    <?= e($u['role']) ?>
                                </span>
                            </td>
                            <td class="small fw-semibold text-secondary">
                                <?= e($u['center_name'] ?: '-') ?>
                            </td>
                            <td class="small">
                                <?= e($u['distributor_name'] ?: '<span class="text-muted">None</span>') ?>
                            </td>
                            <td class="fw-bold text-primary">
                                ₹<?= number_format((float)($u['balance'] ?? 0), 2) ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= (int)$u['app_count'] ?> Apps</span>
                            </td>
                            <td>
                                <span class="badge <?= $u['status'] === 'ACTIVE' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                    <?= e($u['status']) ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= date('d M Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="text-end">
                                <a href="/admin/users/detail?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                                    <i class="bi bi-gear-fill me-1"></i> Manage
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
