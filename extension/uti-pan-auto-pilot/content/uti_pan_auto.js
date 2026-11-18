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
  // Floating Status Badge UI
  // -----------------------------------------------------------------------
  function updateBadge(statusText, color = '#0284c7') {
    if (!document.body) return;
    let host = document.getElementById('wb-uti-badge');
    if (!host) {
      host = document.createElement('div');
      host.id = 'wb-uti-badge';
      host.style.cssText = [
        'position:fixed !important',
        'bottom:16px !important',
        'right:16px !important',
        'top:auto !important',
        'left:auto !important',
        'width:300px !important',
        'max-width:320px !important',
        'height:auto !important',
        'max-height:220px !important',
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
          <strong style="color:#38bdf8;font-size:12px;">🪪 Welcome Bihar PAN Auto-Pilot</strong>
          <span id="wb-uti-ref" style="font-size:10px;color:#fbbf24;font-weight:700;"></span>
        </div>
        <div id="wb-uti-cand" style="font-size:11.5px;font-weight:600;color:#e2e8f0;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
          Waiting for queue data...
        </div>
        <div id="wb-uti-status" style="font-size:11px;color:#34d399;margin-bottom:8px;">Idle</div>
        <div style="display:flex;gap:6px;">
          <button id="wb-btn-refill" type="button" style="flex:1;padding:5px 8px;border:none;border-radius:6px;background:#0284c7;color:#fff;font-size:11px;font-weight:600;cursor:pointer;">🔄 Refill</button>
          <button id="wb-btn-complete" type="button" style="flex:1;padding:5px 8px;border:none;border-radius:6px;background:#10b981;color:#fff;font-size:11px;font-weight:600;cursor:pointer;">✅ Done</button>
          <button id="wb-btn-reject" type="button" style="flex:1;padding:5px 8px;border:none;border-radius:6px;background:#ef4444;color:#fff;font-size:11px;font-weight:600;cursor:pointer;">❌ Reject</button>
        </div>
      `;
      document.body.appendChild(host);

      document.getElementById('wb-btn-refill').addEventListener('click', () => {
        const u = (window.location.href || '').toLowerCase();
        if (u.includes('vlehome')) {
          clickApplicationForNewPanMenu();
        } else {
          aadhaarConfirmed = false;
          runAutoFill(true);
        }
      });
      document.getElementById('wb-btn-complete').addEventListener('click', () => {
        if (!currentCandidate) return;
        chrome.runtime.sendMessage({
          action: 'candidateCompleted',
          appId: currentCandidate.id,
          appRef: currentCandidate.app_ref,
          utiRefNo: currentUtiRefNo
        });
      });
      document.getElementById('wb-btn-reject').addEventListener('click', () => {
        if (!currentCandidate) return;
        const reason = prompt('Rejection reason:', 'Document mismatch / rejected on UTI portal');
        if (reason === null) return;
        chrome.runtime.sendMessage({
          action: 'candidateRejected',
          appId: currentCandidate.id,
          appRef: currentCandidate.app_ref,
          reason: reason.trim() || 'Rejected on UTI portal'
        });
      });
    }

    const candEl = document.getElementById('wb-uti-cand');
    const stEl = document.getElementById('wb-uti-status');
    const refEl = document.getElementById('wb-uti-ref');

    if (candEl) {
      candEl.textContent = currentCandidate
        ? `#${currentCandidate.app_ref} • ${currentCandidate.full_name}`
        : 'No active PAN candidate in queue';
    }
    if (stEl) {
      stEl.textContent = statusText;
      stEl.style.color = color;
    }
    if (refEl) {
      refEl.textContent = currentUtiRefNo ? `UTI: ${currentUtiRefNo}` : '';
    }

    const refillBtn = document.getElementById('wb-btn-refill');
    const u = (window.location.href || '').toLowerCase();
    if (refillBtn) {
      if (u.includes('vlehome')) {
        refillBtn.textContent = '🚀 Open Form 49A';
        refillBtn.style.background = '#10b981';
      } else {
        refillBtn.textContent = '🔄 Refill';
        refillBtn.style.background = '#0284c7';
      }
    }
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
      const okBtn = Array.from(errorDialog.querySelectorAll('.ui-dialog-buttonpane button, button, input[type="button"]')).find((b) => {
        const t = (b.textContent || b.value || '').trim().toLowerCase();
        return t === 'ok' || t === 'okay';
      });
      if (okBtn) {
        try {
          okBtn.click();
        } catch (e) {
          okBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        }
        handled = true;
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

    if (filledAny && !docsFilled && !contactFilled && !addressFilled) {
      updateBadge(`✍️ Personal Info Auto-Filled: ${currentCandidate.full_name}`, '#10b981');
    }
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
  // Router & Observers
  // -----------------------------------------------------------------------
  function runAutoFill(force = false) {
    const url = (window.location.href || '').toLowerCase();

    // 1. Form No. 93 / IndmainForm (Highest Priority)
    if (
      url.includes('indmainform') ||
      url.includes('panind.html') ||
      url.includes('mainformeditind') ||
      document.getElementById('firstName') ||
      document.getElementById('identityProofDoc') ||
      document.getElementById('resAddr1') ||
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
