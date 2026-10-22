
<!-- Reassign Operator Modal (Admin only) -->
<div class="modal fade" id="reassignModal" tabindex="-1" aria-labelledby="reassignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="/admin/applications/reassign" class="modal-content" id="reassignForm">
            <?= csrf_field() ?>
            <input type="hidden" name="application_id" id="reassign_app_id" value="">
            <input type="hidden" name="redirect_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/applications') ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="reassignModalLabel">
                    <i class="bi bi-person-gear text-primary me-2"></i>Reassign Staff Operator
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold mb-1">Application</label>
                    <div id="reassign_app_label" class="fw-bold text-dark font-monospace"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Active Operator <span class="text-danger">*</span></label>
                    <select name="operator_id" id="reassign_operator_id" class="form-select" required>
                        <option value="">-- Loading eligible operators... --</option>
                    </select>
                    <small class="text-muted">Must be an active OPERATOR assigned to the same District & Block.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reassignment Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Explain reason for reassignment (e.g., Staff on leave, load balancing)..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="bi bi-check-circle me-1"></i> Confirm Reassign
                </button>
            </div>
        </form>
    </div>
</div>

<script>
async function openReassignModal(appId, districtId, blockId, appRef, currentOpId) {
    document.getElementById('reassign_app_id').value = appId;
    document.getElementById('reassign_app_label').textContent = appRef;
    const select = document.getElementById('reassign_operator_id');
    select.innerHTML = '<option value="">Loading eligible operators...</option>';
    select.disabled = true;

    const modal = new bootstrap.Modal(document.getElementById('reassignModal'));
    modal.show();

    try {
        const res = await fetch(`/admin/staff-routing/api-block-data?district_id=${districtId}&block_id=${blockId}`);
        const data = await res.json();
        if (data.success && data.operators && data.operators.length > 0) {
            select.innerHTML = '<option value="">-- Select Active Operator --</option>';
            data.operators.forEach(op => {
                const isSelected = (parseInt(op.id) === currentOpId) ? ' (Current)' : '';
                select.innerHTML += `<option value="${op.id}">${op.name} (${op.mobile})${isSelected}</option>`;
            });
            select.disabled = false;
        } else {
            select.innerHTML = '<option value="">No other active operators available for this block</option>';
        }
    } catch (e) {
        select.innerHTML = '<option value="">Failed to load operators</option>';
    }
}
</script>

<!-- =========================================================================
     MODAL 1: COMPLETE APPLICATION DETAILS (Matching Operator View + Full Admin Controls)
     ========================================================================= -->
<div class="modal fade" id="modalAdminAppDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom p-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-folder-check text-primary fs-5"></i>
                    <div>
                        <h5 class="h6 fw-bold text-dark mb-0" id="detailModalTitle">Application Details</h5>
                        <span class="text-muted small" id="detailModalSubtitle">#WB-...</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Status & Action Banner -->
                <div class="p-2.5 rounded-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border bg-light" id="detailStatusBanner">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small fw-semibold">Current Status:</span>
                        <span class="badge px-2.5 py-1 rounded-pill fw-bold" id="detailStatusBadge">--</span>
                    </div>
                    <div id="detailActionButtons" class="d-flex gap-1.5"></div>
                </div>

                <!-- Rejection Banner if rejected -->
                <div class="alert alert-danger p-2.5 rounded-3 mb-3 small d-none" id="detailRejectionAlert">
                    <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                    <strong>Rejection Reason:</strong> <span id="detailRejectionReasonText">--</span>
                    <div class="mt-1 text-danger fw-semibold" id="detailRefundInfoText"></div>
                </div>

                <div class="row g-3 mb-3">
                    <!-- Applicant Info -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <h6 class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-person text-primary me-1"></i> Applicant Information</h6>
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr>
                                    <td class="text-muted ps-0" style="width: 130px;">Applicant Name:</td>
                                    <td class="fw-bold text-dark" id="detailApplicantName">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Mobile Number:</td>
                                    <td class="fw-bold text-dark" id="detailApplicantMobile">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Application No:</td>
                                    <td class="font-monospace fw-bold text-primary" id="detailAppNumber">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Application Code:</td>
                                    <td><span class="badge bg-primary-subtle text-primary border" id="detailAppCode">--</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Location & Retailer Info -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <h6 class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-geo-alt text-primary me-1"></i> Location & Retailer</h6>
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr>
                                    <td class="text-muted ps-0" style="width: 120px;">District:</td>
                                    <td class="fw-bold text-dark" id="detailDistrict">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Block:</td>
                                    <td class="fw-bold text-dark" id="detailBlock">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Submitted By:</td>
                                    <td class="fw-bold text-dark" id="detailRetailer">--</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Service Fee:</td>
                                    <td class="fw-bold text-success font-monospace" id="detailFee">--</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Attached Citizen Documents & Extra Fields -->
                <div class="mb-3">
                    <h6 class="small fw-bold text-dark text-uppercase border-bottom pb-1 mb-2">
                        <i class="bi bi-paperclip text-primary me-1"></i> Submitted Fields & Documents
                    </h6>
                    <div id="detailFieldsContainer" class="p-2 bg-light rounded-3 border small">
                        <!-- Dynamic fields list -->
                    </div>
                </div>

                <!-- Result Certificate Attachment -->
                <div class="mb-3 d-none" id="detailResultContainer">
                    <h6 class="small fw-bold text-dark text-uppercase border-bottom pb-1 mb-2">
                        <i class="bi bi-file-earmark-check text-success me-1"></i> Approved Certificate / Result Document
                    </h6>
                    <div class="p-2.5 bg-success-subtle border border-success-subtle rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-4"></i>
                            <div>
                                <strong class="small text-dark d-block" id="detailResultFileName">Certificate.pdf</strong>
                                <span class="text-muted" style="font-size: 0.72rem;" id="detailResultRemarks">Official output delivered</span>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-xs btn-outline-success py-1 px-2.5 rounded-pill fw-bold" id="btnPreviewResultModal">
                                <i class="bi bi-eye me-1"></i> Preview
                            </button>
                            <a href="#" download id="btnDownloadResultModal" class="btn btn-xs btn-success py-1 px-3 rounded-pill fw-bold">
                                <i class="bi bi-download me-1"></i> Download
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Status History Timeline -->
                <div>
                    <h6 class="small fw-bold text-dark text-uppercase border-bottom pb-1 mb-2">
                        <i class="bi bi-clock-history text-primary me-1"></i> Status History Trail
                    </h6>
                    <div id="detailHistoryContainer" class="p-2 bg-light rounded-3 border small" style="max-height: 160px; overflow-y: auto;">
                        <!-- Status history items -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-2.5 d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2" id="detailFooterActions">
                    <!-- Dynamic footer actions -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: UPLOAD & APPROVE (Optional File Upload Flow)
     ========================================================================= -->
<div class="modal fade" id="modalUploadApprove" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-4 border-0 shadow-lg p-3">
            <div class="modal-header border-bottom pb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-arrow-up-fill text-success fs-5"></i>
                    <h5 class="h6 fw-bold text-dark mb-0">Upload Result & Approve Application</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUploadApprove" enctype="multipart/form-data">
                <input type="hidden" name="application_id" id="upload_app_id" value="">
                <input type="hidden" name="status" value="APPROVED">

                <div class="modal-body py-3">
                    <div class="alert alert-light border small mb-3 p-2">
                        <div>Applicant: <strong id="uploadApplicantName">--</strong></div>
                        <div>Application No: <span class="font-monospace fw-bold text-primary" id="uploadAppNumber">--</span></div>
                        <div class="text-muted" style="font-size: 0.72rem;" id="uploadLocation">--</div>
                    </div>

                    <!-- 1-Click Auto Fetch from Bihar RTPS Portal -->
                    <div class="card border-primary bg-primary-subtle p-3 mb-3 rounded-3 shadow-sm">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="fw-bold text-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-magic fs-5"></i> 1-Click Auto Download from Bihar RTPS
                                </div>
                                <div class="text-secondary small mt-0.5">
                                    Bihar Government portal se official signed PDF automatic download aur attach karein.
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-1.5 shadow-sm text-nowrap" id="btnModalAutoFetch" onclick="triggerAutoFetchFromModal()">
                                <i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Fetch & Attach
                            </button>
                        </div>
                        <div id="modalAutoFetchAlert" class="mt-2 small d-none"></div>
                    </div>

                    <div class="text-center my-3 position-relative">
                        <hr class="my-0 text-muted">
                        <span class="bg-white px-2 text-muted fw-bold small position-relative" style="top: -10px; font-size: 0.72rem;">YA MANUALLY FILE UPLOAD KAREIN</span>
                    </div>

                    <div class="mb-3">
                        <label for="uploadResultFile" class="form-label small fw-bold text-dark">Result Certificate Document (PDF / JPG / PNG)</label>
                        <input type="file" class="form-control form-control-sm" id="uploadResultFile" name="result_file" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text" style="font-size: 0.7rem;">Upload official certificate generated from RTPS/Government Portal (Max 15MB).</div>
                    </div>

                    <!-- File preview card -->
                    <div id="uploadFilePreviewBox" class="p-2 border rounded-3 bg-light mb-3 d-none d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-pdf text-danger fs-4" id="uploadPreviewIcon"></i>
                        <div class="flex-grow-1 overflow-hidden">
                            <strong class="d-block text-truncate small" id="uploadPreviewName">filename.pdf</strong>
                            <span class="text-muted" style="font-size: 0.7rem;" id="uploadPreviewMeta">0 KB</span>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="uploadRemarks" class="form-label small fw-bold text-dark">Admin Remarks (Optional)</label>
                        <input type="text" class="form-control form-control-sm" id="uploadRemarks" name="admin_remarks" placeholder="e.g. Certificate issued successfully">
                    </div>
                </div>

                <div class="modal-footer border-top pt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-4 fw-bold" id="btnSubmitUploadApprove">
                        <i class="bi bi-check-circle-fill me-1"></i> Save & Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: REJECT APPLICATION (Quick & Reliable)
     ========================================================================= -->
<div class="modal fade" id="modalRejectApp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 shadow-lg p-2">
            <div class="modal-header border-bottom pb-2">
                <div class="d-flex align-items-center gap-1.5 text-danger">
                    <i class="bi bi-x-circle-fill fs-5"></i>
                    <h5 class="h6 fw-bold mb-0">Reject Application</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRejectApp">
                <input type="hidden" name="application_id" id="reject_app_id" value="">
                <input type="hidden" name="status" value="REJECTED">

                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">Select or enter rejection reason. Full fee will be automatically refunded to retailer wallet.</p>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1">Common Reasons</label>
                        <select class="form-select form-select-sm" id="rejectReasonPreset" onchange="if(this.value) document.getElementById('rejectReasonText').value = this.value;">
                            <option value="">-- Choose Reason Preset --</option>
                            <option value="Application Number not found on RTPS portal">Application Number not found on RTPS portal</option>
                            <option value="Applicant details / Name mismatch">Applicant details / Name mismatch</option>
                            <option value="Attached documents are blurry or incomplete">Attached documents are blurry or incomplete</option>
                            <option value="Duplicate submission already processed">Duplicate submission already processed</option>
                            <option value="Application already rejected at Government Block level">Application already rejected at Government Block level</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="rejectReasonText" class="form-label small fw-bold text-dark mb-1">Specific Reason *</label>
                        <textarea class="form-control form-control-sm" id="rejectReasonText" name="rejection_reason" rows="2" placeholder="Explain rejection reason..." required>Application details mismatch or incomplete documentation.</textarea>
                    </div>
                </div>

                <div class="modal-footer border-top pt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold" id="btnConfirmReject">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: RESULT DOCUMENT LIGHTBOX PREVIEW
     ========================================================================= -->
<div class="modal fade" id="modalResultLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white p-3 border-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check-fill text-success fs-5"></i>
                    <h5 class="h6 fw-bold mb-0 text-white" id="lightboxTitle">Result Document</h5>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <a href="#" download id="lightboxDownloadBtn" class="btn btn-xs btn-outline-light rounded-pill px-3 fw-bold">
                        <i class="bi bi-download me-1"></i> Download File
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary bg-opacity-10 text-center" id="lightboxBody" style="min-height: 500px;">
                <!-- Iframe or Image dynamically placed -->
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT: ONE-CLICK STATUS UPDATES & MODALS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalDetails = new bootstrap.Modal(document.getElementById('modalAdminAppDetails'));
    const modalUpload = new bootstrap.Modal(document.getElementById('modalUploadApprove'));
    const modalReject = new bootstrap.Modal(document.getElementById('modalRejectApp'));
    const modalLightbox = new bootstrap.Modal(document.getElementById('modalResultLightbox'));

    // Dynamic Block Cascading in Filter Form
    const distSelect = document.getElementById('filter_district_id');
    const blkSelect = document.getElementById('filter_block_id');
    const isOperatorFilter = <?= !empty($isOperator) ? 'true' : 'false' ?>;
    const defaultBlockText = isOperatorFilter ? 'All Assigned Blocks' : 'All Blocks';

    if (distSelect && blkSelect) {
        distSelect.addEventListener('change', function () {
            const distId = this.value;
            if (!distId && !isOperatorFilter) {
                blkSelect.innerHTML = '<option value="">' + defaultBlockText + '</option>';
                blkSelect.disabled = true;
                return;
            }
            blkSelect.innerHTML = '<option value="">Loading blocks...</option>';
            blkSelect.disabled = true;
            fetch('/api/blocks?district_id=' + encodeURIComponent(distId || 0))
                .then(res => res.json())
                .then(data => {
                    blkSelect.innerHTML = '<option value="">' + defaultBlockText + '</option>';
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.id || b.block_id;
                            opt.textContent = b.name || b.block_name;
                            blkSelect.appendChild(opt);
                        });
                        blkSelect.disabled = false;
                    } else {
                        blkSelect.disabled = !isOperatorFilter && !distId;
                    }
                })
                .catch(() => {
                    blkSelect.innerHTML = '<option value="">' + defaultBlockText + '</option>';
                    blkSelect.disabled = false;
                });
        });
    }

    // 1. ONE-CLICK INSTANT STATUS UPDATE
    window.quickUpdateStatus = function (appId, targetStatus, btn = null) {
        let origHtml = '';
        if (btn) {
            origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';
        }

        fetch('/api/requests/update-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                application_id: appId,
                status: targetStatus,
                adminRemarks: targetStatus === 'APPROVED' ? 'Approved by administrator' : 'Processing started by administrator'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const readableTarget = targetStatus === 'APPROVED' ? 'Approved' : (targetStatus === 'IN_PROCESS' ? 'In Process' : targetStatus);
                showToast('success', `Application #${appId} marked as ${readableTarget}!`);
                setTimeout(() => window.location.reload(), 400);
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
            showToast('danger', 'Network error while updating status.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        });
    };

    // 2. OPEN REJECT MODAL
    window.openRejectModal = function (appId) {
        document.getElementById('reject_app_id').value = appId;
        modalReject.show();
    };

    // Reject Form Submit
    document.getElementById('formRejectApp').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnConfirmReject');
        const appId = document.getElementById('reject_app_id').value;
        const reason = document.getElementById('rejectReasonText').value.trim();

        if (!reason) {
            showToast('danger', 'Rejection reason is required.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';

        fetch('/api/requests/update-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                application_id: appId,
                status: 'REJECTED',
                rejectionReason: reason,
                adminRemarks: reason
            })
        })
        .then(res => res.json())
        .then(data => {
            modalReject.hide();
            if (data.success) {
                showToast('success', 'Application marked as REJECTED & fee refunded.');
                setTimeout(() => window.location.reload(), 450);
            } else {
                showToast('danger', data.error || 'Failed to reject application.');
                btn.disabled = false;
                btn.textContent = 'Confirm Rejection';
            }
        })
        .catch(() => {
            showToast('danger', 'Network error.');
            btn.disabled = false;
            btn.textContent = 'Confirm Rejection';
        });
    });

    // 3. OPEN UPLOAD & APPROVE MODAL
    window.openUploadApproveModal = function (appId) {
        const row = document.getElementById('row-app-' + appId);
        if (!row) return;
        const app = JSON.parse(row.dataset.app);

        document.getElementById('upload_app_id').value = appId;
        document.getElementById('uploadApplicantName').textContent = app.applicant_name || '--';
        document.getElementById('uploadAppNumber').textContent = app.applicant_identifier || '#' + app.app_ref;
        document.getElementById('uploadLocation').textContent = `${app.district_name} > ${app.block_name}`;
        document.getElementById('uploadResultFile').value = '';
        document.getElementById('uploadFilePreviewBox').classList.add('d-none');

        // Reset 1-Click Auto Fetch button & alert box
        const alertBox = document.getElementById('modalAutoFetchAlert');
        if (alertBox) {
            alertBox.className = 'mt-2 small d-none';
            alertBox.innerHTML = '';
        }
        const afBtn = document.getElementById('btnModalAutoFetch');
        if (afBtn) {
            afBtn.disabled = false;
            afBtn.innerHTML = '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Fetch & Attach';
        }

        modalUpload.show();
    };

    // 1-Click Auto Fetch from Modal
    window.triggerAutoFetchFromModal = function () {
        const appId = document.getElementById('upload_app_id').value;
        const btn = document.getElementById('btnModalAutoFetch');
        const alertBox = document.getElementById('modalAutoFetchAlert');
        if (!appId) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Fetching from RTPS...';
        alertBox.className = 'mt-2 small alert alert-info p-2 mb-0 d-flex align-items-center gap-1.5';
        alertBox.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span> Bihar Government RTPS portal se certificate verify aur download ho raha hai, kripya prateeksha karein...';

        const formData = new FormData();
        formData.append('application_id', appId);

        fetch('/admin/rtps-queue/auto-fetch', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alertBox.className = 'mt-2 small alert alert-success p-2 mb-0';
                alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + (data.message || 'Certificate attach ho gaya! Application completed.');
                setTimeout(() => {
                    modalUpload.hide();
                    window.location.reload();
                }, 1200);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Fetch & Attach';
                alertBox.className = 'mt-2 small alert alert-warning p-2 mb-0';
                alertBox.innerHTML = '<i class="bi bi-info-circle me-1"></i> ' + (data.message || 'Certificate abhi portal par generate nahi hua hai.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Fetch & Attach';
            alertBox.className = 'mt-2 small alert alert-danger p-2 mb-0';
            alertBox.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Error: ' + err.message;
        });
    };

    // 1-Click Auto Fetch from Table Action Button
    window.autoFetchAndAttachRtps = async function (appId, btn) {
        if (!appId) return;
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Fetching...';
        }

        try {
            const formData = new FormData();
            formData.append('application_id', appId);

            const res = await fetch('/admin/rtps-queue/auto-fetch', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Certificate Attached!',
                        text: data.message || 'Official certificate PDF downloaded and attached successfully!',
                        timer: 1800,
                        showConfirmButton: false
                    });
                } else if (typeof showToast === 'function') {
                    showToast('success', data.message || 'Certificate attached successfully!');
                }
                setTimeout(() => window.location.reload(), 1200);
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'RTPS Portal Notice',
                        text: data.message || 'Certificate not ready yet on RTPS portal. Next check scheduled at 12:00 AM.',
                        confirmButtonColor: '#0284c7'
                    });
                } else if (typeof showToast === 'function') {
                    showToast('warning', data.message || 'Certificate pending on portal.');
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            }
        } catch (e) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Communication Error',
                    text: e.message || 'Failed to connect to server.'
                });
            } else if (typeof showToast === 'function') {
                showToast('danger', 'Error: ' + e.message);
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    };

    // File Preview Handler in Upload Modal
    const uploadInput = document.getElementById('uploadResultFile');
    if (uploadInput) {
        uploadInput.addEventListener('change', function () {
            const file = this.files[0];
            const pBox = document.getElementById('uploadFilePreviewBox');
            if (file) {
                document.getElementById('uploadPreviewName').textContent = file.name;
                document.getElementById('uploadPreviewMeta').textContent = `${(file.size / 1024).toFixed(1)} KB • ${file.type || 'file'}`;
                pBox.classList.remove('d-none');
            } else {
                pBox.classList.add('d-none');
            }
        });
    }

    // Submit Upload & Approve Form
    document.getElementById('formUploadApprove').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitUploadApprove');
        const formData = new FormData(this);

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading & Approving...';

        fetch('/api/requests/update-status', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            modalUpload.hide();
            if (data.success) {
                showToast('success', 'Application approved and certificate saved!');
                setTimeout(() => window.location.reload(), 450);
            } else {
                showToast('danger', data.error || 'Failed to approve application.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Save & Approve';
            }
        })
        .catch(() => {
            showToast('danger', 'Network error during upload.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Save & Approve';
        });
    });

    // 4. OPEN FULL DETAILS MODAL (Matching Operator View + Full Admin Controls)
    window.openDetailsModal = function (appId) {
        const row = document.getElementById('row-app-' + appId);
        if (!row) return;
        const app = JSON.parse(row.dataset.app);

        document.getElementById('detailModalTitle').textContent = `Application #${app.app_ref}`;
        document.getElementById('detailModalSubtitle').textContent = app.applicant_identifier ? `${app.applicant_identifier} • Ref: #${app.app_ref}` : `#${app.app_ref}`;

        document.getElementById('detailApplicantName').textContent = app.applicant_name || '--';
        document.getElementById('detailApplicantMobile').textContent = app.applicant_mobile || '--';
        document.getElementById('detailAppNumber').textContent = app.applicant_identifier || '--';
        document.getElementById('detailAppCode').textContent = app.application_code || (app.applicant_identifier ? app.applicant_identifier.split('/')[0] : '--');

        document.getElementById('detailDistrict').textContent = app.district_name || '--';
        document.getElementById('detailBlock').textContent = app.block_name || '--';
        document.getElementById('detailRetailer').textContent = app.retailer_name ? `${app.retailer_name} (${app.retailer_mobile || ''})` : '--';
        document.getElementById('detailFee').textContent = '₹' + Number(app.fee_debited || 0).toFixed(2);

        // Status badge
        const badgeEl = document.getElementById('detailStatusBadge');
        badgeEl.textContent = app.status;
        if (app.status === 'PENDING') {
            badgeEl.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill fw-bold';
        } else if (app.status === 'IN_PROCESS' || app.status === 'UNDER_REVIEW') {
            badgeEl.className = 'badge bg-info-subtle text-info-emphasis border border-info-subtle px-2.5 py-1 rounded-pill fw-bold';
        } else if (app.status === 'APPROVED' || app.status === 'COMPLETED') {
            badgeEl.className = 'badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-bold';
        } else if (app.status === 'REJECTED') {
            badgeEl.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill fw-bold';
        }

        // Rejection banner
        const rejAlert = document.getElementById('detailRejectionAlert');
        if (app.status === 'REJECTED') {
            document.getElementById('detailRejectionReasonText').textContent = app.rejection_reason || app.admin_remarks || 'Rejected by administrator';
            document.getElementById('detailRefundInfoText').textContent = app.is_refunded ? `✓ Full fee of ₹${Number(app.fee_debited).toFixed(2)} refunded to retailer.` : 'Refund recorded.';
            rejAlert.classList.remove('d-none');
        } else {
            rejAlert.classList.add('d-none');
        }

        // Action Buttons inside banner
        const actionBox = document.getElementById('detailActionButtons');
        actionBox.innerHTML = '';
        if (app.status === 'PENDING') {
            actionBox.innerHTML = `
                <button type="button" class="btn btn-xs btn-primary py-1 px-3 rounded-pill fw-bold shadow-2xs" onclick="modalDetails.hide(); quickUpdateStatus(${app.id}, 'IN_PROCESS')">
                    <i class="bi bi-lightning-fill me-1"></i> 1-Click Process
                </button>
                <button type="button" class="btn btn-xs btn-success py-1 px-3 rounded-pill fw-bold shadow-2xs" onclick="modalDetails.hide(); quickUpdateStatus(${app.id}, 'APPROVED')">
                    <i class="bi bi-check-circle-fill me-1"></i> 1-Click Approve
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2.5 rounded-pill fw-semibold" onclick="modalDetails.hide(); openRejectModal(${app.id})">
                    <i class="bi bi-x-circle me-1"></i> Reject
                </button>
            `;
        } else if (app.status === 'IN_PROCESS' || app.status === 'UNDER_REVIEW') {
            actionBox.innerHTML = `
                <button type="button" class="btn btn-xs btn-success py-1 px-3 rounded-pill fw-bold shadow-2xs" onclick="modalDetails.hide(); quickUpdateStatus(${app.id}, 'APPROVED')">
                    <i class="bi bi-check-circle-fill me-1"></i> 1-Click Approve
                </button>
                <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2.5 rounded-pill fw-semibold" onclick="modalDetails.hide(); openUploadApproveModal(${app.id})">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2.5 rounded-pill fw-semibold" onclick="modalDetails.hide(); openRejectModal(${app.id})">
                    <i class="bi bi-x-circle me-1"></i> Reject
                </button>
            `;
        }

        // Dynamic Submitted Fields
        const fBox = document.getElementById('detailFieldsContainer');
        if (app.fields && Object.keys(app.fields).length > 0) {
            let html = '<div class="row g-2">';
            Object.entries(app.fields).forEach(([k, f]) => {
                const isUrl = String(f.value).startsWith('/uploads/') || String(f.value).startsWith('http');
                html += `
                    <div class="col-6 col-md-4">
                        <span class="text-muted d-block" style="font-size: 0.72rem;">${escapeHtml(f.label || k)}:</span>
                        ${isUrl
                            ? `<a href="${escapeHtml(f.value)}" target="_blank" class="fw-bold text-primary text-truncate d-inline-block" style="max-width: 100%;"><i class="bi bi-file-earmark me-1"></i>View Document</a>`
                            : `<strong class="text-dark text-truncate d-block">${escapeHtml(f.value)}</strong>`
                        }
                    </div>
                `;
            });
            html += '</div>';
            fBox.innerHTML = html;
        } else {
            fBox.innerHTML = '<span class="text-muted">Standard form fields recorded.</span>';
        }

        // Result Container
        const resContainer = document.getElementById('detailResultContainer');
        if (app.result_file) {
            document.getElementById('detailResultFileName').textContent = app.result_file.split('/').pop();
            document.getElementById('detailResultRemarks').textContent = app.admin_remarks || 'Official pass certificate delivered';
            document.getElementById('btnDownloadResultModal').href = app.result_file;
            document.getElementById('btnPreviewResultModal').onclick = () => openResultLightbox(app.result_file);
            resContainer.classList.remove('d-none');
        } else {
            resContainer.classList.add('d-none');
        }

        // History Trail
        const histBox = document.getElementById('detailHistoryContainer');
        if (app.history && app.history.length > 0) {
            histBox.innerHTML = app.history.map(h => `
                <div class="border-bottom py-1 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.65rem;">${h.from_status} &rarr; ${h.to_status}</span>
                        <span class="ms-1 fw-bold text-dark">${escapeHtml(h.actor_name)} (${h.actor_role})</span>
                        ${h.remarks ? `<span class="text-muted ms-1">&bull; ${escapeHtml(h.remarks)}</span>` : ''}
                        ${h.rejection_reason ? `<span class="text-danger ms-1 fw-semibold">&bull; Reason: ${escapeHtml(h.rejection_reason)}</span>` : ''}
                    </div>
                    <span class="text-muted font-monospace" style="font-size: 0.68rem;">${h.created_at}</span>
                </div>
            `).join('');
        } else {
            histBox.innerHTML = `<span class="text-muted">Submitted on: ${app.created_at}</span>`;
        }

        modalDetails.show();
    };

    // 5. LIGHTBOX PREVIEW
    window.openResultLightbox = function (url) {
        if (!url) return;
        document.getElementById('lightboxDownloadBtn').href = url;
        const body = document.getElementById('lightboxBody');
        const cleanUrl = url.split('?')[0].toLowerCase();

        if (cleanUrl.endsWith('.pdf')) {
            body.innerHTML = `<iframe src="${url}#toolbar=1" class="w-100 border-0 rounded-bottom-4" style="height: 75vh;"></iframe>`;
        } else {
            body.innerHTML = `<div class="p-4 text-center"><img src="${url}" class="img-fluid rounded shadow-sm" style="max-height: 75vh; object-fit: contain;"></div>`;
        }

        modalLightbox.show();
    };

    // 6. COPY UTILITY
    window.copyText = function (text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check text-success" style="font-size: 0.75rem;"></i>';
            setTimeout(() => { btn.innerHTML = orig; }, 1500);
        });
    };

    // 7. TOAST NOTIFICATION HELPER
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
