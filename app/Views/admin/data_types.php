<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
    <div>
        <h1 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-sliders text-primary"></i>
            <span>Service Settings</span>
        </h1>
        <div class="small text-muted" style="font-size: 0.74rem;">
            Configuration for Block & District Application Pass Service
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/data-types/fields?id=<?= $activeService['id'] ?? 4 ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold shadow-2xs">
            <i class="bi bi-ui-checks me-1"></i> Form Fields
        </a>
        <a href="/admin/block-rates" class="btn btn-sm btn-warning rounded-pill px-3 py-1 fw-bold text-dark shadow-2xs" style="background-color: #f59e0b; border-color: #d97706;">
            <i class="bi bi-currency-rupee me-1"></i> Block Rates
        </a>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold shadow-2xs" onclick='openNoticeModal(<?= json_encode($activeService ?? []) ?>)'>
            <i class="bi bi-megaphone me-1"></i> Notice
        </button>
    </div>
</div>

<!-- Primary Service Active Management Card -->
<?php if (!empty($activeService)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm rounded-3 border bg-white" style="border: 1px solid #e2e8f0;">
                <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-building text-primary"></i>
                        <h2 class="h6 fw-bold mb-0 text-dark"><?= e($activeService['name_en']) ?></h2>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <form method="POST" action="/admin/data-types/toggle-status" class="m-0 d-flex align-items-center gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $activeService['id'] ?>">
                            <span class="badge <?= $activeService['is_active'] ? 'bg-success text-white' : 'bg-danger text-white' ?> text-uppercase px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">
                                <?= $activeService['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" <?= $activeService['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()" style="cursor: pointer;" title="Toggle Service Availability">
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body p-3.5">
                    <!-- Key Parameters Grid -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6 col-lg-3">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="small text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Base Service Fee</div>
                                <div class="fs-5 fw-bold text-dark mt-1">₹<?= number_format($activeService['fee_amount'], 2) ?></div>
                                <div class="small text-muted" style="font-size: 0.72rem;">Default fallback rate</div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="small text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Distributor Commission</div>
                                <div class="fs-5 fw-bold text-success mt-1">₹<?= number_format($activeService['distributor_commission'], 2) ?></div>
                                <div class="small text-muted" style="font-size: 0.72rem;">Credited on completion</div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="small text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Processing Time</div>
                                <div class="fs-5 fw-bold text-dark mt-1"><?= e($activeService['processing_time'] ?: '10-30 MIN') ?></div>
                                <div class="small text-muted" style="font-size: 0.72rem;">Expected window</div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="small text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Chat Feature</div>
                                <div class="fs-5 fw-bold text-primary mt-1"><?= !empty($activeService['enable_chat']) ? 'Enabled' : 'Disabled' ?></div>
                                <div class="small text-muted" style="font-size: 0.72rem;">Retailer-Operator messaging</div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Service Button -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold shadow-2xs" onclick='editDataType(<?= json_encode($activeService) ?>)'>
                            <i class="bi bi-pencil-square me-1"></i> Edit Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Preserved / Archived Services Section (Collapsed Read-Only) -->
<div class="row">
    <div class="col-12">
        <div class="card border rounded-3 shadow-xs mb-4">
            <div class="card-header bg-light py-2.5 px-3 d-flex align-items-center justify-content-between" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseArchived" aria-expanded="false">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-archive text-muted"></i>
                    <span class="small fw-bold text-dark">Archived Services (<?= count($archivedServices) ?>)</span>
                    <span class="badge bg-secondary-subtle text-secondary border font-monospace" style="font-size: 0.65rem;">Read Only</span>
                </div>
                <i class="bi bi-chevron-down text-muted small"></i>
            </div>
            <div class="collapse" id="collapseArchived">
                <div class="card-body p-0">
                    <div class="p-3 bg-light-subtle border-bottom">
                        <div class="small text-muted">Historical applications, wallet ledgers, and audit trail are safely preserved. New applications for these services are disabled.</div>
                    </div>
                    <?php if (empty($archivedServices)): ?>
                        <div class="p-3 text-muted small">No other services in system.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-muted small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Service Code</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Historical Apps</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archivedServices as $as): ?>
                                        <tr class="opacity-75">
                                            <td class="ps-3 font-monospace"><?= e($as['code']) ?></td>
                                            <td class="fw-semibold text-dark"><?= e($as['name_en']) ?></td>
                                            <td><?= e($as['category']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= (int)($as['app_count'] ?? 0) ?> applications</span></td>
                                            <td><span class="badge bg-secondary text-white">DEACTIVATED</span></td>
                                            <td class="text-end pe-3">
                                                <a href="/applications?data_type_id=<?= $as['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Historical Applications">
                                                    <i class="bi bi-eye"></i> View Apps
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EDIT SERVICE DETAILS                                    -->
<!-- ============================================================== -->
<div class="modal fade" id="dataTypeModal" tabindex="-1" aria-labelledby="dtModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="/admin/data-types/save" class="modal-content shadow-lg border-0">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="dt_id" value="<?= $activeService['id'] ?? 4 ?>">
            <input type="hidden" name="code" value="srv_block_app">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="dtModalTitle">Edit Service: Block & District Application Pass</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="name_en" id="dt_name_en" class="form-control" required placeholder="e.g. Block & District Application Pass Service">
                        <input type="hidden" name="name_hi" id="dt_name_hi" value="">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Category</label>
                        <input type="text" name="category" id="dt_category" class="form-control" placeholder="e.g. District & Block Services" value="District & Block Services">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Processing Time</label>
                        <input type="text" name="processing_time" id="dt_processing_time" class="form-control" placeholder="e.g. 10-30 MIN" value="10-30 MIN">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Badge (Optional)</label>
                        <input type="text" name="badge" id="dt_badge" class="form-control" placeholder="e.g. POPULAR or INSTANT">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Icon (Bootstrap Icon Class)</label>
                        <input type="text" name="icon" id="dt_icon" class="form-control font-monospace" placeholder="e.g. bi-building" value="bi-building">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Base Service Rate (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" name="fee_amount" id="dt_fee" class="form-control fw-bold" required value="50.00">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Distributor Comm. (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" name="distributor_commission" id="dt_comm" class="form-control fw-bold" value="10.00">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Duplicate Check Field (Key)</label>
                        <input type="text" name="duplicate_check_field" id="dt_dup_field" class="form-control font-monospace" value="application_number" readonly>
                        <div class="form-text small">Unique application number check prevents duplicate pass submissions.</div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="dt_active" value="1" checked>
                            <label class="form-check-label fw-semibold" for="dt_active">Service Is Active & Live</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="enable_chat" id="dt_enable_chat" value="1" checked>
                            <label class="form-check-label fw-semibold" for="dt_enable_chat">Enable Retailer-Operator Chat</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Caution / Guide Banner Image URL</label>
                        <input type="text" name="warning_image" id="dt_warning_image" class="form-control font-monospace" placeholder="e.g. /uploads/notices/guide_banner.jpg">
                        <div class="form-text small">Image displayed with zoom preview in the caution section on the retailer form.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description_en" id="dt_desc_en" rows="2" class="form-control" placeholder="Summary of service, eligibility, and instructions..."></textarea>
                        <input type="hidden" name="description_hi" id="dt_desc_hi" value="">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">
                    <i class="bi bi-save me-1"></i> Save Service
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Notice Banner & Announcement -->
<div class="modal fade" id="noticeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/admin/data-types/notice" enctype="multipart/form-data" class="modal-content shadow-lg border-0">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="notice_dt_id" value="<?= $activeService['id'] ?? 4 ?>">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-megaphone-fill text-warning me-2"></i>Notice Banner & Announcement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notice Banner (Alert at Top of Service Form)</label>
                    <textarea name="notice" id="notice_text" class="form-control" rows="3" placeholder="e.g. Please ensure application code and application number are entered accurately."></textarea>
                    <div class="form-text small">Displays as a prominent warning/info alert when user opens the application form.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Announcement / Important Guidelines</label>
                    <textarea name="announcement" id="announcement_text" class="form-control" rows="2" placeholder="e.g. Standard fee applies per application. Exact rate calculated based on District, Block, and Application Code."></textarea>
                    <div class="form-text small">Additional guidelines displayed inside the application form.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Warning Banner Image (Upload or URL)</label>
                    <input type="file" name="warning_image_file" class="form-control form-control-sm mb-1" accept="image/*">
                    <input type="text" name="warning_image" id="notice_warning_image" class="form-control form-control-sm font-monospace" placeholder="Or enter image URL e.g. /uploads/notices/banner.jpg">
                    <div class="form-text small">Optional banner image rendered in the caution alert box.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-warning fw-bold px-4">
                    <i class="bi bi-check-circle me-1"></i> Update Notice Banner
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editDataType(dt) {
    document.getElementById('dt_id').value = dt.id;
    document.getElementById('dt_name_en').value = dt.name_en;
    document.getElementById('dt_name_hi').value = dt.name_hi || '';
    document.getElementById('dt_category').value = dt.category || 'District & Block Services';
    document.getElementById('dt_processing_time').value = dt.processing_time || '10-30 MIN';
    document.getElementById('dt_badge').value = dt.badge || '';
    document.getElementById('dt_icon').value = dt.icon || 'bi-building';
    document.getElementById('dt_fee').value = dt.fee_amount;
    document.getElementById('dt_comm').value = (dt.distributor_commission !== null && dt.distributor_commission !== undefined && dt.distributor_commission !== '') ? dt.distributor_commission : '0.00';
    document.getElementById('dt_dup_field').value = dt.duplicate_check_field || 'application_number';
    document.getElementById('dt_active').checked = (dt.is_active == 1);
    document.getElementById('dt_enable_chat').checked = (dt.enable_chat != 0);
    document.getElementById('dt_warning_image').value = dt.warning_image || '';
    document.getElementById('dt_desc_en').value = dt.description_en || '';
    document.getElementById('dt_desc_hi').value = dt.description_hi || '';

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
