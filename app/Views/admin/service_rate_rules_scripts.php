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
