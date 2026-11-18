// =========================================================================
// Welcome Bihar - Digital Seva Connect Login Auto-Pilot
// Target: https://connect.csc.gov.in/*
// Requested by RIOT:
// - CSC ID: 221612270014
// - Password: Satish@824207
// - Captcha Input: Clean focus only (NO triple-click, NO selection)
// - Auto-restore credentials on captcha retry so encryption never breaks
// =========================================================================

(function () {
  'use strict';

  const CSC_ID = '221612270014';
  const CSC_PASSWORD = 'Satish@824207';

  let hasInitiallyFocusedCaptcha = false;
  let lastHandledErrorText = '';

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
    try {
      const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
      if (setter) setter.call(el, str);
      else el.value = str;
    } catch (e) {
      el.value = str;
    }
    ['input', 'change'].forEach((evt) => {
      try { el.dispatchEvent(new Event(evt, { bubbles: true })); } catch (e) {}
    });
    return true;
  }

  function getCscIdInput() {
    return document.getElementById('csclogin') ||
      document.querySelector('input[name="csclogin"]') ||
      document.querySelector('input[placeholder*="CSC ID" i]') ||
      document.querySelector('input[type="text"]:not([id*="captcha" i]):not([name*="captcha" i])');
  }

  function getPasswordInput() {
    return document.getElementById('cscpass') ||
      document.querySelector('input[name="password"]') ||
      document.querySelector('input[type="password"]');
  }

  function getCaptchaInput() {
    return document.getElementById('captcha') ||
      document.querySelector('input[name="captcha"]') ||
      document.querySelector('input[placeholder*="Captcha" i]');
  }

  function getSubmitButton() {
    return document.querySelector('input[type="submit"][name="login"]') ||
      document.querySelector('.login-btn') ||
      document.querySelector('input[value="Log In" i]') ||
      document.querySelector('button[type="submit"]');
  }

  function showConnectBadge(msg, color = '#10b981') {
    let badge = document.getElementById('wb-connect-badge');
    if (!badge) {
      badge = document.createElement('div');
      badge.id = 'wb-connect-badge';
      badge.style.cssText =
        'position:fixed;top:15px;right:20px;z-index:99999999;background:#0f172a;color:#fff;padding:12px 20px;' +
        'border-radius:10px;font-family:system-ui,-apple-system,sans-serif;font-size:13px;font-weight:600;' +
        'box-shadow:0 8px 30px rgba(0,0,0,0.5);display:flex;align-items:center;gap:10px;border-left:5px solid #10b981;';
      document.body.appendChild(badge);
    }
    badge.style.borderLeftColor = color;
    badge.innerHTML = `<span>${msg}</span>`;
  }

  function checkForCaptchaError() {
    const bodyText = (document.body ? document.body.innerText : '').toLowerCase();
    const isError =
      bodyText.includes('captcha code does not match') ||
      bodyText.includes('invalid captcha') ||
      bodyText.includes('incorrect captcha') ||
      bodyText.includes('enter valid captcha');

    if (isError) {
      const errEl = document.querySelector('.text-danger, #errorlogin, #errorpwd, #show_error');
      const errText = errEl ? errEl.innerText.trim() : 'Captcha Error';

      if (errText && errText !== lastHandledErrorText) {
        lastHandledErrorText = errText;
        showConnectBadge('⚠️ Captcha Mismatch • Credentials Re-filled • Please Enter New Captcha', '#f59e0b');

        // Always re-ensure correct credentials
        const cscInp = getCscIdInput();
        const passInp = getPasswordInput();
        const captchaInp = getCaptchaInput();

        if (cscInp) setInputValue(cscInp, CSC_ID);
        if (passInp) setInputValue(passInp, CSC_PASSWORD);

        // Clear captcha input and place focus cleanly (NO triple click)
        if (captchaInp) {
          captchaInp.value = '';
          try {
            captchaInp.focus();
            if (typeof captchaInp.setSelectionRange === 'function') {
              captchaInp.setSelectionRange(0, 0);
            }
          } catch (e) {}
        }
      }
    }
  }

  function hookSubmitButton() {
    const btn = getSubmitButton();
    if (!btn || btn.dataset.wbHooked === 'true') return;
    btn.dataset.wbHooked = 'true';

    // Mousedown runs BEFORE onclick="return myencryption()"
    // This guarantees plain password is in place for encryption
    const preSubmit = () => {
      const cscInp = getCscIdInput();
      const passInp = getPasswordInput();
      if (cscInp && cscInp.value !== CSC_ID) {
        setInputValue(cscInp, CSC_ID);
      }
      if (passInp && passInp.value !== CSC_PASSWORD) {
        setInputValue(passInp, CSC_PASSWORD);
      }
    };

    btn.addEventListener('mousedown', preSubmit, true);
    btn.addEventListener('touchstart', preSubmit, true);
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') preSubmit();
    }, true);
  }

  function runAutoConnectLogin() {
    hookSubmitButton();
    checkForCaptchaError();

    const cscInput = getCscIdInput();
    const passInput = getPasswordInput();
    const captchaInput = getCaptchaInput();

    if (!cscInput || !passInput) return;

    // 1. Ensure CSC ID is set
    if (cscInput.value !== CSC_ID) {
      setInputValue(cscInput, CSC_ID);
    }

    // 2. Ensure Password is set (do not overwrite if in middle of submitting)
    if (passInput.value !== CSC_PASSWORD && passInput.value.length < 50) {
      setInputValue(passInput, CSC_PASSWORD);
    }

    // 3. Clean focus on Captcha input ONCE on initial load (NO triple-click, NO selection)
    if (captchaInput && !hasInitiallyFocusedCaptcha) {
      hasInitiallyFocusedCaptcha = true;
      try {
        captchaInput.focus();
        if (typeof captchaInput.setSelectionRange === 'function') {
          const len = captchaInput.value.length;
          captchaInput.setSelectionRange(len, len);
        }
      } catch (e) {}

      showConnectBadge('🔐 CSC ID & Password Auto-Filled • Please enter Captcha & Log In', '#10b981');
    }
  }

  // Run on ready and poll
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(runAutoConnectLogin, 250);
    });
  } else {
    setTimeout(runAutoConnectLogin, 250);
  }

  const observer = new MutationObserver(() => {
    runAutoConnectLogin();
  });
  observer.observe(document.documentElement || document.body, { childList: true, subtree: true });

  setInterval(() => {
    runAutoConnectLogin();
  }, 500);
})();
