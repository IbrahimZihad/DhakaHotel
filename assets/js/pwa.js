/* global window, document, navigator */
(function () {
  'use strict';

  var DH = window.DH || {};
  window.DH = DH;
  var deferredPrompt = null;

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var script = document.querySelector('script[src*="pwa.js"]');
      var base = script ? script.getAttribute('src').replace(/assets\/js\/pwa\.js.*$/, '') : '/';
      navigator.serviceWorker.register(base + 'service-worker.js', { scope: base }).catch(function () {
        /* offline support is optional; never block the page */
      });
    });
  }

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    document.querySelectorAll('[data-install-pwa]').forEach(function (button) {
      button.hidden = false;
      button.classList.add('is-ready');
    });
  });

  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    if (DH.toast) {
      DH.toast(DH.i18n ? DH.i18n.t('pwa.installed') : 'App installed', 'success');
    }
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-install-pwa]');
    if (!button) {
      return;
    }
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function () {
        deferredPrompt = null;
      });
      return;
    }
    var page = button.getAttribute('data-install-page');
    if (page) {
      window.location.href = page;
    }
  });
})();
