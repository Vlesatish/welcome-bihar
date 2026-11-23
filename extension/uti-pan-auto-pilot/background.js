// =========================================================================
// Welcome Bihar - UTI PAN Auto-Pilot (Background Service Worker, MV3)
// ONLY ONE LINK: https://www.myutiitsl.com/panonlineservices/vleHome.action
// =========================================================================

const DEFAULT_PORTAL_URL = 'https://bihargo.xyz';
const VLE_HOME_URL = 'https://www.myutiitsl.com/panonlineservices/vleHome.action';
const DOC_UPLOAD_URL = 'https://www.myutiitsl.com/panonlineservices/docUploadListPanApp?applType=B';
const QUEUE_ALARM_NAME = 'WB_UTI_PAN_QUEUE_WATCH';

const VLE_STANDBY_REFRESH_ALARM = 'WB_UTI_VLEHOME_REFRESH_10M';
const LOW_BALANCE_RETRY_ALARM = 'WB_CSC_LOW_BALANCE_RETRY_5M';
const LOW_BALANCE_RETRY_MS = 5 * 60 * 1000;
const INVALID_OTP_RETRY_LIMIT = 3;
const INVALID_OTP_WINDOW_MS = 15 * 60 * 1000;

let isCycleLaunching = false;
let isQueueCheckInFlight = false;
let isCreatingStandbyTab = false;
let activeCandidateKey = null;
let lastLaunchedCandKey = null;
let activeOtpUtiTabId = null;
let createdGmailTabId = null;
let lastLogoutRestartAt = 0;
let lastInvalidOtpAt = 0;
let lastGmailOtpFetchAt = 0;
let lastLowBalanceAlertAt = 0;
let lastLowBalanceUrl = '';
let lowBalanceGmailTabId = null;
let lowBalanceErrorTabId = null;
let isFinalizingLowBalance = false;

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

function getAllOpenTabs() {
  return new Promise((resolve) => {
    chrome.tabs.query({}, (tabs) => {
      void chrome.runtime.lastError;
      resolve(tabs || []);
    });
  });
}

function isErrorOrLogoutUrl(url) {
  const u = String(url || '').toLowerCase();
  return u.includes('error?errormsg') || u.includes('something+went+wrong') || u.includes('logout') || u.includes('editvle');
}

function isWorkflowUrl(url) {
  const u = String(url || '').toLowerCase();
  if (!u || isErrorOrLogoutUrl(u)) return false;
  return (
    u.includes('myutiitsl.com') ||
    u.includes('wallet.csccloud.in') ||
    u.includes('connect.csc.gov.in')
  );
}

function isInProgressNonVleHomeUrl(url) {
  const u = String(url || '').toLowerCase();
  if (!isWorkflowUrl(u)) return false;
  return !u.includes('vlehome');
}

async function getActiveWorkflowTabs(standbyId = null, savedEntryId = null) {
  const allTabs = await getAllOpenTabs();
  return allTabs.filter((t) => {
    if (!t || !t.id) return false;
    if (standbyId && t.id === standbyId) return false;
    const u = (t.url || t.pendingUrl || '').toLowerCase();
    if (savedEntryId && t.id === savedEntryId && !isErrorOrLogoutUrl(u) && (isWorkflowUrl(u) || u === '' || u === 'about:blank')) {
      return true;
    }
    return isWorkflowUrl(u);
  });
}

async function deduplicateVleHomeTabs(keepStandbyId = null, keepEntryId = null) {
  const vleTabs = await getVleHomeTabs();
  const extraIds = [];
  let keptNonStandby = keepEntryId || null;

  for (const t of vleTabs) {
    if (!t || !t.id) continue;
    if (keepStandbyId && t.id === keepStandbyId) continue;
    if (keptNonStandby && t.id === keptNonStandby) continue;
    if (!keptNonStandby) {
      keptNonStandby = t.id;
      continue;
    }
    extraIds.push(t.id);
  }

  if (extraIds.length > 0) {
    chrome.tabs.remove(extraIds, () => void chrome.runtime.lastError);
  }
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

  if (!allowCreate || isCreatingStandbyTab) {
    return { tab: null, created: false };
  }

  const cfg = await getConfig();
  if (!cfg.autoPollEnabled) {
    return { tab: null, created: false };
  }

  // Do not interrupt active CSC login / OTP verification flow
  const allTabs = await getAllOpenTabs();
  const isAuthFlowOpen = allTabs.some((t) => {
    const u = (t?.url || t?.pendingUrl || '').toLowerCase();
    return u.includes('csclogin') || u.includes('logincheckotp') || u.includes('connect.csc.gov.in');
  });
  if (isAuthFlowOpen) {
    return { tab: null, created: false };
  }

  isCreatingStandbyTab = true;
  try {
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
  } finally {
    isCreatingStandbyTab = false;
  }
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
    const extraDocTabIds = matched.slice(1).map((t) => t.id).filter(Boolean);
    if (successTabId && successTabId !== targetTab.id) {
      extraDocTabIds.push(successTabId);
    }
    if (extraDocTabIds.length > 0) {
      chrome.tabs.remove(Array.from(new Set(extraDocTabIds)), () => void chrome.runtime.lastError);
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

async function openVleHomeOnly(cand, options = {}) {
  const forceNavigate = Boolean(options && options.forceNavigate);
  if (isCycleLaunching) return { launched: false, reason: 'launching' };
  isCycleLaunching = true;
  try {
    if (cand) {
      const candKey = String(cand.app_ref || cand.id || '');
      await chrome.storage.local.set({
        currentCandidate: cand,
        currentUtiRefNo: '',
        lastLaunchedCandKey: candKey,
        lastLaunchedAt: Date.now()
      });
    }

    // Close any dead error tabs first
    await closeErrorTabs();

    // Keep persistent standby vleHome tab untouched; enforce strictly ONE active entry/workflow tab at a time
    const storedIds = await new Promise((r) => chrome.storage.local.get(['standbyVleTabId', 'activeEntryVleTabId'], r));
    const { tab: standbyTab } = await ensureStandbyVleTab(false);
    const standbyId = (standbyTab && standbyTab.id) ? standbyTab.id : (storedIds?.standbyVleTabId || null);
    const savedEntryId = storedIds?.activeEntryVleTabId || null;

    const activeWorkflowTabs = await getActiveWorkflowTabs(standbyId, savedEntryId);

    // If a non-vleHome workflow tab is ALREADY actively processing (Form 49A, CSC Wallet, OTP, Login, Receipt, DocUpload),
    // NEVER spawn another tab!
    const inProgressTab = activeWorkflowTabs.find((t) => isInProgressNonVleHomeUrl(t.url || t.pendingUrl || ''));
    if (inProgressTab && !forceNavigate) {
      await chrome.storage.local.set({ activeEntryVleTabId: inProgressTab.id });
      await deduplicateVleHomeTabs(standbyId, inProgressTab.id);
      return { launched: false, reason: 'workflow_already_active', tabId: inProgressTab.id };
    }

    const vleTabs = await getVleHomeTabs();
    const existingEntryTab =
      vleTabs.find((t) => t.id === savedEntryId && (!standbyId || t.id !== standbyId)) ||
      vleTabs.find((t) => !standbyId || t.id !== standbyId) ||
      (forceNavigate ? inProgressTab : null) ||
      null;

    if (!existingEntryTab) {
      const createdEntryTab = await new Promise((resolve) => {
        chrome.tabs.create({ url: VLE_HOME_URL, active: true }, (tab) => {
          void chrome.runtime.lastError;
          resolve(tab || null);
        });
      });
      if (createdEntryTab && createdEntryTab.id) {
        await chrome.storage.local.set({ activeEntryVleTabId: createdEntryTab.id });
        await deduplicateVleHomeTabs(standbyId, createdEntryTab.id);
        return { launched: true, tabId: createdEntryTab.id };
      }
      return { launched: false, reason: 'tab_create_failed' };
    } else {
      await chrome.storage.local.set({ activeEntryVleTabId: existingEntryTab.id });
      await deduplicateVleHomeTabs(standbyId, existingEntryTab.id);
      chrome.tabs.update(existingEntryTab.id, { url: VLE_HOME_URL, active: true }, () => void chrome.runtime.lastError);
      return { launched: true, reused: true, tabId: existingEntryTab.id };
    }
  } finally {
    setTimeout(() => {
      isCycleLaunching = false;
    }, 2000);
  }
}

async function checkQueueAndLaunch(forceRestart = false) {
  if (isCycleLaunching || isQueueCheckInFlight) {
    return { success: false, reason: 'busy' };
  }

  const cfg = await getConfig();
  if (!cfg.autoPollEnabled && !forceRestart) {
    return { success: false, reason: 'paused' };
  }

  isQueueCheckInFlight = true;
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

    const stored = await new Promise((r) => chrome.storage.local.get([
      'awaitingDocUpload',
      'awaitingDocUploadAt',
      'isWaitingForOtp',
      'otpRequestedAt',
      'currentCandidate',
      'lastLaunchedCandKey',
      'standbyVleTabId',
      'activeEntryVleTabId',
      'lowBalanceRetryAt'
    ], r));

    if (forceRestart && stored?.lowBalanceRetryAt) {
      await chrome.storage.local.remove(['lowBalanceRetryAt']);
      chrome.alarms.clear(LOW_BALANCE_RETRY_ALARM);
    }

    const otpWaitingFresh = Boolean(
      stored && stored.isWaitingForOtp &&
      (!stored.otpRequestedAt || (Date.now() - Number(stored.otpRequestedAt) < 180000))
    );

    if (queue.length === 0) {
      // Only keep tab open if actively awaiting immediate document upload right after form submission (max 3 min)
      if (otpWaitingFresh) {
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
      await chrome.storage.local.remove([
        'currentCandidate',
        'currentUtiRefNo',
        'completedReceiptNo',
        'awaitingDocUpload',
        'awaitingDocUploadAt',
        'activeEntryVleTabId',
        'lastLaunchedCandKey',
        'lastLaunchedAt',
        'lowBalanceRetryAt'
      ]);
      await chrome.storage.local.set({
        lastStatus: 'Idle • Standby VLE Home active (10m refresh) | Waiting for queue...',
        lastStatusColor: '#94a3b8'
      });
      await ensureStandbyVleTab(false);
      await closeAllIdleUtiTabs();
      return { success: true, count: 0 };
    }

    // 5-Minute Cooldown after CSC Wallet Error 810 (Insufficient Balance)
    if (!forceRestart && stored && stored.lowBalanceRetryAt) {
      const retryAt = Number(stored.lowBalanceRetryAt) || 0;
      const remainingMs = retryAt - Date.now();
      if (remainingMs > 0) {
        const mins = Math.floor(remainingMs / 60000);
        const secs = Math.floor((remainingMs % 60000) / 1000);
        await chrome.storage.local.set({
          lastStatus: `⏳ CSC में पैसा खत्म (मेल भेज दिया गया) • ${mins}m ${secs}s बाद फिर से अपने-आप Try करेंगे...`,
          lastStatusColor: '#f59e0b'
        });
        return { success: true, count: queue.length, waitingLowBalanceRetry: true, remainingMs };
      } else {
        // 5 minutes completed! Clear cooldown and force fresh launch
        await chrome.storage.local.remove(['lowBalanceRetryAt', 'lastLaunchedCandKey', 'activeEntryVleTabId']);
        activeCandidateKey = null;
        lastLaunchedCandKey = null;
        lastLowBalanceUrl = '';
        forceRestart = true;
      }
    }

    // Do not steal focus / open tabs while OTP verification is actively running
    if (!forceRestart && otpWaitingFresh) {
      return { success: true, count: queue.length, waitingForOtp: true };
    }

    // Do not steal focus / overwrite candidate while document upload for just-finished PAN is actively running (< 60s)
    if (!forceRestart && stored && stored.awaitingDocUpload) {
      const uploadElapsed = Date.now() - Number(stored.awaitingDocUploadAt || 0);
      if (uploadElapsed < 60000) {
        return { success: true, count: queue.length, waitingForDocUpload: true };
      }
    }

    // Check if an active workflow tab is ALREADY running (prevents multiple tabs opening when bulk data arrives!)
    const { tab: standbyTab } = await ensureStandbyVleTab(false);
    const standbyId = (standbyTab && standbyTab.id) ? standbyTab.id : (stored?.standbyVleTabId || null);
    const activeWorkflowTabs = await getActiveWorkflowTabs(standbyId, stored?.activeEntryVleTabId || null);

    // If we already have an active candidate in-flight AND it is still present in the queue, keep locked to it
    const existingCand = stored?.currentCandidate || null;
    const existingCandKey = existingCand ? String(existingCand.app_ref || existingCand.id || '') : '';
    const existingStillQueued = existingCandKey
      ? queue.find((q) => String(q.app_ref || q.id || '') === existingCandKey)
      : null;

    if (!forceRestart && activeWorkflowTabs.length > 0 && existingStillQueued) {
      activeCandidateKey = existingCandKey;
      lastLaunchedCandKey = existingCandKey;
      // Deduplicate any accidental extra vleHome tabs without disturbing active workflow
      await deduplicateVleHomeTabs(standbyId, activeWorkflowTabs[0].id);
      return { success: true, count: queue.length, active: existingStillQueued, alreadyRunning: true };
    }

    const firstCand = existingStillQueued || queue[0];
    const candKey = String(firstCand.app_ref || firstCand.id);
    const persistedLaunchedKey = stored?.lastLaunchedCandKey || lastLaunchedCandKey || null;
    activeCandidateKey = candKey;

    await chrome.storage.local.set({
      currentCandidate: firstCand,
      lastStatus: `🚀 Processing: ${firstCand.full_name || 'Candidate'} (${firstCand.app_ref || firstCand.id})`,
      lastStatusColor: '#38bdf8'
    });

    // Launch ONLY if forced, OR new candidate with no active workflow tab, OR active workflow tab was closed
    const hasLiveWorkflowTab = activeWorkflowTabs.length > 0;
    if (forceRestart || !hasLiveWorkflowTab || persistedLaunchedKey !== candKey) {
      if (!forceRestart && hasLiveWorkflowTab) {
        // An active workflow tab is already open — do not spawn a duplicate tab!
        await deduplicateVleHomeTabs(standbyId, activeWorkflowTabs[0].id);
        return { success: true, count: queue.length, active: firstCand, alreadyRunning: true };
      }
      lastLaunchedCandKey = candKey;
      await openVleHomeOnly(firstCand, { forceNavigate: forceRestart });
    }

    return { success: true, count: queue.length, active: firstCand };
  } catch (err) {
    await chrome.storage.local.set({
      lastQueueCheckAt: Date.now(),
      lastQueueError: err.message || 'Connection failed'
    });
    return { success: false, error: err.message };
  } finally {
    isQueueCheckInFlight = false;
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

async function finalizeLowBalanceAndScheduleRetry(composeTabIdToClose = null, closeComposeDelayMs = 2500) {
  if (isFinalizingLowBalance) return;
  isFinalizingLowBalance = true;
  try {
    const stored = await new Promise((r) => chrome.storage.local.get([
      'lowBalanceGmailTabId',
      'lowBalanceErrorTabId',
      'standbyVleTabId',
      'activeEntryVleTabId',
      'lowBalanceRetryAt'
    ], r));

    const retryAt = (stored?.lowBalanceRetryAt && Number(stored.lowBalanceRetryAt) > Date.now())
      ? Number(stored.lowBalanceRetryAt)
      : (Date.now() + LOW_BALANCE_RETRY_MS);

    // 1. Make sure standby vleHome tab exists so closing error tab never closes the Chrome window
    const { tab: standbyTab } = await ensureStandbyVleTab(true);
    const standbyId = (standbyTab && standbyTab.id) ? standbyTab.id : (stored?.standbyVleTabId || null);

    // 2. Find & close the CSC Wallet Error 810 tab ("ye page cut jaye") + any stale non-standby entry tabs
    const allTabs = await getAllOpenTabs();
    const idsToClose = new Set();

    const errTabId = lowBalanceErrorTabId || stored?.lowBalanceErrorTabId || null;
    if (errTabId && errTabId !== standbyId) {
      idsToClose.add(errTabId);
    }
    if (stored?.activeEntryVleTabId && stored.activeEntryVleTabId !== standbyId) {
      idsToClose.add(stored.activeEntryVleTabId);
    }

    for (const t of allTabs) {
      if (!t || !t.id || t.id === standbyId) continue;
      const u = (t.url || t.pendingUrl || '').toLowerCase();
      if (u.includes('wallet.csccloud.in')) {
        idsToClose.add(t.id);
      }
    }

    if (idsToClose.size > 0) {
      chrome.tabs.remove(Array.from(idsToClose), () => void chrome.runtime.lastError);
    }

    // 3. Schedule 5-minute retry & keep autoPollEnabled TRUE so it automatically retries after 5 min
    lowBalanceErrorTabId = null;
    lowBalanceGmailTabId = null;
    activeCandidateKey = null;
    lastLaunchedCandKey = null;

    await chrome.storage.local.remove([
      'pendingLowBalanceMail',
      'lowBalanceGmailTabId',
      'lowBalanceErrorTabId',
      'activeEntryVleTabId',
      'lastLaunchedCandKey',
      'lastLaunchedAt'
    ]);

    await chrome.storage.local.set({
      autoPollEnabled: true,
      lowBalanceRetryAt: retryAt,
      lastStatus: '✅ मेल भेज दिया और Error पेज बंद कर दिया • 5 मिनट बाद फिर से अपने-आप Try करेंगे...',
      lastStatusColor: '#f59e0b'
    });

    chrome.alarms.create(LOW_BALANCE_RETRY_ALARM, { delayInMinutes: 5 });

    // 4. Close temporary Gmail compose tab if still open
    const gmailTabToClose = composeTabIdToClose || stored?.lowBalanceGmailTabId || null;
    if (gmailTabToClose) {
      setTimeout(() => {
        chrome.tabs.remove(gmailTabToClose, () => void chrome.runtime.lastError);
      }, closeComposeDelayMs);
    }
  } finally {
    setTimeout(() => {
      isFinalizingLowBalance = false;
    }, 1500);
  }
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
          await chrome.storage.local.remove(['currentCandidate', 'currentUtiRefNo', 'completedReceiptNo', 'lastLaunchedCandKey', 'lastLaunchedAt']);
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
        const senderTabId = sender && sender.tab ? sender.tab.id : null;
        const data = await postPortalAction('/api/pan-card/reject', {
          app_id: msg.appId,
          app_ref: msg.appRef,
          reason: msg.reason || 'Rejected from UTI PAN Auto-Pilot'
        });
        activeCandidateKey = null;
        lastLaunchedCandKey = null;
        await chrome.storage.local.remove([
          'currentCandidate',
          'currentUtiRefNo',
          'lastLaunchedCandKey',
          'lastLaunchedAt',
          'activeEntryVleTabId'
        ]);
        await chrome.storage.local.set({
          lastStatus: `❌ Rejected: ${msg.reason || 'Rejected from UTI PAN Auto-Pilot'}`,
          lastStatusColor: '#ef4444'
        });
        // Close the rejected entry tab first (if requested) before launching the next candidate cleanly
        setTimeout(() => checkQueueAndLaunch(true), 1800);
        sendResponse({ success: Boolean(data && data.success), data, senderTabId });
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
        await chrome.storage.local.remove(['isWaitingForOtp', 'otpRequestedAt', 'lastLaunchedCandKey', 'lastLaunchedAt']);

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

  if (msg.action === 'handleInvalidOtpError') {
    (async () => {
      try {
        const now = Date.now();
        if (now - lastInvalidOtpAt < 3000) {
          sendResponse({ success: true, deduplicated: true });
          return;
        }
        lastInvalidOtpAt = now;

        const senderTabId = sender && sender.tab ? sender.tab.id : null;
        const stored = await new Promise((r) => chrome.storage.local.get([
          'invalidOtpRetryCount',
          'invalidOtpWindowAt',
          'standbyVleTabId',
          'activeEntryVleTabId',
          'currentCandidate'
        ], r));

        const prevWindowAt = Number(stored?.invalidOtpWindowAt || 0);
        const prevCount = (prevWindowAt > 0 && (now - prevWindowAt < INVALID_OTP_WINDOW_MS))
          ? Number(stored?.invalidOtpRetryCount || 0)
          : 0;
        const nextCount = prevCount + 1;

        // Clear waiting OTP flags (keep lastSubmittedOtp so Gmail won't pick the same invalid code again!)
        await chrome.storage.local.remove(['isWaitingForOtp', 'otpRequestedAt']);

        if (nextCount > INVALID_OTP_RETRY_LIMIT) {
          // Exceeded 3 retries: DO NOT reject candidate PAN application; pause Auto-Pilot & alert operator
          await chrome.storage.local.set({
            autoPollEnabled: false,
            invalidOtpRetryCount: 0,
            invalidOtpWindowAt: 0,
            lastStatus: `🛑 Invalid OTP ${INVALID_OTP_RETRY_LIMIT} baar se jyada fail hua! Auto-Pilot paused — manually OTP/login check karein.`,
            lastStatusColor: '#ef4444'
          });
          sendResponse({ success: true, limitReached: true, retryCount: nextCount });
          return;
        }

        const hasOtpInputOnPage = Boolean(msg.hasOtpInputOnPage);

        // If the OTP input box is RIGHT HERE on loginCheckOtpVerify.action, DO NOT close the tab
        // and DO NOT re-trigger CSC login / resend OTP — just go to Gmail, grab the latest OTP, and try here!
        if (hasOtpInputOnPage && senderTabId) {
          activeOtpUtiTabId = senderTabId;
          await chrome.storage.local.set({
            invalidOtpRetryCount: nextCount,
            invalidOtpWindowAt: now,
            isWaitingForOtp: true,
            otpRequestedAt: now,
            activeOtpUtiTabId: senderTabId,
            lastStatus: `📧 Invalid OTP (Retry ${nextCount}/${INVALID_OTP_RETRY_LIMIT}) • Page band kiye bina Gmail se latest OTP try kar rahe hain...`,
            lastStatusColor: '#f59e0b'
          });
          await chrome.storage.local.remove(['pendingOtpCode', 'pendingOtpAt']);

          const targetGmailUrl = 'https://mail.google.com/mail/u/0/#inbox';
          const allTabs = await getAllOpenTabs();
          const gmailTabs = (allTabs || []).filter((t) => {
            const u = (t.url || t.pendingUrl || '').toLowerCase();
            return u.includes('mail.google.com');
          });

          const sendSignal = (tabId) => {
            if (!tabId) return;
            chrome.tabs.sendMessage(tabId, { action: 'readOtpNow', triggerTime: now }, () => {
              if (chrome.runtime.lastError) {
                chrome.scripting.executeScript({
                  target: { tabId },
                  files: ['content/gmail_otp_reader.js']
                }, () => {
                  void chrome.runtime.lastError;
                  setTimeout(() => {
                    chrome.tabs.sendMessage(tabId, { action: 'readOtpNow', triggerTime: now }, () => {
                      void chrome.runtime.lastError;
                    });
                  }, 350);
                });
              }
            });
          };

          if (gmailTabs.length > 0) {
            const gTab = gmailTabs[0];
            if (typeof gTab.windowId === 'number') {
              chrome.windows.update(gTab.windowId, { focused: true }, () => void chrome.runtime.lastError);
            }
            const curGmailUrl = (gTab.url || gTab.pendingUrl || '').toLowerCase();
            if (curGmailUrl.includes('#inbox') && !/#inbox\/[a-z0-9_-]{8,}/i.test(curGmailUrl)) {
              await chrome.tabs.update(gTab.id, { active: true });
            } else {
              await chrome.tabs.update(gTab.id, { url: targetGmailUrl, active: true });
            }
            setTimeout(() => sendSignal(gTab.id), 1000);
          } else {
            const createOpts = { url: targetGmailUrl, active: true };
            if (sender && sender.tab && typeof sender.tab.windowId === 'number') {
              createOpts.windowId = sender.tab.windowId;
            }
            const newTab = await new Promise((r) => chrome.tabs.create(createOpts, r));
            createdGmailTabId = newTab ? newTab.id : null;
            await chrome.storage.local.set({ createdGmailTabId: createdGmailTabId || null });
            setTimeout(() => sendSignal(createdGmailTabId), 1800);
          }

          sendResponse({ success: true, limitReached: false, retryingInPlace: true, retryCount: nextCount });
          return;
        }

        await chrome.storage.local.set({
          invalidOtpRetryCount: nextCount,
          invalidOtpWindowAt: now,
          lastStatus: `⚠️ Invalid OTP (Retry ${nextCount}/${INVALID_OTP_RETRY_LIMIT}) • Page close karke fir se try kar rahe hain...`,
          lastStatusColor: '#f59e0b'
        });

        // Clean up stored tab IDs if they match the failed tab
        if (senderTabId && stored?.standbyVleTabId === senderTabId) {
          await chrome.storage.local.remove(['standbyVleTabId']);
        }
        if (senderTabId && stored?.activeEntryVleTabId === senderTabId) {
          await chrome.storage.local.remove(['activeEntryVleTabId']);
        }

        // Collect the failed tab + any stale loginCheckOtp / error tabs to close
        const allUti = await getAllUtiTabs();
        const idsToClose = new Set();
        if (senderTabId) idsToClose.add(senderTabId);
        for (const t of allUti) {
          if (!t || !t.id) continue;
          const u = (t.url || t.pendingUrl || '').toLowerCase();
          if (u.includes('logincheckotp') || u.includes('error') || u.includes('logout')) {
            idsToClose.add(t.id);
          }
        }

        // Open a brand-new fresh vleHome.action tab FIRST (so Chrome never closes a 1-tab window)
        const freshTab = await new Promise((resolve) => {
          chrome.tabs.create({ url: VLE_HOME_URL, active: true }, (tab) => {
            void chrome.runtime.lastError;
            resolve(tab || null);
          });
        });

        if (freshTab && freshTab.id) {
          idsToClose.delete(freshTab.id);
          await chrome.storage.local.set({ activeEntryVleTabId: freshTab.id });
        }

        // Now close the failed Invalid OTP tab(s)
        if (idsToClose.size > 0) {
          chrome.tabs.remove(Array.from(idsToClose), () => {
            void chrome.runtime.lastError;
          });
        }

        sendResponse({ success: true, limitReached: false, retryCount: nextCount, freshTabId: freshTab?.id || null });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'resetInvalidOtpCounter') {
    (async () => {
      const stored = await new Promise((r) => chrome.storage.local.get(['createdGmailTabId'], r));
      const tempGmailId = createdGmailTabId || stored?.createdGmailTabId || null;
      await chrome.storage.local.set({ invalidOtpRetryCount: 0, invalidOtpWindowAt: 0 });
      await chrome.storage.local.remove(['pendingOtpCode', 'pendingOtpAt', 'createdGmailTabId']);
      // Close temporary Gmail tab ONLY after login actually succeeded!
      if (tempGmailId) {
        createdGmailTabId = null;
        chrome.tabs.remove(tempGmailId, () => void chrome.runtime.lastError);
      }
      sendResponse({ success: true });
    })();
    return true;
  }

  if (msg.action === 'fetchGmailOtp') {
    (async () => {
      try {
        const now = Date.now();
        if (now - lastGmailOtpFetchAt < 2500) {
          sendResponse({ success: true, deduplicated: true });
          return;
        }
        lastGmailOtpFetchAt = now;

        const triggerTime = Number(msg.triggerTime) || now;
        activeOtpUtiTabId = sender.tab ? sender.tab.id : null;
        await chrome.storage.local.set({
          isWaitingForOtp: true,
          otpRequestedAt: triggerTime,
          activeOtpUtiTabId: activeOtpUtiTabId || null
        });

        const sendReadOtpSignal = (tabId) => {
          if (!tabId) return;
          chrome.tabs.sendMessage(tabId, { action: 'readOtpNow', triggerTime }, () => {
            if (chrome.runtime.lastError) {
              // If Gmail tab was open before extension reload, inject reader script and retry!
              chrome.scripting.executeScript({
                target: { tabId },
                files: ['content/gmail_otp_reader.js']
              }, () => {
                void chrome.runtime.lastError;
                setTimeout(() => {
                  chrome.tabs.sendMessage(tabId, { action: 'readOtpNow', triggerTime }, () => {
                    void chrome.runtime.lastError;
                  });
                }, 350);
              });
            }
          });
        };

        const allTabs = await getAllOpenTabs();
        const gmailTabs = (allTabs || []).filter((t) => {
          const u = (t.url || t.pendingUrl || '').toLowerCase();
          return u.includes('mail.google.com');
        });

        const targetGmailUrl = 'https://mail.google.com/mail/u/0/#inbox';

        if (gmailTabs.length > 0) {
          createdGmailTabId = null; // Do not close pre-existing tab
          await chrome.storage.local.remove(['createdGmailTabId']);
          const gTab = gmailTabs[0];
          if (typeof gTab.windowId === 'number') {
            chrome.windows.update(gTab.windowId, { focused: true }, () => void chrome.runtime.lastError);
          }
          const curGmailUrl = (gTab.url || gTab.pendingUrl || '').toLowerCase();
          if (curGmailUrl.includes('#inbox') && !/#inbox\/[a-z0-9_-]{8,}/i.test(curGmailUrl)) {
            await chrome.tabs.update(gTab.id, { active: true });
          } else {
            await chrome.tabs.update(gTab.id, { url: targetGmailUrl, active: true });
          }
          setTimeout(() => sendReadOtpSignal(gTab.id), 1200);
        } else {
          const createOpts = { url: targetGmailUrl, active: true };
          if (sender && sender.tab && typeof sender.tab.windowId === 'number') {
            createOpts.windowId = sender.tab.windowId;
          }
          const newTab = await new Promise((r) => {
            chrome.tabs.create(createOpts, r);
          });
          createdGmailTabId = newTab ? newTab.id : null;
          await chrome.storage.local.set({ createdGmailTabId: createdGmailTabId || null });
          setTimeout(() => sendReadOtpSignal(createdGmailTabId), 1800);
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
        const storedOtp = await new Promise((r) => chrome.storage.local.get([
          'activeOtpUtiTabId',
          'createdGmailTabId',
          'activeEntryVleTabId',
          'standbyVleTabId'
        ], r));

        await chrome.storage.local.remove(['isWaitingForOtp']);
        await chrome.storage.local.set({
          pendingOtpCode: code,
          pendingOtpAt: Date.now()
        });

        // 1. Resolve the live UTI OTP tab reliably (survives MV3 service worker restarts!)
        let targetTab = null;
        const candidateIds = [
          activeOtpUtiTabId,
          storedOtp?.activeOtpUtiTabId || null
        ].filter(Boolean);

        for (const tid of candidateIds) {
          const alive = await getTabIfAlive(tid);
          if (alive && (alive.url || alive.pendingUrl || '').toLowerCase().includes('myutiitsl.com')) {
            targetTab = alive;
            break;
          }
        }

        if (!targetTab) {
          const allUti = await getAllUtiTabs();
          targetTab =
            allUti.find((t) => {
              const u = (t.url || t.pendingUrl || '').toLowerCase();
              return u.includes('logincheckotp') || u.includes('otp');
            }) ||
            (storedOtp?.activeEntryVleTabId ? allUti.find((t) => t.id === storedOtp.activeEntryVleTabId) : null) ||
            allUti.find((t) => !storedOtp?.standbyVleTabId || t.id !== storedOtp.standbyVleTabId) ||
            allUti[0] ||
            null;
        }

        if (targetTab && targetTab.id) {
          if (typeof targetTab.windowId === 'number') {
            await new Promise((r) => chrome.windows.update(targetTab.windowId, { focused: true }, () => {
              void chrome.runtime.lastError;
              r();
            }));
          }
          await new Promise((r) => chrome.tabs.update(targetTab.id, { active: true }, () => {
            void chrome.runtime.lastError;
            r();
          }));

          const deliverOtp = (attempt = 1) => {
            chrome.tabs.sendMessage(targetTab.id, { action: 'fillOtpAndSubmit', code }, () => {
              if (chrome.runtime.lastError && attempt < 3) {
                setTimeout(() => deliverOtp(attempt + 1), 700);
              }
            });
          };
          setTimeout(() => deliverOtp(1), 300);
        }

        // 2. Keep Gmail tab open until login actually succeeds (closed in resetInvalidOtpCounter)
        // so Invalid OTP retries do not have to keep reopening Gmail from scratch.

        sendResponse({ success: Boolean(targetTab && targetTab.id), error: targetTab ? '' : 'UTI OTP tab not open' });
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
          // If a queued candidate was waiting (e.g. user just logged in), open an Entry tab ONLY if no workflow tab is already open!
          const liveWorkflow = await getActiveWorkflowTabs(senderTabId, null);
          if (hasCandidate && cand && !isCycleLaunching && liveWorkflow.length === 0) {
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

  if (msg.action === 'cscInsufficientBalance') {
    (async () => {
      try {
        const now = Date.now();
        const errUrl = String(msg.errorUrl || '');
        if ((now - lastLowBalanceAlertAt < 45000) || (errUrl && errUrl === lastLowBalanceUrl && (now - lastLowBalanceAlertAt < 240000))) {
          sendResponse({ success: true, deduplicated: true });
          return;
        }
        lastLowBalanceAlertAt = now;
        if (errUrl) lastLowBalanceUrl = errUrl;

        const senderErrorTabId = sender && sender.tab ? sender.tab.id : null;
        lowBalanceErrorTabId = senderErrorTabId;

        const stored = await new Promise((r) => chrome.storage.local.get(['currentCandidate'], r));
        const cand = stored?.currentCandidate || null;
        const candInfo = cand
          ? `\nCandidate: ${cand.full_name || '-'} (${cand.app_ref || cand.id || '-'})`
          : '';

        const toEmail = String(msg.recipientEmail || 'sat397842@gmail.com').trim();
        const coreMsg = String(msg.alertMessage || 'csc me paisa katm ho gaya add kigiue').trim();
        const subject = coreMsg;
        const body = `${coreMsg}\n\nCSC Wallet Error Code: 810 (Insufficient Wallet Balance, Error ID: no_balance)${candInfo}`;
        const retryAt = now + LOW_BALANCE_RETRY_MS;

        // Keep Auto-Pilot enabled with a 5-minute retry timer
        await chrome.storage.local.set({
          autoPollEnabled: true,
          lowBalanceRetryAt: retryAt,
          lowBalanceErrorTabId: senderErrorTabId || null,
          pendingLowBalanceMail: {
            to: toEmail,
            subject,
            body,
            requestedAt: now
          },
          lastStatus: `⚠️ CSC में पैसा खत्म (Error 810) • ${toEmail} पर मेल भेजकर पेज बंद कर रहे हैं (5 मिनट बाद फिर Try होगा)...`,
          lastStatusColor: '#ef4444'
        });

        chrome.alarms.create(LOW_BALANCE_RETRY_ALARM, { delayInMinutes: 5 });

        const composeUrl =
          `https://mail.google.com/mail/u/0/?view=cm&fs=1&tf=1` +
          `&to=${encodeURIComponent(toEmail)}` +
          `&su=${encodeURIComponent(subject)}` +
          `&body=${encodeURIComponent(body)}` +
          `&wb_csc_low_balance=1`;

        const createOpts = { url: composeUrl, active: true };
        if (sender && sender.tab && typeof sender.tab.windowId === 'number') {
          createOpts.windowId = sender.tab.windowId;
        }

        const newTab = await new Promise((r) => chrome.tabs.create(createOpts, (t) => {
          void chrome.runtime.lastError;
          r(t || null);
        }));

        lowBalanceGmailTabId = newTab && newTab.id ? newTab.id : null;
        await chrome.storage.local.set({ lowBalanceGmailTabId: lowBalanceGmailTabId || null });

        if (lowBalanceGmailTabId) {
          const triggerComposeSend = (tabId) => {
            chrome.tabs.sendMessage(tabId, {
              action: 'sendLowBalanceMailNow',
              to: toEmail,
              subject,
              body
            }, () => {
              if (chrome.runtime.lastError) {
                chrome.scripting.executeScript({
                  target: { tabId },
                  files: ['content/gmail_otp_reader.js']
                }, () => {
                  void chrome.runtime.lastError;
                  setTimeout(() => {
                    chrome.tabs.sendMessage(tabId, {
                      action: 'sendLowBalanceMailNow',
                      to: toEmail,
                      subject,
                      body
                    }, () => void chrome.runtime.lastError);
                  }, 400);
                });
              }
            });
          };
          setTimeout(() => triggerComposeSend(lowBalanceGmailTabId), 2000);

          // Safety fallback: ensure Error 810 tab closes even if Gmail auto-closes the view=cm window
          const capturedGmailId = lowBalanceGmailTabId;
          setTimeout(() => {
            finalizeLowBalanceAndScheduleRetry(capturedGmailId, 500);
          }, 10000);
        } else {
          // If Gmail tab couldn't be created, still close Error 810 tab and wait 5 minutes
          setTimeout(() => {
            finalizeLowBalanceAndScheduleRetry(null, 0);
          }, 2500);
        }

        sendResponse({ success: true, tabId: lowBalanceGmailTabId, retryAt });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }

  if (msg.action === 'lowBalanceEmailSent') {
    (async () => {
      try {
        const senderTabId = sender && sender.tab ? sender.tab.id : null;
        const closeDelay = typeof msg.closeDelayMs === 'number' ? msg.closeDelayMs : 2500;
        await finalizeLowBalanceAndScheduleRetry(senderTabId || lowBalanceGmailTabId, closeDelay);
        sendResponse({ success: true });
      } catch (err) {
        sendResponse({ success: false, error: err.message });
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
  if (lowBalanceGmailTabId && tabId === lowBalanceGmailTabId) {
    // Gmail ?view=cm tab closed itself after sending mail -> close the Error 810 page immediately!
    finalizeLowBalanceAndScheduleRetry(null, 0);
  }
  chrome.storage.local.get(['standbyVleTabId', 'activeEntryVleTabId', 'lowBalanceGmailTabId'], (res) => {
    if (res?.lowBalanceGmailTabId === tabId) {
      finalizeLowBalanceAndScheduleRetry(null, 0);
    }
    const toRemove = [];
    if (res?.standbyVleTabId === tabId) toRemove.push('standbyVleTabId');
    if (res?.activeEntryVleTabId === tabId) toRemove.push('activeEntryVleTabId');
    if (res?.lowBalanceGmailTabId === tabId) toRemove.push('lowBalanceGmailTabId');
    if (toRemove.length > 0) {
      chrome.storage.local.remove(toRemove);
    }
  });
});

chrome.alarms.create(QUEUE_ALARM_NAME, { periodInMinutes: 0.25 });
chrome.alarms.create(VLE_STANDBY_REFRESH_ALARM, { periodInMinutes: 10 });
chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === QUEUE_ALARM_NAME) {
    checkQueueAndLaunch(false);
  } else if (alarm.name === VLE_STANDBY_REFRESH_ALARM) {
    refreshStandbyVleTab();
  } else if (alarm.name === LOW_BALANCE_RETRY_ALARM) {
    activeCandidateKey = null;
    lastLaunchedCandKey = null;
    lastLowBalanceUrl = '';
    await chrome.storage.local.remove(['lowBalanceRetryAt', 'lastLaunchedCandKey', 'activeEntryVleTabId']);
    await chrome.storage.local.set({
      autoPollEnabled: true,
      lastStatus: '🔄 5 मिनट पूरे हो गए • CSC Payment फिर से Try किया जा रहा है...',
      lastStatusColor: '#38bdf8'
    });
    checkQueueAndLaunch(true);
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
