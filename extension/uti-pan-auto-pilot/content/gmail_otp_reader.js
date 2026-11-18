// =========================================================================
// Welcome Bihar - Gmail OTP Auto-Reader (5-Second Settle & Freshness Gate)
// Target: https://mail.google.com/mail/u/0/#inbox
// Requested by RIOT:
// - Email open hone ke 5 second BAAD OTP uthana taaki naya email aa sake
// - Purana OTP kabhi nahi uthana
// =========================================================================

(function () {
  'use strict';

  let hasFoundOtp = false;
  let isSettling = false;
  let countdownTimer = null;
  let rowClicked = false;
  let lastUsedOtp = '';

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

  // 1. Extract from the top "Code Requested" widget (Image 2)
  function extractFromCodeRequestedWidget() {
    const allElements = Array.from(document.querySelectorAll('div, section, article, tr, td'));
    const codeCard = allElements.find((el) => {
      const txt = (el.innerText || '').trim();
      return (
        txt.includes('Code Requested') &&
        (txt.includes('Copy code') || /\d\s+\d\s+\d/.test(txt))
      );
    });

    if (codeCard) {
      const text = codeCard.innerText || '';

      // Pattern 1: Separate digit boxes (e.g. "5  9  5  2  8  9")
      const digitMatch = text.match(/(\d)\s+(\d)\s+(\d)\s+(\d)\s+(\d)\s+(\d)/);
      if (digitMatch) {
        return `${digitMatch[1]}${digitMatch[2]}${digitMatch[3]}${digitMatch[4]}${digitMatch[5]}${digitMatch[6]}`;
      }

      // Pattern 2: Single 6-digit sequence near "Code Requested"
      const singleMatch = text.match(/Code\s+Requested[\s\S]*?\b(\d{6})\b/i);
      if (singleMatch) {
        return singleMatch[1];
      }

      // Pattern 3: Any 6 digits inside the card
      const genericMatch = text.match(/\b(\d{6})\b/);
      if (genericMatch) {
        return genericMatch[1];
      }
    }

    return null;
  }

  // 2. Fallback: Extract from latest message body in thread
  function extractFromOpenThread() {
    const openMessages = Array.from(document.querySelectorAll('.ii.gt, .a3s'));
    if (openMessages.length > 0) {
      for (let i = openMessages.length - 1; i >= 0; i--) {
        const bodyTxt = openMessages[i].innerText || '';
        const regexes = [
          /login\s+verification\s+code\s+is\s*[:\-]?\s*(\d{6})/gi,
          /verification\s+code\s+is\s*[:\-]?\s*(\d{6})/gi,
          /code\s+is\s*[:\-]?\s*(\d{6})/gi,
          /OTP\s+is\s*[:\-]?\s*(\d{6})/gi
        ];
        for (const rgx of regexes) {
          let match;
          let last = null;
          while ((match = rgx.exec(bodyTxt)) !== null) {
            if (match[1] && /^\d{6}$/.test(match[1])) {
              last = match[1];
            }
          }
          if (last) return last;
        }
      }
    }
    return null;
  }

  function clickEmailRow(row) {
    if (!row) return;
    const target =
      row.querySelector('.bog, .y6, .y2, .xS, span[email], .xT, td.yX') ||
      row;

    ['mousedown', 'mouseup', 'click'].forEach((type) => {
      try {
        target.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, view: window }));
      } catch (e) {}
    });
    try { row.click(); } catch (e) {}
  }

  function triggerGmailRefresh() {
    const refreshBtn = document.querySelector(
      'div[role="button"][data-tooltip*="Refresh" i], div[aria-label*="Refresh" i], ' +
      'div[data-tooltip="Refresh"], div[aria-label="Refresh"], div[act="20"]'
    );
    if (refreshBtn) {
      try {
        refreshBtn.click();
      } catch (e) {}
    }
  }

  function isOpenEmailView() {
    const widget = document.querySelector('div, section');
    const hasWidget = document.body && document.body.innerText.includes('Code Requested');
    const hasOpenMsgs = document.querySelectorAll('.ii.gt, .a3s').length > 0;
    const hasSubjectHeader = Boolean(document.querySelector('h2.hP, .ha h2'));
    return hasWidget || hasOpenMsgs || hasSubjectHeader;
  }

  function executeExtractionAfterWait() {
    if (hasFoundOtp) return;

    // A. Check top Code Requested widget
    let code = extractFromCodeRequestedWidget();

    // B. Fallback to latest message body in thread
    if (!code) {
      code = extractFromOpenThread();
    }

    if (code) {
      // Check if it's identical to previously submitted OTP
      if (lastUsedOtp && code === lastUsedOtp) {
        showGmailBadge(`⏳ Detected previous OTP (${code}) • Refreshing for fresh email...`, '#f59e0b');
        triggerGmailRefresh();
        setTimeout(executeExtractionAfterWait, 2000);
        return;
      }

      hasFoundOtp = true;
      lastUsedOtp = code;
      chrome.storage.local.set({ lastSubmittedOtp: code });

      showGmailBadge(`✅ Verified Fresh OTP: ${code} • Submitting to UTI Portal...`, '#10b981');

      try {
        chrome.runtime.sendMessage({ action: 'otpExtracted', code }, () => {
          void chrome.runtime.lastError;
        });
      } catch (e) {}
    } else {
      showGmailBadge('⏳ Waiting for email content to render... Refreshing...', '#38bdf8');
      triggerGmailRefresh();
      setTimeout(executeExtractionAfterWait, 1500);
    }
  }

  function startSettlingCountdown(seconds = 5) {
    if (isSettling) return;
    isSettling = true;

    let remaining = seconds;
    showGmailBadge(`⏳ Email open • Waiting ${remaining}s for newest email to arrive...`, '#38bdf8');

    if (countdownTimer) clearInterval(countdownTimer);

    countdownTimer = setInterval(() => {
      remaining--;

      // Halfway refresh to ensure Google fetches new email
      if (remaining === 2) {
        triggerGmailRefresh();
      }

      if (remaining > 0) {
        showGmailBadge(`⏳ Email open • Waiting ${remaining}s for newest email to arrive...`, '#38bdf8');
      } else {
        clearInterval(countdownTimer);
        showGmailBadge('⚡ 5s completed! Extracting fresh OTP code now...', '#10b981');
        setTimeout(executeExtractionAfterWait, 300);
      }
    }, 1000);
  }

  function monitorAndTriggerFlow() {
    if (hasFoundOtp || isSettling) return;

    // Check if we are already inside the open email view
    if (isOpenEmailView()) {
      startSettlingCountdown(5);
      return;
    }

    // Check if on Inbox list view
    const rows = Array.from(document.querySelectorAll('.zA, tr[role="row"]'));
    const utiRows = rows.filter((r) => {
      const t = (r.innerText || '').toLowerCase();
      return (
        t.includes('panonline') ||
        t.includes('utiitsl') ||
        t.includes('login verification code')
      );
    });

    if (utiRows.length > 0 && !rowClicked) {
      rowClicked = true;
      showGmailBadge('⚡ Clicking latest UTI email in Inbox...', '#38bdf8');
      clickEmailRow(utiRows[0]);

      // Start 5 second wait right after clicking row
      setTimeout(() => {
        startSettlingCountdown(5);
      }, 500);
    } else {
      showGmailBadge('🔍 Locating latest UTI email in Inbox...', '#38bdf8');
      triggerGmailRefresh();
    }
  }

  // Listener for background signal
  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg && msg.action === 'readOtpNow') {
      hasFoundOtp = false;
      isSettling = false;
      rowClicked = false;
      if (countdownTimer) clearInterval(countdownTimer);

      chrome.storage.local.get(['lastSubmittedOtp'], (res) => {
        lastUsedOtp = res.lastSubmittedOtp || '';
        showGmailBadge('🚀 Starting OTP extraction with 5s freshness delay...', '#38bdf8');
        monitorAndTriggerFlow();
      });

      sendResponse({ started: true });
      return true;
    }
    return false;
  });

  // Auto-init on page load if waiting
  chrome.storage.local.get(['isWaitingForOtp', 'otpRequestedAt', 'lastSubmittedOtp'], (res) => {
    const isWaiting = Boolean(res && res.isWaitingForOtp);
    const reqTime = res && res.otpRequestedAt ? Number(res.otpRequestedAt) : Date.now();
    lastUsedOtp = res && res.lastSubmittedOtp ? res.lastSubmittedOtp : '';

    if (isWaiting && Date.now() - reqTime < 180000) {
      setTimeout(monitorAndTriggerFlow, 600);
    }
  });

  // Re-check every 1.5 seconds if waiting
  setInterval(() => {
    if (!hasFoundOtp && !isSettling) {
      monitorAndTriggerFlow();
    }
  }, 1500);
})();
