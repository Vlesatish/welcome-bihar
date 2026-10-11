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
            Submitted on <?= date('d M Y, h:i A', strtotime($app['created_at'])) ?> &bull; Service: <strong><?= e($app['dt_name_en'] ?: $app['dt_name_hi']) ?></strong>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-status badge-<?= strtolower($app['status']) ?> fs-6 py-2 px-3">
            <i class="bi bi-circle-fill fs-7 me-1"></i>
            <?= e(__('status_' . strtolower($app['status']))) ?>
        </span>
        <button class="btn btn-outline-dark btn-sm btn-copy-clipboard" data-copy="<?= e($app['app_ref']) ?>" title="Copy Reference">
            <i class="bi bi-copy"></i>
        </button>
    </div>
</div>

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
        <div class="wb-card mb-4">
            <div class="wb-card-header">
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
                        <div class="text-muted small text-uppercase fw-semibold"><?= e(__('fee_amount')) ?></div>
                        <div class="fs-6 fw-bold text-danger mt-1">₹<?= number_format($app['fee_debited'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submitted Dynamic Form Fields Card -->
        <div class="wb-card">
            <div class="wb-card-header">
                <span class="fw-bold"><i class="bi bi-card-checklist me-2 text-primary"></i>Submitted Details</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Field Label</th>
                            <th>Submitted Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fieldValues as $fv): ?>
                            <tr>
                                <td class="fw-semibold text-secondary">
                                    <?= e($fv['label_en'] ?: $fv['label_hi']) ?>
                                </td>
                                <td class="fw-bold text-dark">
                                    <?php if ($fv['field_type'] === 'textarea'): ?>
                                        <?= nl2br(e($fv['field_value'])) ?>
                                    <?php else: ?>
                                        <?= e($fv['field_value']) ?>
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
        <!-- Review Action Box (For Admins and Permitted Distributors) -->
        <?php if ($canReview): ?>
            <div class="wb-card border-primary shadow-sm mb-4">
                <div class="wb-card-header bg-primary text-white">
                    <span class="fw-bold"><i class="bi bi-check2-circle me-1"></i> Review &amp; Action Panel</span>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="/applications/update-status">
                        <?= csrf_field() ?>
                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Select Target Status <span class="text-danger">*</span></label>
                            <select name="status" id="review_status_select" class="form-select" required onchange="toggleRejectionReason(this.value)">
                                <option value="">-- Choose Action --</option>
                                <?php if ($currentUser['role'] === 'ADMIN'): ?>
                                    <option value="APPROVED" <?= $app['status'] === 'APPROVED' ? 'selected' : '' ?>>Approve</option>
                                <?php endif; ?>
                                <option value="UNDER_REVIEW" <?= $app['status'] === 'UNDER_REVIEW' ? 'selected' : '' ?>>Under Review</option>
                                <option value="REJECTED" <?= $app['status'] === 'REJECTED' ? 'selected' : '' ?>>Reject</option>
                            </select>
                        </div>

                        <!-- Mandatory Rejection Reason -->
                        <div class="mb-3" id="rejection_reason_group" style="display: <?= $app['status'] === 'REJECTED' ? 'block' : 'none' ?>;">
                            <label class="form-label fw-bold small text-danger">
                                Mandatory Rejection Reason <span class="text-danger">*</span>
                            </label>
                            <textarea name="rejection_reason" id="rejection_reason_input" class="form-control" rows="3"
                                      placeholder="Provide detailed explanation for rejection..."><?= e($app['rejection_reason']) ?></textarea>
                            <div class="form-text small text-danger">A clear reason is mandatory for rejection audit.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Reviewer Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Optional internal note..."><?= e($app['remarks']) ?></textarea>
                        </div>

                        <button type="submit" id="btn-confirm-reject" class="btn btn-primary w-100 fw-bold">
                            <i class="bi bi-save me-1"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Submitter Kiosk Info -->
        <div class="wb-card mb-4">
            <div class="wb-card-header">
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
        <div class="wb-card">
            <div class="wb-card-header">
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
function toggleRejectionReason(status) {
    const grp = document.getElementById('rejection_reason_group');
    if (grp) {
        if (status === 'REJECTED') {
            grp.style.display = 'block';
            document.getElementById('rejection_reason_input').setAttribute('required', 'required');
        } else {
            grp.style.display = 'none';
            document.getElementById('rejection_reason_input').removeAttribute('required');
        }
    }
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
