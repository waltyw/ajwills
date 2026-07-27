/**
 * AJ Wills & Estate Planning — Guide Access Gate
 * Requires Name + Email (Phone optional) before a guide page's content
 * and download become accessible. One submission unlocks every guide.
 */
(function () {
  'use strict';

  var form = document.getElementById('guideGateForm');
  if (!form) return;

  function validate() {
    var valid = true;
    form.querySelectorAll('.form-error').forEach(function (el) { el.style.display = 'none'; });
    form.querySelectorAll('.form-input').forEach(function (el) { el.style.borderColor = ''; });

    form.querySelectorAll('[required]').forEach(function (el) {
      var errEl = document.getElementById(el.id + 'Error');
      var ok = el.type === 'email'
        ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value.trim())
        : el.value.trim().length > 0;
      if (!ok) {
        valid = false;
        el.style.borderColor = 'var(--color-error)';
        if (errEl) errEl.style.display = 'block';
      }
    });
    return valid;
  }

  function showMessage(text) {
    var msg = document.getElementById('ggFormMsg');
    if (!msg) return;
    msg.textContent = text;
    msg.style.display = 'block';
  }

  function reveal() {
    document.documentElement.classList.add('guides-preunlocked');
    var overlay = document.getElementById('guideGateOverlay');
    var content = document.getElementById('guideProtectedContent');
    if (overlay) overlay.classList.remove('show');
    if (content) content.classList.remove('guide-locked');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validate()) return;

    var btn = form.querySelector('[type="submit"]');
    var original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Unlocking...';

    fetch(form.action, { method: 'POST', body: new FormData(form) })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          try { localStorage.setItem('ajw_guides_unlocked', '1'); } catch (err) {}
          reveal();
        } else {
          btn.disabled = false;
          btn.textContent = original;
          showMessage(data.message || 'Something went wrong. Please try again.');
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = original;
        showMessage('Network error. Please call us to speak with our team.');
      });
  });
})();
