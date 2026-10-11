<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-geo-alt-fill text-danger me-2"></i><?= e(__('locations_manager')) ?>
        </h1>
        <p class="text-muted mb-0">Manage all 38 Bihar Districts and 708 Administrative Blocks / Urban Bodies</p>
    </div>
    <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="/admin/locations/export-csv" class="btn btn-outline-success fw-semibold">
            <i class="bi bi-download me-1"></i> Export Location CSV
        </a>
        <button type="button" class="btn btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#importCsvModal">
            <i class="bi bi-upload me-1"></i> Import CSV
        </button>
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addDistrictModal">
            <i class="bi bi-plus-circle me-1"></i> Add District
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Districts List Column -->
    <div class="col-lg-5">
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-map me-2 text-primary"></i>Districts (<?= count($districts) ?>)</span>
            </div>
            <div class="card-body p-0" style="max-height: 650px; overflow-y: auto;">
                <div class="list-group list-group-flush">
                    <?php foreach ($districts as $d): ?>
                        <a href="/admin/locations?district_id=<?= $d['id'] ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?= $selectedDistrictId === (int)$d['id'] ? 'active bg-primary text-white' : '' ?>">
                            <div>
                                <div class="fw-bold fs-6"><?= e($d['name']) ?></div>
                                <div class="small <?= $selectedDistrictId === (int)$d['id'] ? 'text-white-50' : 'text-muted' ?>">
                                    Code: <code><?= e($d['code']) ?></code>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= $selectedDistrictId === (int)$d['id'] ? 'bg-light text-primary' : 'bg-primary-subtle text-primary border border-primary' ?> rounded-pill">
                                    <?= (int)$d['block_count'] ?> Blocks
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Blocks Column -->
    <div class="col-lg-7">
        <?php if ($currentDistrict): ?>
            <div class="wb-card">
                <div class="wb-card-header">
                    <div>
                        <span class="fw-bold fs-6">Blocks in <?= e($currentDistrict['name']) ?></span>
                        <span class="badge bg-secondary ms-2"><?= count($blocks) ?> Total</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#addBlockModal">
                        <i class="bi bi-plus-lg me-1"></i> Add Block
                    </button>
                </div>
                <div class="card-body p-0" style="max-height: 650px; overflow-y: auto;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Block Name</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th class="text-end">Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($blocks as $b): ?>
                                    <tr>
                                        <td class="small text-muted"><?= $i++ ?></td>
                                        <td class="fw-bold text-dark"><?= e($b['name']) ?></td>
                                        <td class="font-monospace small"><?= e($b['code']) ?></td>
                                        <td>
                                            <span class="badge <?= $b['is_active'] ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary' ?>">
                                                <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                    onclick="editBlock(<?= $b['id'] ?>, '<?= addslashes($b['name']) ?>', '<?= addslashes($b['name_hi']) ?>', '<?= addslashes($b['code']) ?>', <?= $b['is_active'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="wb-card p-5 text-center text-muted">
                <i class="bi bi-arrow-left-circle fs-1 text-primary opacity-50 mb-3 d-block"></i>
                <h5 class="fw-bold">Select a District</h5>
                <p class="small text-muted mb-0">Click on any district from the left panel to inspect and manage its blocks.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Add / Edit District -->
<div class="modal fade" id="addDistrictModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/admin/locations/save-district" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="dist_id" value="0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">District</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">District Name (English) <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="dist_name" class="form-control" required placeholder="e.g. Patna">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Alternate Name (Optional)</label>
                    <input type="text" name="name_hi" id="dist_name_hi" class="form-control" placeholder="Optional alternate name">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">District Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="dist_code" class="form-control text-uppercase" required placeholder="e.g. PAT">
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="dist_active" value="1" checked>
                    <label class="form-check-label fw-semibold" for="dist_active">Is Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Save District</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add / Edit Block -->
<div class="modal fade" id="addBlockModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/admin/locations/save-block" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="block_id" value="0">
            <input type="hidden" name="district_id" value="<?= $selectedDistrictId ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Block</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Block Name (English) <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="block_name" class="form-control" required placeholder="e.g. Danapur">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Alternate Name (Optional)</label>
                    <input type="text" name="name_hi" id="block_name_hi" class="form-control" placeholder="Optional alternate name">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Block Code</label>
                    <input type="text" name="code" id="block_code" class="form-control" placeholder="e.g. 6483232">
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="block_active" value="1" checked>
                    <label class="form-check-label fw-semibold" for="block_active">Is Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Save Block</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Import CSV -->
<div class="modal fade" id="importCsvModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/admin/locations/import-csv" enctype="multipart/form-data" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Import Location CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    CSV format must have columns: <code>District Name, District Code, Block Name, Block Code, Is Active</code>.
                </p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Start Import</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBlock(id, name, nameHi, code, isActive) {
    document.getElementById('block_id').value = id;
    document.getElementById('block_name').value = name;
    document.getElementById('block_name_hi').value = nameHi;
    document.getElementById('block_code').value = code;
    document.getElementById('block_active').checked = (isActive == 1);
    const modal = new bootstrap.Modal(document.getElementById('addBlockModal'));
    modal.show();
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
