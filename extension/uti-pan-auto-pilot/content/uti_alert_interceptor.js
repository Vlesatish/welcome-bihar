// =========================================================================
// Welcome Bihar - UTI Native Alert Interceptor (MAIN World, document_start)
// Intercepts native window.alert() for Invalid OTP errors on myutiitsl.com
// so the browser never freezes on a blocking alert dialog.
// =========================================================================

(function () {
  'use strict';

  if (window.__wbUtiAlertHookInstalled) return;
  window.__wbUtiAlertHookInstalled = true;

  const originalAlert = window.alert;

  function isOtpSentInfoMessage(text) {
    const low = String(text || '').toLowerCase();
    return (
      low.includes('otp has been sent') ||
      low.includes('otp sent') ||
      (low.includes('registered email') && low.includes('otp')) ||
      (low.includes('valid for') && low.includes('otp') && !low.includes('invalid') && !low.includes('expired')) ||
      low.includes('please do not share the otp')
    );
  }

  function isInvalidOtpMessage(text) {
    const low = String(text || '').toLowerCase();
    return (
      low.includes('invalid otp') ||
      low.includes('wrong otp') ||
      low.includes('incorrect otp') ||
      low.includes('otp expired') ||
      low.includes('otp mismatch') ||
      (low.includes('otp') && (
        low.includes('invalid') ||
        low.includes('not valid') ||
        low.includes('please try again') ||
        low.includes('try again') ||
        low.includes('failed')
      ))
    );
  }

  function recordInvalidOtpAlert(text) {
    const now = Date.now();
    try {
      if (document.documentElement) {
        document.documentElement.setAttribute('data-wb-invalid-otp-alert', text);
        document.documentElement.setAttribute('data-wb-invalid-otp-at', String(now));
      }
    } catch (e) {}

    try {
      sessionStorage.setItem('wb_invalid_otp_alert', JSON.stringify({
        message: text,
        at: now,
        href: window.location.href || ''
      }));
    } catch (e) {}

    const payload = {
      source: 'wb-uti-alert-hook',
      type: 'INVALID_OTP_ALERT',
      message: text,
      at: now
    };
    try {
      window.postMessage(payload, '*');
    } catch (e) {}
    try {
      if (window.top && window.top !== window) {
        window.top.postMessage(payload, '*');
      }
    } catch (e) {}
  }

  window.alert = function (msg) {
    const text = String(msg === undefined || msg === null ? '' : msg);
    if (isOtpSentInfoMessage(text)) {
      // Auto-OK "The OTP has been sent on your registered email..." alert without blocking
      return;
    }
    if (isInvalidOtpMessage(text)) {
      recordInvalidOtpAlert(text);
      return;
    }
    return originalAlert.call(window, msg);
  };
})();
