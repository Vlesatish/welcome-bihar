// =========================================================================
// Welcome Bihar - Digital Seva Connect Login Auto-Pilot
// Target: https://connect.csc.gov.in/*
// Requested by RIOT:
// - CSC ID: 221612270014
// - Password: Satish@824207
// - Captcha Input: Auto-focus & 3 clicks
// =========================================================================

(function () {
  'use strict';

  const CSC_ID = '221612270014';
  const CSC_PASSWORD = 'Satish@824207';

  let filled = false;

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

  function findCscIdInput() {
    const allInputs = Array.from(document.querySelectorAll('input[type="text"], input:not([type])'));
    return allInputs.find((inp) => {
      if (!isVisible(inp)) return false;
      const p = (inp.placeholder || '').toLowerCase();
      const n = (inp.name || '').toLowerCase();
      const id = (inp.id || '').toLowerCase();
      return (
        p.includes('csc id') ||
        p.includes('username') ||
        n.includes('username') ||
        n.includes('csc_id') ||
        id.includes('username') ||
        id.includes('csc')
      ) && !p.includes('captcha') && !n.includes('captcha') && !id.includes('captcha');
    }) || allInputs[0] || null;
  }

  function findPasswordInput() {
    return Array.from(document.querySelectorAll('input[type="password"]')).find(isVisible) ||
      document.querySelector('input[type="password"]');
  }

  function findCaptchaInput() {
    const allInputs = Array.from(document.querySelectorAll('input[type="text"], input:not([type])'));
    return allInputs.find((inp) => {
      if (!isVisible(inp)) return false;
      const p = (inp.placeholder || '').toLowerCase();
      const n = (inp.name || '').toLowerCase();
      const id = (inp.id || '').toLowerCase();
      return (
        p.includes('captcha') ||
        n.includes('captcha') ||
        id.includes('captcha') ||
        p.includes('enter text')
      );
    });
  }

  function tripleClickCaptcha(captchaInput) {
    if (!captchaInput) return;
    try { captchaInput.focus(); } catch (e) {}

    [100, 250, 400].forEach((delay, idx) => {
      setTimeout(() => {
        try {
          captchaInput.focus();
          captchaInput.click();
          captchaInput.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        } catch (e) {}
      }, delay);
    });
  }

  function showConnectBadge(msg, color = '#10b981') {
    let badge = document.getElementById('wb-connect-badge');
    if (!badge) {
      badge = document.createElement('div');
      badge.id = 'wb-connect-badge';
      badge.style.cssText = 'position:fixed;top:15px;right:20px;z-index:99999999;background:#0f172a;color:#fff;padding:12px 20px;border-radius:10px;font-family:system-ui,-apple-system,sans-serif;font-size:13px;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.5);display:flex;align-items:center;gap:10px;border-left:5px solid #10b981;';
      document.body.appendChild(badge);
    }
    badge.style.borderLeftColor = color;
    badge.innerHTML = `<span>${msg}</span>`;
  }

  function runAutoConnectLogin() {
    if (filled) return;

    const cscInput = findCscIdInput();
    const passInput = findPasswordInput();
    const captchaInput = findCaptchaInput();

    if (!cscInput || !passInput) return;

    // Fill Username and Password
    setInputValue(cscInput, CSC_ID);
    setInputValue(passInput, CSC_PASSWORD);

    // 3 clicks on Captcha input as requested
    if (captchaInput) {
      tripleClickCaptcha(captchaInput);
    }

    filled = true;
    showConnectBadge('🔐 CSC ID & Password Auto-Filled • Please enter Captcha & Sign In', '#10b981');

    try {
      chrome.storage.local.set({
        lastStatus: '🔐 Digital Seva Connect: Credentials filled, enter Captcha',
        lastStatusColor: '#10b981'
      });
    } catch (e) {}
  }

  // Poll for dynamic page render
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(runAutoConnectLogin, 300);
    });
  } else {
    setTimeout(runAutoConnectLogin, 300);
  }

  const observer = new MutationObserver(() => {
    if (!filled) {
      runAutoConnectLogin();
    }
  });

  observer.observe(document.documentElement || document.body, { childList: true, subtree: true });

  setInterval(() => {
    if (!filled) {
      runAutoConnectLogin();
    }
  }, 600);
})();
