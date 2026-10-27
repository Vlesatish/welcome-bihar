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
// GLOBAL 4-STEP RTPS STEPPER RENDERER (Available Everywhere)
// ========================================================
window.renderStepperHtml = function(app) {
    if (!app) return { dotsHtml: '', cardHtml: '', curr: 1, isRej: false };
    const status = (app.status || 'PENDING').toUpperCase();
    const rawRejReason = (app.rejection_reason || '').trim();
    const remarks = ((app.admin_remarks || '') + ' ' + (app.remarks || '') + ' ' + (app.portal_status || '') + ' ' + (app.last_status_message || '')).toLowerCase();
    
    const isRej = (status === 'REJECTED' || rawRejReason.length > 0 || remarks.includes('reject') || remarks.includes('अस्वीकृत'));
    
    // Extract clean rejection reason
    let rejReason = '';
    if (isRej) {
        const candidates = [
            rawRejReason,
            app.remarks || '',
            app.admin_remarks || '',
            app.last_status_message || ''
        ];
        for (let c of candidates) {
            if (!c) continue;
            let clean = String(c).replace(/^(?:rejected on portal:\s*|rejected on official portal:\s*|rejected:\s*)/i, '').trim();
            if (clean && !['view', 'na', 'application has been rejected on official portal.'].includes(clean.toLowerCase())) {
                rejReason = clean;
                break;
            }
        }
        if (!rejReason) {
            rejReason = 'आवेदक/आवेदिका द्वारा संलग्न प्रपत्र/दस्तावेज सत्यापन में अस्वीकृत (Verification criteria not fulfilled)';
        }
    }

    let curr = 1;
    if (status === 'COMPLETED' || status === 'APPROVED' || status === 'ATTACHED' || app.result_file || app.certificate_path || app.certificate_file || (remarks.includes('delivered') && !remarks.includes('not available'))) {
        curr = 4;
    } else if (isRej) {
        curr = 3; // Bihar RTPS: Rejected at Step 3 (Field Verification)
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
        { en: 'Form Details Verification', hi: 'प्रपत्र सत्यापन' },
        { en: 'Field Verification', hi: 'क्षेत्रीय जांच (Field Inquiry)' },
        { en: 'Approval & Certificate', hi: 'स्वीकृति एवं प्रमाण पत्र (RO/CO Sign)' }
    ];

    const escapeStr = function(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    // 1. Compact dots
    const summaryTitle = isRej 
        ? ('Application Rejected: ' + rejReason)
        : (curr === 4 ? '4/4 Steps Completed' : (curr + '/4 Steps Completed'));

    let dotsHtml = '<div class="rtps-step-dots ms-1" title="' + escapeStr(summaryTitle) + '">';
    for (let i = 1; i <= 4; i++) {
        let dotClass = 'pending';
        let lineClass = 'pending';
        let title = '';

        if (isRej) {
            if (i === 1) {
                dotClass = 'done';
                lineClass = 'done';
                title = 'Step 1: Application Submission (Completed)';
            } else if (i === 2) {
                dotClass = 'done';
                lineClass = 'rejected';
                title = 'Step 2: Form Details Verification (Completed)';
            } else if (i === 3) {
                dotClass = 'rejected';
                lineClass = 'rejected';
                title = 'Step 3: Field Verification (REJECTED: ' + rejReason + ')';
            } else {
                dotClass = 'rejected';
                lineClass = 'rejected';
                title = 'Step 4: Approval & Certificate (Rejected - Certificate Not Generated)';
            }
        } else {
            const isDone = (i <= curr);
            const isActive = (!isDone && i === (curr + 1));
            dotClass = isDone ? 'done' : (isActive ? 'active' : 'pending');
            lineClass = (i < curr) ? 'done' : 'pending';
            title = 'Step ' + i + ': ' + steps[i-1].en + ' (' + (isDone ? 'Completed' : (isActive ? 'In Progress' : 'Pending')) + ')';
        }

        dotsHtml += '<span class="rtps-dot ' + dotClass + '" title="' + escapeStr(title) + '"></span>';
        if (i < 4) {
            dotsHtml += '<span class="rtps-line ' + lineClass + '"></span>';
        }
    }

    if (isRej) {
        dotsHtml += '<span class="rtps-step-text text-danger" title="' + escapeStr('Rejected: ' + rejReason) + '">3/4 Rej</span>';
    } else {
        const colorClass = curr === 4 ? 'text-success' : 'text-muted';
        dotsHtml += '<span class="rtps-step-text ' + colorClass + '">' + (curr + '/4') + '</span>';
    }
    dotsHtml += '</div>';

    // 2. Full card stepper
    const pct = isRej ? 66 : (curr === 4 ? 100 : Math.round((curr - 1) / 3 * 100));
    const badgeText = isRej ? 'Rejected (3/4 Rej)' : (curr === 4 ? '4/4 Completed' : (curr + '/4 In Progress'));
    const badgeClass = isRej ? 'bg-danger' : (curr === 4 ? 'bg-success' : 'bg-primary');

    let cardHtml = `
        <div class="card border rounded-3 p-3 bg-white shadow-2xs">
            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                <div class="small fw-bold text-dark d-flex align-items-center gap-1.5">
                    <i class="bi bi-diagram-3-fill text-primary"></i>
                    <span>RTPS 4-Step Process Progress</span>
                </div>
                <span class="badge ${badgeClass} px-2.5 py-1 rounded-pill" style="font-size: 0.72rem;">
                    ${badgeText}
                </span>
            </div>
            <div class="rtps-stepper-lg">
                <div class="rtps-stepper-track"><div class="rtps-stepper-progress ${isRej ? 'bg-danger' : ''}" style="width: ${pct}%;"></div></div>
    `;

    for (let i = 1; i <= 4; i++) {
        let circleClass = 'pending';
        let icon = i;
        let stLabel = '<span class="text-muted">Pending</span>';

        if (isRej) {
            if (i === 1) {
                circleClass = 'done';
                icon = '<i class="bi bi-check-lg"></i>';
                stLabel = '<span class="text-success fw-bold">Completed</span>';
            } else if (i === 2) {
                circleClass = 'done';
                icon = '<i class="bi bi-check-lg"></i>';
                stLabel = '<span class="text-success fw-bold">Completed</span>';
            } else if (i === 3) {
                circleClass = 'rejected';
                icon = '<i class="bi bi-x-lg"></i>';
                stLabel = '<span class="text-danger fw-bold">Rejected</span>';
            } else {
                circleClass = 'rejected';
                icon = '<i class="bi bi-x-lg"></i>';
                stLabel = '<span class="text-danger fw-bold">Not Issued</span>';
            }
        } else {
            const isDone = (i <= curr);
            const isActive = (!isDone && i === (curr + 1));
            circleClass = isDone ? 'done' : (isActive ? 'active' : 'pending');
            icon = isDone ? '<i class="bi bi-check-lg"></i>' : i;
            stLabel = isDone ? '<span class="text-success fw-bold">Completed</span>' : (isActive ? '<span class="text-primary fw-bold">In Progress</span>' : '<span class="text-muted">Pending</span>');
        }

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
    `;

    if (isRej && rejReason) {
        cardHtml += `
            <div class="alert alert-danger border border-danger-subtle d-flex align-items-start gap-2 py-2 px-3 mt-3 mb-0 rounded-3" style="font-size: 0.8rem;">
                <i class="bi bi-exclamation-octagon-fill text-danger fs-5 mt-0.5 flex-shrink-0"></i>
                <div>
                    <div class="fw-bold text-danger">Rejection Reason (अस्वीकृति का कारण):</div>
                    <div class="text-dark mt-0.5">${escapeStr(rejReason)}</div>
                </div>
            </div>
        `;
    }

    cardHtml += `
        </div>
    `;

    return { dotsHtml, cardHtml, curr, isRej, rejReason };
};
