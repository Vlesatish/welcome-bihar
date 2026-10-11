<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <a href="/admin/data-types" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Data Types
        </a>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-sliders text-primary me-2"></i>Field Builder: <?= e($dataType['name_en'] ?: $dataType['name_hi']) ?>
        </h1>
        <div class="text-muted small">
            Configure form structure, validation rules, input types, and required constraints for <?= e($dataType['name_en']) ?>
        </div>
    </div>
    <div>
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#fieldModal" onclick="resetFieldModal()">
            <i class="bi bi-plus-circle me-1"></i> Add Dynamic Field
        </button>
    </div>
</div>

<div class="wb-card">
    <div class="card-body p-0">
        <?php if (empty($fields)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-ui-checks fs-1 opacity-50"></i>
                <h5 class="fw-bold mt-2">No Fields Configured</h5>
                <p class="small text-muted mb-3">Add fields to build the form for this data service.</p>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal" onclick="resetFieldModal()">
                    Add First Field
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Field Key / Name</th>
                            <th>Field Label</th>
                            <th>Input Type</th>
                            <th>Required?</th>
                            <th>Unique?</th>
                            <th>Validation Pattern</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $f): ?>
                            <tr>
                                <td class="fw-bold text-muted small"><?= (int)$f['sort_order'] ?></td>
                                <td class="font-monospace fw-semibold text-primary"><?= e($f['field_name']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($f['label_en'] ?: $f['label_hi']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <?= e($f['field_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $f['is_required'] ? 'bg-danger-subtle text-danger' : 'bg-light text-muted border' ?>">
                                        <?= $f['is_required'] ? 'Required' : 'Optional' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $f['is_unique'] ? 'bg-warning-subtle text-warning-emphasis' : 'bg-light text-muted border' ?>">
                                        <?= $f['is_unique'] ? 'Unique' : 'No' ?>
                                    </span>
                                </td>
                                <td class="small font-monospace text-muted">
                                    <?= e($f['validation_pattern'] ?: '-') ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                onclick='editField(<?= json_encode($f) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="/admin/data-types/fields/delete" onsubmit="return confirm('Delete this field from form?')" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Add / Edit Field -->
<div class="modal fade" id="fieldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="/admin/data-types/fields/save" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="f_id" value="0">
            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="fieldModalTitle">Configure Dynamic Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Field Machine Key <span class="text-danger">*</span></label>
                        <input type="text" name="field_name" id="f_name" class="form-control font-monospace" placeholder="e.g. aadhaar_no" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Input Type <span class="text-danger">*</span></label>
                        <select name="field_type" id="f_type" class="form-select" required onchange="toggleSelectOptions(this.value)">
                            <option value="text">Single Line Text</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="tel">Telephone / Mobile</option>
                            <option value="email">Email</option>
                            <option value="select">Select Dropdown</option>
                            <option value="textarea">Multi-line Textarea</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Label (English) <span class="text-danger">*</span></label>
                        <input type="text" name="label_en" id="f_label_en" class="form-control" placeholder="e.g. Aadhaar Number" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Alternate Label (Optional)</label>
                        <input type="text" name="label_hi" id="f_label_hi" class="form-control" placeholder="Optional alternate label">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Placeholder Text</label>
                        <input type="text" name="placeholder_en" id="f_placeholder" class="form-control" placeholder="e.g. Enter 12-digit Aadhaar">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Help Text</label>
                        <input type="text" name="help_text_en" id="f_help" class="form-control" placeholder="e.g. Please enter valid Aadhaar without spaces">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Validation Regex Pattern</label>
                        <input type="text" name="validation_pattern" id="f_pattern" class="form-control font-monospace" placeholder="e.g. ^[0-9]{12}$">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Display Sort Order</label>
                        <input type="number" name="sort_order" id="f_sort" class="form-control" value="0">
                    </div>

                    <!-- Dropdown Options (Visible if select) -->
                    <div class="col-12" id="select_options_group" style="display: none;">
                        <label class="form-label fw-semibold">Select Options (One per line: <code>value|label</code>)</label>
                        <textarea name="options_raw" id="f_options_raw" class="form-control font-monospace" rows="4"
                                  placeholder="Male|Male&#10;Female|Female&#10;Other|Other"></textarea>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_required" id="f_required" value="1" checked>
                            <label class="form-check-label fw-semibold" for="f_required">Mandatory / Required Field</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_unique" id="f_unique" value="1">
                            <label class="form-check-label fw-semibold" for="f_unique">Unique (Duplicate Check Identifier)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Save Field</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSelectOptions(val) {
    const grp = document.getElementById('select_options_group');
    if (grp) grp.style.display = (val === 'select') ? 'block' : 'none';
}

function resetFieldModal() {
    document.getElementById('f_id').value = '0';
    document.getElementById('f_name').value = '';
    document.getElementById('f_type').value = 'text';
    document.getElementById('f_label_en').value = '';
    document.getElementById('f_label_hi').value = '';
    document.getElementById('f_placeholder').value = '';
    document.getElementById('f_help').value = '';
    document.getElementById('f_pattern').value = '';
    document.getElementById('f_sort').value = '0';
    document.getElementById('f_options_raw').value = '';
    document.getElementById('f_required').checked = true;
    document.getElementById('f_unique').checked = false;
    toggleSelectOptions('text');
    document.getElementById('fieldModalTitle').textContent = 'Add Dynamic Field';
}

function editField(f) {
    document.getElementById('f_id').value = f.id;
    document.getElementById('f_name').value = f.field_name;
    document.getElementById('f_type').value = f.field_type;
    document.getElementById('f_label_en').value = f.label_en;
    document.getElementById('f_label_hi').value = f.label_hi;
    document.getElementById('f_placeholder').value = f.placeholder_hi || f.placeholder_en || '';
    document.getElementById('f_help').value = f.help_text_hi || f.help_text_en || '';
    document.getElementById('f_pattern').value = f.validation_pattern || '';
    document.getElementById('f_sort').value = f.sort_order;
    document.getElementById('f_required').checked = (f.is_required == 1);
    document.getElementById('f_unique').checked = (f.is_unique == 1);

    // Format raw options from JSON
    let raw = '';
    if (f.options_json) {
        try {
            const arr = JSON.parse(f.options_json);
            if (Array.isArray(arr)) {
                raw = arr.map(o => `${o.value}|${o.label_en || o.value}|${o.label_hi || o.label_en || o.value}`).join("\n");
            }
        } catch(e) {}
    }
    document.getElementById('f_options_raw').value = raw;
    toggleSelectOptions(f.field_type);
    document.getElementById('fieldModalTitle').textContent = 'Edit Field: ' + f.field_name;

    const modal = new bootstrap.Modal(document.getElementById('fieldModal'));
    modal.show();
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
