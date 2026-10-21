<?php require dirname(__DIR__) . '/layouts/header.php'; ?>
<style>
.app-code-card {
    background-color: #ffffff;
    border: 1.5px solid #e2e8f0;
    transition: all 0.15s ease-in-out;
    user-select: none;
    cursor: pointer;
    min-height: 52px;
}
.app-code-card:hover {
    background-color: #f8fafc;
    border-color: #cbd5e1;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}
.app-code-card:focus-visible {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
}
.app-code-card.selected {
    background-color: #f0f7ff !important;
    border-color: #0d6efd !important;
    border-width: 2px !important;
    box-shadow: 0 4px 6px -1px rgba(13, 110, 253, 0.12), 0 2px 4px -2px rgba(13, 110, 253, 0.08);
}
.app-code-card.selected:hover {
    background-color: #e6f0fd !important;
    border-color: #0b5ed7 !important;
}
.card-check-icon {
    font-size: 1.25rem;
    line-height: 1;
}
.cursor-pointer {
    cursor: pointer;
}
.shadow-xs {
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
</style>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-diagram-3-fill text-primary me-2"></i>Staff Application Code Routing
        </h1>
        <p class="text-muted mb-0">Route incoming block applications to designated Block Operators strictly by Application Code.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <button type="button" class="btn btn-primary fw-bold shadow-sm" onclick="openAssignModal()">
            <i class="bi bi-plus-circle me-1"></i> Configure Staff Routing
        </button>
    </div>
</div>

<!-- Filters Card -->
<div class="wb-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/staff-routing" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search staff name or mobile..." value="<?= e($search ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="district_id" id="filter_district_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- All Districts --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ((int)($filterDistrictId ?? 0) === (int)$d['id']) ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="block_id" id="filter_block_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- All Blocks --</option>
                    <?php foreach ($blocks as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ((int)($filterBlockId ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="app_code" class="form-select" onchange="this.form.submit()">
                    <option value="">-- All App Codes --</option>
                    <?php foreach ($allActiveCodes as $code): ?>
                        <option value="<?= e($code) ?>" <?= ($filterAppCode === $code) ? 'selected' : '' ?>>
                            <?= e($code) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Staff Status --</option>
                    <option value="ACTIVE" <?= ($filterStatus === 'ACTIVE') ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="INACTIVE" <?= ($filterStatus === 'INACTIVE') ? 'selected' : '' ?>>INACTIVE</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100" title="Search"><i class="bi bi-search"></i></button>
                <a href="/admin/staff-routing" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Staff Routing Table -->
<div class="wb-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>Mobile</th>
                        <th>District</th>
                        <th>Block</th>
                        <th style="min-width: 250px;">Assigned Application Codes</th>
                        <th class="text-center">Staff Status</th>
                        <th class="text-center">Pending Queue</th>
                        <th class="text-center">In-Process</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($operators)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                No staff operators found matching your filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($operators as $op): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($op['name']) ?></div>
                                    <small class="text-muted">ID: #<?= (int)$op['id'] ?></small>
                                </td>
                                <td>
                                    <span class="font-monospace"><?= e($op['mobile']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($op['district_name'] ?: 'Not Assigned') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($op['block_name'] ?: 'Not Assigned') ?></span>
                                </td>
                                <td>
                                    <?php if (empty($op['assigned_codes'])): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-exclamation-triangle me-1"></i>No Codes Assigned
                                        </span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($op['assigned_codes'] as $c): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold px-2 py-1">
                                                    <?= e($c) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($op['status'] === 'ACTIVE'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-white px-2 py-1"><?= e($op['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="/admin/applications?district_id=<?= (int)$op['district_id'] ?>&block_id=<?= (int)$op['block_id'] ?>&status=PENDING" class="badge bg-warning-subtle text-dark border border-warning text-decoration-none px-2 py-1">
                                        <?= (int)($op['pending_count'] ?? 0) ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <a href="/admin/applications?district_id=<?= (int)$op['district_id'] ?>&block_id=<?= (int)$op['block_id'] ?>&status=IN_PROCESS" class="badge bg-info-subtle text-dark border border-info text-decoration-none px-2 py-1">
                                        <?= (int)($op['in_process_count'] ?? 0) ?>
                                    </a>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold"
                                            onclick="manageOperatorRouting(<?= htmlspecialchars(json_encode([
                                                'id' => (int)$op['id'],
                                                'name' => $op['name'],
                                                'district_id' => (int)$op['district_id'],
                                                'block_id' => (int)$op['block_id'],
                                                'assigned_codes' => $op['assigned_codes'] ?? []
                                            ]), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-pencil-square me-1"></i>Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manage / Configure Routing Modal -->
<div class="modal fade" id="routingModal" tabindex="-1" aria-labelledby="routingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="/admin/staff-routing/save" class="modal-content" id="routingForm">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="routingModalLabel">
                    <i class="bi bi-diagram-3-fill text-primary me-2"></i>Configure Staff Application Codes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlert" class="alert d-none"></div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">1. District <span class="text-danger">*</span></label>
                        <select name="district_id" id="modal_district_id" class="form-select" required onchange="onModalDistrictChange()">
                            <option value="">-- Select District --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">2. Block <span class="text-danger">*</span></label>
                        <select name="block_id" id="modal_block_id" class="form-select" required onchange="onModalBlockChange()" disabled>
                            <option value="">-- Select District First --</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">3. Active Block Operator <span class="text-danger">*</span></label>
                    <select name="operator_user_id" id="modal_operator_id" class="form-select" required onchange="onModalOperatorChange()" disabled>
                        <option value="">-- Select Block First --</option>
                    </select>
                    <small class="text-muted">Only active OPERATOR staff assigned to this District and Block are eligible.</small>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <label class="form-label fw-semibold mb-0">4. Application Codes to Handle <span class="text-danger">*</span></label>
                            <span class="badge bg-secondary-subtle text-secondary border ms-2 fw-medium" id="selectedCodesCountBadge">0 Selected</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2.5 rounded-2" onclick="selectAllCodes(true)">
                                <i class="bi bi-check-all me-1"></i>Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2.5 rounded-2" onclick="selectAllCodes(false)">
                                <i class="bi bi-dash-circle me-1"></i>Deselect All
                            </button>
                        </div>
                    </div>
                    <div id="codesContainer" class="border rounded-3 p-3 bg-light" style="min-height: 120px;">
                        <div class="text-muted text-center py-4">Please select District and Block to load rate-configured Application Codes.</div>
                    </div>

                    <!-- Selected Codes Summary Chips -->
                    <div id="selectedCodesChipsContainer" class="mt-2.5 p-2 px-3 rounded-2 bg-light border d-none">
                        <div class="small text-secondary fw-semibold d-flex align-items-center flex-wrap gap-1" id="selectedCodesChips"></div>
                    </div>

                    <small class="text-muted mt-1 d-block">Only codes with active rate configurations in this block are displayed.</small>
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm" id="btnSaveRouting" disabled>
                    <i class="bi bi-check-circle me-1" id="saveBtnIcon"></i>
                    <span id="saveBtnText">Save Routing Configuration</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentBlockData = null;
let targetOperatorCodes = [];

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openAssignModal() {
    document.getElementById('routingForm').reset();
    document.getElementById('modal_block_id').innerHTML = '<option value="">-- Select District First --</option>';
    document.getElementById('modal_block_id').disabled = true;
    document.getElementById('modal_operator_id').innerHTML = '<option value="">-- Select Block First --</option>';
    document.getElementById('modal_operator_id').disabled = true;
    document.getElementById('codesContainer').innerHTML = '<div class="text-muted text-center py-4">Please select District and Block to load active Application Codes.</div>';
    
    const countBadge = document.getElementById('selectedCodesCountBadge');
    if (countBadge) {
        countBadge.textContent = '0 Selected';
        countBadge.className = 'badge bg-secondary-subtle text-secondary border ms-2 fw-medium';
    }
    const chipsContainer = document.getElementById('selectedCodesChipsContainer');
    if (chipsContainer) chipsContainer.classList.add('d-none');

    const saveBtn = document.getElementById('btnSaveRouting');
    if (saveBtn) saveBtn.disabled = true;
    const saveIcon = document.getElementById('saveBtnIcon');
    if (saveIcon) saveIcon.className = 'bi bi-check-circle me-1';
    const saveText = document.getElementById('saveBtnText');
    if (saveText) saveText.textContent = 'Save Routing Configuration';

    hideAlert();
    targetOperatorCodes = [];

    const modal = new bootstrap.Modal(document.getElementById('routingModal'));
    modal.show();
}

async function onModalDistrictChange() {
    const distId = document.getElementById('modal_district_id').value;
    const blkSelect = document.getElementById('modal_block_id');
    const opSelect = document.getElementById('modal_operator_id');
    const container = document.getElementById('codesContainer');
    document.getElementById('btnSaveRouting').disabled = true;

    blkSelect.innerHTML = '<option value="">Loading blocks...</option>';
    blkSelect.disabled = true;
    opSelect.innerHTML = '<option value="">-- Select Block First --</option>';
    opSelect.disabled = true;
    container.innerHTML = '<div class="text-muted text-center py-4">Please select District and Block to load active Application Codes.</div>';

    updateSelectionSummary();

    if (!distId) {
        blkSelect.innerHTML = '<option value="">-- Select District First --</option>';
        return;
    }

    try {
        const res = await fetch('/api/blocks?district_id=' + encodeURIComponent(distId));
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        const blocks = Array.isArray(data) ? data : (data.data || []);
        blkSelect.innerHTML = '<option value="">-- Select Block --</option>';
        if (blocks.length === 0) {
            blkSelect.innerHTML = '<option value="">-- No Blocks Found --</option>';
        } else {
            blocks.forEach(b => {
                blkSelect.innerHTML += `<option value="${b.id}">${escapeHtml(b.name)}</option>`;
            });
        }
        blkSelect.disabled = false;
    } catch (e) {
        console.error('Failed to load blocks:', e);
        blkSelect.innerHTML = '<option value="">Failed to load blocks</option>';
        showAlert('Could not load blocks: ' + e.message, 'danger');
    }
}

async function onModalBlockChange() {
    const distId = document.getElementById('modal_district_id').value;
    const blkId = document.getElementById('modal_block_id').value;
    const opSelect = document.getElementById('modal_operator_id');
    const container = document.getElementById('codesContainer');
    document.getElementById('btnSaveRouting').disabled = true;
    hideAlert();

    if (!distId || !blkId) {
        opSelect.innerHTML = '<option value="">-- Select Block First --</option>';
        opSelect.disabled = true;
        container.innerHTML = '<div class="text-muted text-center py-4">Please select District and Block to load active Application Codes.</div>';
        updateSelectionSummary();
        return;
    }

    opSelect.innerHTML = '<option value="">Loading staff & codes...</option>';
    opSelect.disabled = true;

    try {
        const res = await fetch(`/admin/staff-routing/api-block-data?district_id=${encodeURIComponent(distId)}&block_id=${encodeURIComponent(blkId)}`);
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success) {
            showAlert(data.error || 'Failed to fetch block data', 'danger');
            return;
        }

        currentBlockData = data;

        // Populate Operators
        opSelect.innerHTML = '<option value="">-- Select Active Operator --</option>';
        if (data.operators && data.operators.length > 0) {
            data.operators.forEach(op => {
                opSelect.innerHTML += `<option value="${op.id}">${escapeHtml(op.name)} (${escapeHtml(op.mobile)})</option>`;
            });
            opSelect.disabled = false;
        } else {
            opSelect.innerHTML = '<option value="">No Active Operators in this Block</option>';
            showAlert('No active block operators found for this block. Please add/assign an operator first.', 'warning');
        }

        // Render Codes
        const availableCodes = (data.rate_codes && data.rate_codes.length > 0) ? data.rate_codes : (data.active_codes || []);
        renderCodeCheckboxes(availableCodes, targetOperatorCodes);

        if (data.service_status !== 'LIVE') {
            showAlert('Notice: Service availability is currently ' + (data.service_status || 'DISABLED') + ' for this block.', 'warning');
        }
    } catch (e) {
        console.error('Error loading block data:', e);
        opSelect.innerHTML = '<option value="">Error loading data</option>';
        showAlert('Network error fetching block routing data: ' + e.message, 'danger');
    }
}

function onModalOperatorChange() {
    const opId = parseInt(document.getElementById('modal_operator_id').value);
    if (!opId || !currentBlockData) {
        updateSelectionSummary();
        return;
    }

    const op = currentBlockData.operators.find(o => parseInt(o.id) === opId);
    let assigned = [];
    if (op && op.assigned_codes) {
        assigned = op.assigned_codes;
    }
    const availableCodes = (currentBlockData.rate_codes && currentBlockData.rate_codes.length > 0) ? currentBlockData.rate_codes : (currentBlockData.active_codes || []);
    renderCodeCheckboxes(availableCodes, assigned);
    updateSelectionSummary();
}

function renderCodeCheckboxes(activeCodes, checkedCodes = []) {
    const container = document.getElementById('codesContainer');
    if (!activeCodes || activeCodes.length === 0) {
        container.innerHTML = '<div class="alert alert-danger mb-0 py-2"><i class="bi bi-exclamation-triangle me-1"></i> No active block rates configured for this block. Codes cannot be routed without active rates.</div>';
        updateSelectionSummary();
        return;
    }

    // Deduplicate codes and extract optional fee
    const codeMap = new Map();
    activeCodes.forEach(item => {
        if (typeof item === 'object' && item !== null) {
            const c = (item.code || '').trim().toUpperCase();
            if (c) {
                if (!codeMap.has(c)) {
                    codeMap.set(c, item.fee_amount ? parseFloat(item.fee_amount) : null);
                }
            }
        } else if (typeof item === 'string') {
            const c = item.trim().toUpperCase();
            if (c && !codeMap.has(c)) {
                codeMap.set(c, null);
            }
        }
    });

    if (codeMap.size === 0) {
        container.innerHTML = '<div class="alert alert-danger mb-0 py-2"><i class="bi bi-exclamation-triangle me-1"></i> No active application codes found.</div>';
        updateSelectionSummary();
        return;
    }

    let html = '<div class="row g-2.5">';
    codeMap.forEach((fee, code) => {
        const isChecked = checkedCodes.includes(code);
        const feeFormatted = (fee !== null && !isNaN(fee)) ? ('₹' + fee.toFixed(0)) : '';
        const feeBadge = feeFormatted ? `<span class="badge ${isChecked ? 'bg-primary text-white border border-primary' : 'bg-light text-primary border'} px-2 py-1 app-code-rate">${feeFormatted}</span>` : '';

        html += `
            <div class="col-lg-4 col-md-4 col-sm-6 col-12">
                <div class="app-code-card p-2.5 rounded-3 border d-flex align-items-center justify-content-between h-100 ${isChecked ? 'selected' : ''}"
                     id="card_${code}"
                     onclick="toggleCodeCard('${code}', event)"
                     onkeydown="handleCodeCardKeydown('${code}', event)"
                     tabindex="0"
                     role="checkbox"
                     aria-checked="${isChecked ? 'true' : 'false'}"
                     data-code="${code}">
                    
                    <input type="checkbox" name="application_codes[]" value="${code}" id="cb_${code}" class="d-none code-checkbox" ${isChecked ? 'checked' : ''} onchange="syncCardFromCheckbox('${code}')">

                    <div class="d-flex align-items-center gap-2 overflow-hidden me-2">
                        <span class="card-check-icon fs-5 d-flex align-items-center flex-shrink-0 ${isChecked ? 'text-primary' : 'text-muted opacity-50'}">
                            <i class="bi ${isChecked ? 'bi-check-circle-fill' : 'bi-circle'}"></i>
                        </span>
                        <div class="d-flex align-items-center gap-1.5 text-truncate">
                            <span class="app-code-title fw-bold ${isChecked ? 'text-primary' : 'text-dark'}">${escapeHtml(code)}</span>
                            <span class="badge bg-primary text-white py-0.5 px-1.5 rounded-pill card-selected-pill ${isChecked ? '' : 'd-none'}" style="font-size: 0.65rem; letter-spacing: 0.3px;">Selected</span>
                        </div>
                    </div>

                    <div class="text-end flex-shrink-0">
                        ${feeBadge}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;

    updateSelectionSummary();
}

function toggleCodeCard(code, event) {
    if (event && event.target && event.target.tagName === 'INPUT') {
        return;
    }
    const cb = document.getElementById('cb_' + code);
    if (!cb) return;

    cb.checked = !cb.checked;
    updateCardVisualState(code, cb.checked);
    updateSelectionSummary();
}

function handleCodeCardKeydown(code, event) {
    if (event.key === ' ' || event.key === 'Enter') {
        event.preventDefault();
        toggleCodeCard(code, event);
    }
}

function syncCardFromCheckbox(code) {
    const cb = document.getElementById('cb_' + code);
    if (!cb) return;
    updateCardVisualState(code, cb.checked);
    updateSelectionSummary();
}

function updateCardVisualState(code, isChecked) {
    const card = document.getElementById('card_' + code);
    if (!card) return;

    card.setAttribute('aria-checked', isChecked ? 'true' : 'false');
    const iconSpan = card.querySelector('.card-check-icon');
    const titleSpan = card.querySelector('.app-code-title');
    const feeBadge = card.querySelector('.app-code-rate');
    const selectedPill = card.querySelector('.card-selected-pill');

    if (isChecked) {
        card.classList.add('selected');
        if (iconSpan) {
            iconSpan.className = 'card-check-icon fs-5 d-flex align-items-center flex-shrink-0 text-primary';
            iconSpan.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
        }
        if (titleSpan) {
            titleSpan.className = 'app-code-title fw-bold text-primary';
        }
        if (selectedPill) {
            selectedPill.classList.remove('d-none');
        }
        if (feeBadge) {
            feeBadge.className = 'badge bg-primary text-white border border-primary px-2 py-1 app-code-rate';
        }
    } else {
        card.classList.remove('selected');
        if (iconSpan) {
            iconSpan.className = 'card-check-icon fs-5 d-flex align-items-center flex-shrink-0 text-muted opacity-50';
            iconSpan.innerHTML = '<i class="bi bi-circle"></i>';
        }
        if (titleSpan) {
            titleSpan.className = 'app-code-title fw-bold text-dark';
        }
        if (selectedPill) {
            selectedPill.classList.add('d-none');
        }
        if (feeBadge) {
            feeBadge.className = 'badge bg-light text-primary border px-2 py-1 app-code-rate';
        }
    }
}

function selectAllCodes(select) {
    document.querySelectorAll('.code-checkbox').forEach(cb => {
        cb.checked = select;
        updateCardVisualState(cb.value, select);
    });
    updateSelectionSummary();
}

function updateSelectionSummary() {
    const checkedBoxes = Array.from(document.querySelectorAll('.code-checkbox:checked'));
    const count = checkedBoxes.length;
    const badge = document.getElementById('selectedCodesCountBadge');
    const chipsContainer = document.getElementById('selectedCodesChipsContainer');
    const chipsList = document.getElementById('selectedCodesChips');
    const saveBtn = document.getElementById('btnSaveRouting');
    const saveBtnText = document.getElementById('saveBtnText');
    const opSelect = document.getElementById('modal_operator_id');
    const hasOpSelected = opSelect && opSelect.value && parseInt(opSelect.value) > 0;

    // 1. Live Badge Count
    if (badge) {
        badge.textContent = `${count} Selected`;
        if (count > 0) {
            badge.className = 'badge bg-primary text-white border border-primary ms-2 fw-semibold';
        } else {
            badge.className = 'badge bg-secondary-subtle text-secondary border ms-2 fw-medium';
        }
    }

    // 2. Below Grid Selected Chips: e.g. "Selected: BICCO · BCCCO · LABOURCARD"
    if (chipsContainer && chipsList) {
        if (count > 0) {
            const selectedCodes = checkedBoxes.map(cb => cb.value);
            const chipsHtml = selectedCodes.map(c => `<span class="badge bg-white text-primary border border-primary-subtle px-2 py-0.5 fw-bold shadow-xs">${escapeHtml(c)}</span>`).join(' <span class="text-muted opacity-50">·</span> ');
            chipsList.innerHTML = `<span class="fw-bold text-dark me-1"><i class="bi bi-check2-all text-primary me-1"></i>Selected:</span> ${chipsHtml}`;
            chipsContainer.classList.remove('d-none');
        } else {
            chipsContainer.classList.add('d-none');
            chipsList.innerHTML = '';
        }
    }

    // 3. Save Button Enable/Disable and Count Text
    if (saveBtn) {
        if (count > 0 && hasOpSelected) {
            saveBtn.disabled = false;
        } else {
            saveBtn.disabled = true;
        }
    }
    if (saveBtnText) {
        if (count > 0) {
            saveBtnText.textContent = `Save Routing (${count} ${count === 1 ? 'Code' : 'Codes'})`;
        } else {
            saveBtnText.textContent = 'Save Routing Configuration';
        }
    }
}

function showAlert(msg, type = 'info') {
    const el = document.getElementById('modalAlert');
    el.className = `alert alert-${type} py-2 mb-3`;
    el.innerHTML = msg;
    el.classList.remove('d-none');
}

function hideAlert() {
    const el = document.getElementById('modalAlert');
    el.classList.add('d-none');
}

async function manageOperatorRouting(op) {
    openAssignModal();
    targetOperatorCodes = op.assigned_codes || [];

    document.getElementById('modal_district_id').value = op.district_id;
    await onModalDistrictChange();

    document.getElementById('modal_block_id').value = op.block_id;
    await onModalBlockChange();

    document.getElementById('modal_operator_id').value = op.id;
    onModalOperatorChange();
}

// Validation & Double-Submission Prevention
document.addEventListener('DOMContentLoaded', function() {
    const routingForm = document.getElementById('routingForm');
    if (routingForm) {
        routingForm.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.code-checkbox:checked');
            if (selected.length === 0) {
                e.preventDefault();
                showAlert('Select at least one Application Code.', 'danger');
                return false;
            }

            const opId = document.getElementById('modal_operator_id').value;
            if (!opId || parseInt(opId) <= 0) {
                e.preventDefault();
                showAlert('Please select an active Block Operator.', 'danger');
                return false;
            }

            // Prevent double submission and show loading spinner
            const btn = document.getElementById('btnSaveRouting');
            if (btn) {
                btn.disabled = true;
                const icon = document.getElementById('saveBtnIcon');
                const text = document.getElementById('saveBtnText');
                if (icon) icon.className = 'spinner-border spinner-border-sm me-1';
                if (text) text.textContent = 'Saving Configuration...';
            }
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
