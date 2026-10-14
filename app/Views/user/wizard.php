<?php require dirname(__DIR__) . '/layouts/header.php'; ?>
<?php
    $finalFee = (float)($pricingInfo['final_fee'] ?? ($activeDataType['fee_amount'] ?? 50.00));
    $currentUserId = (int)$currentUser['id'];
?>

<style>
/* Clean Full-Width Editable Table Styles (Labour Card 2.0 Row-Entry Flow) */
.table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.entry-table th {
    font-size: 0.74rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    background-color: #f8fafc;
    border-bottom: 1.5px solid #cbd5e1;
    white-space: nowrap;
    padding: 0.6rem 0.5rem;
}
.entry-table td {
    font-size: 0.82rem;
    vertical-align: top;
    padding: 0.5rem 0.45rem;
    border-color: #f1f5f9;
}
.entry-table tbody tr:hover {
    background-color: #f8fafc;
}
.row-serial-badge {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background-color: #0f172a;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
}
.bottom-bar {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    border-bottom-left-radius: 0.65rem;
    border-bottom-right-radius: 0.65rem;
    padding: 0.75rem 1rem;
}
.metric-badge {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 0.35rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.8rem;
}
</style>

<!-- TOAST CONTAINER -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

<div class="container-fluid px-2 px-md-4 py-2 mb-5" style="max-width: 1400px;">

    <!-- TOP BAR: CLEAN SERVICE HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
        <div class="d-flex align-items-center gap-2">
            <a href="/applications" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-2xs">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
            <div class="ms-1">
                <h1 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span><?= e($activeDataType['name_en'] ?? 'New Application Entry') ?></span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 rounded text-uppercase" style="font-size: 0.68rem;">
                        <?= e($activeDataType['badge'] ?? 'FAST TRACK') ?>
                    </span>
                </h1>
                <span class="text-muted small" style="font-size: 0.72rem;">
                    Batch Entry &bull; Processing Time: <?= e($activeDataType['processing_time'] ?? '10-30 MIN') ?>
                </span>
            </div>
        </div>

        <a href="/applications" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold shadow-2xs">
            <i class="bi bi-folder2-open me-1"></i> Application List
        </a>
    </div>

    <?php if (empty($districts)): ?>
        <div class="alert alert-warning py-3 px-3.5 mb-3 rounded-3 shadow-2xs d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill text-warning fs-5"></i>
            <div>
                <strong>No Rate-Configured Locations Available:</strong>
                Applications are currently unavailable because no block-specific rates have been configured by administration. Please check back later.
            </div>
        </div>
    <?php endif; ?>

    <!-- FULL-WIDTH EDITABLE APPLICATIONS TABLE CARD -->

    <div class="card shadow-sm border rounded-3 bg-white mb-3" style="border: 1px solid #e2e8f0;">
        <!-- Card Header -->
        <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-table text-primary"></i>
                <h2 class="h6 fw-bold mb-0 text-dark" style="font-size: 0.85rem;">Application Entries</h2>
                <span class="badge bg-light text-secondary border font-monospace" id="headerCountBadge">1 Row</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0.5 px-2.5 rounded-2 shadow-2xs" onclick="resetAllRows()" style="font-size: 0.75rem;">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Table
            </button>
        </div>

        <!-- Horizontal Scrollable Table Wrapper -->
        <div class="table-responsive" style="min-height: 220px;">
            <table class="table table-sm table-bordered align-middle mb-0 entry-table" style="min-width: 950px;">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th style="width: 175px;">District *</th>
                        <th style="width: 175px;">Block *</th>
                        <th style="width: 165px;">Application Code *</th>
                        <th style="width: 220px;">Application Number *</th>
                        <th style="min-width: 210px;">Applicant Name *</th>
                        <th style="width: 95px;" class="text-end">Rate</th>
                        <th style="width: 50px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="entriesTableBody">
                    <!-- Dynamic Rows populated via JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Bottom Action Bar (Left: + Add Row | Right: Total Fee & Submit Button) -->
        <div class="card-footer bg-white border-top p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <!-- Left: + Add Row Button -->
            <button type="button" id="btnAddRow" class="btn btn-primary btn-sm px-3.5 py-2 fw-bold rounded-2 shadow-xs d-flex align-items-center gap-1.5" onclick="addNewRow()">
                <i class="bi bi-plus-lg"></i>
                <span id="btnAddRowText">+ Add Row</span>
            </button>

            <!-- Right: Total Fee and Submit All Button -->
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <div class="d-flex align-items-center gap-1 px-3 py-1.5 bg-light border rounded-2 shadow-2xs">
                    <span class="text-muted small">Total Fee:</span>
                    <strong class="text-primary font-monospace" id="metricTotalFee">₹0.00</strong>
                </div>

                <!-- Submit All Button (Green) -->
                <button type="button" id="btnSubmitAll" class="btn btn-success btn-sm px-4 py-2 fw-bold rounded-2 shadow-sm d-flex align-items-center gap-2" onclick="handleSubmitAll()">
                    <i class="bi bi-send-check" id="submitIcon"></i>
                    <span id="submitBtnText">Submit All (1 Row — ₹0.00)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Shortfall Alert -->
    <div id="walletShortfallAlert" class="alert alert-danger py-2 px-3 rounded-2 shadow-2xs d-flex align-items-center justify-content-between small d-none mb-3">
        <div>
            <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
            <span>Insufficient Wallet Balance! Shortfall: <strong id="shortfallAmount" class="font-monospace">₹0.00</strong></span>
        </div>
        <a href="/wallet/topup" class="btn btn-xs btn-danger py-0.5 px-3 fw-bold rounded-pill">Recharge Wallet</a>
    </div>

</div>

<!-- SUCCESS CONFIRMATION MODAL -->
<div class="modal fade" id="modalBatchSuccess" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content rounded-4 border-0 shadow-lg text-center p-4">
            <div class="my-3">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            </div>
            <h4 class="h5 fw-bold text-dark mb-4">Your Application Submitted</h4>
            <div class="d-flex justify-content-center">
                <a href="/applications" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold">
                    <i class="bi bi-folder2-open me-1"></i> View Applications
                </a>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTBOX ZOOM MODAL -->
<div class="modal fade" id="modalImageZoom" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0 text-center">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3" data-bs-dismiss="modal"></button>
            <img id="modalZoomImg" src="" alt="Zoomed Preview" class="img-fluid rounded-4 shadow-lg" style="max-height: 85vh;">
        </div>
    </div>
</div>

<script>
// Master Districts data passed from server
window.DISTRICTS = <?= json_encode(array_map(function($d) {
    return [
        'id' => (int)$d['id'],
        'name' => $d['name'],
        'name_hi' => $d['name_hi'] ?? '',
        'state' => $d['state'] ?? 'Bihar'
    ];
}, $districts)) ?>;

// Copied Data if redirected from Application List
window.COPIED_DATA = <?= !empty($copiedData) ? json_encode($copiedData) : 'null' ?>;

document.addEventListener('DOMContentLoaded', function () {
    let currentWallet = <?= (float)$walletBalance ?>;
    const blockCache = {}; // Cache blocks by district_id
    const codeCache = {};  // Cache application codes & rates by district_id + '_' + block_id
    const tableBody = document.getElementById('entriesTableBody');
    const headerCountBadge = document.getElementById('headerCountBadge');
    const metricCount = document.getElementById('metricCount');
    const metricTotalFee = document.getElementById('metricTotalFee');
    const metricWallet = document.getElementById('metricWallet');
    const btnSubmitAll = document.getElementById('btnSubmitAll');
    const submitBtnText = document.getElementById('submitBtnText');
    const submitIcon = document.getElementById('submitIcon');
    const btnAddRowText = document.getElementById('btnAddRowText');
    const walletShortfallAlert = document.getElementById('walletShortfallAlert');
    const shortfallAmount = document.getElementById('shortfallAmount');

    // Code vs Prefix validation rules (Strict parity with ApplicationService.php)
    const prefixRules = {
        'BICCO': { expected: ['BICCO', 'BICC', 'BIIC', 'INC', 'AAY'], conflicting: ['BCCCO', 'BCCC', 'BRCCO', 'BRCC', 'BOBC', 'NCLCO'] },
        'BCCCO': { expected: ['BCCCO', 'BCCC', 'JAAT', 'CST'], conflicting: ['BICCO', 'BICC', 'BIIC', 'BRCCO', 'BRCC', 'BOBC', 'NCLCO'] },
        'BRCCO': { expected: ['BRCCO', 'BRCC', 'BIRC', 'NIWAS', 'RES'], conflicting: ['BCCCO', 'BCCC', 'BICCO', 'BICC', 'BIIC', 'BOBC', 'NCLCO'] },
        'BOBC':  { expected: ['BOBC', 'BCOBC', 'OBC'], conflicting: ['BICCO', 'BCCCO', 'BRCCO'] },
        'NCLCO': { expected: ['NCLCO', 'NCLC', 'BINC'], conflicting: ['BICCO', 'BCCCO', 'BRCCO'] },
        'RTPS':  { expected: ['RTPS', 'BR', 'BICCO', 'BCCCO', 'BRCCO'], conflicting: [] }
    };

    let rowCounter = 0;

    // Helper: Create District Options HTML
    function buildDistrictOptionsHtml(selectedId = null) {
        if (!window.DISTRICTS || window.DISTRICTS.length === 0) {
            return '<option value="">No rate-configured districts</option>';
        }
        let html = '<option value="">-- Select District --</option>';
        window.DISTRICTS.forEach(d => {
            const isSel = (selectedId && String(d.id) === String(selectedId)) ? 'selected' : '';
            html += `<option value="${d.id}" data-name="${escapeHtml(d.name)}" ${isSel}>${escapeHtml(d.name)}</option>`;
        });
        return html;
    }

    // Set row rate value and display
    function setRowRate(tr, rate, label) {
        tr.dataset.rate = String(rate || 0);
        const rateSpan = tr.querySelector('.row-rate');
        if (rateSpan) {
            rateSpan.textContent = label;
            if (rate > 0) {
                rateSpan.className = 'row-rate fw-bold text-success font-monospace';
            } else {
                rateSpan.className = 'row-rate font-monospace text-muted';
            }
        }
    }

    // 1. ADD NEW ROW
    window.addNewRow = function (initialData = null, shouldFocus = true) {
        rowCounter++;
        const rowId = 'row-' + rowCounter;

        let defDistrict = '';
        let defBlock = '';
        let defCode = '';
        let defNumber = '';
        let defName = '';

        if (initialData) {
            defDistrict = initialData.district_id || '';
            defBlock = initialData.block_id || '';
            defCode = initialData.application_code || '';
            defNumber = initialData.application_number || '';
            defName = initialData.applicant_name || '';
        } else {
            // Carry forward district and block from last row if available
            const existingRows = tableBody.querySelectorAll('tr');
            if (existingRows.length > 0) {
                const lastRow = existingRows[existingRows.length - 1];
                defDistrict = lastRow.querySelector('.row-district')?.value || '';
                defBlock = lastRow.querySelector('.row-block')?.value || '';
            }
        }

        const tr = document.createElement('tr');
        tr.id = rowId;
        tr.className = 'entry-row';
        tr.dataset.rate = '0';
        tr.dataset.rateNote = '';
        tr.dataset.isCalculating = '0';

        tr.innerHTML = `
            <!-- S.No -->
            <td class="text-center align-middle">
                <span class="row-serial-badge">1</span>
            </td>

            <!-- District -->
            <td>
                <select class="form-select form-select-sm fw-semibold row-district rounded-2" required>
                    ${buildDistrictOptionsHtml(defDistrict)}
                </select>
                <span class="row-err-district text-danger small d-none" style="font-size: 0.7rem;">Select District</span>
            </td>

            <!-- Block -->
            <td>
                <select class="form-select form-select-sm fw-semibold row-block rounded-2" required disabled>
                    <option value="">-- Select District First --</option>
                </select>
                <span class="row-err-block text-danger small d-none" style="font-size: 0.7rem;">Select Block</span>
            </td>

            <!-- Application Code -->
            <td>
                <select class="form-select form-select-sm fw-semibold row-code rounded-2" required disabled>
                    <option value="">-- Select Block First --</option>
                </select>
                <span class="row-err-code text-danger small d-none" style="font-size: 0.7rem;">Select Service Code</span>
            </td>

            <!-- Application Number -->
            <td>
                <input type="text" class="form-control form-control-sm font-monospace fw-bold text-uppercase row-number rounded-2" placeholder="Application Number" value="${escapeHtml(defNumber)}">
                <span class="row-err-number text-danger small d-none" style="font-size: 0.7rem;"></span>
            </td>

            <!-- Applicant Name -->
            <td>
                <input type="text" class="form-control form-control-sm fw-semibold row-name rounded-2" placeholder="Applicant Full Name" value="${escapeHtml(defName)}">
                <span class="row-err-name text-danger small d-none" style="font-size: 0.7rem;">Name is required</span>
            </td>

            <!-- Server Configured Rate -->
            <td class="text-end align-middle">
                <span class="row-rate font-monospace text-muted" style="font-size: 0.85rem;">—</span>
                <div class="row-rate-spinner spinner-border spinner-border-sm text-primary d-none" role="status"></div>
            </td>

            <!-- Delete Action -->
            <td class="text-center align-middle">
                <button type="button" class="btn btn-xs btn-outline-danger p-1 rounded-2 btn-delete-row" title="Delete Row" onclick="deleteRow('${rowId}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        tableBody.appendChild(tr);

        // Bind events for this row
        bindRowEvents(tr, defDistrict, defBlock, defCode);

        // Re-index all serial numbers & update totals
        reindexRows();

        // Focus new application number input
        if (shouldFocus) {
            setTimeout(() => {
                const numInput = tr.querySelector('.row-number');
                if (numInput) numInput.focus();
            }, 50);
        }
    };

    // 2. BIND EVENTS FOR A ROW
    function bindRowEvents(tr, initialDistrictId = '', initialBlockId = '', initialCode = '') {
        const districtSelect = tr.querySelector('.row-district');
        const blockSelect = tr.querySelector('.row-block');
        const codeSelect = tr.querySelector('.row-code');
        const numberInput = tr.querySelector('.row-number');
        const nameInput = tr.querySelector('.row-name');

        // District change -> fetch & populate live blocks having rates, reset block & code
        districtSelect.addEventListener('change', function () {
            clearRowError(tr, 'district');
            clearRowError(tr, 'block');
            clearRowError(tr, 'code');
            populateBlocksForRow(tr, this.value, null, null);
        });

        // Block change -> fetch & populate configured application codes and exact rates
        blockSelect.addEventListener('change', function () {
            clearRowError(tr, 'block');
            clearRowError(tr, 'code');
            populateCodesForRow(tr, districtSelect.value, this.value, null);
        });

        // Code change -> display exact configured rate, update placeholder, validate prefix
        codeSelect.addEventListener('change', function () {
            clearRowError(tr, 'code');
            const selectedOpt = this.selectedOptions[0];
            const rate = (selectedOpt && selectedOpt.dataset.rate) ? parseFloat(selectedOpt.dataset.rate) : 0;
            if (this.value && rate > 0) {
                setRowRate(tr, rate, '₹' + rate.toFixed(2));
                numberInput.placeholder = `e.g. ${this.value}/2026/...`;
            } else {
                setRowRate(tr, 0, '—');
                numberInput.placeholder = 'Application Number';
            }
            validateRowPrefix(tr);
            updateTotals();
        });

        // Number input -> uppercase, validate prefix, debounce updateTotals
        numberInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
            clearRowError(tr, 'number');
            validateRowPrefix(tr);
            debounce(() => updateTotals(), 200)();
        });

        // Name input -> clear error on input
        nameInput.addEventListener('input', function () {
            clearRowError(tr, 'name');
            debounce(() => updateTotals(), 200)();
        });

        // Initial populate for this row
        if (initialDistrictId) {
            populateBlocksForRow(tr, initialDistrictId, initialBlockId, () => {
                if (initialBlockId) {
                    populateCodesForRow(tr, initialDistrictId, initialBlockId, initialCode);
                }
            });
        }
    }

    // 3. POPULATE BLOCKS FOR ROW WITH CACHING
    function populateBlocksForRow(tr, districtId, preselectBlockId = null, callback = null) {
        const blockSelect = tr.querySelector('.row-block');
        const codeSelect = tr.querySelector('.row-code');

        if (!districtId) {
            blockSelect.innerHTML = '<option value="">-- Select District First --</option>';
            blockSelect.disabled = true;
            codeSelect.innerHTML = '<option value="">-- Select Block First --</option>';
            codeSelect.disabled = true;
            setRowRate(tr, 0, '—');
            updateTotals();
            if (callback) callback();
            return;
        }

        if (blockCache[districtId]) {
            renderBlockOptions(tr, blockSelect, blockCache[districtId], preselectBlockId, callback);
            return;
        }

        blockSelect.innerHTML = '<option value="">Loading blocks...</option>';
        blockSelect.disabled = true;
        codeSelect.innerHTML = '<option value="">-- Select Block First --</option>';
        codeSelect.disabled = true;
        setRowRate(tr, 0, '—');

        fetch('/api/service-blocks?district_id=' + encodeURIComponent(districtId) + '&service=srv_block_app')
            .then(res => {
                if (!res.ok) throw new Error('HTTP error ' + res.status);
                return res.json();
            })
            .then(blocks => {
                blockCache[districtId] = Array.isArray(blocks) ? blocks : [];
                renderBlockOptions(tr, blockSelect, blockCache[districtId], preselectBlockId, callback);
            })
            .catch(err => {
                console.error('Failed to load live blocks for district ' + districtId, err);
                blockSelect.innerHTML = '<option value="">Service is not available in this district.</option>';
                blockSelect.disabled = true;
                codeSelect.innerHTML = '<option value="">-- Select Block First --</option>';
                codeSelect.disabled = true;
                setRowRate(tr, 0, '—');
                updateTotals();
            });
    }

    function renderBlockOptions(tr, selectEl, blocks, preselectBlockId = null, callback = null) {
        const codeSelect = tr.querySelector('.row-code');
        if (!blocks || blocks.length === 0) {
            selectEl.innerHTML = '<option value="">No rate-configured blocks</option>';
            selectEl.disabled = true;
            codeSelect.innerHTML = '<option value="">-- Select Block First --</option>';
            codeSelect.disabled = true;
            setRowRate(tr, 0, '—');
            updateTotals();
            return;
        }

        let html = '<option value="">-- Select Block --</option>';
        blocks.forEach(b => {
            const isSel = (preselectBlockId && String(b.id) === String(preselectBlockId)) ? 'selected' : '';
            html += `<option value="${b.id}" data-name="${escapeHtml(b.name)}" ${isSel}>${escapeHtml(b.name)}</option>`;
        });
        selectEl.innerHTML = html;
        selectEl.disabled = false;

        if (preselectBlockId && blocks.some(b => String(b.id) === String(preselectBlockId))) {
            // Preselected block matched
            if (callback) callback();
        } else {
            codeSelect.innerHTML = '<option value="">-- Select Block First --</option>';
            codeSelect.disabled = true;
            setRowRate(tr, 0, '—');
            updateTotals();
        }
    }

    // 4. POPULATE CODES AND RATES FOR ROW WITH CACHING
    function populateCodesForRow(tr, districtId, blockId, preselectCode = null) {
        const codeSelect = tr.querySelector('.row-code');

        if (!districtId || !blockId) {
            codeSelect.innerHTML = '<option value="">-- Select Block First --</option>';
            codeSelect.disabled = true;
            setRowRate(tr, 0, '—');
            updateTotals();
            return;
        }

        const cacheKey = districtId + '_' + blockId;
        if (codeCache[cacheKey]) {
            renderCodeOptions(tr, codeSelect, codeCache[cacheKey], preselectCode);
            return;
        }

        codeSelect.innerHTML = '<option value="">Loading services...</option>';
        codeSelect.disabled = true;
        setRowRate(tr, 0, '—');

        fetch('/api/service-application-codes?district_id=' + encodeURIComponent(districtId) + '&block_id=' + encodeURIComponent(blockId))
            .then(res => {
                if (!res.ok) throw new Error('HTTP error ' + res.status);
                return res.json();
            })
            .then(codes => {
                codeCache[cacheKey] = Array.isArray(codes) ? codes : [];
                renderCodeOptions(tr, codeSelect, codeCache[cacheKey], preselectCode);
            })
            .catch(err => {
                console.error('Failed to load application codes for block ' + blockId, err);
                codeSelect.innerHTML = '<option value="">No active rates</option>';
                codeSelect.disabled = true;
                setRowRate(tr, 0, '—');
                updateTotals();
            });
    }

    function renderCodeOptions(tr, selectEl, codes, preselectCode = null) {
        const numberInput = tr.querySelector('.row-number');
        if (!codes || codes.length === 0) {
            selectEl.innerHTML = '<option value="">No active rates for this block</option>';
            selectEl.disabled = true;
            setRowRate(tr, 0, '—');
            updateTotals();
            return;
        }

        let html = '<option value="">-- Select Application Code --</option>';
        codes.forEach(c => {
            const isSel = (preselectCode && String(c.code) === String(preselectCode)) ? 'selected' : '';
            html += `<option value="${escapeHtml(c.code)}" data-rate="${c.rate}" ${isSel}>${escapeHtml(c.code)} — ${escapeHtml(c.rate_formatted)}</option>`;
        });
        selectEl.innerHTML = html;
        selectEl.disabled = false;

        const selectedOpt = selectEl.selectedOptions[0];
        const r = (selectedOpt && selectedOpt.dataset.rate) ? parseFloat(selectedOpt.dataset.rate) : 0;
        if (selectEl.value && r > 0) {
            setRowRate(tr, r, '₹' + r.toFixed(2));
            numberInput.placeholder = `e.g. ${selectEl.value}/2026/...`;
        } else {
            setRowRate(tr, 0, '—');
            numberInput.placeholder = 'Application Number';
        }
        updateTotals();
    }

    // 5. VALIDATE PREFIX IN A ROW
    function validateRowPrefix(tr) {
        const code = tr.querySelector('.row-code').value;
        const numInput = tr.querySelector('.row-number');
        const errSpan = tr.querySelector('.row-err-number');
        const num = numInput.value.trim().toUpperCase();

        errSpan.classList.add('d-none');
        errSpan.textContent = '';
        numInput.classList.remove('is-invalid');

        if (!num) return true;

        if (code && prefixRules[code]) {
            const rule = prefixRules[code];

            // Check conflicting prefixes
            for (let cp of rule.conflicting) {
                if (num.startsWith(cp + '/') || num.startsWith(cp + '-') || num.startsWith(cp + '_') || num === cp) {
                    errSpan.textContent = `Prefix mismatch: number starts with '${cp}', Code is '${code}'`;
                    errSpan.classList.remove('d-none');
                    numInput.classList.add('is-invalid');
                    return false;
                }
            }

            // Check expected prefixes
            if (rule.expected.length > 0 && num.length >= 4) {
                let matchesExpected = rule.expected.some(ep => {
                    return num.startsWith(ep + '/') || num.startsWith(ep + '-') || num.startsWith(ep + '_') || num.startsWith(ep);
                });
                if (!matchesExpected) {
                    errSpan.textContent = `Expected prefix: ${rule.expected.join(', ')}`;
                    errSpan.classList.remove('d-none');
                    numInput.classList.add('is-invalid');
                    return false;
                }
            }
        }
        return true;
    }

    // 6. DELETE ROW
    window.deleteRow = function (rowId) {
        const tr = document.getElementById(rowId);
        if (!tr) return;

        const allRows = tableBody.querySelectorAll('tr');
        if (allRows.length <= 1) {
            // If only 1 row, clear inputs instead of deleting
            tr.querySelector('.row-number').value = '';
            tr.querySelector('.row-name').value = '';
            clearRowError(tr, 'number');
            clearRowError(tr, 'name');
            setRowRate(tr, 0, '—');
            showToast('info', 'Row cleared.');
            updateTotals();
            return;
        }

        tr.remove();
        reindexRows();
        updateTotals();
        showToast('info', 'Row removed.');
    };

    // 7. RE-INDEX SERIAL NUMBERS
    function reindexRows() {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach((tr, index) => {
            const serialEl = tr.querySelector('.row-serial-badge');
            if (serialEl) serialEl.textContent = (index + 1);
        });

        const count = rows.length;
        headerCountBadge.textContent = count === 1 ? '1 Row' : `${count} Rows`;
        btnAddRowText.textContent = `+ Add Row (Entry #${count + 1})`;
    }

    // 8. UPDATE TOTALS (COUNT, TOTAL FEE, WALLET, REMAINING)
    function updateTotals() {
        const rows = tableBody.querySelectorAll('tr');
        let completedCount = 0;
        let totalFee = 0;
        let hasIncompleteRow = false;

        rows.forEach(tr => {
            const districtId = tr.querySelector('.row-district')?.value || '';
            const blockId = tr.querySelector('.row-block')?.value || '';
            const code = tr.querySelector('.row-code')?.value || '';
            const num = tr.querySelector('.row-number')?.value.trim() || '';
            const name = tr.querySelector('.row-name')?.value.trim() || '';
            const rate = parseFloat(tr.dataset.rate || '0');

            const isTouched = (districtId || blockId || code || num || name);
            const isComplete = (districtId && blockId && code && rate > 0 && num && name.length >= 2);

            if (isComplete) {
                completedCount++;
                totalFee += rate;
            } else if (isTouched) {
                hasIncompleteRow = true;
            }
        });

        if (!window.DISTRICTS || window.DISTRICTS.length === 0) {
            if (metricCount) metricCount.textContent = '0';
            if (metricTotalFee) metricTotalFee.textContent = '₹0.00';
            btnSubmitAll.disabled = true;
            submitBtnText.textContent = 'Submit Disabled (No Rates Configured)';
            return;
        }

        if (metricCount) metricCount.textContent = completedCount;
        if (metricTotalFee) metricTotalFee.textContent = '₹' + totalFee.toFixed(2);
        if (metricWallet) metricWallet.textContent = '₹' + currentWallet.toFixed(2);

        // Submit button must remain disabled until at least one valid complete row exists, and no partially filled rows
        if (completedCount === 0 || hasIncompleteRow) {
            btnSubmitAll.disabled = true;
            submitBtnText.textContent = `Submit All (${completedCount} ${completedCount === 1 ? 'Row' : 'Rows'} — ₹${totalFee.toFixed(2)})`;
        } else {
            const remaining = currentWallet - totalFee;
            if (remaining >= 0) {
                walletShortfallAlert.classList.add('d-none');
                btnSubmitAll.disabled = false;
            } else {
                const shortfall = Math.abs(remaining);
                shortfallAmount.textContent = '₹' + shortfall.toFixed(2);
                walletShortfallAlert.classList.remove('d-none');
                btnSubmitAll.disabled = true;
            }
            submitBtnText.textContent = `Submit All (${completedCount} ${completedCount === 1 ? 'Row' : 'Rows'} — ₹${totalFee.toFixed(2)})`;
        }
    }

    // 9. SUBMIT ALL (ATOMIC BATCH)
    window.handleSubmitAll = function () {
        const rows = tableBody.querySelectorAll('tr');
        const itemsToSubmit = [];
        let hasValidationError = false;
        let firstErrorInput = null;

        // Collect and validate rows
        const seenNumbers = new Set();

        rows.forEach((tr, index) => {
            const rowNum = index + 1;
            const districtSelect = tr.querySelector('.row-district');
            const blockSelect = tr.querySelector('.row-block');
            const codeSelect = tr.querySelector('.row-code');
            const numInput = tr.querySelector('.row-number');
            const nameInput = tr.querySelector('.row-name');

            const state = 'Bihar';
            const districtId = districtSelect.value;
            const blockId = blockSelect.value;
            const appCode = codeSelect.value;
            const appNumber = numInput.value.trim().toUpperCase();
            const applicantName = nameInput.value.trim();
            const clientFee = parseFloat(tr.dataset.rate || '0');

            // Untouched Blank Row check: if both number and name are empty and no location selected
            const isUntouchedBlank = (!districtId && !blockId && !appCode && !appNumber && !applicantName);
            if (isUntouchedBlank) {
                if (rows.length > 1) return;
            }

            // Partially filled validation
            if (!districtId) {
                showRowError(tr, 'district', 'Select District');
                hasValidationError = true;
                if (!firstErrorInput) firstErrorInput = districtSelect;
            }
            if (!blockId) {
                showRowError(tr, 'block', 'Select Block');
                hasValidationError = true;
                if (!firstErrorInput) firstErrorInput = blockSelect;
            }
            if (!appCode) {
                showRowError(tr, 'code', 'Select Service Code');
                hasValidationError = true;
                if (!firstErrorInput) firstErrorInput = codeSelect;
            }
            if (clientFee <= 0) {
                showToast('danger', `Row #${rowNum} does not have an active rate configured.`);
                hasValidationError = true;
            }
            if (!appNumber) {
                showRowError(tr, 'number', 'Application Number is required');
                hasValidationError = true;
                if (!firstErrorInput) firstErrorInput = numInput;
            } else if (!validateRowPrefix(tr)) {
                hasValidationError = true;
                if (!firstErrorInput) firstErrorInput = numInput;
            } else if (seenNumbers.has(appNumber)) {
                showRowError(tr, 'number', `Duplicate number '${appNumber}' in batch`);
                hasValidationError = true;
                if (!firstErrorInput) firstErrorInput = numInput;
            } else {
                seenNumbers.add(appNumber);
            }

            if (!applicantName) {
                showRowError(tr, 'name', 'Applicant Name is required');
                hasValidationError = true;
                if (!firstErrorInput) firstErrorInput = nameInput;
            } else if (applicantName.length < 2) {
                showRowError(tr, 'name', 'Min 2 characters');
                hasValidationError = true;
                if (!firstErrorInput) firstErrorInput = nameInput;
            }

            if (tr.dataset.isCalculating === '1') {
                showToast('danger', `Row #${rowNum} rate is still calculating. Please wait.`);
                hasValidationError = true;
            }

            if (!hasValidationError) {
                itemsToSubmit.push({
                    state: state,
                    district_id: districtId,
                    block_id: blockId,
                    application_code: appCode,
                    application_number: appNumber,
                    applicant_name: applicantName,
                    client_fee: clientFee
                });
            }
        });

        if (hasValidationError) {
            if (firstErrorInput) firstErrorInput.focus();
            showToast('danger', 'Please correct the highlighted fields before submitting.');
            return;
        }

        if (itemsToSubmit.length === 0) {
            showToast('danger', 'Please enter at least one application before submitting.');
            const firstNum = tableBody.querySelector('.row-number');
            if (firstNum) firstNum.focus();
            return;
        }

        // Wallet check
        const totalFee = itemsToSubmit.reduce((sum, item) => sum + Number(item.client_fee || 0), 0);
        if (currentWallet < totalFee) {
            showToast('danger', `Insufficient wallet balance! Shortfall: ₹${(totalFee - currentWallet).toFixed(2)}.`);
            return;
        }

        // Lock button & submit
        btnSubmitAll.disabled = true;
        submitIcon.className = 'spinner-border spinner-border-sm';
        submitBtnText.textContent = `Submitting ${itemsToSubmit.length} Applications...`;

        executeBatchSubmit(itemsToSubmit, false);
    };

    function executeBatchSubmit(items, acceptPriceChange = false) {
        const payload = {
            accept_price_change: acceptPriceChange,
            items: items
        };

        fetch('/api/requests/batch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json().then(data => ({ status: res.status, body: data })))
        .then(({ status, body }) => {
            btnSubmitAll.disabled = false;
            submitIcon.className = 'bi bi-send-check';
            updateTotals();

            if (status === 200 || status === 201) {
                // Success!
                if (typeof body.remainingWalletBalance === 'number') {
                    currentWallet = body.remainingWalletBalance;
                    const hWal = document.getElementById('header-wallet-balance');
                    if (hWal) hWal.textContent = '₹' + currentWallet.toFixed(2);
                    if (metricWallet) metricWallet.textContent = '₹' + currentWallet.toFixed(2);
                    document.querySelectorAll('.topbar-balance-text, [data-wallet-balance]').forEach(el => {
                        el.textContent = '₹' + currentWallet.toFixed(2);
                    });
                }

                // Show confirmation modal
                const elMsg = document.getElementById('batchSuccessMessage');
                if (elMsg) elMsg.textContent = body.message || 'Your Application Submitted';
                const elRef = document.getElementById('successBatchRef');
                if (elRef) elRef.textContent = body.batch_ref || '--';
                const elCount = document.getElementById('successItemCount');
                if (elCount) elCount.textContent = body.total_items || items.length;
                const elFee = document.getElementById('successTotalFee');
                if (elFee) elFee.textContent = '₹' + Number(body.total_fee || 0).toFixed(2);

                new bootstrap.Modal(document.getElementById('modalBatchSuccess')).show();

                // Reset table to 1 clean fresh row
                resetAllRows();

            } else if (status === 409 && body.price_changed) {
                // Price updated acceptance
                let msg = body.error || 'Rates have updated for some applications.';
                if (Array.isArray(body.changes)) {
                    msg += '\n\nUpdated rows:';
                    body.changes.forEach(c => {
                        msg += `\n• Row #${c.row} (${c.applicant_name}): Old ₹${Number(c.old_fee).toFixed(2)} → New ₹${Number(c.new_fee).toFixed(2)}`;
                    });
                }
                msg += '\n\nConfirm submission with new total of ₹' + Number(body.new_total_fee).toFixed(2) + '?';

                if (confirm(msg)) {
                    btnSubmitAll.disabled = true;
                    submitIcon.className = 'spinner-border spinner-border-sm';
                    submitBtnText.textContent = 'Submitting with updated rates...';
                    executeBatchSubmit(items, true);
                }

            } else {
                showToast('danger', body.error || 'Failed to submit batch.');
            }
        })
        .catch(err => {
            console.error('Batch error:', err);
            btnSubmitAll.disabled = false;
            submitIcon.className = 'bi bi-send-check';
            updateTotals();
            showToast('danger', 'Network error during submission. Please retry.');
        });
    }

    // 10. ERROR HELPERS
    function showRowError(tr, field, message) {
        const input = tr.querySelector(`.row-${field}`);
        const errSpan = tr.querySelector(`.row-err-${field}`);
        if (input) input.classList.add('is-invalid');
        if (errSpan) {
            errSpan.textContent = message;
            errSpan.classList.remove('d-none');
        }
    }

    function clearRowError(tr, field) {
        const input = tr.querySelector(`.row-${field}`);
        const errSpan = tr.querySelector(`.row-err-${field}`);
        if (input) input.classList.remove('is-invalid');
        if (errSpan) {
            errSpan.classList.add('d-none');
            errSpan.textContent = '';
        }
    }

    // 11. RESET TABLE
    window.resetAllRows = function () {
        tableBody.innerHTML = '';
        rowCounter = 0;
        addNewRow(null, true);
        updateTotals();
    };

    // 12. UTILITIES
    window.zoomImage = function (url) {
        document.getElementById('modalZoomImg').src = url;
        new bootstrap.Modal(document.getElementById('modalImageZoom')).show();
    };

    function showToast(type, msg) {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : (type === 'danger' ? 'danger' : 'primary')} border-0 shadow-lg`;
        t.setAttribute('role', 'alert');
        t.innerHTML = `
            <div class="d-flex">
                <div class="toast-body fw-bold small">${escapeHtml(msg)}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        c.appendChild(t);
        const bsToast = new bootstrap.Toast(t, { delay: 4000 });
        bsToast.show();
        t.addEventListener('hidden.bs.toast', () => t.remove());
    }

    function debounce(fn, wait) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // INITIALIZATION: Start with Row 1 (or copied data if present)
    if (window.COPIED_DATA) {
        addNewRow(window.COPIED_DATA, true);
    } else {
        addNewRow(null, true);
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
