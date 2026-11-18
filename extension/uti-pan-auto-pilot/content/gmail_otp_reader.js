// =========================================================================
// Welcome Bihar - Gmail OTP Auto-Reader
// Target: https://mail.google.com/*
// Purpose: Automatically find latest UTI login verification code from panonline@utiitsl.com
// =========================================================================

(function () {
  'use strict';

  let hasFoundOtp = false;
  let pollInterval = null;
  let attempts = 0;
  const MAX_ATTEMPTS = 40; // 40 * 800ms = 32s max

  function showGmailBadge(msg, color = '#10b981') {
    let badge = document.getElementById('wb-gmail-otp-badge');
    if (!badge) {
      badge = document.createElement('div');
      badge.id = 'wb-gmail-otp-badge';
      badge.style.cssText =
        'position:fixed;top:15px;right:25px;z-index:999999999;background:#0f172a;color:#fff;padding:12px 20px;' +
        'border-radius:10px;font-family:system-ui,-apple-system,sans-serif;font-size:13px;font-weight:600;' +
        'box-shadow:0 8px 30px rgba(0,0,0,0.6);display:flex;align-items:center;gap:10px;border-left:5px solid #10b981;';
      document.body.appendChild(badge);
    }
    badge.style.borderLeftColor = color;
    badge.innerHTML = `<span>${msg}</span>`;
  }

  function extractOtpCandidates(text) {
    if (!text) return [];
    const results = [];
    const regexes = [
      /login\s+verification\s+code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /verification\s+code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /OTP\s+is\s*[:\-]?\s*(\d{6})/gi
    ];

    regexes.forEach((rgx) => {
      let match;
      while ((match = rgx.exec(text)) !== null) {
        if (match[1] && /^\d{6}$/.test(match[1])) {
          results.push(match[1]);
        }
      }
    });

    return results;
  }

  function findLatestOtp() {
    if (hasFoundOtp) return null;

    // 1. Check expanded email bodies first (latest message in thread is at bottom)
    const messageBodies = Array.from(document.querySelectorAll('.ii.gt, .adn, div[data-message-id]'));
    if (messageBodies.length > 0) {
      // Check from bottom-most (newest) message upward
      for (let i = messageBodies.length - 1; i >= 0; i--) {
        const bodyTxt = messageBodies[i].innerText || '';
        const found = extractOtpCandidates(bodyTxt);
        if (found.length > 0) {
          return found[found.length - 1];
        }
      }
    }

    // 2. Check full document text (take the very last occurrence)
    const fullText = (document.body ? document.body.innerText : '') || '';
    if (fullText.toLowerCase().includes('panonline') || fullText.toLowerCase().includes('verification code')) {
      const allFound = extractOtpCandidates(fullText);
      if (allFound.length > 0) {
        return allFound[allFound.length - 1];
      }
    }

    // 3. Check inbox or search list rows (.zA / role="row")
    const rows = Array.from(document.querySelectorAll('.zA, tr[role="row"]'));
    const utiRow = rows.find((r) => {
      const t = (r.innerText || '').toLowerCase();
      return t.includes('panonline') || t.includes('login verification code');
    });

    if (utiRow) {
      const rowTxt = utiRow.innerText || '';
      const rowCandidates = extractOtpCandidates(rowTxt);
      if (rowCandidates.length > 0) {
        return rowCandidates[rowCandidates.length - 1];
      }
      // If code not visible in row snippet, click the row to open the thread
      try {
        utiRow.click();
      } catch (e) {}
    }

    // 4. Try clicking Gmail refresh button if still not found after 5 attempts
    if (attempts > 4 && attempts % 4 === 0) {
      const refreshBtn = document.querySelector('div[role="button"][data-tooltip*="Refresh" i], div[aria-label*="Refresh" i]');
      if (refreshBtn) {
        try { refreshBtn.click(); } catch (e) {}
      }
    }

    return null;
  }

  function startOtpScanner() {
    if (hasFoundOtp) return;
    showGmailBadge('🔍 Scanning for latest UTI login verification code...', '#38bdf8');

    pollInterval = setInterval(() => {
      attempts++;
      const otp = findLatestOtp();

      if (otp) {
        hasFoundOtp = true;
        clearInterval(pollInterval);
        showGmailBadge(`✅ Found OTP: ${otp} • Transferring to UTI Portal...`, '#10b981');

        try {
          chrome.runtime.sendMessage({ action: 'otpExtracted', code: otp }, (resp) => {
            void chrome.runtime.lastError;
          });
        } catch (e) {}
      } else if (attempts >= MAX_ATTEMPTS) {
        clearInterval(pollInterval);
        showGmailBadge('⚠️ OTP not found yet. Please click on the email or refresh.', '#f59e0b');
      }
    }, 800);
  }

  // Listen for trigger from background
  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg && msg.action === 'readOtpNow') {
      attempts = 0;
      hasFoundOtp = false;
      startOtpScanner();
      sendResponse({ started: true });
      return true;
    }
    return false;
  });

  // Check if waiting for OTP via storage or URL hash
  chrome.storage.local.get(['isWaitingForOtp'], (res) => {
    const isWaiting = Boolean(res && res.isWaitingForOtp);
    const isSearchUrl = window.location.href.toLowerCase().includes('panonline');

    if (isWaiting || isSearchUrl) {
      setTimeout(startOtpScanner, 600);
    }
  });
})();
