<div class="modal fade" id="modalAdminAppDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2.5 px-4 bg-light rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0">
                    <i class="bi bi-card-text text-primary me-2"></i>Application Details #<span id="detailsModalReqNum"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-2 mb-3 d-flex flex-wrap justify-content-between gap-2 small">
                    <div><strong>Service:</strong> <span id="detailsModalService">--</span></div>
                    <div><strong>Fee Charged:</strong> <span id="detailsModalFee" class="fw-bold text-primary">₹0.00</span></div>
                    <div><strong>Status:</strong> <span id="detailsModalStatus" class="badge">--</span></div>
                </div>
                <div class="table-responsive border rounded-2">
                    <table class="table table-sm table-striped mb-0 small">
                        <tbody id="detailsModalTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-4 bg-light rounded-bottom-3">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: APPROVE APPLICATION / UPLOAD RESULT
     ========================================================================= -->
<div class="modal fade" id="modalUploadApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2 px-3 bg-light rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0 text-success d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill" id="approveModalIcon"></i>
                    <span id="approveModalTitle">Approve Application</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="uploadApproveForm" enctype="multipart/form-data">
                <div class="modal-body p-3 space-y-3">
                    <input type="hidden" name="application_id" id="uploadModalAppId" value="">
                    <input type="hidden" name="status" id="uploadModalStatusInput" value="APPROVED">

                    <!-- Compact Application Details -->
                    <div class="p-2.5 bg-light rounded-2 small mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Application Ref:</span>
                            <strong class="font-monospace text-dark" id="uploadModalAppRef">--</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Applicant Name:</span>
                            <strong class="text-dark" id="uploadModalApplicant">--</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Application Number:</span>
                            <span class="font-monospace fw-bold text-dark" id="uploadModalAppNo">--</span>
                        </div>
                    </div>

                    <!-- Small File Picker with Label "Result File (Optional)" and no asterisk -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1 d-flex justify-content-between align-items-center">
                            <span id="uploadFileLabel">Result File (Optional)</span>
                            <span class="text-muted fw-normal" style="font-size: 0.72rem;">PDF, JPG, PNG (Max 15MB)</span>
                        </label>
                        <input type="file" name="result_file" id="uploadResultFileInput" class="form-control form-control-sm rounded-2" accept=".pdf,.jpg,.jpeg,.png">
                    </div>

                    <!-- Live Preview Container -->
                    <div id="uploadPreviewContainer" class="d-none border rounded-2 p-2 bg-light mb-3 text-center">
                        <div class="small text-muted mb-1 fw-semibold d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-file-earmark-check text-success me-1"></i> Preview: <span id="uploadFileNameLabel"></span></span>
                            <button type="button" class="btn btn-xs btn-link text-danger p-0" onclick="clearUploadFile()">Remove File</button>
                        </div>
                        <div id="uploadPreviewBody" style="max-height: 180px; overflow: hidden;"></div>
                    </div>

                    <!-- Optional Remarks -->
                    <div class="mb-1">
                        <label class="form-label fw-bold small text-dark mb-1">Remarks for Retailer (Optional)</label>
                        <input type="text" name="admin_remarks" id="uploadModalRemarks" class="form-control form-control-sm rounded-2" placeholder="e.g. Approved and certificate issued.">
                    </div>
                </div>

                <div class="modal-footer border-top py-2 px-3 bg-light rounded-bottom-3">
                    <button type="button" class="btn btn-secondary btn-sm rounded-2 px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitApprove" class="btn btn-success btn-sm rounded-2 px-4 fw-bold shadow-2xs">
                        <i class="bi bi-check-circle-fill me-1"></i> <span id="btnSubmitApproveText">Approve</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: REJECT APPLICATION (MANDATORY REASON + REFUND)
     ========================================================================= -->
<div class="modal fade" id="modalRejectApp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2.5 px-4 bg-light rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0 text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-x-circle-fill"></i>
                    <span>Reject Application</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectAppForm">
                <div class="modal-body p-4 space-y-3">
                    <input type="hidden" name="application_id" id="rejectModalAppId" value="">
                    <input type="hidden" name="status" value="REJECTED">

                    <!-- Summary Box -->
                    <div class="p-2.5 bg-light rounded-2 small space-y-1 mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Application Ref:</span>
                            <strong class="font-monospace" id="rejectModalAppRef">--</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Applicant:</span>
                            <strong id="rejectModalApplicant">--</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Refund Amount:</span>
                            <strong class="text-success small font-monospace" id="rejectModalFee">₹0.00</strong>
                        </div>
                    </div>

                    <!-- Preset Reason Chips -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-dark mb-1">Quick Reasons:</label>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill" onclick="setRejectReason('Documents incomplete / blurred')">Incomplete Docs</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill" onclick="setRejectReason('Invalid application number on government portal')">Invalid App No.</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill" onclick="setRejectReason('Applicant name or details mismatch')">Details Mismatch</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill" onclick="setRejectReason('Duplicate submission / already generated')">Duplicate</button>
                        </div>
                    </div>

                    <!-- Mandatory Reason Textarea -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger mb-1">
                            Rejection Reason (Mandatory) <span class="text-danger">*</span>
                        </label>
                        <textarea name="rejection_reason" id="rejectModalReasonInput" class="form-control form-control-sm rounded-2" rows="3" placeholder="Enter reason for rejection..." required></textarea>
                    </div>

                    <!-- Refund Notice -->
                    <div class="alert alert-warning py-1.5 px-3 small rounded-2 mb-0 d-flex align-items-center gap-2" style="font-size: 0.72rem;">
                        <i class="bi bi-wallet2 text-warning fs-6"></i>
                        <span>Fee debited will be <strong>automatically refunded</strong> to retailer wallet.</span>
                    </div>
                </div>

                <div class="modal-footer border-top py-2.5 px-4 bg-light rounded-bottom-3">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitReject" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-2xs">
                        <i class="bi bi-x-circle me-1"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: VIEW REJECTION REASON MODAL
     ========================================================================= -->
<div class="modal fade" id="modalRejectionReason" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2 px-3 bg-danger text-white rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Rejection Reason</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 text-center">
                <div class="p-2.5 bg-danger-subtle text-danger border border-danger-subtle rounded-2 small fw-semibold text-start mb-2.5" id="viewReasonText">
                    --
                </div>
                <div class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2.5 rounded-pill small" style="font-size: 0.72rem;">
                    <i class="bi bi-check-circle me-1"></i> Fee Refunded to Retailer
                </div>
            </div>
            <div class="modal-footer border-top py-1.5 px-3 bg-light rounded-bottom-3 justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 5: RESULT DOCUMENT LIGHTBOX
     ========================================================================= -->
<div class="modal fade" id="modalResultLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-3 border-0 shadow overflow-hidden">
            <div class="modal-header border-bottom py-2.5 px-4 bg-dark text-white">
                <h5 class="modal-title h6 fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check-fill text-success"></i>
                    <span id="resultLightboxTitle">Result Document</span>
                </h5>
                <div class="d-flex gap-2">
                    <a href="#" id="resultDownloadDirect" download target="_blank" class="btn btn-xs btn-success rounded-pill px-3 fw-bold">
                        <i class="bi bi-download me-1"></i> Download
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary-subtle" id="resultLightboxBody" style="min-height: 480px; max-height: 80vh; overflow: auto;">
                <!-- Injected dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 6: RETAILER CHAT
     ========================================================================= -->
<div class="modal fade" id="modalAdminChat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-bottom py-2.5 px-4 bg-primary text-white rounded-top-3">
                <h5 class="modal-title h6 fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span>Retailer Chat (#<span id="adminChatModalReqNum"></span>)</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="adminChatMessagesWindow" class="chat-window mb-3" style="max-height: 320px; overflow-y: auto;"></div>
                <form id="adminChatSendForm" class="d-flex gap-2">
                    <input type="hidden" id="adminChatAppId" value="">
                    <input type="text" id="adminChatInputMessage" class="form-control form-control-sm rounded-pill" placeholder="Type reply to retailer..." required autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT: REALTIME CLIENT & FILTER CONTROLLER -->
