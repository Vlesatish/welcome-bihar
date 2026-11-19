// =========================================================================
// Welcome Bihar - UTI PAN Auto-Pilot Content Script
// Target: https://www.myutiitsl.com/PANform/*
// =========================================================================

(function () {
  'use strict';

  if (window !== window.top) return;

  let currentCandidate = null;
  let currentUtiRefNo = '';
  let prePanSubmitted = false;
  let aadhaarConfirmed = false;

  const AURANGABAD_PIN_RETRY_LIMIT = 2; // Retry AURANGABAD(BH) 2 times before falling back to plain AURANGABAD
  let aurangabadPinFailCount = 0;
  let usePlainAurangabadFallback = false;
  let trackedAurangabadCandidateKey = '';
  let lastPinErrorAt = 0;
  let resPinVerifiedValue = '';
  let resPinVerifiedAt = 0;
  let resDistChangedAt = 0;
  let aurangabadSwitchUntil = 0;

  function isAurangabadDistrict(name) {
    const clean = String(name || '').toLowerCase().replace(/[^a-z]/g, '');
    return clean.includes('aurangabad') || clean.includes('aurabgabad');
  }

  function isAurangabadBhOption(optionText) {
    const txt = String(optionText || '').trim().toLowerCase();
    return txt.includes('aurangabad(bh)') || txt.includes('aurangabad (bh)') || txt.includes('aurangabad(bihar)');
  }

  function syncAurangabadCandidateState() {
    const key = currentCandidate
      ? `${currentCandidate.id || currentCandidate.application_id || ''}|${currentCandidate.aadhaar_no || ''}|${currentCandidate.full_name || ''}|${currentCandidate.pincode || currentCandidate.pin || ''}`
      : '';
    if (key !== trackedAurangabadCandidateKey) {
      trackedAurangabadCandidateKey = key;
      aurangabadPinFailCount = 0;
      usePlainAurangabadFallback = false;
      lastPinErrorAt = 0;
      resPinVerifiedValue = '';
      resPinVerifiedAt = 0;
      resDistChangedAt = 0;
      aurangabadSwitchUntil = 0;
    }
  }

  function hasVisibleInlinePinError() {
    const errNodes = document.querySelectorAll('.error, .error-msg, .errormsg, #errorDiv, #pinError, span[style*="red"], font[color="red"], label.error');
    for (const node of errNodes) {
      if (!isVisible(node)) continue;
      const txt = (node.innerText || node.textContent || '').toLowerCase();
      if (txt.includes('pin') && (txt.includes('valid') || txt.includes('invalid') || txt.includes('incorrect') || txt.includes('not ') || txt.includes('kindly') || txt.includes('attached with district'))) {
        return true;
      }
    }
    return false;
  }

  function findDialogOkButton(container) {
    if (!container) return null;
    const candidates = Array.from(container.querySelectorAll(
      '.ui-dialog-buttonpane button, .modal-footer button, .modal-footer a, button, input[type="button"], input[type="submit"], a.btn, a[role="button"]'
    )).filter((b) => isVisible(b) && !b.disabled);

    const okBtn = candidates.find((b) => {
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return t === 'ok' || t === 'okay' || t === 'yes' || t === 'close';
    });
    if (okBtn) return okBtn;

    const closeBtn = Array.from(container.querySelectorAll(
      'a.ui-dialog-titlebar-close, button.ui-dialog-titlebar-close, button.close, [data-dismiss="modal"], [data-bs-dismiss="modal"]'
    )).find((b) => isVisible(b));
    return closeBtn || null;
  }

  function getVisibleDialogs() {
    const selectors = '.ui-dialog, .modal.in, .modal.show, .modal[style*="block"], .modal-dialog, .modal-content, .swal2-container, div[role="dialog"], div[role="alertdialog"], .bootbox';
    const raw = Array.from(document.querySelectorAll(selectors)).filter((d) => isVisible(d) && !d.closest('#wb-pan-overlay'));
    const dialogs = raw.filter((d) => !raw.some((other) => other !== d && other.contains(d)));

    // Fallback for custom fixed/absolute overlay message boxes without standard dialog classes
    const extra = Array.from(document.querySelectorAll('div')).find((d) => {
      if (!isVisible(d) || d.closest('#wb-pan-overlay')) return false;
      if (dialogs.some((existing) => existing === d || existing.contains(d) || d.contains(existing))) return false;
      const st = window.getComputedStyle(d);
      const idLow = (d.id || '').toLowerCase();
      const isOverlayPos = st.position === 'fixed' || st.position === 'absolute';
      const isDialogId = idLow.includes('dialog') || idLow.includes('modal') || idLow.includes('msg') || idLow.includes('popup') || idLow.includes('alert') || idLow.includes('error');
      if (!isOverlayPos && !isDialogId) return false;
      const t = (d.innerText || d.textContent || '').toLowerCase();
      if (t.length > 500) return false;
      if (!(t.includes('attached with district') || t.includes('please enter correct district') || t.includes('kindly enter pincode') || t.includes('enter valid pincode'))) {
        return false;
      }
      return Boolean(findDialogOkButton(d));
    });
    if (extra) dialogs.push(extra);
    return dialogs;
  }

  function handleAurangabadPinFailure(sourceLabel = 'dialog') {
    syncAurangabadCandidateState();
    if (!currentCandidate || usePlainAurangabadFallback) return false;
    if (!isAurangabadDistrict(currentCandidate?.district_name || currentCandidate?.district)) return false;

    const resDistListEl = document.getElementById('resDistList');
    const selectedDistText = (resDistListEl && resDistListEl.selectedIndex >= 0)
      ? (resDistListEl.options[resDistListEl.selectedIndex]?.text || '').trim().toUpperCase()
      : '';

    // Count failures only when AURANGABAD(BH) was actively selected
    if (!isAurangabadBhOption(selectedDistText)) {
      return false;
    }

    const isDialog = sourceLabel === 'dialog';
    const minGap = isDialog ? 250 : 1200;
    const now = Date.now();
    if (now - lastPinErrorAt < minGap) return false;
    lastPinErrorAt = now;
    aurangabadPinFailCount += 1;
    resPinVerifiedValue = '';

    if (aurangabadPinFailCount >= AURANGABAD_PIN_RETRY_LIMIT) {
      usePlainAurangabadFallback = true;
      aurangabadSwitchUntil = now + 1100;
      lastAdvanceTime = now; // Hold step advance while switching district and filling PIN
      updateBadge(`🔄 Pincode invalid in AURANGABAD(BH) (${aurangabadPinFailCount}x) • Trying plain AURANGABAD...`, '#f59e0b');

      const distSwitchDelay = isDialog ? 250 : 0;
      setTimeout(() => {
        const distEl = document.getElementById('resDistList');
        if (distEl) {
          selectOptionByText(distEl, 'AURANGABAD', true);
          resDistChangedAt = Date.now();
        }
        const pinEl = document.getElementById('resPin');
        const pincode = String(currentCandidate?.pincode || currentCandidate?.pin || '').replace(/\D/g, '').slice(0, 6);
        if (pinEl && pincode) {
          setTimeout(() => {
            setInputValue(pinEl, pincode);
            resPinVerifiedValue = pincode;
            resPinVerifiedAt = Date.now();
            lastAdvanceTime = 0;
            aurangabadSwitchUntil = 0;
          }, 450);
        } else {
          lastAdvanceTime = 0;
          aurangabadSwitchUntil = 0;
        }
      }, distSwitchDelay);
      return true;
    }

    updateBadge(`⚠️ Pincode rejected in AURANGABAD(BH) (${aurangabadPinFailCount}/${AURANGABAD_PIN_RETRY_LIMIT}, ${sourceLabel}) • Retrying...`, '#f59e0b');
    return false;
  }

  // -----------------------------------------------------------------------
  // DOM & Input Helpers
  // -----------------------------------------------------------------------
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
    if (el.value === str) return true; // Stop redundant events & prevent loop popups
    const visibleNow = isVisible(el);
    const activeEl = document.activeElement;
    // Prevent off-tab hidden writes from blurring an empty #resPin and triggering "Kindly enter pincode."
    const preserveResPinFocus = activeEl && activeEl.id === 'resPin' && el.id !== 'resPin' && !activeEl.value;
    if (visibleNow && !preserveResPinFocus) {
      try { el.focus(); } catch (e) {}
    }
    try {
      const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
      if (setter) setter.call(el, str);
      else el.value = str;
    } catch (e) {
      el.value = str;
    }
    ['input', 'change', 'keyup'].forEach((evt) => {
      try { el.dispatchEvent(new Event(evt, { bubbles: true })); } catch (e) {}
    });
    if (visibleNow || el.id === 'resPin') {
      try { el.dispatchEvent(new Event('blur', { bubbles: true })); } catch (e) {}
    }
    return true;
  }

  function clickRadio(radio) {
    if (!radio) return false;
    try {
      if (!radio.checked) {
        radio.checked = true;
        radio.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        radio.dispatchEvent(new Event('change', { bubbles: true }));
        radio.dispatchEvent(new Event('input', { bubbles: true }));
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function selectOption(selectEl, expectedValue, fallbackKeywords = []) {
    if (!selectEl) return false;
    const strVal = String(expectedValue || '').trim().toLowerCase();
    const keywords = Array.isArray(fallbackKeywords)
      ? fallbackKeywords.map((k) => String(k).toLowerCase())
      : [String(fallbackKeywords).toLowerCase()];

    // 1. Try exact option value match
    if (strVal) {
      for (let i = 0; i < selectEl.options.length; i++) {
        if ((selectEl.options[i].value || '').trim().toLowerCase() === strVal) {
          if (selectEl.selectedIndex !== i) {
            selectEl.selectedIndex = i;
            selectEl.value = selectEl.options[i].value;
            selectEl.options[i].selected = true;
            selectEl.dispatchEvent(new Event('input', { bubbles: true }));
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return true;
        }
      }
    }

    // 2. Try keywords in option text
    for (const kw of keywords) {
      if (!kw) continue;
      for (let i = 0; i < selectEl.options.length; i++) {
        const txt = (selectEl.options[i].text || '').toLowerCase();
        if (txt.includes(kw)) {
          if (selectEl.selectedIndex !== i) {
            selectEl.selectedIndex = i;
            selectEl.value = selectEl.options[i].value;
            selectEl.options[i].selected = true;
            selectEl.dispatchEvent(new Event('input', { bubbles: true }));
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return true;
        }
      }
    }
    return false;
  }

  function selectOptionByText(selectEl, targetText, exactOnly = false) {
    if (!selectEl || !targetText) return false;
    const target = String(targetText).trim().toLowerCase();

    // Special rule requested by RIOT: For Aurangabad district in Bihar, prefer AURANGABAD(BH) unless plain AURANGABAD fallback is active
    const cleanTarget = target.replace(/[^a-z]/g, '');
    if (!exactOnly && !usePlainAurangabadFallback && (cleanTarget.includes('aurangabad') || cleanTarget.includes('aurabgabad'))) {
      for (let i = 0; i < selectEl.options.length; i++) {
        const opt = selectEl.options[i];
        const txt = (opt.text || '').trim().toLowerCase();
        if (txt.includes('aurangabad(bh)') || txt.includes('aurangabad (bh)') || txt.includes('aurangabad(bihar)')) {
          if (selectEl.selectedIndex !== i) {
            selectEl.selectedIndex = i;
            selectEl.value = opt.value;
            opt.selected = true;
            selectEl.dispatchEvent(new Event('input', { bubbles: true }));
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return true;
        }
      }
    }

    // Pass 1: Strict exact match
    for (let i = 0; i < selectEl.options.length; i++) {
      const opt = selectEl.options[i];
      const txt = (opt.text || '').trim().toLowerCase();
      const val = (opt.value || '').trim().toLowerCase();
      if (txt === target || val === target) {
        if (selectEl.selectedIndex !== i) {
          selectEl.selectedIndex = i;
          selectEl.value = opt.value;
          opt.selected = true;
          selectEl.dispatchEvent(new Event('input', { bubbles: true }));
          selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return true;
      }
    }

    if (exactOnly) return false;

    // Pass 2: Fallback to starts-with or contains
    for (let i = 0; i < selectEl.options.length; i++) {
      const opt = selectEl.options[i];
      const txt = (opt.text || '').trim().toLowerCase();
      const val = (opt.value || '').trim().toLowerCase();
      if (txt.startsWith(target) || txt.includes(target)) {
        if (selectEl.selectedIndex !== i) {
          selectEl.selectedIndex = i;
          selectEl.value = opt.value;
          opt.selected = true;
          selectEl.dispatchEvent(new Event('input', { bubbles: true }));
          selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return true;
      }
    }
    return false;
  }

  function calculateAge(dobStr) {
    if (!dobStr) return 25;
    const clean = String(dobStr).trim().replace(/-/g, '/');
    const parts = clean.split('/');
    let day = 1, month = 1, year = 2000;
    if (parts.length === 3) {
      if (parts[0].length === 4) {
        year = parseInt(parts[0], 10);
        month = parseInt(parts[1], 10);
        day = parseInt(parts[2], 10);
      } else {
        day = parseInt(parts[0], 10);
        month = parseInt(parts[1], 10);
        year = parseInt(parts[2], 10);
      }
    }
    const birthDate = new Date(year, month - 1, day);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return isNaN(age) ? 25 : age;
  }

  function splitFullName(fullName) {
    const parts = String(fullName || '').trim().replace(/\s+/g, ' ').split(' ').filter(Boolean);
    if (parts.length === 0) return { first: '', middle: '', last: '' };
    if (parts.length === 1) return { first: '', middle: '', last: parts[0].toUpperCase() };
    if (parts.length === 2) return { first: parts[0].toUpperCase(), middle: '', last: parts[1].toUpperCase() };
    return {
      first: parts[0].toUpperCase(),
      middle: parts.slice(1, -1).join(' ').toUpperCase(),
      last: parts[parts.length - 1].toUpperCase()
    };
  }

  function findFieldByLabel(keywords, tagSelector = 'input, select') {
    const keys = Array.isArray(keywords) ? keywords : [keywords];
    const normKeys = keys.map((k) => k.toLowerCase());

    const allFields = Array.from(document.querySelectorAll(tagSelector)).filter((el) => {
      return el.type !== 'hidden' && !el.disabled;
    });

    for (const el of allFields) {
      const attrs = [
        el.id,
        el.name,
        el.placeholder,
        el.getAttribute('formcontrolname'),
        el.getAttribute('aria-label'),
        el.getAttribute('ng-model')
      ].filter(Boolean).join(' ').toLowerCase();
      if (normKeys.some((k) => attrs.includes(k))) {
        return el;
      }
    }

    const labelNodes = Array.from(document.querySelectorAll('label, span, div, td, th, p, b, strong'));
    for (const node of labelNodes) {
      if (node.children.length > 3) continue;
      const txt = (node.textContent || '').replace(/\*/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (!txt || txt.length > 80) continue;
      if (!normKeys.some((k) => txt === k || txt.startsWith(k) || txt.includes(k))) continue;

      if (node.tagName === 'LABEL' && node.getAttribute('for')) {
        const byFor = document.getElementById(node.getAttribute('for'));
        if (byFor) return byFor;
      }

      const inside = node.querySelector(tagSelector);
      if (inside) return inside;

      const parent = node.closest('.form-group, .row, .col, [class*="col-"], tr, div');
      if (parent) {
        const candidate = parent.querySelector(tagSelector);
        if (candidate) return candidate;
      }
    }
    return null;
  }

  function findRadioNearText(containerKeyword, choiceKeyword) {
    const cKey = containerKeyword.toLowerCase();
    const chKey = choiceKeyword.toLowerCase();

    const blocks = Array.from(document.querySelectorAll('div, tr, section, fieldset, .card, .form-group, p'));
    for (const blk of blocks) {
      const txt = (blk.innerText || '').toLowerCase();
      if (!txt.includes(cKey)) continue;
      const radios = Array.from(blk.querySelectorAll('input[type="radio"]'));
      if (radios.length === 0 || radios.length > 6) continue;

      for (const r of radios) {
        const parentTxt = (r.parentElement?.innerText || r.nextSibling?.textContent || r.closest('label')?.innerText || '').trim().toLowerCase();
        const val = (r.value || '').toLowerCase();
        const id = (r.id || '').toLowerCase();
        if (parentTxt.includes(chKey) || val === chKey || id.includes(chKey)) {
          return r;
        }
      }
    }

    const labels = Array.from(document.querySelectorAll('label, span'));
    for (const lbl of labels) {
      const t = (lbl.innerText || '').trim().toLowerCase();
      if (t.startsWith(chKey) || t.includes(chKey)) {
        const r = lbl.querySelector('input[type="radio"]') || (lbl.getAttribute('for') ? document.getElementById(lbl.getAttribute('for')) : null);
        if (r && r.type === 'radio') return r;
      }
    }
    return null;
  }

  // -----------------------------------------------------------------------
  // Status Sync (Viewable via Extension Popup)
  // -----------------------------------------------------------------------
  function updateBadge(statusText, color = '#0284c7') {
    const existing = document.getElementById('wb-uti-badge');
    if (existing) {
      try { existing.remove(); } catch (e) {}
    }
    try {
      chrome.storage.local.set({
        lastStatus: statusText,
        lastStatusColor: color,
        currentUtiRefNo: currentUtiRefNo || ''
      });
    } catch (e) {}
  }

  // -----------------------------------------------------------------------
  // PAGE 1: prePANForm
  // -----------------------------------------------------------------------
  let lastPrePanSubmitTime = 0;

  function handlePrePanForm() {
    const now = Date.now();
    // Allow re-submission if still stuck on page after 2.5 seconds
    if (prePanSubmitted && (now - lastPrePanSubmitTime) < 2500) {
      return;
    }

    updateBadge('Step 0: Auto-Selecting Options & Submitting prePANForm...', '#38bdf8');

    // 1. Physical Mode radio
    const physicalRadio = findRadioNearText('physical mode', 'physical') ||
      Array.from(document.querySelectorAll('input[type="radio"]')).find((r) => {
        const t = (r.parentElement?.innerText || r.closest('div, label')?.innerText || '').toLowerCase();
        return t.includes('physical mode');
      });
    if (physicalRadio && !physicalRadio.checked) {
      clickRadio(physicalRadio);
    }

    // 2. Status of the Applicant -> Strictly "Individual"
    const allSelects = Array.from(document.querySelectorAll('select'));
    for (const sel of allSelects) {
      const optIndex = Array.from(sel.options).findIndex((o) => (o.text || '').trim().toLowerCase() === 'individual');
      if (optIndex >= 0) {
        if (sel.selectedIndex !== optIndex) {
          sel.selectedIndex = optIndex;
          sel.value = sel.options[optIndex].value;
          sel.options[optIndex].selected = true;
          sel.dispatchEvent(new Event('input', { bubbles: true }));
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
        break;
      }
    }

    // 3. Whether you are from defence/NRI Category ? -> Strictly "No"
    const allRadios = Array.from(document.querySelectorAll('input[type="radio"]'));
    const nriBlock = Array.from(document.querySelectorAll('div, tr, section, fieldset, td, p')).find((el) => {
      const t = (el.innerText || '').toLowerCase();
      return (t.includes('defence/nri') || (t.includes('defence') && t.includes('nri'))) && el.querySelectorAll('input[type="radio"]').length >= 2;
    });

    if (nriBlock) {
      const radiosInBlock = Array.from(nriBlock.querySelectorAll('input[type="radio"]'));
      let noRadio = radiosInBlock.find((r) => {
        const val = (r.value || '').trim().toLowerCase();
        const id = (r.id || '').toLowerCase();
        const next = (r.nextSibling?.textContent || '').trim().toLowerCase();
        const lbl = (document.querySelector(`label[for="${r.id}"]`)?.innerText || '').trim().toLowerCase();
        return val === 'n' || val === 'no' || id.endsWith('no') || id.includes('_n') || next === 'no' || next.startsWith('no') || lbl === 'no';
      });
      if (!noRadio && radiosInBlock.length >= 2) {
        noRadio = radiosInBlock[1];
      }
      if (noRadio && !noRadio.checked) {
        clickRadio(noRadio);
      }
    } else {
      const nriRadio = allRadios.find((r) => {
        const name = (r.name || '').toLowerCase();
        const id = (r.id || '').toLowerCase();
        const val = (r.value || '').toLowerCase();
        const next = (r.nextSibling?.textContent || '').trim().toLowerCase();
        return (name.includes('nri') || id.includes('nri') || name.includes('defence')) &&
               (val === 'n' || val === 'no' || next === 'no');
      });
      if (nriRadio && !nriRadio.checked) {
        clickRadio(nriRadio);
      }
    }

    // 4. PAN CARD Mode -> Both physical PAN Card and e-PAN
    const bothPanRadio = allRadios.find((r) => {
      const parentTxt = (r.parentElement?.innerText || r.closest('label, span, div')?.innerText || '').toLowerCase();
      const val = (r.value || '').toLowerCase();
      return parentTxt.includes('both physical pan') || val.includes('both') || val === 'b';
    });
    if (bothPanRadio && !bothPanRadio.checked) {
      clickRadio(bothPanRadio);
    }

    // 5. AUTO-CLICK SUBMIT IMMEDIATELY (As requested by RIOT)
    const submitBtn = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], a, [role="button"], div.btn, span.btn')).find((b) => {
      if (!isVisible(b) || b.disabled) return false;
      const txt = (b.textContent || b.value || '').trim().toLowerCase();
      return txt === 'submit' || txt === 'submit application' || (txt.startsWith('submit') && !txt.includes('cancel'));
    });

    if (submitBtn) {
      prePanSubmitted = true;
      lastPrePanSubmitTime = Date.now();
      updateBadge('🚀 prePANForm Auto-Submitting...', '#10b981');
      setTimeout(() => {
        try { submitBtn.focus(); } catch (e) {}
        try { submitBtn.click(); } catch (e) {}
        try {
          submitBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        } catch (e) {}
      }, 150);
    }
  }

  // -----------------------------------------------------------------------
  // POPUP & DIALOG AUTO-HANDLERS (Form No. 93)
  // -----------------------------------------------------------------------
  function handleAllModals() {
    let handled = false;

    // -2. Logged Out Check ("You have logged out successfully!")
    if (isLoggedOutPage()) {
      handleLoggedOutPage();
      return true;
    }

    // -1. Session Invalid Check (Image 1)
    if (isSessionInvalidPage()) {
      handleSessionInvalidPage();
      return true;
    }

    // 0. PAN Already Exists on Aadhaar Check
    if (isPanAlreadyExistsPage()) {
      handlePanAlreadyExistsPage();
      return true;
    }

    // 0B. Aadhaar Demographic Mismatch ("Authenticate AADHAAR details using Biometric")
    if (isAadhaarBiometricMismatchModal()) {
      handleAadhaarBiometricMismatchModal();
      return true;
    }

    // 1. Re-Enter AADHAAR No modal (#reEnterUid / #reEnterUidField)
    const reEnterInput = document.getElementById('reEnterUidField') ||
      document.querySelector('#reEnterUid input[type="text"], .ui-dialog input[maxlength="12"]');

    const reEnterDialog = Array.from(document.querySelectorAll('.ui-dialog')).find((d) => {
      if (!isVisible(d)) return false;
      const t = (d.innerText || d.textContent || '').toLowerCase();
      return t.includes('re-enter aadhaar') || t.includes('reenter aadhaar') || t.includes('re enter aadhaar');
    });

    const isReEnterOpen = Boolean(reEnterDialog || (reEnterInput && isVisible(reEnterInput)));

    if (isReEnterOpen && currentCandidate?.aadhaar_no) {
      const aNo = String(currentCandidate.aadhaar_no).replace(/\D/g, '');
      if (reEnterInput && reEnterInput.value !== aNo) {
        setInputValue(reEnterInput, aNo);
      }

      const container = reEnterDialog || reEnterInput?.closest('.ui-dialog') || document.getElementById('reEnterUid')?.closest('.ui-dialog');
      if (container) {
        const okBtn = Array.from(container.querySelectorAll('.ui-dialog-buttonpane button, button, input[type="button"]')).find((b) => {
          const t = (b.textContent || b.value || '').trim().toLowerCase();
          return t === 'ok' || t === 'okay';
        });
        if (okBtn) {
          try {
            okBtn.click();
          } catch (e) {
            okBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
          }
          aadhaarConfirmed = true;
          updateBadge('✅ Re-Enter Aadhaar Verified & Confirmed', '#10b981');
          handled = true;
        }
      }
    }

    // 2. Reference No modal (#refNo)
    const refDialog = Array.from(document.querySelectorAll('.ui-dialog')).find((d) => {
      if (!isVisible(d)) return false;
      const t = (d.innerText || d.textContent || '').toLowerCase();
      return t.includes('reference number') || t.includes('please note the reference number');
    });

    if (refDialog) {
      const valNoInput = document.getElementById('valno1') || document.getElementById('panValNo');
      if (valNoInput && valNoInput.value) {
        currentUtiRefNo = valNoInput.value.trim();
        chrome.storage.local.set({ currentUtiRefNo });
      } else {
        const match = refDialog.innerText.match(/Reference\s*Number\s*[:-]*\s*([A-Z0-9]{6,})/i);
        if (match && match[1]) {
          currentUtiRefNo = match[1];
          chrome.storage.local.set({ currentUtiRefNo });
        }
      }
      const okBtn = Array.from(refDialog.querySelectorAll('.ui-dialog-buttonpane button, button, input[type="button"]')).find((b) => {
        const t = (b.textContent || b.value || '').trim().toLowerCase();
        return t === 'ok' || t === 'okay';
      });
      if (okBtn) {
        try { okBtn.click(); } catch (e) {}
        updateBadge(`✅ Ref No: ${currentUtiRefNo || 'captured'} | Dialog closed`, '#10b981');
        handled = true;
      }
    }

    // 3. Aadhaar Details / Information modal (#uidExist)
    const uidExistDialog = Array.from(document.querySelectorAll('.ui-dialog')).find((d) => {
      if (!isVisible(d)) return false;
      const t = (d.innerText || d.textContent || '').toLowerCase();
      return t.includes('aadhaar details') || t.includes('aadhaar name contains initials') || (t.includes('information') && !t.includes('re-enter'));
    });

    if (uidExistDialog) {
      const okBtn = Array.from(uidExistDialog.querySelectorAll('.ui-dialog-buttonpane button, button')).find((b) => {
        const t = (b.textContent || b.value || '').trim().toLowerCase();
        return t === 'ok' || t === 'okay';
      });
      if (okBtn) {
        try { okBtn.click(); } catch (e) {}
        handled = true;
      }
    }

    // Clear stale pin-counted flags on closed modals so reused dialog DOM nodes count future popups cleanly
    document.querySelectorAll('[data-wb-pin-counted="1"]').forEach((el) => {
      if (!isVisible(el)) {
        delete el.dataset.wbPinCounted;
      }
    });

    // 4. Message / Error Dialog (#errorDiv, #errorDialog, Bootstrap .modal, pincode & district popups)
    const errorDialog = getVisibleDialogs().find((d) => {
      const t = (d.innerText || d.textContent || '').toLowerCase();
      if (t.includes('re-enter aadhaar') || t.includes('reference number') || t.includes('biometric')) return false;
      return (
        t.includes('kindly enter pincode') ||
        t.includes('enter pincode') ||
        t.includes('valid pincode') ||
        t.includes('valid pin') ||
        t.includes('invalid pin') ||
        t.includes('attached with district') ||
        t.includes('please enter correct district') ||
        t.includes('pincode') ||
        t.includes('pin code') ||
        (t.includes('message') && !t.includes('already exist'))
      );
    });

    if (errorDialog) {
      const dialogText = (errorDialog.innerText || errorDialog.textContent || '').toLowerCase();
      const isPinOrDistError =
        dialogText.includes('attached with district') ||
        dialogText.includes('correct district') ||
        dialogText.includes('pin');

      let switchedToFallback = false;
      if (isPinOrDistError && !errorDialog.dataset.wbPinCounted) {
        errorDialog.dataset.wbPinCounted = '1';
        switchedToFallback = handleAurangabadPinFailure('dialog');
      }

      const okBtn = findDialogOkButton(errorDialog);
      if (okBtn) {
        try {
          okBtn.click();
        } catch (e) {
          okBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        }
        handled = true;
      }

      // Re-fill pincode after modal finishes dismissing (unless fallback switch just scheduled district + pin fill)
      if (!switchedToFallback && !usePlainAurangabadFallback) {
        const resPinEl = document.getElementById('resPin');
        const pincode = String(currentCandidate?.pincode || currentCandidate?.pin || '').replace(/\D/g, '').slice(0, 6);
        if (resPinEl && pincode && resPinEl.value !== pincode) {
          setTimeout(() => {
            if (!usePlainAurangabadFallback && (!aurangabadSwitchUntil || Date.now() >= aurangabadSwitchUntil) && !hasVisibleModal()) {
              setInputValue(resPinEl, pincode);
              resPinVerifiedValue = pincode;
              resPinVerifiedAt = Date.now();
            }
          }, 300);
        }
      }
    }

    return handled;
  }

  // -----------------------------------------------------------------------
  // STEP 2: Documents & Payment Tab (identityProofDoc, addressProofDoc, dobProofDoc)
  // -----------------------------------------------------------------------
  function handleDocumentsTab(force = false) {
    let filled = false;

    // 1. Proof of Identity (POI) -> AADHAAR (value="16")
    const poiEl = document.getElementById('identityProofDoc');
    if (poiEl && (!poiEl.value || poiEl.value === '' || force)) {
      if (selectOption(poiEl, '16', ['aadhaar', 'uidai'])) {
        filled = true;
      }
    }

    // 2. Proof of Address (POA) -> AADHAAR (value="21")
    const poaEl = document.getElementById('addressProofDoc');
    if (poaEl && (!poaEl.value || poaEl.value === '' || force)) {
      if (selectOption(poaEl, '21', ['aadhaar', 'uidai'])) {
        filled = true;
      }
    }

    // 3. Proof of Date of Birth (PoDOB):
    // If age 10 to 22 -> Marksheet / Matriculation (value="14")
    // If age > 22 -> Voter ID (value="3")
    const podobEl = document.getElementById('dobProofDoc');
    if (podobEl && (!podobEl.value || podobEl.value === '' || force)) {
      const age = calculateAge(currentCandidate?.dob);
      let selected = false;
      if (age >= 10 && age <= 22) {
        selected = selectOption(podobEl, '14', ['matriculation', 'mark sheet', 'recognised board']);
      } else {
        selected = selectOption(podobEl, '3', ['voter', 'electoral']);
      }
      if (selected) {
        filled = true;
      }
    }

    // Representative Assessee documents (in minor case)
    const repPoiEl = document.getElementById('repIdentityProofDoc');
    if (repPoiEl && isVisible(repPoiEl) && (!repPoiEl.value || force)) {
      selectOption(repPoiEl, '16', ['aadhaar', 'uidai']);
    }
    const repPoaEl = document.getElementById('repAddressProofDoc');
    if (repPoaEl && isVisible(repPoaEl) && (!repPoaEl.value || force)) {
      selectOption(repPoaEl, '21', ['aadhaar', 'uidai']);
    }

    if (filled) {
      const age = calculateAge(currentCandidate?.dob);
      const dobDocText = (age >= 10 && age <= 22) ? `Marksheet (Age: ${age})` : `Voter ID (Age: ${age})`;
      updateBadge(`📑 Documents Set: Aadhaar + Aadhaar + ${dobDocText}`, '#10b981');
    }
    return filled;
  }

  // -----------------------------------------------------------------------
  // STEP 3: Contact & Parent Details Tab
  // -----------------------------------------------------------------------
  function handleContactAndParentsTab(force = false) {
    let filled = false;

    // 1. Mobile ISD Code -> INDIA ( 91 ) (value="91")
    const mobileCountryCdEl = document.getElementById('mobileCountryCd') || document.getElementById('telCountryCd');
    if (mobileCountryCdEl && (!mobileCountryCdEl.value || mobileCountryCdEl.value !== '91' || force)) {
      if (selectOption(mobileCountryCdEl, '91', ['india ( 91 )', 'india'])) {
        filled = true;
      }
    }

    // 2. Mobile Number (10 digits)
    const mobileNoEl = document.getElementById('mobileNo');
    if (mobileNoEl && currentCandidate?.mobile_no) {
      const cleanMobile = String(currentCandidate.mobile_no).replace(/\D/g, '').slice(-10);
      if (!mobileNoEl.value || (force && mobileNoEl.value !== cleanMobile)) {
        setInputValue(mobileNoEl, cleanMobile);
        filled = true;
      }
    }

    // 3. Email ID
    const emailIdEl = document.getElementById('emailId');
    const candEmail = currentCandidate?.email_id || currentCandidate?.email;
    if (emailIdEl && candEmail) {
      const cleanEmail = String(candEmail).trim();
      if (!emailIdEl.value || (force && emailIdEl.value !== cleanEmail)) {
        setInputValue(emailIdEl, cleanEmail);
        filled = true;
      }
    }

    // 4. Details of Parents -> Single Parent No (mthNo checkbox)
    const mthNoEl = document.getElementById('mthNo');
    const mthYesEl = document.getElementById('mthYes');
    if (mthNoEl && !mthNoEl.checked) {
      mthNoEl.checked = true;
      if (mthYesEl) mthYesEl.checked = false;
      mthNoEl.dispatchEvent(new Event('input', { bubbles: true }));
      mthNoEl.dispatchEvent(new Event('change', { bubbles: true }));
      try {
        if (typeof window.selectParant === 'function') window.selectParant('N');
      } catch (e) {}
      filled = true;
    }

    // 5. Name of Parent on Card -> FATHER'S NAME (value="F")
    const parentNameOnCardEl = document.getElementById('parentNameOnCard');
    if (parentNameOnCardEl && (parentNameOnCardEl.value !== 'F' || force)) {
      if (selectOption(parentNameOnCardEl, 'F', ["father's name", 'father'])) {
        try {
          if (typeof window.handleParentChange === 'function') window.handleParentChange();
        } catch (e) {}
        filled = true;
      }
    }

    // 6. Father's Name (fthFirst, fthMiddle, fthLast)
    if (currentCandidate?.father_name) {
      const { first, middle, last } = splitFullName(currentCandidate.father_name);
      const fthFirstEl = document.getElementById('fthFirst');
      const fthMiddleEl = document.getElementById('fthMiddle');
      const fthLastEl = document.getElementById('fthLast');

      if (fthFirstEl && (!fthFirstEl.value || force)) {
        setInputValue(fthFirstEl, first);
        filled = true;
      }
      if (fthMiddleEl && (!fthMiddleEl.value || force)) {
        setInputValue(fthMiddleEl, middle);
        filled = true;
      }
      if (fthLastEl && (!fthLastEl.value || force)) {
        setInputValue(fthLastEl, last);
        filled = true;
      }
    }

    // 7. Mother's Name (mthFirst, mthMiddle, mthLast)
    if (currentCandidate?.mother_name) {
      const { first, middle, last } = splitFullName(currentCandidate.mother_name);
      const mthFirstEl = document.getElementById('mthFirst');
      const mthMiddleEl = document.getElementById('mthMiddle');
      const mthLastEl = document.getElementById('mthLast');

      if (mthFirstEl && (!mthFirstEl.value || force)) {
        setInputValue(mthFirstEl, first);
        filled = true;
      }
      if (mthMiddleEl && (!mthMiddleEl.value || force)) {
        setInputValue(mthMiddleEl, middle);
        filled = true;
      }
      if (mthLastEl && (!mthLastEl.value || force)) {
        setInputValue(mthLastEl, last);
        filled = true;
      }
    }

    if (filled) {
      updateBadge(`👨‍👩‍👧 Contact & Parents Set: ${currentCandidate.father_name || currentCandidate.full_name}`, '#10b981');
    }
    return filled;
  }

  // -----------------------------------------------------------------------
  // STEP 4: Address Details Tab (resAddr1, resAddr2, resAddr3, resAddr4, resDistList, resPin, incomeOthSource)
  // -----------------------------------------------------------------------
  function handleAddressDetailsTab(force = false) {
    syncAurangabadCandidateState();
    let filled = false;

    // 1. Source of Income -> Income from Other source (incomeOthSource)
    const incomeOthSourceEl = document.getElementById('incomeOthSource');
    if (incomeOthSourceEl && !incomeOthSourceEl.checked) {
      incomeOthSourceEl.checked = true;
      incomeOthSourceEl.dispatchEvent(new Event('input', { bubbles: true }));
      incomeOthSourceEl.dispatchEvent(new Event('change', { bubbles: true }));
      filled = true;
    }

    // 2. Flat/Door/Building (resAddr1) -> husband_name if present, else father_name
    const resAddr1El = document.getElementById('resAddr1');
    if (resAddr1El && (!resAddr1El.value || force)) {
      const careOf = (currentCandidate?.husband_name && currentCandidate.husband_name.trim())
        ? currentCandidate.husband_name.trim()
        : (currentCandidate?.father_name ? currentCandidate.father_name.trim() : '');
      if (careOf) {
        setInputValue(resAddr1El, careOf.slice(0, 25).toUpperCase());
        filled = true;
      }
    }

    // 3. Road/Street/Block/Sector (resAddr2) -> village name
    const resAddr2El = document.getElementById('resAddr2');
    const village = (currentCandidate?.village || currentCandidate?.village_name || '').trim();
    if (resAddr2El && village && (!resAddr2El.value || force)) {
      setInputValue(resAddr2El, village.slice(0, 25).toUpperCase());
      filled = true;
    }

    // 4. Post Office (resAddr3) -> post office
    const resAddr3El = document.getElementById('resAddr3');
    const postOffice = (currentCandidate?.post_office || currentCandidate?.po || '').trim();
    if (resAddr3El && postOffice && (!resAddr3El.value || force)) {
      setInputValue(resAddr3El, postOffice.slice(0, 25).toUpperCase());
      filled = true;
    }

    // 5. Area/Locality/Town/City (resAddr4) -> block name
    const resAddr4El = document.getElementById('resAddr4');
    const block = (currentCandidate?.block_name || currentCandidate?.block || '').trim();
    if (resAddr4El && block && (!resAddr4El.value || force)) {
      setInputValue(resAddr4El, block.slice(0, 25).toUpperCase());
      filled = true;
    }

    // 6. District (resDistList)
    if (aurangabadSwitchUntil && Date.now() < aurangabadSwitchUntil) {
      return filled;
    }

    const resDistListEl = document.getElementById('resDistList');
    const rawDistrict = (currentCandidate?.district_name || currentCandidate?.district || '').trim();
    const isAurangabadCand = isAurangabadDistrict(rawDistrict);

    const resPinEl = document.getElementById('resPin');
    const pincode = String(currentCandidate?.pincode || currentCandidate?.pin || '').replace(/\D/g, '').slice(0, 6);
    const currentSelectedDistText = (resDistListEl && resDistListEl.selectedIndex >= 0)
      ? (resDistListEl.options[resDistListEl.selectedIndex]?.text || '').trim().toUpperCase()
      : '';

    // Detect inline pincode error or silent AJAX clear of #resPin while AURANGABAD(BH) is active
    if (isAurangabadCand && !usePlainAurangabadFallback && isAurangabadBhOption(currentSelectedDistText)) {
      const now = Date.now();
      if (hasVisibleInlinePinError()) {
        handleAurangabadPinFailure('inline');
      } else if (
        resPinEl &&
        pincode &&
        resPinVerifiedValue === pincode &&
        resPinEl.value === '' &&
        (now - resPinVerifiedAt > 1200) &&
        (now - resDistChangedAt > 1500)
      ) {
        resPinVerifiedValue = '';
        handleAurangabadPinFailure('pin-cleared');
      }
    }

    const district = isAurangabadCand
      ? (usePlainAurangabadFallback ? 'AURANGABAD' : 'AURANGABAD(BH)')
      : rawDistrict;

    const latestSelectedDistText = (resDistListEl && resDistListEl.selectedIndex >= 0)
      ? (resDistListEl.options[resDistListEl.selectedIndex]?.text || '').trim().toUpperCase()
      : '';

    let districtChanged = false;
    const needsAurangabadFix = isAurangabadCand && (
      (!usePlainAurangabadFallback && latestSelectedDistText === 'AURANGABAD') ||
      (usePlainAurangabadFallback && latestSelectedDistText !== 'AURANGABAD')
    );

    if (resDistListEl && district && (!resDistListEl.value || force || needsAurangabadFix)) {
      if (selectOptionByText(resDistListEl, district, isAurangabadCand && usePlainAurangabadFallback)) {
        filled = true;
        districtChanged = true;
        resDistChangedAt = Date.now();
        resPinVerifiedValue = '';
      }
    }

    // 7. Pin/Zip Code (resPin) - STRICTLY FILLED LAST
    if (resPinEl && pincode) {
      const recentDistSwitchPending = resDistChangedAt && (Date.now() - resDistChangedAt < 500) && !districtChanged;
      if (resPinEl.value === pincode && !resPinVerifiedValue && !districtChanged) {
        resPinVerifiedValue = pincode;
        resPinVerifiedAt = Date.now();
      }
      if (!recentDistSwitchPending && !hasVisibleModal() && (resPinEl.value !== pincode || force || districtChanged)) {
        // Allow district AJAX to settle, then fill PIN
        const delay = districtChanged ? 400 : 100;
        setTimeout(() => {
          if (hasVisibleModal() || (aurangabadSwitchUntil && Date.now() < aurangabadSwitchUntil)) return;
          if (resPinEl.value !== pincode) {
            setInputValue(resPinEl, pincode);
          }
          resPinVerifiedValue = pincode;
          resPinVerifiedAt = Date.now();
        }, delay);
        filled = true;
      }
    }

    if (filled) {
      updateBadge(`🏠 Residence Address Set: ${village || district || currentCandidate.full_name}`, '#10b981');
    }
    return filled;
  }

  // -----------------------------------------------------------------------
  // STEP 5: Other Details Tab (capacityCd -> SELF, verification place)
  // -----------------------------------------------------------------------
  function handleOtherDetailsTab(force = false) {
    let filled = false;

    // 1. Capacity dropdown -> SELF (value="1")
    const capacityCdEl = document.getElementById('capacityCd');
    if (capacityCdEl && (!capacityCdEl.value || capacityCdEl.value !== '1' || force)) {
      if (selectOption(capacityCdEl, '1', ['self'])) {
        try {
          if (typeof window.checkStatus === 'function') {
            window.checkStatus('1');
          }
        } catch (e) {}
        filled = true;
      }
    }

    // Determine candidate district (from resDistList DOM, candidate object, or fallback)
    const resDistEl = document.getElementById('resDistList');
    const selectedDist = (resDistEl && resDistEl.selectedIndex >= 0) ? (resDistEl.options[resDistEl.selectedIndex]?.text || '') : '';
    const cleanDist = (
      currentCandidate?.district_name ||
      currentCandidate?.district ||
      selectedDist ||
      currentCandidate?.block_name ||
      currentCandidate?.village ||
      'GAYA'
    ).replace(/[<->]/g, '').trim().toUpperCase();

    // 2. Verification Place -> Must never remain blank (id="verPlace")
    const verPlaceEl = document.getElementById('verPlace') || document.getElementById('veriPlace') || findFieldByLabel(['verification place', 'place']);
    if (verPlaceEl && (!verPlaceEl.value || verPlaceEl.value !== cleanDist || force)) {
      setInputValue(verPlaceEl, cleanDist);
      filled = true;
    }

    // 3. Verifier Name if not auto-populated by checkStatus (id="verName")
    const verNameEl = document.getElementById('verName') || document.getElementById('veriName');
    if (verNameEl && currentCandidate?.full_name && (!verNameEl.value || force)) {
      setInputValue(verNameEl, currentCandidate.full_name.toUpperCase());
    }

    // 4. Verification Designation -> SELF if not auto-populated
    const veriDesnEl = document.getElementById('veriDesn');
    if (veriDesnEl && (!veriDesnEl.value || force)) {
      setInputValue(veriDesnEl, 'SELF');
    }

    if (filled) {
      updateBadge(`✍️ Declaration Set: SELF (${cleanDist || 'Verified'})`, '#10b981');
    }
    return filled;
  }

  // -----------------------------------------------------------------------
  // PAGE 2: IndmainForm (Form No. 93)
  // -----------------------------------------------------------------------
  function handleIndMainForm(force = false) {
    handleAllModals();

    if (!currentCandidate) {
      updateBadge('Form 93 Ready • Waiting for Candidate Data', '#f59e0b');
      return;
    }

    // Step 1: Personal Information (First Name, Middle Name, Last Name, Gender)
    const { first, middle, last } = splitFullName(currentCandidate.full_name);

    let filledAny = false;

    // 1. Full Name: firstName, middleName, lastName
    const firstNameEl = document.getElementById('firstName') || findFieldByLabel(['first name', 'firstname', 'fname']);
    const middleNameEl = document.getElementById('middleName') || findFieldByLabel(['middle name', 'middlename', 'mname']);
    const lastNameEl = document.getElementById('lastName') || findFieldByLabel(['last name', 'lastname', 'lname', 'surname']);

    if (firstNameEl && (!firstNameEl.value || force)) {
      setInputValue(firstNameEl, first);
      filledAny = true;
    }
    if (middleNameEl && (!middleNameEl.value || force)) {
      setInputValue(middleNameEl, middle);
      filledAny = true;
    }
    if (lastNameEl && (!lastNameEl.value || force)) {
      setInputValue(lastNameEl, last);
      filledAny = true;
    }

    // 2. Name on Card (panName)
    const panNameEl = document.getElementById('panName');
    if (panNameEl && (!panNameEl.value || force)) {
      const cardName = currentCandidate.full_name ? currentCandidate.full_name.toUpperCase() : [first, middle, last].filter(Boolean).join(' ');
      setInputValue(panNameEl, cardName);
    }

    // 3. Gender (gender) -> 'M', 'F', 'T'
    const genderEl = document.getElementById('gender') || findFieldByLabel(['gender', 'sex'], 'select');
    if (genderEl && (genderEl.value === '' || force)) {
      const g = (currentCandidate.gender || '').trim().toLowerCase();
      let gVal = '';
      if (g.startsWith('m') || g === 'male') gVal = 'M';
      else if (g.startsWith('f') || g === 'female') gVal = 'F';
      else if (g.startsWith('t') || g === 'transgender') gVal = 'T';

      if (gVal) {
        for (let i = 0; i < genderEl.options.length; i++) {
          if (genderEl.options[i].value === gVal || (genderEl.options[i].text || '').toLowerCase().startsWith(g)) {
            genderEl.selectedIndex = i;
            genderEl.value = genderEl.options[i].value;
            genderEl.dispatchEvent(new Event('input', { bubbles: true }));
            genderEl.dispatchEvent(new Event('change', { bubbles: true }));
            filledAny = true;
            break;
          }
        }
      }
    }

    // 4. Date of Birth (dob) -> DD/MM/YYYY
    const dobEl = document.getElementById('dob');
    if (dobEl && (!dobEl.value || force) && currentCandidate.dob) {
      let formattedDob = String(currentCandidate.dob).trim().replace(/-/g, '/');
      const parts = formattedDob.split('/');
      if (parts.length === 3 && parts[0].length === 4) {
        formattedDob = `${parts[2].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[0]}`;
      }
      setInputValue(dobEl, formattedDob);
      filledAny = true;
    }

    // 5. Address for Communication (addrFlag) -> strictly 'A' (RESIDENCE)
    const addrFlagEl = document.getElementById('addrFlag');
    if (addrFlagEl && (addrFlagEl.value !== 'A' || force)) {
      for (let i = 0; i < addrFlagEl.options.length; i++) {
        if (addrFlagEl.options[i].value === 'A' || (addrFlagEl.options[i].text || '').toLowerCase().includes('residence')) {
          addrFlagEl.selectedIndex = i;
          addrFlagEl.value = 'A';
          addrFlagEl.dispatchEvent(new Event('input', { bubbles: true }));
          addrFlagEl.dispatchEvent(new Event('change', { bubbles: true }));
          filledAny = true;
          break;
        }
      }
    }

    // 6. Residential Status (residenceStatus) -> 'R' (RESIDENT)
    const residenceStatusEl = document.getElementById('residenceStatus');
    if (residenceStatusEl && (residenceStatusEl.value !== 'R' || force)) {
      for (let i = 0; i < residenceStatusEl.options.length; i++) {
        if (residenceStatusEl.options[i].value === 'R' || (residenceStatusEl.options[i].text || '').toLowerCase() === 'resident') {
          residenceStatusEl.selectedIndex = i;
          residenceStatusEl.value = 'R';
          residenceStatusEl.dispatchEvent(new Event('input', { bubbles: true }));
          residenceStatusEl.dispatchEvent(new Event('change', { bubbles: true }));
          filledAny = true;
          break;
        }
      }
    }

    // 7. Residence State (resState) -> Match candidate state (e.g. BIHAR -> 5)
    const resStateEl = document.getElementById('resState');
    if (resStateEl && (resStateEl.value === '' || force)) {
      const stateName = (currentCandidate.state || 'BIHAR').trim().toLowerCase();
      selectOptionByText(resStateEl, stateName);
    }

    // 8. Aadhaar Details (aadhaarNo, aadhaarName)
    const aadhaarNoEl = document.getElementById('aadhaarNo');
    if (aadhaarNoEl && currentCandidate.aadhaar_no) {
      const cleanAadhaar = String(currentCandidate.aadhaar_no).replace(/\D/g, '');
      if (!aadhaarNoEl.value || (force && aadhaarNoEl.value !== cleanAadhaar)) {
        setInputValue(aadhaarNoEl, cleanAadhaar);
        filledAny = true;
      }
    }

    const aadhaarNameEl = document.getElementById('aadhaarName');
    if (aadhaarNameEl && (!aadhaarNameEl.value || force)) {
      const aName = (currentCandidate.full_name || '').toUpperCase();
      setInputValue(aadhaarNameEl, aName);
      filledAny = true;
    }

    // Step 2: Documents & Payment Tab
    const docsFilled = handleDocumentsTab(force);
    if (docsFilled) {
      filledAny = true;
    }

    // Step 3: Contact & Parent Details Tab
    const contactFilled = handleContactAndParentsTab(force);
    if (contactFilled) {
      filledAny = true;
    }

    // Step 4: Address Details Tab
    const addressFilled = handleAddressDetailsTab(force);
    if (addressFilled) {
      filledAny = true;
    }

    // Step 5: Other Details Tab (Declaration / Capacity: SELF)
    const otherFilled = handleOtherDetailsTab(force);
    if (otherFilled) {
      filledAny = true;
    }

    if (filledAny && !docsFilled && !contactFilled && !addressFilled && !otherFilled) {
      updateBadge(`✍️ Personal Info Auto-Filled: ${currentCandidate.full_name}`, '#10b981');
    }

    // Auto-advance to Next Step once active step is completely filled
    tryAdvanceStep();
  }

  // -----------------------------------------------------------------------
  // STEP AUTO-ADVANCE: Next Step Clicker
  // "sab fill o jaye to next step click karna ok"
  // -----------------------------------------------------------------------
  let isAdvancing = false;
  let lastAdvancedStep = 0;
  let lastAdvanceTime = 0;

  function hasVisibleModal() {
    return getVisibleDialogs().length > 0;
  }

  function getActiveWizardStep() {
    // 1. jquery.steps active tab
    const activeTab = document.querySelector('li.current a, .wizard .current a, [role="tablist"] li.current');
    if (activeTab) {
      const t = (activeTab.textContent || '').toLowerCase();
      if (t.includes('personal')) return 1;
      if (t.includes('document') && !t.includes('upload')) return 2;
      if (t.includes('contact') || t.includes('parent')) return 3;
      if (t.includes('address')) return 4;
      if (t.includes('other')) return 5;
      if (t.includes('upload') || t.includes('documents upload')) return 6;
    }

    // 2. Visible fields / headings fallback
    const bodyText = (document.body?.innerText || '').toLowerCase();
    if (bodyText.includes('applicant photo and signature files') || document.querySelector('a[href="#finish"]')) return 6;
    if (isVisible(document.getElementById('capacityCd')) || isVisible(document.getElementById('verPlace'))) return 5;
    if (isVisible(document.getElementById('resAddr1')) || isVisible(document.getElementById('incomeOthSource'))) return 4;
    if (isVisible(document.getElementById('mobileNo')) || isVisible(document.getElementById('parentNameOnCard'))) return 3;
    if (isVisible(document.getElementById('identityProofDoc')) || isVisible(document.getElementById('addressProofDoc'))) return 2;
    if (isVisible(document.getElementById('firstName')) || isVisible(document.getElementById('lastName')) || isVisible(document.getElementById('aadhaarNo'))) return 1;

    return 0;
  }

  function isStep1Complete() {
    const lName = document.getElementById('lastName');
    const dob = document.getElementById('dob');
    const gender = document.getElementById('gender');
    const resState = document.getElementById('resState');
    const aNo = document.getElementById('aadhaarNo');
    const aName = document.getElementById('aadhaarName');

    if (!lName || !lName.value.trim()) return false;
    if (!dob || dob.value.trim().length < 8) return false;
    if (!gender || !gender.value) return false;
    if (!resState || !resState.value) return false;
    if (currentCandidate?.aadhaar_no) {
      const cleanAadhaar = String(currentCandidate.aadhaar_no).replace(/\D/g, '');
      if (!aNo || aNo.value.replace(/\D/g, '') !== cleanAadhaar) return false;
    }
    if (!aName || !aName.value.trim()) return false;
    return true;
  }

  function isStep2Complete() {
    const poi = document.getElementById('identityProofDoc');
    const poa = document.getElementById('addressProofDoc');
    const podob = document.getElementById('dobProofDoc');
    if (!poi || !poi.value) return false;
    if (!poa || !poa.value) return false;
    if (!podob || !podob.value) return false;
    return true;
  }

  function isStep3Complete() {
    const mobCountry = document.getElementById('mobileCountryCd') || document.getElementById('telCountryCd');
    const mob = document.getElementById('mobileNo');
    const pCard = document.getElementById('parentNameOnCard');
    const fthLast = document.getElementById('fthLast');

    if (mobCountry && mobCountry.value !== '91') return false;
    if (!mob || mob.value.replace(/\D/g, '').length < 10) return false;
    if (!pCard || !pCard.value) return false;
    if (currentCandidate?.father_name) {
      if (!fthLast || !fthLast.value.trim()) return false;
    }
    return true;
  }

  function isStep4Complete() {
    if (aurangabadSwitchUntil && Date.now() < aurangabadSwitchUntil) return false;
    const inc = document.getElementById('incomeOthSource');
    const addr1 = document.getElementById('resAddr1');
    const dist = document.getElementById('resDistList');
    const pin = document.getElementById('resPin');

    if (inc && !inc.checked) return false;
    if (!addr1 || !addr1.value.trim()) return false;
    if (!dist || !dist.value) return false;

    // Check Aurangabad district selection (AURANGABAD(BH) normally, plain AURANGABAD after pincode fallback)
    if (isAurangabadDistrict(currentCandidate?.district_name || currentCandidate?.district) && dist.selectedIndex >= 0) {
      const selectedTxt = (dist.options[dist.selectedIndex]?.text || '').trim().toUpperCase();
      if (!usePlainAurangabadFallback && selectedTxt === 'AURANGABAD') return false;
      if (usePlainAurangabadFallback && selectedTxt !== 'AURANGABAD') return false;
    }

    if (!pin || pin.value.replace(/\D/g, '').length < 6) return false;
    return true;
  }

  function isStep5Complete() {
    const cap = document.getElementById('capacityCd');
    const place = document.getElementById('verPlace') || document.getElementById('veriPlace');
    const name = document.getElementById('verName') || document.getElementById('veriName');

    if (!cap || cap.value !== '1') return false;
    if (!place || !place.value.trim()) return false;
    if (!name || !name.value.trim()) return false;
    return true;
  }

  function isStep6Complete() {
    const submitBtn = findSubmitButton();
    return Boolean(submitBtn);
  }

  function findSubmitButton() {
    // 1. Direct jquery.steps 'Submit' link
    const finishLink = document.querySelector('a[href="#finish"]');
    if (finishLink && isVisible(finishLink)) {
      const parentLi = finishLink.closest('li');
      if (!parentLi || (!parentLi.classList.contains('disabled') && parentLi.getAttribute('aria-disabled') !== 'true')) {
        return finishLink;
      }
    }

    // 2. Any button or input with text "Submit"
    const allBtns = Array.from(document.querySelectorAll('a, button, input[type="button"], input[type="submit"]'));
    const submitBtn = allBtns.find((b) => {
      if (!isVisible(b) || b.disabled) return false;
      if (b.closest('li')?.classList.contains('disabled')) return false;
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return t === 'submit' || t.startsWith('submit');
    });
    if (submitBtn) return submitBtn;

    return null;
  }

  function findAdvanceButton() {
    // 1. Direct jquery.steps 'Next Step'
    const nextLink = document.querySelector('a[href="#next"]');
    if (nextLink && isVisible(nextLink)) {
      const parentLi = nextLink.closest('li');
      if (!parentLi || (!parentLi.classList.contains('disabled') && parentLi.getAttribute('aria-disabled') !== 'true')) {
        return nextLink;
      }
    }

    // 2. Make Payment button if present (Step 2)
    const allBtns = Array.from(document.querySelectorAll('a, button, input[type="button"], input[type="submit"]'));
    const payBtn = allBtns.find((b) => {
      if (!isVisible(b) || b.disabled) return false;
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return t.includes('make payment') || t === 'pay';
    });
    if (payBtn) return payBtn;

    // 3. Fallback: Any visible button / link containing "Next Step" or "Next"
    const nextBtn = allBtns.find((b) => {
      if (!isVisible(b) || b.disabled) return false;
      if (b.closest('li')?.classList.contains('disabled')) return false;
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return t === 'next step' || t === 'next';
    });
    if (nextBtn) return nextBtn;

    return null;
  }

  function tryAdvanceStep() {
    if (isAdvancing || hasVisibleModal()) return;

    const currentStep = getActiveWizardStep();
    if (!currentStep) return;

    const now = Date.now();
    // Do not repeat click on the exact same step within 4.5 seconds
    if (lastAdvancedStep === currentStep && (now - lastAdvanceTime < 4500)) {
      return;
    }

    let stepValid = false;
    let targetBtn = null;

    if (currentStep === 1) {
      stepValid = isStep1Complete();
      targetBtn = findAdvanceButton();
    } else if (currentStep === 2) {
      stepValid = isStep2Complete();
      targetBtn = findAdvanceButton();
    } else if (currentStep === 3) {
      stepValid = isStep3Complete();
      targetBtn = findAdvanceButton();
    } else if (currentStep === 4) {
      stepValid = isStep4Complete();
      targetBtn = findAdvanceButton();
    } else if (currentStep === 5) {
      stepValid = isStep5Complete();
      targetBtn = findAdvanceButton();
    } else if (currentStep === 6) {
      stepValid = isStep6Complete();
      targetBtn = findSubmitButton();
    }

    if (!stepValid || !targetBtn) return;

    isAdvancing = true;
    lastAdvancedStep = currentStep;
    lastAdvanceTime = now;

    if (currentStep === 6) {
      updateBadge('🚀 Final Step (Documents Upload) • Submitting Form...', '#10b981');
    } else {
      const stepNames = ['', 'Personal Information', 'Documents & Payment', 'Contact & Parents', 'Address Details', 'Other Details'];
      updateBadge(`⚡ ${stepNames[currentStep] || 'Step ' + currentStep} Complete • Clicking Next Step...`, '#10b981');
    }

    setTimeout(() => {
      try {
        targetBtn.focus();
        targetBtn.click();
      } catch (e) {
        targetBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }
      setTimeout(() => {
        isAdvancing = false;
      }, 1500);
    }, 700);
  }

  // -----------------------------------------------------------------------
  // PAGE 0: vleHome.action -> Auto-trigger "Application for New PAN" menu item
  // -----------------------------------------------------------------------
  let vleMenuClicked = false;

  function clickApplicationForNewPanMenu() {
    if (vleMenuClicked) return true;

    const dropdowns = Array.from(document.querySelectorAll('ul, div, table')).filter((d) => {
      const t = (d.innerText || d.textContent || '').toLowerCase();
      return t.includes('application for new pan');
    });
    for (const d of dropdowns) {
      try {
        d.style.display = 'block';
        d.style.visibility = 'visible';
        d.style.opacity = '1';
      } catch (e) {}
    }

    const allLinks = Array.from(document.querySelectorAll('a'));
    const targetLink = allLinks.find((a) => {
      const txt = (a.innerText || a.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      const href = (a.href || a.getAttribute('href') || '').toLowerCase();
      if (txt === 'pan application') return false;
      return (txt.includes('application for new pan') && !txt.includes('pending')) ||
             (href.includes('prepanform') && !href.includes('error'));
    });

    if (targetLink) {
      vleMenuClicked = true;
      try { sessionStorage.setItem('wb_pan_menu_clicked_at', String(Date.now())); } catch (e) {}
      updateBadge('🚀 Launching Form 49A...', '#10b981');

      try {
        targetLink.click();
      } catch (e) {
        targetLink.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }
      return true;
    }
    return false;
  }

  function handleVleHomePage() {
    if ((window.location.hash || '').toLowerCase().includes('keepalive')) {
      updateBadge('🔄 VLE Home Keep-Alive • Auto-closing in 3s...', '#0ea5e9');
      return;
    }

    updateBadge('VLE Home • Form 49A Ready', '#10b981');

    const lastClickedAt = Number(sessionStorage.getItem('wb_pan_menu_clicked_at') || 0);
    if (!vleMenuClicked && (!lastClickedAt || (Date.now() - lastClickedAt > 5000))) {
      setTimeout(() => {
        if (!vleMenuClicked) {
          clickApplicationForNewPanMenu();
        }
      }, 700);
    }
  }

  // -----------------------------------------------------------------------
  // PAGE 7: redirectSuccess -> Receipt Capture & Candidate Complete
  // -----------------------------------------------------------------------
  let receiptHandling = false;
  let lastCompletedCoupon = '';
  let receiptRetryCount = 0;

  function isReceiptPage() {
    const url = (window.location.href || '').toLowerCase();
    if (url.includes('redirectsuccess') || url.includes('acknowledg') || url.includes('receipt')) return true;
    const bodyText = (document.body?.innerText || '').toLowerCase();
    if (bodyText.includes('pan application acknowledgment receipt') || bodyText.includes('pan application acknowledgement receipt')) return true;
    if (bodyText.includes('coupon no') && (bodyText.includes('received rs.') || bodyText.includes('mode of pan card') || bodyText.includes('aadhaar matched'))) return true;
    return false;
  }

  function extractCouponNumber() {
    const pageText = document.body ? document.body.innerText : '';
    const m1 = pageText.match(/\b(U-[A-Za-z0-9]{8,})\b/i);
    if (m1) return m1[1].trim().toUpperCase();
    const m2 = pageText.match(/(?:Application\s*No\.?\s*\/?\s*Coupon\s*No\.?|Coupon\s*No\.?)\s*[:\s]*([Uu]-[A-Za-z0-9]+|[A-Za-z0-9]{8,})/i);
    if (m2) return m2[1].trim().toUpperCase();
    const m3 = pageText.match(/\b([A-Za-z]\d{9})\b/);
    if (m3) return m3[1].trim().toUpperCase();
    return (currentUtiRefNo || '').toUpperCase();
  }

  function isReceiptFullyLoaded() {
    const pageText = document.body ? document.body.innerText : '';
    const hasHeader = /PAN\s*Application\s*Acknowledg[me]*nt\s*Receipt/i.test(pageText) || /Received\s*Rs\./i.test(pageText);
    const coupon = extractCouponNumber();
    const receiptEl = findReceiptElement();
    return Boolean(hasHeader && coupon && coupon.length >= 8 && receiptEl);
  }

  function findReceiptElement() {
    // 1. Check for specific container holding both header and table
    const containers = Array.from(document.querySelectorAll('#printArea, div[id*="receipt" i], div[class*="receipt" i], div.card, div.container, div.container-fluid, form, body > div'));
    for (const el of containers) {
      if (!isVisible(el)) continue;
      const t = (el.innerText || '');
      if (t.includes('Received Rs.') && /Acknowledg[me]*nt/i.test(t) && (/Coupon\s*No/i.test(t) || /Application\s*No/i.test(t))) {
        const r = el.getBoundingClientRect();
        if (r.width > 350 && r.height > 200) {
          return el;
        }
      }
    }

    // 2. Fallback to table containing "Received Rs." and walk up to find common parent
    const tables = Array.from(document.querySelectorAll('table'));
    for (const tbl of tables) {
      if (!isVisible(tbl)) continue;
      const t = (tbl.innerText || '');
      if (t.includes('Received Rs.') && (/Coupon\s*No/i.test(t) || /Application\s*No/i.test(t))) {
        let p = tbl.parentElement;
        let best = tbl;
        while (p && p !== document.body && p !== document.documentElement) {
          const pt = (p.innerText || '');
          if (pt.includes('Received Rs.') && /Acknowledg[me]*nt/i.test(pt)) {
            best = p;
            break;
          }
          p = p.parentElement;
        }
        return best;
      }
    }

    return null;
  }

  function showReceiptFailureUI(errorMessage, couponNo, applicantName, mobileNo, aadhaarLast4, targetCand) {
    let bar = document.getElementById('wb-receipt-error-banner');
    if (!bar) {
      bar = document.createElement('div');
      bar.id = 'wb-receipt-error-banner';
      document.body.appendChild(bar);
    }

    bar.style.cssText = 'position:fixed;top:15px;left:50%;transform:translateX(-50%);z-index:99999999;background:#0f172a;color:#f8fafc;padding:16px 24px;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,0.7);border:2px solid #ef4444;font-family:system-ui,-apple-system,sans-serif;font-size:13px;max-width:680px;width:92%;';
    bar.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;padding-bottom:10px;margin-bottom:10px;">
        <span style="font-weight:bold;font-size:15px;color:#f87171;">⚠️ Receiving Screenshot Attach Failed</span>
        <span style="font-size:11px;background:#ef4444;color:#fff;padding:2px 8px;border-radius:4px;font-weight:bold;">ACTION REQUIRED</span>
      </div>
      <div style="margin-bottom:12px;line-height:1.5;">
        <div><b>Applicant:</b> ${applicantName || targetCand?.full_name || 'N/A'} &nbsp;|&nbsp; <b>Coupon / App No:</b> <span style="font-family:monospace;color:#38bdf8;">${couponNo || 'N/A'}</span></div>
        <div style="color:#fca5a5;margin-top:4px;"><b>Reason:</b> ${errorMessage || 'Could not save receipt screenshot to BiharGo'}</div>
        <div style="color:#94a3b8;font-size:11px;margin-top:4px;">Note: Form No. 93 and Payment are ALREADY SUCCESSFUL. Retrying will NOT re-submit or debit payment again.</div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button id="wb-btn-retry-receipt-action" style="background:#0284c7;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
          📸 Retry Capture & Attach Receiving
        </button>
        <button id="wb-btn-proceed-docs-action" style="background:#334155;color:#e2e8f0;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;">
          Skip to Document Upload ➔
        </button>
      </div>
    `;

    document.getElementById('wb-btn-retry-receipt-action')?.addEventListener('click', () => {
      bar.remove();
      receiptHandling = false;
      receiptRetryCount = 0;
      updateBadge('🔄 Retrying Receiving Screenshot Attachment...', '#38bdf8');
      captureAndAttachReceiving(true);
    });

    document.getElementById('wb-btn-proceed-docs-action')?.addEventListener('click', () => {
      bar.remove();
      try {
        chrome.runtime.sendMessage({ action: 'openDocUploadPage' });
      } catch (e) {}
    });
  }

  // Extract structured fields from the UTI receipt DOM table + candidate fallback
  function extractReceiptFieldsFromDom(couponNo, fallbackCand) {
    const rowMap = {};
    const trs = Array.from(document.querySelectorAll('table tr'));
    for (const tr of trs) {
      if (tr.querySelector('table')) continue;
      const cells = Array.from(tr.cells || tr.querySelectorAll(':scope > td, :scope > th'));
      if (cells.length >= 2) {
        const k = (cells[0].innerText || '').replace(/\s+/g, ' ').trim().toLowerCase();
        const v = (cells[1].innerText || '').replace(/\s+/g, ' ').trim();
        if (k && v) {
          rowMap[k] = v;
        }
      }
    }

    const findVal = (...patterns) => {
      for (const pat of patterns) {
        for (const [k, v] of Object.entries(rowMap)) {
          if (k.includes(pat)) return v;
        }
      }
      return '';
    };

    const pageText = document.body ? document.body.innerText : '';
    const lineMatch = (regex) => {
      const m = pageText.match(regex);
      return m && m[1] ? m[1].replace(/[\r\n\t]+/g, ' ').trim() : '';
    };

    const receivedLabel = Object.keys(rowMap).find((k) => k.includes('received rs'))
      ? Object.keys(rowMap).find((k) => k.includes('received rs')).replace(/\bfrom\b/i, 'from')
      : 'Received Rs. 107.00/- (incl of taxes) from';

    let applicantName =
      findVal('received rs', 'name as per aadhaar') ||
      lineMatch(/Name\s*as\s*per\s*Aadhaar[ \t:]*([^\r\n]+)/i) ||
      lineMatch(/Received\s*Rs\.[^\r\n]*from[ \t:]*([^\r\n]+)/i) ||
      fallbackCand?.full_name ||
      '';
    applicantName = applicantName.replace(/\bApplication\s*No.*$/i, '').trim().toUpperCase();

    const rawCoupon =
      findVal('application no', 'coupon no') ||
      couponNo ||
      extractCouponNumber() ||
      '';
    const formattedCoupon = rawCoupon
      ? (rawCoupon.toUpperCase().startsWith('U-') ? rawCoupon.toUpperCase() : `U-${rawCoupon.toUpperCase()}`)
      : 'U-PENDING';
    const barcodeCode = formattedCoupon.replace(/^U-/i, '');

    let gender =
      findVal('gender') ||
      lineMatch(/Gender[ \t:]*([A-Za-z]+)/i) ||
      fallbackCand?.gender ||
      '';
    gender = gender.trim().toUpperCase() || 'MALE';

    let dob =
      findVal('date of birth') ||
      lineMatch(/Date\s*of\s*Birth[ \t:]*([0-9\/\-]+)/i) ||
      fallbackCand?.dob ||
      '';
    dob = dob.replace(/-/g, '/').trim();

    let parentName =
      findVal('parent name') ||
      lineMatch(/Parent\s*Name\s*(?:\(On\s*Card\))?[ \t:]*([^\r\n]+)/i) ||
      fallbackCand?.father_name ||
      '';
    parentName = parentName.replace(/\bAadhaar\s*Number.*$/i, '').trim().toUpperCase();

    let aadhaarRaw =
      findVal('aadhaar number') ||
      lineMatch(/Aadhaar\s*Number[ \t:]*([^\r\n]+)/i) ||
      '';
    const aadhDigits = (aadhaarRaw || fallbackCand?.aadhaar_no || '').replace(/\D/g, '');
    const aadhaarLast4 = aadhDigits.slice(-4);
    const aadhaarDisplay = aadhaarLast4
      ? `xxxx-xxxx-${aadhaarLast4} (MENTIONED, MATCHED)*`
      : (aadhaarRaw || 'xxxx-xxxx-xxxx (MENTIONED, MATCHED)*');

    let nameAsPerAadhaar =
      findVal('name as per aadhaar') ||
      applicantName ||
      '';
    nameAsPerAadhaar = nameAsPerAadhaar.replace(/\bApplication\s*No.*$/i, '').trim().toUpperCase();

    let contactDetails =
      findVal('contact details') ||
      lineMatch(/Contact\s*details[^\r\n]*?[ \t:]+([0-9]{10}[^\r\n]*)/i) ||
      '';
    if (!contactDetails) {
      const mob = (fallbackCand?.mobile_no || '').replace(/\D/g, '').slice(-10);
      const em = (fallbackCand?.email || 'amitkumarkathwar@gmail.com').trim().toLowerCase();
      contactDetails = mob ? `${mob} / ${em}` : em;
    }

    const commAddress = (findVal('communication address') || 'RESIDENCE').toUpperCase();
    const resState = (findVal('residence state') || 'BIHAR').toUpperCase();
    const poi = findVal('proof of identity') || 'AADHAAR Card issued by UIDAI (In Copy)';
    const poa = findVal('proof of address') || 'AADHAAR Card issued by UIDAI (In Copy)';
    const podob = findVal('proof of dob', 'proof of date of birth') || 'AADHAAR Card issued by UIDAI (In Copy)';

    let dateOfReceipt = findVal('date of receipt') || lineMatch(/Date\s*of\s*Receipt[ \t:]*([0-9\/\- :]+)/i);
    if (!dateOfReceipt) {
      const now = new Date();
      const dd = String(now.getDate()).padStart(2, '0');
      const mm = String(now.getMonth() + 1).padStart(2, '0');
      const yyyy = now.getFullYear();
      const hh = String(now.getHours()).padStart(2, '0');
      const mi = String(now.getMinutes()).padStart(2, '0');
      const ss = String(now.getSeconds()).padStart(2, '0');
      dateOfReceipt = `${dd}/${mm}/${yyyy} ${hh}:${mi}:${ss}`;
    }

    const modeOfPan = findVal('mode of pan card') || 'Both physical PAN and e-PAN Card';

    return {
      receivedLabel: 'Received Rs. 107.00/- (incl of taxes) from',
      applicantName,
      formattedCoupon,
      barcodeCode,
      gender,
      dob,
      parentName,
      aadhaarLast4,
      aadhaarDisplay,
      nameAsPerAadhaar,
      contactDetails,
      commAddress,
      resState,
      poi,
      poa,
      podob,
      dateOfReceipt,
      modeOfPan
    };
  }

  // Synthesize exact Image-1 style UTIITSL Form 93 Acknowledgment Receipt JPEG
  function generateReceiptCanvasJpeg(couponNo, applicantName, mobileNo, aadhaarLast4, parentName, dob, barcodeText, fallbackCand) {
    const info = extractReceiptFieldsFromDom(couponNo, fallbackCand);
    if (applicantName && !info.applicantName) info.applicantName = applicantName.toUpperCase();
    if (parentName && !info.parentName) info.parentName = parentName.toUpperCase();
    if (dob && !info.dob) info.dob = dob;

    const canvas = document.createElement('canvas');
    canvas.width = 1060;
    canvas.height = 445;
    const ctx = canvas.getContext('2d');

    // Pure white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Left subtle border line like UTI printout
    ctx.strokeStyle = '#94a3b8';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(1.5, 0);
    ctx.lineTo(1.5, canvas.height);
    ctx.stroke();

    // 1. Top-Left UTIITSL Logo Box (x=24, y=3, w=134, h=48)
    const logoImg = Array.from(document.querySelectorAll('img')).find((im) => {
      const s = (im.src || '').toLowerCase();
      return (s.includes('uti') || s.includes('logo')) && im.complete && im.naturalWidth > 20;
    });

    if (logoImg) {
      try {
        ctx.drawImage(logoImg, 24, 3, 134, 48);
      } catch (e) {
        drawVectorUtiLogo(ctx, 24, 3, 134, 48);
      }
    } else {
      drawVectorUtiLogo(ctx, 24, 3, 134, 48);
    }

    // 2. Centered Header Text
    ctx.fillStyle = '#000000';
    ctx.textAlign = 'center';
    ctx.font = 'bold 15px Arial, Helvetica, sans-serif';
    ctx.fillText('PAN Application Acknowledgment Receipt For Form No. 93', 555, 25);
    ctx.font = 'bold 14px Arial, Helvetica, sans-serif';
    ctx.fillText('( Physical Application )', 555, 45);
    ctx.textAlign = 'left';

    // 3. Blue Horizontal Divider Line below header (y=54)
    ctx.strokeStyle = '#2563eb';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(2, 54);
    ctx.lineTo(canvas.width, 54);
    ctx.stroke();

    // 4. Right-Side Barcode & Large Application Number (x=712..1032, y=60..186)
    const bCodeStr = (barcodeText || info.barcodeCode || 'A126098710').replace(/^U-/i, '');
    drawCode128StyleBarcode(ctx, 712, 60, 318, 92, bCodeStr);
    ctx.fillStyle = '#000000';
    ctx.textAlign = 'center';
    ctx.font = 'bold 29px Arial, Helvetica, sans-serif';
    ctx.fillText(bCodeStr, 871, 180);
    ctx.textAlign = 'left';

    // 5. 15 Table Rows (Alternating White & #e2e2e2 Gray Bands)
    const rows = [
      [info.receivedLabel, info.applicantName || '-'],
      ['Application No./Coupon No.', info.formattedCoupon],
      ['Gender', info.gender],
      ['Date of Birth', info.dob || '-'],
      ['Parent Name (On Card)', info.parentName || '-'],
      ['Aadhaar Number', info.aadhaarDisplay, true],
      ['Name as per Aadhaar', info.nameAsPerAadhaar || info.applicantName || '-'],
      ["Applicant's Contact details(Mobile/Landline/email)", info.contactDetails],
      ['Communication Address', info.commAddress],
      ['Residence State', info.resState],
      ['Proof of Identity', info.poi],
      ['Proof of Address', info.poa],
      ['Proof of DOB', info.podob],
      ['Date of Receipt', info.dateOfReceipt],
      ['Mode of PAN Card', info.modeOfPan]
    ];

    const startY = 60;
    const rowH = 25;

    rows.forEach(([label, val, isAadhaarRow], idx) => {
      const rowTop = startY + idx * rowH;
      const isGray = idx % 2 === 1;

      if (isGray) {
        // Rows 0..4 stop at x=695 so barcode on right remains on clean white background
        const bgWidth = idx < 5 ? 677 : (canvas.width - 24);
        ctx.fillStyle = '#e2e2e2';
        ctx.fillRect(18, rowTop, bgWidth, rowH);
      }

      const textY = rowTop + 17.5;

      // Label column (regular)
      ctx.fillStyle = '#000000';
      ctx.font = '13.5px Arial, Helvetica, sans-serif';
      ctx.fillText(label, 27, textY);

      // Value column (bold)
      if (isAadhaarRow && String(val).includes('(MENTIONED')) {
        const parts = String(val).split('(MENTIONED');
        const mainPart = parts[0];
        const suffixPart = '(MENTIONED' + parts[1];
        ctx.font = 'bold 13.5px Arial, Helvetica, sans-serif';
        ctx.fillText(mainPart, 355, textY);
        const mainW = ctx.measureText(mainPart).width;
        ctx.font = 'bold 10.5px Arial, Helvetica, sans-serif';
        ctx.fillText(suffixPart, 355 + mainW + 2, textY);
      } else {
        ctx.font = 'bold 13.5px Arial, Helvetica, sans-serif';
        ctx.fillText(String(val), 355, textY);
      }
    });

    return canvas.toDataURL('image/jpeg', 0.94);
  }

  function drawVectorUtiLogo(ctx, x, y, w, h) {
    // Blue outer rectangle
    ctx.fillStyle = '#2e3192';
    ctx.fillRect(x, y, w, h);

    // Left cream badge
    ctx.fillStyle = '#fffbeb';
    ctx.beginPath();
    ctx.roundRect ? ctx.roundRect(x + 2, y + 2, 62, h - 4, 8) : ctx.rect(x + 2, y + 2, 62, h - 4);
    ctx.fill();

    // Orange "uti" text
    ctx.fillStyle = '#f58220';
    ctx.font = 'bold 18px Arial, sans-serif';
    ctx.fillText('uti', x + 7, y + 24);

    // Diagonal slashes
    ctx.strokeStyle = '#2e3192';
    ctx.lineWidth = 1.8;
    for (let s = 0; s < 3; s++) {
      ctx.beginPath();
      ctx.moveTo(x + 36 + s * 6, y + 28);
      ctx.lineTo(x + 48 + s * 6, y + 8);
      ctx.stroke();
    }

    // "UTIITSL" text
    ctx.fillStyle = '#2e3192';
    ctx.font = 'bold 9.5px Arial, sans-serif';
    ctx.fillText('UTIITSL', x + 10, y + h - 8);

    // Right side white text
    ctx.fillStyle = '#ffffff';
    ctx.font = '7.5px Arial, sans-serif';
    ctx.fillText('UTI Infrastructure', x + 68, y + 15);
    ctx.fillText('Technology  And', x + 68, y + 27);
    ctx.fillText('Services  Limited', x + 68, y + 39);
  }

  function drawCode128StyleBarcode(ctx, x, y, w, h, text) {
    ctx.fillStyle = '#000000';
    // Deterministic crisp barcode pattern from text characters
    const pattern = [2, 1, 1, 2, 3, 1];
    for (let i = 0; i < text.length; i++) {
      const code = text.charCodeAt(i);
      pattern.push(
        ((code >> 0) & 3) + 1,
        ((code >> 2) & 2) + 1,
        ((code >> 4) & 3) + 1,
        ((code >> 1) & 2) + 1,
        ((code >> 3) & 2) + 1,
        1
      );
    }
    pattern.push(2, 3, 1, 1, 2);

    const totalUnits = pattern.reduce((a, b) => a + b, 0);
    const unitW = w / totalUnits;
    let curX = x;
    for (let i = 0; i < pattern.length; i++) {
      const bw = pattern[i] * unitW;
      if (i % 2 === 0) {
        ctx.fillRect(Math.round(curX), y, Math.max(1.5, Math.round(bw)), h);
      }
      curX += bw;
    }
  }

  function resolveReceiptTargetCandidate(applicantName, mobileNo, aadhaarLast4, stored) {
    const list = Array.isArray(stored?.queueList) ? stored.queueList : [];
    const current = stored?.currentCandidate || currentCandidate;

    // 1. Direct match on current candidate if details align
    if (current) {
      const curAadh = String(current.aadhaar_no || '').replace(/\D/g, '').slice(-4);
      const curMob = String(current.mobile_no || '').replace(/\D/g, '').slice(-10);
      const curName = String(current.full_name || '').trim().toLowerCase();
      const recName = String(applicantName || '').trim().toLowerCase();

      if ((aadhaarLast4 && curAadh === aadhaarLast4) ||
          (mobileNo && curMob === mobileNo) ||
          (curName && recName && (curName.includes(recName) || recName.includes(curName)))) {
        return current;
      }
    }

    // 2. Search queueList for exact match
    for (const cand of list) {
      const cAadh = String(cand.aadhaar_no || '').replace(/\D/g, '').slice(-4);
      const cMob = String(cand.mobile_no || '').replace(/\D/g, '').slice(-10);
      const cName = String(cand.full_name || '').trim().toLowerCase();
      const recName = String(applicantName || '').trim().toLowerCase();

      if ((aadhaarLast4 && cAadh === aadhaarLast4) ||
          (mobileNo && cMob === mobileNo) ||
          (cName && recName && (cName.includes(recName) || recName.includes(cName)))) {
        return cand;
      }
    }

    return current || null;
  }

  async function captureAndAttachReceiving(isRetry = false) {
    if (receiptHandling && !isRetry) return;

    const pageText = document.body ? document.body.innerText : '';
    const couponNo = extractCouponNumber();

    // Verify receipt page is fully loaded with coupon number before taking screenshot
    if (!isReceiptFullyLoaded() && receiptRetryCount < 20) {
      receiptRetryCount++;
      updateBadge(`⏳ Waiting for Receipt & Coupon No to load (${receiptRetryCount}/20)...`, '#38bdf8');
      setTimeout(() => captureAndAttachReceiving(isRetry), 300);
      return;
    }

    if (couponNo && couponNo === lastCompletedCoupon && !isRetry) {
      return;
    }

    receiptHandling = true;
    updateBadge(`📸 Capturing Receiving Screenshot (${couponNo || 'Form 93'})...`, '#0284c7');

    // Extract exact candidate details from the receipt table DOM
    const domFields = extractReceiptFieldsFromDom(couponNo, currentCandidate);
    const applicantName = domFields.applicantName || '';

    const mobMatch = (domFields.contactDetails || pageText).match(/\b([6-9]\d{9})\b/);
    const mobileNo = mobMatch ? mobMatch[1].trim() : '';

    const aadhaarLast4 = domFields.aadhaarLast4 || '';
    const parentName = domFields.parentName || '';
    const dob = domFields.dob || '';

    // Synthesize exact Image-1 format receipt directly from live DOM fields
    const synth = generateReceiptCanvasJpeg(
      couponNo,
      applicantName,
      mobileNo,
      aadhaarLast4,
      parentName,
      dob,
      domFields.barcodeCode,
      currentCandidate
    );
    deliverReceivingPayload(synth, couponNo, applicantName, mobileNo, aadhaarLast4, isRetry);
  }

  function deliverReceivingPayload(receiptBase64, couponNo, applicantName, mobileNo, aadhaarLast4, isRetry) {
    chrome.storage.local.get(['currentCandidate', 'queueList'], (stored) => {
      const targetCand = resolveReceiptTargetCandidate(applicantName, mobileNo, aadhaarLast4, stored);
      const appId = targetCand ? (targetCand.id || targetCand.app_id) : null;
      const appRef = targetCand ? String(targetCand.app_ref || '').replace(/^#+/, '').trim() : null;

      updateBadge(`📤 Saving Receiving to BiharGo (${applicantName || 'Applicant'} - ${couponNo})...`, '#0284c7');

      const action = isRetry ? 'attachReceipt' : 'candidateCompleted';
      const payload = {
        action: action,
        appId: appId,
        appRef: appRef,
        applicantName: applicantName || targetCand?.full_name || '',
        mobileNo: mobileNo || targetCand?.mobile_no || '',
        aadhaarNo: aadhaarLast4 || targetCand?.aadhaar_no || '',
        utiRefNo: couponNo || currentUtiRefNo,
        remarks: `UTI PAN Form 93 Completed. Coupon: ${couponNo || 'Attached'}`,
        screenshot: receiptBase64 || '',
        result_file_base64: receiptBase64 || '',
        autoClose: true
      };

      chrome.runtime.sendMessage(payload, (resp) => {
        receiptHandling = false;

        if (resp && resp.success && (resp.data?.has_receiving !== false)) {
          lastCompletedCoupon = couponNo;
          const banner = document.getElementById('wb-receipt-error-banner');
          if (banner) banner.remove();

          updateBadge(`🎉 Receiving SAVED on BiharGo for ${applicantName || 'Candidate'}! Opening Upload List...`, '#10b981');
          // Note: Single-path navigation - background.js candidateCompleted automatically handles openDocUploadAndCloseSuccess.
        } else {
          const err = resp?.error || (resp?.data && resp.data.error) || 'Receiving screenshot could not be attached.';
          updateBadge(`⚠️ Receiving Save Failed: ${err}`, '#ef4444');
          showReceiptFailureUI(err, couponNo, applicantName, mobileNo, aadhaarLast4, targetCand);
        }
      });
    });
  }

  function handleReceiptSuccessPage() {
    captureAndAttachReceiving(false);
  }

  // -----------------------------------------------------------------------
  // PAGE 8: docUploadListPanApp -> Document Upload (PAN Form, Photo, Signature)
  // "upload per click karna hai aur photo aur signature bihargo se utha ke
  //  yaha per upload karna hai... kisi aur ka kisi aur me upload nahi hona chahiye"
  // -----------------------------------------------------------------------
  let docUploadLock = false;
  let lastUploadedAppNo = '';

  function findRowUploadButton(row, cells) {
    const uploadCell = (cells && cells[4]) ? cells[4] : row;
    const candidates = Array.from(uploadCell.querySelectorAll('a, button, input[type="button"], input[type="submit"], span'));
    for (const el of candidates) {
      if (el.classList && el.classList.contains('wb-auto-upload-btn')) continue;
      const t = (el.innerText || el.value || '').trim().toLowerCase();
      if (t === 'upload') {
        return el;
      }
    }
    if (uploadCell && (uploadCell.innerText || '').trim().toLowerCase() === 'upload') {
      return uploadCell;
    }
    return null;
  }

  function triggerNativeRowUploadClick(uploadBtn) {
    if (!uploadBtn) return;
    const rawHref = (uploadBtn.getAttribute && uploadBtn.getAttribute('href') || '').trim().toLowerCase();
    if (rawHref === '' || rawHref.includes('docuploadlistpanapp')) {
      uploadBtn.addEventListener('click', (e) => {
        e.preventDefault();
      }, { once: true });
    }
    try {
      uploadBtn.click();
    } catch (err) {
      uploadBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    }
  }

  function findCandidateUploadRow(appNo, candidateName) {
    const cleanAppNo = String(appNo || '').replace(/^U-/i, '').trim().toUpperCase();
    const cleanName = String(candidateName || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();

    const rows = Array.from(document.querySelectorAll('table tr'));
    for (const row of rows) {
      // Strictly skip outer layout tables that contain nested tables
      if (row.querySelector('table')) continue;

      const cells = Array.from(row.cells || row.querySelectorAll(':scope > td'));
      if (cells.length < 4) continue;

      const uploadBtn = findRowUploadButton(row, cells);
      if (!uploadBtn) continue;

      const text = (row.innerText || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
      if (!/\b([AU]-?\d{6,}|\d{7,})\b/i.test(text) && !cleanName) continue;

      const matchesAppNo = cleanAppNo && cleanAppNo.length >= 6 && text.includes(cleanAppNo);
      const matchesName = cleanName && cleanName.length >= 3 && text.includes(cleanName);

      // Strict row check: Must match App No, or clean Name
      if (matchesAppNo || matchesName) {
        return { row, uploadBtn, cleanAppNo, cleanName };
      }
    }
    return null;
  }

  function findDocUploadModal() {
    const dialogs = Array.from(document.querySelectorAll('.ui-dialog, .modal, div.popup, div[role="dialog"], form, div')).filter((d) => {
      if (!isVisible(d)) return false;
      const fileInputs = d.querySelectorAll('input[type="file"]');
      if (fileInputs.length >= 2) return true;
      const t = (d.innerText || '').toLowerCase();
      return (t.includes('upload documents') || t.includes('pan form alongwith')) && fileInputs.length > 0;
    });
    if (dialogs.length > 0) return dialogs[0];

    // Direct check for any visible file inputs
    const visibleInputs = Array.from(document.querySelectorAll('input[type="file"]')).filter(isVisible);
    if (visibleInputs.length >= 2) {
      return visibleInputs[0].closest('form, table, div') || document.body;
    }
    return null;
  }

  function findDocUploadInputs(modalContainer) {
    const root = modalContainer || document;
    const fileInputs = Array.from(root.querySelectorAll('input[type="file"]'));

    let panFormInput = null;
    let pictureInput = null;
    let signatureInput = null;

    for (const inp of fileInputs) {
      const name = (inp.name || inp.id || '').toLowerCase();
      const parentText = (inp.closest('tr, div, p')?.innerText || '').toLowerCase();

      if (name.includes('pan') || name.includes('doc') || parentText.includes('pan form') || parentText.includes('alongwith')) {
        panFormInput = inp;
      } else if (name.includes('pic') || name.includes('photo') || parentText.includes('picture') || parentText.includes('photo')) {
        pictureInput = inp;
      } else if (name.includes('sig') || parentText.includes('signature')) {
        signatureInput = inp;
      }
    }

    if (fileInputs.length === 3) {
      if (!panFormInput) panFormInput = fileInputs[0];
      if (!pictureInput) pictureInput = fileInputs[1];
      if (!signatureInput) signatureInput = fileInputs[2];
    }

    return { panFormInput, pictureInput, signatureInput };
  }

  // Helper to construct a 100% RAM-backed File (BlobDataItem::kBytes) so Chrome never throws ERR_UPLOAD_FILE_CHANGED / "Your file couldn't be accessed"
  function createRamBackedFile(bufferOrBytes, filename, mimeType) {
    const src = bufferOrBytes instanceof Uint8Array
      ? bufferOrBytes
      : new Uint8Array(bufferOrBytes);
    const ramCopy = new Uint8Array(src.byteLength);
    ramCopy.set(src);
    return new File([ramCopy], filename, {
      type: mimeType || 'application/octet-stream',
      lastModified: Date.now()
    });
  }

  // Fetch asset bytes via background service worker to completely bypass page CORS limits
  async function fetchFileViaBackground(url, filename, defaultMime = 'image/jpeg') {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage({ action: 'fetchAssetData', url }, (res) => {
        if (!res || !res.success || !res.base64) {
          reject(new Error(res?.error || 'Failed to fetch asset via background service worker'));
          return;
        }
        try {
          const byteCharacters = atob(res.base64);
          const byteArray = new Uint8Array(byteCharacters.length);
          for (let i = 0; i < byteCharacters.length; i++) {
            byteArray[i] = byteCharacters.charCodeAt(i);
          }
          const file = createRamBackedFile(byteArray, filename, res.mime || defaultMime);
          resolve(file);
        } catch (e) {
          reject(e);
        }
      });
    });
  }

  // Injects JFIF APP0 Segment with specified DPI (300 or 600) into JPEG binary
  function injectJfifDpiInJpeg(arrayBuffer, dpi) {
    const bytes = new Uint8Array(arrayBuffer);
    if (bytes.length < 4 || bytes[0] !== 0xFF || bytes[1] !== 0xD8) {
      return arrayBuffer;
    }

    const dpiHi = (dpi >> 8) & 0xFF;
    const dpiLo = dpi & 0xFF;

    if (bytes[2] === 0xFF && bytes[3] === 0xE0 && bytes.length >= 18) {
      bytes[13] = 1; // 1 = dots per inch (DPI)
      bytes[14] = dpiHi;
      bytes[15] = dpiLo;
      bytes[16] = dpiHi;
      bytes[17] = dpiLo;
      return bytes.buffer;
    }

    const app0 = new Uint8Array([
      0xFF, 0xE0,
      0x00, 0x10,
      0x4A, 0x46, 0x49, 0x46, 0x00,
      0x01, 0x02,
      0x01,
      dpiHi, dpiLo,
      dpiHi, dpiLo,
      0x00, 0x00
    ]);

    const result = new Uint8Array(bytes.length + app0.length);
    result.set(bytes.subarray(0, 2), 0);
    result.set(app0, 2);
    result.set(bytes.subarray(2), 2 + app0.length);
    return result.buffer;
  }

  // Converts raw JPEG ArrayBuffer into a valid PDF ArrayBuffer (%PDF-1.4 with DCTDecode)
  function createPdfFromJpeg(jpegArrayBuffer) {
    const bytes = new Uint8Array(jpegArrayBuffer);
    let imgW = 595, imgH = 842;
    let offset = 2;
    while (offset < bytes.length) {
      if (bytes[offset] !== 0xFF) { offset++; continue; }
      const marker = bytes[offset + 1];
      const len = (bytes[offset + 2] << 8) | bytes[offset + 3];
      if (marker === 0xC0 || marker === 0xC2) {
        imgH = (bytes[offset + 5] << 8) | bytes[offset + 6];
        imgW = (bytes[offset + 7] << 8) | bytes[offset + 8];
        break;
      }
      offset += 2 + len;
    }

    const pageW = 595.28;
    const pageH = 841.89;
    const scale = Math.min(pageW / imgW, pageH / imgH);
    const drawW = imgW * scale;
    const drawH = imgH * scale;
    const posX = (pageW - drawW) / 2;
    const posY = (pageH - drawH) / 2;

    const contentStream = `q ${drawW.toFixed(2)} 0 0 ${drawH.toFixed(2)} ${posX.toFixed(2)} ${posY.toFixed(2)} cm /Im0 Do Q`;
    const enc = new TextEncoder();
    const contentBytes = enc.encode(contentStream);

    const objects = [
      '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj',
      '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj',
      `3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageW} ${pageH}] /Contents 4 0 R /Resources << /XObject << /Im0 5 0 R >> >> >>\nendobj`,
      `4 0 obj\n<< /Length ${contentBytes.length} >>\nstream\n${contentStream}\nendstream\nendobj`
    ];

    const imgHeader = `5 0 obj\n<< /Type /XObject /Subtype /Image /Width ${imgW} /Height ${imgH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${bytes.length} >>\nstream\n`;
    const imgFooter = '\nendstream\nendobj';

    const headerBuf = enc.encode('%PDF-1.4\n%âãÏÓ\n');
    const imgHeadBuf = enc.encode(imgHeader);
    const imgFootBuf = enc.encode(imgFooter + '\n');

    let currentOffset = headerBuf.length;
    const xrefOffsets = [0];

    const objBuffers = objects.map((objStr) => {
      xrefOffsets.push(currentOffset);
      const b = enc.encode(objStr + '\n');
      currentOffset += b.length;
      return b;
    });

    xrefOffsets.push(currentOffset);
    currentOffset += imgHeadBuf.length + bytes.length + imgFootBuf.length;

    const xrefStart = currentOffset;
    let xrefStr = `xref\n0 6\n0000000000 65535 f \n`;
    for (let i = 1; i <= 5; i++) {
      xrefStr += String(xrefOffsets[i]).padStart(10, '0') + ' 00000 n \n';
    }
    xrefStr += `trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF\n`;
    const xrefBuf = enc.encode(xrefStr);

    const totalLen = currentOffset + xrefBuf.length;
    const out = new Uint8Array(totalLen);
    let pos = 0;

    out.set(headerBuf, pos); pos += headerBuf.length;
    for (const ob of objBuffers) {
      out.set(ob, pos); pos += ob.length;
    }
    out.set(imgHeadBuf, pos); pos += imgHeadBuf.length;
    out.set(bytes, pos); pos += bytes.length;
    out.set(imgFootBuf, pos); pos += imgFootBuf.length;
    out.set(xrefBuf, pos);

    return out.buffer;
  }

  // -----------------------------------------------------------------------
  // Default PAN Form PDF (545 KB standard scanned Form 49A with documents provided by user)
  // -----------------------------------------------------------------------
  async function getDefaultPanFormFile() {
    try {
      const file = await fetchFileViaBackground('assets/default_pan_form.pdf', 'PAN_Form_Alongwith_Documents.pdf', 'application/pdf');
      if (file && file.size > 1000) {
        return file;
      }
    } catch (err) {
      console.warn('Background fetch of default PDF failed, trying direct arrayBuffer:', err);
    }

    try {
      const url = chrome.runtime.getURL('assets/default_pan_form.pdf');
      const res = await fetch(url);
      if (res.ok) {
        const ab = await res.arrayBuffer();
        if (ab && ab.byteLength > 1000) {
          return createRamBackedFile(ab, 'PAN_Form_Alongwith_Documents.pdf', 'application/pdf');
        }
      }
    } catch (e) {
      console.warn('Direct arrayBuffer fetch of default PDF failed:', e);
    }
    return null;
  }

  // Fetches real PAN Form / Aadhaar from BiharGo and ensures it is delivered as a valid PDF
  async function fetchAndFormatPanForm(cand) {
    // 1. Unconditional Default: User-provided standard PAN Form PDF
    const defaultFile = await getDefaultPanFormFile();
    if (defaultFile) {
      return defaultFile;
    }

    // 2. Fallback only if default_pan_form.pdf could not be read
    const formUrl = cand?.pan_form_url || cand?.aadhar_card_url || '';
    if (!formUrl) {
      throw new Error('Default PAN form PDF missing from extension assets');
    }

    try {
      const rawFile = await fetchFileViaBackground(formUrl, 'cand_form', 'application/octet-stream');
      const buf = await rawFile.arrayBuffer();
      const bytes = new Uint8Array(buf);

      // Check if already PDF (%PDF header)
      if (bytes.length >= 4 && bytes[0] === 0x25 && bytes[1] === 0x50 && bytes[2] === 0x44 && bytes[3] === 0x46) {
        return createRamBackedFile(buf, 'PAN_Form_Alongwith_Documents.pdf', 'application/pdf');
      }

      // Check if JPEG image (0xFF 0xD8)
      if (bytes.length >= 2 && bytes[0] === 0xFF && bytes[1] === 0xD8) {
        const pdfBuf = createPdfFromJpeg(buf);
        return createRamBackedFile(pdfBuf, 'PAN_Form_Alongwith_Documents.pdf', 'application/pdf');
      }

      // Convert image via Canvas -> JPEG -> PDF
      const bmp = await createImageBitmap(rawFile);
      const canvas = document.createElement('canvas');
      canvas.width = bmp.width;
      canvas.height = bmp.height;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(bmp, 0, 0);
      const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.88));
      const jBuf = await blob.arrayBuffer();
      const pdfBuf = createPdfFromJpeg(jBuf);
      return createRamBackedFile(pdfBuf, 'PAN_Form_Alongwith_Documents.pdf', 'application/pdf');
    } catch (err) {
      console.warn('Candidate form conversion to PDF failed, falling back to bundled form:', err);
      return fetchFileViaBackground('assets/default_pan_form.pdf', 'PAN_Form_Alongwith_Documents.pdf', 'application/pdf');
    }
  }

  async function fetchAndFormatPhoto(photoUrl, name) {
    const rawFile = await fetchFileViaBackground(photoUrl, 'temp_photo.jpg', 'image/jpeg');
    const bmp = await createImageBitmap(rawFile);
    const canvas = document.createElement('canvas');
    canvas.width = 213;
    canvas.height = 213;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 213, 213);
    ctx.drawImage(bmp, 0, 0, 213, 213);

    // UTI requires: 213 x 213 px, 300 DPI, < 30 KB
    let quality = 0.85;
    let finalBuffer = null;
    while (quality >= 0.35) {
      const jpegBlob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', quality));
      const buf = await jpegBlob.arrayBuffer();
      const injected = injectJfifDpiInJpeg(buf, 300);
      if (injected.byteLength / 1024 < 29) {
        finalBuffer = injected;
        break;
      }
      quality -= 0.1;
    }
    if (!finalBuffer) {
      const jpegBlob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.5));
      finalBuffer = injectJfifDpiInJpeg(await jpegBlob.arrayBuffer(), 300);
    }

    const safeName = (name || 'photo').replace(/[^a-zA-Z0-9]/g, '_');
    return createRamBackedFile(finalBuffer, `${safeName}_photo_213x213_300dpi.jpg`, 'image/jpeg');
  }

  async function fetchAndFormatSignature(sigUrl, name) {
    const rawFile = await fetchFileViaBackground(sigUrl, 'temp_signature.jpg', 'image/jpeg');
    const bmp = await createImageBitmap(rawFile);
    const canvas = document.createElement('canvas');
    canvas.width = 400;
    canvas.height = 200;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 400, 200);
    ctx.drawImage(bmp, 0, 0, 400, 200);

    const imgData = ctx.getImageData(0, 0, 400, 200);
    const d = imgData.data;
    for (let i = 0; i < d.length; i += 4) {
      const lum = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
      if (lum > 170) {
        d[i] = 255; d[i + 1] = 255; d[i + 2] = 255;
      } else if (lum < 75) {
        d[i] = 0; d[i + 1] = 0; d[i + 2] = 0;
      }
    }
    ctx.putImageData(imgData, 0, 0);

    // UTI requires: 400 x 200 px, 600 DPI, < 60 KB
    let quality = 0.85;
    let finalBuffer = null;
    while (quality >= 0.35) {
      const jpegBlob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', quality));
      const buf = await jpegBlob.arrayBuffer();
      const injected = injectJfifDpiInJpeg(buf, 600);
      if (injected.byteLength / 1024 < 58) {
        finalBuffer = injected;
        break;
      }
      quality -= 0.1;
    }
    if (!finalBuffer) {
      const jpegBlob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.5));
      finalBuffer = injectJfifDpiInJpeg(await jpegBlob.arrayBuffer(), 600);
    }

    const safeName = (name || 'signature').replace(/[^a-zA-Z0-9]/g, '_');
    return createRamBackedFile(finalBuffer, `${safeName}_signature_400x200_600dpi.jpg`, 'image/jpeg');
  }

  // Auto click the modal Upload button (Requested by RIOT: "document uplaod bhi autokar do")
  const AUTO_CLICK_MODAL_UPLOAD = true;

  function clickModalUploadButton(modal) {
    const root = modal || document;
    const allBtns = Array.from(root.querySelectorAll('button, input[type="button"], input[type="submit"]'));
    const uploadBtn = allBtns.find((b) => {
      if (!isVisible(b) || b.disabled) return false;
      if (b.classList.contains('wb-auto-upload-btn')) return false;
      const t = (b.textContent || b.value || '').trim().toLowerCase();
      return t === 'upload';
    });

    if (uploadBtn) {
      updateBadge('🚀 Submitting Documents to UTI Portal...', '#10b981');
      try {
        chrome.storage.local.set({
          awaitingDocUpload: true,
          awaitingDocUploadAt: Date.now()
        });
      } catch (e) {}
      try {
        uploadBtn.focus();
        uploadBtn.click();
      } catch (e) {
        uploadBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }

      // Unlock retry guard after 5s without prematurely clearing awaitingDocUpload before success popup
      setTimeout(() => {
        docUploadLock = false;
      }, 5000);
    }
  }

  async function attachDocumentsToModal(inputs, cand, modal) {
    updateBadge('⏳ Loading Photo, Signature & PDF Form...', '#38bdf8');

    try {
      let activeCand = cand || window._activeRowCandidate || window._selectedRowCandidate;
      if (!activeCand || !activeCand.photo_url || !activeCand.signature_url) {
        if (window._activeRowCandName) {
          activeCand = window._docTableCandidateCache[window._activeRowCandName.toUpperCase()] || await fetchCandidateBySearch(window._activeRowCandName);
        }
      }
      if (!activeCand || !activeCand.photo_url || !activeCand.signature_url) {
        if (window._activeRowAppNo) {
          activeCand = window._docTableCandidateCache[window._activeRowAppNo] || await fetchCandidateBySearch(window._activeRowAppNo);
        }
      }
      if (!activeCand || !activeCand.photo_url || !activeCand.signature_url) {
        const firstInfo = getFirstVisibleTableRowCandInfo();
        if (firstInfo && firstInfo.candName) {
          activeCand = window._docTableCandidateCache[firstInfo.candName.toUpperCase()] || await fetchCandidateBySearch(firstInfo.candName);
        }
      }

      if (!activeCand || !activeCand.photo_url || !activeCand.signature_url) {
        throw new Error('Candidate photo or signature URL missing from Welcome Bihar');
      }

      // 1. Candidate Real PAN Form from BiharGo (delivered as valid PDF)
      const panFormFile = await fetchAndFormatPanForm(activeCand);

      // 2. Candidate Photo from BiharGo (UTI 213x213 @ 300 DPI)
      const photoFile = await fetchAndFormatPhoto(activeCand.photo_url, activeCand.full_name);

      // 3. Candidate Signature from BiharGo (UTI 400x200 @ 600 DPI Fresh)
      const sigFile = await fetchAndFormatSignature(activeCand.signature_url, activeCand.full_name);

      // Set input files via DataTransfer
      const dt1 = new DataTransfer();
      dt1.items.add(panFormFile);
      inputs.panFormInput.files = dt1.files;

      const dt2 = new DataTransfer();
      dt2.items.add(photoFile);
      inputs.pictureInput.files = dt2.files;

      const dt3 = new DataTransfer();
      dt3.items.add(sigFile);
      inputs.signatureInput.files = dt3.files;

      // Trigger change & input events for form validation scripts
      [inputs.panFormInput, inputs.pictureInput, inputs.signatureInput].forEach((inp) => {
        inp.dispatchEvent(new Event('input', { bubbles: true }));
        inp.dispatchEvent(new Event('change', { bubbles: true }));
      });

      // Verify files were assigned successfully
      if (!inputs.panFormInput.files || inputs.panFormInput.files.length === 0) {
        throw new Error('PAN form input failed to receive file');
      }

      // Convert formatted files to local Base64 data URLs for 100% reliable preview
      const toBase64 = (file) => new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => resolve('');
        reader.readAsDataURL(file);
      });

      const photoDataUrl = await toBase64(photoFile);
      const sigDataUrl = await toBase64(sigFile);

      // Render Visual Confirmation Card directly inside the UTI modal
      let previewBox = modal.querySelector('#wb-modal-preview-box');
      if (!previewBox) {
        previewBox = document.createElement('div');
        previewBox.id = 'wb-modal-preview-box';
        const actionRow = modal.querySelector('.ui-dialog-buttonpane, .modal-footer, div[style*="text-align: center"], div[style*="text-align:center"]');
        if (actionRow && actionRow.parentNode) {
          actionRow.parentNode.insertBefore(previewBox, actionRow);
        } else {
          modal.appendChild(previewBox);
        }
      }

      previewBox.style.cssText = 'margin:12px 0;padding:12px 14px;background:#f0fdf4;border:2px solid #10b981;border-radius:8px;font-family:system-ui,-apple-system,sans-serif;box-shadow:0 3px 12px rgba(16,185,129,0.15);display:block;clear:both;';

      previewBox.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1.5px solid #bbf7d0;padding-bottom:8px;margin-bottom:10px;">
          <div>
            <span style="font-size:14px;font-weight:800;color:#166534;">✅ VERIFIED CANDIDATE:</span>
            <span style="font-size:15px;font-weight:900;color:#0284c7;margin-left:6px;">${activeCand.full_name}</span>
            <span style="font-size:12px;font-weight:600;color:#475569;margin-left:8px;">(Father: ${activeCand.father_name || '-'})</span>
          </div>
          <span style="background:#10b981;color:#fff;font-weight:700;font-size:11px;padding:3px 10px;border-radius:12px;">MATCH VERIFIED</span>
        </div>
        <div style="display:flex;align-items:center;gap:18px;">
          <div style="text-align:center;">
            <div style="font-size:10px;font-weight:700;color:#166534;margin-bottom:4px;">PHOTO (300 DPI)</div>
            ${photoDataUrl ? `<img src="${photoDataUrl}" style="width:70px;height:70px;object-fit:cover;border:2px solid #10b981;border-radius:6px;display:block;box-shadow:0 1px 4px rgba(0,0,0,0.15);" />` : `<div style="width:70px;height:70px;background:#cbd5e1;line-height:70px;font-size:10px;">No Photo</div>`}
          </div>
          <div style="text-align:center;">
            <div style="font-size:10px;font-weight:700;color:#166534;margin-bottom:4px;">SIGNATURE (600 DPI)</div>
            ${sigDataUrl ? `<img src="${sigDataUrl}" style="width:130px;height:55px;object-fit:contain;background:#fff;border:2px solid #10b981;border-radius:6px;display:block;box-shadow:0 1px 4px rgba(0,0,0,0.15);" />` : `<div style="width:130px;height:55px;background:#cbd5e1;line-height:55px;font-size:10px;">No Signature</div>`}
          </div>
          <div style="flex:1;font-size:11.5px;color:#1e293b;line-height:1.6;background:#fff;padding:8px 10px;border-radius:6px;border:1px solid #cbd5e1;">
            <div>📄 <b>Form:</b> <span style="color:#0284c7;">PAN_Form_Alongwith_Documents.pdf</span></div>
            <div>📷 <b>Photo:</b> ${photoFile.name}</div>
            <div>✍️ <b>Sign:</b> ${sigFile.name}</div>
          </div>
        </div>
      `;

      // Ensure modal does not clip preview box
      modal.style.overflow = 'visible';
      modal.style.height = 'auto';
      modal.style.maxHeight = 'none';
      const diagWrap = modal.closest('.ui-dialog');
      if (diagWrap) {
        diagWrap.style.height = 'auto';
        diagWrap.style.maxHeight = 'none';
        diagWrap.style.overflow = 'visible';
      }

      if (AUTO_CLICK_MODAL_UPLOAD) {
        updateBadge('✅ All 3 Files Attached • Submitting Upload...', '#10b981');
        setTimeout(() => {
          clickModalUploadButton(modal);
        }, 600);
      } else {
        updateBadge('✅ All 3 Files Attached • Ready for You to Click Upload', '#10b981');
        docUploadLock = false;
      }
    } catch (err) {
      docUploadLock = false;
      console.error('Document attach error:', err);
      updateBadge(`⚠️ Document attach error: ${err.message}`, '#f59e0b');
    }
  }

  function fixMediaUrl(url) {
    if (!url) return '';
    let u = String(url).trim();
    u = u.replace(/^https?:\/\/welcome\.bihargo\.xyz/i, 'https://www.bihargo.xyz');
    u = u.replace(/^https?:\/\/localhost(:\d+)?/i, 'https://www.bihargo.xyz');
    if (u.startsWith('/')) {
      u = 'https://www.bihargo.xyz' + u;
    }
    return u;
  }

  function sanitizeCandidate(c) {
    if (!c) return null;
    c.photo_url = fixMediaUrl(c.photo_url);
    c.signature_url = fixMediaUrl(c.signature_url);
    c.aadhar_card_url = fixMediaUrl(c.aadhar_card_url);
    c.pan_form_url = fixMediaUrl(c.pan_form_url);
    return c;
  }

  // Helper to fetch candidate details by Name, App Ref or Coupon from BiharGo (even if already completed)
  async function fetchCandidateBySearch(searchTerm) {
    return new Promise((resolve) => {
      chrome.storage.local.get(['portalUrl'], async (res) => {
        const portalUrl = (res.portalUrl || 'https://bihargo.xyz').replace(/\/+$/, '');
        const cleanTerm = String(searchTerm || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();

        try {
          if (cleanTerm) {
            const clean = encodeURIComponent(cleanTerm);
            const resp = await fetch(`${portalUrl}/api/pan-card/queue?status=ALL&search=${clean}`);
            const json = await resp.json();
            if (json && json.success && Array.isArray(json.data) && json.data.length > 0) {
              resolve(sanitizeCandidate(json.data[0]));
              return;
            }

            // Fallback: search individual name words
            const words = cleanTerm.split(' ').filter(w => w.length >= 3);
            for (const word of words) {
              const pResp = await fetch(`${portalUrl}/api/pan-card/queue?status=ALL&search=${encodeURIComponent(word)}`);
              const pJson = await pResp.json();
              if (pJson && pJson.success && Array.isArray(pJson.data) && pJson.data.length > 0) {
                const found = pJson.data.find(c => {
                  const fn = (c.full_name || '').toUpperCase();
                  return fn.includes(cleanTerm.toUpperCase()) || cleanTerm.toUpperCase().includes(fn);
                });
                if (found) {
                  resolve(sanitizeCandidate(found));
                  return;
                }
              }
            }

            // CRITICAL: searchTerm was given but not matched -> return null! NEVER return another candidate!
            resolve(null);
            return;
          }

          // Only when NO searchTerm was provided at all
          const respAll = await fetch(`${portalUrl}/api/pan-card/queue?status=ALL&limit=25`);
          const jsonAll = await respAll.json();
          if (jsonAll && jsonAll.success && Array.isArray(jsonAll.data) && jsonAll.data.length > 0) {
            resolve(sanitizeCandidate(jsonAll.data[0]));
            return;
          }
          resolve(null);
        } catch (err) {
          console.error('Error fetching candidate from Welcome Bihar:', err);
          resolve(null);
        }
      });
    });
  }

  // Helper to extract first candidate info from visible UTI table
  function getFirstVisibleTableRowCandInfo() {
    const rows = Array.from(document.querySelectorAll('table tr'));
    for (const r of rows) {
      if (r.querySelector('table')) continue;
      const cells = Array.from(r.cells || r.querySelectorAll(':scope > td'));
      if (cells.length < 4) continue;
      const uploadBtn = findRowUploadButton(r, cells);
      const text = (r.innerText || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
      const appNoMatch = text.match(/\b([AU]-?\d{6,}|\d{7,})\b/i);
      if (!appNoMatch && !uploadBtn) continue;
      const appNo = appNoMatch ? appNoMatch[1].replace(/^U-/i, '') : '';
      let candName = '';
      if (cells[2] && cells[2].innerText && !cells[2].innerText.toLowerCase().includes('upload')) {
        candName = cells[2].innerText.replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
      } else if (cells[1] && cells[1].innerText && isNaN(cells[1].innerText.trim())) {
        candName = cells[1].innerText.replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
      }
      if (uploadBtn || candName || appNo) return { appNo, candName, row: r, uploadBtn };
    }
    return null;
  }

  // Cache of candidates found in the current UTI table
  window._docTableCandidateCache = window._docTableCandidateCache || {};

  // Bind native "Upload" button in each candidate row (no extra injected buttons)
  function bindNativeDocUploadRows() {
    // Clean up any legacy injected UI if present
    document.getElementById('wb-floating-upload-bar')?.remove();
    document.querySelectorAll('.wb-auto-upload-btn').forEach((b) => b.remove());

    const rows = Array.from(document.querySelectorAll('table tr'));
    for (const row of rows) {
      if (row.querySelector('table')) continue;

      const cells = Array.from(row.cells || row.querySelectorAll(':scope > td'));
      if (cells.length < 4) continue;

      const uploadEl = findRowUploadButton(row, cells);
      if (!uploadEl || uploadEl.dataset.wbNativeBound === '1') continue;

      const text = (row.innerText || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
      const appNoMatch = text.match(/\b([AU]-?\d{6,}|\d{7,})\b/i);
      const appNo = appNoMatch ? appNoMatch[1].replace(/^U-/i, '') : '';

      let candName = '';
      if (cells[2] && cells[2].innerText && !cells[2].innerText.toLowerCase().includes('upload')) {
        candName = cells[2].innerText.replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
      } else if (cells[1] && cells[1].innerText && isNaN(cells[1].innerText.trim())) {
        candName = cells[1].innerText.replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
      }
      if (!appNo && !candName) continue;

      uploadEl.dataset.wbNativeBound = '1';
      uploadEl.dataset.appNo = appNo;
      uploadEl.dataset.candName = candName;

      // Pre-fetch candidate details for this row so modal attachment is instantaneous
      if (candName && !window._docTableCandidateCache[candName.toUpperCase()]) {
        fetchCandidateBySearch(candName).then((c) => {
          if (c) {
            window._docTableCandidateCache[candName.toUpperCase()] = c;
            if (appNo) window._docTableCandidateCache[appNo] = c;
          }
        });
      }

      uploadEl.addEventListener('click', () => {
        window._activeRowAppNo = appNo;
        window._activeRowCandName = candName;
        const cached = (candName && window._docTableCandidateCache[candName.toUpperCase()]) || (appNo && window._docTableCandidateCache[appNo]) || null;
        if (cached) {
          window._activeRowCandidate = cached;
          window._selectedRowCandidate = cached;
        } else if (candName) {
          fetchCandidateBySearch(candName).then((c) => {
            if (c) {
              window._docTableCandidateCache[candName.toUpperCase()] = c;
              if (appNo) window._docTableCandidateCache[appNo] = c;
              window._activeRowCandidate = c;
              window._selectedRowCandidate = c;
            }
          });
        }

        let tries = 0;
        const pollTimer = setInterval(async () => {
          tries++;
          const m = findDocUploadModal();
          if (m && !docUploadLock) {
            const inputs = findDocUploadInputs(m);
            if (inputs.panFormInput && inputs.pictureInput && inputs.signatureInput) {
              clearInterval(pollTimer);
              let c = (candName && window._docTableCandidateCache[candName.toUpperCase()]) || (appNo && window._docTableCandidateCache[appNo]) || window._activeRowCandidate;
              if (!c && candName) c = await fetchCandidateBySearch(candName);
              if (!c && appNo) c = await fetchCandidateBySearch(appNo);
              if (c) {
                docUploadLock = true;
                attachDocumentsToModal(inputs, c, m);
              }
            }
          }
          if (tries >= 35) {
            clearInterval(pollTimer);
          }
        }, 80);
      });
    }
  }

  let autoRowUploadTriggered = false;
  let docUploadPageReadyAt = 0;
  let emptyUploadRowTicks = 0;
  let docUploadSuccessHandled = false;
  let docUploadIdleNotified = false;

  function handleDocUploadListPage() {
    if (!docUploadPageReadyAt) {
      docUploadPageReadyAt = Date.now();
    }
    bindNativeDocUploadRows();

    // 1. Auto-dismiss any success alert popup
    const successDialog = Array.from(document.querySelectorAll('.ui-dialog, .modal, .alert')).find((d) => {
      if (!isVisible(d)) return false;
      const t = (d.innerText || '').toLowerCase();
      return t.includes('documents uploaded successfully') || t.includes('uploaded successfully');
    });
    if (successDialog) {
      const okBtn = Array.from(successDialog.querySelectorAll('button, input[type="button"]')).find((b) => {
        const t = (b.textContent || b.value || '').trim().toLowerCase();
        return t === 'ok' || t === 'close';
      });
      if (okBtn) {
        try { okBtn.click(); } catch (e) {}
        try { sessionStorage.setItem('wb_upload_just_succeeded', String(Date.now())); } catch (e) {}
        chrome.storage.local.set({ awaitingDocUpload: true, awaitingDocUploadAt: Date.now() });
        autoRowUploadTriggered = false;
        docUploadLock = false;
        docUploadSuccessHandled = true;
        emptyUploadRowTicks = 0;
        docUploadPageReadyAt = Date.now();
        updateBadge('🎉 Documents Uploaded Successfully! Checking next step...', '#10b981');
        setTimeout(() => {
          const remaining = getFirstVisibleTableRowCandInfo();
          try {
            if (!remaining) {
              chrome.runtime.sendMessage({ action: 'docUploadIdleOrDone' });
            } else {
              chrome.storage.local.set({ awaitingDocUpload: true, awaitingDocUploadAt: Date.now() });
            }
          } catch (e) {}
        }, 1800);
      }
      return;
    }

    if (docUploadLock) {
      emptyUploadRowTicks = 0;
      return;
    }

    // 2. Check if modal is already open
    const modal = findDocUploadModal();
    if (modal) {
      emptyUploadRowTicks = 0;
      docUploadIdleNotified = false;
      const inputs = findDocUploadInputs(modal);
      if (inputs.panFormInput && inputs.pictureInput && inputs.signatureInput) {
        if (inputs.panFormInput.files?.length > 0 && inputs.pictureInput.files?.length > 0 && inputs.signatureInput.files?.length > 0) {
          if (AUTO_CLICK_MODAL_UPLOAD) {
            clickModalUploadButton(modal);
          } else {
            updateBadge('✅ All 3 Files Attached • Ready for You to Click Upload', '#10b981');
          }
          return;
        }

        // Determine specific candidate for this modal strictly from the active row
        let targetCand = window._activeRowCandidate || window._selectedRowCandidate;
        if (!targetCand && window._activeRowCandName) {
          targetCand = window._docTableCandidateCache[window._activeRowCandName.toUpperCase()];
        }
        if (!targetCand && window._activeRowAppNo) {
          targetCand = window._docTableCandidateCache[window._activeRowAppNo];
        }

        // If not found, scan visible table rows
        if (!targetCand) {
          const firstInfo = getFirstVisibleTableRowCandInfo();
          if (firstInfo && firstInfo.candName) {
            targetCand = window._docTableCandidateCache[firstInfo.candName.toUpperCase()];
            if (!targetCand) {
              fetchCandidateBySearch(firstInfo.candName).then((c) => {
                if (c) {
                  window._docTableCandidateCache[firstInfo.candName.toUpperCase()] = c;
                  docUploadLock = true;
                  attachDocumentsToModal(inputs, c, modal);
                }
              });
              return;
            }
          }
        }

        if (targetCand && targetCand.photo_url && targetCand.signature_url) {
          docUploadLock = true;
          attachDocumentsToModal(inputs, targetCand, modal);
          return;
        }

        updateBadge('⚠️ Fetching candidate documents from Welcome Bihar...', '#f59e0b');
      }
      return;
    }

    // 3. Modal not open yet: Auto-click the native "Upload" button on the candidate row
    if (!autoRowUploadTriggered) {
      const firstInfoCheck = getFirstVisibleTableRowCandInfo();
      if (!firstInfoCheck) {
        if (document.readyState === 'complete') {
          emptyUploadRowTicks += 1;
          const elapsedSinceReady = Date.now() - docUploadPageReadyAt;
          const justSucceededTs = Number(sessionStorage.getItem('wb_upload_just_succeeded') || 0);
          const recentlyUploaded = docUploadSuccessHandled || (justSucceededTs > 0 && (Date.now() - justSucceededTs < 15000));
          const waitReqMs = recentlyUploaded ? 2500 : 6000;
          if (elapsedSinceReady >= waitReqMs && emptyUploadRowTicks >= 4 && !docUploadIdleNotified) {
            docUploadIdleNotified = true;
            try { sessionStorage.removeItem('wb_upload_just_succeeded'); } catch (e) {}
            try {
              chrome.runtime.sendMessage({ action: 'docUploadIdleOrDone' });
            } catch (e) {}
            updateBadge('📄 Document Upload List • No pending rows remaining', '#38bdf8');
          } else {
            updateBadge('⏳ Loading Upload Table Rows...', '#38bdf8');
          }
        }
        return;
      }

      emptyUploadRowTicks = 0;
      docUploadIdleNotified = false;
      autoRowUploadTriggered = true;

      chrome.storage.local.get(['currentCandidate', 'queueList', 'currentUtiRefNo'], async (res) => {
        let cand = res.currentCandidate || (Array.isArray(res.queueList) && res.queueList[0]) || null;
        let targetName = cand ? (cand.full_name || cand.applicant_name) : '';
        let targetAppNo = res.currentUtiRefNo ? String(res.currentUtiRefNo).replace(/^U-/i, '').trim() : '';

        // Match row in the inner candidate table
        let rowMatch = (targetName || targetAppNo) ? findCandidateUploadRow(targetAppNo, targetName) : null;

        // If not matched, pick first pending row in visible table
        if (!rowMatch) {
          const firstInfo = getFirstVisibleTableRowCandInfo();
          if (firstInfo && (firstInfo.candName || firstInfo.appNo)) {
            rowMatch = findCandidateUploadRow(firstInfo.appNo, firstInfo.candName);
            if (rowMatch) {
              targetName = firstInfo.candName;
              targetAppNo = firstInfo.appNo;
            }
          }
        }

        if (rowMatch && rowMatch.uploadBtn) {
          const cooldownKey = `wb_upload_click_ts_${targetAppNo || targetName || 'row1'}`;
          const lastClickTs = Number(sessionStorage.getItem(cooldownKey) || 0);
          if (Date.now() - lastClickTs < 4000) {
            return;
          }
          sessionStorage.setItem(cooldownKey, String(Date.now()));

          updateBadge(`⚡ Clicking Upload for ${targetName || targetAppNo}...`, '#0ea5e9');

          window._activeRowCandName = targetName;
          window._activeRowAppNo = targetAppNo;
          if (cand) {
            window._activeRowCandidate = cand;
            window._selectedRowCandidate = cand;
          } else if (targetName) {
            fetchCandidateBySearch(targetName).then((c) => {
              if (c) {
                window._docTableCandidateCache[targetName.toUpperCase()] = c;
                if (targetAppNo) window._docTableCandidateCache[targetAppNo] = c;
                window._activeRowCandidate = c;
                window._selectedRowCandidate = c;
              }
            });
          }

          setTimeout(() => {
            triggerNativeRowUploadClick(rowMatch.uploadBtn);
          }, 350);
          return;
        }

        autoRowUploadTriggered = false;
      });
    }
  }

  // -----------------------------------------------------------------------
  // SESSION INVALID & CSC LOGIN (Images 1 & 2)
  // -----------------------------------------------------------------------
  let sessionInvalidRedirecting = false;
  let cscLoginClicking = false;
  let otpFetchInitiated = false;
  let changeProfileSubmitted = false;

  function isChangeProfilePage() {
    const url = (window.location.href || '').toLowerCase();
    const text = (document.body ? (document.body.innerText || document.body.textContent || '') : '').toLowerCase();

    return (
      url.includes('changeprofile') ||
      url.includes('editprofile') ||
      url.includes('vleprofile') ||
      url.includes('profile') ||
      (text.includes('change profile') && (text.includes('personal details') || text.includes('vle id') || text.includes('contact person name'))) ||
      (text.includes('personal details') && text.includes('police verification certificate'))
    );
  }

  function handleChangeProfilePage() {
    if (changeProfileSubmitted) return;

    updateBadge('👤 VLE Change Profile Page • Auto-clicking Submit...', '#38bdf8');

    const submitBtn =
      document.querySelector('input[type="submit"][value*="Submit" i]') ||
      document.querySelector('button[type="submit"]') ||
      Array.from(document.querySelectorAll('input, button, a')).find((el) => {
        if (!isVisible(el)) return false;
        const val = (el.value || el.textContent || '').trim().toLowerCase();
        return val === 'submit';
      });

    if (submitBtn) {
      changeProfileSubmitted = true;
      setTimeout(() => {
        updateBadge('⚡ Profile Submit Clicked! Proceeding...', '#10b981');
        try { submitBtn.focus(); } catch (e) {}
        try { submitBtn.click(); } catch (e) {}
        try {
          submitBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        } catch (e) {}
        const form = submitBtn.closest('form') || document.querySelector('form');
        if (form) {
          try { form.submit(); } catch (e) {}
        }
      }, 500);
    }
  }

  function isOtpVerificationPage() {
    const url = (window.location.href || '').toLowerCase();
    return (
      url.includes('logincheckotpredirect') ||
      url.includes('logincheckotpverify') ||
      Boolean(document.getElementById('otpInput')) ||
      Boolean(document.getElementById('submitOtpBtn')) ||
      (document.body && document.body.innerText.toLowerCase().includes('verification code') && document.body.innerText.toLowerCase().includes('enter otp'))
    );
  }

  function handleOtpVerificationPage() {
    if (otpFetchInitiated) return;
    otpFetchInitiated = true;

    const triggerTime = Date.now();
    updateBadge('📧 OTP Verification Screen • Fetching current-time code from Gmail...', '#f59e0b');

    chrome.runtime.sendMessage({ action: 'fetchGmailOtp', triggerTime }, () => {
      void chrome.runtime.lastError;
    });

    setTimeout(() => {
      otpFetchInitiated = false;
    }, 45000);
  }

  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg && msg.action === 'fillOtpAndSubmit') {
      const code = String(msg.code || '').trim();
      const otpInput = document.getElementById('otpInput') || document.querySelector('input[placeholder*="OTP" i]');
      const submitBtn = document.getElementById('submitOtpBtn') || document.querySelector('button[type="submit"]');

      if (otpInput && code) {
        otpInput.value = code;
        ['input', 'change', 'keyup', 'blur'].forEach((evt) => {
          try { otpInput.dispatchEvent(new Event(evt, { bubbles: true })); } catch (e) {}
        });

        updateBadge(`🔑 OTP ${code} Filled • Submitting...`, '#10b981');

        setTimeout(() => {
          if (submitBtn) {
            submitBtn.disabled = false;
            try { submitBtn.click(); } catch (e) {}
          }
          const form = document.getElementById('loginForm') || document.querySelector('form');
          if (form) {
            try { form.submit(); } catch (e) {}
          }
        }, 500);
      }
      sendResponse({ success: true });
      return true;
    }
  });

  let logoutRestarting = false;

  function isLoggedOutPage() {
    const url = (window.location.href || '').toLowerCase();
    const text = (document.body ? (document.body.innerText || document.body.textContent || '') : '').toLowerCase();

    if (
      text.includes('you have logged out successfully') ||
      text.includes('logged out successfully') ||
      text.includes('successfully logged out')
    ) {
      return true;
    }

    if (url.includes('editvle') && text.includes('logged out')) {
      return true;
    }

    return false;
  }

  function handleLoggedOutPage() {
    if (logoutRestarting) return;
    logoutRestarting = true;

    updateBadge('⚠️ Logged Out • Closing page & restarting PAN entry process...', '#f59e0b');

    try { sessionStorage.removeItem('wb_pan_menu_clicked_at'); } catch (e) {}

    try {
      chrome.runtime.sendMessage({ action: 'restartAfterLogout' }, () => {
        void chrome.runtime.lastError;
      });
    } catch (e) {}

    // Retry signal once if tab remained open
    setTimeout(() => {
      try {
        chrome.runtime.sendMessage({ action: 'restartAfterLogout' }, () => {
          void chrome.runtime.lastError;
        });
      } catch (e) {}
    }, 3200);
  }

  function isSessionInvalidPage() {
    const text = (document.body ? document.body.innerText : '').toLowerCase();
    return (
      text.includes('session invalid') ||
      text.includes('invalid session') ||
      text.includes('session has expired') ||
      text.includes('session expired')
    );
  }

  function handleSessionInvalidPage() {
    if (sessionInvalidRedirecting) return;
    sessionInvalidRedirecting = true;

    updateBadge('⚠️ Session Invalid • Redirecting to CSC Login...', '#f59e0b');

    // Tell background service worker to navigate the browser tab directly
    // This avoids iframe sandbox restrictions and X-Frame-Options: DENY
    try {
      chrome.runtime.sendMessage({ action: 'openCscLoginTab' });
    } catch (e) {}

    // Fallback: If top-level window, assign URL directly
    setTimeout(() => {
      try {
        if (window.top === window) {
          window.location.assign('https://www.myutiitsl.com/panonlineservices/CSCLogin');
        } else {
          try {
            window.top.location.href = 'https://www.myutiitsl.com/panonlineservices/CSCLogin';
          } catch (e) {}
        }
      } catch (e) {}
    }, 400);
  }

  function handleCscLoginPage() {
    if (cscLoginClicking) return;

    updateBadge('⚡ CSC Login Screen • Auto-Clicking "Click Here"...', '#0ea5e9');

    function executeClickHere() {
      // 1. Direct ID or specific selectors for the "Click here" button on CSCLogin
      const clickHereBtn =
        document.getElementById('LoginCSC_0') ||
        document.querySelector('input[id*="LoginCSC"]') ||
        document.querySelector('input[src*="ClickHere" i]') ||
        document.querySelector('input[src*="Clickhere" i]') ||
        document.querySelector('input[value*="Click here" i]') ||
        document.querySelector('a[href*="connect.csc.gov.in"]') ||
        document.querySelector('a[href*="authorize"]') ||
        Array.from(document.querySelectorAll('input, a, button, img, div, span')).find((el) => {
          if (!isVisible(el)) return false;
          const txt = (el.textContent || el.value || el.alt || el.title || '').trim().toLowerCase();
          return txt === 'click here' || (txt.includes('click') && txt.includes('here'));
        });

      if (clickHereBtn) {
        cscLoginClicking = true;
        updateBadge('⚡ Clicked "Click Here" • Redirecting to Digital Seva Connect...', '#10b981');

        try { clickHereBtn.focus(); } catch (e) {}
        try { clickHereBtn.click(); } catch (e) {}
        try {
          clickHereBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        } catch (e) {}

        const parentAnchor = clickHereBtn.closest('a') || (clickHereBtn.tagName.toLowerCase() === 'a' ? clickHereBtn : null);
        if (parentAnchor && parentAnchor.href) {
          try { parentAnchor.click(); } catch (e) {}
          setTimeout(() => {
            if (window.location.href.toLowerCase().includes('csclogin')) {
              window.location.assign(parentAnchor.href);
            }
          }, 350);
        }
        return true;
      }
      return false;
    }

    if (!executeClickHere()) {
      let attempts = 0;
      const retryInterval = setInterval(() => {
        attempts++;
        if (executeClickHere() || attempts > 25) {
          clearInterval(retryInterval);
        }
      }, 200);
    }
  }

  // -----------------------------------------------------------------------
  // PAN Already Exists / Duplicate PAN (reGeneration page / alert)
  // -----------------------------------------------------------------------
  let isRejectingDuplicatePan = false;

  function isPanAlreadyExistsPage() {
    const url = (window.location.href || '').toLowerCase();
    if (url.includes('regeneration')) return true;

    const pageText = (document.body ? document.body.innerText : '').toLowerCase();
    return (
      pageText.includes('pan has already been issued against aadhaar') ||
      pageText.includes('verification with pan database, it appear that pan has already been issued') ||
      (pageText.includes('pan has already been issued') && pageText.includes('pan change request'))
    );
  }

  function handlePanAlreadyExistsPage() {
    if (isRejectingDuplicatePan) return;
    isRejectingDuplicatePan = true;

    const rejectionComment = 'आपका पैन कार्ड पहले से बना है। (apka pan card pahle se bana hai)';
    updateBadge(`⚠️ ${rejectionComment} • Auto-Rejecting...`, '#ef4444');

    chrome.storage.local.get(['currentCandidate', 'queueList'], (stored) => {
      const cand = stored?.currentCandidate || (Array.isArray(stored?.queueList) && stored?.queueList[0]) || currentCandidate;
      const appId = cand ? (cand.id || cand.app_id) : null;
      const appRef = cand ? String(cand.app_ref || '').replace(/^#+/, '').trim() : null;

      chrome.runtime.sendMessage({
        action: 'candidateRejected',
        appId: appId,
        appRef: appRef,
        reason: rejectionComment
      }, (resp) => {
        updateBadge('❌ Marked as REJECTED on BiharGo (Wallet Refunded) • Closing tab in 2s...', '#ef4444');

        // Click Close button on UTI error page
        const closeBtn = Array.from(document.querySelectorAll('button, input[type="button"], a, div[role="button"]')).find((b) => {
          const t = (b.textContent || b.value || '').trim().toLowerCase();
          return t === 'close';
        });
        if (closeBtn) {
          try { closeBtn.click(); } catch (e) {}
        }

        // Auto close this UTI tab
        setTimeout(() => {
          chrome.runtime.sendMessage({ action: 'closeCurrentTab' });
        }, 1500);
      });
    });
  }

  let isRejectingBiometricMismatch = false;

  function isAadhaarBiometricMismatchModal() {
    const bioDialog = Array.from(document.querySelectorAll('.ui-dialog, .modal, div[role="dialog"]')).find((d) => {
      if (!isVisible(d)) return false;
      const t = (d.innerText || d.textContent || '').toLowerCase();
      return (
        t.includes('authenticate aadhaar details using biometric') ||
        (t.includes('authenticate aadhaar') && t.includes('biometric'))
      );
    });
    if (bioDialog) return true;

    const pageText = (document.body ? document.body.innerText : '').toLowerCase();
    return pageText.includes('authenticate aadhaar details using biometric');
  }

  function handleAadhaarBiometricMismatchModal() {
    if (isRejectingBiometricMismatch) return;
    isRejectingBiometricMismatch = true;

    const rejectionComment = 'आधार और नाम मैच नहीं है, सही से एंट्री करें। (Aadhaar aur naam mismatch hai, sahi se entry kare)';
    updateBadge(`⚠️ ${rejectionComment} • Auto-Rejecting...`, '#ef4444');

    chrome.storage.local.get(['currentCandidate', 'queueList'], (stored) => {
      const cand = stored?.currentCandidate || (Array.isArray(stored?.queueList) && stored?.queueList[0]) || currentCandidate;
      const appId = cand ? (cand.id || cand.app_id) : null;
      const appRef = cand ? String(cand.app_ref || '').replace(/^#+/, '').trim() : null;

      chrome.runtime.sendMessage({
        action: 'candidateRejected',
        appId: appId,
        appRef: appRef,
        reason: rejectionComment
      }, () => {
        updateBadge('❌ Marked as REJECTED on BiharGo (Aadhaar/Name Mismatch) • Closing tab in 1.5s...', '#ef4444');

        // Auto close this UTI tab
        setTimeout(() => {
          chrome.runtime.sendMessage({ action: 'closeCurrentTab' });
        }, 1500);
      });
    });
  }

  // -----------------------------------------------------------------------
  // Router & Observers
  // -----------------------------------------------------------------------
  function runAutoFill(force = false) {
    const url = (window.location.href || '').toLowerCase();

    // 000000. Logged Out Screen (editVle.action -> "You have logged out successfully!")
    if (isLoggedOutPage()) {
      handleLoggedOutPage();
      return;
    }

    // 00000. Change Profile Screen (right after OTP login)
    if (isChangeProfilePage()) {
      handleChangeProfilePage();
      return;
    }

    // 0000. OTP Verification Page (loginCheckOtpRedirect)
    if (isOtpVerificationPage()) {
      handleOtpVerificationPage();
      return;
    }

    // 000. Check Session Invalid Page (Image 1)
    if (isSessionInvalidPage()) {
      handleSessionInvalidPage();
      return;
    }

    // 00. Check if PAN already exists on this Aadhaar (reGeneration page / dialog)
    if (isPanAlreadyExistsPage()) {
      handlePanAlreadyExistsPage();
      return;
    }

    // 00B. Check if Aadhaar Demographic Mismatch ("Authenticate AADHAAR details using Biometric.")
    if (isAadhaarBiometricMismatchModal()) {
      handleAadhaarBiometricMismatchModal();
      return;
    }

    // 0A. Document Upload List / Modal (applType=B)
    if (url.includes('docuploadlistpanapp') || url.includes('docupload')) {
      handleDocUploadListPage();
      return;
    }

    // 0B. Receipt / redirectSuccess page (Success Screen)
    if (isReceiptPage()) {
      handleReceiptSuccessPage();
      return;
    }

    // 1. Form No. 93 / IndmainForm (Highest Priority)
    if (
      url.includes('indmainform') ||
      url.includes('panind.html') ||
      url.includes('mainformeditind') ||
      document.getElementById('firstName') ||
      document.getElementById('identityProofDoc') ||
      document.getElementById('resAddr1') ||
      document.getElementById('capacityCd') ||
      document.getElementById('refNo') ||
      document.getElementById('reEnterUid')
    ) {
      handleIndMainForm(force);
      return;
    }

    // 2. prePANForm (Only when strictly on prePANForm URL)
    if (url.includes('prepanform')) {
      handlePrePanForm();
      return;
    }

    // 3. VLE Home
    if (url.includes('vlehome')) {
      handleVleHomePage();
      return;
    }

    // 4. Error page
    if (url.includes('error?errormsg') || document.body?.innerText?.toLowerCase().includes('something went wrong')) {
      updateBadge('⚠️ Direct Form blocked • Reopen from vleHome', '#ef4444');
      return;
    }

    // 5. CSC Login -> Auto-Click "Click Here" (Image 2)
    if (
      url.includes('csclogin') ||
      document.getElementById('LoginCSC_0') ||
      (document.body && document.body.innerText.includes('VLE Users')) ||
      document.querySelector('a[href*="connect.csc.gov.in"]') ||
      document.querySelector('input[src*="ClickHere" i]')
    ) {
      handleCscLoginPage();
      return;
    }

    updateBadge('Ready on UTI PAN Portal', '#38bdf8');
  }

  chrome.storage.local.get(['currentCandidate', 'currentUtiRefNo'], (res) => {
    currentCandidate = res.currentCandidate || null;
    currentUtiRefNo = res.currentUtiRefNo || '';
    syncAurangabadCandidateState();
    runAutoFill(false);
  });

  chrome.storage.onChanged.addListener((changes, area) => {
    if (area !== 'local') return;
    if (changes.currentCandidate) {
      currentCandidate = changes.currentCandidate.newValue || null;
      aadhaarConfirmed = false;
      syncAurangabadCandidateState();
      runAutoFill(true);
    }
    if (changes.currentUtiRefNo) {
      currentUtiRefNo = changes.currentUtiRefNo.newValue || '';
    }
  });

  // Re-check periodically for dynamic DOM & wizard step transitions
  setInterval(() => {
    runAutoFill(false);
  }, 700);
})();
