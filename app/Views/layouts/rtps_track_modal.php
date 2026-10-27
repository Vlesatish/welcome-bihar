<!-- Modal: RTPS ServicePlus Live Track, Rejection Sync & Auto Attach -->
<div class="modal fade" id="modalRtpsTrackAndAttach" tabindex="-1" aria-labelledby="modalRtpsTrackTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-gradient text-white py-3 px-4" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check fs-4"></i>
                    <div>
                        <h5 class="modal-title h6 fw-bold mb-0" id="modalRtpsTrackTitle">
                            Bihar RTPS ServicePlus — आवेदन की स्थिति एवं प्रमाण पत्र जांच
                        </h5>
                        <div class="small opacity-75" style="font-size: 0.72rem;">
                            Official Source: serviceonline.bihar.gov.in &bull; swcs.bihar.gov.in
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Top Overview Banner -->
                <div class="card border rounded-3 p-3 bg-light mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-sm-6">
                            <div class="text-muted small" style="font-size: 0.72rem;">Application Reference Number:</div>
                            <div class="d-flex align-items-center gap-1.5">
                                <span class="font-monospace fw-bold text-primary fs-6" id="rtpsModalRefNo">—</span>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" title="Copy" onclick="copyRtpsRef()">
                                    <i class="bi bi-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-sm-6 text-sm-end">
                            <div class="text-muted small" style="font-size: 0.72rem;">Applicant Name:</div>
                            <div class="fw-bold text-dark" id="rtpsModalApplicantName">—</div>
                        </div>
                    </div>
                    <div class="row g-2 align-items-center mt-2 pt-2 border-top">
                        <div class="col-sm-6">
                            <span class="small text-muted">Local Status:</span>
                            <span id="rtpsModalLocalStatus" class="badge bg-secondary ms-1">PENDING</span>
                        </div>
                        <div class="col-sm-6 text-sm-end">
                            <span class="small text-muted">Official Portal Status:</span>
                            <span id="rtpsModalPortalStatus" class="badge bg-info text-dark ms-1">Checking...</span>
                        </div>
                    </div>
                </div>

                <!-- 4-Step Stepper Progress Bar -->
                <div id="rtpsModalStepperContainer" class="mb-3"></div>

                <!-- Rejection Alert Box (if rejected) -->
                <div id="rtpsModalRejectionBox" class="alert alert-danger border border-danger-subtle d-none mb-3 p-3 rounded-3">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-octagon-fill text-danger fs-4 mt-0.5 flex-shrink-0"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                <h6 class="fw-bold text-danger mb-0">आवेदन अस्वीकृत (Application Rejected on Portal)</h6>
                                <span class="badge bg-danger text-white">3/4 Rej</span>
                            </div>
                            <div class="text-dark mt-1" id="rtpsModalRejectionReason" style="font-size: 0.85rem; line-height: 1.45;">—</div>
                            <!-- Task-wise remarks accordion -->
                            <div id="rtpsModalTaskRemarksContainer" class="mt-2 pt-2 border-top border-danger-subtle small d-none">
                                <div class="fw-semibold text-danger" style="font-size: 0.75rem;">विभागीय जांच टिप्पणी (Task Remarks):</div>
                                <div id="rtpsModalTaskRemarksList" class="mt-1 font-monospace" style="font-size: 0.75rem;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Message Box -->
                <div id="rtpsModalAlertBox" class="alert d-none mb-3 py-2 px-3 rounded-3" style="font-size: 0.82rem;"></div>

                <!-- Official ServiceOnline Tracking Form -->
                <div id="rtpsModalTrackingFormSection">
                    <div class="card border rounded-3 p-3 mb-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.85rem;">
                                <i class="bi bi-ui-checks text-primary me-1"></i> serviceonline.bihar.gov.in Official Verification
                            </h6>
                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.68rem;">Step-by-Step</span>
                        </div>

                        <div class="row g-2 mb-2">
                            <!-- Track Using -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Track Using (तिथि का प्रकार):</label>
                                <div class="d-flex gap-2">
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="rtps_track_using" id="trackUsingSubDate" value="1" checked onchange="onTrackUsingChange()">
                                        <label class="form-check-label small" for="trackUsingSubDate">Submission Date</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="rtps_track_using" id="trackUsingDelDate" value="2" onchange="onTrackUsingChange()">
                                        <label class="form-check-label small" for="trackUsingDelDate">Delivery Date</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Actual Date Picker -->
                            <div class="col-md-6">
                                <label for="rtpsApplyDate" class="form-label small fw-semibold text-dark mb-1">
                                    <span id="rtpsDateLabel">Application Submission Date (आवेदन तिथि)</span>:
                                </label>
                                <input type="text" id="rtpsApplyDate" class="form-control form-control-sm font-monospace" placeholder="DD/MM/YYYY" maxlength="10">
                                <div class="text-muted" style="font-size: 0.68rem;">Receipt par darj actual date chunein (DD/MM/YYYY)</div>
                            </div>
                        </div>

                        <!-- Verification Fields -->
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label for="rtpsFatherName" class="form-label small fw-semibold text-dark mb-1">Father's Name (पिता का नाम):</label>
                                <input type="text" id="rtpsFatherName" class="form-control form-control-sm" placeholder="Applicant Father's Name (Optional)">
                            </div>
                            <div class="col-md-6">
                                <label for="rtpsMotherName" class="form-label small fw-semibold text-dark mb-1">Mother's Name (माता का नाम):</label>
                                <input type="text" id="rtpsMotherName" class="form-control form-control-sm" placeholder="Applicant Mother's Name (Optional)">
                            </div>
                        </div>

                        <!-- CAPTCHA Section -->
                        <div class="p-2.5 rounded-3 bg-light border mt-2">
                            <label class="form-label small fw-bold text-dark mb-1.5 d-flex align-items-center justify-content-between">
                                <span><i class="bi bi-key-fill text-warning me-1"></i> Official Portal CAPTCHA:</span>
                                <span class="text-muted fw-normal" style="font-size: 0.7rem;">User manually enter karein</span>
                            </label>
                            <div class="row g-2 align-items-center">
                                <div class="col-sm-5 d-flex align-items-center gap-2">
                                    <div id="rtpsCaptchaImgWrapper" class="bg-white border rounded p-1 d-inline-flex align-items-center justify-content-center" style="min-width: 130px; height: 44px;">
                                        <div class="spinner-border spinner-border-sm text-secondary" id="rtpsCaptchaSpinner"></div>
                                        <img id="rtpsCaptchaImg" src="" alt="CAPTCHA" class="d-none" style="height: 38px; max-width: 140px;">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Refresh CAPTCHA" onclick="fetchLiveRtpsCaptcha()">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <div class="col-sm-7">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white text-muted"><i class="bi bi-123"></i></span>
                                        <input type="text" id="rtpsCaptchaAnswer" class="form-control font-monospace fw-bold text-center fs-6" placeholder="Enter 6 digits" maxlength="6" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions Grid -->
                        <div class="d-flex align-items-center justify-content-between gap-2 mt-3 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-success fw-bold px-3 py-1.5 rounded-pill" id="btnSwcsFastCheck" onclick="triggerFastSwcsCheck()">
                                <i class="bi bi-lightning-charge-fill me-1 text-warning"></i> ⚡ 1-Click Fast Check (SWCS Direct)
                            </button>

                            <button type="button" class="btn btn-sm btn-primary fw-bold px-4 py-1.5 rounded-pill shadow-sm" id="btnSubmitRtpsTrack" onclick="submitOfficialRtpsTracking()">
                                <i class="bi bi-arrow-repeat me-1"></i> Check Status & Auto Attach
                            </button>
                        </div>
                    </div>

                    <!-- Guided Official Popup & Manual Certificate Upload Fallback -->
                    <div class="accordion" id="accOfficialFallback">
                        <div class="accordion-item border rounded-3">
                            <h2 class="accordion-header" id="headingFallback">
                                <button class="accordion-button collapsed py-2 px-3 small fw-bold text-secondary bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFallback">
                                    <i class="bi bi-box-arrow-up-right me-2 text-primary"></i>
                                    Government Portal Popup & Manual Upload Fallback (वैकल्पिक विकल्प)
                                </button>
                            </h2>
                            <div id="collapseFallback" class="accordion-collapse collapse" data-bs-parent="#accOfficialFallback">
                                <div class="accordion-body p-3 bg-white small">
                                    <div class="alert alert-warning py-2 px-3 mb-2" style="font-size: 0.78rem;">
                                        <i class="bi bi-info-circle-fill me-1"></i>
                                        <strong>Browser Security Notice:</strong> Cross-origin security policy ke kaaran government portal popup ko website direct read nahi kar sakti. Agar portal par OTP verification ya additional check required ho, to niche diye direct link se check karein aur certificate PDF download karke yahan upload karein.
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                                        <a href="https://serviceonline.bihar.gov.in/officials/citizenApplication.html" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary fw-semibold">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Open serviceonline.bihar.gov.in in New Tab
                                        </a>
                                        <a href="https://swcs.bihar.gov.in/RtpsReportView/certificate_download.jsp" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary fw-semibold">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Open swcs.bihar.gov.in (Direct Download)
                                        </a>
                                    </div>

                                    <!-- Manual Certificate Upload Form -->
                                    <form id="formManualCertUpload" enctype="multipart/form-data" onsubmit="submitManualCertPdf(event)">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> Manual Certificate PDF Upload (प्रमाण पत्र अपलोड):
                                        </label>
                                        <div class="input-group input-group-sm mb-1">
                                            <input type="file" id="manualCertFile" name="result_file" class="form-control" accept=".pdf,application/pdf" required>
                                            <button type="submit" class="btn btn-success fw-bold" id="btnManualUploadSubmit">
                                                <i class="bi bi-upload me-1"></i> Upload & Complete
                                            </button>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.68rem;">Sirf valid RTPS Certificate PDF upload karein (Acknowledgment ya Receipt upload na karein).</div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="rtpsModalAppId" value="0">
                <input type="hidden" id="rtpsModalSessionId" value="">
            </div>

            <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
                <div class="small text-muted" style="font-size: 0.72rem;">
                    <i class="bi bi-clock-history me-1"></i> Next Automatic Scheduled Check: <strong>Har 4 Ghante</strong> me server-side check hota hai.
                </div>
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let currentAppId = 0;
    let pollInterval = null;
    let isPolling = false;

    // Detect role context (admin or user)
    function getApiPrefix() {
        return window.location.pathname.startsWith('/admin') ? '/admin/applications' : '/applications';
    }

    // Open RTPS Tracking Modal
    window.openRtpsTrackModal = function(appId, appNum, applicantName, customData = {}) {
        currentAppId = appId;
        document.getElementById('rtpsModalAppId').value = appId;
        document.getElementById('rtpsModalRefNo').textContent = appNum || '—';
        document.getElementById('rtpsModalApplicantName').textContent = applicantName || '—';

        // Clear alerts & inputs
        const alertBox = document.getElementById('rtpsModalAlertBox');
        alertBox.className = 'alert d-none mb-3 py-2 px-3 rounded-3';
        alertBox.innerHTML = '';
        document.getElementById('rtpsCaptchaAnswer').value = '';
        document.getElementById('rtpsModalRejectionBox').classList.add('d-none');
        document.getElementById('rtpsModalTaskRemarksContainer').classList.add('d-none');

        // Prepopulate dates
        const dateInput = document.getElementById('rtpsApplyDate');
        if (customData.sub_date) {
            dateInput.value = customData.sub_date;
        } else if (customData.created_at) {
            const d = new Date(customData.created_at);
            if (!isNaN(d.getTime())) {
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yyyy = d.getFullYear();
                dateInput.value = `${dd}/${mm}/${yyyy}`;
            }
        } else {
            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const yyyy = today.getFullYear();
            dateInput.value = `${dd}/${mm}/${yyyy}`;
        }

        if (customData.father_name) {
            document.getElementById('rtpsFatherName').value = customData.father_name;
        }
        if (customData.mother_name) {
            document.getElementById('rtpsMotherName').value = customData.mother_name;
        }

        // Fetch initial live status
        pollStatusOnce(appId);

        // Fetch fresh CAPTCHA
        fetchLiveRtpsCaptcha();

        const modalEl = document.getElementById('modalRtpsTrackAndAttach');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    };

    window.onTrackUsingChange = function() {
        const val = document.querySelector('input[name="rtps_track_using"]:checked').value;
        const lbl = document.getElementById('rtpsDateLabel');
        if (val === '2') {
            lbl.textContent = 'Application Delivery Date (सेवा प्रदान तिथि)';
        } else {
            lbl.textContent = 'Application Submission Date (आवेदन तिथि)';
        }
    };

    window.copyRtpsRef = function() {
        const text = document.getElementById('rtpsModalRefNo').textContent;
        if (text && text !== '—') {
            navigator.clipboard.writeText(text);
            if (typeof showToast === 'function') {
                showToast('success', 'Application reference copied: ' + text);
            } else {
                alert('Copied: ' + text);
            }
        }
    };

    // Fetch fresh CAPTCHA from ServiceOnline
    window.fetchLiveRtpsCaptcha = function() {
        const img = document.getElementById('rtpsCaptchaImg');
        const spinner = document.getElementById('rtpsCaptchaSpinner');
        img.classList.add('d-none');
        spinner.classList.remove('d-none');

        const prefix = getApiPrefix();
        fetch(prefix + '/rtps-captcha')
            .then(r => r.json())
            .then(data => {
                spinner.classList.add('d-none');
                if (data.success && data.captcha_image) {
                    img.src = data.captcha_image;
                    img.classList.remove('d-none');
                    document.getElementById('rtpsModalSessionId').value = data.session_id || '';
                } else {
                    showModalAlert('warning', 'Live CAPTCHA image load nahi ho payi. Aap 1-Click Fast Check use kar sakte hain.');
                }
            })
            .catch(() => {
                spinner.classList.add('d-none');
                showModalAlert('warning', 'Portal se CAPTCHA load karne me samay lag raha hai.');
            });
    };

    // 1-Click Fast Check (SWCS Direct)
    window.triggerFastSwcsCheck = function() {
        const btn = document.getElementById('btnSwcsFastCheck');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Fast Checking...';
        showModalAlert('info', '<span class="spinner-border spinner-border-sm me-1"></span> swcs.bihar.gov.in se instant check ho raha hai...');

        const fd = new FormData();
        fd.append('application_id', currentAppId);
        fd.append('force', '1');

        const prefix = getApiPrefix();
        fetch(prefix + '/auto-fetch', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = orig;
            handleTrackingResponse(data);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = orig;
            showModalAlert('danger', 'Error: ' + err.message);
        });
    };

    // Submit Official ServiceOnline Tracking with User CAPTCHA
    window.submitOfficialRtpsTracking = function() {
        const captchaAnswer = document.getElementById('rtpsCaptchaAnswer').value.trim();
        if (!captchaAnswer || captchaAnswer.length < 5) {
            showModalAlert('warning', 'Kripya official portal ka 6-digit CAPTCHA answer enter karein.');
            document.getElementById('rtpsCaptchaAnswer').focus();
            return;
        }

        const btn = document.getElementById('btnSubmitRtpsTrack');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying on Portal...';
        showModalAlert('info', '<span class="spinner-border spinner-border-sm me-1"></span> serviceonline.bihar.gov.in par tracking request verify ho rahi hai...');

        const fd = new FormData();
        fd.append('application_id', currentAppId);
        fd.append('track_using', document.querySelector('input[name="rtps_track_using"]:checked').value);
        fd.append('apply_date', document.getElementById('rtpsApplyDate').value.trim());
        fd.append('captcha_answer', captchaAnswer);
        fd.append('captcha_session_id', document.getElementById('rtpsModalSessionId').value);
        fd.append('father_name', document.getElementById('rtpsFatherName').value.trim());
        fd.append('mother_name', document.getElementById('rtpsMotherName').value.trim());
        fd.append('force', '1');

        const prefix = getApiPrefix();
        fetch(prefix + '/auto-fetch', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = orig;
            handleTrackingResponse(data);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = orig;
            showModalAlert('danger', 'Tracking communication error: ' + err.message);
        });
    };

    function handleTrackingResponse(data) {
        if (data.success && data.status === 'ATTACHED') {
            showModalAlert('success', '<i class="bi bi-check-circle-fill me-1"></i> <strong>Badhai ho!</strong> Official RTPS Certificate PDF download hokar application se attach ho gaya!');
            updateStepperDisplay(4, false);
            setTimeout(() => {
                window.location.reload();
            }, 1800);
            return;
        }

        if (data.status === 'REJECTED') {
            const reason = data.rejection_reason || data.message || 'Application rejected on portal';
            document.getElementById('rtpsModalRejectionBox').classList.remove('d-none');
            document.getElementById('rtpsModalRejectionReason').textContent = reason;
            document.getElementById('rtpsModalPortalStatus').className = 'badge bg-danger text-white ms-1';
            document.getElementById('rtpsModalPortalStatus').textContent = 'Rejected';
            document.getElementById('rtpsModalLocalStatus').className = 'badge bg-danger text-white ms-1';
            document.getElementById('rtpsModalLocalStatus').textContent = 'REJECTED';

            if (data.task_remarks && Object.keys(data.task_remarks).length > 0) {
                const remList = document.getElementById('rtpsModalTaskRemarksList');
                remList.innerHTML = '';
                for (const [tName, rem] of Object.entries(data.task_remarks)) {
                    remList.innerHTML += `<div><strong>${tName}:</strong> ${rem}</div>`;
                }
                document.getElementById('rtpsModalTaskRemarksContainer').classList.remove('d-none');
            }

            updateStepperDisplay(3, true, reason);
            showModalAlert('danger', 'Official portal par aavedan अस्वीकृत (Rejected) paya gaya: ' + reason);
            fetchLiveRtpsCaptcha();
            return;
        }

        if (data.conflict) {
            showModalAlert('warning', '<i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Conflict Detected:</strong> ' + data.message);
            return;
        }

        // In Process / Pending
        const pStatus = data.portal_status || 'Under Process';
        document.getElementById('rtpsModalPortalStatus').className = 'badge bg-primary text-white ms-1';
        document.getElementById('rtpsModalPortalStatus').textContent = pStatus;
        showModalAlert('info', '<i class="bi bi-info-circle-fill me-1"></i> ' + (data.message || 'Application is under process on portal.'));
        fetchLiveRtpsCaptcha();
    }

    function pollStatusOnce(appId) {
        const prefix = getApiPrefix();
        fetch(prefix + '/poll-status?id=' + encodeURIComponent(appId))
            .then(r => r.json())
            .then(data => {
                if (data.is_completed) {
                    document.getElementById('rtpsModalLocalStatus').className = 'badge bg-success text-white ms-1';
                    document.getElementById('rtpsModalLocalStatus').textContent = 'COMPLETED';
                    document.getElementById('rtpsModalPortalStatus').className = 'badge bg-success text-white ms-1';
                    document.getElementById('rtpsModalPortalStatus').textContent = 'Delivered';
                    updateStepperDisplay(4, false);
                } else if (data.is_rejected) {
                    document.getElementById('rtpsModalLocalStatus').className = 'badge bg-danger text-white ms-1';
                    document.getElementById('rtpsModalLocalStatus').textContent = 'REJECTED';
                    document.getElementById('rtpsModalPortalStatus').className = 'badge bg-danger text-white ms-1';
                    document.getElementById('rtpsModalPortalStatus').textContent = 'Rejected';
                    document.getElementById('rtpsModalRejectionBox').classList.remove('d-none');
                    document.getElementById('rtpsModalRejectionReason').textContent = data.rejection_reason || 'Rejected on official portal';
                    updateStepperDisplay(3, true, data.rejection_reason);
                } else {
                    document.getElementById('rtpsModalLocalStatus').className = 'badge bg-warning text-dark ms-1';
                    document.getElementById('rtpsModalLocalStatus').textContent = data.status || 'IN_PROCESS';
                    document.getElementById('rtpsModalPortalStatus').className = 'badge bg-info text-dark ms-1';
                    document.getElementById('rtpsModalPortalStatus').textContent = data.portal_status || 'Under Process';
                    updateStepperDisplay(2, false);
                }
            })
            .catch(() => {});
    }

    function updateStepperDisplay(step, isRej, reason = '') {
        const container = document.getElementById('rtpsModalStepperContainer');
        if (typeof window.renderStepperHtml === 'function') {
            container.innerHTML = window.renderStepperHtml(step, isRej, reason);
        }
    }

    function showModalAlert(type, html) {
        const box = document.getElementById('rtpsModalAlertBox');
        box.className = `alert alert-${type} mb-3 py-2 px-3 rounded-3`;
        box.innerHTML = html;
    }

    // Manual Certificate Upload Form Submit
    window.submitManualCertPdf = function(e) {
        e.preventDefault();
        const fileInput = document.getElementById('manualCertFile');
        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Kripya certificate PDF file chunein.');
            return;
        }

        const btn = document.getElementById('btnManualUploadSubmit');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...';

        const fd = new FormData();
        fd.append('application_id', currentAppId);
        fd.append('result_file', fileInput.files[0]);
        fd.append('status', 'COMPLETED');
        fd.append('remarks', 'Manual certificate PDF attached via RTPS tracking fallback');

        const uploadUrl = window.location.pathname.startsWith('/admin')
            ? '/admin/applications/upload'
            : '/applications/update-status';

        fetch(uploadUrl, {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = orig;
            if (data.success) {
                showModalAlert('success', '<i class="bi bi-check-circle-fill me-1"></i> Certificate PDF successfully uploaded and attached! Application completed.');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showModalAlert('danger', 'Upload error: ' + (data.message || data.error || 'Failed to attach PDF'));
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = orig;
            showModalAlert('danger', 'Upload network error: ' + err.message);
        });
    };
})();
</script>
