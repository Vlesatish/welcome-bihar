<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
    <div>
        <h1 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-currency-rupee text-primary"></i>
            <span>Block Rates</span>
        </h1>
        <div class="small text-muted" style="font-size: 0.74rem;">
            Custom rates per District, Block, and Application Code prefix
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold shadow-2xs" data-bs-toggle="modal" data-bs-target="#customAppCodeModal" onclick="openCustomAppCodeModal()">
            <i class="bi bi-tag-fill text-primary me-1"></i> Add App Code
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold shadow-2xs" data-bs-toggle="modal" data-bs-target="#locationModal" onclick="openLocationModal()">
            <i class="bi bi-geo-alt me-1"></i> Add Block
        </button>
        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold shadow-2xs" data-bs-toggle="modal" data-bs-target="#rateRuleModal" onclick="resetRateRuleModal()">
            <i class="bi bi-plus-lg me-1"></i> Add Rate
        </button>
    </div>
</div>

<!-- Compact Default Rates Section -->
<div class="card border border-warning-subtle shadow-sm mb-3 rounded-3 bg-light">
    <div class="card-body p-3">
        <form method="POST" action="/admin/block-rates/save-fallbacks" class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <?= csrf_field() ?>
            <div>
                <strong class="text-dark small"><i class="bi bi-gear-fill text-warning me-1"></i>Default Rates (When specific Block Rate is not set):</strong>
                <div class="text-muted small mt-0.5" style="font-size: 0.75rem;">
                    Standard default applied if no custom block rate matches the applicant location.
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="width: 175px;">
                    <span class="input-group-text bg-white small fw-bold">BICCO: ₹</span>
                    <input type="number" step="0.01" name="fallback_rate_standard" class="form-control fw-bold text-center" value="<?= number_format($fallbackStandard, 2, '.', '') ?>" required title="Default for BICCO, BCCCO, BRCCO">
                </div>
                <div class="input-group input-group-sm" style="width: 175px;">
                    <span class="input-group-text bg-white small fw-bold">BOBC: ₹</span>
                    <input type="number" step="0.01" name="fallback_rate_other" class="form-control fw-bold text-center" value="<?= number_format($fallbackOther, 2, '.', '') ?>" required title="Default for BOBC, NCLCO, OTHER">
                </div>
                <button type="submit" class="btn btn-sm btn-warning fw-bold px-3">
                    Save Defaults
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Search Bar & Filters Card -->
<div class="card border-0 shadow-sm mb-3 bg-white rounded-3 border">
    <div class="card-body p-3">
        <form method="GET" action="/admin/block-rates" class="row g-2 align-items-end">
            <input type="hidden" name="id" value="<?= $dataType['id'] ?>">

            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">District</label>
                <select name="filter_district" id="filter_district" class="form-select form-select-sm" onchange="onFilterDistrictChange(this.value)">
                    <option value="">All Districts</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filterDistrict === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Block</label>
                <select name="filter_block" id="filter_block" class="form-select form-select-sm">
                    <option value="">All Blocks</option>
                    <?php foreach ($preloadedBlocks as $pb): ?>
                        <option value="<?= $pb['id'] ?>" <?= $filterBlock === (int)$pb['id'] ? 'selected' : '' ?>>
                            <?= e($pb['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">App Code</label>
                <select name="filter_code" class="form-select form-select-sm font-monospace">
                    <option value="">All Codes</option>
                    <?php foreach ($appCodes as $cVal => $cLabel): ?>
                        <option value="<?= e($cVal) ?>" <?= $filterCode === $cVal ? 'selected' : '' ?>>
                            <?= e($cVal) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="filter_status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="disabled" <?= $filterStatus === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>

            <div class="col-md-2 col-sm-12 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="/admin/block-rates" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Block Rates Table: District | Block | App Code | Rate | Active | Edit -->
<div class="card shadow-sm border rounded-3 bg-white mb-4" style="border: 1px solid #e2e8f0;">
    <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="h6 fw-bold mb-0 text-dark">Configured Rates</h5>
            <span class="badge bg-light text-secondary border font-monospace" id="rulesCountBadge"><?= count($rules) ?> Rates</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rules)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-tags fs-1 opacity-50 text-primary"></i>
                <h5 class="fw-bold mt-2 h6">No Rates Found</h5>
                <p class="small text-muted mb-3">No matching block rates configured yet.</p>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#rateRuleModal" onclick="resetRateRuleModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add Rate
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="blockRatesTable">
                    <thead class="table-light text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">
                        <tr>
                            <th class="ps-3 py-2.5">District</th>
                            <th class="py-2.5">Block</th>
                            <th class="py-2.5">App Code</th>
                            <th class="py-2.5 text-center">Rate</th>
                            <th class="py-2.5 text-center">Staff Routing</th>
                            <th class="py-2.5 text-center">Active</th>
                            <th class="py-2.5 text-end pe-3">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $r): ?>
                            <tr class="rate-table-row" data-search="<?= strtolower(e(($r['state'] ?? 'Bihar') . ' ' . ($r['district_name'] ?? '') . ' ' . ($r['block_name'] ?? '') . ' ' . ($r['application_code'] ?? 'ALL') . ' ' . ($r['rule_label'] ?? ''))) ?>">
                                <td class="ps-3 py-2.5 fw-semibold text-dark">
                                    <?= !empty($r['district_name']) ? e($r['district_name']) : 'All Districts' ?>
                                </td>
                                <td class="py-2.5">
                                    <?php if (!empty($r['block_name'])): ?>
                                        <span class="fw-medium text-dark"><?= e($r['block_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">All Blocks</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5">
                                    <span class="badge bg-light text-dark border font-monospace fw-bold">
                                        <?= !empty($r['application_code']) ? e($r['application_code']) : 'ALL' ?>
                                    </span>
                                </td>
                                <td class="py-2.5 text-center font-monospace fw-bold text-success fs-6">
                                    ₹<?= number_format($r['fee_amount'], 2) ?>
                                </td>
                                <td class="py-2.5 text-center">
                                    <?php
                                        $rDistId = (int)($r['district_id'] ?? 0);
                                        $rBlkId = (int)($r['block_id'] ?? 0);
                                        $rAppCode = (string)($r['application_code'] ?? '');
                                        $hasStaff = \App\Services\StaffRoutingService::hasStaffAssignedForCode($rDistId, $rBlkId, $rAppCode);
                                    ?>
                                    <?php if ($hasStaff): ?>
                                        <a href="/admin/staff-routing?district_id=<?= $rDistId ?>&block_id=<?= $rBlkId ?>&app_code=<?= e($rAppCode) ?>" class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none px-2 py-1" title="Staff Assigned. Click to view or manage.">
                                            <i class="bi bi-person-check-fill me-1"></i>Staff Assigned
                                        </a>
                                    <?php else: ?>
                                        <a href="/admin/staff-routing?district_id=<?= $rDistId ?>&block_id=<?= $rBlkId ?>&app_code=<?= e($rAppCode) ?>" class="badge bg-warning-subtle text-dark border border-warning text-decoration-none px-2 py-1" title="No Staff Assigned yet. Click to configure staff.">
                                            <i class="bi bi-exclamation-triangle me-1"></i>No Staff Assigned
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 text-center">
                                    <form method="POST" action="/admin/data-types/rate-rules/toggle" class="m-0 d-inline-flex align-items-center gap-1.5">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
                                        <span class="badge <?= $r['is_active'] ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>" style="font-size: 0.68rem; font-weight: 700;">
                                            <?= $r['is_active'] ? 'Active' : 'Disabled' ?>
                                        </span>
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" <?= $r['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()" style="cursor: pointer;" title="Toggle Active">
                                        </div>
                                    </form>
                                </td>
                                <td class="py-2.5 text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5"
                                                onclick='editRateRule(<?= json_encode($r) ?>)' title="Edit Rule">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </button>
                                        <form method="POST" action="/admin/data-types/rate-rules/delete" onsubmit="return confirm('Delete this block rate?');" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="data_type_id" value="<?= $dataType['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm px-1.5 py-0.5" title="Delete Rule">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="noSearchMatches" class="text-center py-4 text-muted d-none">
                <i class="bi bi-search fs-3 opacity-50"></i>
                <p class="mt-2 mb-0 small">No block rates matched your search filter.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================== -->

<?php require __DIR__ . '/service_rate_rules_modals.php'; ?>
<?php require __DIR__ . '/service_rate_rules_scripts.php'; ?>
