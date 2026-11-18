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
  function handlePrePanForm() {
    if (prePanSubmitted) return;

    updateBadge('Step 0: Configuring Individual & NRI No...', '#38bdf8');

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
    let individualSelected = false;
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
        individualSelected = (sel.selectedIndex === optIndex);
        break;
      }
    }

    // 3. Whether you are from defence/NRI Category ? -> Strictly "No"
    let nriNoSelected = false;
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

      if (noRadio) {
        clickRadio(noRadio);
        nriNoSelected = Boolean(noRadio.checked);
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
      if (nriRadio) {
        clickRadio(nriRadio);
        nriNoSelected = Boolean(nriRadio.checked);
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

    // 5. Submit once Individual and NRI No are configured
    if (individualSelected && nriNoSelected) {
      const submitBtn = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], a.btn, .btn')).find((b) => {
        const txt = (b.textContent || b.value || '').trim().toLowerCase();
        return txt === 'submit' || txt.startsWith('submit') || (txt.includes('submit') && !txt.includes('cancel'));
      });

      if (submitBtn && !prePanSubmitted) {
        prePanSubmitted = true;
        updateBadge('🚀 prePANForm ready! Submitting...', '#10b981');
        setTimeout(() => {
          try { submitBtn.click(); } catch (e) {}
        }, 400);
      }
    }
  }

  // -----------------------------------------------------------------------
  // POPUP & DIALOG AUTO-HANDLERS (Form No. 93)
  // -----------------------------------------------------------------------
  function handleAllModals() {
    let handled = false;

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

  function isReceiptPage() {
    const url = (window.location.href || '').toLowerCase();
    if (url.includes('redirectsuccess') || url.includes('acknowledg') || url.includes('receipt')) return true;
    const bodyText = (document.body?.innerText || '').toLowerCase();
    if (bodyText.includes('pan application acknowledgment receipt') || bodyText.includes('pan application acknowledgement receipt')) return true;
    if (bodyText.includes('coupon no') && (bodyText.includes('received rs.') || bodyText.includes('mode of pan card') || bodyText.includes('aadhaar matched'))) return true;
    return false;
  }

  function findReceiptElement() {
    // 1. Check for specific table that has the receipt rows and parent with header
    const tables = Array.from(document.querySelectorAll('table'));
    for (const tbl of tables) {
      if (!isVisible(tbl)) continue;
      const t = (tbl.innerText || tbl.textContent || '');
      if (t.includes('Received Rs.') && (t.includes('Coupon No') || t.includes('Application No'))) {
        const p = tbl.closest('div.card, div.container, div#printArea, div[id*="receipt" i], div[class*="receipt" i], table, div');
        if (p && isVisible(p) && (p.innerText || '').toLowerCase().includes('acknowledg')) {
          const pr = p.getBoundingClientRect();
          if (pr.width > 300 && pr.height > 200) {
            return p;
          }
        }
        return tbl;
      }
    }

    // 2. Search any container holding the receipt header & details
    const allContainers = Array.from(document.querySelectorAll('div, table, section, form'));
    const matches = allContainers.filter((el) => {
      if (!isVisible(el)) return false;
      const txt = (el.innerText || el.textContent || '').toLowerCase();
      return txt.includes('acknowledg') && (txt.includes('coupon no') || txt.includes('received rs.'));
    });

    if (matches.length > 0) {
      matches.sort((a, b) => {
        const ra = a.getBoundingClientRect();
        const rb = b.getBoundingClientRect();
        return (ra.width * ra.height) - (rb.width * rb.height);
      });
      return matches[0];
    }

    return null;
  }

  function sendCompletionPayload(croppedBase64, couponNo, applicantName, mobileNo, aadhaarLast4) {
    chrome.storage.local.get(['currentCandidate', 'queueList'], (stored) => {
      const cand = stored?.currentCandidate || (Array.isArray(stored?.queueList) && stored?.queueList[0]) || null;
      const appId = cand ? (cand.id || cand.app_id) : null;
      const appRef = cand ? String(cand.app_ref || '').replace(/^#+/, '').trim() : null;

      updateBadge(`📤 Updating Welcome Bihar (Coupon: ${couponNo || 'Saved'})...`, '#38bdf8');

      chrome.runtime.sendMessage({
        action: 'candidateCompleted',
        appId: appId,
        appRef: appRef,
        applicantName: applicantName || cand?.full_name || '',
        mobileNo: mobileNo || cand?.mobile_no || '',
        aadhaarNo: aadhaarLast4 || cand?.aadhaar_no || '',
        utiRefNo: couponNo || currentUtiRefNo,
        remarks: `UTI PAN Form 93 Completed. Coupon: ${couponNo || 'Attached'}`,
        screenshot: croppedBase64 || '',
        result_file_base64: croppedBase64 || ''
      }, (resp) => {
        receiptHandling = false;
        if (resp && resp.success) {
          lastCompletedCoupon = couponNo;
          updateBadge(`🎉 Application COMPLETED on Welcome Bihar! Opening Upload List...`, '#10b981');
          setTimeout(() => {
            try {
              chrome.runtime.sendMessage({ action: 'openDocUploadPage' });
            } catch (e) {}
          }, 800);
        } else {
          updateBadge(`⚠️ Complete update: ${resp?.error || 'Pending retry'}`, '#f59e0b');
        }
      });
    });
  }

  function handleReceiptSuccessPage() {
    if (receiptHandling) return;

    const pageText = document.body?.innerText || '';
    const couponMatch = pageText.match(/(?:Application\s*No\.?\s*\/?\s*Coupon\s*No\.?|Coupon\s*No\.?)\s*[:\s]*([Uu]-[A-Za-z0-9]+|[A-Za-z0-9]{8,})/i) ||
                        pageText.match(/\b(U-[A-Za-z0-9]{8,})\b/i);
    const couponNo = couponMatch ? couponMatch[1].trim() : (currentUtiRefNo || '');

    // If already completed for this specific coupon, avoid duplicate API calls
    if (couponNo && couponNo === lastCompletedCoupon) {
      return;
    }

    // Extract demographic details directly from the on-screen receipt table
    const nameMatch = pageText.match(/Received\s*Rs\.[^\n]*from\s*([A-Za-z\s]+)/i) || pageText.match(/Name\s*as\s*per\s*Aadhaar\s*([A-Za-z\s]+)/i);
    const applicantName = nameMatch ? nameMatch[1].replace(/\n/g, ' ').trim() : '';

    const mobMatch = pageText.match(/Contact\s*details[^\n]*\b(\d{10})\b/i) || pageText.match(/\b([6-9]\d{9})\b/);
    const mobileNo = mobMatch ? mobMatch[1].trim() : '';

    const aadhMatch = pageText.match(/Aadhaar\s*Number\s*([xX0-9\-]+)/i);
    const aadhaarLast4 = aadhMatch ? aadhMatch[1].replace(/\D/g, '').slice(-4) : '';

    const receiptEl = findReceiptElement();
    receiptHandling = true;

    // Auto-unlock after 6 seconds to prevent any permanent lock
    const safetyTimer = setTimeout(() => {
      receiptHandling = false;
    }, 6000);

    updateBadge(`📸 Processing Receipt (${couponNo || 'Form 93'})...`, '#38bdf8');

    // Scroll receipt smoothly into view
    if (receiptEl) {
      try {
        receiptEl.scrollIntoView({ behavior: 'auto', block: 'center', inline: 'center' });
      } catch (e) {}
    }

    setTimeout(() => {
      const rect = receiptEl ? receiptEl.getBoundingClientRect() : null;
      const dpr = window.devicePixelRatio || 1;

      const crop = rect ? {
        x: Math.max(0, Math.floor((rect.left - 4) * dpr)),
        y: Math.max(0, Math.floor((rect.top - 4) * dpr)),
        width: Math.min(window.innerWidth * dpr, Math.ceil((rect.width + 8) * dpr)),
        height: Math.min(window.innerHeight * dpr, Math.ceil((rect.height + 8) * dpr))
      } : null;

      // Request tab screenshot from background
      chrome.runtime.sendMessage({ action: 'captureTab' }, (res) => {
        clearTimeout(safetyTimer);
        // Even if captureTab returns no dataUrl, ALWAYS complete the candidate!
        if (!res || !res.dataUrl || !crop) {
          sendCompletionPayload('', couponNo, applicantName, mobileNo, aadhaarLast4);
          return;
        }

        const img = new Image();
        img.onload = () => {
          let croppedBase64 = '';
          try {
            const canvas = document.createElement('canvas');
            const targetW = Math.max(10, Math.min(crop.width, img.width - crop.x));
            const targetH = Math.max(10, Math.min(crop.height, img.height - crop.y));
            canvas.width = targetW;
            canvas.height = targetH;
            const ctx = canvas.getContext('2d');

            ctx.drawImage(
              img,
              crop.x,
              crop.y,
              targetW,
              targetH,
              0,
              0,
              targetW,
              targetH
            );
            croppedBase64 = canvas.toDataURL('image/png', 0.95);
          } catch (err) {
            console.warn('Canvas crop fallback to full snapshot:', err);
            croppedBase64 = res.dataUrl;
          }

          sendCompletionPayload(croppedBase64, couponNo, applicantName, mobileNo, aadhaarLast4);
        };

        img.onerror = () => {
          sendCompletionPayload('', couponNo, applicantName, mobileNo, aadhaarLast4);
        };

        img.src = res.dataUrl;
      });
    }, 500);
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
    const cleanName = String(candidateName || '').trim().toUpperCase();

    const rows = Array.from(document.querySelectorAll('table tr'));
    for (const row of rows) {
      const text = (row.innerText || '').toUpperCase();
      const matchesAppNo = cleanAppNo && cleanAppNo.length >= 6 && text.includes(cleanAppNo);
      const matchesName = cleanName && cleanName.length >= 3 && text.includes(cleanName);

      // Strict row check: Must match App No, or clean Name
      if (matchesAppNo || (cleanAppNo === '' && matchesName)) {
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

  async function fetchAndFormatPhoto(photoUrl, name) {
    const cleanUrl = String(photoUrl || '').replace(/welcome\.bihargo\.xyz/gi, 'bihargo.xyz');
    const imgRes = await fetch(cleanUrl);
    const blob = await imgRes.blob();
    const bmp = await createImageBitmap(blob);
    const canvas = document.createElement('canvas');
    canvas.width = 213;
    canvas.height = 213;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 213, 213);
    ctx.drawImage(bmp, 0, 0, 213, 213);
    const jpegBlob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.88));
    const safeName = (name || 'photo').replace(/[^a-zA-Z0-9]/g, '_');
    return new File([jpegBlob], `${safeName}_photo_213x213.jpg`, { type: 'image/jpeg' });
  }

  async function fetchAndFormatSignature(sigUrl, name) {
    const cleanUrl = String(sigUrl || '').replace(/welcome\.bihargo\.xyz/gi, 'bihargo.xyz');
    const imgRes = await fetch(cleanUrl);
    const blob = await imgRes.blob();
    const bmp = await createImageBitmap(blob);
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

    const jpegBlob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.85));
    const safeName = (name || 'signature').replace(/[^a-zA-Z0-9]/g, '_');
    return new File([jpegBlob], `${safeName}_signature_400x200.jpg`, { type: 'image/jpeg' });
  }

  function clickModalUploadButton(modal) {
    const allBtns = Array.from(modal.querySelectorAll('button, input[type="button"], input[type="submit"]'));
    const uploadBtn = allBtns.find((b) => {
      if (!isVisible(b) || b.disabled) return false;
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
      // 1. User-Provided Standard PAN Form PDF (assets/default_pan_form.pdf)
      const pdfUrl = chrome.runtime.getURL('assets/default_pan_form.pdf');
      const pdfRes = await fetch(pdfUrl);
      const pdfBlob = await pdfRes.blob();
      const panFormFile = new File([pdfBlob], 'PAN_Form_Alongwith_Documents.pdf', { type: 'application/pdf' });

      // 2. Candidate Photo from BiharGo (UTI 213x213 @ 300 DPI)
      const photoFile = await fetchAndFormatPhoto(cand.photo_url, cand.full_name);

      // 3. Candidate Signature from BiharGo (UTI 400x200 @ 600 DPI Fresh)
      const sigFile = await fetchAndFormatSignature(cand.signature_url, cand.full_name);

      // Set input files via DataTransfer
      const dt1 = new DataTransfer();
      dt1.items.add(panFormFile);
      inputs.panFormInput.files = dt1.files;
      inputs.panFormInput.dispatchEvent(new Event('change', { bubbles: true }));
      inputs.panFormInput.dispatchEvent(new Event('input', { bubbles: true }));

      const dt2 = new DataTransfer();
      dt2.items.add(photoFile);
      inputs.pictureInput.files = dt2.files;
      inputs.pictureInput.dispatchEvent(new Event('change', { bubbles: true }));
      inputs.pictureInput.dispatchEvent(new Event('input', { bubbles: true }));

      const dt3 = new DataTransfer();
      dt3.items.add(sigFile);
      inputs.signatureInput.files = dt3.files;
      inputs.signatureInput.dispatchEvent(new Event('change', { bubbles: true }));
      inputs.signatureInput.dispatchEvent(new Event('input', { bubbles: true }));

      updateBadge('✅ All 3 Files Attached • Clicking Upload...', '#10b981');

      setTimeout(() => {
        clickModalUploadButton(modal);
      }, 700);
    } catch (err) {
      docUploadLock = false;
      console.error('Document attach error:', err);
      updateBadge(`⚠️ Document attach error: ${err.message}`, '#f59e0b');
    }
  }

  function handleDocUploadListPage() {
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
          clickModalUploadButton(modal);
          return;
        }

        if (currentCandidate && currentCandidate.photo_url && currentCandidate.signature_url) {
          docUploadLock = true;
          attachDocumentsToModal(inputs, currentCandidate, modal);
          return;
        }
      }
      return;
    }

    // 3. Modal not open yet: Match row by Candidate App No & Name
    if (!currentCandidate) {
      updateBadge('📄 Document Upload List • Waiting for Candidate Data', '#f59e0b');
      return;
    }

    const appNo = String(currentUtiRefNo || currentCandidate.coupon_no || currentCandidate.app_ref || '').replace(/^U-/i, '').trim().toUpperCase();
    const candName = (currentCandidate.full_name || '').trim().toUpperCase();

    if (appNo && appNo === lastUploadedAppNo) {
      return;
    }

    const match = findCandidateUploadRow(appNo, candName);
    if (!match) {
      updateBadge(`🔍 Finding Row for App No: ${appNo || candName}...`, '#38bdf8');
      return;
    }

    docUploadLock = true;
    lastUploadedAppNo = match.cleanAppNo || appNo;
    updateBadge(`⚡ Clicking Upload for ${match.cleanName} (${match.cleanAppNo || 'Matched'})...`, '#10b981');

    setTimeout(() => {
      try {
        match.uploadBtn.focus();
        match.uploadBtn.click();
      } catch (e) {
        match.uploadBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }
      setTimeout(() => {
        docUploadLock = false;
      }, 1500);
    }, 400);
  }

  // -----------------------------------------------------------------------
  // Router & Observers
  // -----------------------------------------------------------------------
  function runAutoFill(force = false) {
    const url = (window.location.href || '').toLowerCase();

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
