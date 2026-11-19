// =========================================================================
// Welcome Bihar - UTI PAN Auto-Pilot (Background Service Worker, MV3)
// ONLY ONE LINK: https://www.myutiitsl.com/panonlineservices/vleHome.action
// =========================================================================

const DEFAULT_PORTAL_URL = 'https://bihargo.xyz';
const VLE_HOME_URL = 'https://www.myutiitsl.com/panonlineservices/vleHome.action';
const DOC_UPLOAD_URL = 'https://www.myutiitsl.com/panonlineservices/docUploadListPanApp?applType=B';
const QUEUE_ALARM_NAME = 'WB_UTI_PAN_QUEUE_WATCH';

const VLE_STANDBY_REFRESH_ALARM = 'WB_UTI_VLEHOME_REFRESH_10M';

let isCycleLaunching = false;
let activeCandidateKey = null;
let lastLaunchedCandKey = null;
let activeOtpUtiTabId = null;
let createdGmailTabId = null;
let lastLogoutRestartAt = 0;

function getTabIfAlive(tabId) {
  return new Promise((resolve) => {
    if (!tabId) {
      resolve(null);
      return;
    }
    chrome.tabs.get(tabId, (tab) => {
      if (chrome.runtime.lastError || !tab || !tab.id) {
        resolve(null);
      } else {
        resolve(tab);
      }
    });
  });
}

async function ensureStandbyVleTab(allowCreate = false) {
  const stored = await new Promise((r) => chrome.storage.local.get(['standbyVleTabId', 'activeEntryVleTabId'], r));
  const savedStandbyId = stored?.standbyVleTabId || null;
  const savedEntryId = stored?.activeEntryVleTabId || null;

  if (savedStandbyId) {
    const tab = await getTabIfAlive(savedStandbyId);
    const u = (tab?.url || tab?.pendingUrl || '').toLowerCase();
    if (tab && u.includes('vlehome')) {
      return { tab, created: false };
    }
  }

  const vleTabs = await getVleHomeTabs();
  const adoptable = vleTabs.find((t) => t.id !== savedEntryId) || null;
  if (adoptable) {
    await chrome.storage.local.set({ standbyVleTabId: adoptable.id });
    return { tab: adoptable, created: false };
  }

  if (!allowCreate) {
    return { tab: null, created: false };
  }

  const cfg = await getConfig();
  if (!cfg.autoPollEnabled) {
    return { tab: null, created: false };
  }

  // Do not interrupt active CSC login / OTP verification flow
  const allTabs = await new Promise((resolve) => chrome.tabs.query({}, (tabs) => {
    void chrome.runtime.lastError;
    resolve(tabs || []);
  }));
  const isAuthFlowOpen = allTabs.some((t) => {
    const u = (t?.url || t?.pendingUrl || '').toLowerCase();
    return u.includes('csclogin') || u.includes('logincheckotp') || u.includes('connect.csc.gov.in');
  });
  if (isAuthFlowOpen) {
    return { tab: null, created: false };
  }

  const createdTab = await new Promise((resolve) => {
    chrome.tabs.create({ url: VLE_HOME_URL, active: false }, (tab) => {
      void chrome.runtime.lastError;
      resolve(tab || null);
    });
  });
  if (createdTab && createdTab.id) {
    await chrome.storage.local.set({ standbyVleTabId: createdTab.id });
  }
  return { tab: createdTab, created: true };
}

async function refreshStandbyVleTab() {
  try {
    const allTabs = await new Promise((resolve) => chrome.tabs.query({}, (tabs) => {
      void chrome.runtime.lastError;
      resolve(tabs || []);
    }));
    const isAuthFlowOpen = allTabs.some((t) => {
      const u = (t?.url || t?.pendingUrl || '').toLowerCase();
      return u.includes('csclogin') || u.includes('logincheckotp') || u.includes('connect.csc.gov.in');
    });
    if (isAuthFlowOpen) return;

    const { tab, created } = await ensureStandbyVleTab(true);
    if (!tab || !tab.id || created) return;

    const curUrl = (tab.url || tab.pendingUrl || '').toLowerCase();
    if (curUrl.includes('vlehome')) {
      chrome.tabs.reload(tab.id, { bypassCache: false }, () => void chrome.runtime.lastError);
    } else {
      chrome.tabs.update(tab.id, { url: VLE_HOME_URL, active: false }, () => void chrome.runtime.lastError);
    }
    await chrome.storage.local.set({ lastStandbyRefreshAt: Date.now() });
  } catch (err) {
    console.error('[Background] Standby vleHome refresh error:', err);
  }
}

async function closeAllIdleUtiTabs(options = {}) {
  const includeDocUpload = Boolean(options && options.includeDocUpload);
  const { tab: standbyTab } = await ensureStandbyVleTab(false);
  const standbyId = standbyTab && standbyTab.id ? standbyTab.id : null;
  const allUtiTabs = await getAllUtiTabs();
  const idsToClose = allUtiTabs
    .filter((t) => {
      if (!t || !t.id) return false;
      // Keep the single persistent standby vleHome.action tab open
      if (standbyId && t.id === standbyId) return false;
      const u = (t.url || t.pendingUrl || '').toLowerCase();
      // Do not close if user is actively on CSC Login or OTP verification screen
      if (u.includes('logincheckotp') || u.includes('csclogin')) return false;
      // Protect Document Upload tabs until upload finishes or table is confirmed empty
      if (!includeDocUpload && (u.includes('docuploadlistpanapp') || u.includes('docupload'))) return false;
      return true;
    })
    .map((t) => t.id);

  if (idsToClose.length > 0) {
    chrome.tabs.remove(idsToClose, () => void chrome.runtime.lastError);
  }
}

function getConfig() {
  return new Promise((resolve) => {
    chrome.storage.local.get(['portalUrl', 'autoPollEnabled'], (res) => {
      const rawUrl = (res && res.portalUrl ? res.portalUrl : DEFAULT_PORTAL_URL).trim().replace(/\/+$/, '');
      resolve({
        portalUrl: rawUrl || DEFAULT_PORTAL_URL,
        autoPollEnabled: res.autoPollEnabled !== false
      });
    });
  });
}

function getVleHomeTabs() {
  return new Promise((resolve) => {
    chrome.tabs.query({}, (allTabs) => {
      void chrome.runtime.lastError;
      const matched = (allTabs || []).filter((t) => {
        const u = (t.url || t.pendingUrl || '').toLowerCase();
        return u.includes('vlehome');
      });
      resolve(matched);
    });
  });
}

function getAllUtiTabs() {
  return new Promise((resolve) => {
    chrome.tabs.query({}, (allTabs) => {
      void chrome.runtime.lastError;
      const matched = (allTabs || []).filter((t) => {
        const u = (t.url || t.pendingUrl || '').toLowerCase();
        return u.includes('myutiitsl.com');
      });
      resolve(matched);
    });
  });
}

let lastDocUploadTriggerKey = '';
let lastDocUploadTriggerTime = 0;

async function openDocUploadAndCloseSuccess(successTabId, triggerKey = '') {
  const now = Date.now();
  if (triggerKey && triggerKey === lastDocUploadTriggerKey && (now - lastDocUploadTriggerTime < 8000)) {
    console.log(`[Background] Deduplicated openDocUpload request for ${triggerKey}`);
    return;
  }
  if (triggerKey) {
    lastDocUploadTriggerKey = triggerKey;
    lastDocUploadTriggerTime = now;
  }

  await chrome.storage.local.set({
    awaitingDocUpload: true,
    awaitingDocUploadAt: now
  });

  const allTabs = await new Promise((resolve) => chrome.tabs.query({}, resolve));
  const matched = (allTabs || []).filter((t) => {
    const u = (t.url || t.pendingUrl || '').toLowerCase();
    return u.includes('docuploadlistpanapp') || u.includes('docupload');
  });

  if (matched.length > 0) {
    const targetTab = matched[0];
    const curUrl = (targetTab.url || targetTab.pendingUrl || '').toLowerCase();
    // Tab already exists! Only activate/focus it, NEVER reset the URL if already on docUploadList
    if (curUrl.includes('docuploadlistpanapp') || curUrl.includes('docupload')) {
      await chrome.tabs.update(targetTab.id, { active: true });
    } else {
      await chrome.tabs.update(targetTab.id, { url: DOC_UPLOAD_URL, active: true });
    }
    if (successTabId && successTabId !== targetTab.id) {
      chrome.tabs.remove(successTabId, () => void chrome.runtime.lastError);
    }
  } else {
    if (successTabId) {
      chrome.tabs.update(successTabId, { url: DOC_UPLOAD_URL, active: true });
    } else {
      chrome.tabs.create({ url: DOC_UPLOAD_URL, active: true });
    }
  }
}

async function closeErrorTabs() {
  const allTabs = await getAllUtiTabs();
  const errorTabs = allTabs.filter((t) => {
    const u = (t.url || t.pendingUrl || '').toLowerCase();
    return u.includes('error') || u.includes('something+went+wrong');
  });
  const ids = errorTabs.map((t) => t.id).filter(Boolean);
  if (ids.length > 0) {
    chrome.tabs.remove(ids, () => void chrome.runtime.lastError);
  }
}

async function openVleHomeOnly(cand) {
  if (isCycleLaunching) return;
  isCycleLaunching = true;
  try {
    if (cand) {
      await chrome.storage.local.set({
        currentCandidate: cand,
        currentUtiRefNo: ''
      });
    }

    // Close any dead error tabs first
    await closeErrorTabs();

    // Keep persistent standby vleHome tab untouched; open or reuse a separate entry tab for Form 49A
    const { tab: standbyTab } = await ensureStandbyVleTab(false);
    const standbyId = standbyTab && standbyTab.id ? standbyTab.id : null;
    const vleTabs = await getVleHomeTabs();
    const existingEntryTab = vleTabs.find((t) => !standbyId || t.id !== standbyId) || null;

    if (!existingEntryTab) {
      const createdEntryTab = await new Promise((resolve) => {
        chrome.tabs.create({ url: VLE_HOME_URL, active: true }, (tab) => {
          void chrome.runtime.lastError;
          resolve(tab || null);
        });
      });
      if (createdEntryTab && createdEntryTab.id) {
        await chrome.storage.local.set({ activeEntryVleTabId: createdEntryTab.id });
      }
    } else {
      await chrome.storage.local.set({ activeEntryVleTabId: existingEntryTab.id });
      chrome.tabs.update(existingEntryTab.id, { url: VLE_HOME_URL, active: true }, () => void chrome.runtime.lastError);
    }
  } finally {
    setTimeout(() => {
      isCycleLaunching = false;
    }, 2000);
  }
}

async function checkQueueAndLaunch(forceRestart = false) {
  if (isCycleLaunching) return { success: false, reason: 'launching' };

  const cfg = await getConfig();
  if (!cfg.autoPollEnabled && !forceRestart) {
    return { success: false, reason: 'paused' };
  }

  try {
    const res = await fetch(`${cfg.portalUrl}/api/pan-card/queue`, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    if (!res.ok) {
      const errText = await res.text();
      let errMsg = `HTTP ${res.status}`;
      try {
        const errJson = JSON.parse(errText);
        if (errJson && errJson.error) errMsg = errJson.error;
      } catch (e) {
        errMsg = `HTTP ${res.status} (Endpoint returned HTML instead of JSON)`;
      }
      throw new Error(errMsg);
    }
    const json = await res.json();
    const queue = json && json.success && Array.isArray(json.data) ? json.data : [];

    await chrome.storage.local.set({
      lastQueueCheckAt: Date.now(),
      lastQueueCount: queue.length,
      lastQueueError: '',
      queueList: queue
    });

    const stored = await new Promise((r) => chrome.storage.local.get(['awaitingDocUpload', 'awaitingDocUploadAt', 'isWaitingForOtp'], r));

    if (queue.length === 0) {
      // Only keep tab open if actively awaiting immediate document upload right after form submission (max 3 min)
      if (stored && stored.isWaitingForOtp) {
        return { success: true, count: 0, inFlight: true };
      }
      if (stored && stored.awaitingDocUpload) {
        const elapsed = Date.now() - Number(stored.awaitingDocUploadAt || 0);
        if (elapsed < 180000) {
          return { success: true, count: 0, inFlight: true };
        }
      }
      activeCandidateKey = null;
      lastLaunchedCandKey = null;
      await chrome.storage.local.remove(['currentCandidate', 'currentUtiRefNo', 'completedReceiptNo', 'awaitingDocUpload', 'awaitingDocUploadAt', 'activeEntryVleTabId']);
      await chrome.storage.local.set({
        lastStatus: 'Idle • Standby VLE Home active (10m refresh) | Waiting for queue...',
        lastStatusColor: '#94a3b8'
      });
      await ensureStandbyVleTab(false);
      await closeAllIdleUtiTabs();
      return { success: true, count: 0 };
    }

    // Do not steal focus / overwrite candidate while document upload for just-finished PAN is actively running (< 60s)
    if (!forceRestart && stored && stored.awaitingDocUpload) {
      const uploadElapsed = Date.now() - Number(stored.awaitingDocUploadAt || 0);
      if (uploadElapsed < 60000) {
        return { success: true, count: queue.length, waitingForDocUpload: true };
      }
    }

    const firstCand = queue[0];
    const candKey = String(firstCand.app_ref || firstCand.id);
    activeCandidateKey = candKey;

    await chrome.storage.local.set({
      currentCandidate: firstCand,
      lastStatus: `🚀 Processing: ${firstCand.full_name || 'Candidate'} (${firstCand.app_ref || firstCand.id})`,
      lastStatusColor: '#38bdf8'
    });

    // Naya data detect hone par ya force button dabane par vleHome open karo
    if (forceRestart || lastLaunchedCandKey !== candKey) {
      lastLaunchedCandKey = candKey;
      await openVleHomeOnly(firstCand);
    }

    return { success: true, count: queue.length, active: firstCand };
  } catch (err) {
    await chrome.storage.local.set({
      lastQueueCheckAt: Date.now(),
      lastQueueError: err.message || 'Connection failed'
    });
    return { success: false, error: err.message };
  }
}

async function postPortalAction(endpoint, payload) {
  const cfg = await getConfig();
  const res = await fetch(`${cfg.portalUrl}${endpoint}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload || {})
  });
  return res.json();
}

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (!msg || !msg.action) return false;

  if (msg.action === 'checkQueueNow') {
    checkQueueAndLaunch(Boolean(msg.force)).then((r) => sendResponse(r));
    return true;
  }

  if (msg.action === 'launchCandidate') {
    (async () => {
      const cand = msg.candidate || null;
      if (cand) {
        activeCandidateKey = String(cand.app_ref || cand.id);
        lastLaunchedCandKey = activeCandidateKey;
        await openVleHomeOnly(cand);
        sendResponse({ success: true });
      } else {
        const r = await checkQueueAndLaunch(true);
        sendResponse(r);
      }
    })();
    return true;
  }

  if (msg.action === 'captureTab') {
    (async () => {
      try {
        const tabId = sender.tab ? sender.tab.id : null;
        const winId = sender.tab ? sender.tab.windowId : null;

        if (winId) {
          await new Promise((res) => chrome.windows.update(winId, { focused: true }, () => {
            void chrome.runtime.lastError;
            res();
          }));
        }
        if (tabId) {
          await new Promise((res) => chrome.tabs.update(tabId, { active: true }, () => {
            void chrome.runtime.lastError;
            res();
          }));
        }

        // Wait for tab paint after focusing
        await new Promise((res) => setTimeout(res, 300));

        let dataUrl = await new Promise((resolve) => {
          if (typeof winId === 'number') {
            chrome.tabs.captureVisibleTab(winId, { format: 'png' }, (d) => {
              if (chrome.runtime.lastError || !d) {
                chrome.tabs.captureVisibleTab({ format: 'png' }, (d2) => {
                  void chrome.runtime.lastError;
                  resolve(d2 || null);
                });
              } else {
                resolve(d);
              }
            });
          } else {
            chrome.tabs.captureVisibleTab({ format: 'png' }, (d) => {
              void chrome.runtime.lastError;
              resolve(d || null);
            });
          }
        });

        // Retry once if initially null
        if (!dataUrl) {
          await new Promise((res) => setTimeout(res, 400));
          dataUrl = await new Promise((resolve) => {
            chrome.tabs.captureVisibleTab({ format: 'png' }, (d3) => {
              void chrome.runtime.lastError;
              resolve(d3 || null);
            });
          });
        }

        console.log('[Background] captureTab completed. Size:', dataUrl ? dataUrl.length : 0);
        sendResponse({ success: Boolean(dataUrl), dataUrl: dataUrl || null });
      } catch (e) {
        console.error('[Background] captureTab exception:', e);
        sendResponse({ success: false, dataUrl: null, error: e.message });
      }
    })();
    return true;
  }

  if (msg.action === 'candidateCompleted') {
    (async () => {
      try {
        const stored = await new Promise((resolve) => {
          chrome.storage.local.get(['currentCandidate', 'queueList', 'currentUtiRefNo'], resolve);
        });

        const cand = stored.currentCandidate || {};
        const appId = msg.appId || cand.id || cand.app_id;
        const rawRef = msg.appRef || cand.app_ref || (cand.id ? `WB-${cand.id}` : '');
        const appRef = String(rawRef || '').replace(/^#+/, '').trim();
        const utiRef = msg.utiRefNo || stored.currentUtiRefNo || '';

        const data = await postPortalAction('/api/pan-card/complete', {
          app_id: appId,
          app_ref: appRef,
          applicant_name: msg.applicantName || cand.full_name || '',
          mobile_no: msg.mobileNo || cand.mobile_no || '',
          aadhaar_no: msg.aadhaarNo || cand.aadhaar_no || '',
          uti_ref_no: utiRef,
          remarks: msg.remarks || `UTI PAN Form 93 Completed. Coupon: ${utiRef}`,
          screenshot: msg.screenshot || msg.result_file_base64 || '',
          result_file_base64: msg.result_file_base64 || msg.screenshot || ''
        });

        if (data && data.success) {
          activeCandidateKey = null;
          lastLaunchedCandKey = null;
          // Completed candidate ko storage se remove karo, par awaitingDocUpload true rakho jab tak docUpload poora na ho
          await chrome.storage.local.remove(['currentCandidate', 'currentUtiRefNo', 'completedReceiptNo']);
          await chrome.storage.local.set({
            awaitingDocUpload: true,
            awaitingDocUploadAt: Date.now(),
            lastStatus: `✅ Candidate Completed & Receipt Attached (${utiRef || 'Done'})`,
            lastStatusColor: '#10b981'
          });

          sendResponse({ success: true, data });

          // Auto-close and proceed only if autoClose is true
          if (msg.autoClose !== false) {
            const successTabId = sender && sender.tab ? sender.tab.id : null;
            const triggerKey = String(appRef || appId || utiRef || 'cand_completed');
            setTimeout(() => {
              openDocUploadAndCloseSuccess(successTabId, triggerKey);
            }, 1200);
          }

          // Re-check queue: agar koi naya application aaya hoga toh turant auto-start hoga
          setTimeout(() => {
            checkQueueAndLaunch(false);
          }, 2000);
        } else {
          sendResponse({ success: false, data, error: data?.error || 'Portal update failed' });
        }
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'attachReceipt') {
    (async () => {
      try {
        const data = await postPortalAction('/api/pan-card/attach-receipt', {
          app_id: msg.appId,
          app_ref: msg.appRef,
          applicant_name: msg.applicantName || '',
          mobile_no: msg.mobileNo || '',
          aadhaar_no: msg.aadhaarNo || '',
          uti_ref_no: msg.utiRefNo || '',
          screenshot: msg.screenshot || msg.result_file_base64 || ''
        });
        sendResponse({ success: Boolean(data && data.success), data, error: data?.error });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'fetchAssetData') {
    (async () => {
      try {
        let cleanUrl = String(msg.url || '').trim();
        let isAsset = false;
        if (cleanUrl.startsWith('assets/')) {
          cleanUrl = chrome.runtime.getURL(cleanUrl);
          isAsset = true;
        } else {
          cleanUrl = cleanUrl.replace(/^https?:\/\/welcome\.bihargo\.xyz/i, 'https://www.bihargo.xyz');
          cleanUrl = cleanUrl.replace(/^https?:\/\/localhost(:\d+)?/i, 'https://www.bihargo.xyz');
          if (!cleanUrl.startsWith('http://') && !cleanUrl.startsWith('https://')) {
            const cfg = await getConfig();
            const base = (cfg.portalUrl || 'https://www.bihargo.xyz').replace(/\/+$/, '');
            cleanUrl = `${base}/${cleanUrl.replace(/^\/+/, '')}`;
          }
        }

        const fetchOpts = isAsset ? {} : { cache: 'no-cache' };
        const res = await fetch(cleanUrl, fetchOpts);
        if (!res.ok) throw new Error(`HTTP ${res.status} when fetching ${cleanUrl}`);

        const buf = await res.arrayBuffer();
        const bytes = new Uint8Array(buf);

        // Sanity check: Ensure not 404/500 HTML error page
        if (bytes.length > 10 && bytes[0] === 0x3C && (bytes[1] === 0x21 || bytes[1] === 0x68 || bytes[1] === 0x48)) {
          throw new Error(`File at ${cleanUrl} returned HTML error page instead of binary media`);
        }

        let binary = '';
        const len = bytes.byteLength;
        const chunkSize = 8192;
        for (let i = 0; i < len; i += chunkSize) {
          binary += String.fromCharCode.apply(null, bytes.subarray(i, Math.min(i + chunkSize, len)));
        }
        const b64 = btoa(binary);
        const mime = res.headers.get('content-type') || (cleanUrl.endsWith('.pdf') ? 'application/pdf' : 'image/jpeg');
        sendResponse({ success: true, base64: b64, mime });
      } catch (e) {
        sendResponse({ success: false, error: e.message });
      }
    })();
    return true;
  }

  if (msg.action === 'openDocUploadPage') {
    const tabId = sender && sender.tab ? sender.tab.id : null;
    const triggerKey = String(msg.appRef || msg.appId || msg.utiRefNo || 'manual_open');
    chrome.storage.local.set({
      awaitingDocUpload: true,
      awaitingDocUploadAt: Date.now()
    }, () => {
      openDocUploadAndCloseSuccess(tabId, triggerKey);
      sendResponse({ success: true });
    });
    return true;
  }

  if (msg.action === 'docUploadIdleOrDone') {
    (async () => {
      await chrome.storage.local.remove(['awaitingDocUpload', 'awaitingDocUploadAt']);
      await ensureStandbyVleTab(true);
      const r = await checkQueueAndLaunch(true);
      if (r && r.success && r.count === 0) {
        await closeAllIdleUtiTabs({ includeDocUpload: true });
      }
      sendResponse({ success: true, result: r });
    })();
    return true;
  }

  if (msg.action === 'candidateRejected') {
    (async () => {
      try {
        const data = await postPortalAction('/api/pan-card/reject', {
          app_id: msg.appId,
          app_ref: msg.appRef,
          reason: msg.reason || 'Rejected from UTI PAN Auto-Pilot'
        });
        activeCandidateKey = null;
        lastLaunchedCandKey = null;
        await chrome.storage.local.remove(['currentCandidate', 'currentUtiRefNo']);
        setTimeout(() => checkQueueAndLaunch(true), 1000);
        sendResponse({ success: Boolean(data && data.success), data });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'closeCurrentTab') {
    const tabId = sender.tab ? sender.tab.id : null;
    if (tabId) {
      chrome.tabs.remove(tabId, () => {
        void chrome.runtime.lastError;
      });
    }
    sendResponse({ success: true });
    return true;
  }

  if (msg.action === 'restartAfterLogout') {
    (async () => {
      try {
        const now = Date.now();
        if (now - lastLogoutRestartAt < 3000) {
          sendResponse({ success: true, deduplicated: true });
          return;
        }
        lastLogoutRestartAt = now;

        const senderTabId = sender && sender.tab ? sender.tab.id : null;
        const storedIds = await new Promise((r) => chrome.storage.local.get(['standbyVleTabId', 'activeEntryVleTabId'], r));
        if (senderTabId && storedIds?.standbyVleTabId === senderTabId) {
          await chrome.storage.local.remove(['standbyVleTabId']);
        }
        if (senderTabId && storedIds?.activeEntryVleTabId === senderTabId) {
          await chrome.storage.local.remove(['activeEntryVleTabId']);
        }
        const allUti = await getAllUtiTabs();
        const idsToRemove = new Set();
        if (senderTabId) idsToRemove.add(senderTabId);
        for (const t of allUti) {
          const u = (t.url || t.pendingUrl || '').toLowerCase();
          if (t.id && (u.includes('editvle') || u.includes('logout') || u.includes('error'))) {
            idsToRemove.add(t.id);
          }
        }

        activeCandidateKey = null;
        lastLaunchedCandKey = null;
        isCycleLaunching = false;
        await chrome.storage.local.remove(['isWaitingForOtp', 'otpRequestedAt']);

        // Launch fresh PAN entry tab FIRST (so Chrome never closes a 1-tab window before opening vleHome)
        const queueRes = await checkQueueAndLaunch(true);
        if (!queueRes || !queueRes.success) {
          const stored = await new Promise((r) => chrome.storage.local.get(['currentCandidate'], r));
          if (stored && stored.currentCandidate) {
            await openVleHomeOnly(stored.currentCandidate);
          }
        }

        // Now close the logged-out / editVle tab(s)
        if (idsToRemove.size > 0) {
          chrome.tabs.remove(Array.from(idsToRemove), () => {
            void chrome.runtime.lastError;
          });
        }

        sendResponse({ success: true, queueResult: queueRes });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'openCscLoginTab') {
    (async () => {
      try {
        const cscLoginUrl = 'https://www.myutiitsl.com/panonlineservices/CSCLogin';
        const senderTabId = sender.tab ? sender.tab.id : null;
        if (senderTabId) {
          chrome.tabs.update(senderTabId, { url: cscLoginUrl, active: true }, () => {
            void chrome.runtime.lastError;
          });
        } else {
          const allTabs = await getAllUtiTabs();
          if (allTabs.length > 0) {
            chrome.tabs.update(allTabs[0].id, { url: cscLoginUrl, active: true }, () => {
              void chrome.runtime.lastError;
            });
          } else {
            chrome.tabs.create({ url: cscLoginUrl, active: true });
          }
        }
        sendResponse({ success: true });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'fetchGmailOtp') {
    (async () => {
      try {
        const triggerTime = Number(msg.triggerTime) || Date.now();
        activeOtpUtiTabId = sender.tab ? sender.tab.id : null;
        await chrome.storage.local.set({ isWaitingForOtp: true, otpRequestedAt: triggerTime });

        const allTabs = await new Promise((r) => chrome.tabs.query({}, r));
        const gmailTabs = (allTabs || []).filter((t) => {
          const u = (t.url || t.pendingUrl || '').toLowerCase();
          return u.includes('mail.google.com');
        });

        const targetGmailUrl = 'https://mail.google.com/mail/u/0/#inbox';

        if (gmailTabs.length > 0) {
          createdGmailTabId = null; // Do not close pre-existing tab
          const gTab = gmailTabs[0];
          await chrome.tabs.update(gTab.id, { url: targetGmailUrl, active: true });
          setTimeout(() => {
            chrome.tabs.sendMessage(gTab.id, { action: 'readOtpNow', triggerTime }, () => {
              void chrome.runtime.lastError;
            });
          }, 1200);
        } else {
          const newTab = await new Promise((r) => {
            chrome.tabs.create({ url: targetGmailUrl, active: true }, r);
          });
          createdGmailTabId = newTab ? newTab.id : null;
          setTimeout(() => {
            if (createdGmailTabId) {
              chrome.tabs.sendMessage(createdGmailTabId, { action: 'readOtpNow', triggerTime }, () => {
                void chrome.runtime.lastError;
              });
            }
          }, 1800);
        }

        sendResponse({ success: true });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'otpExtracted') {
    (async () => {
      try {
        const code = String(msg.code || '').trim();
        await chrome.storage.local.remove(['isWaitingForOtp']);

        // 1. Switch back to UTI tab and submit OTP
        if (activeOtpUtiTabId) {
          await new Promise((r) => chrome.tabs.update(activeOtpUtiTabId, { active: true }, r));
          setTimeout(() => {
            chrome.tabs.sendMessage(activeOtpUtiTabId, { action: 'fillOtpAndSubmit', code }, () => {
              void chrome.runtime.lastError;
            });
          }, 300);
        } else {
          // If tab id was lost, find any UTI tab on loginCheckOtpRedirect
          const allTabs = await getAllUtiTabs();
          const otpTab = allTabs.find((t) => (t.url || '').toLowerCase().includes('logincheckotpredirect'));
          if (otpTab) {
            await chrome.tabs.update(otpTab.id, { active: true });
            setTimeout(() => {
              chrome.tabs.sendMessage(otpTab.id, { action: 'fillOtpAndSubmit', code }, () => {
                void chrome.runtime.lastError;
              });
            }, 300);
          }
        }

        // 2. Close temporary Gmail tab if we created it
        if (createdGmailTabId) {
          setTimeout(() => {
            chrome.tabs.remove(createdGmailTabId, () => void chrome.runtime.lastError);
            createdGmailTabId = null;
          }, 2500);
        }

        sendResponse({ success: true });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'getVleHomeRole') {
    (async () => {
      try {
        const senderTabId = sender && sender.tab ? sender.tab.id : null;
        const stored = await new Promise((r) => chrome.storage.local.get(['standbyVleTabId', 'activeEntryVleTabId', 'currentCandidate', 'queueList'], r));
        const hasCandidate = Boolean(stored?.currentCandidate || (Array.isArray(stored?.queueList) && stored.queueList.length > 0));
        const cand = stored?.currentCandidate || (Array.isArray(stored?.queueList) && stored.queueList[0]) || null;

        // If this tab was explicitly launched as an Entry tab (and queue has work), treat as Entry tab
        if (senderTabId && stored?.activeEntryVleTabId === senderTabId && hasCandidate) {
          sendResponse({ isStandby: false, hasCandidate: true });
          return;
        }

        // Check if saved standby tab is still alive on vleHome
        let standbyTab = null;
        if (stored?.standbyVleTabId) {
          const alive = await getTabIfAlive(stored.standbyVleTabId);
          const aliveUrl = (alive?.url || alive?.pendingUrl || '').toLowerCase();
          if (alive && aliveUrl.includes('vlehome')) {
            standbyTab = alive;
          }
        }

        if (!standbyTab && senderTabId) {
          // Adopt this vleHome tab as the single persistent standby tab
          await chrome.storage.local.set({ standbyVleTabId: senderTabId });
          if (stored?.activeEntryVleTabId === senderTabId) {
            await chrome.storage.local.remove(['activeEntryVleTabId']);
          }
          // If a queued candidate was waiting (e.g. user just logged in), open an Entry tab alongside standby
          if (hasCandidate && cand && !isCycleLaunching) {
            setTimeout(() => {
              openVleHomeOnly(cand);
            }, 300);
          }
          sendResponse({ isStandby: true, hasCandidate });
          return;
        }

        const isStandby = Boolean(standbyTab && senderTabId && standbyTab.id === senderTabId);
        sendResponse({ isStandby, hasCandidate });
      } catch (err) {
        sendResponse({ isStandby: true, hasCandidate: false });
      }
    })();
    return true;
  }

  return false;
});

// Clear old open-and-close alarms and use 10-minute in-place refresh on the standby vleHome tab
chrome.alarms.clear('WB_CSC_DASHBOARD_KEEPALIVE_30M');
chrome.alarms.clear('WB_UTI_VLEHOME_KEEPALIVE_7M');

chrome.tabs.onRemoved.addListener((tabId) => {
  chrome.storage.local.get(['standbyVleTabId', 'activeEntryVleTabId'], (res) => {
    const toRemove = [];
    if (res?.standbyVleTabId === tabId) toRemove.push('standbyVleTabId');
    if (res?.activeEntryVleTabId === tabId) toRemove.push('activeEntryVleTabId');
    if (toRemove.length > 0) {
      chrome.storage.local.remove(toRemove);
    }
  });
});

chrome.alarms.create(QUEUE_ALARM_NAME, { periodInMinutes: 0.25 });
chrome.alarms.create(VLE_STANDBY_REFRESH_ALARM, { periodInMinutes: 10 });
chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === QUEUE_ALARM_NAME) {
    checkQueueAndLaunch(false);
  } else if (alarm.name === VLE_STANDBY_REFRESH_ALARM) {
    refreshStandbyVleTab();
  }
});

setInterval(() => {
  checkQueueAndLaunch(false);
}, 5000);

chrome.runtime.onStartup.addListener(async () => {
  await ensureStandbyVleTab(true);
  checkQueueAndLaunch(true);
});
chrome.runtime.onInstalled.addListener(async () => {
  await ensureStandbyVleTab(true);
  checkQueueAndLaunch(true);
});

checkQueueAndLaunch(false);
