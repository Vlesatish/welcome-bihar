<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Breadcrumb / Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="bi bi-file-earmark-plus text-primary me-2"></i><?= e(__('new_application')) ?>
                </h1>
                <p class="text-muted mb-0">Unified Bihar Citizen Services Application Form</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border p-2 fs-6">
                    <i class="bi bi-wallet2 text-warning me-1"></i> Balance: <strong>₹<?= number_format($walletBalance, 2) ?></strong>
                </span>
                <a href="/wallet/topup" class="btn btn-sm btn-outline-warning fw-semibold">
                    <i class="bi bi-plus-lg"></i> Top-Up
                </a>
            </div>
        </div>

        <!-- Wizard Step Indicators -->
        <div class="wizard-steps px-md-5 mb-4">
            <div class="wizard-step <?= empty($selectedDistrict) ? 'active' : 'completed' ?>">
                <div class="step-circle"><i class="bi bi-geo-alt"></i></div>
                <div class="step-label"><?= e(__('step_1_location')) ?></div>
            </div>
            <div class="wizard-step <?= (!empty($selectedDistrict) && empty($selectedDataType)) ? 'active' : (!empty($selectedDataType) ? 'completed' : '') ?>">
                <div class="step-circle"><i class="bi bi-folder2-open"></i></div>
                <div class="step-label"><?= e(__('step_2_service')) ?></div>
            </div>
            <div class="wizard-step <?= !empty($selectedDataType) ? 'active' : '' ?>">
                <div class="step-circle"><i class="bi bi-ui-checks"></i></div>
                <div class="step-label"><?= e(__('step_3_details')) ?></div>
            </div>
        </div>

        <!-- Main Form Card -->
        <div class="wb-card shadow-sm">
            <div class="card-body p-4 p-md-5">

                <!-- STEP 1 & 2 SELECTOR FORM (GET) -->
                <form method="GET" action="/applications/new" id="location-service-form" class="mb-4">
                    <div class="row g-3 p-3 bg-light rounded-3 border mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="district_select">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> District <span class="text-danger">*</span>
                            </label>
                            <select name="district_id" id="district_select" class="form-select" required>
                                <option value="">-- Select District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $selectedDistrict === (int)$d['id'] ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="block_select">
                                <i class="bi bi-building text-primary me-1"></i> Block / ULB <span class="text-danger">*</span>
                            </label>
                            <select name="block_id" id="block_select" class="form-select" data-selected="<?= $selectedBlock ?>" required>
                                <option value="">-- Select Block --</option>
                                <?php foreach ($blocks as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $selectedBlock === (int)$b['id'] ? 'selected' : '' ?>>
                                        <?= e($b['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="data_type_select">
                                <i class="bi bi-card-checklist text-success me-1"></i> Citizen Service <span class="text-danger">*</span>
                            </label>
                            <select name="data_type_id" id="data_type_select" class="form-select" onchange="this.form.submit()" required>
                                <option value="">-- Select Service --</option>
                                <?php foreach ($dataTypes as $dt): ?>
                                    <option value="<?= $dt['id'] ?>" <?= $selectedDataType === (int)$dt['id'] ? 'selected' : '' ?>>
                                        <?= e($dt['name_en'] ?: $dt['name_hi']) ?> (Fee: ₹<?= number_format($dt['fee_amount'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <?php if ($activeDataType && !empty($fields)): ?>
                    <!-- Service Header Banner -->
                    <div class="alert alert-primary d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 border-0 bg-primary-subtle">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-3 bg-primary text-white rounded-circle">
                                <i class="bi <?= e($activeDataType['icon']) ?> fs-3"></i>
                            </div>
                            <div>
                                <h4 class="h5 fw-bold text-primary mb-1"><?= e($activeDataType['name_en'] ?: $activeDataType['name_hi']) ?></h4>
                                <?php if (!empty($activeDataType['description_en'])): ?>
                                    <div class="small mt-1 text-secondary"><?= e($activeDataType['description_en']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted text-uppercase fw-semibold"><?= e(__('fee_amount')) ?></div>
                            <div class="fs-4 fw-bold text-danger">₹<?= number_format($activeDataType['fee_amount'], 2) ?></div>
                        </div>
                    </div>

                    <?php if ($walletBalance < (float)$activeDataType['fee_amount']): ?>
                        <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 mb-4 rounded-3">
                            <div>
                                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
                                <strong><?= e(__('insufficient_balance')) ?></strong>
                                <div class="small mt-1">Available balance: ₹<?= number_format($walletBalance, 2) ?> | Required: ₹<?= number_format($activeDataType['fee_amount'], 2) ?></div>
                            </div>
                            <a href="/wallet/topup" class="btn btn-warning fw-bold px-3">
                                <i class="bi bi-qr-code me-1"></i> Recharge Now
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- STEP 3 DYNAMIC FORM SUBMIT (POST) -->
                    <form method="POST" action="/applications/submit" id="application-submit-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="district_id" value="<?= $selectedDistrict ?>">
                        <input type="hidden" name="block_id" value="<?= $selectedBlock ?>">
                        <input type="hidden" name="data_type_id" value="<?= $selectedDataType ?>">

                        <div class="row g-3">
                            <?php foreach ($fields as $field): ?>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label fw-semibold">
                                        <?= e($field['label_en'] ?: $field['label_hi']) ?>
                                        <?php if ($field['is_required']): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>

                                    <?php if ($field['field_type'] === 'select'): ?>
                                        <?php
                                            $options = !empty($field['options_json']) ? json_decode($field['options_json'], true) : [];
                                        ?>
                                        <select name="<?= e($field['field_name']) ?>" class="form-select" <?= $field['is_required'] ? 'required' : '' ?>>
                                            <option value="">-- Choose Option --</option>
                                            <?php foreach ($options as $opt): ?>
                                                <option value="<?= e($opt['value']) ?>">
                                                    <?= e($opt['label_en'] ?? $opt['label_hi'] ?? $opt['value']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                    <?php elseif ($field['field_type'] === 'textarea'): ?>
                                        <textarea name="<?= e($field['field_name']) ?>" class="form-control" rows="3"
                                                  placeholder="<?= e($field['placeholder_en'] ?: $field['placeholder_hi']) ?>"
                                                  <?= $field['is_required'] ? 'required' : '' ?>></textarea>

                                    <?php elseif ($field['field_type'] === 'date'): ?>
                                        <input type="date" name="<?= e($field['field_name']) ?>" class="form-control"
                                               <?= $field['is_required'] ? 'required' : '' ?>>

                                    <?php elseif ($field['field_type'] === 'number'): ?>
                                        <input type="number" name="<?= e($field['field_name']) ?>" class="form-control"
                                               placeholder="<?= e($field['placeholder_en'] ?: $field['placeholder_hi']) ?>"
                                               <?= !empty($field['validation_pattern']) ? 'pattern="' . e($field['validation_pattern']) . '"' : '' ?>
                                               <?= $field['is_required'] ? 'required' : '' ?>>

                                    <?php else: ?>
                                        <input type="<?= e($field['field_type']) ?>" name="<?= e($field['field_name']) ?>" class="form-control"
                                               placeholder="<?= e($field['placeholder_en'] ?: $field['placeholder_hi']) ?>"
                                               <?= !empty($field['validation_pattern']) ? 'pattern="' . e($field['validation_pattern']) . '"' : '' ?>
                                               <?= $field['is_required'] ? 'required' : '' ?>>
                                    <?php endif; ?>

                                    <?php if (!empty($field['help_text_en']) || !empty($field['help_text_hi'])): ?>
                                        <div class="form-text small text-muted">
                                            <?= e($field['help_text_en'] ?: $field['help_text_hi']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Submission Disclaimer & Lock Notice -->
                        <div class="alert alert-secondary mt-4 p-3 small">
                            <i class="bi bi-info-circle-fill me-1 text-primary"></i>
                            <strong><?= e(__('application_locked_notice')) ?></strong>
                            Please review all details before submitting. Application fee of ₹<?= number_format($activeDataType['fee_amount'], 2) ?> will be debited from your wallet immediately upon submission.
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-2">
                            <a href="/user/dashboard" class="btn btn-outline-secondary px-4 fw-semibold">
                                <i class="bi bi-arrow-left me-1"></i> <?= e(__('cancel')) ?>
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow-sm"
                                    <?= $walletBalance < (float)$activeDataType['fee_amount'] ? 'disabled' : '' ?>>
                                <i class="bi bi-send-check me-2"></i> <?= e(__('submit')) ?>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-hand-index fs-1 text-primary opacity-50"></i>
                        <h4 class="h6 fw-bold mt-3 mb-1">Please select District, Block, and Data Type above to load the form</h4>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
