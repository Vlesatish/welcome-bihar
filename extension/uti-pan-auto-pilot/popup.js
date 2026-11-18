document.addEventListener('DOMContentLoaded', () => {
  const DEFAULT_PORTAL_URL = 'https://bihargo.xyz';

  const queueCountBadge = document.getElementById('queueCountBadge');
  const autoPollToggle = document.getElementById('autoPollToggle');
  const pollStatusText = document.getElementById('pollStatusText');
  const candEmpty = document.getElementById('candEmpty');
  const candDetails = document.getElementById('candDetails');
  const cRef = document.getElementById('cRef');
  const cName = document.getElementById('cName');
  const cGenDob = document.getElementById('cGenDob');
  const cFather = document.getElementById('cFather');
  const cAadhaar = document.getElementById('cAadhaar');
  const cMobile = document.getElementById('cMobile');
  const portalUrlInput = document.getElementById('portalUrlInput');
  const btnSavePortal = document.getElementById('btnSavePortal');
  const btnCheckNow = document.getElementById('btnCheckNow');
  const btnOpenUti = document.getElementById('btnOpenUti');
  const btnOpenDocUpload = document.getElementById('btnOpenDocUpload');
  const btnMarkComplete = document.getElementById('btnMarkComplete');
  const btnMarkReject = document.getElementById('btnMarkReject');
  const statusMsg = document.getElementById('statusMsg');

  let activeCand = null;

  function setStatus(msg, color = '#34d399') {
    statusMsg.textContent = msg;
    statusMsg.style.color = color;
  }

  function renderState() {
    chrome.storage.local.get([
      'portalUrl',
      'autoPollEnabled',
      'lastQueueCount',
      'lastQueueError',
      'currentCandidate',
      'queueList'
    ], (res) => {
      portalUrlInput.value = res.portalUrl || DEFAULT_PORTAL_URL;
      autoPollToggle.checked = res.autoPollEnabled !== false;

      const count = res.lastQueueCount || 0;
      queueCountBadge.textContent = `Queue: ${count}`;

      if (res.lastQueueError) {
        pollStatusText.textContent = `Error: ${res.lastQueueError}`;
        pollStatusText.style.color = '#f87171';
      } else {
        pollStatusText.textContent = autoPollToggle.checked ? 'Auto-polling every 5s' : 'Paused';
        pollStatusText.style.color = autoPollToggle.checked ? '#34d399' : '#94a3b8';
      }

      activeCand = res.currentCandidate || (Array.isArray(res.queueList) && res.queueList[0]) || null;
      if (activeCand) {
        candEmpty.style.display = 'none';
        candDetails.style.display = 'block';
        cRef.textContent = activeCand.app_ref || `#${activeCand.id}`;
        cName.textContent = activeCand.full_name || '-';
        cGenDob.textContent = `${activeCand.gender || '-'} / ${activeCand.dob || '-'}`;
        cFather.textContent = activeCand.father_name || '-';
        cAadhaar.textContent = activeCand.aadhaar_no || '-';
        cMobile.textContent = activeCand.mobile_no || '-';
      } else {
        candEmpty.style.display = 'block';
        candDetails.style.display = 'none';
      }

      if (res.lastStatus) {
        setStatus(res.lastStatus, res.lastStatusColor || '#38bdf8');
      }
    });
  }

  renderState();
  chrome.storage.onChanged.addListener(() => renderState());

  autoPollToggle.addEventListener('change', () => {
    chrome.storage.local.set({ autoPollEnabled: autoPollToggle.checked }, () => {
      renderState();
      if (autoPollToggle.checked) {
        chrome.runtime.sendMessage({ action: 'checkQueueNow', force: false });
      }
    });
  });

  btnSavePortal.addEventListener('click', () => {
    const url = (portalUrlInput.value || '').trim().replace(/\/+$/, '') || DEFAULT_PORTAL_URL;
    chrome.storage.local.set({ portalUrl: url }, () => {
      setStatus('Portal URL saved ✅');
      chrome.runtime.sendMessage({ action: 'checkQueueNow', force: false });
    });
  });

  btnCheckNow.addEventListener('click', () => {
    setStatus('Checking queue...', '#38bdf8');
    chrome.runtime.sendMessage({ action: 'checkQueueNow', force: false }, (res) => {
      if (res && res.success) {
        setStatus(`Queue checked (${res.count} pending) ✅`);
      } else {
        setStatus(res?.error || 'Check failed', '#f87171');
      }
      renderState();
    });
  });

  btnOpenUti.addEventListener('click', () => {
    chrome.runtime.sendMessage({ action: 'launchCandidate', candidate: activeCand }, () => {
      setStatus('Opening vleHome.action... 🚀', '#38bdf8');
    });
  });

  btnOpenDocUpload.addEventListener('click', () => {
    setStatus('Opening UTI Document Upload Page... 📂', '#0ea5e9');
    chrome.runtime.sendMessage({ action: 'openDocUploadPage' });
  });

  btnMarkComplete.addEventListener('click', () => {
    if (!activeCand) return;
    chrome.runtime.sendMessage({
      action: 'candidateCompleted',
      appId: activeCand.id,
      appRef: String(activeCand.app_ref || '').replace(/^#+/, '').trim()
    }, (res) => {
      setStatus(res?.success ? 'Marked Completed ✅' : (res?.error || 'Failed'), res?.success ? '#34d399' : '#f87171');
    });
  });

  btnMarkReject.addEventListener('click', () => {
    if (!activeCand) return;
    const reason = prompt('Rejection reason:', 'Rejected from UTI PAN Auto-Pilot');
    if (reason === null) return;
    chrome.runtime.sendMessage({
      action: 'candidateRejected',
      appId: activeCand.id,
      appRef: activeCand.app_ref,
      reason: reason.trim() || 'Rejected from extension'
    }, (res) => {
      setStatus(res?.success ? 'Marked Rejected & Refunded ✅' : (res?.error || 'Failed'), res?.success ? '#34d399' : '#f87171');
    });
  });
});
