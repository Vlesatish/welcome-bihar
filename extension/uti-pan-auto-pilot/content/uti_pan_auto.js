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
    if (el.value === str) return true;
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

  function selectOptionByText(selectEl, targetText, exactOnly = false) {
    if (!selectEl || !targetText) return false;
    const target = String(targetText).trim().toLowerCase();

    // Pass 1: Strict exact match (Prevents "Individual" matching "Body of Individual")
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

    // Pass 2: Fallback to starts-with or contains only if exact match is absent
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

  // Find input/select near a label text (works for Angular/Bootstrap/Material/table layouts)
  function findFieldByLabel(keywords, tagSelector = 'input, select') {
    const keys = Array.isArray(keywords) ? keywords : [keywords];
    const normKeys = keys.map((k) => k.toLowerCase());

    // 1. Direct attribute match (id, name, placeholder, formcontrolname, aria-label)
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

    // 2. Label / text node proximity match
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

    // Fallback: match label directly
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
        'position:fixed',
        'bottom:16px',
        'right:16px',
        'z-index:2147483647',
        'background:#0f172a',
        'color:#f8fafc',
        'border:1px solid #38bdf8',
        'border-radius:12px',
        'padding:10px 14px',
        'font-family:system-ui,-apple-system,sans-serif',
        'font-size:12px',
        'box-shadow:0 8px 24px rgba(0,0,0,0.35)',
        'min-width:260px',
        'max-width:340px'
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

      document.getElementById('wb-btn-refill').addEventListener('click', () => runAutoFill(true));
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
  }

  // -----------------------------------------------------------------------
  // PAGE 1: prePANForm (Physical Mode -> Individual -> Defence/NRI No -> Both PAN -> Submit)
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

    // 2. Status of the Applicant -> Strictly "Individual" (Never "Body of Individual")
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

    // Find the container block for defence/NRI
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

      // If not uniquely matched, in a standard Yes/No pair, [1] is always 'No'
      if (!noRadio && radiosInBlock.length >= 2) {
        noRadio = radiosInBlock[1];
      }

      if (noRadio) {
        clickRadio(noRadio);
        nriNoSelected = Boolean(noRadio.checked);
      }
    } else {
      // Fallback: search across all radios
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
      const submitBtn = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], a.btn')).find((b) => {
        if (!isVisible(b)) return false;
        const txt = (b.textContent || b.value || '').trim().toLowerCase();
        return txt === 'submit';
      });

      if (submitBtn && !prePanSubmitted) {
        prePanSubmitted = true;
        updateBadge('🚀 prePANForm ready! Auto-submitting...', '#10b981');
        setTimeout(() => {
          submitBtn.click();
        }, 350);
      }
    }
  }

  // -----------------------------------------------------------------------
  // PAGE 2: IndmainForm (Form No. 93)
  // -----------------------------------------------------------------------
  function dismissReferenceNoModal() {
    const modals = Array.from(document.querySelectorAll('.modal, [role="dialog"], .ui-dialog, .popup, div'));
    for (const m of modals) {
      if (!isVisible(m)) continue;
      const txt = (m.innerText || '').trim();
      if (!txt.includes('Reference Number') && !txt.includes('Please note the reference number')) continue;

      const match = txt.match(/Reference\s*Number\s*[:-]*\s*([A-Z0-9]{6,})/i);
      if (match && match[1] && currentUtiRefNo !== match[1]) {
        currentUtiRefNo = match[1];
        chrome.storage.local.set({ currentUtiRefNo });
      }

      const okBtn = Array.from(m.querySelectorAll('button, input[type="button"], a')).find((b) => {
        return isVisible(b) && (b.textContent || b.value || '').trim().toLowerCase() === 'ok';
      });
      if (okBtn) {
        okBtn.click();
        updateBadge(`✅ Ref No: ${currentUtiRefNo || 'captured'} | Modal closed`, '#10b981');
        return true;
      }
    }
    return false;
  }

  function handleIndMainForm(force = false) {
    dismissReferenceNoModal();

    if (!currentCandidate) {
      updateBadge('Waiting for Welcome Bihar queue data...', '#f59e0b');
      return;
    }

    // Step 1: Personal Information (First Name, Middle Name, Last Name, Gender)
    const { first, middle, last } = splitFullName(currentCandidate.full_name);

    const firstNameEl = findFieldByLabel(['first name', 'firstname', 'fname'], 'input[type="text"], input:not([type])');
    const middleNameEl = findFieldByLabel(['middle name', 'middlename', 'mname'], 'input[type="text"], input:not([type])');
    const lastNameEl = findFieldByLabel(['last name', 'lastname', 'lname', 'surname'], 'input[type="text"], input:not([type])');
    const genderEl = findFieldByLabel(['gender', 'sex'], 'select');

    let filledAny = false;

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
    if (genderEl && currentCandidate.gender) {
      if (selectOptionByText(genderEl, currentCandidate.gender)) {
        filledAny = true;
      }
    }

    if (filledAny) {
      updateBadge(`✍️ Personal Info filled: ${currentCandidate.full_name}`, '#10b981');
    } else {
      updateBadge(`Active: ${currentCandidate.full_name}`, '#38bdf8');
    }
  }

  // -----------------------------------------------------------------------
  // Router & Observers
  // -----------------------------------------------------------------------
  function runAutoFill(force = false) {
    const url = (window.location.href || '').toLowerCase();
    const bodyText = (document.body?.innerText || '').toLowerCase();

    if (url.includes('prepanform') || bodyText.includes('status of the applicant') || bodyText.includes('pan card for indian citizen')) {
      handlePrePanForm();
    } else if (url.includes('indmainform') || bodyText.includes('form no. 49a') || bodyText.includes('form 49a')) {
      handleIndMainForm(force);
    } else if (url.includes('csclogin')) {
      updateBadge('CSC Login Screen • Please sign in', '#fbbf24');
    } else {
      updateBadge('Ready on UTI PAN Portal', '#38bdf8');
    }
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
      runAutoFill(true);
    }
    if (changes.currentUtiRefNo) {
      currentUtiRefNo = changes.currentUtiRefNo.newValue || '';
    }
  });

  // Re-check periodically for Angular dynamic DOM & modals
  setInterval(() => {
    runAutoFill(false);
  }, 800);
})();
