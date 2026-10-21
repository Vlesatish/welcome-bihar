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

