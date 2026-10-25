<?php
$pageTitle = 'RTPS Certificate Queue & Tracking';
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-3">
    <!-- Header Breadcrumbs and Actions -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-muted">
                    <li class="breadcrumb-item"><a href="/admin/dashboard" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">RTPS Certificate Queue</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-check-fill text-success"></i>
                RTPS Certificate Automatic Check &amp; Attachment
            </h4>
            <p class="text-muted small mb-0">Track application certificate status, scheduled 12:00 AM portal checks, and PDF attachments.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="https://swcs.bihar.gov.in/RtpsReportView/certificate_download.jsp" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm rounded-2 fw-semibold shadow-2xs">
                <i class="bi bi-box-arrow-up-right me-1"></i> Official RTPS Portal
            </a>
            <?php if (\App\Services\AuthService::isAdmin()): ?>
                <button type="button" class="btn btn-primary btn-sm rounded-2 fw-bold shadow-2xs d-flex align-items-center gap-1.5" id="btnQueueRunWorker" onclick="runAdminBatchRtpsCheck(this)" title="Eligible applications ko abhi portal se check karke certificate download aur auto attach karein">
                    <i class="bi bi-magic"></i> <span>Auto Check &amp; Attach Now</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-light border border-info-subtle shadow-2xs rounded-3 py-2.5 px-3 mb-4">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-info-circle-fill text-info mt-1 fs-5"></i>
            <div class="small">
                <strong>Target Schedule:</strong> RTPS applications marked <code>IN_PROCESS</code> are automatically added to this queue and checked every night at <strong>12:00 AM (Asia/Kolkata)</strong>.
                When an operator or authorized session verifies and attaches the genuine certificate PDF, the application is automatically marked <strong>COMPLETED/APPROVED</strong> and the retailer receives an instant <strong>Download Certificate</strong> button.
                If unavailable or if a portal communication issue occurs, the application remains safely <strong>IN_PROCESS</strong>.
            </div>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-2xs rounded-3 h-100 bg-white border-start border-4 border-primary">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Total in Queue</span>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($stats['total_queued'] ?? 0) ?></h3>
                    <small class="text-muted" style="font-size: 0.72rem;">Tracked Applications</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-2xs rounded-3 h-100 bg-white border-start border-4 border-warning">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Scheduled (5-Hr)</span>
                    <h3 class="fw-bold mb-0 text-warning"><?= number_format($stats['pending_check'] ?? 0) ?></h3>
                    <small class="text-muted" style="font-size: 0.72rem;">5-Hour / 7 AM Cycle</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-2xs rounded-3 h-100 bg-white border-start border-4 border-info">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Due Now</span>
                    <h3 class="fw-bold mb-0 text-info"><?= number_format($stats['due_now'] ?? 0) ?></h3>
                    <small class="text-muted" style="font-size: 0.72rem;">Eligible for checking</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-2xs rounded-3 h-100 bg-white border-start border-4 border-secondary">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Unavailable</span>
                    <h3 class="fw-bold mb-0 text-secondary"><?= number_format($stats['unavailable'] ?? 0) ?></h3>
                    <small class="text-muted" style="font-size: 0.72rem;">Retrying every 5 hours</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-2xs rounded-3 h-100 bg-white border-start border-4 border-success">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Attached &amp; Done</span>
                    <h3 class="fw-bold mb-0 text-success"><?= number_format($stats['attached_completed'] ?? 0) ?></h3>
                    <small class="text-muted" style="font-size: 0.72rem;">PDF Attached &amp; Approved</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-2xs rounded-3 h-100 bg-white border-start border-4 border-danger">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Portal Retries</span>
                    <h3 class="fw-bold mb-0 text-danger"><?= number_format($stats['failed_retrying'] ?? 0) ?></h3>
                    <small class="text-muted" style="font-size: 0.72rem;">Network / Retrying</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card border-0 shadow-2xs rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="/admin/rtps-queue" id="filterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="App No (BRCCO...), Name..." value="<?= e($filters['search']) ?>">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-secondary mb-1">Queue Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="QUEUED" <?= $filters['status'] === 'QUEUED' ? 'selected' : '' ?>>Queued</option>
                            <option value="UNAVAILABLE" <?= $filters['status'] === 'UNAVAILABLE' ? 'selected' : '' ?>>Unavailable (5-Hr Retry)</option>
                            <option value="ATTACHED" <?= $filters['status'] === 'ATTACHED' ? 'selected' : '' ?>>Attached &amp; Completed</option>
                            <option value="FAILED" <?= $filters['status'] === 'FAILED' ? 'selected' : '' ?>>Portal Retry</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-secondary mb-1">District</label>
                        <select name="district_id" id="filterDistrict" class="form-select form-select-sm">
                            <option value="">All Districts</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $filters['district_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-secondary mb-1">Block</label>
                        <select name="block_id" id="filterBlock" class="form-select form-select-sm" <?= empty($blocks) ? 'disabled' : '' ?>>
                            <option value="">All Blocks</option>
                            <?php foreach ($blocks as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $filters['block_id'] === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm rounded-2 fw-semibold flex-grow-1">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <a href="/admin/rtps-queue" class="btn btn-light btn-sm border rounded-2 fw-semibold px-3">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Queue Table Card -->
    <div class="card border-0 shadow-2xs rounded-3 overflow-hidden bg-white mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-list-task text-primary"></i>
                Tracked RTPS Applications
                <span class="badge bg-secondary-subtle text-secondary border px-2 py-0.5 rounded-pill"><?= number_format($totalItems) ?></span>
            </h6>
            <small class="text-muted">Showing page <?= $currentPage ?> of <?= max(1, $totalPages) ?></small>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light text-secondary text-uppercase small" style="font-size: 0.72rem;">
                    <tr>
                        <th class="ps-3">Application Ref</th>
                        <th>Service / Code</th>
                        <th>Application Number</th>
                        <th>Applicant Name</th>
                        <th>Location</th>
                        <th>Queue Status</th>
                        <th>Next Check (12:00 AM)</th>
                        <th>Checks</th>
                        <th>Attached File</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No applications found in the RTPS queue matching the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td class="ps-3 fw-semibold">
                                    <a href="/admin/applications?search=<?= urlencode($row['app_ref']) ?>" class="text-decoration-none">
                                        <?= e($row['app_ref']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size: 0.72rem;">App #<?= (int)$row['application_id'] ?></div>
                                </td>

                                <td>
                                    <?php
                                        $displayCode = !empty($row['application_code']) && $row['application_code'] !== 'OTHER'
                                            ? $row['application_code']
                                            : (preg_match('/^([A-Z]{3,10})/i', (string)$row['application_number'], $m) ? strtoupper($m[1]) : ($row['application_code'] ?: 'RTPS'));
                                        $displayService = !empty($row['service_type']) && $row['service_type'] !== 'OTHER'
                                            ? $row['service_type']
                                            : (preg_match('/^(BCC|BRC|BIC|BOBC|NCL|EWS|RTPS)/i', (string)$row['application_number']) ? 'RTPS' : ($row['service_type'] ?: 'RTPS'));
                                    ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold px-2 py-1">
                                        <?= e($displayCode) ?>
                                    </span>
                                    <div class="text-muted mt-0.5" style="font-size: 0.7rem;">Portal: <?= e($displayService) ?></div>
                                </td>

                                <td>
                                    <span class="font-monospace fw-bold text-dark user-select-all"><?= e($row['application_number']) ?></span>
                                    <button type="button" class="btn btn-xs btn-link p-0 text-muted ms-1" onclick="navigator.clipboard.writeText('<?= e($row['application_number']) ?>'); alert('Copied Application Number: <?= e($row['application_number']) ?>');" title="Copy Number">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </td>

                                <td>
                                    <div class="fw-semibold text-dark user-select-all"><?= e($row['applicant_name']) ?></div>
                                    <small class="text-muted" style="font-size: 0.72rem;">Retailer: <?= e($row['retailer_name'] ?? 'N/A') ?></small>
                                </td>

                                <td>
                                    <div class="fw-medium text-dark"><?= e($row['district_name'] ?? 'N/A') ?></div>
                                    <small class="text-muted">&gt; <?= e($row['block_name'] ?? 'N/A') ?></small>
                                </td>

                                <td>
                                    <?php if ($row['status'] === 'ATTACHED'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Attached &amp; Completed
                                        </span>
                                    <?php elseif ($row['status'] === 'CHECKING'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                            <i class="bi bi-arrow-repeat spin me-1"></i> Checking...
                                        </span>
                                    <?php elseif ($row['status'] === 'UNAVAILABLE'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-clock-history me-1"></i> Unavailable (5-Hr Retry)
                                        </span>
                                    <?php elseif ($row['status'] === 'FAILED'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Portal Error
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                            <i class="bi bi-hourglass-split me-1"></i> Queued
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($row['last_status_message'])): ?>
                                        <div class="text-muted text-truncate mt-1" style="max-width: 170px; font-size: 0.68rem;" title="<?= e($row['last_status_message']) ?>">
                                            <?= e($row['last_status_message']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="fw-semibold text-dark" style="font-size: 0.78rem;">
                                        <?= date('d M Y, h:i A', strtotime($row['next_check_at'])) ?>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.7rem;">Asia/Kolkata (Every 5 Hours)</small>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= (int)$row['check_count'] ?></span>
                                </td>

                                <td>
                                    <?php if (!empty($row['certificate_file']) || !empty($row['result_file'])): ?>
                                        <?php $filePath = $row['certificate_file'] ?: $row['result_file']; ?>
                                        <a href="<?= e($filePath) ?>" target="_blank" download class="btn btn-xs btn-outline-success rounded-2 px-2 py-1 fw-bold text-decoration-none shadow-2xs">
                                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic" style="font-size: 0.72rem;">Pending Attachment</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($row['status'] !== 'ATTACHED'): ?>
                                            <button type="button" class="btn btn-primary fw-bold text-nowrap" title="1-Click Auto Download from Bihar RTPS & Attach" onclick="autoFetchFromQueue(<?= (int)$row['application_id'] ?>, this)">
                                                <i class="bi bi-cloud-arrow-down-fill me-0.5"></i> Auto Attach
                                            </button>
                                        <?php endif; ?>

                                        <a href="https://swcs.bihar.gov.in/RtpsReportView/certificate_download.jsp" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary" title="Open RTPS Portal to Check" onclick="navigator.clipboard.writeText('<?= e($row['application_number']) ?>');">
                                            <i class="bi bi-box-arrow-up-right"></i> Portal
                                        </a>

                                        <button type="button" class="btn btn-outline-success" title="Attach Downloaded Certificate PDF" onclick="openAttachModal(<?= (int)$row['application_id'] ?>, '<?= e($row['application_number']) ?>', '<?= e($row['applicant_name']) ?>')">
                                            <i class="bi bi-paperclip"></i> Upload
                                        </button>

                                        <?php if ($row['status'] !== 'ATTACHED'): ?>
                                            <button type="button" class="btn btn-outline-warning" title="Mark as not ready on portal (Retains IN_PROCESS, schedules next check in 5 hours / 7 AM)" onclick="openUnavailableModal(<?= (int)$row['application_id'] ?>, '<?= e($row['application_number']) ?>')">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted">Showing page <?= $currentPage ?> of <?= $totalPages ?></span>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $buildUrl = function($p) use ($filters) {
                            $q = array_merge($filters, ['page' => $p]);
                            return '/admin/rtps-queue?' . http_build_query(array_filter($q));
                        };
                        ?>
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildUrl($currentPage - 1) ?>">Previous</a>
                        </li>
                        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $buildUrl($p) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $buildUrl($currentPage + 1) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal 1: Attach Certificate PDF -->
<div class="modal fade" id="attachCertModal" tabindex="-1" aria-labelledby="attachCertModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="/admin/rtps-queue/attach" enctype="multipart/form-data" class="modal-content rounded-3 shadow">
            <input type="hidden" name="csrf_token" value="<?= \App\Services\AuthService::generateCsrfToken() ?>">
            <input type="hidden" name="app_id" id="attachAppId" value="">

            <div class="modal-header border-bottom py-2.5 px-3">
                <h6 class="modal-title fw-bold text-dark" id="attachCertModalLabel">
                    <i class="bi bi-file-earmark-arrow-up-fill text-success me-1"></i> Attach Official RTPS Certificate (PDF)
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3">
                <div class="alert alert-light border small py-2 px-3 rounded-2 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>Application: <strong id="attachAppNumberText" class="user-select-all font-monospace text-dark">-</strong></div>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-1.5" onclick="navigator.clipboard.writeText(document.getElementById('attachAppNumberText').textContent); alert('Application number copied!');">Copy</button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>Applicant (Title Case): <strong id="attachApplicantNameTitleText" class="user-select-all text-dark">-</strong></div>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-1.5" onclick="navigator.clipboard.writeText(document.getElementById('attachApplicantNameTitleText').textContent); alert('Applicant name copied!');">Copy</button>
                    </div>
                </div>

                <!-- 1-Click Auto Fetch Option -->
                <div class="card border-primary bg-primary-subtle p-3 mb-3 rounded-2 shadow-2xs">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div>
                            <div class="fw-bold text-primary small"><i class="bi bi-magic me-1"></i> 1-Click Auto Download from Bihar RTPS</div>
                            <div class="text-secondary" style="font-size: 0.72rem;">Portal se direct official signed PDF fetch aur attach karein</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary fw-bold text-nowrap px-3" id="btnQueueModalAutoFetch" onclick="triggerQueueModalAutoFetch()">
                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Attach
                        </button>
                    </div>
                    <div id="queueModalAutoFetchAlert" class="mt-2 small d-none"></div>
                </div>

                <div class="text-center my-2 text-muted fw-bold small" style="font-size: 0.7rem;">OR UPLOAD MANUALLY</div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">
                        Option A: Upload Certificate PDF File
                    </label>
                    <input type="file" name="certificate_pdf" id="attachCertFile" class="form-control rounded-2" accept=".pdf,application/pdf">
                    <small class="text-muted">Upload the original downloaded PDF certificate from your computer.</small>
                </div>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <hr class="flex-grow-1 my-0"><span class="small text-muted fw-bold">OR</span><hr class="flex-grow-1 my-0">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">
                        Option B: Paste Official Certificate URL
                    </label>
                    <input type="url" name="certificate_url" id="attachCertUrl" class="form-control rounded-2" placeholder="https://serviceonline.bihar.gov.in/officials/t/...">
                    <small class="text-muted">System will directly download and attach the genuine certificate from the government server.</small>
                </div>

                <div class="alert alert-info-subtle border border-info small py-2 px-3 rounded-2 mb-0">
                    <i class="bi bi-info-circle text-info me-1"></i>
                    Attaching this PDF will automatically:
                    <ul class="mb-0 ps-3 mt-1">
                        <li>Mark the application as <strong>COMPLETED/APPROVED</strong>.</li>
                        <li>Update the queue status to <strong>ATTACHED</strong>.</li>
                        <li>Enable the <strong>Download Certificate</strong> button for the retailer.</li>
                    </ul>
                </div>
            </div>

            <div class="modal-footer border-top py-2 px-3">
                <button type="button" class="btn btn-light btn-sm border rounded-2" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm rounded-2 fw-semibold px-3">
                    <i class="bi bi-check2-circle me-1"></i> Verify &amp; Attach Certificate
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Mark Unavailable (Next 5-Hour / 7:00 AM Check) -->
<div class="modal fade" id="unavailableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="/admin/rtps-queue/unavailable" class="modal-content rounded-3 shadow">
            <input type="hidden" name="csrf_token" value="<?= \App\Services\AuthService::generateCsrfToken() ?>">
            <input type="hidden" name="app_id" id="unavailableAppId" value="">

            <div class="modal-header border-bottom py-2.5 px-3">
                <h6 class="modal-title fw-bold text-dark">
                    <i class="bi bi-clock-history text-warning me-1"></i> Reschedule RTPS Check
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3">
                <p class="small text-muted mb-2">
                    Application <strong id="unavailableAppNumberText">-</strong> is currently pending on the RTPS portal.
                </p>
                <div class="mb-2">
                    <label class="form-label small fw-semibold text-secondary">Schedule Next Run</label>
                    <select name="schedule_preset" id="schedulePresetSelect" class="form-select form-select-sm" onchange="toggleCustomScheduleInput(this.value)">
                        <option value="5_hours" selected>Next 5 Hours (Auto Cycle)</option>
                        <option value="7_am_today">Today 7:00 AM (Due Now / Test)</option>
                        <option value="7_am_tomorrow">Tomorrow 7:00 AM (Morning Schedule)</option>
                        <option value="due_now">Immediate (Due Right Now)</option>
                        <option value="custom">Custom Date &amp; Time...</option>
                    </select>
                </div>
                <div class="mb-2 d-none" id="customDatetimeContainer">
                    <label class="form-label small fw-semibold text-secondary">Custom Datetime</label>
                    <input type="datetime-local" name="custom_datetime" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold text-secondary">Status Note</label>
                    <input type="text" name="reason" class="form-control form-control-sm rounded-2" value="Certificate not generated yet on RTPS portal (Application under process)" required>
                </div>
                <small class="text-success d-block">
                    <i class="bi bi-shield-check me-1"></i> Application will remain <strong>IN_PROCESS</strong> and will NOT be rejected.
                </small>
            </div>

            <div class="modal-footer border-top py-2 px-3">
                <button type="button" class="btn btn-light btn-sm border rounded-2" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning btn-sm rounded-2 fw-semibold">
                    <i class="bi bi-check2 me-1"></i> Confirm Reschedule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Trigger Scheduled Worker Now -->
<div class="modal fade" id="triggerWorkerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="/admin/rtps-queue/trigger" class="modal-content rounded-3 shadow">
            <input type="hidden" name="csrf_token" value="<?= \App\Services\AuthService::generateCsrfToken() ?>">

            <div class="modal-header border-bottom py-2.5 px-3">
                <h6 class="modal-title fw-bold text-dark">
                    <i class="bi bi-play-circle text-primary me-1"></i> Run Queue Worker (5-Hr Cycle)
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3">
                <p class="small text-muted mb-3">
                    Manually trigger the scheduled queue runner to evaluate eligible applications.
                </p>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="force" value="1" id="forceRunSwitch">
                    <label class="form-check-label small fw-semibold" for="forceRunSwitch">
                        Force check all queued items now
                    </label>
                </div>
                <small class="text-muted d-block" style="font-size: 0.72rem;">
                    Runs automatically every 5 hours (and daily 7:00 AM) Asia/Kolkata.
                </small>
            </div>

            <div class="modal-footer border-top py-2 px-3">
                <button type="button" class="btn btn-light btn-sm border rounded-2" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm rounded-2 fw-semibold">
                    <i class="bi bi-arrow-repeat me-1"></i> Run Worker
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toTitleCase(str) {
    return (str || '').toLowerCase().replace(/\b\w/g, function(txt) {
        return txt.toUpperCase();
    });
}

function openAttachModal(appId, appNumber, applicantName) {
    document.getElementById('attachAppId').value = appId;
    document.getElementById('attachAppNumberText').textContent = appNumber;
    document.getElementById('attachApplicantNameTitleText').textContent = toTitleCase(applicantName);
    document.getElementById('attachCertFile').value = '';
    document.getElementById('attachCertUrl').value = '';
    const alertBox = document.getElementById('queueModalAutoFetchAlert');
    if (alertBox) {
        alertBox.className = 'mt-2 small d-none';
        alertBox.innerHTML = '';
    }
    const afBtn = document.getElementById('btnQueueModalAutoFetch');
    if (afBtn) {
        afBtn.disabled = false;
        afBtn.innerHTML = '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Attach';
    }
    new bootstrap.Modal(document.getElementById('attachCertModal')).show();
}

function triggerQueueModalAutoFetch() {
    const appId = document.getElementById('attachAppId').value;
    const btn = document.getElementById('btnQueueModalAutoFetch');
    const alertBox = document.getElementById('queueModalAutoFetchAlert');
    if (!appId) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Fetching...';
    alertBox.className = 'mt-2 small alert alert-info p-2 mb-0';
    alertBox.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Bihar Government RTPS portal se certificate verify aur download ho raha hai...';

    const formData = new FormData();
    formData.append('application_id', appId);

    fetch('/admin/rtps-queue/auto-fetch', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alertBox.className = 'mt-2 small alert alert-success p-2 mb-0';
            alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + (data.message || 'Certificate attach ho gaya!');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Attach';
            alertBox.className = 'mt-2 small alert alert-warning p-2 mb-0';
            alertBox.innerHTML = '<i class="bi bi-info-circle me-1"></i> ' + (data.message || 'Certificate abhi portal par uplabdh nahi hai.');
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Auto Attach';
        alertBox.className = 'mt-2 small alert alert-danger p-2 mb-0';
        alertBox.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Error: ' + e.message;
    });
}

async function autoFetchFromQueue(appId, btn) {
    if (!appId) return;
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Fetching...';
    }

    try {
        const formData = new FormData();
        formData.append('application_id', appId);

        const res = await fetch('/admin/rtps-queue/auto-fetch', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Certificate Attached!',
                    text: data.message || 'Official certificate PDF downloaded and attached successfully!',
                    timer: 1800,
                    showConfirmButton: false
                });
            } else {
                alert(data.message || 'Certificate attached successfully!');
            }
            setTimeout(() => window.location.reload(), 1200);
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'RTPS Portal Notice',
                    text: data.message || 'Certificate not ready yet on RTPS portal. Next check scheduled in 5 hours.',
                    confirmButtonColor: '#0284c7'
                });
            } else {
                alert(data.message || 'Certificate pending on portal; next check scheduled in 5 hours.');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    } catch (e) {
        alert('Communication error: ' + e.message);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
}

function toggleCustomScheduleInput(val) {
    const container = document.getElementById('customDatetimeContainer');
    if (container) {
        if (val === 'custom') {
            container.classList.remove('d-none');
        } else {
            container.classList.add('d-none');
        }
    }
}

function openUnavailableModal(appId, appNumber) {
    document.getElementById('unavailableAppId').value = appId;
    document.getElementById('unavailableAppNumberText').textContent = appNumber;
    const preset = document.getElementById('schedulePresetSelect');
    if (preset) preset.value = '5_hours';
    toggleCustomScheduleInput('5_hours');
    new bootstrap.Modal(document.getElementById('unavailableModal')).show();
}

async function runAdminBatchRtpsCheck(btn) {
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> RTPS Portal Checking...';
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Checking RTPS Portal...',
            html: '<div class="py-2"><span class="spinner-border text-primary mb-2"></span><p class="small text-muted mb-0">Bihar Government RTPS portal par eligible applications verify aur certificates download ho rahe hain, kripya prateeksha karein...</p></div>',
            allowOutsideClick: false,
            showConfirmButton: false
        });
    }

    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('force', '1');
        formData.append('limit', '25');

        const res = await fetch('/admin/rtps-queue/trigger', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const data = await res.json();

        if (data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: data.ready > 0 ? 'success' : 'info',
                    title: data.ready > 0 ? 'Certificates Downloaded & Attached!' : 'RTPS Check Complete',
                    html: `
                        <div class="text-start p-2 small">
                            <p class="mb-2"><strong>Total Eligible Applications Checked:</strong> ${data.processed}</p>
                            <p class="mb-2 text-success"><strong>Certificates Downloaded &amp; Attached:</strong> <span class="badge bg-success fs-6">${data.ready}</span></p>
                            <p class="mb-0 text-secondary"><strong>Pending at Govt Office (Scheduled Every 5 Hours):</strong> ${data.unavailable}</p>
                        </div>
                    `,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#0284c7'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                alert(`Auto Check Complete! Checked: ${data.processed}, Attached: ${data.ready}`);
                window.location.reload();
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Notice',
                    text: data.message || 'Could not complete auto-check.'
                });
            } else {
                alert(data.message || 'Auto check failed.');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    } catch (e) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Communication Error',
                text: e.message || 'Failed to connect to RTPS checker endpoint.'
            });
        } else {
            alert('Error: ' + e.message);
        }
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const distSelect = document.getElementById('filterDistrict');
    const blkSelect = document.getElementById('filterBlock');

    if (distSelect && blkSelect) {
        distSelect.addEventListener('change', function () {
            const distId = this.value;
            blkSelect.innerHTML = '<option value="">All Blocks</option>';
            if (!distId) {
                blkSelect.disabled = true;
                return;
            }

            blkSelect.disabled = true;
            fetch('/api/blocks?district_id=' + encodeURIComponent(distId))
                .then(r => r.json())
                .then(blocks => {
                    blkSelect.innerHTML = '<option value="">All Blocks</option>';
                    if (Array.isArray(blocks) && blocks.length > 0) {
                        blocks.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.id;
                            opt.textContent = b.name + (b.name_hi ? ' (' + b.name_hi + ')' : '');
                            blkSelect.appendChild(opt);
                        });
                        blkSelect.disabled = false;
                    }
                })
                .catch(() => {
                    blkSelect.disabled = true;
                });
        });
    }

    // Automatic Live Checker if due items exist
    const dueCount = <?= (int)($stats['due_now'] ?? 0) ?>;
    if (dueCount > 0) {
        console.log('[RTPS Auto-Checker] ' + dueCount + ' due application(s) detected. Running auto verification in background...');
        setTimeout(() => {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('force', '0'); // check due items
            formData.append('limit', '10');

            fetch('/admin/rtps-queue/trigger', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.ready > 0) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Auto-Attached ' + data.ready + ' certificate(s)!',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                    setTimeout(() => window.location.reload(), 1500);
                }
            })
            .catch(e => console.warn('[RTPS Auto-Checker]', e));
        }, 1200);
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
