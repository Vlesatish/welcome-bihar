<?php
if (!defined('SMARTPDS_WIDGET_LOADED')) :
    define('SMARTPDS_WIDGET_LOADED', true);
?>
<style>
.spds-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    overflow: hidden;
}
.spds-header {
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 0.75rem 1rem;
}
.spds-meta-label {
    font-size: 0.7rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-weight: 600;
}
.spds-meta-val {
    font-size: 0.84rem;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
}
.spds-timeline-wrap {
    display: flex;
    gap: 0.85rem;
    overflow-x: auto;
    padding: 0.75rem 0.25rem 0.5rem;
}
.spds-step-card {
    flex: 1 1 200px;
    min-width: 195px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem;
    position: relative;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.spds-step-node {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}
.spds-step-node-done {
    background: #f59e0b;
    color: #ffffff;
    box-shadow: 0 0 0 3px #fef3c7;
}
.spds-step-node-rej {
    background: #dc2626;
    color: #ffffff;
    box-shadow: 0 0 0 3px #fee2e2;
}
.spds-step-node-wait {
    background: #cbd5e1;
    color: #334155;
}
</style>

<script>
(function () {
    if (window.__smartPdsWidgetReady) return;
    window.__smartPdsWidgetReady = true;

    function spdsEsc(str) {
        const d = document.createElement('div');
        d.textContent = str === null || str === undefined ? '' : String(str);
        return d.innerHTML;
    }

    window.isSmartPdsApp = function (appNum, appCode) {
        const num = String(appNum || '').trim().toUpperCase();
        const code = String(appCode || '').trim().toUpperCase();
        if (/^RC\d{6,}/i.test(num)) return true;
        if (['EPDS', 'RATION', 'RATION_CARD', 'RC', 'SMARTPDS'].includes(code) && num.startsWith('RC')) return true;
        return false;
    };

    window.parseSmartPdsJson = function (raw) {
        if (!raw) return null;
        if (typeof raw === 'object') return raw;
        try {
            const parsed = JSON.parse(String(raw));
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) {
            return null;
        }
    };

    window.renderSmartPdsPanel = function (container, rawData, opts) {
        if (!container) return;
        opts = opts || {};
        const data = window.parseSmartPdsJson(rawData);
        const appId = opts.appId ? Number(opts.appId) : 0;
        const rcNo = opts.rcNo || (data && data.rc_id) || '';
        const endpoint = opts.endpoint || '/applications/smartpds-track';

        if (!data) {
            container.classList.remove('d-none');
            container.innerHTML = `
                <div class="spds-card mb-3">
                    <div class="spds-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-card-checklist text-primary fs-5"></i>
                            <div>
                                <div class="fw-bold text-dark small">Bihar SmartPDS Ration Card Live Status</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Application No: <span class="font-monospace fw-bold">${spdsEsc(rcNo || '--')}</span> &bull; Status abhi sync nahi hua hai</div>
                            </div>
                        </div>
                        ${appId > 0 ? `
                            <button type="button" class="btn btn-sm btn-primary fw-bold rounded-pill px-3" onclick="checkSmartPdsStatus(${appId}, this, '${spdsEsc(endpoint)}', true)">
                                <i class="bi bi-arrow-repeat me-1"></i> Check Live Status (SmartPDS)
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
            return;
        }

        const norm = String(data.normalized_status || 'IN_PROCESS').toUpperCase();
        const statusLabel = data.application_status || (norm === 'APPROVED' ? 'APPROVED' : (norm === 'REJECTED' ? 'Rejected' : 'In Process'));
        let badgeHtml = '';
        let iconHtml = '';
        let borderAccent = '#bfdbfe';

        if (norm === 'APPROVED') {
            badgeHtml = `<span class="badge bg-success-subtle text-success border border-success-subtle fs-6 px-2.5 py-1 fw-bold">${spdsEsc(statusLabel)}</span>`;
            iconHtml = `<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white" style="width:36px;height:36px;"><i class="bi bi-check-lg fs-5"></i></span>`;
            borderAccent = '#86efac';
        } else if (norm === 'REJECTED') {
            badgeHtml = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-6 px-2.5 py-1 fw-bold">${spdsEsc(statusLabel)}</span>`;
            iconHtml = `<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger text-white" style="width:36px;height:36px;"><i class="bi bi-x-lg fs-5"></i></span>`;
            borderAccent = '#fca5a5';
        } else {
            badgeHtml = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-2.5 py-1 fw-bold">${spdsEsc(statusLabel)}</span>`;
            iconHtml = `<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white" style="width:36px;height:36px;"><i class="bi bi-hourglass-split fs-5"></i></span>`;
        }

        const timeline = Array.isArray(data.timeline) ? data.timeline : [];
        let timelineHtml = '<div class="text-muted small py-2">Timeline abhi SmartPDS portal par available nahi hai.</div>';
        if (timeline.length > 0) {
            timelineHtml = `<div class="spds-timeline-wrap">` + timeline.map(step => {
                const stText = String(step.status || '').toUpperCase();
                const isDone = (stText === 'COMPLETED' || stText === 'APPROVED');
                const stepTitle = String(step.step_name || '');
                const isRejStep = stepTitle.toLowerCase().includes('reject');
                const pillClass = isRejStep
                    ? 'bg-danger-subtle text-danger border-danger-subtle'
                    : (isDone ? 'bg-success-subtle text-success border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border-warning-subtle');
                const pillText = isRejStep ? 'Rejected' : (step.status || 'Completed');
                const nodeClass = isRejStep ? 'spds-step-node-rej' : (isDone ? 'spds-step-node-done' : 'spds-step-node-wait');
                const nodeIcon = isRejStep ? '<i class="bi bi-x"></i>' : (isDone ? '<i class="bi bi-check-lg"></i>' : '<i class="bi bi-clock"></i>');

                return `
                    <div class="spds-step-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="spds-step-node ${nodeClass}">${nodeIcon}</span>
                            <span class="badge border ${pillClass}" style="font-size: 0.68rem;">${spdsEsc(pillText)}</span>
                        </div>
                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">${spdsEsc(step.step_name || 'Step')}</div>
                        <div class="text-muted mb-1" style="font-size: 0.72rem;">${spdsEsc(step.date || '')}</div>
                        ${step.office_name ? `<div style="font-size: 0.73rem;"><span class="text-muted">Office:</span> <span class="text-dark fw-medium">${spdsEsc(step.office_name)}</span></div>` : ''}
                        ${step.office_code ? `<div style="font-size: 0.73rem;"><span class="text-muted">Code:</span> <span class="text-dark fw-medium">${spdsEsc(step.office_code)}</span></div>` : ''}
                        ${step.by_user ? `<div style="font-size: 0.73rem;"><span class="text-muted">By:</span> <span class="text-dark fw-semibold">${spdsEsc(step.by_user)}</span></div>` : ''}
                    </div>
                `;
            }).join('') + `</div>`;
        }

        container.classList.remove('d-none');
        container.innerHTML = `
            <div class="spds-card mb-3" style="border-color: ${borderAccent};">
                <div class="spds-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        ${iconHtml}
                        <div>
                            <div class="spds-meta-label">SmartPDS Application Status <a href="https://br.smartpds.nic.in/track" target="_blank" class="text-primary text-decoration-none ms-1">(br.smartpds.nic.in)</a></div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mt-0.5">
                                ${badgeHtml}
                                ${data.checked_at ? `<span class="text-muted" style="font-size: 0.7rem;">Synced: ${spdsEsc(data.checked_at)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                    ${appId > 0 ? `
                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold rounded-pill px-3 bg-white" onclick="checkSmartPdsStatus(${appId}, this, '${spdsEsc(endpoint)}', true)">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Live Status
                        </button>
                    ` : ''}
                </div>

                <div class="p-3">
                    ${data.note ? `<div class="alert ${norm === 'REJECTED' ? 'alert-danger' : 'alert-light border'} py-1.5 px-2.5 small mb-3"><strong>Note:</strong> ${spdsEsc(data.note)}</div>` : ''}

                    <div class="row g-3 pb-3 border-bottom">
                        <div class="col-6 col-md-4">
                            <div class="spds-meta-label">Application Number</div>
                            <div class="spds-meta-val font-monospace">${spdsEsc(data.rc_id || '--')}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="spds-meta-label">Acknowledgement Number</div>
                            <div class="spds-meta-val font-monospace">${spdsEsc(data.acknowledgement_number || '--')}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="spds-meta-label">Application Date</div>
                            <div class="spds-meta-val">${spdsEsc(data.application_date || '--')}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="spds-meta-label">Form Type</div>
                            <div class="spds-meta-val text-primary">${spdsEsc(data.form_type || '--')}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="spds-meta-label">Ration Card Number</div>
                            <div class="spds-meta-val font-monospace">${spdsEsc(data.ration_card_number || '--')}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="spds-meta-label">Existing Ration Card No</div>
                            <div class="spds-meta-val font-monospace">${spdsEsc(data.existing_rc_number || '--')}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="spds-meta-label">Scheme / HOF / FPS ID</div>
                            <div class="spds-meta-val">${spdsEsc(data.scheme_name || '--')} &bull; ${spdsEsc(data.hof_name || '--')} &bull; <span class="font-monospace">${spdsEsc(data.fps_id || '--')}</span></div>
                        </div>
                    </div>

                    <div class="pt-3">
                        <div class="spds-meta-label mb-1"><i class="bi bi-diagram-3 me-1 text-primary"></i> Timeline Tracker</div>
                        ${timelineHtml}
                    </div>
                </div>
            </div>
        `;
    };

    window.checkSmartPdsStatus = async function (appId, btn, endpoint, reloadOnFinish) {
        if (!appId) return;
        endpoint = endpoint || '/applications/smartpds-track';
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking...';
        }

        try {
            const fd = new FormData();
            fd.append('application_id', appId);
            const res = await fetch(endpoint, { method: 'POST', body: fd });
            const data = await res.json();

            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }

            if (!data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'SmartPDS Check', text: data.message || 'Record not found on SmartPDS portal.' });
                } else {
                    alert(data.message || 'SmartPDS check failed.');
                }
                return data;
            }

            // Update any inline container inside active details modal
            ['detailSmartPdsContainer', 'modalDetailsSmartPdsContainer', 'adminDashSmartPdsContainer', 'appDetailSmartPdsContainer'].forEach(cid => {
                const el = document.getElementById(cid);
                if (el && data.smartpds) {
                    window.renderSmartPdsPanel(el, data.smartpds, { appId: appId, endpoint: endpoint });
                }
            });

            // Show full status popup if invoked from row button (reloadOnFinish !== true) or prompt reload
            if (typeof Swal !== 'undefined') {
                const tmpDiv = document.createElement('div');
                tmpDiv.style.textAlign = 'left';
                window.renderSmartPdsPanel(tmpDiv, data.smartpds, { appId: 0 });
                Swal.fire({
                    title: 'Ration Card Live Status (SmartPDS)',
                    html: tmpDiv.innerHTML,
                    width: '860px',
                    confirmButtonText: 'OK & Refresh List',
                    confirmButtonColor: '#2563eb'
                }).then(() => {
                    window.location.reload();
                });
            } else if (reloadOnFinish) {
                window.location.reload();
            }
            return data;
        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Network error while checking SmartPDS.' });
            } else {
                alert('Error: ' + err.message);
            }
        }
    };

    window.runQuickSmartPdsLookup = async function () {
        const input = document.getElementById('quickSmartPdsInput');
        const out = document.getElementById('quickSmartPdsResult');
        const btn = document.getElementById('btnQuickSmartPdsSearch');
        if (!input || !out) return;

        const rcNo = input.value.trim().toUpperCase();
        if (!rcNo) {
            out.innerHTML = '<div class="alert alert-warning small py-2 mb-0">Kripya Application / RC Number (e.g. RC2026062700018149355) daalein.</div>';
            return;
        }

        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Searching...';
        }
        out.innerHTML = '<div class="text-center py-4 text-muted small"><span class="spinner-border text-primary mb-2"></span><div>SmartPDS Portal (br.smartpds.nic.in) se live data fetch ho raha hai...</div></div>';

        try {
            const fd = new FormData();
            fd.append('rc_no', rcNo);
            const res = await fetch('/admin/applications/smartpds-track', { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success || !data.data) {
                out.innerHTML = `<div class="alert alert-danger small py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>${spdsEsc(data.message || 'Application not found on SmartPDS.')}</div>`;
            } else {
                window.renderSmartPdsPanel(out, data.data, { appId: 0 });
            }
        } catch (e) {
            out.innerHTML = `<div class="alert alert-danger small py-2 mb-0">Error: ${spdsEsc(e.message)}</div>`;
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    };
})();
</script>

<?php if (!empty($includeSmartPdsQuickModal)): ?>
<div class="modal fade" id="modalSmartPdsQuickTrack" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom py-2.5 px-4 bg-light">
                <div>
                    <h5 class="modal-title h6 fw-bold mb-0">
                        <i class="bi bi-search text-primary me-1"></i> Track Ration Card Application (SmartPDS Bihar)
                    </h5>
                    <div class="text-muted" style="font-size: 0.72rem;">Direct live check from <a href="https://br.smartpds.nic.in/track" target="_blank">br.smartpds.nic.in/track</a></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form onsubmit="event.preventDefault(); runQuickSmartPdsLookup();" class="row g-2 align-items-end mb-3">
                    <div class="col-md-8">
                        <label for="quickSmartPdsInput" class="form-label small fw-bold text-dark mb-1">Enter Application / RC No. <span class="text-danger">*</span></label>
                        <input type="text" id="quickSmartPdsInput" class="form-control font-monospace fw-semibold" placeholder="e.g. RC2026062700018149355">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" id="btnQuickSmartPdsSearch" class="btn btn-warning fw-bold px-4 flex-grow-1 text-dark">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                        <button type="button" class="btn btn-outline-secondary fw-semibold px-3" onclick="document.getElementById('quickSmartPdsInput').value=''; document.getElementById('quickSmartPdsResult').innerHTML='';">
                            Reset
                        </button>
                    </div>
                </form>
                <div class="d-flex gap-2 flex-wrap align-items-center mb-3 small">
                    <span class="text-muted fw-semibold">Test Samples:</span>
                    <button type="button" class="btn btn-xs btn-outline-success rounded-pill font-monospace" onclick="document.getElementById('quickSmartPdsInput').value='RC2026062700018149355'; runQuickSmartPdsLookup();">
                        RC2026062700018149355 (Approved)
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-danger rounded-pill font-monospace" onclick="document.getElementById('quickSmartPdsInput').value='RC2026062700011772091'; runQuickSmartPdsLookup();">
                        RC2026062700011772091 (Rejected)
                    </button>
                </div>
                <div id="quickSmartPdsResult"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
