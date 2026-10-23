<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('entriesTableBody');
    const btnAddRow = document.getElementById('btnAddRow');
    const btnAddRowText = document.getElementById('btnAddRowText');
    const btnSubmitAll = document.getElementById('btnSubmitAll');
    const submitBtnText = document.getElementById('submitBtnText');
    const submitIcon = document.getElementById('submitIcon');
    const metricTotalFee = document.getElementById('metricTotalFee');
    const headerCountBadge = document.getElementById('headerCountBadge');
    const walletShortfallAlert = document.getElementById('walletShortfallAlert');
    const shortfallAmount = document.getElementById('shortfallAmount');

    let currentWallet = <?= (float)($walletBalance ?? $userBalance ?? 0) ?>;
    let rowCounter = 0;

    // Caches
    const blockCache = {};
    const codeCache = {};
    const formFieldsCache = {};

    window.rowFieldDefs = {};
    window.rowDynamicData = {};
    window.rowDynamicFiles = {};

    const prefixRules = {
        'BICCO': { expected: ['BICCO'], conflicting: ['BCCCO', 'BRCCO', 'BOBC', 'NCLCO'] },
        'BCCCO': { expected: ['BCCCO'], conflicting: ['BICCO', 'BRCCO', 'BOBC', 'NCLCO'] },
        'BRCCO': { expected: ['BRCCO'], conflicting: ['BICCO', 'BCCCO', 'BOBC', 'NCLCO'] },
        'BOBC':  { expected: ['BOBC'],  conflicting: ['BICCO', 'BCCCO', 'BRCCO', 'NCLCO'] },
        'NCLCO': { expected: ['NCLCO'], conflicting: ['BICCO', 'BCCCO', 'BRCCO', 'BOBC'] }
    };

    // 1. ADD NEW APPLICATION ROW
    window.addNewRow = function (initialData = null, shouldFocus = true) {
        rowCounter++;
        const rowId = 'row_' + rowCounter;

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
            const existingRows = tableBody.querySelectorAll('tr');
            if (existingRows.length > 0) {
                const lastRow = existingRows[existingRows.length - 1];
                defDistrict = lastRow.querySelector('.row-district')?.value || '';
                defBlock = lastRow.querySelector('.row-block')?.value || '';
            } else {
                const dOpts = (window.DISTRICTS && window.DISTRICTS.length > 0) ? window.DISTRICTS : (window.DISTRICTS_DATA || []);
                if (dOpts.length === 1) {
                    defDistrict = String(dOpts[0].id);
                }
            }
        }

        const tr = document.createElement('tr');
        tr.id = rowId;
        tr.className = 'entry-row';
        tr.dataset.rate = '0';
        tr.dataset.formConfigured = '1';

        tr.innerHTML = `
            <td class="text-center align-middle">
                <span class="row-serial-badge">${rowCounter}</span>
            </td>
            <td>
                <select class="form-select form-select-sm fw-semibold row-district rounded-2" required>
                    ${buildDistrictOptionsHtml(defDistrict)}
                </select>
                <span class="row-err-district text-danger small d-none" style="font-size: 0.7rem;">Select District</span>
            </td>
            <td>
                <select class="form-select form-select-sm fw-semibold row-block rounded-2" required disabled>
                    <option value="">-- Select District First --</option>
                </select>
                <span class="row-err-block text-danger small d-none" style="font-size: 0.7rem;">Select Block</span>
            </td>
            <td>
                <select class="form-select form-select-sm fw-semibold row-code rounded-2" required disabled>
                    <option value="">-- Select Block First --</option>
                </select>
                <div class="row-code-status text-muted small mt-0.5" style="font-size: 0.68rem;"></div>
                <span class="row-err-code text-danger small d-none" style="font-size: 0.7rem;">Select Service Code</span>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm font-monospace fw-bold text-uppercase row-number rounded-2" placeholder="Application Number" value="${escapeHtml(defNumber)}">
                <span class="row-err-number text-danger small d-none" style="font-size: 0.7rem;"></span>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm fw-semibold row-name rounded-2" placeholder="Applicant Full Name" value="${escapeHtml(defName)}">
                <span class="row-err-name text-danger small d-none" style="font-size: 0.7rem;">Name is required</span>
            </td>
            <td class="align-middle">
                <div class="row-details-cell">
                    <span class="text-muted small">—</span>
                </div>
            </td>
            <td class="text-end align-middle">
                <span class="row-rate font-monospace text-muted" style="font-size: 0.85rem;">—</span>
                <div class="row-rate-spinner spinner-border spinner-border-sm text-primary d-none" role="status"></div>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-xs btn-outline-danger p-1 rounded-2 btn-delete-row" title="Delete Row" onclick="deleteRow('${rowId}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        tableBody.appendChild(tr);
        bindRowEvents(tr, defDistrict, defBlock, defCode);
        reindexRows();

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

        districtSelect.addEventListener('change', function () {
            clearRowError(tr, 'district');
            clearRowError(tr, 'block');
            clearRowError(tr, 'code');
            resetRowDynamicFields(tr);
            setRowRate(tr, 0, '—');
            const codeSelect = tr.querySelector('.row-code');
            if (codeSelect) {
                codeSelect.innerHTML = '<option value="">-- Select Block First --</option>';
                codeSelect.disabled = true;
            }
            populateBlocksForRow(tr, this.value, null, null);
        });

        blockSelect.addEventListener('change', function () {
            clearRowError(tr, 'block');
            clearRowError(tr, 'code');
            resetRowDynamicFields(tr);
            setRowRate(tr, 0, '—');
            const codeSelect = tr.querySelector('.row-code');
            if (codeSelect) {
                codeSelect.innerHTML = '<option value="">Loading rates...</option>';
                codeSelect.disabled = true;
            }
            populateCodesForRow(tr, districtSelect.value, this.value, null);
        });

        codeSelect.addEventListener('change', function () {
            clearRowError(tr, 'code');
            resetRowDynamicFields(tr);

            const selectedOpt = this.selectedOptions[0];
            const rate = (selectedOpt && selectedOpt.dataset.rate) ? parseFloat(selectedOpt.dataset.rate) : 0;
            const codeVal = this.value;

            if (codeVal && rate > 0) {
                setRowRate(tr, rate, '₹' + rate.toFixed(2));
                numberInput.placeholder = (codeVal === 'LABOURCARD') ? 'Application Number' : `e.g. ${codeVal}/2026/...`;
                loadFieldsForCode(tr, codeVal);
            } else {
                setRowRate(tr, 0, '—');
                numberInput.placeholder = 'Application Number';
                const statusEl = tr.querySelector('.row-code-status');
                if (statusEl) statusEl.innerHTML = '';
            }

            validateRowPrefix(tr);
            updateTotals();
        });

        numberInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
            clearRowError(tr, 'number');
            validateRowPrefix(tr);
            debounce(() => updateTotals(), 200)();
        });

        nameInput.addEventListener('input', function () {
            clearRowError(tr, 'name');
            debounce(() => updateTotals(), 200)();
        });

        if (initialDistrictId) {
            populateBlocksForRow(tr, initialDistrictId, initialBlockId, () => {
                if (initialBlockId) {
                    populateCodesForRow(tr, initialDistrictId, initialBlockId, initialCode);
                }
            });
        }
    }

    // 3. LOAD DYNAMIC FORM FIELDS FOR CODE
    async function loadFieldsForCode(tr, code) {
        const rowId = tr.id;
        const statusEl = tr.querySelector('.row-code-status');
        const detailsCell = tr.querySelector('.row-details-cell');
        const districtSelect = tr.querySelector('.row-district');
        const blockSelect = tr.querySelector('.row-block');
        const districtId = districtSelect ? districtSelect.value : '';
        const blockId = blockSelect ? blockSelect.value : '';

        statusEl.innerHTML = '<span class="text-primary"><i class="spinner-border spinner-border-sm me-1" style="width: 0.65rem; height: 0.65rem;"></i>Loading form fields...</span>';
        detailsCell.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';

        if (!districtId || !blockId || !code) {
            tr.dataset.formConfigured = '0';
            statusEl.innerHTML = '';
            detailsCell.innerHTML = '<span class="text-muted small">—</span>';
            resetRowDynamicFields(tr);
            updateTotals();
            return;
        }

        const cacheKey = districtId + '_' + blockId + '_' + code;
        if (formFieldsCache[cacheKey]) {
            applyLoadedFields(tr, code, formFieldsCache[cacheKey]);
            return;
        }

        try {
            const res = await fetch(`/api/application-code-fields?district_id=${encodeURIComponent(districtId)}&block_id=${encodeURIComponent(blockId)}&application_code=${encodeURIComponent(code)}`);
            const data = await res.json();

            if (res.ok && data.success && data.configured && Array.isArray(data.fields) && data.fields.length > 0) {
                formFieldsCache[cacheKey] = data.fields;
                applyLoadedFields(tr, code, data.fields);
            } else {
                tr.dataset.formConfigured = '0';
                const errMsg = (data && data.error) ? data.error : 'Application form is not configured for this code.';
                statusEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${escapeHtml(errMsg)}</span>`;
                detailsCell.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger">Unavailable</span>';
                window.rowFieldDefs[rowId] = [];
                window.rowDynamicData[rowId] = {};
                updateTotals();
            }
        } catch (e) {
            statusEl.innerHTML = '<span class="text-danger">Failed to load fields</span>';
            detailsCell.innerHTML = '<span class="text-danger small">—</span>';
            tr.dataset.formConfigured = '0';
            window.rowFieldDefs[rowId] = [];
            window.rowDynamicData[rowId] = {};
            updateTotals();
        }
    }

    function applyLoadedFields(tr, code, fields) {
        const rowId = tr.id;
        const statusEl = tr.querySelector('.row-code-status');
        tr.dataset.formConfigured = '1';
        statusEl.innerHTML = '';

        window.rowFieldDefs[rowId] = fields;
        window.rowDynamicData[rowId] = {};

        renderRowDetailsBadge(tr);
        updateTotals();
    }

    function renderRowDetailsBadge(tr) {
        const rowId = tr.id;
        const detailsCell = tr.querySelector('.row-details-cell');
        const fields = window.rowFieldDefs[rowId] || [];

        // Extra fields beyond application_number and applicant_name
        const extraFields = fields.filter(f => !['application_number', 'applicant_name'].includes(f.field_name));

        if (extraFields.length === 0) {
            detailsCell.innerHTML = '<span class="badge bg-light text-muted border px-2 py-1"><i class="bi bi-dash"></i> None</span>';
            return;
        }

        const values = window.rowDynamicData[rowId] = window.rowDynamicData[rowId] || {};

        let html = '<div class="d-flex flex-column gap-1">';
        extraFields.forEach(f => {
            const curVal = values[f.field_name] !== undefined ? values[f.field_name] : '';
            const isReq = parseInt(f.is_required) === 1;
            const reqStar = isReq ? ' *' : '';
            const placeholder = (f.placeholder || f.label) + reqStar;
            const maxLenAttr = f.max_length ? `maxlength="${f.max_length}"` : (f.field_type === 'aadhaar' ? 'maxlength="12"' : '');

            if (f.field_type === 'select' && f.options && Array.isArray(f.options) && f.options.length > 0) {
                let opts = `<option value="">-- ${escapeHtml(f.label)}${reqStar} --</option>`;
                f.options.forEach(o => {
                    const val = typeof o === 'object' ? o.value : o;
                    const txt = typeof o === 'object' ? (o.label || o.value) : o;
                    const sel = (String(curVal) === String(val)) ? 'selected' : '';
                    opts += `<option value="${escapeHtml(val)}" ${sel}>${escapeHtml(txt)}</option>`;
                });
                html += `
                    <div class="dynamic-field-inline" data-field-name="${escapeHtml(f.field_name)}">
                        <select class="form-select form-select-sm row-dynamic-input rounded-2" 
                                data-field-name="${escapeHtml(f.field_name)}" 
                                data-field-type="${escapeHtml(f.field_type)}"
                                title="${escapeHtml(f.label)}">
                            ${opts}
                        </select>
                        <span class="row-err-dyn text-danger small d-none" style="font-size: 0.68rem;"></span>
                    </div>
                `;
            } else if (f.field_type === 'file') {
                const allowedExts = (f.allowed_file_types || 'pdf,jpg,jpeg,png').toLowerCase();
                const acceptAttr = allowedExts.split(',').map(ext => '.' + ext.trim()).join(',');
                const maxMb = f.max_file_size_mb || 2;
                const fileVal = values[f.field_name] || '';
                const fileName = values[f.field_name + '_name'] || (fileVal ? fileVal.split('/').pop() : '');
                const hasFile = !!fileVal;
                const isPdf = fileName.toLowerCase().endsWith('.pdf');

                html += `
                    <div class="dynamic-field-inline dynamic-field-file-box" data-field-name="${escapeHtml(f.field_name)}">
                        <input type="file" 
                                class="d-none row-dynamic-file-input" 
                                id="file_${rowId}_${escapeHtml(f.field_name)}"
                                data-field-name="${escapeHtml(f.field_name)}"
                                data-field-type="file"
                                data-max-mb="${maxMb}"
                                data-allowed-exts="${escapeHtml(allowedExts)}"
                                accept="${escapeHtml(acceptAttr)}">
                        <div class="file-picker-container">
                            <div class="file-picker-btn btn btn-sm w-100 py-1 px-2 rounded-2 border d-flex align-items-center justify-content-between ${hasFile ? 'btn-light border-success text-success shadow-2xs' : 'btn-white border-secondary-subtle text-secondary'}"
                                 style="font-size: 0.76rem; cursor: pointer; background: #ffffff;"
                                 onclick="document.getElementById('file_${rowId}_${escapeHtml(f.field_name)}').click()"
                                 title="${escapeHtml(f.label)}${reqStar} (Allowed: ${escapeHtml(allowedExts)} | Max: ${maxMb}MB)">
                                <div class="d-flex align-items-center gap-1.5 text-truncate flex-grow-1 me-1 text-start">
                                    <i class="bi ${hasFile ? (isPdf ? 'bi-file-earmark-pdf-fill text-danger fs-6' : 'bi-file-earmark-image-fill text-primary fs-6') : 'bi-cloud-arrow-up-fill text-primary fs-6'}"></i>
                                    <span class="file-display-name text-truncate fw-semibold" style="max-width: 130px;">
                                        ${hasFile ? escapeHtml(fileName) : `<span class="text-dark">${escapeHtml(f.label)}${reqStar}</span>`}
                                    </span>
                                </div>
                                <span class="badge ${hasFile ? 'bg-success text-white' : 'bg-primary-subtle text-primary border border-primary-subtle'} px-1.5 py-0.5 rounded-pill" style="font-size: 0.65rem;">
                                    ${hasFile ? 'Attached' : 'Upload'}
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-0.5 px-0.5" style="font-size: 0.65rem;">
                                <span class="text-muted text-truncate" style="max-width: 140px;">
                                    <i class="bi bi-paperclip text-muted"></i> ${escapeHtml(allowedExts.toUpperCase())} (${maxMb}MB)
                                </span>
                                ${hasFile ? `
                                    <button type="button" class="btn btn-link btn-xs text-danger p-0 text-decoration-none fw-bold" 
                                            onclick="event.stopPropagation(); window.removeRowFile('${rowId}', '${escapeHtml(f.field_name)}')"
                                            style="font-size: 0.65rem;" title="Remove attachment">
                                        <i class="bi bi-x-circle-fill"></i> Remove
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                        <span class="row-err-dyn text-danger small d-none" style="font-size: 0.68rem;"></span>
                    </div>
                `;
            } else {
                const inputType = f.field_type === 'number' ? 'number' : (f.field_type === 'date' ? 'date' : 'text');
                const fontClass = f.field_type === 'aadhaar' ? 'font-monospace' : (f.field_name.includes('number') ? 'font-monospace' : '');
                html += `
                    <div class="dynamic-field-inline" data-field-name="${escapeHtml(f.field_name)}">
                        <input type="${inputType}" 
                               class="form-control form-control-sm row-dynamic-input ${fontClass} rounded-2" 
                               placeholder="${escapeHtml(placeholder)}" 
                               value="${escapeHtml(curVal)}"
                               title="${escapeHtml(f.label)}"
                               data-field-name="${escapeHtml(f.field_name)}"
                               data-field-type="${escapeHtml(f.field_type)}"
                               ${maxLenAttr}>
                        <span class="row-err-dyn text-danger small d-none" style="font-size: 0.68rem;"></span>
                    </div>
                `;
            }
        });
        html += '</div>';

        detailsCell.innerHTML = html;

        // Bind input and change events to update window.rowDynamicData[rowId]
        detailsCell.querySelectorAll('.row-dynamic-input').forEach(input => {
            const onUpdate = function () {
                const fName = this.dataset.fieldName;
                const fType = this.dataset.fieldType;
                if (fType === 'aadhaar') {
                    this.value = this.value.replace(/\D/g, '').slice(0, 12);
                }
                window.rowDynamicData[rowId][fName] = this.value.trim();

                const errSpan = this.closest('.dynamic-field-inline')?.querySelector('.row-err-dyn');
                if (errSpan) {
                    errSpan.classList.add('d-none');
                    errSpan.textContent = '';
                }
                this.classList.remove('is-invalid');

                debounce(() => updateTotals(), 150)();
            };

            input.addEventListener('input', onUpdate);
            input.addEventListener('change', onUpdate);
        });

        // Bind file input change events with auto-upload
        detailsCell.querySelectorAll('.row-dynamic-file-input').forEach(fileInput => {
            fileInput.addEventListener('change', async function () {
                const fName = this.dataset.fieldName;
                const maxMb = parseFloat(this.dataset.maxMb || '2');
                const allowedExts = (this.dataset.allowedExts || 'pdf,jpg,jpeg,png').split(',').map(e => e.trim().toLowerCase());
                const file = this.files && this.files[0];
                const box = this.closest('.dynamic-field-file-box');
                const errSpan = box?.querySelector('.row-err-dyn');
                const pickerBtn = box?.querySelector('.file-picker-btn');

                if (!file) return;

                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedExts.includes(ext)) {
                    if (errSpan) {
                        errSpan.textContent = `Allowed types: ${allowedExts.join(', ')}`;
                        errSpan.classList.remove('d-none');
                    }
                    this.value = '';
                    showToast('danger', `Invalid file type '.${ext}'. Allowed: ${allowedExts.join(', ')}`);
                    return;
                }

                if (file.size > maxMb * 1024 * 1024) {
                    if (errSpan) {
                        errSpan.textContent = `File exceeds max size ${maxMb}MB`;
                        errSpan.classList.remove('d-none');
                    }
                    this.value = '';
                    showToast('danger', `File size exceeds maximum limit of ${maxMb}MB.`);
                    return;
                }

                if (errSpan) {
                    errSpan.classList.add('d-none');
                    errSpan.textContent = '';
                }

                // Show uploading indicator on the button
                if (pickerBtn) {
                    pickerBtn.innerHTML = `
                        <div class="d-flex align-items-center gap-1.5 text-primary">
                            <span class="spinner-border spinner-border-sm" style="width: 0.75rem; height: 0.75rem;"></span>
                            <span class="fw-semibold">Uploading ${escapeHtml(file.name)}...</span>
                        </div>
                    `;
                }

                // Keep file reference
                window.rowDynamicFiles[rowId] = window.rowDynamicFiles[rowId] || {};
                window.rowDynamicFiles[rowId][fName] = file;

                // Also read as base64 DataURL for resilient dual-payload
                const reader = new FileReader();
                reader.onload = function (re) {
                    window.rowDynamicData[rowId] = window.rowDynamicData[rowId] || {};
                    window.rowDynamicData[rowId][fName + '_base64'] = re.target.result;
                };
                reader.readAsDataURL(file);

                // Auto-upload to /api/requests/upload endpoint
                const upForm = new FormData();
                upForm.append('file', file);
                try {
                    const upRes = await fetch('/api/requests/upload', {
                        method: 'POST',
                        body: upForm
                    });
                    const upData = await upRes.json();
                    if (upRes.ok && upData.success && upData.url) {
                        window.rowDynamicData[rowId] = window.rowDynamicData[rowId] || {};
                        window.rowDynamicData[rowId][fName] = upData.url;
                        window.rowDynamicData[rowId][fName + '_name'] = file.name;
                    } else {
                        // Fallback: keep file name
                        window.rowDynamicData[rowId] = window.rowDynamicData[rowId] || {};
                        window.rowDynamicData[rowId][fName] = file.name;
                        window.rowDynamicData[rowId][fName + '_name'] = file.name;
                    }
                } catch (e) {
                    window.rowDynamicData[rowId] = window.rowDynamicData[rowId] || {};
                    window.rowDynamicData[rowId][fName] = file.name;
                    window.rowDynamicData[rowId][fName + '_name'] = file.name;
                }

                renderRowDetailsBadge(tr);
                updateTotals();
            });
        });
    }

    window.removeRowFile = function (rowId, fieldName) {
        if (window.rowDynamicFiles && window.rowDynamicFiles[rowId]) {
            delete window.rowDynamicFiles[rowId][fieldName];
        }
        if (window.rowDynamicData && window.rowDynamicData[rowId]) {
            delete window.rowDynamicData[rowId][fieldName];
            delete window.rowDynamicData[rowId][fieldName + '_name'];
            delete window.rowDynamicData[rowId][fieldName + '_base64'];
        }
        const tr = document.getElementById(rowId);
        if (tr) {
            renderRowDetailsBadge(tr);
            updateTotals();
        }
    };

    function resetRowDynamicFields(tr) {
        const rowId = tr.id;
        window.rowDynamicData[rowId] = {};
        window.rowFieldDefs[rowId] = [];
        if (window.rowDynamicFiles) {
            delete window.rowDynamicFiles[rowId];
        }
        tr.dataset.formConfigured = '1';
        const statusEl = tr.querySelector('.row-code-status');
        if (statusEl) statusEl.innerHTML = '';
        const detailsCell = tr.querySelector('.row-details-cell');
        if (detailsCell) detailsCell.innerHTML = '<span class="text-muted small">—</span>';
    }

    // 4. POPULATE BLOCKS FOR ROW WITH CACHING
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
            .then(res => res.json())
            .then(blocks => {
                blockCache[districtId] = Array.isArray(blocks) ? blocks : [];
                renderBlockOptions(tr, blockSelect, blockCache[districtId], preselectBlockId, callback);
            })
            .catch(() => {
                blockSelect.innerHTML = '<option value="">Service is not available in this district.</option>';
                blockSelect.disabled = true;
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
            if (callback) callback();
        } else if (blocks.length === 1) {
            selectEl.selectedIndex = 1;
            const distVal = tr.querySelector('.row-district')?.value || '';
            populateCodesForRow(tr, distVal, blocks[0].id, null);
        } else {
            codeSelect.innerHTML = '<option value="">-- Select Block First --</option>';
            codeSelect.disabled = true;
            setRowRate(tr, 0, '—');
            updateTotals();
        }
    }

    // 5. POPULATE CODES AND RATES FOR ROW WITH CACHING
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

        codeSelect.innerHTML = '<option value="">Loading rates...</option>';
        codeSelect.disabled = true;
        setRowRate(tr, 0, '—');

        fetch('/api/service-application-codes?district_id=' + encodeURIComponent(districtId) + '&block_id=' + encodeURIComponent(blockId) + '&service=srv_block_app')
            .then(res => res.json())
            .then(codes => {
                codeCache[cacheKey] = Array.isArray(codes) ? codes : [];
                renderCodeOptions(tr, codeSelect, codeCache[cacheKey], preselectCode);
            })
            .catch(() => {
                codeSelect.innerHTML = '<option value="">No rates configured</option>';
                codeSelect.disabled = true;
                setRowRate(tr, 0, '—');
                updateTotals();
            });
    }

    function renderCodeOptions(tr, selectEl, codes, preselectCode = null) {
        const numberInput = tr.querySelector('.row-number');

        if (!codes || codes.length === 0) {
            selectEl.innerHTML = '<option value="">No rate-configured codes</option>';
            selectEl.disabled = true;
            setRowRate(tr, 0, '—');
            updateTotals();
            return;
        }

        const seen = new Set();
        const uniqueCodes = [];
        codes.forEach(c => {
            const key = String(c.code).trim().toUpperCase();
            if (key && !seen.has(key)) {
                seen.add(key);
                uniqueCodes.push(c);
            }
        });

        let html = '<option value="">-- Select Application Code --</option>';
        uniqueCodes.forEach(c => {
            const isSel = (preselectCode && String(c.code) === String(preselectCode)) ? 'selected' : '';
            html += `<option value="${escapeHtml(c.code)}" data-rate="${c.rate}" ${isSel}>${escapeHtml(c.code)} — ${escapeHtml(c.rate_formatted)}</option>`;
        });

        selectEl.innerHTML = html;
        selectEl.disabled = false;

        const selectedOpt = selectEl.selectedOptions[0];
        const r = (selectedOpt && selectedOpt.dataset.rate) ? parseFloat(selectedOpt.dataset.rate) : 0;
        if (selectEl.value && r > 0) {
            setRowRate(tr, r, '₹' + r.toFixed(2));
            numberInput.placeholder = selectEl.value === 'LABOURCARD' ? 'Application Number' : `e.g. ${selectEl.value}/2026/...`;
            loadFieldsForCode(tr, selectEl.value);
        } else {
            setRowRate(tr, 0, '—');
            numberInput.placeholder = 'Application Number';
        }
        updateTotals();
    }

    // 6. VALIDATE PREFIX IN A ROW
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
            for (let cp of rule.conflicting) {
                if (num.startsWith(cp + '/') || num.startsWith(cp + '-') || num.startsWith(cp + '_') || num === cp) {
                    errSpan.textContent = `Prefix mismatch: number starts with '${cp}', Code is '${code}'`;
                    errSpan.classList.remove('d-none');
                    numInput.classList.add('is-invalid');
                    return false;
                }
            }

            if (rule.expected.length > 0 && num.length >= 4) {
                let matchesExpected = rule.expected.some(ep => num.startsWith(ep + '/') || num.startsWith(ep + '-') || num.startsWith(ep + '_') || num.startsWith(ep));
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

    // 7. DELETE ROW
    window.deleteRow = function (rowId) {
        const tr = document.getElementById(rowId);
        if (!tr) return;

        delete window.rowDynamicData[rowId];
        delete window.rowFieldDefs[rowId];

        const allRows = tableBody.querySelectorAll('tr');
        if (allRows.length <= 1) {
            tr.querySelector('.row-number').value = '';
            tr.querySelector('.row-name').value = '';
            clearRowError(tr, 'number');
            clearRowError(tr, 'name');
            setRowRate(tr, 0, '—');
            resetRowDynamicFields(tr);
            showToast('info', 'Row cleared.');
            updateTotals();
            return;
        }

        tr.remove();
        reindexRows();
        updateTotals();
        showToast('info', 'Row removed.');
    };

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

    // 8. UPDATE TOTALS
    function updateTotals() {
        const rows = tableBody.querySelectorAll('tr');
        let completedCount = 0;
        let totalFee = 0;
        let hasIncompleteRow = false;
        let hasUnconfiguredCode = false;

        rows.forEach(tr => {
            const rowId = tr.id;
            const districtId = tr.querySelector('.row-district')?.value || '';
            const blockId = tr.querySelector('.row-block')?.value || '';
            const code = tr.querySelector('.row-code')?.value || '';
            const num = tr.querySelector('.row-number')?.value.trim() || '';
            const name = tr.querySelector('.row-name')?.value.trim() || '';
            const rate = parseFloat(tr.dataset.rate || '0');
            const isConfigured = tr.dataset.formConfigured !== '0';

            if (!isConfigured && code) {
                hasUnconfiguredCode = true;
            }

            // Check extra fields completion
            const fields = window.rowFieldDefs[rowId] || [];
            const values = window.rowDynamicData[rowId] || {};
            const files = window.rowDynamicFiles?.[rowId] || {};
            let missingExtraReq = false;
            fields.filter(f => !['application_number', 'applicant_name'].includes(f.field_name)).forEach(f => {
                if (parseInt(f.is_required) === 1) {
                    if (f.field_type === 'file') {
                        const fileVal = values[f.field_name] || files[f.field_name];
                        if (!fileVal) {
                            missingExtraReq = true;
                        }
                    } else {
                        const v = values[f.field_name];
                        if (v === undefined || v === null || String(v).trim() === '') {
                            missingExtraReq = true;
                        }
                    }
                }
            });

            const isTouched = (districtId || blockId || code || num || name);
            const isComplete = (districtId && blockId && code && isConfigured && rate > 0 && num && name.length >= 2 && !missingExtraReq);

            if (isComplete) {
                completedCount++;
                totalFee += rate;
            } else if (isTouched) {
                hasIncompleteRow = true;
            }
        });

        if (hasUnconfiguredCode) {
            btnAddRow.disabled = true;
            btnSubmitAll.disabled = true;
            submitBtnText.textContent = 'Application form is not configured for this code.';
            return;
        } else {
            btnAddRow.disabled = false;
        }

        if (metricTotalFee) metricTotalFee.textContent = '₹' + totalFee.toFixed(2);

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
        const seenNumbers = new Set();

        rows.forEach((tr, index) => {
            const rowNum = index + 1;
            const rowId = tr.id;
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

            const isUntouchedBlank = (!districtId && !blockId && !appCode && !appNumber && !applicantName);
            if (isUntouchedBlank && rows.length > 1) return;

            if (tr.dataset.formConfigured === '0') {
                showToast('danger', `Row #${rowNum}: Application form is not configured for this code.`);
                hasValidationError = true;
                return;
            }

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

            // Check extra fields
            const fields = window.rowFieldDefs[rowId] || [];
            const values = window.rowDynamicData[rowId] || {};
            const files = window.rowDynamicFiles?.[rowId] || {};
            fields.filter(f => !['application_number', 'applicant_name'].includes(f.field_name)).forEach(f => {
                const v = values[f.field_name];
                const dynInput = tr.querySelector(`.row-dynamic-input[data-field-name="${f.field_name}"]`);
                const dynBox = tr.querySelector(`.dynamic-field-inline[data-field-name="${f.field_name}"]`);
                const dynErr = dynBox?.querySelector('.row-err-dyn');

                if (parseInt(f.is_required) === 1) {
                    if (f.field_type === 'file') {
                        const hasFile = (v !== undefined && v !== null && String(v).trim() !== '') || !!files[f.field_name];
                        if (!hasFile) {
                            if (dynErr) {
                                dynErr.textContent = `${f.label} is required`;
                                dynErr.classList.remove('d-none');
                            }
                            const pickerBtn = dynBox?.querySelector('.file-picker-btn');
                            if (pickerBtn) pickerBtn.classList.add('border-danger');
                            if (!firstErrorInput) firstErrorInput = pickerBtn;
                            showToast('danger', `Row #${rowNum}: Document '${f.label}' is required.`);
                            hasValidationError = true;
                            return;
                        }
                    } else {
                        if (v === undefined || v === null || String(v).trim() === '') {
                            if (dynErr && dynInput) {
                                dynErr.textContent = `${f.label} is required`;
                                dynErr.classList.remove('d-none');
                                dynInput.classList.add('is-invalid');
                                if (!firstErrorInput) firstErrorInput = dynInput;
                            }
                            showToast('danger', `Row #${rowNum}: Field '${f.label}' is required.`);
                            hasValidationError = true;
                            return;
                        }
                    }
                }

                if (f.field_type === 'aadhaar' && v) {
                    if (!/^\d{12}$/.test(String(v).trim())) {
                        if (dynErr && dynInput) {
                            dynErr.textContent = '12 digits required';
                            dynErr.classList.remove('d-none');
                            dynInput.classList.add('is-invalid');
                            if (!firstErrorInput) firstErrorInput = dynInput;
                        }
                        showToast('danger', `Row #${rowNum}: Aadhaar must be exactly 12 digits.`);
                        hasValidationError = true;
                    }
                }
            });

            if (!hasValidationError) {
                itemsToSubmit.push({
                    state: state,
                    district_id: districtId,
                    block_id: blockId,
                    application_code: appCode,
                    application_number: appNumber,
                    applicant_name: applicantName,
                    client_fee: clientFee,
                    ...values
                });
            }
        });

        if (hasValidationError) {
            if (firstErrorInput) firstErrorInput.focus();
            return;
        }

        if (itemsToSubmit.length === 0) {
            showToast('danger', 'Please enter at least one application before submitting.');
            return;
        }

        const totalFee = itemsToSubmit.reduce((sum, item) => sum + Number(item.client_fee || 0), 0);
        if (currentWallet < totalFee) {
            showToast('danger', `Insufficient wallet balance! Shortfall: ₹${(totalFee - currentWallet).toFixed(2)}.`);
            return;
        }

        btnSubmitAll.disabled = true;
        submitIcon.className = 'spinner-border spinner-border-sm';
        submitBtnText.textContent = `Submitting ${itemsToSubmit.length} Applications...`;

        executeBatchSubmit(itemsToSubmit, false);
    };

    function executeBatchSubmit(items, acceptPriceChange = false) {
        // Build FormData to support file uploads alongside items payload
        const formData = new FormData();
        formData.append('accept_price_change', acceptPriceChange ? '1' : '0');
        formData.append('items', JSON.stringify(items));

        let hasRawFiles = false;
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach((tr, idx) => {
            const rowId = tr.id;
            const rFiles = window.rowDynamicFiles?.[rowId] || {};
            for (const [fName, fObj] of Object.entries(rFiles)) {
                if (fObj instanceof File) {
                    formData.append(`batch_files_${idx}_${fName}`, fObj);
                    hasRawFiles = true;
                }
            }
        });

        const reqOptions = hasRawFiles ? {
            method: 'POST',
            body: formData
        } : {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accept_price_change: acceptPriceChange, items: items })
        };

        fetch('/api/requests/batch', reqOptions)
        .then(res => res.json().then(data => ({ status: res.status, body: data })))
        .then(({ status, body }) => {
            btnSubmitAll.disabled = false;
            submitIcon.className = 'bi bi-send-check';
            updateTotals();

            if (status === 200 || status === 201) {
                if (typeof body.remainingWalletBalance === 'number') {
                    currentWallet = body.remainingWalletBalance;
                    const hWal = document.getElementById('header-wallet-balance');
                    if (hWal) hWal.textContent = '₹' + currentWallet.toFixed(2);
                }

                // Show rich Success Modal Popup instead of browser alert()
                showSuccessModal(body);
            } else if (status === 409 && body.price_changed) {
                // Show rich Price Change Confirmation Modal Popup instead of browser confirm()
                showPriceChangeModal(body, items);
            } else {
                showGeneralAlert('Submission Error', body.error || 'Failed to submit applications.', 'danger');
            }
        })
        .catch(err => {
            console.error('Batch error:', err);
            btnSubmitAll.disabled = false;
            submitIcon.className = 'bi bi-send-check';
            updateTotals();
            showGeneralAlert('Network Error', 'Network connection error during submission. Please check connection and retry.', 'danger');
        });
    }

    // Modal Popups for Rate Updates, Success, and Errors
    function showPriceChangeModal(body, items) {
        const modalEl = document.getElementById('modalPriceChange');
        if (!modalEl) {
            showToast('warning', body.error || 'Rate update detected.');
            return;
        }

        const descEl = document.getElementById('priceChangeDescription');
        if (descEl && body.error) {
            descEl.textContent = body.error;
        }

        const tbody = document.getElementById('priceChangeTableBody');
        if (tbody) {
            let rowsHtml = '';
            const changes = body.changes || [];
            if (changes.length > 0) {
                changes.forEach(c => {
                    const oldF = typeof c.old_fee === 'number' ? '₹' + c.old_fee.toFixed(2) : '—';
                    const newF = typeof c.new_fee === 'number' ? '₹' + c.new_fee.toFixed(2) : '—';
                    rowsHtml += `
                        <tr>
                            <td class="fw-bold">${c.row || 1}</td>
                            <td>
                                <div class="fw-semibold text-dark">${escapeHtml(c.applicant_name || 'Applicant')}</div>
                                <div class="text-muted small" style="font-size: 0.72rem;">${escapeHtml(c.application_number || '')}</div>
                            </td>
                            <td class="text-end text-muted"><del>${oldF}</del></td>
                            <td class="text-end fw-bold text-primary font-monospace">${newF}</td>
                        </tr>
                    `;
                });
            } else {
                rowsHtml = '<tr><td colspan="4" class="text-center text-muted py-2">Updated rates ready for confirmation.</td></tr>';
            }
            tbody.innerHTML = rowsHtml;
        }

        const totalEl = document.getElementById('modalNewTotalFee');
        if (totalEl) {
            const tot = typeof body.new_total_fee === 'number' ? body.new_total_fee : 0;
            totalEl.textContent = '₹' + tot.toFixed(2);
        }

        const confirmBtn = document.getElementById('btnConfirmPriceChange');
        if (confirmBtn) {
            confirmBtn.onclick = function () {
                const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                bsModal.hide();

                btnSubmitAll.disabled = true;
                submitIcon.className = 'spinner-border spinner-border-sm me-1';
                submitBtnText.textContent = 'Submitting with updated rates...';

                executeBatchSubmit(items, true);
            };
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function showSuccessModal(body) {
        const modalEl = document.getElementById('modalBatchSuccess');
        if (!modalEl) {
            window.location.href = '/applications';
            return;
        }

        const msgEl = document.getElementById('modalSuccessMessage');
        if (msgEl) msgEl.textContent = body.message || 'Applications submitted successfully!';

        const refEl = document.getElementById('modalSuccessBatchRef');
        if (refEl) refEl.textContent = body.batch_ref || (body.applications?.[0]?.app_ref || 'SUCCESS');

        const cntEl = document.getElementById('modalSuccessAppCount');
        if (cntEl) cntEl.textContent = body.total_items || (body.applications?.length || 1);

        const walEl = document.getElementById('modalSuccessWallet');
        if (walEl) {
            const w = typeof body.remainingWalletBalance === 'number' ? body.remainingWalletBalance : currentWallet;
            walEl.textContent = '₹' + w.toFixed(2);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function showGeneralAlert(title, message, type = 'danger') {
        const modalEl = document.getElementById('modalGeneralAlert');
        if (!modalEl) {
            showToast(type, message);
            return;
        }

        const titleEl = document.getElementById('alertModalTitle');
        if (titleEl) titleEl.textContent = title;

        const bodyEl = document.getElementById('alertModalBody');
        if (bodyEl) bodyEl.textContent = message;

        const iconEl = document.getElementById('alertModalIcon');
        const wrapperEl = document.getElementById('alertModalIconWrapper');
        if (iconEl && wrapperEl) {
            if (type === 'danger') {
                wrapperEl.style.backgroundColor = '#fee2e2';
                wrapperEl.style.color = '#dc2626';
                iconEl.className = 'bi bi-exclamation-octagon-fill fs-2';
            } else if (type === 'warning') {
                wrapperEl.style.backgroundColor = '#fef3c7';
                wrapperEl.style.color = '#d97706';
                iconEl.className = 'bi bi-exclamation-triangle-fill fs-2';
            } else {
                wrapperEl.style.backgroundColor = '#e0f2fe';
                wrapperEl.style.color = '#0284c7';
                iconEl.className = 'bi bi-info-circle-fill fs-2';
            }
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    // 10. ERROR & UI HELPERS
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

    function setRowRate(tr, fee, label) {
        tr.dataset.rate = fee;
        const rateEl = tr.querySelector('.row-rate');
        if (rateEl) {
            rateEl.textContent = label;
            if (fee > 0) {
                rateEl.classList.remove('text-muted');
                rateEl.classList.add('text-success', 'fw-bold');
            } else {
                rateEl.classList.remove('text-success', 'fw-bold');
                rateEl.classList.add('text-muted');
            }
        }
    }

    function buildDistrictOptionsHtml(preselectedId = '') {
        const dOpts = (window.DISTRICTS && window.DISTRICTS.length > 0) ? window.DISTRICTS : (window.DISTRICTS_DATA || []);
        let html = '<option value="">-- Select District --</option>';
        dOpts.forEach(d => {
            const isSel = (preselectedId && String(d.id) === String(preselectedId)) ? 'selected' : '';
            html += `<option value="${d.id}" ${isSel}>${escapeHtml(d.name)}</option>`;
        });
        return html;
    }

    window.resetAllRows = function () {
        tableBody.innerHTML = '';
        rowCounter = 0;
        addNewRow(null, true);
        updateTotals();
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
        d.textContent = str || '';
        return d.innerHTML;
    }

    // INITIALIZATION: Start with Row 1 (or copied data)
    if (window.COPIED_DATA) {
        addNewRow(window.COPIED_DATA, true);
    } else {
        addNewRow(null, true);
    }
});
</script>
