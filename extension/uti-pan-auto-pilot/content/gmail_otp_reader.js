// =========================================================================
// Welcome Bihar - Gmail OTP Auto-Reader (Open Mail + Code Requested Box)
// Target: https://mail.google.com/mail/u/0/#inbox
// Features:
// - Strictly ignores hidden Gmail SPA DOM nodes
// - Goes to Mail List (#inbox), refreshes, OPENS the top/latest UTI email
// - Waits 5s for newest email & "Code Requested" widget to settle
// - Reads OTP primarily from the visible "Code Requested" box (fallback: latest visible message, EN/HI)
// - If previous OTP detected -> waits 5s -> returns to Mail List (#inbox) -> refreshes -> re-opens latest mail
// =========================================================================

(function () {
  'use strict';

  if (window.__wbGmailOtpReaderLoaded) return;
  window.__wbGmailOtpReaderLoaded = true;

  let isOtpWatchActive = false;
  let hasFoundOtp = false;
  let isSettling = false;
  let isReturningToMailList = false;
  let countdownTimer = null;
  let returnToListTimer = null;
  let rowClicked = false;
  let emptyExtractionCount = 0;
  let returnLoopCount = 0;
  let otpWatchStartedAt = 0;
  const MAX_RETURN_LOOPS = 5;
  const MAX_OTP_WATCH_MS = 90000;
  let lastUsedOtp = '';
  let isLowBalanceSendInProgress = false;
  let hasSentLowBalanceEmail = false;

  function stopOtpWatchSession(hideBadge = true) {
    isOtpWatchActive = false;
    isSettling = false;
    isReturningToMailList = false;
    rowClicked = false;
    emptyExtractionCount = 0;
    returnLoopCount = 0;
    otpWatchStartedAt = 0;
    clearAllTimers();
    if (hideBadge) {
      const b = document.getElementById('wb-gmail-otp-badge');
      if (b) {
        try { b.remove(); } catch (e) {}
      }
    }
  }

  function verifyOtpTabStillOpen(onAlive) {
    if (!isOtpWatchActive || hasFoundOtp) return;
    if (otpWatchStartedAt > 0 && (Date.now() - otpWatchStartedAt > MAX_OTP_WATCH_MS)) {
      stopOtpWatchSession(true);
      return;
    }
    try {
      chrome.runtime.sendMessage({ action: 'checkOtpTabAlive' }, (resp) => {
        if (chrome.runtime.lastError || !resp || !resp.alive) {
          stopOtpWatchSession(true);
          return;
        }
        if (isOtpWatchActive && !hasFoundOtp && typeof onAlive === 'function') {
          onAlive();
        }
      });
    } catch (e) {
      stopOtpWatchSession(true);
    }
  }

  function isLowBalanceComposeMode() {
    const href = String(window.location.href || '').toLowerCase();
    return href.includes('wb_csc_low_balance=1') || (href.includes('view=cm') && href.includes('sat397842'));
  }

  function findGmailSendButton() {
    const selectors = [
      'div[role="button"][aria-label^="Send" i]',
      'div[role="button"][data-tooltip^="Send" i]',
      'div[role="button"][aria-label*="Send " i]',
      'div[role="button"][data-tooltip*="Send " i]',
      'div[role="button"][aria-label^="भेजें" i]',
      'div[role="button"][data-tooltip^="भेजें" i]',
      'div.T-I.J-J5-Ji.aoO.v7.T-I-atl.L3',
      'div.T-I.J-J5-Ji.aoO.T-I-atl'
    ];
    for (const sel of selectors) {
      const matches = Array.from(document.querySelectorAll(sel)).filter(isVisibleEl);
      if (matches.length > 0) return matches[0];
    }
    const allBtns = Array.from(document.querySelectorAll('div[role="button"], button')).filter(isVisibleEl);
    return allBtns.find((b) => {
      const t = (b.innerText || b.textContent || '').trim().toLowerCase();
      return t === 'send' || t === 'भेजें';
    }) || null;
  }

  function ensureComposeFieldsFilled(toEmail, subjectText, bodyText) {
    // 1. Subject box
    const subjInput = document.querySelector('input[name="subjectbox"]');
    if (subjInput && isVisibleEl(subjInput) && !String(subjInput.value || '').trim()) {
      subjInput.focus();
      subjInput.value = subjectText;
      subjInput.dispatchEvent(new Event('input', { bubbles: true }));
      subjInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // 2. Message body editor
    const bodyEditor =
      document.querySelector('div[aria-label="Message Body" i]') ||
      document.querySelector('div[aria-label="संदेश का मुख्य भाग" i]') ||
      document.querySelector('div[role="textbox"][aria-label*="Message" i]') ||
      document.querySelector('div.Am.Al.editable');

    if (bodyEditor && isVisibleEl(bodyEditor) && !String(bodyEditor.innerText || '').trim()) {
      bodyEditor.focus();
      bodyEditor.innerText = bodyText;
      bodyEditor.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // 3. Recipient ("To") if no email chip exists yet
    const pageHasRecipientChip =
      document.querySelector(`[data-hovercard-id="${toEmail}"]`) ||
      document.querySelector(`[email="${toEmail}"]`) ||
      (document.body && (document.body.innerText || '').toLowerCase().includes(toEmail.toLowerCase()));

    if (!pageHasRecipientChip) {
      const toInput =
        document.querySelector('input[aria-label*="To recipients" i]') ||
        document.querySelector('input[aria-label^="To" i]') ||
        document.querySelector('input[peoplekit-id]') ||
        document.querySelector('textarea[name="to"]');
      if (toInput && isVisibleEl(toInput) && !String(toInput.value || '').trim()) {
        toInput.focus();
        toInput.value = toEmail;
        toInput.dispatchEvent(new Event('input', { bubbles: true }));
        toInput.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true }));
        toInput.dispatchEvent(new Event('blur', { bubbles: true }));
      }
    }
  }

  function clickGmailSendButton(sendBtn) {
    if (!sendBtn) return;
    try { sendBtn.focus(); } catch (e) {}
    ['mousedown', 'mouseup', 'click'].forEach((type) => {
      try {
        sendBtn.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, view: window, buttons: 1 }));
      } catch (e) {}
    });
    try { sendBtn.click(); } catch (e) {}
  }

  function runLowBalanceAutoSendFlow(customData = null) {
    if (hasSentLowBalanceEmail || isLowBalanceSendInProgress) return;
    isLowBalanceSendInProgress = true;

    const toEmail = (customData && customData.to) || 'sat397842@gmail.com';
    const subjectText = (customData && customData.subject) || 'csc me paisa katm ho gaya add kigiue';
    const bodyText =
      (customData && customData.body) ||
      'csc me paisa katm ho gaya add kigiue\n\nCSC Wallet Error Code: 810 (Insufficient Wallet Balance, Error ID: no_balance)';

    showGmailBadge(`📧 CSC Low Balance Alert • ${toEmail} पर मैसेज भेजा जा रहा है...`, '#f59e0b');

    let attempts = 0;
    const maxAttempts = 25;

    const pollCompose = setInterval(() => {
      if (hasSentLowBalanceEmail) {
        clearInterval(pollCompose);
        return;
      }
      attempts++;

      ensureComposeFieldsFilled(toEmail, subjectText, bodyText);
      const sendBtn = findGmailSendButton();

      if (sendBtn) {
        clearInterval(pollCompose);
        showGmailBadge(`🚀 Send बटन मिल गया • "${subjectText}" भेजा जा रहा है...`, '#10b981');

        setTimeout(() => {
          if (hasSentLowBalanceEmail) return;
          ensureComposeFieldsFilled(toEmail, subjectText, bodyText);
          const freshSendBtn = findGmailSendButton() || sendBtn;

          hasSentLowBalanceEmail = true;
          isLowBalanceSendInProgress = false;
          showGmailBadge(`✅ ${toEmail} पर मैसेज भेज दिया गया • Error पेज बंद करके 5 मिनट बाद फिर Try होगा...`, '#10b981');

          // Notify background BEFORE click in case Gmail's ?view=cm closes the popup immediately on Send click
          try {
            chrome.runtime.sendMessage({
              action: 'lowBalanceEmailSent',
              closeDelayMs: 2500
            }, () => void chrome.runtime.lastError);
          } catch (e) {}

          clickGmailSendButton(freshSendBtn);
        }, 1200);
        return;
      }

      if (attempts >= maxAttempts) {
        clearInterval(pollCompose);
        isLowBalanceSendInProgress = false;
        showGmailBadge('⚠️ Gmail Send बटन लोड नहीं हो पाया, कृपया Send पर क्लिक करें।', '#ef4444');
      }
    }, 800);
  }

  function clearAllTimers() {
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
    if (returnToListTimer) {
      clearInterval(returnToListTimer);
      returnToListTimer = null;
    }
  }

  function isVisibleEl(el) {
    if (!el || !el.isConnected) return false;
    try {
      const style = window.getComputedStyle(el);
      if (!style || style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0) {
        return false;
      }
      const rect = el.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    } catch (e) {
      return false;
    }
  }

  function showGmailBadge(msg, color = '#10b981') {
    if (!document.body) return;
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

  function extractOtpFromText(txt) {
    const text = String(txt || '');
    if (!text) return null;
    const regexes = [
      /login\s+verification\s+code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /verification\s+code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /code\s+is\s*[:\-]?\s*(\d{6})/gi,
      /OTP\s+is\s*[:\-]?\s*(\d{6})/gi,
      /सत्यापन\s+कोड\s*[:\-]?\s*(\d{6})/gi,
      /कोड\s*[:\-]?\s*(\d{6})/gi
    ];
    for (const rgx of regexes) {
      let match;
      let last = null;
      while ((match = rgx.exec(text)) !== null) {
        if (match[1] && /^\d{6}$/.test(match[1])) {
          last = match[1];
        }
      }
      if (last) return last;
    }
    return null;
  }

  // Get ONLY currently visible UTI / panonline email rows in Mail List (top = latest)
  function getVisibleUtiInboxRows() {
    const rows = Array.from(document.querySelectorAll('.zA, tr[role="row"]')).filter(isVisibleEl);
    return rows.filter((r) => {
      const t = (r.innerText || '').toLowerCase();
      return (
        t.includes('panonline') ||
        t.includes('utiitsl') ||
        t.includes('login verification code') ||
        t.includes('लॉगिन सत्यापन कोड')
      );
    });
  }

  // 1. Primary: Extract from the innermost VISIBLE "Code Requested" widget (e.g. "8 5 6 2 6 4" + "Copy code")
  function extractFromCodeRequestedWidget() {
    const candidates = Array.from(document.querySelectorAll('div, section, article, tr, td')).filter((el) => {
      if (!isVisibleEl(el)) return false;
      const txt = (el.innerText || '').trim();
      return (
        txt.length > 0 &&
        txt.length < 300 &&
        txt.includes('Code Requested') &&
        (txt.includes('Copy code') || /\d\s+\d\s+\d/.test(txt) || /\b\d{6}\b/.test(txt))
      );
    });

    if (candidates.length === 0) return null;

    // Sort shortest text first so we inspect the exact innermost "Code Requested" box (not an outer wrapper)
    candidates.sort((a, b) => (a.innerText || '').length - (b.innerText || '').length);
    const codeCard = candidates[0];
    const text = codeCard.innerText || '';

    // Pattern 1: Separate digit boxes (e.g. "8  5  6  2  6  4")
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

    return null;
  }

  // 2. Fallback: Extract from VISIBLE latest message body in open thread (newest message first, EN + Hindi)
  function extractFromOpenThread() {
    const openMessages = Array.from(document.querySelectorAll('.ii.gt, .a3s')).filter(isVisibleEl);
    if (openMessages.length > 0) {
      for (let i = openMessages.length - 1; i >= 0; i--) {
        const code = extractOtpFromText(openMessages[i].innerText || '');
        if (code) return code;
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
    const refreshBtns = Array.from(document.querySelectorAll(
      'div[role="button"][data-tooltip*="Refresh" i], div[aria-label*="Refresh" i], ' +
      'div[data-tooltip="Refresh"], div[aria-label="Refresh"], div[act="20"]'
    )).filter(isVisibleEl);
    if (refreshBtns.length > 0) {
      try {
        refreshBtns[0].click();
      } catch (e) {}
    }
  }

  function navigateToMailList() {
    try {
      const backBtn = Array.from(document.querySelectorAll(
        'div[role="button"][aria-label*="Back to Inbox" i], div[data-tooltip*="Back to Inbox" i], ' +
        'div[role="button"][aria-label*="Back to" i], div[act="19"]'
      )).find(isVisibleEl);
      if (backBtn) {
        ['mousedown', 'mouseup', 'click'].forEach((type) => {
          try {
            backBtn.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, view: window }));
          } catch (e) {}
        });
        try { backBtn.click(); } catch (e) {}
      }
    } catch (e) {}

    // Always ensure hash returns cleanly to the Inbox mail list
    try {
      if ((window.location.hash || '').toLowerCase() !== '#inbox') {
        window.location.hash = '#inbox';
      }
    } catch (e) {}
  }

  function isInboxListView() {
    const hash = (window.location.hash || '').toLowerCase();
    const isThreadHash = /^#(inbox|all|sent|starred|search\/[^/]+)\/[a-z0-9_-]{8,}/i.test(hash);
    if (isThreadHash) return false;
    const visibleRows = Array.from(document.querySelectorAll('.zA, tr[role="row"]')).filter(isVisibleEl);
    return visibleRows.length > 0;
  }

  function isOpenEmailView() {
    // If the Inbox Mail List is currently visible, we are NOT in an open email view (ignore hidden Gmail SPA thread DOM!)
    if (isInboxListView()) return false;

    const hash = (window.location.hash || '').toLowerCase();
    const hashLooksLikeThread = /^#(inbox|all|sent|starred|search\/[^/]+)\/[a-z0-9_-]{8,}/i.test(hash);
    const hasVisibleWidget = Boolean(extractFromCodeRequestedWidget());
    const hasVisibleMsgs = Array.from(document.querySelectorAll('.ii.gt, .a3s')).some(isVisibleEl);
    const hasVisibleSubject = Array.from(document.querySelectorAll('h2.hP, .ha h2')).some(isVisibleEl);
    return hashLooksLikeThread || hasVisibleWidget || hasVisibleMsgs || hasVisibleSubject;
  }

  // Wait 5 seconds, return to Gmail Mail List (#inbox), refresh, and open the latest UTI email
  function returnToMailListAfterDelay(reasonLabel, seconds = 5) {
    if (!isOtpWatchActive || hasFoundOtp || isReturningToMailList) return;
    returnLoopCount++;
    if (returnLoopCount > MAX_RETURN_LOOPS) {
      stopOtpWatchSession(false);
      showGmailBadge('⏹️ OTP wait timeout • Stopped auto-refresh.', '#94a3b8');
      setTimeout(() => {
        const b = document.getElementById('wb-gmail-otp-badge');
        if (b && !isOtpWatchActive) b.remove();
      }, 4000);
      return;
    }
    clearAllTimers();
    isReturningToMailList = true;
    isSettling = false;
    rowClicked = false;
    emptyExtractionCount = 0;

    let remaining = seconds;
    showGmailBadge(`⏳ ${reasonLabel} • ${remaining}s baad Mail List me jakar latest email open karenge...`, '#f59e0b');

    returnToListTimer = setInterval(() => {
      if (!isOtpWatchActive || hasFoundOtp) {
        clearAllTimers();
        isReturningToMailList = false;
        return;
      }

      remaining--;
      if (remaining > 0) {
        showGmailBadge(`⏳ ${reasonLabel} • ${remaining}s baad Mail List me jakar latest email open karenge...`, '#f59e0b');
        return;
      }

      clearInterval(returnToListTimer);
      returnToListTimer = null;

      verifyOtpTabStillOpen(() => {
        showGmailBadge('📥 Mail List (#inbox) me ja rahe hain aur refresh kar rahe hain...', '#38bdf8');
        if (!isInboxListView()) {
          navigateToMailList();
        }

        setTimeout(() => {
          if (!isOtpWatchActive || hasFoundOtp) {
            isReturningToMailList = false;
            return;
          }
          verifyOtpTabStillOpen(() => {
            triggerGmailRefresh();
            showGmailBadge('🔄 Inbox refreshed • Latest UTI email open kar rahe hain...', '#38bdf8');

            setTimeout(() => {
              isReturningToMailList = false;
              rowClicked = false;
              isSettling = false;
              if (isOtpWatchActive && !hasFoundOtp) {
                monitorAndTriggerFlow();
              }
            }, 1200);
          });
        }, 900);
      });
    }, 1000);
  }

  function submitFreshOtp(code) {
    hasFoundOtp = true;
    isOtpWatchActive = false;
    isSettling = false;
    isReturningToMailList = false;
    clearAllTimers();

    lastUsedOtp = code;
    chrome.storage.local.set({ lastSubmittedOtp: code });

    showGmailBadge(`✅ Verified Fresh OTP: ${code} • Submitting to UTI Portal...`, '#10b981');

    try {
      chrome.runtime.sendMessage({ action: 'otpExtracted', code }, (resp) => {
        if (chrome.runtime.lastError || (resp && resp.success === false)) {
          const errMsg = chrome.runtime.lastError?.message || resp?.error || 'UTI tab not found';
          showGmailBadge(`⚠️ OTP ${code} mila par UTI tab par bhej nahi paye: ${errMsg}`, '#ef4444');
        }
      });
    } catch (e) {}
  }

  function extractFromOpenEmailView() {
    if (!isOtpWatchActive || hasFoundOtp || isReturningToMailList) return;

    if (isInboxListView()) {
      rowClicked = false;
      monitorAndTriggerFlow();
      return;
    }

    // 1. Primary: Read from "Code Requested" widget box (8 5 6 2 6 4)
    const widgetCode = extractFromCodeRequestedWidget();
    // 2. Fallback: Read from latest visible message body (EN / Hindi)
    const threadCode = extractFromOpenThread();

    // Prefer "Code Requested" widget if fresh, otherwise latest visible thread body if fresh
    let freshCode = null;
    if (widgetCode && (!lastUsedOtp || widgetCode !== lastUsedOtp)) {
      freshCode = widgetCode;
    } else if (threadCode && (!lastUsedOtp || threadCode !== lastUsedOtp)) {
      freshCode = threadCode;
    }

    if (freshCode) {
      submitFreshOtp(freshCode);
      return;
    }

    const oldCode = widgetCode || threadCode;
    if (oldCode && lastUsedOtp && oldCode === lastUsedOtp) {
      // Previous OTP detected -> wait 5s, go back to Mail List (#inbox), refresh, and open latest email
      returnToMailListAfterDelay(`Previous OTP (${oldCode}) detected`, 5);
      return;
    }

    emptyExtractionCount++;
    if (emptyExtractionCount >= 3) {
      returnToMailListAfterDelay('Code Requested / OTP content nahi mila', 5);
      return;
    }

    showGmailBadge('⏳ Waiting for Code Requested / email content to load...', '#38bdf8');
    setTimeout(extractFromOpenEmailView, 1500);
  }

  function startSettlingCountdown(seconds = 5) {
    if (!isOtpWatchActive || hasFoundOtp || isSettling || isReturningToMailList) return;
    isSettling = true;

    let remaining = seconds;
    showGmailBadge(`⏳ Email open • Waiting ${remaining}s for newest Code Requested OTP...`, '#38bdf8');

    if (countdownTimer) clearInterval(countdownTimer);

    countdownTimer = setInterval(() => {
      if (!isOtpWatchActive || hasFoundOtp) {
        clearAllTimers();
        isSettling = false;
        return;
      }

      remaining--;

      if (remaining > 0) {
        showGmailBadge(`⏳ Email open • Waiting ${remaining}s for newest Code Requested OTP...`, '#38bdf8');
      } else {
        clearInterval(countdownTimer);
        countdownTimer = null;
        isSettling = false;
        showGmailBadge('⚡ 5s completed! Extracting OTP from Code Requested box...', '#10b981');
        setTimeout(extractFromOpenEmailView, 350);
      }
    }, 1000);
  }

  function monitorAndTriggerFlow() {
    if (!isOtpWatchActive || hasFoundOtp || isSettling || isReturningToMailList) return;

    verifyOtpTabStillOpen(() => {
      // If email is ALREADY open (after we clicked the top row), start 5s settle & extract from Code Requested box
      if (isOpenEmailView()) {
        startSettlingCountdown(5);
        return;
      }

      // On Mail List (#inbox) -> ALWAYS CLICK & OPEN the top/latest UTI email row
      if (isInboxListView()) {
        const utiRows = getVisibleUtiInboxRows();
        if (utiRows.length > 0 && !rowClicked) {
          rowClicked = true;
          showGmailBadge('⚡ Opening latest UTI email from Mail List...', '#38bdf8');
          clickEmailRow(utiRows[0]);

          // Wait briefly for email thread + "Code Requested" widget to mount, then start 5s countdown
          setTimeout(() => {
            if (isOtpWatchActive && !hasFoundOtp && !isReturningToMailList) {
              if (isOpenEmailView()) {
                startSettlingCountdown(5);
              } else {
                rowClicked = false;
              }
            }
          }, 900);
        } else if (utiRows.length === 0) {
          showGmailBadge('🔍 Locating latest UTI email in Mail List...', '#38bdf8');
          triggerGmailRefresh();
        }
        return;
      }

      showGmailBadge('🔍 Gmail load hone ka wait kar rahe hain...', '#38bdf8');
    });
  }

  function startOtpWatchSession() {
    clearAllTimers();
    isOtpWatchActive = true;
    hasFoundOtp = false;
    isSettling = false;
    isReturningToMailList = false;
    rowClicked = false;
    emptyExtractionCount = 0;
    returnLoopCount = 0;
    otpWatchStartedAt = Date.now();

    verifyOtpTabStillOpen(() => {
      chrome.storage.local.get(['lastSubmittedOtp'], (res) => {
        lastUsedOtp = (res && res.lastSubmittedOtp) ? res.lastSubmittedOtp : '';

        // Always go to Mail List (#inbox) first, refresh, and then click & open the latest UTI email!
        if (!isInboxListView()) {
          showGmailBadge('📥 Mail List (#inbox) par jakar latest UTI email open kar rahe hain...', '#38bdf8');
          navigateToMailList();
          setTimeout(() => {
            if (!isOtpWatchActive) return;
            triggerGmailRefresh();
            setTimeout(() => {
              monitorAndTriggerFlow();
            }, 1200);
          }, 800);
        } else {
          showGmailBadge('🔄 Mail List refresh karke latest UTI email open kar rahe hain...', '#38bdf8');
          triggerGmailRefresh();
          setTimeout(() => {
            monitorAndTriggerFlow();
          }, 1200);
        }
      });
    });
  }

  // Listener for background signal
  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg && msg.action === 'stopOtpWatch') {
      stopOtpWatchSession(true);
      sendResponse({ stopped: true });
      return true;
    }
    if (msg && msg.action === 'sendLowBalanceMailNow') {
      runLowBalanceAutoSendFlow(msg);
      sendResponse({ started: true });
      return true;
    }
    if (msg && msg.action === 'readOtpNow') {
      if (isLowBalanceComposeMode()) {
        sendResponse({ started: false, composeMode: true });
        return true;
      }
      startOtpWatchSession();
      sendResponse({ started: true });
      return true;
    }
    return false;
  });

  // Keep local watch state synced with chrome.storage.local
  chrome.storage.onChanged.addListener((changes, area) => {
    if (area !== 'local') return;
    if (isLowBalanceComposeMode()) return;
    if (changes.lastSubmittedOtp) {
      lastUsedOtp = changes.lastSubmittedOtp.newValue || '';
    }
    if (changes.isWaitingForOtp) {
      if (changes.isWaitingForOtp.newValue) {
        if (!isOtpWatchActive) {
          startOtpWatchSession();
        }
      } else {
        stopOtpWatchSession(true);
      }
    }
  });

  // Auto-init on page load: Low-balance compose tab OR OTP watch
  if (isLowBalanceComposeMode()) {
    chrome.storage.local.get(['pendingLowBalanceMail'], (res) => {
      setTimeout(() => {
        runLowBalanceAutoSendFlow(res && res.pendingLowBalanceMail ? res.pendingLowBalanceMail : null);
      }, 600);
    });
  } else {
    chrome.storage.local.get(['isWaitingForOtp', 'otpRequestedAt', 'lastSubmittedOtp'], (res) => {
      const isWaiting = Boolean(res && res.isWaitingForOtp);
      const reqTime = res && res.otpRequestedAt ? Number(res.otpRequestedAt) : 0;
      lastUsedOtp = res && res.lastSubmittedOtp ? res.lastSubmittedOtp : '';

      if (isWaiting && reqTime > 0 && (Date.now() - reqTime < 180000)) {
        isOtpWatchActive = true;
        otpWatchStartedAt = reqTime;
        setTimeout(monitorAndTriggerFlow, 700);
      }
    });
  }

  // Re-check every 1.5 seconds ONLY while actively waiting for OTP
  setInterval(() => {
    if (!isLowBalanceComposeMode() && isOtpWatchActive && !hasFoundOtp && !isSettling && !isReturningToMailList) {
      monitorAndTriggerFlow();
    }
  }, 1500);
})();
