<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1 d-flex align-items-center gap-2">
            <i class="bi bi-ui-checks-grid text-primary"></i>
            <span>Application Code Forms</span>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 rounded text-uppercase" style="font-size: 0.72rem;">
                Custom Field Builder
            </span>
        </h1>
        <div class="text-muted small">
            Configure dynamic fields, custom inputs, Aadhaar verification, and validations per Application Code.
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1 shadow-2xs" data-bs-toggle="modal" data-bs-target="#previewModal" onclick="buildPreviewForm()">
            <i class="bi bi-eye"></i>
            <span>Preview Form</span>
        </button>
        <button type="button" class="btn btn-sm btn-primary px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1 shadow-xs" data-bs-toggle="modal" data-bs-target="#addFieldModal" onclick="openAddFieldModal()">
            <i class="bi bi-plus-lg"></i>
            <span>Add Custom Field</span>
        </button>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success py-2 px-3 mb-3 small d-flex align-items-center gap-2 shadow-2xs">
        <i class="bi bi-check-circle-fill fs-6 text-success"></i>
        <div><?= e($_SESSION['flash_success']) ?></div>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger py-2 px-3 mb-3 small d-flex align-items-center gap-2 shadow-2xs">
        <i class="bi bi-exclamation-triangle-fill fs-6 text-danger"></i>
        <div><?= e($_SESSION['flash_error']) ?></div>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- APPLICATION CODE TABS -->
<div class="card shadow-sm border rounded-3 bg-white mb-3" style="border: 1px solid #e2e8f0;">
    <div class="card-body p-2.5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                <span class="text-muted small fw-semibold me-1"><i class="bi bi-tag-fill me-1 text-primary"></i>Service Code:</span>
                <?php if (empty($rateCodes)): ?>
                    <span class="badge bg-warning-subtle text-dark border">No Active Rate Codes Found</span>
                <?php else: ?>
                    <?php foreach ($rateCodes as $rc): ?>
                        <?php $isActiveTab = ($selectedCode === $rc['application_code']); ?>
                        <a href="/admin/application-code-forms?code=<?= urlencode($rc['application_code']) ?>" 
                           class="btn btn-sm <?= $isActiveTab ? 'btn-primary fw-bold shadow-xs' : 'btn-outline-secondary' ?> py-1 px-2.5 rounded-2 d-inline-flex align-items-center gap-1.5" style="font-size: 0.78rem;">
                            <span><?= e($rc['application_code']) ?></span>
                            <?php if (empty($rc['has_core_fields'])): ?>
                                <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.62rem;">Incomplete</span>
                            <?php else: ?>
                                <span class="badge <?= $isActiveTab ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill" style="font-size: 0.65rem;">
                                    <?= (int)$rc['field_count'] ?> fields
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-success py-1 px-2.5 rounded-2 d-inline-flex align-items-center gap-1 shadow-2xs" style="font-size: 0.78rem;" onclick="promptNewApplicationCode()">
                    <i class="bi bi-plus-circle"></i>
                    <span>+ New Code</span>
                </button>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="/admin/block-rates" class="btn btn-xs btn-outline-primary py-1 px-2 rounded-2" style="font-size: 0.74rem;">
                    <i class="bi bi-currency-rupee me-1"></i>Manage Rates
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!$hasCoreFields && $selectedCode !== ''): ?>
    <div class="alert alert-warning py-2.5 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-2xs rounded-3 border">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-octagon-fill fs-5 text-warning"></i>
            <div>
                <strong>Form Incomplete:</strong> Missing active core fields (<code>application_number</code> and <code>applicant_name</code>). This code is not available to retailers.
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="/admin/application-code-forms/init-core" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="code" value="<?= e($selectedCode) ?>">
                <button type="submit" class="btn btn-sm btn-dark fw-bold d-inline-flex align-items-center gap-1 shadow-xs">
                    <i class="bi bi-magic"></i>
                    <span>Initialize Core Fields</span>
                </button>
            </form>
            <form method="POST" action="/admin/application-code-forms/delete-code" class="m-0" onsubmit="return confirm(<?= htmlspecialchars(json_encode("Are you sure you want to delete Application Code \"{$selectedCode}\"?"), ENT_QUOTES, 'UTF-8') ?>)">
                <?= csrf_field() ?>
                <input type="hidden" name="code" value="<?= e($selectedCode) ?>">
                <button type="submit" class="btn btn-sm btn-danger fw-bold d-inline-flex align-items-center gap-1 shadow-xs">
                    <i class="bi bi-trash"></i>
                    <span>Delete Code</span>
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- CODE SPECIFIC FIELDS TABLE CARD -->
<div class="card shadow-sm border rounded-3 bg-white mb-4" style="border: 1px solid #e2e8f0;">
    <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-dark" style="font-size: 0.9rem;">
                Form Fields for <span class="text-primary font-monospace"><?= e($selectedCode) ?></span>
            </span>
            <span class="badge bg-light text-secondary border"><?= count($fields) ?> Fields</span>
            <?php if (!$hasCoreFields): ?>
                <span class="badge bg-warning text-dark border">Form Incomplete</span>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="text-muted small" style="font-size: 0.74rem;">
                <i class="bi bi-info-circle me-1"></i>Global code form: Configured fields automatically apply to all blocks for this code.
            </div>
            <?php if ($selectedCode !== ''): ?>
                <form method="POST" action="/admin/application-code-forms/delete-code" class="m-0 d-inline" onsubmit="return confirm(<?= htmlspecialchars(json_encode("Delete Application Code \"{$selectedCode}\" and all its configured fields/rates?"), ENT_QUOTES, 'UTF-8') ?>)">
                    <?= csrf_field() ?>
                    <input type="hidden" name="code" value="<?= e($selectedCode) ?>">
                    <button type="submit" class="btn btn-xs btn-outline-danger py-1 px-2 rounded-2 fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.74rem;" title="Delete Application Code <?= e($selectedCode) ?>">
                        <i class="bi bi-trash"></i>
                        <span>Delete Code</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;" class="text-center">Order</th>
                    <th style="width: 190px;">Field Label</th>
                    <th style="width: 170px;">Field Name</th>
                    <th style="width: 120px;">Input Type</th>
                    <th style="width: 90px;" class="text-center">Required</th>
                    <th style="width: 90px;" class="text-center">Unique</th>
                    <th style="width: 90px;" class="text-center">Status</th>
                    <th style="min-width: 160px;">Validation Rules</th>
                    <th style="width: 110px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="fieldTableBody">
                <?php if (empty($fields)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="bi bi-ui-checks fs-3 d-block mb-1 text-muted opacity-50"></i>
                            <div>No fields configured for <strong><?= e($selectedCode) ?></strong> yet.</div>
                            <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addFieldModal" onclick="openAddFieldModal()">
                                <i class="bi bi-plus-lg me-1"></i>Add First Field
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fields as $idx => $f): ?>
                        <?php 
                        $isCore = in_array($f['field_name'], \App\Services\ApplicationCodeFieldService::CORE_FIELDS, true);
                        $typeBadgeClass = 'bg-light text-dark border';
                        if ($f['field_type'] === 'aadhaar') $typeBadgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                        elseif ($f['field_type'] === 'select') $typeBadgeClass = 'bg-info-subtle text-info-emphasis border';
                        elseif ($f['field_type'] === 'file') $typeBadgeClass = 'bg-warning-subtle text-warning-emphasis border';
                        elseif ($f['field_type'] === 'number') $typeBadgeClass = 'bg-success-subtle text-success border';
                        ?>
                        <tr data-id="<?= (int)$f['id'] ?>" data-sort="<?= (int)$f['sort_order'] ?>">
                            <td class="text-center font-monospace fw-bold text-muted">
                                #<?= (int)$f['sort_order'] ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($f['label']) ?></div>
                                <?php if (!empty($f['help_text'])): ?>
                                    <div class="text-muted small" style="font-size: 0.72rem;"><?= e($f['help_text']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-monospace text-primary fw-semibold"><?= e($f['field_name']) ?></span>
                                <?php if ($isCore): ?>
                                    <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.65rem;">CORE</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $typeBadgeClass ?> text-uppercase" style="font-size: 0.7rem;">
                                    <?= e($f['field_type']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$f['is_required'] === 1): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$f['is_unique'] === 1): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">Unique</span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isCore): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Always Active</span>
                                <?php else: ?>
                                    <form method="POST" action="/admin/application-code-forms/toggle" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                        <input type="hidden" name="code" value="<?= e($selectedCode) ?>">
                                        <button type="submit" class="btn btn-xs <?= (int)$f['is_active'] === 1 ? 'btn-outline-success' : 'btn-outline-secondary' ?> py-0.5 px-1.5 rounded-pill" style="font-size: 0.7rem;">
                                            <i class="bi <?= (int)$f['is_active'] === 1 ? 'bi-check-circle-fill' : 'bi-dash-circle' ?> me-0.5"></i>
                                            <?= (int)$f['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-muted small" style="font-size: 0.72rem;">
                                    <?php if ($f['min_length'] || $f['max_length']): ?>
                                        <span>Len: <?= $f['min_length'] ?: '0' ?>–<?= $f['max_length'] ?: '∞' ?></span> &bull;
                                    <?php endif; ?>
                                    <?php if ($f['validation_pattern']): ?>
                                        <span class="font-monospace" title="<?= e($f['validation_pattern']) ?>">Regex</span> &bull;
                                    <?php endif; ?>
                                    <?php if ($f['field_type'] === 'select' || $f['field_type'] === 'radio'): ?>
                                        <span><?= count($f['options'] ?? []) ?> options</span>
                                    <?php elseif ($f['field_type'] === 'file'): ?>
                                        <span>Max: <?= $f['max_file_size_mb'] ?? 2 ?>MB (<?= e($f['allowed_file_types'] ?: 'pdf,jpg,png') ?>)</span>
                                    <?php else: ?>
                                        <span>Standard</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <button type="button" class="btn btn-xs btn-outline-primary p-1 rounded-2" title="Edit Field" onclick='editField(<?= json_encode($f) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (!$isCore): ?>
                                        <form method="POST" action="/admin/application-code-forms/delete" class="d-inline" onsubmit="return confirm('Delete or deactivate field <?= e($f['label']) ?>?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                            <input type="hidden" name="code" value="<?= e($selectedCode) ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger p-1 rounded-2" title="Delete Field">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-xs btn-outline-secondary p-1 rounded-2 disabled" title="Core fields cannot be deleted">
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD / EDIT FIELD MODAL -->
<div class="modal fade" id="addFieldModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="/admin/application-code-forms/save" id="fieldConfigForm">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="field_id" value="0">
                <input type="hidden" name="application_code" value="<?= e($selectedCode) ?>">

                <div class="modal-header border-bottom py-2.5 px-3">
                    <h5 class="modal-title h6 fw-bold mb-0 text-dark" id="fieldModalTitle">
                        Add Custom Field to <span class="text-primary font-monospace"><?= e($selectedCode) ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-3">
                    <div class="row g-2.5">
                        <!-- Field Label -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold mb-1">Field Label <span class="text-danger">*</span></label>
                            <input type="text" name="label" id="field_label" class="form-control form-control-sm rounded-2" placeholder="e.g. Aadhaar Number, Father Name" required oninput="autoSlugFieldName(this.value)">
                            <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">Display label shown to retailers</div>
                        </div>

                        <!-- Machine Field Name (lowercase snake_case) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold mb-1">Field Key (Machine Name) <span class="text-danger">*</span></label>
                            <input type="text" name="field_name" id="field_name_key" class="form-control form-control-sm font-monospace rounded-2" placeholder="e.g. aadhaar_number, father_name" required pattern="^[a-z][a-z0-9_]{1,63}$">
                            <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">Lowercase snake_case (letters, numbers, underscore)</div>
                        </div>

                        <!-- Input Type -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">Input Type <span class="text-danger">*</span></label>
                            <select name="field_type" id="field_type" class="form-select form-select-sm rounded-2" required onchange="onFieldTypeChange(this.value)">
                                <option value="text">Text (Single Line)</option>
                                <option value="number">Number</option>
                                <option value="mobile">Mobile (10 Digits)</option>
                                <option value="aadhaar">Aadhaar (12 Digits)</option>
                                <option value="date">Date</option>
                                <option value="select">Dropdown Select</option>
                                <option value="radio">Radio Options</option>
                                <option value="textarea">Textarea (Multi Line)</option>
                                <option value="file">File / Document Upload</option>
                                <option value="email">Email</option>
                            </select>
                        </div>

                        <!-- Sort Order -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">Sort Order</label>
                            <input type="number" name="sort_order" id="field_sort_order" class="form-control form-control-sm rounded-2" value="<?= count($fields) + 1 ?>">
                        </div>

                        <!-- Placeholder -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">Placeholder</label>
                            <input type="text" name="placeholder" id="field_placeholder" class="form-control form-control-sm rounded-2" placeholder="e.g. Enter 12-digit number">
                        </div>

                        <!-- Help Text -->
                        <div class="col-md-12">
                            <label class="form-label small fw-bold mb-1">Help Text / Subtext</label>
                            <input type="text" name="help_text" id="field_help_text" class="form-control form-control-sm rounded-2" placeholder="Brief guidance shown under the input">
                        </div>

                        <!-- Options Box (for Select / Radio) -->
                        <div class="col-md-12 d-none" id="optionsBox">
                            <label class="form-label small fw-bold mb-1">Options (One per line: value|Label)</label>
                            <textarea name="options_raw" id="field_options_raw" class="form-control form-control-sm font-monospace rounded-2" rows="3" placeholder="MALE|Male&#10;FEMALE|Female&#10;OTHER|Other"></textarea>
                            <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">Format: <code>value|Label</code> or just <code>Option Name</code> per line</div>
                        </div>

                        <!-- File Upload Settings -->
                        <div class="col-md-12 d-none" id="fileSettingsBox">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold mb-1">Allowed File Extensions</label>
                                    <input type="text" name="allowed_file_types" id="field_allowed_files" class="form-control form-control-sm rounded-2" value="pdf,jpg,jpeg,png" placeholder="e.g. pdf,jpg,jpeg,png">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold mb-1">Max File Size (MB)</label>
                                    <input type="number" name="max_file_size_mb" id="field_max_size_mb" class="form-control form-control-sm rounded-2" value="2" min="1" max="10">
                                </div>
                            </div>
                        </div>

                        <!-- Validation Constraints -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">Min Length / Min Value</label>
                            <input type="number" name="min_length" id="field_min_length" class="form-control form-control-sm rounded-2" placeholder="e.g. 2">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">Max Length / Max Value</label>
                            <input type="number" name="max_length" id="field_max_length" class="form-control form-control-sm rounded-2" placeholder="e.g. 64">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">Regex Pattern</label>
                            <input type="text" name="validation_pattern" id="field_regex" class="form-control form-control-sm font-monospace rounded-2" placeholder="e.g. ^[0-9]{12}$">
                        </div>

                        <!-- Checkboxes -->
                        <div class="col-12 mt-2">
                            <div class="p-2.5 bg-light border rounded-2 d-flex align-items-center gap-4 flex-wrap">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_required" id="field_is_required" value="1" checked>
                                    <label class="form-check-label small fw-bold text-dark" for="field_is_required">
                                        Required Field <span class="text-danger">*</span>
                                    </label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_unique" id="field_is_unique" value="1">
                                    <label class="form-check-label small fw-bold text-dark" for="field_is_unique">
                                        Unique Constraint (Prevents duplicate entries across this code)
                                    </label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="field_is_active" value="1" checked>
                                    <label class="form-check-label small fw-bold text-dark" for="field_is_active">
                                        Active in Form
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top py-2 px-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">Save Field</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- LIVE PREVIEW MODAL -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2.5 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-eye text-primary"></i>
                    <h5 class="modal-title h6 fw-bold mb-0 text-dark">
                        Live Preview: <span class="text-primary font-monospace"><?= e($selectedCode) ?> Form</span>
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info py-1.5 px-2.5 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>This is how retail operators will see the input fields when selecting <strong><?= e($selectedCode) ?></strong>.
                </div>
                <div id="previewFormContainer" class="row g-2.5">
                    <!-- Built dynamically via JS -->
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-3">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close Preview</button>
            </div>
        </div>
    </div>
</div>

<script>
const currentFields = <?= json_encode($fields) ?>;

function openAddFieldModal() {
    document.getElementById('fieldModalTitle').innerHTML = 'Add Custom Field to <span class="text-primary font-monospace"><?= e($selectedCode) ?></span>';
    document.getElementById('field_id').value = '0';
    document.getElementById('field_label').value = '';
    document.getElementById('field_name_key').value = '';
    document.getElementById('field_name_key').readOnly = false;
    document.getElementById('field_type').value = 'text';
    document.getElementById('field_placeholder').value = '';
    document.getElementById('field_help_text').value = '';
    document.getElementById('field_sort_order').value = (currentFields.length + 1);
    document.getElementById('field_options_raw').value = '';
    document.getElementById('field_min_length').value = '';
    document.getElementById('field_max_length').value = '';
    document.getElementById('field_regex').value = '';
    document.getElementById('field_is_required').checked = true;
    document.getElementById('field_is_unique').checked = false;
    document.getElementById('field_is_active').checked = true;
    onFieldTypeChange('text');
}

function editField(f) {
    document.getElementById('fieldModalTitle').innerHTML = 'Edit Field: <span class="text-primary font-monospace">' + escapeHtml(f.label) + '</span>';
    document.getElementById('field_id').value = f.id;
    document.getElementById('field_label').value = f.label;
    document.getElementById('field_name_key').value = f.field_name;
    
    // Core fields machine name cannot be altered
    const isCore = ['application_number', 'applicant_name'].includes(f.field_name);
    document.getElementById('field_name_key').readOnly = isCore;

    document.getElementById('field_type').value = f.field_type;
    document.getElementById('field_placeholder').value = f.placeholder || '';
    document.getElementById('field_help_text').value = f.help_text || '';
    document.getElementById('field_sort_order').value = f.sort_order || '1';

    let rawOpts = '';
    if (f.options && Array.isArray(f.options)) {
        rawOpts = f.options.map(o => (typeof o === 'object' ? `${o.value}|${o.label}` : o)).join('\n');
    }
    document.getElementById('field_options_raw').value = rawOpts;
    document.getElementById('field_allowed_files').value = f.allowed_file_types || 'pdf,jpg,jpeg,png';
    document.getElementById('field_max_size_mb').value = f.max_file_size_mb || '2';
    document.getElementById('field_min_length').value = f.min_length || '';
    document.getElementById('field_max_length').value = f.max_length || '';
    document.getElementById('field_regex').value = f.validation_pattern || '';
    document.getElementById('field_is_required').checked = (parseInt(f.is_required) === 1);
    document.getElementById('field_is_unique').checked = (parseInt(f.is_unique) === 1);
    document.getElementById('field_is_active').checked = (parseInt(f.is_active) === 1);

    onFieldTypeChange(f.field_type);
    new bootstrap.Modal(document.getElementById('addFieldModal')).show();
}

function autoSlugFieldName(val) {
    if (document.getElementById('field_id').value !== '0') return;
    const slug = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 60);
    document.getElementById('field_name_key').value = slug;
}

function onFieldTypeChange(type) {
    const optsBox = document.getElementById('optionsBox');
    const fileBox = document.getElementById('fileSettingsBox');
    const regexInput = document.getElementById('field_regex');
    const minLen = document.getElementById('field_min_length');
    const maxLen = document.getElementById('field_max_length');

    optsBox.classList.toggle('d-none', !['select', 'radio'].includes(type));
    fileBox.classList.toggle('d-none', type !== 'file');

    if (type === 'aadhaar') {
        minLen.value = 12;
        maxLen.value = 12;
        regexInput.value = '^[0-9]{12}$';
    } else if (type === 'mobile') {
        minLen.value = 10;
        maxLen.value = 10;
        regexInput.value = '^[6-9][0-9]{9}$';
    }
}

function buildPreviewForm() {
    const container = document.getElementById('previewFormContainer');
    container.innerHTML = '';

    if (!currentFields || currentFields.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-3">No active fields configured.</div>';
        return;
    }

    currentFields.filter(f => parseInt(f.is_active) === 1).forEach(f => {
        const col = document.createElement('div');
        col.className = 'col-md-6 mb-2';
        const reqStar = parseInt(f.is_required) === 1 ? '<span class="text-danger">*</span>' : '<span class="text-muted small">(Optional)</span>';
        const placeholder = f.placeholder || f.label;

        let inputHtml = '';
        if (f.field_type === 'select') {
            let opts = '<option value="">-- Select ' + escapeHtml(f.label) + ' --</option>';
            if (f.options && Array.isArray(f.options)) {
                f.options.forEach(o => {
                    const val = typeof o === 'object' ? o.value : o;
                    const txt = typeof o === 'object' ? o.label : o;
                    opts += `<option value="${escapeHtml(val)}">${escapeHtml(txt)}</option>`;
                });
            }
            inputHtml = `<select class="form-select form-select-sm rounded-2">${opts}</select>`;
        } else if (f.field_type === 'textarea') {
            inputHtml = `<textarea class="form-control form-control-sm rounded-2" rows="2" placeholder="${escapeHtml(placeholder)}"></textarea>`;
        } else if (f.field_type === 'file') {
            inputHtml = `<input type="file" class="form-control form-control-sm rounded-2">`;
        } else {
            const fontMono = (f.field_type === 'aadhaar' || f.field_name.includes('number')) ? 'font-monospace' : '';
            inputHtml = `<input type="${f.field_type === 'number' ? 'number' : (f.field_type === 'date' ? 'date' : 'text')}" class="form-control form-control-sm rounded-2 ${fontMono}" placeholder="${escapeHtml(placeholder)}">`;
        }

        col.innerHTML = `
            <label class="form-label small fw-bold mb-1 d-flex align-items-center justify-content-between">
                <span>${escapeHtml(f.label)} ${reqStar}</span>
                <span class="text-muted font-monospace" style="font-size: 0.65rem;">${escapeHtml(f.field_name)}</span>
            </label>
            ${inputHtml}
            ${f.help_text ? `<div class="text-muted small mt-0.5" style="font-size: 0.68rem;">${escapeHtml(f.help_text)}</div>` : ''}
        `;
        container.appendChild(col);
    });
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

function promptNewApplicationCode() {
    const code = prompt("Enter new Application Code (e.g. LABOURCARD, DRIVING_LIC, PAN):");
    if (!code) return;
    const clean = code.trim().toUpperCase().replace(/[^A-Z0-9_]/g, '');
    if (clean.length < 2) {
        alert("Invalid code. Must be at least 2 alphanumeric characters.");
        return;
    }
    window.location.href = '/admin/application-code-forms?code=' + encodeURIComponent(clean);
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
