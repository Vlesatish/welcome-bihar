<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Header with Navigation Tabs -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h3 fw-bold mb-0">
                <i class="bi bi-toggle-on text-success me-2"></i>Service Availability
            </h1>
            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-1">srv_block_app</span>
        </div>
        <p class="text-muted small mb-0">Control block-level public service availability for application submission</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/locations" class="btn btn-outline-secondary fw-semibold btn-sm">
            <i class="bi bi-geo-alt me-1"></i> Districts & Blocks Master
        </a>
        <a href="/admin/block-rates" class="btn btn-outline-warning fw-semibold btn-sm text-dark">
            <i class="bi bi-currency-rupee me-1"></i> Block Rates
        </a>
        <button type="button" class="btn btn-primary fw-bold btn-sm shadow-xs" data-bs-toggle="modal" data-bs-target="#quickActivateModal">
            <i class="bi bi-lightning-charge-fill me-1"></i> Quick Activate Block
        </button>
    </div>
</div>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-3 small d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-check-circle-fill text-success fs-6"></i>
        <div><?= e($_SESSION['flash_success']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 px-3 mb-3 small d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill text-danger fs-6"></i>
        <div><?= e($_SESSION['flash_error']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Summary Cards (Clickable Filter Links) -->
<div class="row g-3 mb-4">
    <!-- Total Blocks -->
    <div class="col-6 col-md-4 col-xl">
        <a href="/admin/service-availability" class="card shadow-2xs border text-decoration-none h-100 transition-all hover-shadow bg-white">
            <div class="card-body p-3">
                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Total Blocks</span>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <span class="h4 fw-bold mb-0 text-dark"><?= number_format($summary['total_blocks']) ?></span>
                    <i class="bi bi-grid text-secondary opacity-50 fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Live Blocks -->
    <div class="col-6 col-md-4 col-xl">
        <a href="/admin/service-availability?status=LIVE" class="card shadow-2xs border text-decoration-none h-100 transition-all hover-shadow bg-white <?= $filterStatus === 'LIVE' ? 'border-success ring-1' : '' ?>">
            <div class="card-body p-3">
                <span class="text-success text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Live Blocks</span>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <span class="h4 fw-bold mb-0 text-success"><?= number_format($summary['live_blocks']) ?></span>
                    <i class="bi bi-check2-circle text-success fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Inactive Blocks -->
    <div class="col-6 col-md-4 col-xl">
        <a href="/admin/service-availability?status=INACTIVE" class="card shadow-2xs border text-decoration-none h-100 transition-all hover-shadow bg-white <?= $filterStatus === 'INACTIVE' ? 'border-secondary ring-1' : '' ?>">
            <div class="card-body p-3">
                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Inactive Blocks</span>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <span class="h4 fw-bold mb-0 text-secondary"><?= number_format($summary['inactive_blocks']) ?></span>
                    <i class="bi bi-dash-circle text-muted fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Live Blocks Without Operator -->
    <div class="col-6 col-md-6 col-xl">
        <a href="/admin/service-availability?status=LIVE&operator_filter=MISSING_OPERATOR" class="card shadow-2xs border text-decoration-none h-100 transition-all hover-shadow bg-white <?= ($filterStatus === 'LIVE' && $filterOperator === 'MISSING_OPERATOR') ? 'border-warning ring-1' : '' ?>">
            <div class="card-body p-3">
                <span class="text-warning text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Live Without Operator</span>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <span class="h4 fw-bold mb-0 text-warning"><?= number_format($summary['live_missing_operator']) ?></span>
                    <i class="bi bi-person-exclamation text-warning fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Live Blocks Without Rate -->
    <div class="col-6 col-md-6 col-xl">
        <a href="/admin/service-availability?status=LIVE&rate_filter=MISSING_RATE" class="card shadow-2xs border text-decoration-none h-100 transition-all hover-shadow bg-white <?= ($filterStatus === 'LIVE' && $filterRate === 'MISSING_RATE') ? 'border-info ring-1' : '' ?>">
            <div class="card-body p-3">
                <span class="text-info text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Live Without Rate</span>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <span class="h4 fw-bold mb-0 text-info"><?= number_format($summary['live_missing_rate']) ?></span>
                    <i class="bi bi-tag text-info fs-5"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card shadow-sm border bg-white rounded-3 mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/admin/service-availability" class="row g-2 align-items-center">
            <!-- District Filter -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small text-muted mb-1 fw-semibold">District</label>
                <select name="district_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Districts (38)</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filterDistrictId === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label small text-muted mb-1 fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>All Status</option>
                    <option value="LIVE" <?= $filterStatus === 'LIVE' ? 'selected' : '' ?>>LIVE Only</option>
                    <option value="INACTIVE" <?= $filterStatus === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE Only</option>
                </select>
            </div>

            <!-- Operator Filter -->
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label small text-muted mb-1 fw-semibold">Operator</label>
                <select name="operator_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="ALL" <?= $filterOperator === 'ALL' ? 'selected' : '' ?>>All Operators</option>
                    <option value="HAS_OPERATOR" <?= $filterOperator === 'HAS_OPERATOR' ? 'selected' : '' ?>>Has Operator</option>
                    <option value="MISSING_OPERATOR" <?= $filterOperator === 'MISSING_OPERATOR' ? 'selected' : '' ?>>Missing Operator</option>
                </select>
            </div>

            <!-- Rate Filter -->
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label small text-muted mb-1 fw-semibold">Rate Rule</label>
                <select name="rate_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="ALL" <?= $filterRate === 'ALL' ? 'selected' : '' ?>>All Rates</option>
                    <option value="HAS_RATE" <?= $filterRate === 'HAS_RATE' ? 'selected' : '' ?>>Block-Specific Rate</option>
                    <option value="MISSING_RATE" <?= $filterRate === 'MISSING_RATE' ? 'selected' : '' ?>>Base/Default Rate</option>
                </select>
            </div>

            <!-- Search Input -->
            <div class="col-12 col-sm-9 col-md-3">
                <label class="form-label small text-muted mb-1 fw-semibold">Search</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search block or district..." value="<?= e($filterSearch) ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                    <?php if ($filterDistrictId || $filterStatus !== 'ALL' || $filterOperator !== 'ALL' || $filterRate !== 'ALL' || !empty($filterSearch)): ?>
                        <a href="/admin/service-availability" class="btn btn-outline-secondary" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Compact Blocks Availability Table -->
<div class="card shadow-sm border bg-white rounded-3">
    <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
        <span class="fw-bold small text-dark d-flex align-items-center gap-2">
            <i class="bi bi-list-check text-primary"></i>
            <span>Block Service Status (<?= count($blocksList) ?> Blocks)</span>
        </span>
        <span class="text-muted small font-monospace">Service: srv_block_app</span>
    </div>

    <div class="table-responsive" style="min-height: 250px;">
        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
            <thead class="table-light">
                <tr>
                    <th style="width: 45px;" class="text-center">#</th>
                    <th style="width: 140px;">District</th>
                    <th style="width: 150px;">Block</th>
                    <th style="min-width: 180px;">Available Application Codes</th>
                    <th style="min-width: 170px;">Configured Rates</th>
                    <th style="width: 110px;" class="text-center">Operators</th>
                    <th style="width: 110px;" class="text-center">Service Status</th>
                    <th style="width: 140px;" class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($blocksList)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                            <div class="fw-semibold text-secondary">No rate-configured blocks found. Add a block rate first.</div>
                            <div class="mt-2.5">
                                <a href="/admin/data-types/rate-rules?data_type_id=<?= $serviceId ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                    <i class="bi bi-plus-circle me-1"></i> Add Block Rate
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($blocksList as $idx => $b): ?>
                        <?php
                            $isLive = ($b['service_status'] === 'LIVE');
                            $hasRate = ((int)$b['block_rate_count'] > 0);
                            $hasOp = ((int)$b['operator_count'] > 0);
                            $waReady = ((int)$b['whatsapp_operator_count'] > 0);
                        ?>
                        <tr>
                            <td class="text-center text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-semibold text-dark"><?= e($b['district_name']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($b['block_name']) ?></div>
                                <div class="text-muted font-monospace" style="font-size: 0.72rem;">Code: <?= e($b['block_code']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($b['available_application_codes'])): ?>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?= e($b['available_application_codes']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="font-monospace text-success fw-bold" style="font-size: 0.85rem;">
                                    <?= e($b['configured_rates_summary'] ?: ('₹' . number_format((float)$b['sample_block_fee'], 2))) ?>
                                </div>
                                <span class="text-muted small" style="font-size: 0.7rem;">(<?= $b['block_rate_count'] ?> active rule<?= $b['block_rate_count'] > 1 ? 's' : '' ?>)</span>
                            </td>
                            <td class="text-center">
                                <?php if ($hasOp): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <?= $b['operator_count'] ?> Assigned
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        0 Assigned
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($waReady): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-whatsapp me-1"></i><?= $b['whatsapp_operator_count'] ?> Ready
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">
                                        <i class="bi bi-whatsapp text-muted me-1"></i>0 Ready
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isLive): ?>
                                    <span class="badge bg-success px-2 py-1">
                                        <i class="bi bi-broadcast me-1"></i>LIVE
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary px-2 py-1">
                                        INACTIVE
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="/admin/service-availability/toggle" class="d-inline" onsubmit="return handleToggleSubmit(this, <?= $isLive ? 'true' : 'false' ?>, <?= (int)$b['operator_count'] ?>, <?= (int)$b['block_rate_count'] ?>, '<?= addslashes(e($b['block_name'])) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="district_id" value="<?= (int)$b['district_id'] ?>">
                                    <input type="hidden" name="block_id" value="<?= (int)$b['block_id'] ?>">
                                    <input type="hidden" name="status" value="<?= $isLive ? 'INACTIVE' : 'LIVE' ?>">

                                    <?php if ($isLive): ?>
                                        <button type="submit" class="btn btn-outline-danger btn-xs py-1 px-2 fw-semibold" style="font-size: 0.75rem;">
                                            <i class="bi bi-pause-circle me-1"></i> Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-success btn-xs py-1 px-2.5 fw-bold shadow-2xs" style="font-size: 0.75rem;">
                                            <i class="bi bi-play-circle-fill me-1"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- QUICK ACTIVATION MODAL -->
<div class="modal fade" id="quickActivateModal" tabindex="-1" aria-labelledby="quickActivateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" action="/admin/service-availability/quick-activate">
                <?= csrf_field() ?>

                <div class="modal-header py-2.5 px-3 bg-light border-bottom">
                    <h5 class="modal-title h6 fw-bold mb-0" id="quickActivateModalLabel">
                        <i class="bi bi-lightning-charge-fill text-primary me-1"></i>Quick Activate Block Service
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-3">
                    <div class="mb-2.5">
                        <label class="form-label small fw-semibold text-dark mb-1">1. Select District *</label>
                        <select name="district_id" id="qaDistrictSelect" class="form-select form-select-sm" required onchange="handleQaDistrictChange(this.value)">
                            <option value="">-- Choose District --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $filterDistrictId === (int)$d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2.5">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold text-dark mb-0">2. Select Block *</label>
                            <a href="javascript:void(0)" class="small text-primary text-decoration-none" onclick="toggleNewBlockInput()">
                                <span id="toggleNewBlockText">+ Or Create New Block</span>
                            </a>
                        </div>
                        <div id="qaExistingBlockWrapper">
                            <select name="block_id" id="qaBlockSelect" class="form-select form-select-sm">
                                <option value="">-- Choose Block --</option>
                            </select>
                        </div>
                        <div id="qaNewBlockWrapper" class="d-none mt-1">
                            <input type="text" name="new_block_name" id="qaNewBlockInput" class="form-control form-control-sm" placeholder="Enter new block name">
                        </div>
                    </div>

                    <div class="row g-2 mb-2.5">
                        <div class="col-7">
                            <label class="form-label small fw-semibold text-dark mb-1">3. Service Rate (₹)</label>
                            <input type="number" step="0.01" min="0" name="service_rate" class="form-control form-control-sm" placeholder="e.g. 60.00 (optional)">
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-semibold text-dark mb-1">App Code</label>
                            <select name="application_code" class="form-select form-select-sm">
                                <option value="*">All Codes (*)</option>
                                <option value="BICCO">BICCO</option>
                                <option value="BCCCO">BCCCO</option>
                                <option value="BRCCO">BRCCO</option>
                                <option value="BOBC">BOBC</option>
                                <option value="NCLCO">NCLCO</option>
                                <option value="RTPS">RTPS</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2.5">
                        <label class="form-label small fw-semibold text-dark mb-1">4. Assign Block Operator</label>
                        <select name="operator_id" class="form-select form-select-sm">
                            <option value="">-- None / Keep Unchanged --</option>
                            <?php foreach ($operators as $op): ?>
                                <option value="<?= $op['id'] ?>">
                                    <?= e($op['name']) ?> (<?= e($op['mobile']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2.5">
                        <label class="form-label small fw-semibold text-dark mb-1">5. Service Status *</label>
                        <select name="service_status" class="form-select form-select-sm fw-bold">
                            <option value="LIVE" selected class="text-success">LIVE (Publicly Active for Retailers)</option>
                            <option value="INACTIVE" class="text-secondary">INACTIVE (Hidden / Disabled)</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-dark mb-1">Internal Note (Optional)</label>
                        <input type="text" name="internal_note" class="form-control form-control-sm" placeholder="e.g. Connected via local operator">
                    </div>
                </div>

                <div class="modal-footer py-2 px-3 bg-light border-top">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold">Save & Activate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function handleToggleSubmit(form, isCurrentlyLive, opCount, rateCount, blockName) {
    if (isCurrentlyLive) {
        return confirm('Are you sure you want to deactivate ' + blockName + '? New application submissions will stop immediately. Existing applications will remain intact.');
    } else {
        let warnings = [];
        if (opCount === 0) {
            warnings.push('• No active Block Operator is currently assigned.');
        }
        if (rateCount === 0) {
            warnings.push('• No block-specific rate rule is configured (standard base rate applies).');
        }

        if (warnings.length > 0) {
            let msg = 'Readiness check for ' + blockName + ':\n\n' + warnings.join('\n') + '\n\nDo you want to proceed and activate this block as LIVE?';
            return confirm(msg);
        }
        return true;
    }
}

function toggleNewBlockInput() {
    const existing = document.getElementById('qaExistingBlockWrapper');
    const newBlock = document.getElementById('qaNewBlockWrapper');
    const toggleText = document.getElementById('toggleNewBlockText');
    const newBlockInput = document.getElementById('qaNewBlockInput');
    const blockSelect = document.getElementById('qaBlockSelect');

    if (newBlock.classList.contains('d-none')) {
        newBlock.classList.remove('d-none');
        existing.classList.add('d-none');
        toggleText.textContent = '← Or Select Existing Block';
        newBlockInput.required = true;
        blockSelect.required = false;
        blockSelect.value = '';
    } else {
        newBlock.classList.add('d-none');
        existing.classList.remove('d-none');
        toggleText.textContent = '+ Or Create New Block';
        newBlockInput.required = false;
        newBlockInput.value = '';
        blockSelect.required = true;
    }
}

function handleQaDistrictChange(districtId) {
    const blockSelect = document.getElementById('qaBlockSelect');
    if (!districtId) {
        blockSelect.innerHTML = '<option value="">-- Choose Block --</option>';
        return;
    }

    blockSelect.innerHTML = '<option value="">Loading blocks...</option>';
    fetch('/api/blocks?district_id=' + encodeURIComponent(districtId))
        .then(res => res.json())
        .then(blocks => {
            let html = '<option value="">-- Choose Block --</option>';
            if (Array.isArray(blocks)) {
                blocks.forEach(b => {
                    html += `<option value="${b.id}">${escapeHtml(b.name)}</option>`;
                });
            }
            blockSelect.innerHTML = html;
        })
        .catch(err => {
            blockSelect.innerHTML = '<option value="">Failed to load blocks</option>';
        });
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

document.addEventListener('DOMContentLoaded', function () {
    const initialDist = document.getElementById('qaDistrictSelect').value;
    if (initialDist) {
        handleQaDistrictChange(initialDist);
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
