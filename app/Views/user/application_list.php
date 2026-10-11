<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Title & Actions -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-folder2-open text-primary me-2"></i><?= e(__('application_list')) ?>
        </h1>
        <p class="text-muted mb-0">Total records: <?= number_format($totalRecords) ?></p>
    </div>
    <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/applications/export-csv?<?= http_build_query($_GET) ?>" class="btn btn-outline-success fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> <?= e(__('export_csv')) ?>
        </a>
        <?php if ($currentUser['role'] === 'USER'): ?>
            <a href="/applications/new" class="btn btn-primary fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> <?= e(__('new_application')) ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Filters Card -->
<div class="wb-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/applications" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Ref No / Name / Mobile / ID..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">-- All Statuses --</option>
                    <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>><?= e(__('status_pending')) ?></option>
                    <option value="UNDER_REVIEW" <?= $status === 'UNDER_REVIEW' ? 'selected' : '' ?>><?= e(__('status_under_review')) ?></option>
                    <option value="APPROVED" <?= $status === 'APPROVED' ? 'selected' : '' ?>><?= e(__('status_approved')) ?></option>
                    <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>><?= e(__('status_rejected')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="data_type_id" class="form-select">
                    <option value="">-- All Services --</option>
                    <?php foreach ($dataTypes as $dt): ?>
                        <option value="<?= $dt['id'] ?>" <?= $dataTypeId === (int)$dt['id'] ? 'selected' : '' ?>>
                            <?= e($dt['name_en'] ?: $dt['name_hi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="district_id" class="form-select">
                    <option value="">-- All Districts --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $districtId === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-filter me-1"></i> <?= e(__('filter')) ?>
                </button>
                <a href="/applications" class="btn btn-outline-secondary" title="<?= e(__('reset')) ?>">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Applications Table Card -->
<div class="wb-card">
    <div class="card-body p-0">
        <?php if (empty($applications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 opacity-50"></i>
                <h5 class="fw-bold mt-2">No Applications Found</h5>
                <p class="small text-muted mb-3">No matching records found for the applied search criteria.</p>
                <a href="/applications" class="btn btn-sm btn-outline-primary rounded-pill">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ref Number</th>
                            <th>Service</th>
                            <th>Applicant</th>
                            <th>District &amp; Block</th>
                            <th>Fee</th>
                            <?php if ($currentUser['role'] !== 'USER'): ?>
                                <th>Operator</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Date</th>
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
                                    <?php if (!empty($app['applicant_identifier'])): ?>
                                        <div class="small text-muted font-monospace"><?= e($app['applicant_identifier']) ?></div>
                                    <?php endif; ?>
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
                                <?php if ($currentUser['role'] !== 'USER'): ?>
                                    <td>
                                        <span class="small fw-semibold"><?= e($app['operator_name']) ?></span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge-status badge-<?= strtolower($app['status']) ?>">
                                        <?= e(__('status_' . strtolower($app['status']))) ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M Y, h:i A', strtotime($app['created_at'])) ?>
                                </td>
                                <td class="text-end">
                                    <a href="/applications/view?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= e(__('view_details')) ?>">
                                        <i class="bi bi-arrow-right-circle me-1"></i> <?= e(__('view_details')) ?>
                                    </a>
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
                        Page <?= $page ?> of <?= $totalPages ?> (Total <?= $totalRecords ?> items)
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
