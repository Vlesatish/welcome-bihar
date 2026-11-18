// =========================================================================
// Welcome Bihar - UTI PAN Auto-Pilot (Background Service Worker, MV3)
// ONLY ONE LINK: https://www.myutiitsl.com/panonlineservices/vleHome.action
// =========================================================================

const DEFAULT_PORTAL_URL = 'https://bihargo.xyz';
const VLE_HOME_URL = 'https://www.myutiitsl.com/panonlineservices/vleHome.action';
const QUEUE_ALARM_NAME = 'WB_UTI_PAN_QUEUE_WATCH';

let isCycleLaunching = false;
let activeCandidateKey = null;
let lastLaunchedCandKey = null;

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

    // Check if vleHome.action is already open
    const vleTabs = await getVleHomeTabs();
    if (vleTabs.length === 0) {
      // Sirf aur sirf vleHome.action open karo
      await new Promise((resolve) => {
        chrome.tabs.create({ url: VLE_HOME_URL, active: true }, () => {
          void chrome.runtime.lastError;
          resolve();
        });
      });
    } else {
      // Tab pehle se khula hai, focus karo
      chrome.tabs.update(vleTabs[0].id, { active: true });
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

    if (queue.length === 0) {
      activeCandidateKey = null;
      lastLaunchedCandKey = null;
      return { success: true, count: 0 };
    }

    const firstCand = queue[0];
    const candKey = String(firstCand.app_ref || firstCand.id);
    activeCandidateKey = candKey;

    // Naya data detect hone par ya force button dabane par vleHome open karo
    const vleTabs = await getVleHomeTabs();
    if (forceRestart || (lastLaunchedCandKey !== candKey && vleTabs.length === 0)) {
      lastLaunchedCandKey = candKey;
      await openVleHomeOnly(firstCand);
    } else {
      // Update candidate details in local storage for badge
      await chrome.storage.local.set({
        currentCandidate: firstCand
      });
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

  if (msg.action === 'candidateCompleted') {
    (async () => {
      try {
        const data = await postPortalAction('/api/pan-card/complete', {
          app_id: msg.appId,
          app_ref: msg.appRef,
          uti_ref_no: msg.utiRefNo || '',
          remarks: msg.remarks || ''
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

  return false;
});

chrome.alarms.create(QUEUE_ALARM_NAME, { periodInMinutes: 0.25 });
chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === QUEUE_ALARM_NAME) {
    checkQueueAndLaunch(false);
  }
});

setInterval(() => {
  checkQueueAndLaunch(false);
}, 5000);

chrome.runtime.onStartup.addListener(() => checkQueueAndLaunch(true));
chrome.runtime.onInstalled.addListener(() => checkQueueAndLaunch(true));

checkQueueAndLaunch(false);
