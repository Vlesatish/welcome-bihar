<?php require dirname(__DIR__) . '/layouts/header.php'; ?>

<?php
// Helper to choose aesthetic color scheme and icon per audit action
function getAuditActionBadge(string $action): array {
    $actionUpper = strtoupper($action);

    if (str_starts_with($actionUpper, 'WALLET_') || str_starts_with($actionUpper, 'TOPUP_') || str_starts_with($actionUpper, 'PAYMENT_') || str_starts_with($actionUpper, 'REFUND_') || str_starts_with($actionUpper, 'FEE_')) {
        return [
            'class' => 'bg-success-subtle text-success border border-success-subtle',
            'icon'  => 'bi-wallet2',
            'label' => $action
        ];
    }

    if (str_starts_with($actionUpper, 'DATA_TYPE_') || str_starts_with($actionUpper, 'RATE_RULE_') || str_starts_with($actionUpper, 'SERVICE_') || str_starts_with($actionUpper, 'FIELD_')) {
        return [
            'class' => 'bg-primary-subtle text-primary border border-primary-subtle',
            'icon'  => 'bi-sliders',
            'label' => $action
        ];
    }

    if (str_contains($actionUpper, 'REJECT') || str_starts_with($actionUpper, 'DELETE_') || str_starts_with($actionUpper, 'DISABLE_') || str_contains($actionUpper, 'SUSPEND') || str_contains($actionUpper, 'SECURITY')) {
        return [
            'class' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'icon'  => 'bi-shield-x',
            'label' => $action
        ];
    }

    if (str_contains($actionUpper, 'APPROV') || str_contains($actionUpper, 'STATUS') || str_contains($actionUpper, 'PROCESS') || str_contains($actionUpper, 'SUBMIT')) {
        return [
            'class' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
            'icon'  => 'bi-arrow-repeat',
            'label' => $action
        ];
    }

    if (str_starts_with($actionUpper, 'USER_') || str_starts_with($actionUpper, 'OPERATOR_') || str_starts_with($actionUpper, 'LOGIN_') || str_starts_with($actionUpper, 'AUTH_')) {
        return [
            'class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            'icon'  => 'bi-person-badge',
            'label' => $action
        ];
    }

    if (str_starts_with($actionUpper, 'LOCATION_') || str_starts_with($actionUpper, 'DISTRICT_') || str_starts_with($actionUpper, 'BLOCK_')) {
        return [
            'class' => 'bg-teal-subtle text-teal border border-teal-subtle',
            'icon'  => 'bi-geo-alt-fill',
            'label' => $action
        ];
    }

    return [
        'class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        'icon'  => 'bi-activity',
        'label' => $action
    ];
}
?>

<!-- Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 g-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3" style="width: 42px; height: 42px;">
                <i class="bi bi-shield-check fs-4"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold text-dark mb-0">Audit Trail</h1>
                <p class="text-muted small mb-0">Immutable Security, Administrative & Transaction Event Logs</p>
            </div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border px-3 py-2 fw-semibold" style="font-size: 0.82rem;">
            <i class="bi bi-list-check me-1 text-primary"></i>
            <?= number_format(count($logs)) ?> Records <?= isset($totalCount) && $totalCount > count($logs) ? "(of {$totalCount})" : '' ?>
        </span>
        <a href="/admin/audit-logs" class="btn btn-outline-secondary btn-sm px-2.5 py-1.5 fw-semibold" title="Refresh Logs">
            <i class="bi bi-arrow-clockwise"></i>
        </a>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/audit-logs" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="action" class="form-control border-start-0 ps-0" placeholder="Search by action, ID, or detail keywords..." value="<?= e($action ?? '') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="target_type" class="form-select">
                    <option value="">-- All Target Types --</option>
                    <?php if (!empty($availableTargetTypes)): ?>
                        <?php foreach ($availableTargetTypes as $tType): ?>
                            <option value="<?= e($tType) ?>" <?= ($targetType ?? '') === $tType ? 'selected' : '' ?>><?= e($tType) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="APPLICATION" <?= ($targetType ?? '') === 'APPLICATION' ? 'selected' : '' ?>>APPLICATION</option>
                        <option value="DATA_TYPE" <?= ($targetType ?? '') === 'DATA_TYPE' ? 'selected' : '' ?>>DATA_TYPE</option>
                        <option value="RATE_RULE" <?= ($targetType ?? '') === 'RATE_RULE' ? 'selected' : '' ?>>RATE_RULE</option>
                        <option value="WALLET" <?= ($targetType ?? '') === 'WALLET' ? 'selected' : '' ?>>WALLET</option>
                        <option value="TOPUP_ORDER" <?= ($targetType ?? '') === 'TOPUP_ORDER' ? 'selected' : '' ?>>TOPUP_ORDER</option>
                        <option value="USER" <?= ($targetType ?? '') === 'USER' ? 'selected' : '' ?>>USER</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($action) || !empty($targetType)): ?>
                    <a href="/admin/audit-logs" class="btn btn-light border fw-semibold rounded-3 px-3" title="Clear Filters">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-shield-slash fs-1 opacity-50"></i>
                <h6 class="fw-bold mt-2">No Audit Records Found</h6>
                <p class="small text-muted mb-0">No events matched the selected filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                    <thead class="table-light border-bottom" style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-3 py-3" style="width: 60px;">ID</th>
                            <th class="py-3" style="min-width: 190px;">Action / Event</th>
                            <th class="py-3" style="min-width: 120px;">Target</th>
                            <th class="py-3" style="min-width: 160px;">Actor / Role</th>
                            <th class="py-3" style="min-width: 110px;">IP Address</th>
                            <th class="py-3" style="min-width: 250px;">Details</th>
                            <th class="py-3 text-end pe-3" style="min-width: 160px;">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($logs as $log): ?>
                            <?php 
                                $badgeInfo = getAuditActionBadge($log['action'] ?? '');
                                $decodedDetails = null;
                                if (!empty($log['details_json'])) {
                                    $decodedDetails = json_decode($log['details_json'], true);
                                }
                                $actorName = $log['actor_name'] ?: 'System / Guest';
                                $initials = strtoupper(substr($actorName, 0, 2));
                            ?>
                            <tr>
                                <td class="ps-3 py-2.5 font-monospace text-muted small">
                                    #<?= e($log['id']) ?>
                                </td>
                                <td class="py-2.5">
                                    <span class="badge <?= $badgeInfo['class'] ?> font-monospace fw-bold py-1.5 px-2.5 rounded-2 d-inline-flex align-items-center gap-1.5 text-nowrap" style="font-size: 0.73rem; letter-spacing: 0.3px;">
                                        <i class="bi <?= $badgeInfo['icon'] ?>"></i>
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5">
                                    <div class="d-inline-flex flex-column">
                                        <span class="badge bg-light text-dark border font-monospace fw-semibold px-2 py-0.5" style="font-size: 0.72rem; width: fit-content;">
                                            <?= e($log['target_type'] ?: 'GENERAL') ?>
                                        </span>
                                        <?php if (!empty($log['target_id'])): ?>
                                            <span class="font-monospace text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                #<?= e($log['target_id']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-2.5">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-light border text-secondary fw-bold d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.7rem; flex-shrink: 0;">
                                            <?= e($initials) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark lh-sm" style="font-size: 0.82rem;"><?= e($actorName) ?></div>
                                            <?php if (!empty($log['actor_role'])): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border px-1.5 py-0 mt-0.5" style="font-size: 0.65rem; font-weight: 600;">
                                                    <?= e($log['actor_role']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5">
                                    <span class="badge bg-light text-secondary border font-monospace px-2 py-1" style="font-size: 0.72rem;">
                                        <i class="bi bi-hdd-network text-muted me-1"></i><?= e($log['ip_address'] ?: '127.0.0.1') ?>
                                    </span>
                                </td>
                                <td class="py-2.5">
                                    <?php if (is_array($decodedDetails) && !empty($decodedDetails)): ?>
                                        <div class="d-flex flex-wrap align-items-center gap-1">
                                            <?php 
                                                $shownCount = 0;
                                                $maxPills = 3;
                                            ?>
                                            <?php foreach ($decodedDetails as $k => $v): ?>
                                                <?php 
                                                    if ($shownCount >= $maxPills) break;
                                                    $valStr = is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? json_encode($v) : (string)$v);
                                                    if (strlen($valStr) > 30) {
                                                        $valStr = substr($valStr, 0, 27) . '...';
                                                    }
                                                    $shownCount++;
                                                ?>
                                                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.72rem; font-weight: 500;">
                                                    <span class="text-secondary fw-bold"><?= e($k) ?>:</span> <?= e($valStr) ?>
                                                </span>
                                            <?php endforeach; ?>

                                            <?php if (count($decodedDetails) > $maxPills || strlen($log['details_json']) > 80): ?>
                                                <button type="button" class="btn btn-outline-primary btn-sm px-1.5 py-0 rounded-2 fw-semibold" style="font-size: 0.7rem;" onclick='openJsonModal(<?= htmlspecialchars(json_encode($log['details_json']), ENT_QUOTES) ?>, "<?= e($log['action']) ?> #<?= $log['id'] ?>")'>
                                                    <i class="bi bi-code-slash me-0.5"></i>All (<?= count($decodedDetails) ?>)
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (!empty($log['details_json'])): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5 rounded-2 font-monospace" style="font-size: 0.72rem;" onclick='openJsonModal(<?= htmlspecialchars(json_encode($log['details_json']), ENT_QUOTES) ?>, "<?= e($log['action']) ?> #<?= $log['id'] ?>")'>
                                            <i class="bi bi-file-earmark-code me-1"></i>View Raw Data
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 text-end pe-3 text-nowrap">
                                    <div class="fw-semibold text-dark small" style="font-size: 0.8rem;">
                                        <?= date('d M Y', strtotime($log['created_at'])) ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.73rem;">
                                        <i class="bi bi-clock me-1 opacity-75"></i><?= date('h:i:s A', strtotime($log['created_at'])) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Clean JSON Details Modal -->
<div class="modal fade" id="jsonDetailsModal" tabindex="-1" aria-labelledby="jsonModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3" style="width: 36px; height: 36px;">
                        <i class="bi bi-file-earmark-text-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0 fs-6" id="jsonModalTitle">Event Details</h6>
                        <div class="text-muted" style="font-size: 0.74rem;">Formatted payload inspection</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <span class="small fw-bold text-secondary text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Structured Data Payload</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2 rounded-2 fw-semibold" id="btnCopyJson" onclick="copyJsonData()" style="font-size: 0.72rem;">
                        <i class="bi bi-clipboard me-1"></i>Copy JSON
                    </button>
                </div>
                <div class="border rounded-3 bg-white p-3 shadow-2xs" style="max-height: 380px; overflow-y: auto;">
                    <pre id="jsonPayloadDisplay" class="m-0 font-monospace text-dark" style="font-size: 0.78rem; white-space: pre-wrap; word-break: break-word;"></pre>
                </div>
            </div>
            <div class="modal-footer py-2 px-4 bg-white border-top">
                <button type="button" class="btn btn-light border px-3 rounded-2 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRawJson = '';

function openJsonModal(rawJson, title) {
    currentRawJson = rawJson;
    document.getElementById('jsonModalTitle').textContent = title || 'Event Details';
    const displayEl = document.getElementById('jsonPayloadDisplay');
    
    try {
        const parsed = (typeof rawJson === 'string') ? JSON.parse(rawJson) : rawJson;
        displayEl.textContent = JSON.stringify(parsed, null, 2);
    } catch(e) {
        displayEl.textContent = rawJson;
    }

    const modal = new bootstrap.Modal(document.getElementById('jsonDetailsModal'));
    modal.show();
}

function copyJsonData() {
    const displayEl = document.getElementById('jsonPayloadDisplay');
    const btn = document.getElementById('btnCopyJson');
    if (!displayEl) return;

    navigator.clipboard.writeText(displayEl.textContent).then(() => {
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = origHtml; }, 2000);
    }).catch(err => {
        alert('Failed to copy to clipboard.');
    });
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
