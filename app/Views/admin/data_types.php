<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-card-checklist text-primary me-2"></i><?= e(__('data_types_manager')) ?>
        </h1>
        <p class="text-muted mb-0">Manage 3 Configurable Data Types, Fees, and Commissions</p>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#dataTypeModal" onclick="resetDataTypeModal()">
            <i class="bi bi-plus-circle me-1"></i> Add New Data Type
        </button>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($dataTypes as $dt): ?>
        <div class="col-md-6 col-lg-4">
            <div class="wb-card h-100 d-flex flex-column border shadow-sm">
                <div class="wb-card-header bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?= e($dt['icon']) ?> fs-4 text-primary"></i>
                        <span class="fw-bold fs-6"><?= e($dt['name_en'] ?: $dt['name_hi']) ?></span>
                    </div>
                    <span class="badge <?= $dt['is_active'] ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary' ?>">
                        <?= $dt['is_active'] ? 'Active' : 'Disabled' ?>
                    </span>
                </div>
                <div class="card-body p-4 flex-grow-1">
                    <h6 class="fw-semibold text-muted mb-2">Code: <code><?= e($dt['code']) ?></code></h6>
                    <p class="small text-secondary mb-3">
                        <?= e($dt['description_en'] ?: $dt['description_hi']) ?>
                    </p>

                    <div class="row g-2 mb-3 p-3 bg-light rounded-3 border">
                        <div class="col-6">
                            <span class="small text-muted text-uppercase fw-semibold d-block">App Fee:</span>
                            <span class="fs-5 fw-bold text-danger">₹<?= number_format($dt['fee_amount'], 2) ?></span>
                        </div>
                        <div class="col-6">
                            <span class="small text-muted text-uppercase fw-semibold d-block">Commission:</span>
                            <span class="fs-5 fw-bold text-success">₹<?= number_format($dt['distributor_commission'], 2) ?></span>
                        </div>
                        <div class="col-12 mt-2 pt-2 border-top small text-muted">
                            Duplicate Check On: <code><?= e($dt['duplicate_check_field']) ?></code>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between text-muted small mb-0">
                        <span><i class="bi bi-ui-checks me-1"></i> <?= (int)$dt['field_count'] ?> Dynamic Fields</span>
                        <span><i class="bi bi-folder me-1"></i> <?= (int)$dt['app_count'] ?> Submissions</span>
                    </div>
                </div>
                <div class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
                    <a href="/admin/data-types/fields?id=<?= $dt['id'] ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                        <i class="bi bi-sliders me-1"></i> Manage Fields (<?= (int)$dt['field_count'] ?>)
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick='editDataType(<?= json_encode($dt) ?>)'>
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Add / Edit Data Type -->
<div class="modal fade" id="dataTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="/admin/data-types/save" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="dt_id" value="0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="dtModalTitle">Configure Data Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Service Code (Slug) <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="dt_code" class="form-control" placeholder="e.g. bocw_registration" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Icon (Bootstrap Icon Class)</label>
                        <input type="text" name="icon" id="dt_icon" class="form-control" placeholder="e.g. bi-person-badge">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Name (English) <span class="text-danger">*</span></label>
                        <input type="text" name="name_en" id="dt_name_en" class="form-control" required placeholder="e.g. BOCW Shramik Registration">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Alternate Name (Optional)</label>
                        <input type="text" name="name_hi" id="dt_name_hi" class="form-control" placeholder="Optional alternate name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Application Fee (INR) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="fee_amount" id="dt_fee" class="form-control" required value="50.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Distributor Commission (INR)</label>
                        <input type="number" step="0.01" name="distributor_commission" id="dt_comm" class="form-control" value="10.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Duplicate Check Field</label>
                        <input type="text" name="duplicate_check_field" id="dt_dup_field" class="form-control" placeholder="e.g. aadhaar_no or ration_card_no" value="aadhaar_no">
                    </div>
                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="dt_active" value="1" checked>
                            <label class="form-check-label fw-semibold" for="dt_active">Service Is Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description (English)</label>
                        <textarea name="description_en" id="dt_desc_hi" rows="2" class="form-control" placeholder="Short description of this citizen data service..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Save Configuration</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetDataTypeModal() {
    document.getElementById('dt_id').value = '0';
    document.getElementById('dt_code').value = '';
    document.getElementById('dt_name_en').value = '';
    document.getElementById('dt_name_hi').value = '';
    document.getElementById('dt_icon').value = 'bi-folder2-open';
    document.getElementById('dt_fee').value = '50.00';
    document.getElementById('dt_comm').value = '10.00';
    document.getElementById('dt_dup_field').value = 'aadhaar_no';
    document.getElementById('dt_desc_hi').value = '';
    document.getElementById('dt_active').checked = true;
    document.getElementById('dtModalTitle').textContent = 'Add New Data Type';
}

function editDataType(dt) {
    document.getElementById('dt_id').value = dt.id;
    document.getElementById('dt_code').value = dt.code;
    document.getElementById('dt_name_en').value = dt.name_en;
    document.getElementById('dt_name_hi').value = dt.name_hi;
    document.getElementById('dt_icon').value = dt.icon;
    document.getElementById('dt_fee').value = dt.fee_amount;
    document.getElementById('dt_comm').value = dt.distributor_commission;
    document.getElementById('dt_dup_field').value = dt.duplicate_check_field;
    document.getElementById('dt_desc_hi').value = dt.description_hi || dt.description_en;
    document.getElementById('dt_active').checked = (dt.is_active == 1);
    document.getElementById('dtModalTitle').textContent = 'Edit Data Type: ' + dt.name_en;
    const modal = new bootstrap.Modal(document.getElementById('dataTypeModal'));
    modal.show();
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
