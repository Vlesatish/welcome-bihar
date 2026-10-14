<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-geo-alt-fill text-danger me-2"></i><?= e(__('locations_manager')) ?>
        </h1>
        <p class="text-muted mb-0">Manage all 38 Bihar Districts and 708 Administrative Blocks / Urban Bodies</p>
    </div>
        <a href="/admin/service-availability" class="btn btn-outline-success fw-semibold">
            <i class="bi bi-toggle-on me-1"></i> Service Availability
        </a>
        <a href="/admin/locations/export-csv" class="btn btn-outline-secondary fw-semibold">
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
            <div class="wb-card-header d-flex flex-column gap-2">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fw-bold"><i class="bi bi-map me-2 text-primary"></i>Districts (<?= count($districts) ?>)</span>
                    <span class="badge bg-light text-muted border small" id="distCountBadge"><?= count($districts) ?></span>
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="districtSearchInput" class="form-control border-start-0 ps-0" placeholder="Search district name or code..." onkeyup="filterDistrictsLive()">
                </div>
            </div>
            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                <div class="list-group list-group-flush" id="districtsList">
                    <?php foreach ($districts as $d): ?>
                        <a href="/admin/locations?district_id=<?= $d['id'] ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 district-list-item <?= $selectedDistrictId === (int)$d['id'] ? 'active bg-primary text-white' : '' ?>"
                           data-search="<?= strtolower(e($d['name'] . ' ' . $d['code'])) ?>">
                            <div>
                                <div class="fw-bold fs-6"><?= e($d['name']) ?></div>
                                <div class="small <?= $selectedDistrictId === (int)$d['id'] ? 'text-white-50' : 'text-muted' ?>">
                                    Code: <code><?= e($d['code']) ?></code>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= $selectedDistrictId === (int)$d['id'] ? 'bg-light text-primary' : 'bg-primary-subtle text-primary border border-primary-subtle' ?> rounded-pill">
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
                <div class="wb-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6">Blocks in <?= e($currentDistrict['name']) ?></span>
                        <span class="badge bg-secondary-subtle text-secondary border small" id="blockCountBadge"><?= count($blocks) ?> Total</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="blockSearchInput" class="form-control form-control-sm" placeholder="Search block..." onkeyup="filterBlocksLive()" style="max-width: 170px;">
                        <button type="button" class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#addBlockModal">
                            <i class="bi bi-plus-lg me-1"></i> Add Block
                        </button>
                    </div>
                </div>
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="blocksTable">
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
                                    <tr class="block-table-row" data-search="<?= strtolower(e($b['name'] . ' ' . ($b['name_hi'] ?? '') . ' ' . $b['code'])) ?>">
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
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/locations/save-district" class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="dist_id" value="0">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3" style="width: 38px; height: 38px;">
                        <i class="bi bi-map fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6">District Configuration</h5>
                        <div class="text-muted small" style="font-size: 0.74rem;">Add or update district details</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">District Name (English) <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="dist_name" class="form-control" required placeholder="e.g. Patna">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Alternate Name (Optional)</label>
                    <input type="text" name="name_hi" id="dist_name_hi" class="form-control" placeholder="Optional alternate name">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">District Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="dist_code" class="form-control text-uppercase font-monospace" required placeholder="e.g. PAT">
                </div>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="dist_active" value="1" checked>
                    <label class="form-check-label fw-semibold small" for="dist_active">Is Active (Immediately Available)</label>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2.5 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light border px-3 rounded-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4 rounded-2 shadow-sm">Save District</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add / Edit Block -->
<div class="modal fade" id="addBlockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/locations/save-block" class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="block_id" value="0">
            <input type="hidden" name="district_id" value="<?= $selectedDistrictId ?>">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3" style="width: 38px; height: 38px;">
                        <i class="bi bi-geo-alt-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6">Block Configuration</h5>
                        <div class="text-muted small" style="font-size: 0.74rem;">Configure block within <?= e($currentDistrict['name'] ?? 'District') ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Block Name (English) <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="block_name" class="form-control" required placeholder="e.g. Danapur">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Alternate Name (Optional)</label>
                    <input type="text" name="name_hi" id="block_name_hi" class="form-control" placeholder="Optional alternate name">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Block Code</label>
                    <input type="text" name="code" id="block_code" class="form-control font-monospace text-uppercase" placeholder="e.g. DANAPUR">
                </div>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="block_active" value="1" checked>
                    <label class="form-check-label fw-semibold small" for="block_active">Is Active (Immediately Available)</label>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2.5 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light border px-3 rounded-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4 rounded-2 shadow-sm">Save Block</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Import CSV -->
<div class="modal fade" id="importCsvModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/locations/import-csv" enctype="multipart/form-data" class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            <?= csrf_field() ?>
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-3" style="width: 38px; height: 38px;">
                        <i class="bi bi-file-earmark-spreadsheet-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6">Import Location CSV</h5>
                        <div class="text-muted small" style="font-size: 0.74rem;">Bulk upload districts and administrative blocks</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 bg-info-subtle text-info-emphasis rounded-3 p-3 small mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    CSV format must have columns: <code>District Name, District Code, Block Name, Block Code, Is Active</code>.
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Select CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2.5 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light border px-3 rounded-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4 rounded-2 shadow-sm">Start Import</button>
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

function filterDistrictsLive() {
    const q = (document.getElementById('districtSearchInput')?.value || '').toLowerCase().trim();
    const items = document.querySelectorAll('.district-list-item');
    let visible = 0;
    items.forEach(el => {
        const text = el.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
            el.style.display = '';
            visible++;
        } else {
            el.style.display = 'none';
        }
    });
    const badge = document.getElementById('distCountBadge');
    if (badge) badge.textContent = visible;
}

function filterBlocksLive() {
    const q = (document.getElementById('blockSearchInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.block-table-row');
    let visible = 0;
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
            r.style.display = '';
            visible++;
        } else {
            r.style.display = 'none';
        }
    });
    const badge = document.getElementById('blockCountBadge');
    if (badge) badge.textContent = visible + ' Total';
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
