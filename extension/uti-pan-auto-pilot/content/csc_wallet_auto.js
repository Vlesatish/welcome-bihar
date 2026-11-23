// =========================================================================
// Welcome Bihar - CSC Wallet Auto-Pilot Content Script
// Target: https://wallet.csccloud.in/*
// =========================================================================

(function () {
  'use strict';

  if (window !== window.top) return;

  const WALLET_PASSWORD = 'Satish@824207';
  const WALLET_PIN = '967235';

  let passwordSubmitted = false;
  let pinSubmitted = false;
  let proceedClicked = false;
  let insufficientBalanceHandled = false;
  let lastProceedAttempt = 0;
  let lastPassAttempt = 0;
  let lastPinAttempt = 0;

  function showFloatingWalletAlert(msg, color = '#ef4444') {
    if (!document.body) return;
    let badge = document.getElementById('wb-csc-error-alert');
    if (!badge) {
      badge = document.createElement('div');
      badge.id = 'wb-csc-error-alert';
      badge.style.cssText =
        'position:fixed;top:15px;right:20px;z-index:999999999;background:#0f172a;color:#fff;padding:14px 22px;' +
        'border-radius:10px;font-family:system-ui,-apple-system,sans-serif;font-size:13.5px;font-weight:600;' +
        'box-shadow:0 8px 30px rgba(0,0,0,0.65);display:flex;align-items:center;gap:10px;border-left:5px solid #ef4444;';
      document.body.appendChild(badge);
    }
    badge.style.borderLeftColor = color;
    badge.innerHTML = `<span>${msg}</span>`;
  }

  function isInsufficientBalancePage(bodyText) {
    const t = String(bodyText || '').toLowerCase();
    const title = String(document.title || '').toLowerCase();
    return (
      t.includes('insufficient wallet balance') ||
      t.includes('error id: no_balance') ||
      t.includes('no_balance') ||
      t.includes('error code: 810') ||
      (t.includes('pay page: error') && t.includes('810')) ||
      (title.includes('pay page error') && (t.includes('810') || t.includes('insufficient')))
    );
  }

  function handleInsufficientBalanceError() {
    if (insufficientBalanceHandled) return true;
    insufficientBalanceHandled = true;

    const statusMsg = '⚠️ CSC में पैसा खत्म हो गया (Error 810)! sat397842@gmail.com पर मैसेज भेजा जा रहा है...';
    updateWalletBadge(statusMsg, '#ef4444');
    showFloatingWalletAlert(statusMsg, '#ef4444');

    const storageKey = `wb_csc_no_balance_${window.location.pathname || 'default'}`;
    if (sessionStorage.getItem(storageKey) === '1') {
      return true;
    }
    sessionStorage.setItem(storageKey, '1');

    try {
      chrome.runtime.sendMessage(
        {
          action: 'cscInsufficientBalance',
          errorCode: '810',
          errorId: 'no_balance',
          errorUrl: window.location.href,
          recipientEmail: 'sat397842@gmail.com',
          alertMessage: 'csc me paisa katm ho gaya add kigiue'
        },
        (resp) => {
          void chrome.runtime.lastError;
          if (resp && resp.success) {
            showFloatingWalletAlert(
              '📧 sat397842@gmail.com पर मैसेज भेजा जा रहा है: "csc me paisa katm ho gaya add kigiue"',
              '#f59e0b'
            );
          }
        }
      );
    } catch (e) {}

    return true;
  }

  function isVisible(el) {
    if (!el || !el.isConnected) return false;
    const style = window.getComputedStyle(el);
    if (!style || style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0) {
      return false;
    }
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  }

  function setInputValue(el, val) {
    if (!el || val === undefined || val === null) return false;
    const str = String(val);
    try { el.focus(); } catch (e) {}
    try {
      const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
      if (setter) setter.call(el, str);
      else el.value = str;
    } catch (e) {
      el.value = str;
    }
    ['input', 'change', 'keyup', 'blur'].forEach((evt) => {
      try { el.dispatchEvent(new Event(evt, { bubbles: true })); } catch (e) {}
    });
    return true;
  }

  function updateWalletBadge(statusText, color = '#0284c7') {
    const existing = document.getElementById('wb-csc-badge');
    if (existing) {
      try { existing.remove(); } catch (e) {}
    }
    try {
      chrome.storage.local.set({
        lastStatus: statusText,
        lastStatusColor: color
      });
    } catch (e) {}
  }

  // -----------------------------------------------------------------------
  // STEP 1A: "Proceed to Wallet" (When password is not requested)
  // -----------------------------------------------------------------------
  function handleProceedToWallet() {
    const now = Date.now();
    if (proceedClicked && (now - lastProceedAttempt < 3500)) return false;

    const allButtons = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], a.btn, .btn, a'));
    const proceedBtn = allButtons.find((b) => {
      if (!isVisible(b)) return false;
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return t.includes('proceed to wallet') || (t.includes('proceed') && !t.includes('cancel'));
    });

    if (proceedBtn) {
      proceedClicked = true;
      lastProceedAttempt = now;
      updateWalletBadge('🚀 Clicking Proceed to Wallet...', '#10b981');
      setTimeout(() => {
        try {
          proceedBtn.focus();
          proceedBtn.click();
        } catch (e) {
          proceedBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        }
      }, 250);
      return true;
    }
    return false;
  }

  // -----------------------------------------------------------------------
  // STEP 1B: Password & Validate Screen
  // -----------------------------------------------------------------------
  function handlePasswordScreen() {
    const now = Date.now();
    if (passwordSubmitted && (now - lastPassAttempt < 3500)) return false;

    const allInputs = Array.from(document.querySelectorAll('input'));
    const passInput = allInputs.find((el) => {
      if (!isVisible(el)) return false;
      const type = (el.type || '').toLowerCase();
      const name = (el.name || '').toLowerCase();
      const id = (el.id || '').toLowerCase();
      const ph = (el.placeholder || '').toLowerCase();
      return type === 'password' || name.includes('pass') || id.includes('pass') || ph.includes('password');
    });

    const allButtons = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], a.btn, .btn'));
    const validateBtn = allButtons.find((b) => {
      if (!isVisible(b)) return false;
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return t === 'validate' || t.startsWith('validate') || (t.includes('validate') && !t.includes('cancel'));
    });

    if (passInput && validateBtn) {
      updateWalletBadge('🔑 Auto-filling Wallet Password...', '#38bdf8');
      setInputValue(passInput, WALLET_PASSWORD);

      passwordSubmitted = true;
      lastPassAttempt = now;
      sessionStorage.setItem('wb_csc_pass_submitted', '1');

      setTimeout(() => {
        updateWalletBadge('🚀 Clicking Validate...', '#10b981');
        try {
          validateBtn.focus();
          validateBtn.click();
        } catch (e) {
          validateBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        }
      }, 300);
      return true;
    }
    return false;
  }

  // -----------------------------------------------------------------------
  // STEP 2: Wallet Pin & Pay Screen
  // -----------------------------------------------------------------------
  function handlePinScreen() {
    const now = Date.now();
    if (pinSubmitted && (now - lastPinAttempt < 3500)) return false;

    const allInputs = Array.from(document.querySelectorAll('input'));

    const pinInput = allInputs.find((el) => {
      if (!isVisible(el)) return false;
      const type = (el.type || '').toLowerCase();
      const name = (el.name || '').toLowerCase();
      const id = (el.id || '').toLowerCase();
      const ph = (el.placeholder || '').toLowerCase();
      const max = el.getAttribute('maxlength');
      return (
        ph.includes('wallet pin') ||
        ph.includes('pin') ||
        name.includes('pin') ||
        id.includes('pin') ||
        (max === '6' && (type === 'password' || type === 'text' || type === 'tel' || type === 'number')) ||
        (type === 'password' && !ph.includes('password'))
      );
    });

    const allButtons = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], a.btn, .btn'));
    const payBtn = allButtons.find((b) => {
      if (!isVisible(b)) return false;
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return (t.includes('pay') && !t.includes('cancel')) || t.startsWith('pay');
    });

    if (pinInput && payBtn) {
      updateWalletBadge('🔢 Auto-filling 6-digit Wallet PIN...', '#38bdf8');
      setInputValue(pinInput, WALLET_PIN);

      pinSubmitted = true;
      lastPinAttempt = now;
      sessionStorage.setItem('wb_csc_pin_submitted', '1');

      setTimeout(() => {
        updateWalletBadge('💰 Executing ₹97.37 Payment...', '#10b981');
        try {
          payBtn.focus();
          payBtn.click();
        } catch (e) {
          payBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        }
      }, 350);
      return true;
    }
    return false;
  }

  // -----------------------------------------------------------------------
  // Router
  // -----------------------------------------------------------------------
  function runCscWalletAuto() {
    const url = (window.location.href || '').toLowerCase();
    const bodyText = (document.body?.innerText || '').toLowerCase();

    // 0. Error Code 810 / Insufficient Wallet Balance (no_balance)
    if (isInsufficientBalancePage(bodyText)) {
      handleInsufficientBalanceError();
      return;
    }

    // 1. PIN Screen
    if (
      url.includes('wallet_pin_form') ||
      bodyText.includes('enter 6 digit wallet pin') ||
      bodyText.includes('wallet pin') ||
      bodyText.includes('pay via wallet')
    ) {
      handlePinScreen();
      return;
    }

    // 2. "Proceed to Wallet" button (Direct without password)
    if (
      bodyText.includes('proceed to wallet') ||
      document.querySelector('button, a, input[value*="Proceed"]')
    ) {
      if (handleProceedToWallet()) return;
    }

    // 3. Password / Validate screen
    if (
      url.includes('payment') ||
      bodyText.includes('pay by wallet') ||
      bodyText.includes('please enter password')
    ) {
      if (handlePasswordScreen()) return;
    }

    updateWalletBadge('CSC Wallet Portal Active', '#38bdf8');
  }

  // Run on start and on dynamic transitions
  runCscWalletAuto();
  setInterval(() => {
    runCscWalletAuto();
  }, 500);
})();
