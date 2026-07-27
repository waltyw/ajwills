/**
 * AJ Wills & Estate Planning — Main JS
 * Navigation, FAQ accordion, form validation, cookie consent,
 * exit-intent popup, scroll effects.
 */

(function () {
  'use strict';

  // ── Navigation ─────────────────────────────────────────────────────────
  function initNav() {
    const hamburger = document.getElementById('hamburger');
    const navMenu   = document.getElementById('navMenu');
    if (!hamburger || !navMenu) return;

    hamburger.addEventListener('click', () => {
      const isOpen = navMenu.classList.toggle('open');
      hamburger.classList.toggle('active', isOpen);
      hamburger.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', (e) => {
      if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
        navMenu.classList.remove('open');
        hamburger.classList.remove('active');
        hamburger.setAttribute('aria-expanded', 'false');
      }
    });

    navMenu.querySelectorAll('.has-dropdown').forEach((item) => {
      const link = item.querySelector('a');
      link.addEventListener('click', (e) => {
        if (window.innerWidth <= 992) {
          e.preventDefault();
          item.classList.toggle('open');
        }
      });
    });

    const header = document.querySelector('.site-header');
    if (header) {
      window.addEventListener('scroll', () => {
        const nav = header.querySelector('.main-nav');
        if (nav) nav.style.boxShadow = window.scrollY > 10 ? '0 4px 20px rgba(45,45,45,0.12)' : '';
        header.classList.toggle('scrolled', window.scrollY > 40);
      }, { passive: true });
    }
  }

  // ── FAQ Accordion ──────────────────────────────────────────────────────
  function initFAQ() {
    document.querySelectorAll('.faq-item').forEach((item) => {
      const btn = item.querySelector('.faq-question');
      if (!btn) return;
      btn.addEventListener('click', () => {
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach((openItem) => {
          openItem.classList.remove('open');
          openItem.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
        });
        if (!isOpen) {
          item.classList.add('open');
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }

  // ── Generic AJAX form handler (contact + lead magnet) ─────────────────
  function initForms() {
    document.querySelectorAll('form[data-ajax-form]').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm(form)) return;

        const submitBtn = form.querySelector('[type="submit"]');
        const successEl = form.querySelector('.form-success') || document.getElementById(form.dataset.successTarget || '');
        const originalText = submitBtn ? submitBtn.textContent : '';

        if (submitBtn) { submitBtn.textContent = 'Sending...'; submitBtn.disabled = true; }

        try {
          const formData = new FormData(form);
          const res = await fetch(form.action, { method: 'POST', body: formData });
          const data = await res.json();

          if (data.success) {
            form.reset();
            if (successEl) {
              successEl.style.display = 'block';
              successEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            showToast('✓ ' + (data.message || 'Thank you — we will be in touch shortly.'), 'success');
          } else {
            showToast('⚠ ' + (data.message || 'Something went wrong. Please try again.'), 'error');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
          }
        } catch {
          showToast('⚠ Network error. Please call us to speak with our team.', 'error');
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
        }
      });
    });
  }

  function validateForm(form) {
    let valid = true;
    form.querySelectorAll('.form-error').forEach((el) => (el.style.display = 'none'));
    form.querySelectorAll('.form-input, .form-select, .form-textarea').forEach((el) => { el.style.borderColor = ''; });

    form.querySelectorAll('[required]').forEach((el) => {
      const errEl = document.getElementById(el.id + 'Error');
      let ok = true;
      if (el.type === 'checkbox') ok = el.checked;
      else if (el.type === 'email') ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value.trim());
      else ok = el.value.trim().length > 0;

      if (!ok) {
        valid = false;
        if (el.type !== 'checkbox') el.style.borderColor = 'var(--color-error)';
        if (errEl) errEl.style.display = 'block';
      }
    });

    if (!valid) {
      form.querySelector('[style*="border-color"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return valid;
  }

  // ── Toast ──────────────────────────────────────────────────────────────
  function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'toast toast-' + type;
    toast.style.display = 'block';
    toast.getBoundingClientRect();
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => { toast.style.display = 'none'; }, 320);
    }, 4500);
  }

  // ── Cookie Consent ─────────────────────────────────────────────────────
  function initCookieBanner() {
    const banner     = document.getElementById('cookieBanner');
    const acceptBtn  = document.getElementById('cookieAccept');
    const declineBtn = document.getElementById('cookieDecline');
    if (!banner) return;

    if (!localStorage.getItem('ajw_cookie_consent')) {
      setTimeout(() => banner.classList.add('show'), 1500);
    }

    acceptBtn?.addEventListener('click', () => {
      localStorage.setItem('ajw_cookie_consent', 'all');
      banner.classList.remove('show');
    });
    declineBtn?.addEventListener('click', () => {
      localStorage.setItem('ajw_cookie_consent', 'necessary');
      banner.classList.remove('show');
    });
  }

  // ── Exit Intent Popup ──────────────────────────────────────────────────
  function initExitIntent() {
    const overlay = document.getElementById('exitOverlay');
    const closeBtn = document.getElementById('exitClose');
    if (!overlay) return;

    let shown = sessionStorage.getItem('ajw_exit_shown');

    document.addEventListener('mouseout', (e) => {
      if (shown) return;
      if (e.clientY > 0) return;
      if (!localStorage.getItem('ajw_cookie_consent') && !sessionStorage.getItem('ajw_exit_shown')) {
        overlay.classList.add('show');
        shown = true;
        sessionStorage.setItem('ajw_exit_shown', '1');
      } else if (!sessionStorage.getItem('ajw_exit_shown')) {
        overlay.classList.add('show');
        shown = true;
        sessionStorage.setItem('ajw_exit_shown', '1');
      }
    });

    closeBtn?.addEventListener('click', () => overlay.classList.remove('show'));
    overlay?.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('show'); });
  }

  // ── Scroll Animations ──────────────────────────────────────────────────
  function initScrollAnimations() {
    if (!('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );

    document.querySelectorAll('.card, .feature-item, .process-step, .stat-item').forEach((el) => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      observer.observe(el);
    });
  }

  // ── Smooth scroll for anchor links ────────────────────────────────────
  function initAnchorScroll() {
    document.querySelectorAll('a[href^="#"]').forEach((a) => {
      a.addEventListener('click', (e) => {
        const href = a.getAttribute('href');
        if (href === '#') return;
        const target = document.querySelector(href);
        if (!target) return;
        e.preventDefault();
        const headerH = document.querySelector('.site-header')?.offsetHeight || 80;
        const top = target.getBoundingClientRect().top + window.scrollY - headerH - 16;
        window.scrollTo({ top, behavior: 'smooth' });
      });
    });
  }

  // ── Back to Top ────────────────────────────────────────────────────────
  function initBackToTop() {
    const btn = document.getElementById('backToTop');
    if (!btn) return;
    window.addEventListener('scroll', () => {
      btn.classList.toggle('show', window.scrollY > 400);
    }, { passive: true });
    btn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // ── Initialise all ────────────────────────────────────────────────────
  function init() {
    initNav();
    initFAQ();
    initForms();
    initCookieBanner();
    initExitIntent();
    initScrollAnimations();
    initAnchorScroll();
    initBackToTop();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
