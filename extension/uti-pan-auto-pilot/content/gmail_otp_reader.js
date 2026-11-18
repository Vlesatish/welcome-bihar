// =========================================================================
// Welcome Bihar - Gmail OTP Auto-Reader (Fresh / Current-Time Enforcement)
// Target: https://mail.google.com/*
// Purpose: Only extract OTP from emails arriving at the CURRENT session time
// Ignores all stale/old emails from earlier turns or previous attempts
// =========================================================================

(function () {
  'use strict';

  let hasFoundOtp = false;
  let pollInterval = null;
  let attempts = 0;
  const MAX_ATTEMPTS = 50; // 50 * 1000ms = 50s max
  let targetTriggerTime = Date.now();

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

  // Checks if a timestamp string belongs to the CURRENT time (within 2-3 mins of trigger)
  function isTimestampFresh(rawTimeStr, baseTimeMs) {
    if (!rawTimeStr) return false;
    const s = String(rawTimeStr).trim().toLowerCase();

    // 1. Explicit relative time (e.g. "just now", "0 minutes ago", "1 minute ago")
    if (s.includes('just now') || s.includes('0 min') || s.includes('moments ago')) {
      return true;
    }
    const relMatch = s.match(/(\d+)\s*minutes?\s*ago/);
    if (relMatch) {
      const mins = parseInt(relMatch[1], 10);
      return mins <= 2; // Only accept if sent within last 0-2 minutes
    }

    // 2. Clock time (e.g. "2:23 PM", "02:23 pm", "14:23")
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
      // Midnight boundary wrap
      if (diff < -720) diff += 1440;
      if (diff > 720) diff -= 1440;

      // Accept if difference between current time and email is within -1 to +2 minutes
      return diff >= -1 && diff <= 2;
    }

    // 3. Absolute date parse
    const parsed = Date.parse(rawTimeStr);
    if (!isNaN(parsed)) {
      const diffMs = (baseTimeMs || Date.now()) - parsed;
      return diffMs >= -60000 && diffMs <= 180000;
    }

    return false;
  }

  function getMessageTimestampText(msgContainer) {
    if (!msgContainer) return '';
    // Check .g3 (Gmail's standard timestamp class), title attribute, or time elements
    const timeEls = Array.from(msgContainer.querySelectorAll('.g3, .gH span, span[title], time, .xW span'));
    for (const el of timeEls) {
      const title = el.getAttribute('title') || '';
      const aria = el.getAttribute('aria-label') || '';
      const text = el.innerText || '';
      if (isTimestampFresh(title, targetTriggerTime)) return title;
      if (isTimestampFresh(aria, targetTriggerTime)) return aria;
      if (isTimestampFresh(text, targetTriggerTime)) return text;
    }
    // Also check entire message header text
    const header = msgContainer.querySelector('.gE, .kv, .adn');
    if (header) {
      const t = header.innerText || '';
      const m = t.match(/(\d{1,2}:\d{2}\s*(?:am|pm)?|\d+\s*minutes?\s*ago|just now)/i);
      if (m && isTimestampFresh(m[1], targetTriggerTime)) {
        return m[1];
      }
    }
    return '';
  }

  function findFreshOtp() {
    if (hasFoundOtp) return null;

    // A. Check open email thread: inspect individual message containers (.adn.ads or div[role="listitem"])
    const messageContainers = Array.from(document.querySelectorAll('.adn, .ads, div[role="listitem"], .ii.gt'));
    if (messageContainers.length > 0) {
      // Check from newest (bottom) message upward
      for (let i = messageContainers.length - 1; i >= 0; i--) {
        const container = messageContainers[i];
        const timeStr = getMessageTimestampText(container);

        // If message has a fresh timestamp or is explicitly the active newest message with fresh text
        if (timeStr && isTimestampFresh(timeStr, targetTriggerTime)) {
          // If collapsed, expand it
          const expandBtn = container.querySelector('.adx, .kQ, .kv');
          if (expandBtn) {
            try { expandBtn.click(); } catch (e) {}
          }
          const bodyEl = container.querySelector('.ii.gt, .a3s') || container;
          const candidates = extractOtpCandidates(bodyEl.innerText || '');
          if (candidates.length > 0) {
            return { otp: candidates[candidates.length - 1], time: timeStr };
          }
        }
      }
    }

    // B. Check Inbox or Search List rows (.zA / tr[role="row"])
    const rows = Array.from(document.querySelectorAll('.zA, tr[role="row"]'));
    const utiRows = rows.filter((r) => {
      const t = (r.innerText || '').toLowerCase();
      return t.includes('panonline') || t.includes('login verification code');
    });

    if (utiRows.length > 0) {
      // Top row is the most recent
      const topRow = utiRows[0];
      const rowTimeEl = topRow.querySelector('.xW span, .bA4 span, td:last-child');
      const rowTimeText = rowTimeEl ? rowTimeEl.innerText : '';

      if (isTimestampFresh(rowTimeText, targetTriggerTime)) {
        // Fresh email arrived in list!
        const rowCandidates = extractOtpCandidates(topRow.innerText || '');
        if (rowCandidates.length > 0) {
          return { otp: rowCandidates[rowCandidates.length - 1], time: rowTimeText };
        }
        // Click to open thread and read body
        try {
          topRow.click();
        } catch (e) {}
      }
    }

    return null;
  }

  function triggerGmailRefresh() {
    // Click Gmail standard refresh button
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

  function startOtpScanner() {
    if (hasFoundOtp) return;

    const clockStr = formatClock(targetTriggerTime);
    showGmailBadge(`⏳ Waiting for NEW email from panonline@utiitsl.com (Time: ${clockStr})...`, '#38bdf8');

    if (pollInterval) clearInterval(pollInterval);

    pollInterval = setInterval(() => {
      attempts++;

      // Trigger Gmail refresh every 3 seconds to pull incoming email
      if (attempts % 3 === 0) {
        triggerGmailRefresh();
      }

      const result = findFreshOtp();

      if (result && result.otp) {
        hasFoundOtp = true;
        clearInterval(pollInterval);
        showGmailBadge(`✅ Verified Fresh OTP: ${result.otp} (${result.time || clockStr}) • Submitting to UTI...`, '#10b981');

        try {
          chrome.runtime.sendMessage({ action: 'otpExtracted', code: result.otp }, () => {
            void chrome.runtime.lastError;
          });
        } catch (e) {}
      } else {
        showGmailBadge(
          `⏳ Awaiting NEW email for ${clockStr} (${attempts}/${MAX_ATTEMPTS}) • Refreshing inbox...`,
          '#38bdf8'
        );

        if (attempts >= MAX_ATTEMPTS) {
          clearInterval(pollInterval);
          showGmailBadge('⚠️ Fresh email not received yet. Check registered email or click Resend OTP.', '#f59e0b');
        }
      }
    }, 1000);
  }

  // Listen for trigger from background
  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg && msg.action === 'readOtpNow') {
      attempts = 0;
      hasFoundOtp = false;
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

    // Only start if requested in last 2 minutes
    if (isWaiting && Date.now() - reqTime < 120000) {
      targetTriggerTime = reqTime;
      setTimeout(startOtpScanner, 500);
    }
  });
})();
