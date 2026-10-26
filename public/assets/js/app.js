/**
 * Welcome Bihar - Client Application JS
 * Cascading Dropdowns, Status Polling, Form Validation, and UX
 */

document.addEventListener('DOMContentLoaded', function () {
    // 0. Responsive Sidebar Toggle (Desktop Collapse & Mobile Drawer)
    const sidebar = document.getElementById('wbSidebar');
    const sidebarToggle = document.getElementById('wbSidebarToggle');
    const sidebarClose = document.getElementById('wbSidebarClose');
    const backdrop = document.getElementById('wbBackdrop');

    function isDesktop() {
        return window.innerWidth >= 992;
    }

    function toggleSidebar() {
        if (!sidebar) return;
        if (isDesktop()) {
            const isCurrentlyCollapsed = document.body.classList.toggle('sidebar-collapsed');
            document.documentElement.classList.toggle('sidebar-collapsed', isCurrentlyCollapsed);
            try {
                localStorage.setItem('wb_sidebar_collapsed', isCurrentlyCollapsed ? '1' : '0');
            } catch (e) {}
        } else {
            sidebar.classList.toggle('show');
            if (backdrop) backdrop.classList.toggle('show');
        }
    }

    function closeSidebar() {
        if (!sidebar) return;
        if (isDesktop()) {
            document.body.classList.add('sidebar-collapsed');
            document.documentElement.classList.add('sidebar-collapsed');
            try {
                localStorage.setItem('wb_sidebar_collapsed', '1');
            } catch (e) {}
        } else {
            sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
        }
    }

    // Restore desktop collapsed state on page load if set
    try {
        if (isDesktop() && localStorage.getItem('wb_sidebar_collapsed') === '1') {
            document.body.classList.add('sidebar-collapsed');
            document.documentElement.classList.add('sidebar-collapsed');
        }
    } catch (e) {}

    // Clean up classes on window resize
    window.addEventListener('resize', function () {
        if (isDesktop()) {
            if (sidebar) sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            try {
                if (localStorage.getItem('wb_sidebar_collapsed') === '1') {
                    document.body.classList.add('sidebar-collapsed');
                    document.documentElement.classList.add('sidebar-collapsed');
                } else {
                    document.body.classList.remove('sidebar-collapsed');
                    document.documentElement.classList.remove('sidebar-collapsed');
                }
            } catch (e) {}
        }
    });

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    // 1. Cascading District -> Block Loader (for AJAX-driven forms like user permissions)
    const districtSelect = document.getElementById('district_select');
    const blockSelect = document.getElementById('block_select');

    // Skip AJAX cascade if the form handles location cascading via server-side reload (like wizard location-service-form)
    if (districtSelect && blockSelect && (!districtSelect.form || districtSelect.form.id !== 'location-service-form')) {
        districtSelect.addEventListener('change', function () {
            const districtId = this.value;
            blockSelect.innerHTML = '<option value="">-- Loading blocks... --</option>';
            blockSelect.disabled = true;

            if (!districtId) {
                blockSelect.innerHTML = '<option value="">-- Select District First --</option>';
                return;
            }

            fetch('/api/blocks?district_id=' + encodeURIComponent(districtId))
                .then(response => response.json())
                .then(blocks => {
                    blockSelect.innerHTML = '<option value="">-- Select Block --</option>';
                    if (blocks && blocks.length > 0) {
                        blocks.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.id;
                            opt.textContent = b.name;
                            blockSelect.appendChild(opt);
                        });
                        blockSelect.disabled = false;

                        // Auto-select if preselected dataset
                        const preselected = blockSelect.getAttribute('data-selected');
                        if (preselected) {
                            blockSelect.value = preselected;
                        }
                    } else {
                        blockSelect.innerHTML = '<option value="">-- No blocks found for district --</option>';
                    }
                })
                .catch(err => {
                    console.error('Error fetching blocks:', err);
                    blockSelect.innerHTML = '<option value="">Error loading blocks</option>';
                });
        });

        // Trigger on page load only if options are not already loaded by server
        if (districtSelect.value && blockSelect.options.length <= 1) {
            districtSelect.dispatchEvent(new Event('change'));
        }
    }

    // 2. Real-time Status Polling for QR Payment
    const qrContainer = document.getElementById('payment-qr-container');
    if (qrContainer) {
        const orderId = qrContainer.getAttribute('data-order-id');
        const statusBadge = document.getElementById('payment-live-status');
        const statusMessage = document.getElementById('payment-status-message');
        const checkBtn = document.getElementById('btn-manual-check-status');

        let isPolling = true;
        let pollCount = 0;
        const maxPolls = 100; // ~5 minutes at 3s intervals
        let pollInterval = null;

        function stopPolling() {
            isPolling = false;
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }

        function checkPaymentStatus(isManual = false) {
            if (!isManual) {
                if (!isPolling) return;

                if (pollCount >= maxPolls) {
                    stopPolling();
                    if (statusBadge) {
                        statusBadge.className = 'badge bg-warning text-dark p-2 fs-6';
                        statusBadge.textContent = 'Verification Timeout / Pending';
                    }
                    if (statusMessage) {
                        statusMessage.className = 'alert alert-warning mt-3';
                        statusMessage.textContent = 'Live payment polling timed out after 5 minutes. If money was deducted from your UPI app, it will be automatically credited upon confirmation or click "Check Status Now" to verify.';
                    }
                    return;
                }

                pollCount++;
            }

            fetch('/wallet/topup/status?order_id=' + encodeURIComponent(orderId))
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'PAID') {
                        stopPolling();
                        if (statusBadge) {
                            statusBadge.className = 'badge bg-success p-2 fs-6';
                            statusBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Paid &amp; Verified';
                        }
                        if (statusMessage) {
                            statusMessage.className = 'alert alert-success mt-3';
                            statusMessage.textContent = '';
                            const strongEl = document.createElement('strong');
                            strongEl.textContent = (data.message || 'Payment confirmed!') + ' ';
                            statusMessage.appendChild(strongEl);
                            const textNode = document.createTextNode('Wallet credited. Redirecting to passbook in 2 seconds...');
                            statusMessage.appendChild(textNode);
                        }
                        // Redirect to passbook
                        setTimeout(() => {
                            window.location.href = '/wallet/passbook';
                        }, 2500);
                    } else if (data.status === 'FAILED') {
                        stopPolling();
                        if (statusBadge) {
                            statusBadge.className = 'badge bg-danger p-2 fs-6';
                            statusBadge.textContent = 'Payment Failed';
                        }
                        if (statusMessage) {
                            statusMessage.className = 'alert alert-danger mt-3';
                            statusMessage.textContent = data.message || 'Payment was marked failed by provider.';
                        }
                    } else {
                        // Still Pending
                        if (statusMessage) {
                            statusMessage.className = 'alert alert-info mt-3';
                            statusMessage.textContent = (data.message ? data.message + ' - ' : '') + 'Listening for verified merchant payment confirmation...';
                        }
                    }
                })
                .catch(err => {
                    console.error('Polling error:', err);
                });
        }

        // Run poll every 3.5 seconds
        pollInterval = setInterval(() => {
            if (isPolling) {
                checkPaymentStatus(false);
            } else {
                stopPolling();
            }
        }, 3500);

        if (checkBtn) {
            checkBtn.addEventListener('click', function () {
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking...';
                checkPaymentStatus(true);
                setTimeout(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Check Live Status';
                }, 2000);
            });
        }
    }

    // 3. Copy to clipboard helper
    document.querySelectorAll('.btn-copy-clipboard').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetText = this.getAttribute('data-copy');
            if (targetText) {
                navigator.clipboard.writeText(targetText).then(() => {
                    const originalHtml = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check2 me-1"></i> Copied!';
                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                    }, 2000);
                });
            }
        });
    });

    // 4. Client-side form confirmation on Rejection
    const rejectBtn = document.getElementById('btn-confirm-reject');
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function (e) {
            const reasonInput = document.getElementById('rejection_reason_input');
            if (reasonInput && !reasonInput.value.trim()) {
                e.preventDefault();
                alert('A mandatory rejection reason is required before submitting.');
                reasonInput.focus();
            }
        });
    }
});

// ========================================================
// GLOBAL 4-STEP RTPS STEPPER RENDERER (Available Everywhere)
// ========================================================
window.renderStepperHtml = function(app) {
    if (!app) return { dotsHtml: '', cardHtml: '', curr: 1, isRej: false };
    const status = (app.status || 'PENDING').toUpperCase();
    const remarks = ((app.admin_remarks || '') + ' ' + (app.remarks || '') + ' ' + (app.portal_status || '') + ' ' + (app.last_status_message || '')).toLowerCase();
    
    let curr = 1;
    const isRej = (status === 'REJECTED' || remarks.includes('rejected'));
    if (status === 'COMPLETED' || status === 'APPROVED' || status === 'ATTACHED' || app.result_file || app.certificate_path || app.certificate_file || (remarks.includes('delivered') && !remarks.includes('not available'))) {
        curr = 4;
    } else if (isRej) {
        curr = 1;
    } else if (status === 'IN_PROCESS' || status === 'UNDER_REVIEW' || status === 'UNAVAILABLE' || status === 'CHECKING' || app.portal_status) {
        if (remarks.includes('sign pending') || 
            remarks.includes('forwarded to ro/co') || 
            remarks.includes('forwarded to ro') || 
            remarks.includes('forwarded to co') || 
            remarks.includes('field verification: forwarded') || 
            remarks.includes('field verification') ||
            remarks.includes('forwarded') ||
            remarks.includes('digital signature pending') || 
            remarks.includes('sign baki hai')) {
            curr = 3;
        } else if (remarks.includes('verification') || remarks.includes('inquiry') || remarks.includes('under process') || remarks.includes('processing')) {
            curr = 2;
        } else {
            curr = 2;
        }
    } else {
        curr = 1; // PENDING
    }

    const steps = [
        { en: 'Application Submission', hi: 'आवेदन जमा' },
        { en: 'Form Verification', hi: 'प्रपत्र सत्यापन' },
        { en: 'Field Verification', hi: 'क्षेत्रीय जांच (Field Inquiry)' },
        { en: 'Approval & Issuance', hi: 'स्वीकृति एवं प्रमाण पत्र (RO/CO Sign)' }
    ];

    // 1. Compact dots
    let dotsHtml = '<div class="rtps-step-dots ms-1" title="' + (isRej ? 'Application Rejected' : (curr === 4 ? '4/4 Steps Completed' : (curr + '/4 Steps Completed'))) + '">';
    for (let i = 1; i <= 4; i++) {
        const isDone = (i <= curr && !isRej) || (isRej && i === 1);
        const isActive = (!isDone && i === (curr + 1) && !isRej);
        const dotClass = isDone ? 'done' : (isActive ? 'active' : 'pending');
        const lineClass = (i < curr && !isRej) ? 'done' : 'pending';
        const title = 'Step ' + i + ': ' + steps[i-1].en + ' (' + (isDone ? 'Completed' : (isActive ? 'In Progress' : 'Pending')) + ')';
        dotsHtml += '<span class="rtps-dot ' + dotClass + '" title="' + title + '"></span>';
        if (i < 4) {
            dotsHtml += '<span class="rtps-line ' + lineClass + '"></span>';
        }
    }
    const colorClass = curr === 4 ? 'text-success' : (isRej ? 'text-danger' : 'text-muted');
    dotsHtml += '<span class="rtps-step-text ' + colorClass + '">' + (isRej ? 'Rej' : (curr + '/4')) + '</span>';
    dotsHtml += '</div>';

    // 2. Full card stepper
    const pct = curr === 4 ? 100 : Math.round((curr - 1) / 3 * 100);
    let cardHtml = `
        <div class="card border rounded-3 p-3 bg-white shadow-2xs">
            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                <div class="small fw-bold text-dark d-flex align-items-center gap-1.5">
                    <i class="bi bi-diagram-3-fill text-primary"></i>
                    <span>RTPS 4-Step Process Progress</span>
                </div>
                <span class="badge ${curr === 4 ? 'bg-success' : (isRej ? 'bg-danger' : 'bg-primary')} px-2 py-0.5 rounded-pill" style="font-size: 0.7rem;">
                    ${isRej ? 'Rejected' : (curr === 4 ? '4/4 Completed' : (curr + '/4 Completed'))}
                </span>
            </div>
            <div class="rtps-stepper-lg">
                <div class="rtps-stepper-track"><div class="rtps-stepper-progress" style="width: ${pct}%;"></div></div>
    `;

    for (let i = 1; i <= 4; i++) {
        const isDone = (i <= curr && !isRej) || (isRej && i === 1);
        const isActive = (!isDone && i === (curr + 1) && !isRej);
        const circleClass = isDone ? 'done' : (isActive ? 'active' : 'pending');
        const icon = isDone ? '<i class="bi bi-check-lg"></i>' : i;
        let stLabel = isDone ? '<span class="text-success fw-bold">Done</span>' : (isActive ? '<span class="text-primary fw-bold">Active</span>' : '<span class="text-muted">Pending</span>');
        if (isRej && i === 2) stLabel = '<span class="text-danger fw-bold">Rejected</span>';

        cardHtml += `
            <div class="rtps-stepper-item">
                <div class="rtps-stepper-circle ${circleClass}">${icon}</div>
                <div class="fw-bold text-dark" style="font-size: 0.72rem;">${steps[i-1].en}</div>
                <div class="text-muted" style="font-size: 0.65rem;">${steps[i-1].hi}</div>
                <div style="font-size: 0.68rem;" class="mt-0.5">${stLabel}</div>
            </div>
        `;
    }

    cardHtml += `
            </div>
        </div>
    `;

    return { dotsHtml, cardHtml, curr, isRej };
};
