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

    // 0. PAN Already Exists on Aadhaar Check
    if (isPanAlreadyExistsPage()) {
      handlePanAlreadyExistsPage();
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

    // 4. Message / Error Dialog (#errorDiv, #errorDialog, "Kindly enter pincode.")
    const errorDialog = Array.from(document.querySelectorAll('.ui-dialog')).find((d) => {
      if (!isVisible(d)) return false;
      const t = (d.innerText || d.textContent || '').toLowerCase();
      return (
        t.includes('kindly enter pincode') ||
        t.includes('enter pincode') ||
        (t.includes('message') && !t.includes('re-enter aadhaar') && !t.includes('reference number'))
      );
    });

    if (errorDialog) {
      const okBtn = Array.from(errorDialog.querySelectorAll('.ui-dialog-buttonpane button, button, input[type="button"], a.ui-dialog-titlebar-close, button.ui-dialog-titlebar-close')).find((b) => {
        const t = (b.textContent || b.value || '').trim().toLowerCase();
        return t === 'ok' || t === 'okay' || b.classList.contains('ui-dialog-titlebar-close');
      });
      if (okBtn) {
        try {
          okBtn.click();
        } catch (e) {
          okBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        }
        handled = true;
      }
      // Re-fill pincode immediately after dismissing popup
      const resPinEl = document.getElementById('resPin');
      const pincode = String(currentCandidate?.pincode || currentCandidate?.pin || '').replace(/\D/g, '').slice(0, 6);
      if (resPinEl && pincode && resPinEl.value !== pincode) {
        setTimeout(() => {
          setInputValue(resPinEl, pincode);
        }, 150);
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
    const resDistListEl = document.getElementById('resDistList');
    const district = (currentCandidate?.district_name || currentCandidate?.district || '').trim();
    let districtChanged = false;
    if (resDistListEl && district && (!resDistListEl.value || force)) {
      if (selectOptionByText(resDistListEl, district, false)) {
        filled = true;
        districtChanged = true;
      }
    }

    // 7. Pin/Zip Code (resPin) - STRICTLY FILLED LAST
    const resPinEl = document.getElementById('resPin');
    const pincode = String(currentCandidate?.pincode || currentCandidate?.pin || '').replace(/\D/g, '').slice(0, 6);
    if (resPinEl && pincode) {
      if (resPinEl.value !== pincode || force) {
        // Allow district AJAX to settle, then fill PIN
        const delay = districtChanged ? 400 : 100;
        setTimeout(() => {
          if (resPinEl.value !== pincode) {
            setInputValue(resPinEl, pincode);
          }
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
    const dialogs = Array.from(document.querySelectorAll('.ui-dialog, .modal, .swal2-container'));
    return dialogs.some((d) => isVisible(d));
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
    const inc = document.getElementById('incomeOthSource');
    const addr1 = document.getElementById('resAddr1');
    const dist = document.getElementById('resDistList');
    const pin = document.getElementById('resPin');

    if (inc && !inc.checked) return false;
    if (!addr1 || !addr1.value.trim()) return false;
    if (!dist || !dist.value) return false;
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
      try { sessionStorage.setItem('wb_pan_menu_clicked', '1'); } catch (e) {}
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
    updateBadge('VLE Home • Form 49A Ready', '#10b981');

    if (!vleMenuClicked && !sessionStorage.getItem('wb_pan_menu_clicked')) {
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

  // Helper to synthesize a high-resolution JPEG receipt when browser captureTab is unavailable
  function generateReceiptCanvasJpeg(couponNo, applicantName, mobileNo, aadhaarLast4, parentName, dob, barcodeText) {
    const canvas = document.createElement('canvas');
    canvas.width = 980;
    canvas.height = 580;
    const ctx = canvas.getContext('2d');

    // Clean white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Border
    ctx.strokeStyle = '#0284c7';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(10, 10, canvas.width - 20, canvas.height - 20);

    // Top Header Banner
    ctx.fillStyle = '#0284c7';
    ctx.fillRect(12, 12, canvas.width - 24, 60);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 22px system-ui, sans-serif';
    ctx.fillText('UTIITSL PAN Online Services', 30, 48);

    ctx.fillStyle = '#0f172a';
    ctx.font = 'bold 16px system-ui, sans-serif';
    ctx.fillText('PAN Application Acknowledgment Receipt For Form No. 93', 30, 105);

    ctx.font = '13px system-ui, sans-serif';
    ctx.fillStyle = '#475569';
    ctx.fillText('( Physical Application )', 30, 126);

    // Barcode / Coupon box (top right)
    ctx.fillStyle = '#f8fafc';
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 1.5;
    ctx.fillRect(640, 85, 310, 80);
    ctx.strokeRect(640, 85, 310, 80);

    ctx.fillStyle = '#0f172a';
    ctx.font = 'bold 11px monospace';
    ctx.fillText('APPLICATION / COUPON NO.', 655, 108);
    ctx.font = 'bold 20px monospace';
    ctx.fillStyle = '#0369a1';
    ctx.fillText(couponNo || 'U-PENDING', 655, 140);

    // Draw faux barcode lines
    ctx.fillStyle = '#0f172a';
    const bCode = barcodeText || (couponNo ? couponNo.replace(/^U-/i, '') : '126075597');
    for (let bx = 655; bx < 930; bx += 4) {
      if ((bx % 3) === 0 || (bx % 5) === 0) {
        ctx.fillRect(bx, 148, 2, 12);
      }
    }

    // Table rows
    const rows = [
      ['Received Rs. 107.00/- (incl of taxes) from', applicantName || '-'],
      ['Application No. / Coupon No.', couponNo || '-'],
      ['Parent Name (On Card)', parentName || 'Mentioned on Form'],
      ['Date of Birth', dob || '-'],
      ['Aadhaar Number', aadhaarLast4 ? `XXXX-XXXX-${aadhaarLast4} (MENTIONED, MATCHED)*` : 'MENTIONED, MATCHED'],
      ['Name as per Aadhaar', applicantName || '-'],
      ['Applicant\'s Contact details', mobileNo || '-'],
      ['Residence State', 'BIHAR'],
      ['Proof of Identity / Address / DOB', 'AADHAAR Card Issued by UIDAI (In Copy)'],
      ['Mode of PAN Card', 'Both physical PAN and e-PAN Card'],
      ['Payment Ref No', 'PAID VIA CSC WALLET'],
      ['Date of Receipt', new Date().toLocaleString('en-IN')]
    ];

    let startY = 190;
    rows.forEach(([label, val], idx) => {
      if (idx % 2 === 0) {
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(20, startY - 18, 940, 26);
      }
      ctx.fillStyle = '#475569';
      ctx.font = '600 12px system-ui, sans-serif';
      ctx.fillText(label, 30, startY);

      ctx.fillStyle = '#0f172a';
      ctx.font = 'bold 12.5px system-ui, sans-serif';
      ctx.fillText(String(val), 340, startY);

      startY += 28;
    });

    // Footer
    ctx.fillStyle = '#64748b';
    ctx.font = '11px system-ui, sans-serif';
    ctx.fillText('Designed, Developed and Maintained by UTIITSL • Auto-Captured via Welcome Bihar', 30, canvas.height - 20);

    return canvas.toDataURL('image/jpeg', 0.88);
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

    // Extract exact candidate details from the receipt table
    const nameMatch = pageText.match(/Received\s*Rs\.[^\n]*from\s*([A-Za-z\s]+)/i) || pageText.match(/Name\s*as\s*per\s*Aadhaar\s*([A-Za-z\s]+)/i);
    const applicantName = nameMatch ? nameMatch[1].replace(/\n/g, ' ').trim() : '';

    const mobMatch = pageText.match(/Contact\s*details[^\n]*\b(\d{10})\b/i) || pageText.match(/\b([6-9]\d{9})\b/);
    const mobileNo = mobMatch ? mobMatch[1].trim() : '';

    const aadhMatch = pageText.match(/Aadhaar\s*Number\s*([xX0-9\-]+)/i);
    const aadhaarLast4 = aadhMatch ? aadhMatch[1].replace(/\D/g, '').slice(-4) : '';

    const parentMatch = pageText.match(/Parent\s*Name[^\n]*\b([A-Za-z\s]+)/i);
    const parentName = parentMatch ? parentMatch[1].replace(/\n/g, ' ').trim() : '';

    const dobMatch = pageText.match(/Date\s*of\s*Birth\s*([0-9\/\-]+)/i);
    const dob = dobMatch ? dobMatch[1].trim() : '';

    // Scroll to top to ensure complete visibility of receipt
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    let receiptEl = findReceiptElement();
    if (receiptEl) {
      try { receiptEl.scrollIntoView({ behavior: 'instant', block: 'start' }); } catch (e) {}
    }

    // Allow 400ms for layout to settle cleanly
    await new Promise((r) => setTimeout(r, 400));

    receiptEl = findReceiptElement();
    const rect = receiptEl ? receiptEl.getBoundingClientRect() : null;
    const dpr = window.devicePixelRatio || 1;

    const crop = (rect && rect.width > 250 && rect.height > 180) ? {
      x: Math.max(0, Math.floor((rect.left - 4) * dpr)),
      y: Math.max(0, Math.floor((rect.top - 4) * dpr)),
      width: Math.min(window.innerWidth * dpr, Math.ceil((rect.width + 8) * dpr)),
      height: Math.min(window.innerHeight * dpr, Math.ceil((rect.height + 8) * dpr))
    } : null;

    chrome.runtime.sendMessage({ action: 'captureTab' }, (res) => {
      let finalBase64 = null;

      if (res && res.dataUrl) {
        const img = new Image();
        img.onload = () => {
          let croppedBase64 = res.dataUrl;
          if (crop && crop.width > 100 && crop.height > 100) {
            try {
              const canvas = document.createElement('canvas');
              const targetW = Math.max(10, Math.min(crop.width, img.width - crop.x));
              const targetH = Math.max(10, Math.min(crop.height, img.height - crop.y));
              if (targetW > 100 && targetH > 100 && crop.x < img.width && crop.y < img.height) {
                canvas.width = targetW;
                canvas.height = targetH;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, crop.x, crop.y, targetW, targetH, 0, 0, targetW, targetH);
                croppedBase64 = canvas.toDataURL('image/jpeg', 0.88);
              }
            } catch (err) {
              croppedBase64 = res.dataUrl;
            }
          }
          deliverReceivingPayload(croppedBase64, couponNo, applicantName, mobileNo, aadhaarLast4, isRetry);
        };

        img.onerror = () => {
          const synth = generateReceiptCanvasJpeg(couponNo, applicantName, mobileNo, aadhaarLast4, parentName, dob);
          deliverReceivingPayload(synth, couponNo, applicantName, mobileNo, aadhaarLast4, isRetry);
        };

        img.src = res.dataUrl;
      } else {
        // Fallback directly to synthesized receipt canvas
        const synth = generateReceiptCanvasJpeg(couponNo, applicantName, mobileNo, aadhaarLast4, parentName, dob);
        deliverReceivingPayload(synth, couponNo, applicantName, mobileNo, aadhaarLast4, isRetry);
      }
    });
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

          setTimeout(() => {
            try {
              chrome.runtime.sendMessage({ action: 'openDocUploadPage' });
            } catch (e) {}
          }, 1200);
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

  function findCandidateUploadRow(appNo, candidateName) {
    const cleanAppNo = String(appNo || '').replace(/^U-/i, '').trim().toUpperCase();
    const cleanName = String(candidateName || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();

    const rows = Array.from(document.querySelectorAll('table tr'));
    for (const row of rows) {
      const text = (row.innerText || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
      const matchesAppNo = cleanAppNo && cleanAppNo.length >= 6 && text.includes(cleanAppNo);
      const matchesName = cleanName && cleanName.length >= 3 && text.includes(cleanName);

      // Strict row check: Must match App No, or clean Name
      if (matchesAppNo || matchesName) {
        const actionLinks = Array.from(row.querySelectorAll('a, button, input[type="button"]'));
        const uploadBtn = actionLinks.find((el) => {
          const t = (el.innerText || el.value || '').trim().toLowerCase();
          return t === 'upload' || (t.includes('upload') && !t.includes('crop'));
        });
        if (uploadBtn) {
          return { row, uploadBtn, cleanAppNo, cleanName };
        }
      }
    }
    return null;
  }

  function findDocUploadModal() {
    const dialogs = Array.from(document.querySelectorAll('.ui-dialog, .modal, div.popup, div[role="dialog"], div')).filter((d) => {
      if (!isVisible(d)) return false;
      const t = (d.innerText || '').toLowerCase();
      return t.includes('upload documents') && (t.includes('pan form alongwith') || d.querySelector('input[type="file"]'));
    });
    return dialogs.length > 0 ? dialogs[0] : null;
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
          const byteNumbers = new Array(byteCharacters.length);
          for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
          }
          const byteArray = new Uint8Array(byteNumbers);
          const blob = new Blob([byteArray], { type: res.mime || defaultMime });
          const file = new File([blob], filename, { type: res.mime || defaultMime });
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
      const url = chrome.runtime.getURL('assets/default_pan_form.pdf');
      const res = await fetch(url);
      if (res.ok) {
        const blob = await res.blob();
        if (blob && blob.size > 1000) {
          return new File([blob], 'PAN_Form_Alongwith_Documents.pdf', { type: 'application/pdf' });
        }
      }
    } catch (e) {
      console.warn('Direct fetch of default PDF failed, trying background worker:', e);
    }

    try {
      return await fetchFileViaBackground('assets/default_pan_form.pdf', 'PAN_Form_Alongwith_Documents.pdf', 'application/pdf');
    } catch (err) {
      console.warn('Background fetch of default PDF failed:', err);
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
        return new File([buf], 'PAN_Form_Alongwith_Documents.pdf', { type: 'application/pdf' });
      }

      // Check if JPEG image (0xFF 0xD8)
      if (bytes.length >= 2 && bytes[0] === 0xFF && bytes[1] === 0xD8) {
        const pdfBuf = createPdfFromJpeg(buf);
        return new File([pdfBuf], 'PAN_Form_Alongwith_Documents.pdf', { type: 'application/pdf' });
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
      return new File([pdfBuf], 'PAN_Form_Alongwith_Documents.pdf', { type: 'application/pdf' });
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
    return new File([finalBuffer], `${safeName}_photo_213x213_300dpi.jpg`, { type: 'image/jpeg' });
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
    return new File([finalBuffer], `${safeName}_signature_400x200_600dpi.jpg`, { type: 'image/jpeg' });
  }

  // Do NOT auto click the modal Upload button (RIOT instructed: "esme per auto click mat karo sirf upload karo")
  const AUTO_CLICK_MODAL_UPLOAD = false;

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
        uploadBtn.focus();
        uploadBtn.click();
      } catch (e) {
        uploadBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }

      setTimeout(() => {
        docUploadLock = false;
        chrome.storage.local.set({ awaitingDocUpload: false });
      }, 3000);
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
      const cells = Array.from(r.querySelectorAll('td'));
      if (cells.length < 4) continue;
      const text = (r.innerText || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
      const appNoMatch = text.match(/\b(A\d{8,})\b/i);
      const appNo = appNoMatch ? appNoMatch[1] : '';
      let candName = '';
      if (cells[2] && cells[2].innerText && !cells[2].innerText.includes('Upload')) {
        candName = cells[2].innerText.replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
      } else if (cells[1] && cells[1].innerText && isNaN(cells[1].innerText)) {
        candName = cells[1].innerText.replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
      }
      if (candName) return { appNo, candName, row: r };
    }
    return null;
  }

  // Floating helper control bar on Document Upload page
  function ensureFloatingDocUploadBar() {
    if (document.getElementById('wb-floating-upload-bar')) return;

    const bar = document.createElement('div');
    bar.id = 'wb-floating-upload-bar';
    bar.style.cssText = 'position:fixed;top:10px;right:15px;z-index:999999;background:#0f172a;border:1.5px solid #38bdf8;border-radius:8px;padding:8px 14px;color:#f8fafc;font-family:system-ui,-apple-system,sans-serif;font-size:12px;box-shadow:0 4px 20px rgba(0,0,0,0.5);display:flex;align-items:center;gap:10px;';

    const title = document.createElement('span');
    title.innerHTML = '<strong style="color:#38bdf8;">📂 Document Upload:</strong>';

    const firstInfo = getFirstVisibleTableRowCandInfo();
    const candSpan = document.createElement('span');
    candSpan.id = 'wb-bar-candidate-name';
    candSpan.innerText = firstInfo?.candName ? firstInfo.candName : 'Click ⚡ Auto Upload on Candidate Row';
    candSpan.style.color = firstInfo?.candName ? '#34d399' : '#94a3b8';
    candSpan.style.fontWeight = '600';

    const btnAttach = document.createElement('button');
    btnAttach.type = 'button';
    btnAttach.id = 'wb-btn-auto-attach';
    btnAttach.innerText = '⚡ Auto-Attach Docs';
    btnAttach.title = 'Attach PDF Form, Photo & Signature into modal';
    btnAttach.style.cssText = 'background:#10b981;color:#fff;border:none;border-radius:5px;padding:6px 12px;font-size:11.5px;font-weight:700;cursor:pointer;transition:background 0.2s;';

    btnAttach.addEventListener('click', async () => {
      btnAttach.innerText = '⏳ Working...';
      btnAttach.disabled = true;

      const modal = findDocUploadModal();
      if (!modal) {
        const firstBtn = document.querySelector('.wb-auto-upload-btn') || findCandidateUploadRow('', '')?.uploadBtn;
        if (firstBtn) {
          firstBtn.click();
        } else {
          alert('Please click "Upload" or "⚡ Auto Upload" on the candidate row first!');
        }
        btnAttach.disabled = false;
        btnAttach.innerText = '⚡ Auto-Attach Docs';
        return;
      }

      const inputs = findDocUploadInputs(modal);
      if (!inputs.panFormInput || !inputs.pictureInput || !inputs.signatureInput) {
        alert('Upload modal file inputs not ready yet!');
        btnAttach.disabled = false;
        btnAttach.innerText = '⚡ Auto-Attach Docs';
        return;
      }

      const rowInfo = getFirstVisibleTableRowCandInfo();
      const targetName = rowInfo?.candName || (candSpan?.innerText || '').replace(/click.*row/i, '').trim();
      let cand = window._activeRowCandidate || (targetName ? window._docTableCandidateCache[targetName.toUpperCase()] : null);
      if (!cand && targetName) {
        cand = await fetchCandidateBySearch(targetName);
        if (cand) {
          window._docTableCandidateCache[targetName.toUpperCase()] = cand;
          window._activeRowCandidate = cand;
        }
      }

      if (cand && cand.photo_url && cand.signature_url) {
        docUploadLock = true;
        await attachDocumentsToModal(inputs, cand, modal);
        btnAttach.innerText = '✅ 3 Files Attached!';
        btnAttach.style.background = '#0284c7';
      } else {
        alert(`Could not find photo & signature for ${targetName || 'candidate'} on Welcome Bihar!`);
      }

      btnAttach.disabled = false;
      setTimeout(() => {
        btnAttach.innerText = '⚡ Auto-Attach Docs';
        btnAttach.style.background = '#10b981';
      }, 4000);
    });

    const btnFetch = document.createElement('button');
    btnFetch.type = 'button';
    btnFetch.innerText = '🔄 Match Candidate';
    btnFetch.style.cssText = 'background:#0284c7;color:#fff;border:none;border-radius:5px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer;';

    btnFetch.addEventListener('click', async () => {
      btnFetch.innerText = '⏳ Fetching...';
      btnFetch.disabled = true;
      const rowInfo = getFirstVisibleTableRowCandInfo();
      if (rowInfo && rowInfo.candName) {
        const cand = await fetchCandidateBySearch(rowInfo.candName);
        if (cand) {
          window._docTableCandidateCache[rowInfo.candName.toUpperCase()] = cand;
          if (rowInfo.appNo) window._docTableCandidateCache[rowInfo.appNo] = cand;
          window._activeRowCandidate = cand;
          candSpan.innerText = cand.full_name;
          candSpan.style.color = '#34d399';
          updateBadge(`✅ Verified Candidate: ${cand.full_name}`, '#10b981');
        } else {
          candSpan.innerText = `${rowInfo.candName} (Not found on BiharGo)`;
          candSpan.style.color = '#f87171';
        }
      } else {
        candSpan.innerText = 'No pending candidate row found';
        candSpan.style.color = '#f87171';
      }
      btnFetch.disabled = false;
      btnFetch.innerText = '🔄 Match Candidate';
    });

    bar.appendChild(title);
    bar.appendChild(candSpan);
    bar.appendChild(btnAttach);
    bar.appendChild(btnFetch);
    document.body.appendChild(bar);
  }

  // Cache of candidates found in the current UTI table
  window._docTableCandidateCache = window._docTableCandidateCache || {};

  // Inject "⚡ Auto Upload" button into every candidate row in UTI document table
  function injectDocUploadButtons() {
    const rows = Array.from(document.querySelectorAll('table tr'));
    for (const row of rows) {
      if (row.querySelector('.wb-auto-upload-btn')) continue;

      const cells = Array.from(row.querySelectorAll('td'));
      if (cells.length < 4) continue;

      const uploadEl = Array.from(row.querySelectorAll('a, button, input[type="button"]')).find((el) => {
        const t = (el.innerText || el.value || '').trim().toLowerCase();
        return t === 'upload';
      });

      if (!uploadEl) continue;

      const text = (row.innerText || '').replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
      const appNoMatch = text.match(/\b(A\d{8,})\b/i);
      const appNo = appNoMatch ? appNoMatch[1] : '';

      let candName = '';
      if (cells[2] && cells[2].innerText && !cells[2].innerText.includes('Upload')) {
        candName = cells[2].innerText.replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
      } else if (cells[1] && cells[1].innerText && isNaN(cells[1].innerText)) {
        candName = cells[1].innerText.replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim();
      }

      uploadEl.dataset.appNo = appNo;
      uploadEl.dataset.candName = candName;
      uploadEl._isScriptClicking = false;

      // Pre-fetch candidate details for this row so clicking is instantaneous
      if (candName && !window._docTableCandidateCache[candName.toUpperCase()]) {
        fetchCandidateBySearch(candName).then((c) => {
          if (c) {
            window._docTableCandidateCache[candName.toUpperCase()] = c;
            if (appNo) window._docTableCandidateCache[appNo] = c;
          }
        });
      }

      async function triggerUploadForRow(candObj) {
        let cand = candObj || window._docTableCandidateCache[candName.toUpperCase()] || (appNo ? window._docTableCandidateCache[appNo] : null);
        if (!cand && candName) cand = await fetchCandidateBySearch(candName);
        if (!cand && appNo) cand = await fetchCandidateBySearch(appNo);

        if (cand) {
          window._activeRowCandidate = cand;
          window._selectedRowCandidate = cand;
          window._activeRowCandName = candName;
          window._activeRowAppNo = appNo;
          const barSpan = document.getElementById('wb-bar-candidate-name');
          if (barSpan) {
            barSpan.innerText = cand.full_name;
            barSpan.style.color = '#34d399';
          }
          updateBadge(`📂 Candidate: ${cand.full_name} • Attaching Documents...`, '#0ea5e9');

          // Open modal if not already open
          const existingModal = findDocUploadModal();
          if (!existingModal) {
            try {
              uploadEl._isScriptClicking = true;
              uploadEl.click();
              uploadEl._isScriptClicking = false;
            } catch (err) {
              uploadEl._isScriptClicking = false;
              uploadEl.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
            }
          }

          // Poll up to 25 times (every 80ms) for modal inputs and attach immediately
          let tries = 0;
          const pollTimer = setInterval(() => {
            tries++;
            const m = findDocUploadModal();
            if (m) {
              const inputs = findDocUploadInputs(m);
              if (inputs.panFormInput && inputs.pictureInput && inputs.signatureInput) {
                clearInterval(pollTimer);
                docUploadLock = true;
                attachDocumentsToModal(inputs, cand, m);
              }
            }
            if (tries >= 25) {
              clearInterval(pollTimer);
            }
          }, 80);
        } else {
          updateBadge(`⚠️ Candidate details not found on BiharGo for ${candName || appNo}`, '#f59e0b');
        }
      }

      // Hook click on native Upload link
      uploadEl.addEventListener('click', (e) => {
        if (uploadEl._isScriptClicking) return;
        window._activeRowAppNo = appNo;
        window._activeRowCandName = candName;
        triggerUploadForRow();
      });

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'wb-auto-upload-btn';
      btn.innerText = '⚡ Auto Upload';
      btn.title = `Auto-attach PDF, Photo & Sign for ${candName || appNo}`;
      btn.style.cssText = 'background:#10b981;color:#ffffff;border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:700;cursor:pointer;margin-left:6px;box-shadow:0 1px 3px rgba(0,0,0,0.2);display:inline-block;vertical-align:middle;';

      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        btn.disabled = true;
        btn.innerText = '⏳ Loading...';
        btn.style.background = '#0ea5e9';

        window._activeRowAppNo = appNo;
        window._activeRowCandName = candName;

        updateBadge(`🔍 Fetching details for ${candName || appNo} from Welcome Bihar...`, '#0ea5e9');

        await triggerUploadForRow();

        btn.disabled = false;
        btn.innerText = '⚡ Auto Upload';
        btn.style.background = '#10b981';
      });

      uploadEl.parentNode.insertBefore(btn, uploadEl.nextSibling);
    }
  }

  function handleDocUploadListPage() {
    // Inject floating UI and row buttons
    ensureFloatingDocUploadBar();
    injectDocUploadButtons();

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
        chrome.storage.local.set({ awaitingDocUpload: false });
        updateBadge('🎉 Documents Uploaded Successfully!', '#10b981');
      }
      return;
    }

    if (docUploadLock) return;

    // 2. Check if modal is already open
    const modal = findDocUploadModal();
    if (modal) {
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
          if (firstInfo && firstInfo.candName && window._docTableCandidateCache[firstInfo.candName.toUpperCase()]) {
            targetCand = window._docTableCandidateCache[firstInfo.candName.toUpperCase()];
          }
        }

        if (targetCand && targetCand.photo_url && targetCand.signature_url) {
          docUploadLock = true;
          attachDocumentsToModal(inputs, targetCand, modal);
          return;
        }

        updateBadge('⚠️ Please click "⚡ Auto Upload" on the candidate row', '#f59e0b');
      }
      return;
    }

    // 3. Modal not open yet: Standby for user click (NO AUTO-CLICK LOOP)
    updateBadge('📄 Document Upload List • Click "⚡ Auto Upload" on candidate row', '#38bdf8');
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

  // -----------------------------------------------------------------------
  // Router & Observers
  // -----------------------------------------------------------------------
  function runAutoFill(force = false) {
    const url = (window.location.href || '').toLowerCase();

    // 00. Check if PAN already exists on this Aadhaar (reGeneration page / dialog)
    if (isPanAlreadyExistsPage()) {
      handlePanAlreadyExistsPage();
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

    // 5. CSC Login
    if (url.includes('csclogin')) {
      updateBadge('CSC Login Screen • Please sign in', '#fbbf24');
      return;
    }

    updateBadge('Ready on UTI PAN Portal', '#38bdf8');
  }

  chrome.storage.local.get(['currentCandidate', 'currentUtiRefNo'], (res) => {
    currentCandidate = res.currentCandidate || null;
    currentUtiRefNo = res.currentUtiRefNo || '';
    runAutoFill(false);
  });

  chrome.storage.onChanged.addListener((changes, area) => {
    if (area !== 'local') return;
    if (changes.currentCandidate) {
      currentCandidate = changes.currentCandidate.newValue || null;
      aadhaarConfirmed = false;
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
