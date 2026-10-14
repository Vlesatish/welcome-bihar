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
