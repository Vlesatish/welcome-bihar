<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-sliders text-primary fs-5"></i>
        <h1 class="h5 fw-bold mb-0 text-dark">Service Management</h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/block-rates" class="btn btn-sm btn-warning rounded-pill px-3 py-1 fw-bold text-dark shadow-2xs">
            <i class="bi bi-currency-rupee me-1"></i>Block Rates
        </a>
        <a href="/admin/application-code-forms" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold shadow-2xs">
            <i class="bi bi-ui-checks-grid me-1"></i>Code Forms
        </a>
        <a href="/admin/service-availability" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold shadow-2xs">
            <i class="bi bi-toggle-on me-1"></i>Block ON/OFF
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- 1. BLOCK APPLICATION PASS SERVICE -->
    <?php if (!empty($activeService)): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm rounded-3 border bg-white h-100">
                <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-2" style="width: 34px; height: 34px;">
                            <i class="bi bi-building fs-6"></i>
                        </span>
                        <div>
                            <h2 class="h6 fw-bold mb-0 text-dark"><?= e($activeService['name_en']) ?></h2>
                            <span class="text-muted font-monospace" style="font-size: 0.72rem;"><?= (int)($activeCodeCount ?? 0) ?> Codes &bull; <?= (int)($activeService['rule_count'] ?? 0) ?> Block Rates</span>
                        </div>
                    </div>

                    <form method="POST" action="/admin/data-types/toggle-status" class="m-0 d-flex align-items-center gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$activeService['id'] ?>">
                        <span class="badge <?= $activeService['is_active'] ? 'bg-success' : 'bg-danger' ?> text-white px-2 py-1">
                            <?= $activeService['is_active'] ? 'ON' : 'OFF' ?>
                        </span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" <?= $activeService['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()" style="cursor: pointer;">
                        </div>
                    </form>
                </div>

                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                    <div class="row g-2.5 mb-3">
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 bg-light rounded-3 border text-center h-100">
                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">Default Fee</div>
                                <div class="fs-5 fw-bold text-dark mt-0.5">₹<?= number_format((float)$activeService['fee_amount'], 0) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 bg-light rounded-3 border text-center h-100">
                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">Distributor</div>
                                <div class="fs-5 fw-bold text-success mt-0.5">₹<?= number_format((float)$activeService['distributor_commission'], 0) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 bg-light rounded-3 border text-center h-100">
                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">Time</div>
                                <div class="fw-bold text-dark mt-1" style="font-size: 0.92rem;"><?= e($activeService['processing_time'] ?: '24-48 Hrs') ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 bg-light rounded-3 border text-center h-100">
                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">Chat</div>
                                <div class="fw-bold <?= !empty($activeService['enable_chat']) ? 'text-primary' : 'text-secondary' ?> mt-1" style="font-size: 0.92rem;">
                                    <?= !empty($activeService['enable_chat']) ? 'ON' : 'OFF' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2.5 border-top">
                        <div class="d-flex gap-1.5 flex-wrap">
                            <a href="/admin/block-rates" class="btn btn-sm btn-outline-dark rounded-2 px-2.5 py-1 fw-semibold">
                                <i class="bi bi-currency-rupee"></i> Rates
                            </a>
                            <a href="/admin/application-code-forms" class="btn btn-sm btn-outline-secondary rounded-2 px-2.5 py-1 fw-semibold">
                                <i class="bi bi-ui-checks-grid"></i> Forms
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-2 px-2.5 py-1 fw-semibold" onclick='openNoticeModal(<?= htmlspecialchars(json_encode($activeService), ENT_QUOTES, "UTF-8") ?>)'>
                                <i class="bi bi-megaphone"></i> Notice
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary rounded-2 px-3 py-1 fw-bold" onclick='editDataType(<?= htmlspecialchars(json_encode($activeService), ENT_QUOTES, "UTF-8") ?>)'>
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. NEW PAN CARD SERVICE -->
    <?php if (!empty($panService)): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm rounded-3 border bg-white h-100">
                <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-2" style="width: 34px; height: 34px;">
                            <i class="bi bi-person-vcard fs-6"></i>
                        </span>
                        <div>
                            <h2 class="h6 fw-bold mb-0 text-dark"><?= e($panService['name_en']) ?></h2>
                            <span class="text-muted font-monospace" style="font-size: 0.72rem;">PAN_CARD &bull; <?= (int)($panService['app_count'] ?? 0) ?> Applications</span>
                        </div>
                    </div>

                    <form method="POST" action="/admin/data-types/toggle-status" class="m-0 d-flex align-items-center gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$panService['id'] ?>">
                        <span class="badge <?= $panService['is_active'] ? 'bg-success' : 'bg-danger' ?> text-white px-2 py-1">
                            <?= $panService['is_active'] ? 'ON' : 'OFF' ?>
                        </span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" <?= $panService['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()" style="cursor: pointer;">
                        </div>
                    </form>
                </div>

                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                    <div class="row g-2.5 mb-3">
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 bg-light rounded-3 border text-center h-100">
                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">PAN Fee</div>
                                <div class="fs-5 fw-bold text-dark mt-0.5">₹<?= number_format((float)$panService['fee_amount'], 0) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 bg-light rounded-3 border text-center h-100">
                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">Distributor</div>
                                <div class="fs-5 fw-bold text-success mt-0.5">₹<?= number_format((float)$panService['distributor_commission'], 0) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 bg-light rounded-3 border text-center h-100">
                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">Time</div>
                                <div class="fw-bold text-dark mt-1" style="font-size: 0.92rem;"><?= e($panService['processing_time'] ?: '1-2 Days') ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 bg-light rounded-3 border text-center h-100">
                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">Applications</div>
                                <div class="fs-5 fw-bold text-primary mt-0.5"><?= (int)($panService['app_count'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2.5 border-top">
                        <div class="d-flex gap-1.5 flex-wrap">
                            <a href="/services/pan-card/list" class="btn btn-sm btn-outline-dark rounded-2 px-2.5 py-1 fw-semibold">
                                <i class="bi bi-card-list"></i> PAN List
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-2 px-2.5 py-1 fw-semibold" onclick='openNoticeModal(<?= htmlspecialchars(json_encode($panService), ENT_QUOTES, "UTF-8") ?>)'>
                                <i class="bi bi-megaphone"></i> Notice
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary rounded-2 px-3 py-1 fw-bold" onclick='editDataType(<?= htmlspecialchars(json_encode($panService), ENT_QUOTES, "UTF-8") ?>)'>
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- MODAL 1: SIMPLE EDIT SERVICE                                   -->
<!-- ============================================================== -->
<div class="modal fade" id="dataTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/data-types/save" class="modal-content shadow border-0 rounded-3">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="dt_id" value="0">
            <input type="hidden" name="code" id="dt_code" value="srv_block_app">

            <div class="modal-header py-2.5 px-3.5 border-bottom">
                <h5 class="modal-title h6 fw-bold mb-0" id="dtModalTitle">Edit Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3.5">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Service Name</label>
                        <input type="text" name="name_en" id="dt_name_en" class="form-control form-control-sm" required>
                    </div>

                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Fee (₹)</label>
                        <input type="number" step="0.01" name="fee_amount" id="dt_fee" class="form-control form-control-sm fw-bold" required>
                    </div>

                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Distributor Comm. (₹)</label>
                        <input type="number" step="0.01" name="distributor_commission" id="dt_comm" class="form-control form-control-sm fw-bold">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Processing Time</label>
                        <input type="text" name="processing_time" id="dt_processing_time" class="form-control form-control-sm" placeholder="e.g. 24-48 Hours">
                    </div>

                    <div class="col-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="dt_active" value="1">
                            <label class="form-check-label small fw-bold" for="dt_active">Service ON</label>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="enable_chat" id="dt_enable_chat" value="1">
                            <label class="form-check-label small fw-bold" for="dt_enable_chat">Chat ON</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer py-2 px-3.5 bg-light border-top">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL 2: NOTICE & BANNER                                       -->
<!-- ============================================================== -->
<div class="modal fade" id="noticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="/admin/data-types/notice" enctype="multipart/form-data" class="modal-content shadow border-0 rounded-3">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="notice_dt_id" value="0">

            <div class="modal-header py-2.5 px-3.5 bg-warning-subtle border-bottom">
                <h5 class="modal-title h6 fw-bold mb-0 text-dark">
                    <i class="bi bi-megaphone-fill text-warning me-1"></i>Form Notice
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3.5">
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Notice Text</label>
                    <textarea name="notice" id="notice_text" class="form-control form-control-sm" rows="2" placeholder="Important notice shown on form..."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Guidelines (Optional)</label>
                    <textarea name="announcement" id="announcement_text" class="form-control form-control-sm" rows="2"></textarea>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-bold mb-1">Guide / Banner Image (Optional)</label>
                    <input type="file" name="warning_image_file" class="form-control form-control-sm mb-1.5" accept="image/*">
                    <input type="text" name="warning_image" id="notice_warning_image" class="form-control form-control-sm font-monospace" placeholder="/uploads/notices/banner.jpg">
                </div>
            </div>

            <div class="modal-footer py-2 px-3.5 bg-light border-top">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-sm btn-warning fw-bold px-3">Save Notice</button>
            </div>
        </form>
    </div>
</div>

<script>
function editDataType(dt) {
    document.getElementById('dt_id').value = dt.id;
    document.getElementById('dt_code').value = dt.code || 'srv_block_app';
    document.getElementById('dtModalTitle').textContent = 'Edit: ' + (dt.name_en || 'Service');
    document.getElementById('dt_name_en').value = dt.name_en || '';
    document.getElementById('dt_processing_time').value = dt.processing_time || '24-48 Hours';
    document.getElementById('dt_fee').value = dt.fee_amount || '0';
    document.getElementById('dt_comm').value = (dt.distributor_commission !== null && dt.distributor_commission !== undefined && dt.distributor_commission !== '') ? dt.distributor_commission : '0';
    document.getElementById('dt_active').checked = (parseInt(dt.is_active) === 1);
    document.getElementById('dt_enable_chat').checked = (parseInt(dt.enable_chat || 0) !== 0);

    new bootstrap.Modal(document.getElementById('dataTypeModal')).show();
}

function openNoticeModal(dt) {
    document.getElementById('notice_dt_id').value = dt.id;
    document.getElementById('notice_text').value = dt.notice || '';
    document.getElementById('announcement_text').value = dt.announcement || '';
    document.getElementById('notice_warning_image').value = dt.warning_image || '';

    new bootstrap.Modal(document.getElementById('noticeModal')).show();
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
