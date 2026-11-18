// =========================================================================
// Welcome Bihar - Gmail OTP Auto-Reader
// Target: https://mail.google.com/mail/u/0/#inbox
// Requested by RIOT:
// 1. Open https://mail.google.com/mail/u/0/#inbox
// 2. Click the latest panonline / UTI email row
// 3. Extract the 6-digit OTP from the "Code Requested" widget at the top
// =========================================================================

(function () {
  'use strict';

  let hasFoundOtp = false;
  let pollInterval = null;
  let attempts = 0;
  const MAX_ATTEMPTS = 50; // 50 * 500ms = 25s max
  let targetTriggerTime = Date.now();
  let rowClicked = false;

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

  // 1. Extract from the top "Code Requested" widget in Gmail (Image 2)
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

  // 2. Fallback: Extract from message body / general text
  function extractFromGeneralText(text) {
    if (!text) return null;
    const regexes = [
      /login\s+verification\s+code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /verification\s+code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /OTP\s+is\s*[:\-]?\s*(\d{6})/gi
    ];

    const results = [];
    regexes.forEach((rgx) => {
      let match;
      while ((match = rgx.exec(text)) !== null) {
        if (match[1] && /^\d{6}$/.test(match[1])) {
          results.push(match[1]);
        }
      }
    });

    return results.length > 0 ? results[results.length - 1] : null;
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

  function scanAndExtract() {
    if (hasFoundOtp) return null;

    // STEP A: Check for the "Code Requested" top widget (Image 2)
    const widgetCode = extractFromCodeRequestedWidget();
    if (widgetCode) {
      return { otp: widgetCode, source: 'code_requested_widget' };
    }

    // STEP B: Check open email thread / messages
    const openMessages = Array.from(document.querySelectorAll('.ii.gt, .a3s'));
    if (openMessages.length > 0) {
      for (let i = openMessages.length - 1; i >= 0; i--) {
        const bodyTxt = openMessages[i].innerText || '';
        const code = extractFromGeneralText(bodyTxt);
        if (code) {
          return { otp: code, source: 'open_message_body' };
        }
      }
    }

    // STEP C: If on Inbox / List View, locate and click the latest UTI email row
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
      const topRow = utiRows[0];
      rowClicked = true;
      showGmailBadge('⚡ Clicking latest UTI email row...', '#38bdf8');
      clickEmailRow(topRow);

      // Re-check snippet in case code is also visible
      const snippetCode = extractFromGeneralText(topRow.innerText || '');
      if (snippetCode) {
        return { otp: snippetCode, source: 'row_snippet' };
      }
    }

    return null;
  }

  function startOtpScanner() {
    if (hasFoundOtp) return;
    showGmailBadge('🔍 Scanning for UTI login email in Inbox...', '#38bdf8');

    if (pollInterval) clearInterval(pollInterval);

    pollInterval = setInterval(() => {
      attempts++;

      // Auto-refresh inbox if row not opened after 4 attempts
      if (attempts % 4 === 0 && !rowClicked) {
        triggerGmailRefresh();
      }

      const res = scanAndExtract();

      if (res && res.otp) {
        hasFoundOtp = true;
        clearInterval(pollInterval);
        showGmailBadge(`✅ Found Top OTP: ${res.otp} • Submitting to UTI Portal...`, '#10b981');

        try {
          chrome.runtime.sendMessage({ action: 'otpExtracted', code: res.otp }, () => {
            void chrome.runtime.lastError;
          });
        } catch (e) {}
      } else if (attempts >= MAX_ATTEMPTS) {
        clearInterval(pollInterval);
        showGmailBadge('⚠️ Could not auto-detect OTP. Please open the email and enter code.', '#f59e0b');
      }
    }, 500);
  }

  // Listener for background signal
  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg && msg.action === 'readOtpNow') {
      attempts = 0;
      hasFoundOtp = false;
      rowClicked = false;
      targetTriggerTime = msg.triggerTime || Date.now();
      startOtpScanner();
      sendResponse({ started: true });
      return true;
    }
    return false;
  });

  // Auto-init on page load if waiting
  chrome.storage.local.get(['isWaitingForOtp', 'otpRequestedAt'], (res) => {
    const isWaiting = Boolean(res && res.isWaitingForOtp);
    const reqTime = res && res.otpRequestedAt ? Number(res.otpRequestedAt) : Date.now();

    if (isWaiting && Date.now() - reqTime < 180000) {
      targetTriggerTime = reqTime;
      setTimeout(startOtpScanner, 300);
    }
  });
})();
