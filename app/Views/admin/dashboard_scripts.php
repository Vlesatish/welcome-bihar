<script>
// Global filter helpers (immediately callable by onclick handlers)
window.filterByStatus = function (st) {
    const form = document.getElementById('filterForm');
    const statusSelect = document.getElementById('filterStatusSelect');
    const statusInput = document.getElementById('filterStatusInput');
    const pageInput = document.getElementById('filterPageInput');
    if (pageInput) pageInput.value = '1';

    const currentSt = (statusSelect ? statusSelect.value : (statusInput ? statusInput.value : 'ALL'));
    const targetSt = (currentSt === st) ? 'ALL' : st;

    if (statusSelect) statusSelect.value = targetSt;
    if (statusInput) statusInput.value = targetSt;
    if (form) form.submit();
};

window.setStatusFilter = function (st) {
    const form = document.getElementById('filterForm');
    const statusSelect = document.getElementById('filterStatusSelect');
    const statusInput = document.getElementById('filterStatusInput');
    const pageInput = document.getElementById('filterPageInput');
    if (pageInput) pageInput.value = '1';

    if (statusSelect) statusSelect.value = st;
    if (statusInput) statusInput.value = st;
    if (form) form.submit();
};

document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('filterForm');
    const filterDistrict = document.getElementById('filterDistrict');
    const filterBlock = document.getElementById('filterBlock');
    const filterStatusSelect = document.getElementById('filterStatusSelect');
    const filterCode = document.getElementById('filterCode');
    const filterDate = document.getElementById('filterDate');
    const searchInput = document.getElementById('admin-search-input');
    const filterPageInput = document.getElementById('filterPageInput');
    const filterStatusInput = document.getElementById('filterStatusInput');

    const allBlocksByDistrict = <?= json_encode($allBlocksGrouped ?? []) ?>;

    // 1. Status dropdown change listener -> resets page and auto-submits
    if (filterStatusSelect) {
        filterStatusSelect.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            if (filterStatusInput) filterStatusInput.value = this.value;
            filterForm.submit();
        });
    }

    // 2. District change listener (only for admin where filterDistrict is a select)
    if (filterDistrict && filterDistrict.tagName === 'SELECT') {
        filterDistrict.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            const distId = this.value;
            if (filterBlock) {
                filterBlock.innerHTML = '<option value="">All Blocks</option>';
                if (distId && allBlocksByDistrict[distId]) {
                    filterBlock.disabled = false;
                    allBlocksByDistrict[distId].forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        opt.textContent = b.name;
                        filterBlock.appendChild(opt);
                    });
                } else {
                    filterBlock.disabled = true;
                }
                filterBlock.value = '';
            }
            filterForm.submit();
        });
    }

    // 3. Block change listener
    if (filterBlock && filterBlock.tagName === 'SELECT') {
        filterBlock.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            filterForm.submit();
        });
    }

    // 4. Application Code change listener -> resets page and auto-submits
    if (filterCode) {
        filterCode.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            filterForm.submit();
        });
    }

    // 5. Date filter change listener -> resets page and auto-submits
    if (filterDate) {
        filterDate.addEventListener('change', function () {
            if (filterPageInput) filterPageInput.value = '1';
            filterForm.submit();
        });
    }

    // 6. Search Enter submit
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (filterPageInput) filterPageInput.value = '1';
                filterForm.submit();
            }
        });
    }

    // Copy to clipboard helper
    window.copyToClipboard = function (text, btn) {
        if (!text || text === 'N/A') return;
        navigator.clipboard.writeText(text).then(() => {
            showToast('success', 'Application Number copied: ' + text);
            if (btn) {
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check text-success" style="font-size: 0.82rem;"></i>';
                setTimeout(() => { btn.innerHTML = origHtml; }, 1500);
            }
        }).catch(() => {
            showToast('info', 'Number: ' + text);
        });
    };

    // -------------------------------------------------------------
    // KPI COUNTER HELPER & SEAMLESS ROW TRANSITION (No forced redirect)
    // -------------------------------------------------------------
    function adjustKpiCount(fromStatus, toStatus) {
        const map = {
            'PENDING': 'stat-pending',
            'IN_PROCESS': 'stat-in-process',
            'UNDER_REVIEW': 'stat-in-process',
            'APPROVED': 'stat-approved',
            'COMPLETED': 'stat-approved',
            'REJECTED': 'stat-rejected'
        };
        const fromId = map[fromStatus];
        const toId = map[toStatus];
        if (fromId && fromId !== toId) {
            const el = document.getElementById(fromId);
            if (el) {
                let val = parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
                if (val > 0) el.textContent = (val - 1).toLocaleString();
            }
        }
        if (toId && fromId !== toId) {
            const el = document.getElementById(toId);
            if (el) {
                let val = parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
                el.textContent = (val + 1).toLocaleString();
            }
        }
    }

    function removeRowWithAnimation(row) {
        row.style.transition = 'all 0.35s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateX(25px)';
        setTimeout(() => {
            row.remove();
            const tbody = document.getElementById('adminRequestsTbody');
            if (tbody && tbody.querySelectorAll('.admin-req-row').length === 0) {
                tbody.innerHTML = `
                    <tr id="adminNoRequestsRow">
                        <td colspan="7" class="text-center py-5 text-muted small">
                            <i class="bi bi-inbox fs-2 d-block opacity-50 mb-1"></i>
                            No applications found.
                        </td>
                    </tr>
                `;
            }
        }, 350);
    }

    function handleStatusTransition(appId, newStatus, resultFileUrl = null, rejectionReason = null) {
        const row = document.getElementById('admin-row-' + appId);
        const oldStatus = row ? (row.getAttribute('data-status') || 'PENDING') : 'PENDING';

        adjustKpiCount(oldStatus, newStatus);

        const currentTab = (document.getElementById('filterStatusInput')?.value || 'ALL').toUpperCase();

        if (currentTab === 'PENDING' && newStatus !== 'PENDING') {
            if (row) removeRowWithAnimation(row);
        } else if (currentTab === 'IN_PROCESS' && newStatus !== 'IN_PROCESS' && newStatus !== 'UNDER_REVIEW') {
            if (row) removeRowWithAnimation(row);
        } else if (currentTab === 'APPROVED' && newStatus !== 'APPROVED' && newStatus !== 'COMPLETED') {
            if (row) removeRowWithAnimation(row);
        } else if (currentTab === 'REJECTED' && newStatus !== 'REJECTED') {
            if (row) removeRowWithAnimation(row);
        } else {
            updateRowState(appId, newStatus, resultFileUrl, rejectionReason);
        }
    }

    // -------------------------------------------------------------
    // ACTION: 1-CLICK QUICK STATUS UPDATE
    // -------------------------------------------------------------
    window.quickUpdateStatus = function (appId, targetStatus, btn = null) {
        let origHtml = '';
        if (btn) {
            origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }

        const fd = new FormData();
        fd.append('application_id', appId);
        fd.append('status', targetStatus);
        fd.append('admin_remarks', targetStatus === 'APPROVED' ? 'Approved by administrator' : 'Processing started by administrator');

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const readableTarget = targetStatus === 'APPROVED' ? 'Approved' : (targetStatus === 'IN_PROCESS' ? 'In Process' : targetStatus);
                showToast('success', `Application #${appId} marked as ${readableTarget}!`);
                handleStatusTransition(appId, targetStatus);
            } else {
                showToast('danger', data.error || 'Failed to update status.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }
        })
        .catch(err => {
            console.error(err);
            showToast('danger', 'Network error.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        });
    };

    // -------------------------------------------------------------
    // ACTION 1: START PROCESSING (Pending -> In Process)
    // -------------------------------------------------------------
    window.startProcessing = function (appId, btn) {
        if (!confirm('Start processing application #' + appId + '?')) return;

        btn.disabled = true;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        const fd = new FormData();
        fd.append('application_id', appId);
        fd.append('status', 'IN_PROCESS');
        fd.append('admin_remarks', 'Admin started processing');

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Application #' + appId + ' marked as In Process.');
                handleStatusTransition(appId, 'IN_PROCESS');
            } else {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                showToast('danger', data.error || 'Failed to update status.');
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = origHtml;
            showToast('danger', 'Network error.');
        });
    };

    // -------------------------------------------------------------
    // ACTION 2: APPROVE APPLICATION (with optional file) & POST-APPROVAL UPLOAD
    // -------------------------------------------------------------
    window.openUploadApproveModal = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        const f = req.formData || {};

        document.getElementById('uploadModalAppId').value = req.id;
        document.getElementById('uploadModalStatusInput').value = 'APPROVED';
        document.getElementById('uploadModalAppRef').textContent = req.app_ref || ('WB-' + req.id);
        document.getElementById('uploadModalApplicant').textContent = f.applicant_name || req.applicant_name;
        document.getElementById('uploadModalAppNo').textContent = f.application_number || req.applicant_identifier;
        document.getElementById('uploadModalRemarks').value = req.admin_remarks || '';

        document.getElementById('approveModalTitle').textContent = 'Approve Application';
        document.getElementById('approveModalIcon').className = 'bi bi-check-circle-fill';
        document.getElementById('btnSubmitApproveText').textContent = 'Approve';
        document.getElementById('uploadFileLabel').textContent = 'Result File (Optional)';

        clearUploadFile();
        new bootstrap.Modal(document.getElementById('modalUploadApprove')).show();
    };

    window.openUploadResultModal = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        const f = req.formData || {};

        document.getElementById('uploadModalAppId').value = req.id;
        document.getElementById('uploadModalStatusInput').value = 'APPROVED';
        document.getElementById('uploadModalAppRef').textContent = req.app_ref || ('WB-' + req.id);
        document.getElementById('uploadModalApplicant').textContent = f.applicant_name || req.applicant_name;
        document.getElementById('uploadModalAppNo').textContent = f.application_number || req.applicant_identifier;
        document.getElementById('uploadModalRemarks').value = req.admin_remarks || '';

        document.getElementById('approveModalTitle').textContent = 'Upload Result File';
        document.getElementById('approveModalIcon').className = 'bi bi-cloud-arrow-up-fill';
        document.getElementById('btnSubmitApproveText').textContent = 'Save Result';
        document.getElementById('uploadFileLabel').textContent = req.result_file ? 'Replace Result File (Optional)' : 'Select Result File';

        clearUploadFile();
        new bootstrap.Modal(document.getElementById('modalUploadApprove')).show();
    };

    const fileInput = document.getElementById('uploadResultFileInput');
    fileInput.addEventListener('change', function () {
        if (!this.files || !this.files[0]) {
            clearUploadFile();
            return;
        }
        const file = this.files[0];
        const ext = file.name.split('.').pop().toLowerCase();
        const allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!allowed.includes(ext)) {
            showToast('danger', 'Invalid file type (. ' + ext + '). Only PDF, JPG, and PNG are allowed.');
            clearUploadFile();
            return;
        }
        if (file.size > 15 * 1024 * 1024) {
            showToast('danger', 'File size exceeds maximum 15MB limit.');
            clearUploadFile();
            return;
        }

        const nameLabel = document.getElementById('uploadFileNameLabel');
        if (nameLabel) nameLabel.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        const previewContainer = document.getElementById('uploadPreviewContainer');
        const previewBody = document.getElementById('uploadPreviewBody');
        if (previewContainer) previewContainer.classList.remove('d-none');

        const objUrl = URL.createObjectURL(file);
        if (previewBody) {
            if (ext === 'pdf') {
                previewBody.innerHTML = `<iframe src="${objUrl}" class="w-100 border rounded" style="height: 180px;"></iframe>`;
            } else {
                previewBody.innerHTML = `<img src="${objUrl}" class="img-fluid rounded border shadow-2xs" style="max-height: 180px; object-fit: contain;">`;
            }
        }
    });

    window.clearUploadFile = function () {
        fileInput.value = '';
        const nameLabel = document.getElementById('uploadFileNameLabel');
        if (nameLabel) nameLabel.textContent = '';
        const previewContainer = document.getElementById('uploadPreviewContainer');
        if (previewContainer) previewContainer.classList.add('d-none');
        const previewBody = document.getElementById('uploadPreviewBody');
        if (previewBody) previewBody.innerHTML = '';
    };

    document.getElementById('uploadApproveForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitApprove');
        const isPostUpload = document.getElementById('approveModalTitle').textContent.includes('Upload Result');

        // If file selected, validate type and size
        if (fileInput.files && fileInput.files[0]) {
            const file = fileInput.files[0];
            const ext = file.name.split('.').pop().toLowerCase();
            const allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!allowed.includes(ext)) {
                showToast('danger', 'Invalid file type (. ' + ext + '). Only PDF, JPG, and PNG are allowed.');
                return;
            }
            if (file.size > 15 * 1024 * 1024) {
                showToast('danger', 'File size exceeds maximum 15MB limit.');
                return;
            }
        }

        btn.disabled = true;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        const fd = new FormData(this);

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = origHtml;

            if (data.success) {
                const appId = document.getElementById('uploadModalAppId').value;
                const successMsg = isPostUpload
                    ? (data.outputAttachmentUrl ? 'Result document uploaded successfully!' : 'Remarks updated successfully!')
                    : (data.outputAttachmentUrl ? 'Application approved with result file!' : 'Application approved successfully!');
                showToast('success', successMsg);

                const modalEl = document.getElementById('modalUploadApprove');
                const modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) modalInst.hide();

                handleStatusTransition(appId, 'APPROVED', data.outputAttachmentUrl);
            } else {
                showToast('danger', data.error || 'Failed to update application.');
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = origHtml;
            showToast('danger', 'Network error during request.');
        });
    });

    // -------------------------------------------------------------
    // ACTION 3: REJECT & REFUND
    // -------------------------------------------------------------
    window.openRejectModal = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        const f = req.formData || {};

        document.getElementById('rejectModalAppId').value = req.id;
        document.getElementById('rejectModalAppRef').textContent = req.app_ref || ('WB-' + req.id);
        document.getElementById('rejectModalApplicant').textContent = f.applicant_name || req.applicant_name;
        document.getElementById('rejectModalFee').textContent = '₹' + Number(req.fee_debited || 0).toFixed(2);
        document.getElementById('rejectModalReasonInput').value = '';

        new bootstrap.Modal(document.getElementById('modalRejectApp')).show();
    };

    window.setRejectReason = function (str) {
        document.getElementById('rejectModalReasonInput').value = str;
    };

    document.getElementById('rejectAppForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const reason = document.getElementById('rejectModalReasonInput').value.trim();
        if (!reason) {
            showToast('danger', 'Rejection reason is mandatory.');
            return;
        }

        const btn = document.getElementById('btnSubmitReject');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';

        const fd = new FormData(this);

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Reject';

            if (data.success) {
                showToast('success', 'Application rejected and fee refunded to retailer.');
                const modalEl = document.getElementById('modalRejectApp');
                const modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) modalInst.hide();
                const appId = document.getElementById('rejectModalAppId').value;
                handleStatusTransition(appId, 'REJECTED', null, reason);
            } else {
                showToast('danger', data.error || 'Failed to reject application.');
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Reject';
            showToast('danger', 'Network error during rejection.');
        });
    });

    // -------------------------------------------------------------
    // ACTION 4: VIEW REJECTION REASON MODAL
    // -------------------------------------------------------------
    window.viewRejectionReason = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        document.getElementById('viewReasonText').textContent = req.rejection_reason || req.admin_remarks || req.remarks || 'Reason not specified.';
        new bootstrap.Modal(document.getElementById('modalRejectionReason')).show();
    };

    // -------------------------------------------------------------
    // ACTION 5: VIEW RESULT FILE (LIGHTBOX)
    // -------------------------------------------------------------
    window.viewResultFile = function (url, ref) {
        if (!url) return;
        document.getElementById('resultLightboxTitle').textContent = 'Result Document - ' + (ref || '');
        document.getElementById('resultDownloadDirect').href = url;
        const body = document.getElementById('resultLightboxBody');

        const cleanUrl = url.split('?')[0].toLowerCase();
        if (cleanUrl.endsWith('.pdf')) {
            body.innerHTML = `<iframe src="${url}#toolbar=1" class="w-100 border-0 rounded-bottom-3" style="height: 75vh;"></iframe>`;
        } else {
            body.innerHTML = `<div class="text-center p-4"><img src="${url}" class="img-fluid rounded shadow-sm" style="max-height: 75vh; object-fit: contain;"></div>`;
        }

        new bootstrap.Modal(document.getElementById('modalResultLightbox')).show();
    };

    // -------------------------------------------------------------
    // ACTION 6: APPLICATION FULL DETAILS MODAL
    // -------------------------------------------------------------
    window.openDetailsModal = function (appId) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;
        const req = JSON.parse(row.getAttribute('data-req'));
        const f = req.formData || {};

        document.getElementById('detailsModalReqNum').textContent = req.id;
        document.getElementById('detailsModalService').textContent = req.service_title || 'Block & District Application Pass Service';
        document.getElementById('detailsModalFee').textContent = '₹' + Number(req.fee_debited || 0).toFixed(2);

        const statusEl = document.getElementById('detailsModalStatus');
        statusEl.textContent = req.status;
        statusEl.className = 'badge ' + (req.status === 'APPROVED' ? 'badge-status-approved' : (req.status === 'REJECTED' ? 'badge-status-rejected' : (req.status === 'IN_PROCESS' ? 'badge-status-in-process' : 'badge-status-pending')));

        const tbody = document.getElementById('detailsModalTableBody');
        tbody.innerHTML = '';

        addDetailRow(tbody, 'APPLICATION REFERENCE', req.app_ref);
        if (req.batch_ref) addDetailRow(tbody, 'BATCH REFERENCE', req.batch_ref);
        addDetailRow(tbody, 'INTERNAL ID', '#' + req.id);
        addDetailRow(tbody, 'SUBMITTED AT', new Date(req.created_at).toLocaleString('en-IN', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12:true}));
        addDetailRow(tbody, 'SERVICE NAME', req.service_title || 'Block & District Application Pass Service');
        addDetailRow(tbody, 'RETAILER NAME', req.retailer_name);
        if (req.retailer_mobile) addDetailRow(tbody, 'RETAILER MOBILE', req.retailer_mobile);
        if (req.center_name) addDetailRow(tbody, 'SHOP / CENTER', req.center_name);
        addDetailRow(tbody, 'APPLICANT NAME', f.applicant_name || req.applicant_name);
        addDetailRow(tbody, 'APPLICATION NUMBER', f.application_number || req.applicant_identifier);
        if (f.application_code) addDetailRow(tbody, 'APPLICATION CODE', f.application_code);
        addDetailRow(tbody, 'DISTRICT', req.district_name);
        addDetailRow(tbody, 'BLOCK', req.block_name);

        if (req.rejection_reason) {
            addDetailRow(tbody, 'REJECTION REASON', `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>${escapeHtml(req.rejection_reason)}</span>`);
        }
        if (req.admin_remarks || req.remarks) {
            addDetailRow(tbody, 'ADMIN REMARKS', req.admin_remarks || req.remarks);
        }
        if (req.result_file) {
            addDetailRow(tbody, 'RESULT DOCUMENT', `<div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-xs btn-outline-success py-1 px-2.5 rounded-pill fw-bold" onclick="viewResultFile('${req.result_file}', '${req.app_ref || req.id}')"><i class="bi bi-eye"></i> View Result</button>
                <a href="${req.result_file}" target="_blank" download class="btn btn-xs btn-success py-1 px-2.5 rounded-pill fw-bold"><i class="bi bi-download"></i> Download</a>
            </div>`);
        }

        // Dynamic Extra Fields
        Object.keys(f).forEach(key => {
            if (['applicant_name', 'application_number', 'district_name', 'block_name', 'mobile', 'application_code'].includes(key)) return;
            addDetailRow(tbody, key.replace(/_/g, ' ').toUpperCase(), f[key]);
        });

        new bootstrap.Modal(document.getElementById('modalAdminAppDetails')).show();
    };

    function addDetailRow(tbody, label, val) {
        const tr = document.createElement('tr');
        const th = document.createElement('th');
        th.style.width = '35%';
        th.textContent = label;
        const td = document.createElement('td');

        const strVal = String(val || '');
        if (strVal.startsWith('/uploads/') || strVal.endsWith('.pdf') || strVal.match(/\.(jpg|jpeg|png)$/i)) {
            td.innerHTML = `<a href="${strVal}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-file-earmark-arrow-down"></i> View File</a>`;
        } else if (strVal.startsWith('<span') || strVal.startsWith('<div')) {
            td.innerHTML = strVal;
        } else {
            td.textContent = strVal || '--';
        }

        tr.appendChild(th);
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    // -------------------------------------------------------------
    // ACTION 7: UPDATE DOM ROW ON STATUS CHANGE
    // -------------------------------------------------------------
    function updateRowState(appId, newStatus, resultFileUrl, rejectionReason) {
        const row = document.getElementById('admin-row-' + appId);
        if (!row) return;

        row.setAttribute('data-status', newStatus);
        const req = JSON.parse(row.getAttribute('data-req'));
        req.status = newStatus;
        req.display_status = newStatus;
        if (resultFileUrl) req.result_file = resultFileUrl;
        if (rejectionReason) req.rejection_reason = rejectionReason;
        if (newStatus === 'REJECTED') req.is_refunded = 1;
        row.setAttribute('data-req', JSON.stringify(req));

        // Update Status Column
        const statusCol = document.getElementById('row-status-col-' + appId);
        let badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        let statusLabel = 'PENDING';
        let iconHtml = '<span class="pulse-dot me-1"></span>';
        if (newStatus === 'IN_PROCESS' || newStatus === 'UNDER_REVIEW') {
            badgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
            statusLabel = 'PROCESSING';
            iconHtml = '<i class="bi bi-arrow-repeat spin-icon me-1"></i>';
        } else if (newStatus === 'APPROVED' || newStatus === 'COMPLETED') {
            badgeClass = 'bg-success-subtle text-success border border-success-subtle';
            statusLabel = 'COMPLETED';
            iconHtml = '<i class="bi bi-check2-circle me-1"></i>';
        } else if (newStatus === 'REJECTED') {
            badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
            statusLabel = 'REJECTED';
            iconHtml = '<i class="bi bi-x-circle me-1"></i>';
        }

        statusCol.innerHTML = `
            <span class="badge status-pill ${badgeClass} px-2.5 py-1 fw-bold">
                ${iconHtml}${statusLabel}
            </span>
            ${newStatus === 'REJECTED' ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block mt-1 font-monospace" style="font-size: 0.62rem; width: fit-content;">Refunded</span>' : ''}
        `;

        // Update Actions Column
        const actionCol = document.getElementById('row-action-col-' + appId);
        let actionButtons = '';
        if (newStatus === 'PENDING') {
            actionButtons = `
                <button type="button" class="btn btn-xs btn-primary fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(${appId}, 'IN_PROCESS', this)" title="1-Click: Mark In Process">
                    <i class="bi bi-lightning-charge-fill me-0.5"></i>Process
                </button>
                <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2 py-1 shadow-2xs" onclick="quickUpdateStatus(${appId}, 'APPROVED', this)" title="1-Click: Approve">
                    <i class="bi bi-check-circle-fill me-0.5"></i>Approve
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-1.5 py-1" onclick="openRejectModal(${appId})" title="Reject Application">
                    <i class="bi bi-x-circle"></i>
                </button>
            `;
        } else if (newStatus === 'IN_PROCESS' || newStatus === 'UNDER_REVIEW') {
            actionButtons = `
                <button type="button" class="btn btn-xs btn-success fw-bold rounded-2 px-2.5 py-1 shadow-2xs" onclick="quickUpdateStatus(${appId}, 'APPROVED', this)" title="1-Click: Approve">
                    <i class="bi bi-check-circle-fill me-0.5"></i>Approve
                </button>
                <button type="button" class="btn btn-xs btn-outline-primary fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadResultModal(${appId})" title="Upload File & Approve">
                    <i class="bi bi-cloud-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-2 py-1" onclick="openRejectModal(${appId})" title="Reject Application">
                    Reject
                </button>
            `;
        } else if (newStatus === 'APPROVED' || newStatus === 'COMPLETED') {
            const finalFile = resultFileUrl || req.result_file;
            if (finalFile) {
                actionButtons = `
                    <button type="button" class="btn btn-xs btn-outline-success fw-bold rounded-2 px-2 py-1" onclick="viewResultFile('${finalFile}', '${req.app_ref || ('WB-' + appId)}')">
                        <i class="bi bi-file-earmark-check me-0.5"></i>Result
                    </button>
                    <a href="${finalFile}" target="_blank" download class="btn btn-xs btn-success fw-semibold rounded-2 px-1.5 py-1" title="Download Result">
                        <i class="bi bi-download"></i>
                    </a>
                `;
            }
            actionButtons += `
                <button type="button" class="btn btn-xs btn-light border fw-semibold rounded-2 px-1.5 py-1" onclick="openUploadResultModal(${appId})" title="Upload or Replace Certificate">
                    <i class="bi bi-cloud-arrow-up text-primary"></i>
                </button>
            `;
        } else if (newStatus === 'REJECTED') {
            actionButtons = `
                <button type="button" class="btn btn-xs btn-outline-danger fw-semibold rounded-2 px-2 py-1" onclick="viewRejectionReason(${appId})">
                    Reason
                </button>
            `;
        }

        actionCol.innerHTML = `
            <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                ${actionButtons}
                <button type="button" class="btn btn-xs btn-outline-warning fw-semibold rounded-2 px-2.5 py-1 text-decoration-none btn-more-details" onclick="openDetailsModal(${appId})">
                    More Details
                </button>
                <button type="button" class="btn btn-xs btn-light border py-1 px-1.5 rounded-2 text-primary btn-chat-icon" title="Retailer Chat" onclick="adminOpenChat(${appId})">
                    <i class="bi bi-chat-dots-fill"></i>
                </button>
            </div>
        `;
    }

    // -------------------------------------------------------------
    // ACTION 8: RETAILER CHAT MODAL
    // -------------------------------------------------------------
    let adminChatInterval = null;
    window.adminOpenChat = function (appId) {
        document.getElementById('adminChatModalReqNum').textContent = appId;
        document.getElementById('adminChatAppId').value = appId;
        const win = document.getElementById('adminChatMessagesWindow');
        win.innerHTML = '<div class="text-center text-muted small py-3"><div class="spinner-border spinner-border-sm me-1"></div> Loading...</div>';

        fetchAdminChat(appId);
        if (adminChatInterval) clearInterval(adminChatInterval);
        adminChatInterval = setInterval(() => fetchAdminChat(appId), 4000);

        new bootstrap.Modal(document.getElementById('modalAdminChat')).show();
    };

    document.getElementById('modalAdminChat').addEventListener('hidden.bs.modal', function () {
        if (adminChatInterval) {
            clearInterval(adminChatInterval);
            adminChatInterval = null;
        }
    });

    document.getElementById('adminChatSendForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const appId = document.getElementById('adminChatAppId').value;
        const input = document.getElementById('adminChatInputMessage');
        const text = input.value.trim();
        if (!text) return;

        input.value = '';
        fetch('/api/requests/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ application_id: appId, message: text })
        })
        .then(res => res.json())
        .then(() => fetchAdminChat(appId))
        .catch(err => console.error(err));
    });

    function fetchAdminChat(appId) {
        fetch('/api/requests/chat?application_id=' + encodeURIComponent(appId))
            .then(res => res.json())
            .then(data => {
                const win = document.getElementById('adminChatMessagesWindow');
                const messages = data.messages || [];
                if (messages.length === 0) {
                    win.innerHTML = '<div class="text-center text-muted small py-4">No messages yet. Send a message to communicate with the retailer.</div>';
                    return;
                }
                win.innerHTML = '';
                messages.forEach(m => {
                    const isSelf = (m.sender_role === 'ADMIN' || m.sender_role === 'OPERATOR');
                    const div = document.createElement('div');
                    div.className = `chat-msg ${isSelf ? 'chat-msg-sent' : 'chat-msg-recv'}`;
                    div.innerHTML = `
                        <div>${escapeHtml(m.message)}</div>
                        <div class="chat-meta text-${isSelf ? 'white-50' : 'muted'}">${escapeHtml(m.sender_name)} &bull; ${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                    `;
                    win.appendChild(div);
                });
                win.scrollTop = win.scrollHeight;
            })
            .catch(err => console.error(err));
    }

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

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
