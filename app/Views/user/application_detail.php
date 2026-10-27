<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Top Action Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <a href="/applications" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Applications List
        </a>
        <h1 class="h3 fw-bold mb-1">
            <?= e(__('app_reference')) ?>: <span class="text-primary font-monospace"><?= e($app['app_ref']) ?></span>
        </h1>
        <div class="text-muted small">
            Submitted on <?= date('d M Y, h:i A', strtotime($app['created_at'])) ?> &bull; Service: <strong><?= e($app['dt_name_en']) ?></strong>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge-status badge-<?= strtolower($app['status']) ?> fs-6 py-2 px-3">
            <i class="bi bi-circle-fill fs-7 me-1"></i>
            <?= e(__('status_' . strtolower($app['status']))) ?>
        </span>
        <?= render_status_stepper_dots($app, true, 'fs-6 ms-1') ?>
        <button type="button"
                class="btn btn-primary btn-sm fw-bold rounded-pill px-3 shadow-sm"
                onclick="openRtpsTrackModal(<?= (int)$app['id'] ?>, '<?= e(addslashes($app['applicant_identifier'] ?? '')) ?>', '<?= e(addslashes($app['applicant_name'] ?? '')) ?>', {created_at: '<?= e($app['created_at']) ?>'})">
            <i class="bi bi-arrow-repeat me-1"></i> Check Status & Auto Attach
        </button>
        <button class="btn btn-outline-dark btn-sm btn-copy-clipboard" data-copy="<?= e($app['app_ref']) ?>" title="Copy Reference">
            <i class="bi bi-copy"></i>
        </button>
    </div>
</div>

<!-- 4-Step RTPS Tracking Stepper Card -->
<?= render_status_stepper_bar($app) ?>

<!-- Completed / Result File Banner for User -->
<?php if (in_array($app['status'], ['COMPLETED', 'APPROVED'], true) || !empty($app['result_file'])): ?>
    <div class="alert alert-success p-3 mb-4 rounded-3 shadow-sm border-success">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-success text-white rounded-circle">
                    <i class="bi bi-check2-circle fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1 text-success">Application Approved Successfully!</h5>
                    <div class="text-dark small">
                        <?= !empty($app['result_file']) ? 'The requested service certificate / acknowledgement slip has been generated and approved.' : 'Your application has been approved by administration.' ?>
                    </div>
                    <?php if (!empty($app['admin_remarks'])): ?>
                        <div class="small mt-1 text-secondary">
                            <strong>Official Remarks:</strong> <?= e($app['admin_remarks']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($app['result_file'])): ?>
                <div>
                    <a href="<?= e($app['result_file']) ?>" target="_blank" download class="btn btn-success fw-bold shadow-sm px-4 py-2">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download Certificate
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- RTPS Queue Active Banner when In Process -->
<?php if ($app['status'] === 'IN_PROCESS'): ?>
    <div class="alert alert-info p-3 mb-4 rounded-3 shadow-sm border-info-subtle bg-info-subtle text-info-emphasis">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-info text-white rounded-circle">
                    <i class="bi bi-clock-history fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1 text-info-emphasis">Application In Process — Automatic RTPS Tracking Active</h5>
                    <div class="text-dark small">
                        This application is tracked in the RTPS certificate queue. The system automatically checks the official portal every <strong>4 hours (Asia/Kolkata)</strong>.
                    </div>
                    <div class="small mt-1 text-secondary">
                        Application Number: <strong><?= e($app['applicant_identifier'] ?? $app['app_ref']) ?></strong> | Applicant: <strong><?= e($app['applicant_name']) ?></strong>
                    </div>
                </div>
            </div>
            <div>
                <span class="badge bg-primary text-white px-3 py-2 rounded-pill">
                    <i class="bi bi-arrow-repeat me-1"></i> Auto Check (Every 4 Hours / 7 AM)
                </span>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Admin Remarks Banner if not completed but remarks exist -->
<?php if ($app['status'] !== 'COMPLETED' && !empty($app['admin_remarks'])): ?>
    <div class="alert alert-info p-3 mb-4 rounded-3 shadow-sm border-info">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-chat-left-dots-fill fs-5 text-primary mt-1"></i>
            <div>
                <h6 class="fw-bold mb-1 text-dark">Official Remarks:</h6>
                <div class="text-dark small"><?= nl2br(e($app['admin_remarks'])) ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Rejection Reason Banner -->
<?php if ($app['status'] === 'REJECTED' && !empty($app['rejection_reason'])): ?>
    <div class="alert alert-danger p-3 mb-4 rounded-3 shadow-sm">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-x-circle-fill fs-4 text-danger mt-1"></i>
            <div>
                <h5 class="fw-bold mb-1"><?= e(__('rejection_reason')) ?></h5>
                <p class="mb-0 text-dark"><?= nl2br(e($app['rejection_reason'])) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Application Details & Field Values -->
    <div class="col-lg-8">
        <!-- Overview Card -->
        <div class="wb-card mb-4 shadow-sm">
            <div class="wb-card-header bg-light">
                <span class="fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Application Summary</span>
                <span class="badge bg-secondary-subtle text-secondary border">
                    <i class="bi bi-lock-fill me-1"></i> Locked Record
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold"><?= e(__('applicant_name')) ?></div>
                        <div class="fs-6 fw-bold text-dark mt-1"><?= e($app['applicant_name']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold"><?= e(__('applicant_mobile')) ?></div>
                        <div class="fs-6 fw-semibold text-dark mt-1">
                            <i class="bi bi-telephone text-primary me-1"></i><?= e($app['applicant_mobile']) ?>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold">Identifier (Aadhaar/Ration)</div>
                        <div class="fs-6 fw-bold font-monospace text-dark mt-1"><?= e($app['applicant_identifier'] ?: 'N/A') ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold"><?= e(__('district')) ?></div>
                        <div class="fs-6 fw-semibold text-dark mt-1"><?= e($app['district_name']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold"><?= e(__('block')) ?></div>
                        <div class="fs-6 fw-semibold text-dark mt-1"><?= e($app['block_name']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold">Fee Charged (Debited)</div>
                        <div class="fs-6 fw-bold text-danger mt-1">₹<?= number_format($app['fee_debited'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submitted Dynamic Form Fields Card -->
        <div class="wb-card shadow-sm">
            <div class="wb-card-header bg-light">
                <span class="fw-bold"><i class="bi bi-card-checklist me-2 text-primary"></i>Submitted Details & Uploaded Documents</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 35%;">Field Label</th>
                            <th>Submitted Value / Document</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fieldValues as $fv): ?>
                            <tr>
                                <td class="fw-semibold text-secondary">
                                    <?= e($fv['label_en']) ?>
                                </td>
                                <td>
                                    <?php if ($fv['field_type'] === 'file' && !empty($fv['field_value'])): 
                                        $fVal = (str_starts_with($fv['field_value'], 'http://') || str_starts_with($fv['field_value'], 'https://') || str_starts_with($fv['field_value'], '/'))
                                            ? $fv['field_value']
                                            : '/' . $fv['field_value'];
                                    ?>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <a href="<?= e($fVal) ?>" target="_blank" class="btn btn-sm btn-outline-secondary fw-semibold">
                                                <i class="bi bi-file-earmark-arrow-down me-1"></i> View Original
                                            </a>
                                            <?php 
                                                $fnLower = strtolower($fv['field_name'] . ' ' . ($fv['label_en'] ?? ''));
                                                if (str_contains($fnLower, 'photo')): ?>
                                                    <button type="button" class="btn btn-sm btn-primary fw-bold shadow-2xs" onclick="downloadUtiPhoto('<?= e($fVal) ?>', '<?= e(addslashes($app['applicant_name'])) ?>', this)">
                                                        <i class="bi bi-download me-1"></i> UTI Photo (213x213 @ 300 DPI)
                                                    </button>
                                                <?php elseif (str_contains($fnLower, 'signature') || str_contains($fnLower, 'sign')): ?>
                                                    <button type="button" class="btn btn-sm btn-danger fw-bold shadow-2xs" onclick="downloadUtiSignature('<?= e($fVal) ?>', '<?= e(addslashes($app['applicant_name'])) ?>', this)">
                                                        <i class="bi bi-download me-1"></i> UTI Signature (400x200 Fresh & Clean)
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="openUtiCroppingTool(null, '<?= e($fVal) ?>', '<?= e(addslashes($app['applicant_name'])) ?>')">
                                                        <i class="bi bi-sliders me-1"></i> PSA Crop Tool
                                                    </button>
                                                <?php endif; ?>
                                            <span class="small text-muted font-monospace"><?= e(basename($fv['field_value'])) ?></span>
                                        </div>
                                    <?php elseif ($fv['field_type'] === 'textarea'): ?>
                                        <div class="fw-semibold text-dark"><?= nl2br(e($fv['field_value'])) ?></div>
                                    <?php else: ?>
                                        <div class="fw-bold text-dark"><?= e($fv['field_value'] ?: '-') ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Status History & Review Actions -->
    <div class="col-lg-4">
        <!-- Review Action Box (For Admins and Permitted Distributors on non-terminal applications) -->
        <?php if ($canReview && !in_array($app['status'], ['REJECTED', 'COMPLETED'], true)): ?>
            <div class="wb-card border-primary shadow-sm mb-4">
                <div class="wb-card-header bg-primary text-white">
                    <span class="fw-bold"><i class="bi bi-check2-circle me-1"></i> Review & Action Panel</span>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="/applications/update-status" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Select Target Status <span class="text-danger">*</span></label>
                            <select name="status" id="review_status_select" class="form-select" required onchange="handleStatusChange(this.value)">
                                <option value="">-- Choose Action --</option>
                                <?php if ($currentUser['role'] === 'ADMIN'): ?>
                                    <?php if ($app['status'] === 'PENDING'): ?>
                                        <option value="UNDER_REVIEW">Mark Under Review</option>
                                    <?php endif; ?>
                                    <option value="APPROVED">Approve (In-Progress)</option>
                                    <option value="COMPLETED">Complete & Deliver Result</option>
                                    <option value="REJECTED">Reject (Refund Fee)</option>
                                <?php elseif ($currentUser['role'] === 'DISTRIBUTOR'): ?>
                                    <?php if ($app['status'] === 'PENDING'): ?>
                                        <option value="UNDER_REVIEW">Mark Under Review</option>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Result Document Upload (Visible when COMPLETED or APPROVED) -->
                        <div class="mb-3" id="result_upload_group" style="display: none;">
                            <label class="form-label fw-bold small text-success">
                                <i class="bi bi-file-earmark-check me-1"></i> Attach Result Document / Certificate (PDF/JPG)
                            </label>
                            <input type="file" name="result_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text small">Upload the issued certificate or acknowledgement receipt.</div>
                        </div>

                        <!-- Mandatory Rejection Reason -->
                        <div class="mb-3" id="rejection_reason_group" style="display: none;">
                            <label class="form-label fw-bold small text-danger">
                                Mandatory Rejection Reason <span class="text-danger">*</span>
                            </label>
                            <textarea name="rejection_reason" id="rejection_reason_input" class="form-control" rows="3"
                                      placeholder="Provide detailed explanation for rejection..."><?= e($app['rejection_reason']) ?></textarea>
                            <div class="form-text small text-danger">A clear reason is mandatory for rejection audit & user refund.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Official Remarks (Visible to User)</label>
                            <textarea name="admin_remarks" class="form-control" rows="2" placeholder="e.g. Certificate generated successfully, collection available at center."><?= e($app['admin_remarks'] ?: $app['remarks']) ?></textarea>
                        </div>

                        <button type="submit" id="btn-confirm-action" class="btn btn-primary w-100 fw-bold">
                            <i class="bi bi-save me-1"></i> Update Status & Notify User
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Submitter Kiosk Info -->
        <div class="wb-card mb-4 shadow-sm">
            <div class="wb-card-header bg-light">
                <span class="fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Operator / Submitter Details</span>
            </div>
            <div class="card-body p-3">
                <div class="fw-bold text-dark"><?= e($app['operator_name']) ?></div>
                <div class="small text-muted mb-2"><i class="bi bi-telephone me-1"></i><?= e($app['operator_mobile']) ?></div>
                <div class="small text-secondary mb-1">
                    <strong>Center:</strong> <?= e($app['center_name'] ?: 'N/A') ?>
                </div>
                <?php if (!empty($app['distributor_name'])): ?>
                    <div class="small text-secondary">
                        <strong>Distributor:</strong> <?= e($app['distributor_name']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status History Timeline -->
        <div class="wb-card shadow-sm">
            <div class="wb-card-header bg-light">
                <span class="fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Status Timeline</span>
            </div>
            <div class="card-body p-3">
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge-status badge-<?= strtolower($h['to_status']) ?>">
                                        <?= e($h['to_status']) ?>
                                    </span>
                                    <span class="small text-muted" style="font-size: 0.72rem;">
                                        <?= date('d M, h:i A', strtotime($h['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="small text-secondary fw-semibold">
                                    By: <?= e($h['actor_name']) ?> (<?= e($h['actor_role']) ?>)
                                </div>
                                <?php if (!empty($h['remarks'])): ?>
                                    <div class="small text-muted mt-1 fst-italic">"<?= e($h['remarks']) ?>"</div>
                                <?php endif; ?>
                                <?php if (!empty($h['rejection_reason'])): ?>
                                    <div class="small text-danger mt-1 fw-bold">Reason: <?= e($h['rejection_reason']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function handleStatusChange(status) {
    const rejGrp = document.getElementById('rejection_reason_group');
    const rejInput = document.getElementById('rejection_reason_input');
    const resGrp = document.getElementById('result_upload_group');

    if (rejGrp && rejInput) {
        if (status === 'REJECTED') {
            rejGrp.style.display = 'block';
            rejInput.setAttribute('required', 'required');
        } else {
            rejGrp.style.display = 'none';
            rejInput.removeAttribute('required');
        }
    }

    if (resGrp) {
        if (status === 'COMPLETED' || status === 'APPROVED') {
            resGrp.style.display = 'block';
        } else {
            resGrp.style.display = 'none';
        }
    }
}
</script>

<?php require dirname(__DIR__) . '/layouts/rtps_track_modal.php'; ?>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
