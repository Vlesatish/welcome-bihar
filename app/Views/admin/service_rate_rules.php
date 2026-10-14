<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
    <div>
        <h1 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-currency-rupee text-primary"></i>
            <span>Block Rates</span>
        </h1>
        <div class="small text-muted" style="font-size: 0.74rem;">
            Custom rates per District, Block, and Application Code prefix
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold shadow-2xs" data-bs-toggle="modal" data-bs-target="#customAppCodeModal" onclick="openCustomAppCodeModal()">
            <i class="bi bi-tag-fill text-primary me-1"></i> Add App Code
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold shadow-2xs" data-bs-toggle="modal" data-bs-target="#locationModal" onclick="openLocationModal()">
            <i class="bi bi-geo-alt me-1"></i> Add Block
        </button>
        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold shadow-2xs" data-bs-toggle="modal" data-bs-target="#rateRuleModal" onclick="resetRateRuleModal()">
            <i class="bi bi-plus-lg me-1"></i> Add Rate
        </button>
    </div>
</div>

<!-- Compact Default Rates Section -->
<div class="card border border-warning-subtle shadow-sm mb-3 rounded-3 bg-light">
    <div class="card-body p-3">
        <form method="POST" action="/admin/block-rates/save-fallbacks" class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <?= csrf_field() ?>
            <div>
                <strong class="text-dark small"><i class="bi bi-gear-fill text-warning me-1"></i>Default Rates (When specific Block Rate is not set):</strong>
                <div class="text-muted small mt-0.5" style="font-size: 0.75rem;">
                    Standard default applied if no custom block rate matches the applicant location.
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="width: 175px;">
                    <span class="input-group-text bg-white small fw-bold">BICCO: ₹</span>
                    <input type="number" step="0.01" name="fallback_rate_standard" class="form-control fw-bold text-center" value="<?= number_format($fallbackStandard, 2, '.', '') ?>" required title="Default for BICCO, BCCCO, BRCCO">
                </div>
                <div class="input-group input-group-sm" style="width: 175px;">
                    <span class="input-group-text bg-white small fw-bold">BOBC: ₹</span>
                    <input type="number" step="0.01" name="fallback_rate_other" class="form-control fw-bold text-center" value="<?= number_format($fallbackOther, 2, '.', '') ?>" required title="Default for BOBC, NCLCO, OTHER">
                </div>
                <button type="submit" class="btn btn-sm btn-warning fw-bold px-3">
                    Save Defaults
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Search Bar & Filters Card -->
<div class="card border-0 shadow-sm mb-3 bg-white rounded-3 border">
    <div class="card-body p-3">
        <form method="GET" action="/admin/block-rates" class="row g-2 align-items-end">
            <input type="hidden" name="id" value="<?= $dataType['id'] ?>">

            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">District</label>
                <select name="filter_district" id="filter_district" class="form-select form-select-sm" onchange="onFilterDistrictChange(this.value)">
                    <option value="">All Districts</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filterDistrict === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Block</label>
                <select name="filter_block" id="filter_block" class="form-select form-select-sm">
                    <option value="">All Blocks</option>
                    <?php foreach ($preloadedBlocks as $pb): ?>
                        <option value="<?= $pb['id'] ?>" <?= $filterBlock === (int)$pb['id'] ? 'selected' : '' ?>>
                            <?= e($pb['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">App Code</label>
                <select name="filter_code" class="form-select form-select-sm font-monospace">
                    <option value="">All Codes</option>
                    <?php foreach ($appCodes as $cVal => $cLabel): ?>
                        <option value="<?= e($cVal) ?>" <?= $filterCode === $cVal ? 'selected' : '' ?>>
                            <?= e($cVal) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="filter_status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="disabled" <?= $filterStatus === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>

            <div class="col-md-2 col-sm-12 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="/admin/block-rates" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Block Rates Table: District | Block | App Code | Rate | Active | Edit -->
<div class="card shadow-sm border rounded-3 bg-white mb-4" style="border: 1px solid #e2e8f0;">
    <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="h6 fw-bold mb-0 text-dark">Configured Rates</h5>
            <span class="badge bg-light text-secondary border font-monospace" id="rulesCountBadge"><?= count($rules) ?> Rates</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rules)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-tags fs-1 opacity-50 text-primary"></i>
                <h5 class="fw-bold mt-2 h6">No Rates Found</h5>
                <p class="small text-muted mb-3">No matching block rates configured yet.</p>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#rateRuleModal" onclick="resetRateRuleModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add Rate
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="blockRatesTable">
                    <thead class="table-light text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">
                        <tr>
                            <th class="ps-3 py-2.5">District</th>
                            <th class="py-2.5">Block</th>
                            <th class="py-2.5">App Code</th>
                            <th class="py-2.5 text-center">Rate</th>
                            <th class="py-2.5 text-center">Active</th>
                            <th class="py-2.5 text-end pe-3">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $r): ?>
                            <tr class="rate-table-row" data-search="<?= strtolower(e(($r['state'] ?? 'Bihar') . ' ' . ($r['district_name'] ?? '') . ' ' . ($r['block_name'] ?? '') . ' ' . ($r['application_code'] ?? 'ALL') . ' ' . ($r['rule_label'] ?? ''))) ?>">
                                <td class="ps-3 py-2.5 fw-semibold text-dark">
                                    <?= !empty($r['district_name']) ? e($r['district_name']) : 'All Districts' ?>
                                </td>
                                <td class="py-2.5">
                                    <?php if (!empty($r['block_name'])): ?>
                                        <span class="fw-medium text-dark"><?= e($r['block_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">All Blocks</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5">
                                    <span class="badge bg-light text-dark border font-monospace fw-bold">
                                        <?= !empty($r['application_code']) ? e($r['application_code']) : 'ALL' ?>
                                    </span>
                                </td>
                                <td class="py-2.5 text-center font-monospace fw-bold text-success fs-6">
                                    ₹<?= number_format($r['fee_amount'], 2) ?>
                                </td>
                                <td class="py-2.5 text-center">
                                    <form method="POST" action="/admin/data-types/rate-rules/toggle" class="m-0 d-inline-flex align-items-center gap-1.5">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
                                        <span class="badge <?= $r['is_active'] ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>" style="font-size: 0.68rem; font-weight: 700;">
                                            <?= $r['is_active'] ? 'Active' : 'Disabled' ?>
                                        </span>
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" <?= $r['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()" style="cursor: pointer;" title="Toggle Active">
                                        </div>
                                    </form>
                                </td>
                                <td class="py-2.5 text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5"
                                                onclick='editRateRule(<?= json_encode($r) ?>)' title="Edit Rule">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </button>
                                        <form method="POST" action="/admin/data-types/rate-rules/delete" onsubmit="return confirm('Delete this block rate?');" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm px-1.5 py-0.5" title="Delete Rule">
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
            <div id="noSearchMatches" class="text-center py-4 text-muted d-none">
                <i class="bi bi-search fs-3 opacity-50"></i>
                <p class="mt-2 mb-0 small">No block rates matched your search filter.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================== -->
<!-- ============================================================== -->
<!-- MODAL 1: ADD STATE / DISTRICT / BLOCK (SEPARATE WORKFLOW)      -->
<!-- ============================================================== -->
<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
        <form method="POST" action="/admin/data-types/rate-rules/add-location" id="addLocationForm" class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <?= csrf_field() ?>
            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
            <input type="hidden" name="is_ajax" value="1">

            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-geo-alt-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="locationModalTitle">Add New Block</h5>
                        <div class="small text-muted" style="font-size: 0.78rem;">Create a new block and link it to a district</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div id="locationAlert" class="alert alert-danger d-none py-2 px-3 small rounded-3 mb-3"></div>

                <div class="alert alert-info py-2 px-3 small border-0 bg-info-subtle text-info-emphasis rounded-3 mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Newly created blocks are persistently saved into the database, immediately available for this service's rate rules, and automatically shown in the Applicant registration form.
                </div>

                <div class="row g-3">
                    <!-- State Selection -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">State <span class="text-danger">*</span></label>
                        <select name="state" id="loc_state" class="form-select" onchange="onLocationStateChange(this.value)" required>
                            <?php foreach ($states as $st): ?>
                                <option value="<?= e($st) ?>" <?= $st === 'Bihar' ? 'selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                            <option value="__NEW__">+ Add New State...</option>
                        </select>
                        <input type="text" name="custom_state" id="loc_custom_state" class="form-control mt-2 d-none" placeholder="Enter New State Name">
                    </div>

                    <!-- District Selection -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">District <span class="text-danger">*</span></label>
                        <select name="district_id" id="loc_district_id" class="form-select" onchange="onLocationDistrictChange(this.value)" required>
                            <option value="">-- Select Existing District --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" data-state="<?= e($d['state'] ?? 'Bihar') ?>" <?= $preselectedDistrict === (int)$d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="0">+ Add New District in this State...</option>
                        </select>
                    </div>

                    <!-- New District Fields (Visible only if + Add New District is selected) -->
                    <div id="newDistrictFields" class="col-12 p-3 bg-light border rounded-3 d-none">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-plus-circle me-1"></i>New District Details</h6>
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">New District Name <span class="text-danger">*</span></label>
                                <input type="text" name="new_district_name" id="loc_new_district_name" class="form-control form-control-sm" placeholder="e.g. Vaishali North">
                                <input type="hidden" name="new_district_name_hi" id="loc_new_district_name_hi" value="">
                            </div>
                        </div>
                    </div>

                    <!-- Block Details -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Block / ULB Name <span class="text-danger">*</span></label>
                        <input type="text" name="block_name" id="loc_block_name" class="form-control" placeholder="e.g. Danapur Cantt" required>
                        <input type="hidden" name="block_name_hi" id="loc_block_name_hi" value="">
                        <div class="form-text small">Duplicate block names within the same district are strictly prevented.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Block Code (Optional)</label>
                        <input type="text" name="block_code" id="loc_block_code" class="form-control text-uppercase font-monospace" placeholder="e.g. DANAPUR_CANTT (Auto-generated if blank)">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="loc_is_active" value="1" checked>
                            <label class="form-check-label fw-semibold" for="loc_is_active">Active (Immediately Available)</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-top py-2.5 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light border px-3 fw-semibold rounded-2" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary fw-bold rounded-2 px-3" onclick="submitLocationForm(false)">
                    <i class="bi bi-save me-1"></i> Save Block
                </button>
                <button type="button" class="btn btn-primary fw-bold px-4 rounded-2 shadow-sm" onclick="submitLocationForm(true)">
                    <i class="bi bi-arrow-right-circle me-1"></i> Save & Add Rate
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL 3: ADD CUSTOM APPLICATION CODE (SYSTEM-WIDE)             -->
<!-- ============================================================== -->
<div class="modal fade" id="customAppCodeModal" tabindex="-1" aria-labelledby="customAppCodeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
        <form method="POST" action="/admin/data-types/rate-rules/add-app-code" id="addAppCodeForm" class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <?= csrf_field() ?>
            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
            <input type="hidden" name="is_ajax" value="1">

            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-tag-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="customAppCodeModalTitle">Add Custom Application Code</h5>
                        <div class="small text-muted" style="font-size: 0.78rem;">Register a new certificate/service prefix code system-wide</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div id="appCodeAlert" class="alert alert-danger d-none py-2 px-3 small rounded-3 mb-3"></div>

                <div class="alert alert-info py-2 px-3 small border-0 bg-primary-subtle text-primary rounded-3 mb-3" style="font-size: 0.78rem;">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Newly added application codes become instantly available for Block Rates, Citizen Forms, and Application tracking filters.
                </div>

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            App Code Prefix <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="code" id="modal_app_code" class="form-control font-monospace text-uppercase fw-bold rounded-3" placeholder="e.g. DLCO" required maxlength="32" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '')">
                        <div class="form-text text-muted" style="font-size: 0.68rem;">A-Z, 0-9 & _ only</div>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            Service Title (English) <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="label_en" id="modal_app_label_en" class="form-control rounded-3" placeholder="e.g. Driving License Service" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            Service Title (Hindi)
                        </label>
                        <input type="text" name="label_hi" id="modal_app_label_hi" class="form-control rounded-3" placeholder="e.g. ड्राइविंग लाइसेंस सेवा (वैकल्पिक)">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            Default Fallback Fee (₹)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-dark border-end-0">₹</span>
                            <input type="number" step="0.01" name="default_fee" id="modal_app_default_fee" class="form-control fw-bold border-start-0" placeholder="50.00">
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.68rem;">Base fee when block rate is not customized</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            Distributor Comm. (₹)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-dark border-end-0">₹</span>
                            <input type="number" step="0.01" name="distributor_commission" id="modal_app_comm" class="form-control fw-bold border-start-0" placeholder="0.00">
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.68rem;">Optional commission per application</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top py-2.5 px-4 bg-light d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light border px-3 fw-semibold rounded-2" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary fw-bold rounded-2 px-3" onclick="submitAppCodeForm(false)">
                    <i class="bi bi-save me-1"></i> Save Code
                </button>
                <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm rounded-2" onclick="submitAppCodeForm(true)">
                    <i class="bi bi-arrow-right-circle me-1"></i> Save & Configure Rate
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL 2: ADD / EDIT RATE RULE (CLEAN & FRESH DESIGN)           -->
<!-- ============================================================== -->
<div class="modal fade" id="rateRuleModal" tabindex="-1" aria-labelledby="rateRuleModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
        <form method="POST" action="/admin/data-types/rate-rules/save" id="rateRuleForm" class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="rule_id" value="0">
            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
            <input type="hidden" name="rule_label" id="rule_label" value="">
            <input type="hidden" name="state" id="rule_state" value="Bihar">
            <input type="hidden" name="adjustment_type" id="rule_adj_type" value="FIXED">
            <input type="hidden" name="rule_mode" id="rule_mode_input" value="single">

            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-tag-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="rateRuleModalTitle">Add Block Rate</h5>
                        <div class="small text-muted" id="rateRuleModalSubtitle" style="font-size: 0.78rem;">Configure application fees for District and Block</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 pt-3.5">
                <!-- Location Row: District & Block -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            District <span class="text-danger">*</span>
                        </label>
                        <select name="district_id" id="rule_district_id" class="form-select rounded-3 py-2" onchange="onRuleDistrictChange(this.value)" required>
                            <option value="">-- Select District --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" data-state="<?= e($d['state'] ?? 'Bihar') ?>" <?= $preselectedDistrict === (int)$d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-0" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                Block <span class="text-danger">*</span>
                            </label>
                            <a href="#" class="small text-primary text-decoration-none fw-semibold" onclick="event.preventDefault(); openLocationModalFromRate();" style="font-size: 0.75rem;">
                                <i class="bi bi-plus-circle me-1"></i>New Block
                            </a>
                        </div>
                        <select name="block_id" id="rule_block_id" class="form-select rounded-3 py-2">
                            <option value="">-- Apply to All Blocks in District --</option>
                            <?php foreach ($preloadedBlocks as $pb): ?>
                                <option value="<?= $pb['id'] ?>" <?= $preselectedBlock === (int)$pb['id'] ? 'selected' : '' ?>>
                                    <?= e($pb['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Clean Mode Selector Pills -->
                <div class="bg-light p-1 rounded-3 d-flex mb-3 border">
                    <button type="button" class="btn btn-sm flex-fill fw-bold rounded-2 py-1.5 rule-mode-btn bg-white text-primary shadow-2xs" id="btn_mode_single" onclick="switchRuleMode('single')">
                        <i class="bi bi-tag me-1"></i> Single Rate
                    </button>
                    <button type="button" class="btn btn-sm flex-fill fw-bold rounded-2 py-1.5 rule-mode-btn text-muted" id="btn_mode_multi" onclick="switchRuleMode('multi')">
                        <i class="bi bi-grid-3x3-gap me-1"></i> Multiple Codes (Batch)
                    </button>
                </div>

                <!-- SINGLE RATE SECTION -->
                <div id="singleModeSection">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-secondary text-uppercase mb-0" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                    Application Code
                                </label>
                                <a href="#" class="small text-primary text-decoration-none fw-semibold" id="btnToggleCustomAppCode" onclick="event.preventDefault(); toggleCustomAppCodeMode();" style="font-size: 0.75rem;">
                                    <i class="bi bi-plus-circle me-1"></i>+ Custom App Code
                                </a>
                            </div>
                            <div id="appCodeSelectWrap">
                                <select name="application_code" id="rule_app_code" class="form-select font-monospace rounded-3 py-2" onchange="onRuleAppCodeChange(this.value)">
                                    <option value="">-- All Codes (Default Rate for this Block) --</option>
                                    <option value="__CUSTOM__" class="text-primary fw-bold py-1">+ Add Custom App Code...</option>
                                    <option disabled>────────────────────────────</option>
                                    <?php foreach ($appCodes as $codeVal => $codeLabel): ?>
                                        <option value="<?= e($codeVal) ?>"><?= e($codeVal) ?> &mdash; <?= e($codeLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Inline Custom App Code Input Form -->
                            <div id="customAppCodeBox" class="p-3 rounded-3 border border-primary-subtle bg-primary-subtle bg-opacity-10 mt-2 d-none">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom border-primary-subtle border-opacity-50">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">NEW</span>
                                        <span class="small fw-bold text-primary" style="font-size: 0.78rem;">Custom Application Code</span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-muted small" onclick="toggleCustomAppCodeMode(false)" style="font-size: 0.75rem;">
                                        <i class="bi bi-arrow-left me-1"></i>Select from List
                                    </button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            Code Prefix <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="custom_application_code" id="custom_application_code" 
                                               class="form-control form-control-sm font-monospace text-uppercase fw-bold rounded-2" 
                                               placeholder="e.g. DLCO" maxlength="32"
                                               oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '')">
                                        <div class="form-text text-muted" style="font-size: 0.67rem;">A-Z, 0-9 & _ only</div>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            Service Name / Title
                                        </label>
                                        <input type="text" name="custom_code_label" id="custom_code_label" 
                                               class="form-control form-control-sm rounded-2" 
                                               placeholder="e.g. Driving License Service">
                                        <div class="form-text text-muted" style="font-size: 0.67rem;">Friendly service title</div>
                                    </div>
                                </div>
                                <div class="form-check mt-2 pt-1 border-top border-light">
                                    <input class="form-check-input" type="checkbox" name="save_to_service_options" id="save_to_service_options" value="1" checked>
                                    <label class="form-check-label text-muted small" for="save_to_service_options" style="font-size: 0.73rem;">
                                        Save globally for future rate rules & applicant dropdowns
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                Rate Amount <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light fw-bold text-dark border-end-0">₹</span>
                                <input type="number" step="0.01" name="fee_amount" id="rule_fee" class="form-control form-control-lg fw-bold border-start-0" placeholder="50.00">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                Distributor Commission
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light fw-bold text-dark border-end-0">₹</span>
                                <input type="number" step="0.01" name="distributor_commission" id="rule_comm" class="form-control form-control-lg fw-bold border-start-0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MULTI CODE BATCH SECTION -->
                <div id="multiModeSection" class="d-none">
                    <div class="d-flex align-items-center justify-content-between p-2.5 bg-light rounded-3 border mb-2.5">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small fw-bold text-dark">Common Rate:</span>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <span class="input-group-text bg-white fw-bold">₹</span>
                                <input type="number" step="0.01" id="batch_common_fee" class="form-control fw-bold" placeholder="50.00">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="applyBatchFeeToAll()">Apply</button>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-light border px-2 py-0.5 small fw-semibold" onclick="toggleAllCodes(true)">All</button>
                            <button type="button" class="btn btn-sm btn-light border px-2 py-0.5 small fw-semibold" onclick="toggleAllCodes(false)">None</button>
                            <button type="button" class="btn btn-sm btn-outline-primary border px-2 py-0.5 small fw-semibold" onclick="promptAddBatchCustomCode()">
                                <i class="bi bi-plus-circle me-1"></i>+ Custom Code
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive border rounded-3 bg-white" style="max-height: 230px; overflow-y: auto;">
                        <table class="table table-hover table-sm align-middle mb-0" id="batchCodesTable">
                            <thead class="table-light sticky-top" style="font-size: 0.72rem; text-transform: uppercase;">
                                <tr>
                                    <th style="width: 36px;" class="text-center">#</th>
                                    <th style="width: 90px;">Code</th>
                                    <th>Service Name</th>
                                    <th style="width: 110px;">Rate (₹)</th>
                                    <th style="width: 100px;">Comm (₹)</th>
                                </tr>
                            </thead>
                            <tbody id="batchCodesTbody">
                                <?php foreach ($appCodes as $cCode => $cDesc): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input class="form-check-input multi-code-check" type="checkbox" name="multi_app_codes[]" value="<?= e($cCode) ?>" id="chk_<?= e($cCode) ?>" onchange="onCodeCheckChange('<?= e($cCode) ?>')">
                                        </td>
                                        <td>
                                            <label for="chk_<?= e($cCode) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1 mb-0" style="cursor: pointer;">
                                                <?= e($cCode) ?>
                                            </label>
                                        </td>
                                        <td>
                                            <label for="chk_<?= e($cCode) ?>" class="small text-dark mb-0 fw-semibold" style="cursor: pointer;">
                                                <?= e($cDesc) ?>
                                            </label>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="fee_amount_<?= e($cCode) ?>" id="fee_<?= e($cCode) ?>" class="form-control form-control-sm fw-bold text-dark fee-input" placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="comm_<?= e($cCode) ?>" id="comm_<?= e($cCode) ?>" class="form-control form-control-sm text-dark comm-input" placeholder="0.00">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-check mt-2 small">
                        <input class="form-check-input" type="checkbox" name="allow_overwrite" id="allow_overwrite" value="1" checked>
                        <label class="form-check-label text-muted" for="allow_overwrite" style="font-size: 0.78rem;">
                            Update rate if already configured for this block & code
                        </label>
                    </div>
                </div>

                <!-- Active Toggle -->
                <div class="mt-3 pt-2.5 border-top d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark small">Active Status</div>
                        <div class="text-muted" style="font-size: 0.75rem;">Immediately effective for applications in this block</div>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="rule_is_active" value="1" checked style="width: 2.25rem; height: 1.25rem; cursor: pointer;">
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top py-2.5 px-4 bg-light d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light border px-3 fw-semibold rounded-2" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-2" id="btn_save_rule">
                    <i class="bi bi-check2-circle me-1"></i> Save Rate Rule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Cache of blocks by district ID
const blocksCache = {};

function onFilterDistrictChange(districtId) {
    const blockSelect = document.getElementById('filter_block');
    if (!blockSelect) return;

    blockSelect.innerHTML = '<option value="">-- All Blocks --</option>';
    if (!districtId) return;

    loadBlocksForDistrict(districtId, (blocks) => {
        blocks.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.name;
            blockSelect.appendChild(opt);
        });
    });
}

function onRuleDistrictChange(districtId, callback) {
    const blockSelect = document.getElementById('rule_block_id');
    if (!blockSelect) return;

    blockSelect.innerHTML = '<option value="">-- Apply to All Blocks in District --</option>';
    if (!districtId) {
        if (callback) callback();
        return;
    }

    loadBlocksForDistrict(districtId, (blocks) => {
        blocks.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.name;
            blockSelect.appendChild(opt);
        });
        if (callback) callback();
    });
}

function loadBlocksForDistrict(districtId, callback) {
    if (blocksCache[districtId]) {
        callback(blocksCache[districtId]);
        return;
    }

    fetch('/api/blocks?district_id=' + districtId)
        .then(r => r.json())
        .then(blocks => {
            blocksCache[districtId] = blocks;
            callback(blocks);
        })
        .catch(err => console.error('Error fetching blocks:', err));
}

function onLocationStateChange(val) {
    const customState = document.getElementById('loc_custom_state');
    if (val === '__NEW__') {
        customState.classList.remove('d-none');
        customState.required = true;
    } else {
        customState.classList.add('d-none');
        customState.required = false;
        // Filter districts dropdown by state
        filterDistrictDropdown('loc_district_id', val);
    }
}

function onLocationDistrictChange(val) {
    const newDistFields = document.getElementById('newDistrictFields');
    if (val === '0') {
        newDistFields.classList.remove('d-none');
        document.getElementById('loc_new_district_name').required = true;
    } else {
        newDistFields.classList.add('d-none');
        document.getElementById('loc_new_district_name').required = false;
    }
}

function filterDistrictDropdown(selectId, state) {
    const select = document.getElementById(selectId);
    if (!select) return;

    for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
        if (opt.value === '' || opt.value === '0') continue;
        const optState = opt.getAttribute('data-state') || 'Bihar';
        if (!state || optState.toLowerCase() === state.toLowerCase()) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    }
}

function openLocationModal() {
    document.getElementById('locationAlert').classList.add('d-none');
    document.getElementById('loc_block_name').value = '';
    document.getElementById('loc_block_name_hi').value = '';
    document.getElementById('loc_block_code').value = '';
    document.getElementById('loc_is_active').checked = true;
}

function submitLocationForm(openRuleAfter) {
    const form = document.getElementById('addLocationForm');
    const alertBox = document.getElementById('locationAlert');
    alertBox.classList.add('d-none');

    const stateSelect = document.getElementById('loc_state');
    const customState = document.getElementById('loc_custom_state');
    const stateVal = (stateSelect.value === '__NEW__') ? customState.value.trim() : stateSelect.value;
    const districtSelect = document.getElementById('loc_district_id');
    const districtId = districtSelect.value;
    const newDistName = document.getElementById('loc_new_district_name')?.value.trim() || '';
    const blockName = document.getElementById('loc_block_name').value.trim();

    if (!blockName) {
        alertBox.textContent = 'Please enter a Block Name.';
        alertBox.classList.remove('d-none');
        return;
    }

    if (!districtId && districtId !== '0') {
        alertBox.textContent = 'Please select a District.';
        alertBox.classList.remove('d-none');
        return;
    }

    if (districtId === '0' && !newDistName) {
        alertBox.textContent = 'Please enter a name for the new district.';
        alertBox.classList.remove('d-none');
        return;
    }

    const formData = new FormData(form);
    if (stateSelect.value === '__NEW__') {
        formData.set('state', stateVal);
    }

    fetch('/admin/data-types/rate-rules/add-location', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alertBox.textContent = res.message || 'Error creating block.';
            alertBox.classList.remove('d-none');
            return;
        }

        // Successfully created block
        const newBlock = res.block;

        // Clear cache for this district
        delete blocksCache[newBlock.district_id];

        // Close location modal
        const locModal = bootstrap.Modal.getInstance(document.getElementById('locationModal'));
        if (locModal) locModal.hide();

        if (openRuleAfter) {
            // Open Rate Rule modal with newly created block pre-selected!
            resetRateRuleModal();
            document.getElementById('rule_state').value = newBlock.state || 'Bihar';
            document.getElementById('rule_district_id').value = newBlock.district_id;
            document.getElementById('rule_label').value = newBlock.name + ' RTPS Service Rate';

            onRuleDistrictChange(newBlock.district_id, () => {
                const blkSelect = document.getElementById('rule_block_id');
                blkSelect.value = newBlock.id;
            });

            const ruleModal = new bootstrap.Modal(document.getElementById('rateRuleModal'));
            ruleModal.show();
        } else {
            // Flash notification and update dropdown
            alert("Success: " + res.message);
            // Refresh page with selected block parameter so filter reflects it
            window.location.href = '/admin/data-types/rate-rules?id=<?= $dataType['id'] ?>&selected_district=' + newBlock.district_id + '&selected_block=' + newBlock.id;
        }
    })
    .catch(err => {
        alertBox.textContent = 'Server request failed: ' + err.message;
        alertBox.classList.remove('d-none');
    });
}

function openLocationModalFromRate() {
    const rateModalEl = document.getElementById('rateRuleModal');
    const rateModal = bootstrap.Modal.getInstance(rateModalEl);
    if (rateModal) {
        rateModal.hide();
    }
    openLocationModal();
    // Pre-select current district in location modal if selected
    const curDist = document.getElementById('rule_district_id')?.value;
    if (curDist) {
        const locDist = document.getElementById('loc_district_id');
        if (locDist) locDist.value = curDist;
    }
    const locModal = new bootstrap.Modal(document.getElementById('locationModal'));
    locModal.show();
}

function switchRuleMode(mode) {
    const singleSec = document.getElementById('singleModeSection');
    const multiSec = document.getElementById('multiModeSection');
    const ruleFee = document.getElementById('rule_fee');
    const modeInput = document.getElementById('rule_mode_input');
    const btnSingle = document.getElementById('btn_mode_single');
    const btnMulti = document.getElementById('btn_mode_multi');

    if (mode === 'multi') {
        if (modeInput) modeInput.value = 'multi';
        singleSec.classList.add('d-none');
        multiSec.classList.remove('d-none');
        if (ruleFee) ruleFee.required = false;

        if (btnMulti) {
            btnMulti.classList.add('bg-white', 'text-primary', 'shadow-2xs');
            btnMulti.classList.remove('text-muted');
        }
        if (btnSingle) {
            btnSingle.classList.remove('bg-white', 'text-primary', 'shadow-2xs');
            btnSingle.classList.add('text-muted');
        }
    } else {
        if (modeInput) modeInput.value = 'single';
        singleSec.classList.remove('d-none');
        multiSec.classList.add('d-none');
        if (ruleFee) ruleFee.required = true;

        if (btnSingle) {
            btnSingle.classList.add('bg-white', 'text-primary', 'shadow-2xs');
            btnSingle.classList.remove('text-muted');
        }
        if (btnMulti) {
            btnMulti.classList.remove('bg-white', 'text-primary', 'shadow-2xs');
            btnMulti.classList.add('text-muted');
        }
    }
}

function toggleAllCodes(checked) {
    document.querySelectorAll('.multi-code-check').forEach(cb => {
        cb.checked = checked;
    });
}

function applyBatchFeeToAll() {
    const feeVal = document.getElementById('batch_common_fee').value;
    if (!feeVal) return;

    document.querySelectorAll('.multi-code-check').forEach(cb => {
        if (cb.checked) {
            const feeInput = document.getElementById('fee_' + cb.value);
            if (feeInput) feeInput.value = feeVal;
        }
    });
}

function onCodeCheckChange(code) {
    const cb = document.getElementById('chk_' + code);
    const feeInput = document.getElementById('fee_' + code);
    if (cb && cb.checked && feeInput && !feeInput.value) {
        const commonFee = document.getElementById('batch_common_fee').value || document.getElementById('rule_fee').value;
        if (commonFee) feeInput.value = commonFee;
    }
}

function toggleCustomAppCodeMode(forceState) {
    const box = document.getElementById('customAppCodeBox');
    const selectWrap = document.getElementById('appCodeSelectWrap');
    const sel = document.getElementById('rule_app_code');
    const customCodeInput = document.getElementById('custom_application_code');
    const btnToggle = document.getElementById('btnToggleCustomAppCode');
    if (!box || !selectWrap) return;

    const willShowCustom = forceState !== undefined ? forceState : box.classList.contains('d-none');

    if (willShowCustom) {
        box.classList.remove('d-none');
        selectWrap.classList.add('d-none');
        if (sel) sel.value = '__CUSTOM__';
        if (btnToggle) {
            btnToggle.innerHTML = '<i class="bi bi-arrow-left me-1"></i>Choose Existing Code';
            btnToggle.classList.remove('text-primary');
            btnToggle.classList.add('text-secondary');
        }
        if (customCodeInput) {
            customCodeInput.required = true;
            setTimeout(() => customCodeInput.focus(), 100);
        }
    } else {
        box.classList.add('d-none');
        selectWrap.classList.remove('d-none');
        if (sel && sel.value === '__CUSTOM__') {
            sel.value = '';
        }
        if (btnToggle) {
            btnToggle.innerHTML = '<i class="bi bi-plus-circle me-1"></i>+ Custom App Code';
            btnToggle.classList.remove('text-secondary');
            btnToggle.classList.add('text-primary');
        }
        if (customCodeInput) {
            customCodeInput.required = false;
        }
    }
}

function onRuleAppCodeChange(val) {
    if (val === '__CUSTOM__') {
        toggleCustomAppCodeMode(true);
    } else {
        toggleCustomAppCodeMode(false);
    }
}

function promptAddBatchCustomCode() {
    const rawCode = prompt('Enter New Custom Application Code (e.g. DLCO, BIRTH_CERT):');
    if (!rawCode) return;

    const code = rawCode.trim().toUpperCase().replace(/[^A-Z0-9_]/g, '');
    if (code.length < 2) {
        alert('Invalid code prefix. Minimum 2 alphanumeric characters required.');
        return;
    }

    // Check if already in batch table
    const existingCheck = document.getElementById('chk_' + code);
    if (existingCheck) {
        existingCheck.checked = true;
        existingCheck.scrollIntoView({ behavior: 'smooth', block: 'center' });
        existingCheck.closest('tr').classList.add('table-primary');
        setTimeout(() => existingCheck.closest('tr').classList.remove('table-primary'), 1500);
        return;
    }

    const title = prompt(`Enter Service Title for "${code}" (e.g. Driving License Service):`, `${code} Service`) || `${code} Service`;
    const commonFee = document.getElementById('batch_common_fee')?.value || '';

    const tbody = document.getElementById('batchCodesTbody');
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.className = 'table-info';
    tr.innerHTML = `
        <td class="text-center">
            <input class="form-check-input multi-code-check" type="checkbox" name="multi_app_codes[]" value="${code}" id="chk_${code}" checked onchange="onCodeCheckChange('${code}')">
        </td>
        <td>
            <label for="chk_${code}" class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-1 mb-0" style="cursor: pointer;">
                ${code}
            </label>
            <input type="hidden" name="custom_code_label_${code}" value="${title.replace(/"/g, '&quot;')}">
        </td>
        <td>
            <label for="chk_${code}" class="small text-dark mb-0 fw-semibold" style="cursor: pointer;">
                ${title} <span class="badge bg-light text-primary border" style="font-size: 0.65rem;">Custom</span>
            </label>
        </td>
        <td>
            <input type="number" step="0.01" name="fee_amount_${code}" id="fee_${code}" class="form-control form-control-sm fw-bold text-dark fee-input" value="${commonFee}" placeholder="0.00">
        </td>
        <td>
            <input type="number" step="0.01" name="comm_${code}" id="comm_${code}" class="form-control form-control-sm text-dark comm-input" placeholder="0.00">
        </td>
    `;

    tbody.prepend(tr);
    tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => tr.classList.remove('table-info'), 2000);
}

function openCustomAppCodeModal() {
    const alertBox = document.getElementById('appCodeAlert');
    if (alertBox) {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    }
    document.getElementById('modal_app_code').value = '';
    document.getElementById('modal_app_label_en').value = '';
    document.getElementById('modal_app_label_hi').value = '';
    document.getElementById('modal_app_default_fee').value = '';
    document.getElementById('modal_app_comm').value = '';
}

function submitAppCodeForm(openRateModalAfter = false) {
    const form = document.getElementById('addAppCodeForm');
    const alertBox = document.getElementById('appCodeAlert');
    const codeInput = document.getElementById('modal_app_code');
    const labelInput = document.getElementById('modal_app_label_en');

    if (!form || !codeInput || !labelInput) return;

    if (!codeInput.value.trim() || codeInput.value.trim().length < 2) {
        alertBox.textContent = 'Please enter a valid code prefix (minimum 2 characters).';
        alertBox.classList.remove('d-none');
        codeInput.focus();
        return;
    }

    if (!labelInput.value.trim()) {
        alertBox.textContent = 'Please enter a service title.';
        alertBox.classList.remove('d-none');
        labelInput.focus();
        return;
    }

    const submitBtn = document.getElementById('btn_save_app_code');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    }

    const formData = new FormData(form);

    fetch('/admin/data-types/rate-rules/add-app-code', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(res => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }

        if (res.success && res.code) {
            // 1. Add to Single Mode dropdown
            const sel = document.getElementById('rule_app_code');
            if (sel) {
                let exists = false;
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === res.code.value) {
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = res.code.value;
                    opt.textContent = res.code.label;
                    sel.appendChild(opt);
                }
                sel.value = res.code.value;
            }

            // Close Custom App Code Modal
            const codeModalEl = document.getElementById('customAppCodeModal');
            const codeModal = bootstrap.Modal.getInstance(codeModalEl);
            if (codeModal) codeModal.hide();

            if (openRateModalAfter) {
                resetRateRuleModal();
                if (sel) sel.value = res.code.value;
                if (res.code.default_fee > 0) {
                    document.getElementById('rule_fee').value = parseFloat(res.code.default_fee).toFixed(2);
                }
                const rateModal = new bootstrap.Modal(document.getElementById('rateRuleModal'));
                rateModal.show();
            } else {
                window.location.reload();
            }
        } else {
            alertBox.textContent = res.message || 'Error adding application code.';
            alertBox.classList.remove('d-none');
        }
    })
    .catch(err => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
        alertBox.textContent = 'Server communication error: ' + err.message;
        alertBox.classList.remove('d-none');
    });
}

function resetRateRuleModal() {
    document.getElementById('rule_id').value = '0';
    document.getElementById('rule_label').value = '';
    document.getElementById('rule_state').value = 'Bihar';
    document.getElementById('rule_district_id').value = '<?= $preselectedDistrict ?: '' ?>';
    document.getElementById('rule_app_code').value = '';
    document.getElementById('rule_adj_type').value = 'FIXED';
    document.getElementById('rule_fee').value = '';
    document.getElementById('rule_comm').value = '';
    document.getElementById('rule_is_active').checked = true;

    // Reset Custom App Code Section
    toggleCustomAppCodeMode(false);
    const customCodeInput = document.getElementById('custom_application_code');
    const customLabelInput = document.getElementById('custom_code_label');
    if (customCodeInput) customCodeInput.value = '';
    if (customLabelInput) customLabelInput.value = '';

    const btnMulti = document.getElementById('btn_mode_multi');
    if (btnMulti) {
        btnMulti.style.display = '';
        btnMulti.disabled = false;
    }
    switchRuleMode('single');

    document.getElementById('rateRuleModalTitle').textContent = 'Add Block Rate';
    document.getElementById('rateRuleModalSubtitle').textContent = 'Configure application fees for District and Block';

    if (<?= $preselectedDistrict ? 'true' : 'false' ?>) {
        onRuleDistrictChange(<?= (int)$preselectedDistrict ?>, () => {
            document.getElementById('rule_block_id').value = '<?= $preselectedBlock ?: '' ?>';
        });
    } else {
        document.getElementById('rule_block_id').innerHTML = '<option value="">-- Apply to All Blocks in District --</option>';
    }
}

function editRateRule(r) {
    resetRateRuleModal();
    document.getElementById('rule_id').value = r.id;
    document.getElementById('rule_label').value = r.rule_label || '';
    document.getElementById('rule_state').value = r.state || 'Bihar';
    document.getElementById('rule_district_id').value = r.district_id || '';
    document.getElementById('rule_adj_type').value = r.adjustment_type || 'FIXED';
    document.getElementById('rule_fee').value = r.fee_amount;
    document.getElementById('rule_comm').value = r.distributor_commission || '';
    document.getElementById('rule_is_active').checked = (r.is_active == 1);

    const appCode = r.application_code || '';
    const sel = document.getElementById('rule_app_code');
    let found = false;
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === appCode) {
            found = true;
            break;
        }
    }

    if (found || !appCode) {
        toggleCustomAppCodeMode(false);
        sel.value = appCode;
    } else {
        // Custom Code not found in dropdown -> open custom mode and prefill
        toggleCustomAppCodeMode(true);
        document.getElementById('custom_application_code').value = appCode;
        document.getElementById('custom_code_label').value = r.rule_label || '';
    }

    const btnMulti = document.getElementById('btn_mode_multi');
    if (btnMulti) btnMulti.style.display = 'none';

    switchRuleMode('single');

    if (r.district_id) {
        onRuleDistrictChange(r.district_id, () => {
            document.getElementById('rule_block_id').value = r.block_id || '';
        });
    }

    document.getElementById('rateRuleModalTitle').textContent = 'Edit Block Rate';
    const locName = (r.district_name || 'All Districts') + (r.block_name ? ' / ' + r.block_name : '');
    document.getElementById('rateRuleModalSubtitle').textContent = 'Updating rate for ' + locName;

    const modal = new bootstrap.Modal(document.getElementById('rateRuleModal'));
    modal.show();
}

function filterRateTableLive() {
    const query = (document.getElementById('tableSearchInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.rate-table-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (!query || text.includes(query)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const noMatches = document.getElementById('noSearchMatches');
    if (noMatches) {
        noMatches.classList.toggle('d-none', visibleCount > 0 || rows.length === 0);
    }
    const countBadge = document.getElementById('rulesCountBadge');
    if (countBadge) {
        countBadge.textContent = visibleCount + ' Rates';
    }
}

// On page load check if modal hash or open_modal param exists
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (window.location.hash === '#rateRuleModal' || window.location.hash === '#add-rule' || urlParams.get('open_modal') === 'rateRuleModal') {
        const modal = new bootstrap.Modal(document.getElementById('rateRuleModal'));
        modal.show();
    } else if (window.location.hash === '#customAppCodeModal' || urlParams.get('open_modal') === 'customAppCodeModal') {
        const modal = new bootstrap.Modal(document.getElementById('customAppCodeModal'));
        modal.show();
    }

    const rateForm = document.getElementById('rateRuleForm');
    if (rateForm) {
        rateForm.addEventListener('submit', function(e) {
            const mode = document.getElementById('rule_mode_input')?.value || 'single';
            if (mode === 'multi') {
                const checked = document.querySelectorAll('.multi-code-check:checked');
                if (checked.length === 0) {
                    alert('Please select at least one Application Code for batch pricing.');
                    e.preventDefault();
                    return false;
                }
            } else {
                const isCustomMode = !document.getElementById('customAppCodeBox')?.classList.contains('d-none');
                const customCodeVal = (document.getElementById('custom_application_code')?.value || '').trim();
                const codeSel = document.getElementById('rule_app_code');

                if ((isCustomMode || codeSel?.value === '__CUSTOM__') && customCodeVal.length < 2) {
                    alert('Please enter a valid Custom Application Code prefix (minimum 2 characters).');
                    document.getElementById('custom_application_code').focus();
                    e.preventDefault();
                    return false;
                }

                const feeInput = document.getElementById('rule_fee');
                if (!feeInput.value || parseFloat(feeInput.value) < 0) {
                    alert('Please enter a valid rate amount.');
                    feeInput.focus();
                    e.preventDefault();
                    return false;
                }
            }

            // Auto-generate clean rule label if empty
            const labelInput = document.getElementById('rule_label');
            if (!labelInput.value || document.getElementById('rule_id').value === '0') {
                const distSel = document.getElementById('rule_district_id');
                const blkSel = document.getElementById('rule_block_id');
                const codeSel = document.getElementById('rule_app_code');
                const distText = distSel?.options[distSel.selectedIndex]?.text?.trim() || 'All Districts';
                const blkText = blkSel?.options[blkSel.selectedIndex]?.text?.trim() || 'All Blocks';

                const isCustom = !document.getElementById('customAppCodeBox')?.classList.contains('d-none');
                const customCode = (document.getElementById('custom_application_code')?.value || '').trim().toUpperCase();
                const customTitle = (document.getElementById('custom_code_label')?.value || '').trim();
                const codeText = (isCustom && customCode) ? customCode : (codeSel?.value || 'ALL');

                if (customTitle) {
                    labelInput.value = `${distText} - ${blkText} (${customTitle})`;
                } else {
                    labelInput.value = `${distText} - ${blkText} (${codeText}) Rate`;
                }
            }
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
