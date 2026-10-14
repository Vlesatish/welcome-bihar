<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <a href="/admin/data-types" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Services
        </a>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-sliders text-primary me-2"></i>Form Builder: <?= e($dataType['name_en'] ?: $dataType['name_hi']) ?>
        </h1>
        <div class="text-muted small">
            Configure dynamic fields, document uploads, validation rules, and input constraints for <?= e($dataType['name_en']) ?>
        </div>
    </div>
    <div>
        <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#fieldModal" onclick="resetFieldModal()">
            <i class="bi bi-plus-circle-fill me-1"></i> Add Form Field
        </button>
    </div>
</div>

<div class="wb-card">
    <div class="card-body p-0">
        <?php if (empty($fields)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-ui-checks fs-1 opacity-50"></i>
                <h5 class="fw-bold mt-2">No Form Fields Configured</h5>
                <p class="small text-muted mb-3">Add configurable fields (text, number, select, date, textarea, or document upload) to build the citizen application form.</p>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal" onclick="resetFieldModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add First Field
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Order</th>
                            <th>Field Machine Key</th>
                            <th>Field Label</th>
                            <th>Field Type</th>
                            <th>Required?</th>
                            <th>Unique?</th>
                            <th>Specifications / Validation</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $f): ?>
                            <tr>
                                <td class="fw-bold text-muted small">#<?= (int)$f['sort_order'] ?></td>
                                <td class="font-monospace fw-semibold text-primary"><?= e($f['field_name']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($f['label_en']) ?></div>
                                </td>
                                <td>
                                    <?php if ($f['field_type'] === 'file'): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary">
                                            <i class="bi bi-file-earmark-arrow-up me-1"></i> Document Upload
                                        </span>
                                    <?php elseif ($f['field_type'] === 'select'): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis border">
                                            <i class="bi bi-ui-radios me-1"></i> Dropdown Select
                                        </span>
                                    <?php elseif ($f['field_type'] === 'textarea'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">
                                            <i class="bi bi-text-paragraph me-1"></i> Textarea
                                        </span>
                                    <?php elseif ($f['field_type'] === 'date'): ?>
                                        <span class="badge bg-warning-subtle text-dark border">
                                            <i class="bi bi-calendar-event me-1"></i> Date
                                        </span>
                                    <?php elseif ($f['field_type'] === 'number'): ?>
                                        <span class="badge bg-success-subtle text-success border">
                                            <i class="bi bi-123 me-1"></i> Number
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-input-cursor-text me-1"></i> <?= e($f['field_type']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $f['is_required'] ? 'bg-danger-subtle text-danger border border-danger' : 'bg-light text-muted border' ?>">
                                        <?= $f['is_required'] ? 'Mandatory' : 'Optional' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $f['is_unique'] ? 'bg-warning-subtle text-warning-emphasis border border-warning' : 'bg-light text-muted border' ?>">
                                        <?= $f['is_unique'] ? 'Duplicate Check' : 'No' ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?php if ($f['field_type'] === 'file'): ?>
                                        <div class="text-secondary">Types: <code><?= e($f['file_types'] ?? 'pdf,jpg,png') ?></code> (Max: <?= (int)($f['max_size_mb'] ?? 2) ?>MB)</div>
                                    <?php elseif ($f['field_type'] === 'select'): ?>
                                        <div class="text-secondary text-truncate" style="max-width: 220px;" title="<?= e($f['options_json']) ?>">
                                            Options: <code><?= e($f['options_json']) ?></code>
                                        </div>
                                    <?php elseif (!empty($f['validation_pattern'])): ?>
                                        <div class="text-secondary font-monospace">Regex: <code><?= e($f['validation_pattern']) ?></code></div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary py-0 px-2"
                                                onclick='editField(<?= json_encode($f) ?>)' title="Edit Field">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="/admin/data-types/fields/delete" onsubmit="return confirm('Delete this field from form?')" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger py-0 px-2" title="Delete Field">
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

<!-- Modal: Add / Edit Dynamic Field -->
<div class="modal fade" id="fieldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="/admin/data-types/fields/save" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="f_id" value="0">
            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="fieldModalTitle">Configure Form Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Field Machine Key <span class="text-danger">*</span></label>
                        <input type="text" name="field_name" id="f_name" class="form-control font-monospace" placeholder="e.g. aadhaar_no, ration_card_doc" required>
                        <div class="form-text small">Unique alphanumeric key (e.g. <code>applicant_income</code>, <code>caste_cert_doc</code>).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Input Type <span class="text-danger">*</span></label>
                        <select name="field_type" id="f_type" class="form-select" required onchange="toggleFieldTypeInputs(this.value)">
                            <option value="text">Single Line Text</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="tel">Telephone / Mobile</option>
                            <option value="email">Email</option>
                            <option value="select">Select Dropdown</option>
                            <option value="textarea">Multi-line Textarea</option>
                            <option value="file">Document Upload (PDF, JPG, PNG)</option>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Field Label <span class="text-danger">*</span></label>
                        <input type="text" name="label_en" id="f_label_en" class="form-control" placeholder="e.g. Aadhaar Card Number" required>
                        <input type="hidden" name="label_hi" id="f_label_hi" value="">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Placeholder Text</label>
                        <input type="text" name="placeholder_en" id="f_placeholder" class="form-control" placeholder="e.g. Enter 12-digit Aadhaar number">
                        <input type="hidden" name="placeholder_hi" id="f_placeholder_hi" value="">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Help Text / Guidance</label>
                        <input type="text" name="help_text_en" id="f_help" class="form-control" placeholder="e.g. Keep your registered mobile phone handy for OTP">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" id="f_sort" class="form-control" value="0">
                    </div>

                    <!-- Document Upload Settings -->
                    <div class="col-12 p-3 bg-light rounded-3 border" id="file_settings_group" style="display: none;">
                        <h6 class="fw-bold mb-2 text-primary"><i class="bi bi-file-earmark-arrow-up me-1"></i>Document Upload Settings</h6>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold small">Allowed File Extensions</label>
                                <input type="text" name="file_types" id="f_file_types" class="form-control font-monospace" placeholder="pdf,jpg,jpeg,png" value="pdf,jpg,jpeg,png">
                                <div class="form-text small">Comma separated list (e.g. <code>pdf,jpg,jpeg,png</code>).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Maximum File Size (MB)</label>
                                <input type="number" name="max_size_mb" id="f_max_size_mb" class="form-control" value="2" min="1" max="10">
                            </div>
                        </div>
                    </div>

                    <!-- Dropdown Options (Visible if select) -->
                    <div class="col-12" id="select_options_group" style="display: none;">
                        <label class="form-label fw-semibold">Select Options (One per line: <code>value|Label</code>)</label>
                        <textarea name="options_raw" id="f_options_raw" class="form-control font-monospace" rows="4"
                                  placeholder="M|Male&#10;F|Female&#10;O|Other"></textarea>
                    </div>

                    <!-- Regular Expression Validation Pattern -->
                    <div class="col-12" id="regex_pattern_group">
                        <label class="form-label fw-semibold">Validation Regex Pattern (Optional)</label>
                        <input type="text" name="validation_pattern" id="f_pattern" class="form-control font-monospace" placeholder="e.g. ^[0-9]{12}$ for 12 digits">
                        <div class="form-text small">Leave blank if standard input validation is sufficient.</div>
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
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">
                    <i class="bi bi-save me-1"></i> Save Field
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleFieldTypeInputs(val) {
    const selGrp = document.getElementById('select_options_group');
    const fileGrp = document.getElementById('file_settings_group');
    const regexGrp = document.getElementById('regex_pattern_group');

    if (selGrp) selGrp.style.display = (val === 'select') ? 'block' : 'none';
    if (fileGrp) fileGrp.style.display = (val === 'file') ? 'block' : 'none';
    if (regexGrp) regexGrp.style.display = (val === 'file') ? 'none' : 'block';
}

function resetFieldModal() {
    document.getElementById('f_id').value = '0';
    document.getElementById('f_name').value = '';
    document.getElementById('f_type').value = 'text';
    document.getElementById('f_label_en').value = '';
    document.getElementById('f_label_hi').value = '';
    document.getElementById('f_placeholder').value = '';
    document.getElementById('f_placeholder_hi').value = '';
    document.getElementById('f_help').value = '';
    document.getElementById('f_pattern').value = '';
    document.getElementById('f_file_types').value = 'pdf,jpg,jpeg,png';
    document.getElementById('f_max_size_mb').value = '2';
    document.getElementById('f_sort').value = '0';
    document.getElementById('f_options_raw').value = '';
    document.getElementById('f_required').checked = true;
    document.getElementById('f_unique').checked = false;
    toggleFieldTypeInputs('text');
    document.getElementById('fieldModalTitle').textContent = 'Add Form Field';
}

function editField(f) {
    document.getElementById('f_id').value = f.id;
    document.getElementById('f_name').value = f.field_name;
    document.getElementById('f_type').value = f.field_type;
    document.getElementById('f_label_en').value = f.label_en;
    document.getElementById('f_label_hi').value = f.label_hi || '';
    document.getElementById('f_placeholder').value = f.placeholder_en || '';
    document.getElementById('f_placeholder_hi').value = f.placeholder_hi || '';
    document.getElementById('f_help').value = f.help_text_en || f.help_text_hi || '';
    document.getElementById('f_pattern').value = f.validation_pattern || '';
    document.getElementById('f_file_types').value = f.file_types || 'pdf,jpg,jpeg,png';
    document.getElementById('f_max_size_mb').value = f.max_size_mb || 2;
    document.getElementById('f_sort').value = f.sort_order || 0;
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
    toggleFieldTypeInputs(f.field_type);
    document.getElementById('fieldModalTitle').textContent = 'Edit Field: ' + f.field_name;

    const modal = new bootstrap.Modal(document.getElementById('fieldModal'));
    modal.show();
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
