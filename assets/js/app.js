/* global window, document */
(function () {
  'use strict';

  var DH = window.DH || {};
  window.DH = DH;

  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  DH.csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

  /* ---------------------------------------------------------------- toast */
  function toast(message, type) {
    var region = document.getElementById('toast-region');
    if (!region) {
      return;
    }
    var el = document.createElement('div');
    el.className = 'toast' + (type ? ' is-' + type : '');
    el.setAttribute('role', 'status');
    var span = document.createElement('span');
    span.textContent = message;
    el.appendChild(span);
    region.appendChild(el);
    window.setTimeout(function () {
      el.remove();
    }, 3200);
  }
  DH.toast = toast;

  /* ---------------------------------------------------------------- fetch */
  DH.post = function (url, data) {
    var body = data instanceof FormData ? data : new URLSearchParams(data);
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-Token': DH.csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: body
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, message: 'Unexpected response' };
      }).then(function (payload) {
        if (!response.ok && payload.ok !== false) {
          payload.ok = false;
        }
        return payload;
      });
    });
  };

  /* ------------------------------------------------------------ cart badge */
  function setCartCount(count) {
    document.querySelectorAll('#cart-count, [data-cart-count]').forEach(function (el) {
      el.textContent = DH.formatCount ? DH.formatCount(count) : String(count);
      el.hidden = !count || count <= 0;
    });
  }
  DH.setCartCount = setCartCount;

  /* ----------------------------------------------------------- mobile menu */
  var toggle = document.getElementById('menu-toggle');
  var mobileNav = document.getElementById('mobile-nav');
  if (toggle && mobileNav) {
    toggle.addEventListener('click', function () {
      var open = mobileNav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      var label = toggle.querySelector('.sr-only');
      if (label) {
        label.textContent = label.getAttribute(open ? 'data-close' : 'data-open') || label.textContent;
      }
    });
  }

  /* --------------------------------------------------- add-to-cart (async) */
  function apiUrl() {
    var script = document.querySelector('script[src*="app.js"]');
    var base = '';
    if (script) {
      base = script.getAttribute('src').replace(/assets\/js\/app\.js.*$/, '');
    }
    return base + 'api/cart.php';
  }
  DH.apiUrl = apiUrl;

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (form.hasAttribute('data-cart-add')) {
      event.preventDefault();
      var itemId = form.getAttribute('data-item-id');
      var qty = form.querySelector('input[name="quantity"]');
      var fd = new FormData();
      fd.append('action', 'add');
      fd.append('item_id', itemId);
      fd.append('quantity', qty ? qty.value : '1');
      var button = form.querySelector('button');
      if (button) { button.disabled = true; }
      DH.post(apiUrl(), fd).then(function (data) {
        if (data.ok) {
          setCartCount(data.cart ? data.cart.count : 0);
          toast(data.message || 'Added to cart', 'success');
        } else {
          toast(data.message || 'Could not add this item', 'error');
        }
      }).catch(function () {
        toast('Network error. Please try again.', 'error');
      }).finally(function () {
        if (button) { button.disabled = false; }
      });
      return;
    }

    var confirmText = form.getAttribute('data-confirm');
    if (confirmText) {
      event.preventDefault();
      askConfirm(confirmText).then(function (ok) {
        if (ok) {
          form.removeAttribute('data-confirm');
          if (form.hasAttribute('data-confirm-keep')) {
            form.setAttribute('data-confirm', confirmText);
          }
          form.submit();
        }
      });
    }
  });

  /* --------------------------------------------------------------- dialog */
  function ensureDialog() {
    var dialog = document.getElementById('dh-confirm');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.className = 'modal';
      dialog.id = 'dh-confirm';
      var inner = document.createElement('div');
      inner.className = 'modal-inner';
      var title = document.createElement('h2');
      title.textContent = 'Confirm';
      var text = document.createElement('p');
      var actions = document.createElement('div');
      actions.className = 'modal-actions';
      var cancel = document.createElement('button');
      cancel.type = 'button';
      cancel.className = 'btn btn-ghost';
      cancel.textContent = 'Cancel';
      var ok = document.createElement('button');
      ok.type = 'button';
      ok.className = 'btn btn-danger';
      ok.textContent = 'Confirm';
      actions.appendChild(cancel);
      actions.appendChild(ok);
      inner.appendChild(title);
      inner.appendChild(text);
      inner.appendChild(actions);
      dialog.appendChild(inner);
      document.body.appendChild(dialog);
    }
    return dialog;
  }

  function askConfirm(message, options) {
    options = options || {};
    var dialog = ensureDialog();
    var title = dialog.querySelector('h2');
    var text = dialog.querySelector('p');
    var cancelBtn = dialog.querySelector('.modal-actions .btn-ghost');
    var okBtn = dialog.querySelector('.modal-actions .btn-danger');
    var isBn = document.documentElement.getAttribute('lang') === 'bn';
    title.textContent = options.title || (isBn ? 'নিশ্চিত করুন' : 'Confirm');
    text.textContent = message;
    cancelBtn.textContent = options.cancel || (isBn ? 'বাতিল' : 'Cancel');
    okBtn.textContent = options.ok || (isBn ? 'হ্যাঁ' : 'Yes');

    return new Promise(function (resolve) {
      function close(value) {
        dialog.close();
        dialog.removeEventListener('close', onClose);
        resolve(value);
      }
      function onClose() {
        dialog.removeEventListener('close', onClose);
        resolve(dialog.returnValue === 'ok');
      }
      cancelBtn.onclick = function () { close(false); };
      okBtn.onclick = function () { close(true); };
      dialog.addEventListener('close', onClose);
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else if (window.confirm(message)) {
        close(true);
      } else {
        close(false);
      }
    });
  }
  DH.confirm = askConfirm;

  /* ----------------------------------------------------------- copy button */
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-copy]');
    if (!trigger) {
      return;
    }
    var value = trigger.getAttribute('data-copy');
    var done = function () {
      toast(trigger.getAttribute('data-copied') || 'Copied', 'success');
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(value).then(done).catch(function () {});
    } else {
      var temp = document.createElement('textarea');
      temp.value = value;
      temp.setAttribute('readonly', '');
      temp.style.position = 'absolute';
      temp.style.left = '-9999px';
      document.body.appendChild(temp);
      temp.select();
      try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
      temp.remove();
    }
  });

  /* ---------------------------------------------- quantity stepper (cart) */
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-qty]');
    if (!button) {
      return;
    }
    var form = button.closest('form');
    if (!form) {
      return;
    }
    var input = form.querySelector('input[name="quantity"]');
    if (!input) {
      return;
    }
    var step = button.getAttribute('data-qty') === 'up' ? 1 : -1;
    var next = Math.max(1, Math.min(99, parseInt(input.value, 10) + step));
    input.value = String(next);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    if (form.hasAttribute('data-auto-submit')) {
      form.submit();
    }
  });

  /* --------------------------------------------- product detail qty total */
  document.addEventListener('input', function (event) {
    if (!event.target.matches('[data-qty-input]')) {
      return;
    }
    var qty = Math.max(1, Math.min(99, parseInt(event.target.value, 10) || 1));
    var unit = parseInt(event.target.getAttribute('data-unit-price'), 10) || 0;
    document.querySelectorAll('[data-detail-total]').forEach(function (el) {
      if (window.DH && window.DH.i18n) {
        el.textContent = window.DH.i18n.money(unit * qty);
      }
    });
    document.querySelectorAll('[data-qty-display]').forEach(function (el) {
      el.textContent = String(qty);
    });
  });

  /* --------------------------------------------------------------- misc */
  document.addEventListener('DOMContentLoaded', function () {
    var countEl = document.getElementById('cart-count');
    if (countEl && countEl.hidden) {
      countEl.hidden = true;
    }
  });
})();
