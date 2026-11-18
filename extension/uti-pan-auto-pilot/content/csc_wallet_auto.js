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
    if (!document.body) return;
    let host = document.getElementById('wb-csc-badge');
    if (!host) {
      host = document.createElement('div');
      host.id = 'wb-csc-badge';
      host.style.cssText = [
        'position:fixed !important',
        'bottom:16px !important',
        'right:16px !important',
        'width:300px !important',
        'max-width:320px !important',
        'z-index:2147483647 !important',
        'background:#0f172a !important',
        'color:#f8fafc !important',
        'border:1px solid #38bdf8 !important',
        'border-radius:12px !important',
        'padding:10px 14px !important',
        'font-family:system-ui,-apple-system,sans-serif !important',
        'font-size:12px !important',
        'box-shadow:0 8px 24px rgba(0,0,0,0.45) !important',
        'box-sizing:border-box !important',
        'line-height:1.4 !important'
      ].join(';');

      host.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <strong style="color:#38bdf8;font-size:12px;">💳 CSC Wallet Auto-Pilot</strong>
          <span style="font-size:10px;color:#fbbf24;font-weight:700;">₹97.37</span>
        </div>
        <div id="wb-csc-status" style="font-size:11px;color:#34d399;">Ready</div>
      `;
      document.body.appendChild(host);
    }
    const stEl = document.getElementById('wb-csc-status');
    if (stEl) {
      stEl.textContent = statusText;
      stEl.style.color = color;
    }
  }

  // -----------------------------------------------------------------------
  // STEP 1A: "Proceed to Wallet" (When password is not requested)
  // -----------------------------------------------------------------------
  function handleProceedToWallet() {
    if (proceedClicked) return false;

    const allButtons = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], a.btn, .btn, a'));
    const proceedBtn = allButtons.find((b) => {
      if (!isVisible(b)) return false;
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return t.includes('proceed to wallet') || (t.includes('proceed') && !t.includes('cancel'));
    });

    if (proceedBtn) {
      proceedClicked = true;
      updateWalletBadge('🚀 Clicking Proceed to Wallet...', '#10b981');
      setTimeout(() => {
        try {
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
    if (passwordSubmitted) return false;

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
      sessionStorage.setItem('wb_csc_pass_submitted', '1');

      setTimeout(() => {
        updateWalletBadge('🚀 Clicking Validate...', '#10b981');
        try {
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
    if (pinSubmitted) return false;

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
      sessionStorage.setItem('wb_csc_pin_submitted', '1');

      setTimeout(() => {
        updateWalletBadge('💰 Executing ₹97.37 Payment...', '#10b981');
        try {
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
