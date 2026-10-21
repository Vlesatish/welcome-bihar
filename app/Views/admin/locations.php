<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Header with Action Buttons -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-geo-alt-fill text-danger me-2"></i><?= e(__('locations_manager', 'Districts & Blocks')) ?>
        </h1>
        <p class="text-muted small mb-0">Manage all 38 Bihar Districts and 708 Administrative Blocks / Urban Bodies</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/service-availability" class="btn btn-outline-success fw-semibold btn-sm">
            <i class="bi bi-toggle-on me-1"></i> Service Availability
        </a>
        <a href="/admin/locations/export-csv" class="btn btn-outline-secondary fw-semibold btn-sm">
            <i class="bi bi-download me-1"></i> Export Location CSV
        </a>
        <button type="button" class="btn btn-outline-primary fw-semibold btn-sm" data-bs-toggle="modal" data-bs-target="#importCsvModal">
            <i class="bi bi-upload me-1"></i> Import CSV
        </button>
        <button type="button" class="btn btn-primary fw-bold btn-sm shadow-xs" onclick="openAddDistrictModal()">
            <i class="bi bi-plus-circle me-1"></i> Add District
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Districts List Column (Left Panel) -->
    <div class="col-lg-4 col-md-5">
        <div class="wb-card h-100 mb-0 shadow-sm border">
            <div class="wb-card-header d-flex flex-column gap-2 p-3 bg-white border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark fs-6">
                        <i class="bi bi-map me-2 text-primary"></i>Districts
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1" id="distCountBadge">
                        <?= count($districts) ?>
                    </span>
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="districtSearchInput" class="form-control border-start-0 ps-0 bg-light" placeholder="Search district name or code..." onkeyup="filterDistrictsLive()">
                </div>
            </div>
            <div class="card-body p-0" style="max-height: calc(100vh - 280px); min-height: 520px; overflow-y: auto;">
                <div class="list-group list-group-flush" id="districtsList">
                    <?php foreach ($districts as $d): ?>
                        <?php $isSelected = ($selectedDistrictId === (int)$d['id']); ?>
                        <a href="/admin/locations?district_id=<?= $d['id'] ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 district-list-item <?= $isSelected ? 'active bg-primary text-white border-primary shadow-xs' : '' ?>"
                           data-search="<?= strtolower(e($d['name'] . ' ' . ($d['name_hi'] ?? '') . ' ' . $d['code'])) ?>"
                           title="<?= e($d['name']) ?> (<?= (int)$d['block_count'] ?> Blocks)">
                            <div class="me-2 overflow-hidden">
                                <div class="fw-bold fs-6 mb-0 text-truncate">
                                    <?= e($d['name']) ?>
                                    <?php if (!empty($d['name_hi'])): ?>
                                        <span class="small fw-normal <?= $isSelected ? 'text-white-50' : 'text-muted' ?>">(<?= e($d['name_hi']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small <?= $isSelected ? 'text-white-50' : 'text-muted' ?>">
                                    Code: <code class="<?= $isSelected ? 'text-white bg-white-20 px-1 rounded' : '' ?>"><?= e($d['code']) ?></code>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                <span class="badge <?= $isSelected ? 'bg-light text-primary' : 'bg-primary-subtle text-primary border border-primary-subtle' ?> rounded-pill text-nowrap px-2.5 py-1" style="font-size: 0.75rem;">
                                    <?= (int)$d['block_count'] ?> Blocks
                                </span>
                                <?php if ($isSelected): ?>
                                    <i class="bi bi-chevron-right text-white small"></i>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Blocks Column (Right Panel) -->
    <div class="col-lg-8 col-md-7">
        <?php if ($currentDistrict): ?>
            <div class="wb-card h-100 mb-0 shadow-sm border">
                <div class="wb-card-header d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 bg-white border-bottom">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-bold fs-6 text-dark">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>Blocks in <?= e($currentDistrict['name']) ?>
                        </span>
                        <?php if (!empty($currentDistrict['name_hi'])): ?>
                            <span class="text-muted small">(<?= e($currentDistrict['name_hi']) ?>)</span>
                        <?php endif; ?>
                        <span class="badge bg-secondary-subtle text-secondary border small rounded-pill px-2.5 py-1" id="blockCountBadge">
                            <?= count($blocks) ?> Total
                        </span>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 rounded-2 ms-1"
                                onclick="editDistrict(<?= (int)$currentDistrict['id'] ?>, '<?= addslashes($currentDistrict['name']) ?>', '<?= addslashes($currentDistrict['name_hi'] ?? '') ?>', '<?= addslashes($currentDistrict['code']) ?>', <?= (int)$currentDistrict['is_active'] ?>)"
                                title="Edit District Details">
                            <i class="bi bi-pencil me-1"></i> Edit District
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="blockSearchInput" class="form-control border-start-0 ps-0 bg-light" placeholder="Search block..." onkeyup="filterBlocksLive()" style="max-width: 180px;">
                        </div>
                        <button type="button" class="btn btn-sm btn-primary fw-semibold text-nowrap shadow-xs" onclick="openAddBlockModal()">
                            <i class="bi bi-plus-lg me-1"></i> Add Block
                        </button>
                    </div>
                </div>
                <div class="card-body p-0" style="max-height: calc(100vh - 280px); min-height: 520px; overflow-y: auto;">
                    <?php if (empty($blocks)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-inbox fs-1 text-secondary opacity-50 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark">No Blocks Found</h6>
                            <p class="small text-muted mb-3">No administrative blocks are currently registered under <?= e($currentDistrict['name']) ?>.</p>
                            <button type="button" class="btn btn-sm btn-primary fw-semibold" onclick="openAddBlockModal()">
                                <i class="bi bi-plus-circle me-1"></i> Add First Block
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="blocksTable">
                                <thead class="table-light border-bottom text-secondary small text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                    <tr>
                                        <th class="ps-3" style="width: 50px;">#</th>
                                        <th>Block Name</th>
                                        <th>Hindi / Local</th>
                                        <th>Code</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($blocks as $b): ?>
                                        <tr class="block-table-row" data-search="<?= strtolower(e($b['name'] . ' ' . ($b['name_hi'] ?? '') . ' ' . $b['code'])) ?>">
                                            <td class="ps-3 small text-muted font-monospace"><?= $i++ ?></td>
                                            <td class="fw-bold text-dark"><?= e($b['name']) ?></td>
                                            <td class="text-muted small"><?= e($b['name_hi'] ?: '—') ?></td>
                                            <td class="font-monospace small">
                                                <span class="badge bg-light text-dark border font-monospace"><?= e($b['code'] ?: '—') ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $b['is_active'] ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?> rounded-pill px-2.5 py-1">
                                                    <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-3">
                                                <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 rounded-2 fw-semibold"
                                                        onclick="editBlock(<?= (int)$b['id'] ?>, '<?= addslashes($b['name']) ?>', '<?= addslashes($b['name_hi'] ?? '') ?>', '<?= addslashes($b['code'] ?? '') ?>', <?= (int)$b['is_active'] ?>)"
                                                        title="Edit Block">
                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty State when no district is selected -->
            <div class="wb-card h-100 mb-0 shadow-sm border d-flex flex-column align-items-center justify-content-center p-5 text-center text-muted" style="min-height: 520px;">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-geo-alt fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Select a District</h5>
                <p class="small text-muted mb-4" style="max-width: 360px;">
                    Click on any of the 38 Bihar districts from the left list to inspect, edit, or configure its administrative blocks and codes.
                </p>
                <?php if (!empty($districts)): ?>
                    <a href="/admin/locations?district_id=<?= $districts[0]['id'] ?>" class="btn btn-sm btn-outline-primary fw-semibold rounded-pill px-3 shadow-xs">
                        <i class="bi bi-arrow-right-circle me-1"></i> Inspect <?= e($districts[0]['name']) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Add / Edit District -->
<div class="modal fade" id="addDistrictModal" tabindex="-1" aria-hidden="true">
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
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="distModalTitle">District Configuration</h5>
                        <div class="text-muted small" style="font-size: 0.74rem;">Add or update district details</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        District Name (English) <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name" id="dist_name" class="form-control" required placeholder="e.g. Patna">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        Alternate Name (Hindi / Local)
                    </label>
                    <input type="text" name="name_hi" id="dist_name_hi" class="form-control" placeholder="e.g. पटना">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        District Code <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="code" id="dist_code" class="form-control text-uppercase font-monospace" required placeholder="e.g. PAT">
                </div>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="dist_active" value="1" checked>
                    <label class="form-check-label fw-semibold small text-dark" for="dist_active">Is Active (Immediately Available)</label>
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
<div class="modal fade" id="addBlockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/locations/save-block" class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="block_id" value="0">
            <input type="hidden" name="district_id" value="<?= (int)($currentDistrict['id'] ?? $selectedDistrictId) ?>">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3" style="width: 38px; height: 38px;">
                        <i class="bi bi-geo-alt-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="blockModalTitle">Block Configuration</h5>
                        <div class="text-muted small" style="font-size: 0.74rem;">Configure block within <?= e($currentDistrict['name'] ?? 'District') ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        Block Name (English) <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name" id="block_name" class="form-control" required placeholder="e.g. Danapur">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        Alternate Name (Hindi / Local)
                    </label>
                    <input type="text" name="name_hi" id="block_name_hi" class="form-control" placeholder="e.g. दानापुर">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        Block Code
                    </label>
                    <input type="text" name="code" id="block_code" class="form-control font-monospace text-uppercase" placeholder="e.g. DANAPUR">
                </div>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="block_active" value="1" checked>
                    <label class="form-check-label fw-semibold small text-dark" for="block_active">Is Active (Immediately Available)</label>
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
<div class="modal fade" id="importCsvModal" tabindex="-1" aria-hidden="true">
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 bg-info-subtle text-info-emphasis rounded-3 p-3 small mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    CSV format must have columns: <code>District Name, District Alt Name, District Code, Block Name, Block Alt Name, Block Code, Is Active</code>.
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        Select CSV File
                    </label>
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
function openAddDistrictModal() {
    document.getElementById('dist_id').value = '0';
    document.getElementById('dist_name').value = '';
    document.getElementById('dist_name_hi').value = '';
    document.getElementById('dist_code').value = '';
    document.getElementById('dist_active').checked = true;
    document.getElementById('distModalTitle').textContent = 'Add New District';
    const modal = new bootstrap.Modal(document.getElementById('addDistrictModal'));
    modal.show();
}

function editDistrict(id, name, nameHi, code, isActive) {
    document.getElementById('dist_id').value = id;
    document.getElementById('dist_name').value = name;
    document.getElementById('dist_name_hi').value = nameHi || '';
    document.getElementById('dist_code').value = code;
    document.getElementById('dist_active').checked = (isActive == 1);
    document.getElementById('distModalTitle').textContent = 'Edit District: ' + name;
    const modal = new bootstrap.Modal(document.getElementById('addDistrictModal'));
    modal.show();
}

function openAddBlockModal() {
    document.getElementById('block_id').value = '0';
    document.getElementById('block_name').value = '';
    document.getElementById('block_name_hi').value = '';
    document.getElementById('block_code').value = '';
    document.getElementById('block_active').checked = true;
    document.getElementById('blockModalTitle').textContent = 'Add Block';
    const modal = new bootstrap.Modal(document.getElementById('addBlockModal'));
    modal.show();
}

function editBlock(id, name, nameHi, code, isActive) {
    document.getElementById('block_id').value = id;
    document.getElementById('block_name').value = name;
    document.getElementById('block_name_hi').value = nameHi || '';
    document.getElementById('block_code').value = code || '';
    document.getElementById('block_active').checked = (isActive == 1);
    document.getElementById('blockModalTitle').textContent = 'Edit Block: ' + name;
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
