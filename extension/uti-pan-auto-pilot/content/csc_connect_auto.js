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
      Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], a, [role="button"]')).find((el) =>
        isVisible(el) && /^log\s*in$/i.test((el.value || el.textContent || '').trim())) ||
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

    clickCaptchaSearchOnce();
  }

  // Delegate clicks because the captcha search control can be injected later.
  let searchLoginTimer = null;
  let hasClickedCaptchaSearch = false;
  let loginSubmitted = false;

  function clickCaptchaSearchOnce() {
    if (hasClickedCaptchaSearch || loginSubmitted || searchLoginTimer !== null) return;
    const candidates = Array.from(document.querySelectorAll('button, a, [role="button"], div, span'))
      .filter((el) => !el.disabled && isCaptchaSearchClick({ composedPath: () => [el] }));
    // A wrapper can have the same size/text as its button. Click the actual
    // hit-tested descendant so listeners on that button receive the event.
    const control = candidates.find((el) => el.matches('button, a, [role="button"]')) ||
      candidates.find((el) => !candidates.some((other) => other !== el && el.contains(other)));
    if (!control) return;
    const rect = control.getBoundingClientRect();
    const clientX = rect.left + rect.width / 2;
    const clientY = rect.top + rect.height / 2;
    let target = document.elementFromPoint(clientX, clientY);
    if (!target || !(target === control || control.contains(target))) return;
    while (target.shadowRoot) {
      const inner = target.shadowRoot.elementFromPoint(clientX, clientY);
      if (!inner || inner === target) break;
      target = inner;
    }
    // Mark before clicking: injected controls can synchronously change the DOM.
    hasClickedCaptchaSearch = true;
    const options = { bubbles: true, cancelable: true, composed: true, clientX, clientY, button: 0 };
    for (const type of ['pointerdown', 'mousedown', 'pointerup', 'mouseup', 'click']) {
      const EventClass = type.startsWith('pointer') ? PointerEvent : MouseEvent;
      target.dispatchEvent(new EventClass(type, {
        ...options, buttons: type.endsWith('down') ? 1 : 0, pointerId: 1, pointerType: 'mouse', isPrimary: true
      }));
    }
  }

  function cancelSearchLogin() {
    clearTimeout(searchLoginTimer);
    searchLoginTimer = null;
  }

  function isCaptchaSearchClick(event) {
    const captcha = getCaptchaInput();
    if (!captcha || !isVisible(captcha)) return false;
    const inputRect = captcha.getBoundingClientRect();
    const path = event.composedPath();
    // Inspect each candidate: SVG className is not necessarily a string.
    return path.some((el) => {
      if (!(el instanceof Element) || el === document.body || el === document.documentElement ||
          el === captcha || el.matches('input, img, form')) return false;
      const rect = el.getBoundingClientRect();
      if (!isVisible(el) || rect.width > 90 || rect.height > 90) return false;
      const nearCaptcha = Math.abs((rect.top + rect.bottom) / 2 -
        (inputRect.top + inputRect.bottom) / 2) <= 120 &&
        rect.right >= inputRect.left - 500 && rect.left <= inputRect.right + 120;
      if (!nearCaptcha) return false;
      const label = [el.textContent, el.getAttribute('aria-label'), el.getAttribute('title'),
        el.getAttribute('class'), el.getAttribute('id')].filter(Boolean).join(' ');
      if (/refresh|reload|eye|password/i.test(label)) return false;
      if (/🔍|🔎|search|magnif/i.test(label)) return true;
      // Some extensions use an unlabelled image or a closed shadow-root button.
      // Match only a small control below the captcha image, left of its input.
      const images = Array.from(document.querySelectorAll('img')).filter((img) => {
        const r = img.getBoundingClientRect();
        return isVisible(img) && r.width >= 80 && r.height >= 20 && r.height <= 120 &&
          r.right <= inputRect.left + 20 && r.left >= inputRect.left - 500 &&
          Math.abs((r.top + r.bottom) / 2 - (inputRect.top + inputRect.bottom) / 2) <= 100;
      });
      return images.some((img) => {
        const r = img.getBoundingClientRect();
        return rect.width >= 16 && rect.height >= 16 && rect.left >= r.left - 10 &&
          rect.right <= r.left + 90 && rect.top >= r.bottom - 5 && rect.top <= r.bottom + 45;
      });
    });
  }
  window.addEventListener('click', (event) => {
    const submit = getSubmitButton();
    if (submit && event.composedPath().includes(submit)) {
      loginSubmitted = true;
      cancelSearchLogin();
      return;
    }
    if (!isCaptchaSearchClick(event)) return;
    hasClickedCaptchaSearch = true;
    cancelSearchLogin();
    showConnectBadge('Search clicked • Waiting for Captcha before Log In', '#10b981');
    const deadline = Date.now() + 15000;
    const tryLogin = () => {
      searchLoginTimer = null;
      const captcha = getCaptchaInput();
      const button = getSubmitButton();
      if (!captcha || !String(captcha.value || '').trim()) {
        if (Date.now() < deadline) {
          searchLoginTimer = setTimeout(tryLogin, 250);
          return;
        }
        showConnectBadge('Captcha not filled • Click the captcha search icon manually to retry', '#f59e0b');
        return;
      }
      if (!button || !isVisible(button) || button.disabled) {
        showConnectBadge('Auto-login stopped: Log In button unavailable', '#f59e0b');
        return;
      }
      // click() does not emit mousedown; restore credentials before page encryption.
      const cscInput = getCscIdInput();
      const passInput = getPasswordInput();
      if (cscInput) setInputValue(cscInput, CSC_ID);
      if (passInput) setInputValue(passInput, CSC_PASSWORD);
      loginSubmitted = true;
      button.click();
    };
    searchLoginTimer = setTimeout(tryLogin, 2000);
  }, true);

  document.addEventListener('submit', cancelSearchLogin, true);
  window.addEventListener('pagehide', cancelSearchLogin);

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
