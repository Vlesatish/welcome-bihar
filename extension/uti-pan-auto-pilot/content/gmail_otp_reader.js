// =========================================================================
// Welcome Bihar - Gmail OTP Auto-Reader (Fresh / Current-Time Enforcement)
// Target: https://mail.google.com/*
// Purpose: Automatically click the fresh email row from panonline@utiitsl.com
// and extract the latest 6-digit verification code from the opened thread.
// =========================================================================

(function () {
  'use strict';

  let hasFoundOtp = false;
  let pollInterval = null;
  let attempts = 0;
  const MAX_ATTEMPTS = 50; // 50 * 800ms = 40s max
  let targetTriggerTime = Date.now();
  let rowClicked = false;

  function formatClock(ts) {
    const d = new Date(ts);
    let h = d.getHours();
    const m = String(d.getMinutes()).padStart(2, '0');
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m} ${ampm}`;
  }

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

  function isTimestampFresh(rawTimeStr, baseTimeMs) {
    if (!rawTimeStr) return false;
    const s = String(rawTimeStr).trim().toLowerCase();

    if (s.includes('just now') || s.includes('0 min') || s.includes('moments ago')) {
      return true;
    }
    const relMatch = s.match(/(\d+)\s*minutes?\s*ago/);
    if (relMatch) {
      const mins = parseInt(relMatch[1], 10);
      return mins <= 2;
    }

    const clockMatch = s.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(am|pm)?/i);
    if (clockMatch) {
      let h = parseInt(clockMatch[1], 10);
      const m = parseInt(clockMatch[2], 10);
      const ampm = (clockMatch[4] || '').toLowerCase();

      if (ampm === 'pm' && h < 12) h += 12;
      if (ampm === 'am' && h === 12) h = 0;

      const baseDate = new Date(baseTimeMs || Date.now());
      const emailTotalMins = h * 60 + m;
      const baseTotalMins = baseDate.getHours() * 60 + baseDate.getMinutes();

      let diff = baseTotalMins - emailTotalMins;
      if (diff < -720) diff += 1440;
      if (diff > 720) diff -= 1440;

      return diff >= -1 && diff <= 3;
    }

    const parsed = Date.parse(rawTimeStr);
    if (!isNaN(parsed)) {
      const diffMs = (baseTimeMs || Date.now()) - parsed;
      return diffMs >= -60000 && diffMs <= 180000;
    }

    return false;
  }

  function isRowFresh(row, baseTimeMs) {
    if (!row) return false;
    const txt = (row.innerText || '').toLowerCase();

    if (txt.includes('just now') || txt.includes('0 min') || txt.includes('1 min')) return true;

    // Check all clock time matches in row text
    const matches = txt.match(/(\d{1,2}):(\d{2})\s*(am|pm)?/gi);
    if (matches && matches.length > 0) {
      const lastTimeStr = matches[matches.length - 1];
      if (isTimestampFresh(lastTimeStr, baseTimeMs)) {
        return true;
      }
    }

    // Check date cell attributes directly
    const timeEl = row.querySelector('.xW, .bA4, td:last-child');
    if (timeEl) {
      const val = timeEl.innerText || timeEl.getAttribute('title') || '';
      if (isTimestampFresh(val, baseTimeMs)) return true;
    }

    return false;
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
      try { refreshBtn.click(); } catch (e) {}
    }
  }

  function findAndExtractOtp() {
    if (hasFoundOtp) return null;

    // 1. Check if inside an OPEN EMAIL THREAD
    const messageContainers = Array.from(document.querySelectorAll('.adn, .ads, div[role="listitem"]'));
    if (messageContainers.length > 0) {
      // Bottom-most container is the newest message
      for (let i = messageContainers.length - 1; i >= 0; i--) {
        const container = messageContainers[i];

        // If collapsed, expand it
        const expandBtn = container.querySelector('.adx, .kQ, .kv');
        if (expandBtn) {
          try { expandBtn.click(); } catch (e) {}
        }

        const bodyEl = container.querySelector('.ii.gt, .a3s') || container;
        const candidates = extractOtpCandidates(bodyEl.innerText || '');
        if (candidates.length > 0) {
          return { otp: candidates[candidates.length - 1], source: 'opened_thread' };
        }
      }
    }

    // Check full view text if on opened email page
    const fullBody = document.body ? document.body.innerText : '';
    if (fullBody.includes('panonline@utiitsl.com') && fullBody.includes('Login verification code')) {
      const fullCandidates = extractOtpCandidates(fullBody);
      if (fullCandidates.length > 0) {
        return { otp: fullCandidates[fullCandidates.length - 1], source: 'body_text' };
      }
    }

    // 2. Check SEARCH LIST / INBOX ROWS
    const rows = Array.from(document.querySelectorAll('.zA, tr[role="row"]'));
    const utiRows = rows.filter((r) => {
      const t = (r.innerText || '').toLowerCase();
      return t.includes('panonline') || t.includes('login verification code');
    });

    if (utiRows.length > 0) {
      const topRow = utiRows[0];
      const fresh = isRowFresh(topRow, targetTriggerTime);

      if (fresh) {
        showGmailBadge(`⚡ Fresh email row found (${formatClock(targetTriggerTime)}) • Opening thread...`, '#38bdf8');

        // Check if OTP happens to be in the snippet
        const snippetCandidates = extractOtpCandidates(topRow.innerText || '');
        if (snippetCandidates.length > 0) {
          return { otp: snippetCandidates[snippetCandidates.length - 1], source: 'snippet' };
        }

        // Click row to open thread and reveal full OTP
        if (!rowClicked) {
          rowClicked = true;
          clickEmailRow(topRow);
        }
      }
    }

    return null;
  }

  function startOtpScanner() {
    if (hasFoundOtp) return;

    const clockStr = formatClock(targetTriggerTime);
    showGmailBadge(`⏳ Scanning for email row from panonline@utiitsl.com (${clockStr})...`, '#38bdf8');

    if (pollInterval) clearInterval(pollInterval);

    pollInterval = setInterval(() => {
      attempts++;

      // Auto-refresh Gmail every 3 attempts to pull incoming mail
      if (attempts % 3 === 0 && !rowClicked) {
        triggerGmailRefresh();
      }

      const result = findAndExtractOtp();

      if (result && result.otp) {
        hasFoundOtp = true;
        clearInterval(pollInterval);
        showGmailBadge(`✅ Found Fresh OTP: ${result.otp} • Submitting to UTI Portal...`, '#10b981');

        try {
          chrome.runtime.sendMessage({ action: 'otpExtracted', code: result.otp }, () => {
            void chrome.runtime.lastError;
          });
        } catch (e) {}
      } else {
        showGmailBadge(
          `⏳ Awaiting fresh email row for ${clockStr} (${attempts}/${MAX_ATTEMPTS})...`,
          '#38bdf8'
        );

        if (attempts >= MAX_ATTEMPTS) {
          clearInterval(pollInterval);
          showGmailBadge('⚠️ Could not open email or find OTP. Click the email row manually.', '#f59e0b');
        }
      }
    }, 800);
  }

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

  chrome.storage.local.get(['isWaitingForOtp', 'otpRequestedAt'], (res) => {
    const isWaiting = Boolean(res && res.isWaitingForOtp);
    const reqTime = res && res.otpRequestedAt ? Number(res.otpRequestedAt) : Date.now();

    if (isWaiting && Date.now() - reqTime < 180000) {
      targetTriggerTime = reqTime;
      setTimeout(startOtpScanner, 400);
    }
  });
})();
