/* global window, document */
(function () {
  'use strict';

  var sidebar = document.getElementById('admin-sidebar');
  var backdrop = document.querySelector('[data-sidebar-backdrop]');
  var toggle = document.querySelector('[data-sidebar-toggle]');

  function closeSidebar() {
    if (!sidebar) {
      return;
    }
    sidebar.classList.remove('is-open');
    if (backdrop) {
      backdrop.classList.remove('is-open');
    }
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
    }
  }

  if (sidebar && toggle) {
    toggle.addEventListener('click', function () {
      var open = sidebar.classList.toggle('is-open');
      if (backdrop) {
        backdrop.classList.toggle('is-open', open);
      }
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeSidebar);
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeSidebar();
    }
  });

  /* Print buttons */
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-print]');
    if (!trigger) {
      return;
    }
    event.preventDefault();
    window.print();
  });

  /* Local image preview before the upload is saved */
  document.querySelectorAll('[data-image-input]').forEach(function (input) {
    var preview = document.getElementById(input.getAttribute('data-image-input'));
    if (!preview) {
      return;
    }
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file || !/^image\//.test(file.type)) {
        return;
      }
      var reader = new FileReader();
      reader.onload = function () {
        preview.src = String(reader.result);
      };
      reader.readAsDataURL(file);
    });
  });

  /* Payment method reference fields appear only when needed */
  document.querySelectorAll('[data-payment-method]').forEach(function (select) {
    var form = select.closest('form');
    var reference = form ? form.querySelector('[data-payment-reference]') : null;
    if (!reference) {
      return;
    }
    var update = function () {
      var needsReference = ['bkash', 'nagad', 'card', 'other'].indexOf(select.value) !== -1;
      reference.hidden = !needsReference;
      var input = reference.querySelector('input');
      if (input) {
        input.required = needsReference;
      }
    };
    select.addEventListener('change', update);
    update();
  });
})();
