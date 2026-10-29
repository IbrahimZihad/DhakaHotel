/* global window, document */
/**
 * Single-screen POS client. Prices shown here are display only: every write
 * posts item ids and quantities, and admin/api/pos.php recomputes the bill.
 */
(function () {
  'use strict';

  var configEl = document.getElementById('pos-config');
  if (!configEl) {
    return;
  }

  var config;
  try {
    config = JSON.parse(configEl.textContent);
  } catch (error) {
    return;
  }
  if (!config || !config.endpoint) {
    return;
  }

  var DH = window.DH || {};
  var messages = config.messages || {};
  var labels = config.labels || {};
  var bnMap = { '0': '০', '1': '১', '2': '২', '3': '৩', '4': '৪', '5': '৫', '6': '৬', '7': '৭', '8': '৮', '9': '৯' };
  var bnChars = '০১২৩৪৫৬৭৮৯';

  function byId(id) {
    return document.getElementById(id);
  }

  function bnDigits(value) {
    return String(value).replace(/[0-9]/g, function (digit) {
      return bnMap[digit];
    });
  }

  function latinDigits(value) {
    return String(value).replace(/[০-৯]/g, function (digit) {
      return String(bnChars.indexOf(digit));
    });
  }

  function localNumber(value) {
    return config.lang === 'bn' ? bnDigits(value) : String(value);
  }

  function formatMoney(cents) {
    var negative = cents < 0;
    var abs = Math.abs(Math.round(cents));
    var whole = Math.floor(abs / 100);
    var fraction = abs % 100;
    var text = whole.toLocaleString('en-US');
    if (fraction > 0) {
      text += '.' + (fraction < 10 ? '0' : '') + fraction;
    }
    text = localNumber(text);
    return (negative ? '−' : '') + '৳' + text;
  }

  function decimalFromCents(cents) {
    var abs = Math.abs(Math.round(cents));
    var whole = Math.floor(abs / 100);
    var fraction = abs % 100;
    return String(whole) + (fraction > 0 ? '.' + (fraction < 10 ? '0' : '') + fraction : '');
  }

  function parseMoney(raw) {
    if (raw === null || raw === undefined) {
      return null;
    }
    var value = latinDigits(String(raw)).trim().replace(/[৳,\s]/g, '');
    if (!/^\d{1,8}(\.\d{1,2})?$/.test(value)) {
      return null;
    }
    return Math.round(parseFloat(value) * 100);
  }

  function cleanMoneyField(input) {
    var cleaned = latinDigits(input.value).trim().replace(/[৳,\s]/g, '');
    if (cleaned !== input.value) {
      input.value = cleaned;
    }
  }

  function newKey() {
    var bytes = new Uint8Array(16);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(bytes);
    } else {
      for (var i = 0; i < bytes.length; i++) {
        bytes[i] = Math.floor(Math.random() * 256);
      }
    }
    var out = '';
    for (var j = 0; j < bytes.length; j++) {
      out += (bytes[j] < 16 ? '0' : '') + bytes[j].toString(16);
    }
    return out;
  }

  function toast(message, type) {
    if (DH.toast) {
      DH.toast(message, type);
      return;
    }
    window.alert(message);
  }

  function confirmAction(message) {
    if (DH.confirm) {
      return DH.confirm(message);
    }
    return Promise.resolve(window.confirm(message));
  }

  var els = {
    grid: byId('pos-grid'),
    search: byId('pos-search'),
    category: byId('pos-category'),
    cats: byId('pos-cats'),
    refresh: byId('pos-refresh'),
    lines: byId('pos-lines'),
    billNumber: byId('pos-bill-number'),
    type: byId('pos-type'),
    tableField: byId('pos-table-field'),
    table: byId('pos-table'),
    customer: byId('pos-customer'),
    phone: byId('pos-phone'),
    addressField: byId('pos-address-field'),
    address: byId('pos-address'),
    notes: byId('pos-notes'),
    discount: byId('pos-discount'),
    discountAmount: byId('pos-discount-amount'),
    subtotal: byId('pos-subtotal'),
    deliveryRow: byId('pos-delivery-row'),
    delivery: byId('pos-delivery'),
    taxRate: byId('pos-tax-rate'),
    tax: byId('pos-tax'),
    total: byId('pos-total'),
    method: byId('pos-method'),
    received: byId('pos-received'),
    referenceField: byId('pos-reference-field'),
    reference: byId('pos-reference'),
    changeLabel: byId('pos-change-label'),
    changeAmount: byId('pos-change-amount'),
    change: byId('pos-change'),
    complete: byId('pos-complete'),
    generate: byId('pos-generate'),
    print: byId('pos-print'),
    save: byId('pos-save'),
    newBill: byId('pos-new'),
    tabCount: byId('pos-tab-count'),
    tabTotal: byId('pos-tab-total'),
    tabs: document.querySelectorAll('[data-pos-view]'),
    shell: document.querySelector('.pos-shell')
  };

  if (!els.grid || !els.lines || !els.type) {
    return;
  }

  var tiles = Array.prototype.slice.call(els.grid.querySelectorAll('.pos-item'));
  var emptyTemplate = null;
  var emptyNode = byId('pos-empty');
  if (emptyNode) {
    emptyTemplate = emptyNode.cloneNode(true);
  }
  var noMatchNode = null;

  var meta = config.initialMeta || {};
  var state = {
    lines: [],
    activeCat: 0,
    resumeId: parseInt(config.resumeId, 10) || 0,
    orderId: parseInt(config.resumeId, 10) || 0,
    orderNumber: config.resumeNumber || '',
    receiptUrl: '',
    key: newKey(),
    locked: false,
    busy: false,
    receivedTouched: false
  };

  (config.initialLines || []).forEach(function (line) {
    var id = parseInt(line.id, 10);
    if (!id) {
      return;
    }
    state.lines.push({
      id: id,
      name_bn: line.name_bn || '',
      name_en: line.name_en || '',
      price_cents: parseInt(line.price_cents, 10) || 0,
      quantity: Math.max(1, Math.min(99, parseInt(line.quantity, 10) || 1))
    });
  });

  function lineName(line) {
    var primary = config.lang === 'bn' ? line.name_bn : line.name_en;
    return primary || line.name_en || line.name_bn || '';
  }

  function receiptUrlFor(orderId) {
    return config.receiptBase + '?id=' + orderId + '&paper=' + encodeURIComponent(config.receiptPaper || '80');
  }

  if (state.resumeId) {
    state.receiptUrl = receiptUrlFor(state.resumeId);
  }

  /* --------------------------------------------------------------- totals */
  function computeTotals() {
    var subtotal = 0;
    state.lines.forEach(function (line) {
      subtotal += line.price_cents * line.quantity;
    });

    var discount = parseMoney(els.discount.value);
    if (discount === null) {
      discount = 0;
    }
    discount = Math.max(0, Math.min(discount, subtotal));

    var delivery = els.type.value === 'delivery' ? Math.max(0, parseInt(config.deliveryFeeCents, 10) || 0) : 0;
    var rate = Math.max(0, Math.min(10000, parseInt(config.taxBasisPoints, 10) || 0));
    var taxable = subtotal - discount;
    var tax = Math.round(taxable * rate / 10000);

    return {
      subtotal: subtotal,
      discount: discount,
      delivery: delivery,
      tax: tax,
      grandTotal: taxable + delivery + tax
    };
  }

  function taxRateText() {
    var value = (parseInt(config.taxBasisPoints, 10) || 0) / 100;
    var text = value.toFixed(2).replace(/\.?0+$/, '');
    if (text === '') {
      text = '0';
    }
    return localNumber(text);
  }

  function isReferenceMethod(method) {
    return method === 'bkash' || method === 'nagad' || method === 'card' || method === 'other';
  }

  function syncFields() {
    var type = els.type.value;
    var method = els.method.value;

    els.tableField.hidden = type !== 'dine_in';
    els.addressField.hidden = type !== 'delivery';
    els.deliveryRow.hidden = type !== 'delivery' || (parseInt(config.deliveryFeeCents, 10) || 0) <= 0;

    els.referenceField.hidden = !isReferenceMethod(method);
    var receivedField = els.received.closest('.field');
    if (receivedField) {
      receivedField.hidden = method !== 'cash';
    }
  }

  function updateChange(totals) {
    var method = els.method.value;
    var label = messages.change || 'Change due';
    var amount = 0;
    var negative = false;

    if (method === 'cod') {
      label = messages.remaining || 'Remaining';
      amount = totals.grandTotal;
    } else if (method !== 'cash') {
      amount = 0;
    } else {
      var received = parseMoney(els.received.value);
      if (received !== null && received > 0) {
        if (received >= totals.grandTotal) {
          amount = received - totals.grandTotal;
        } else {
          label = messages.remaining || 'Remaining';
          amount = totals.grandTotal - received;
          negative = true;
        }
      }
    }

    els.changeLabel.textContent = label;
    els.changeAmount.textContent = formatMoney(amount);
    els.change.classList.toggle('is-negative', negative);
  }

  function updateTotals() {
    var totals = computeTotals();

    if (els.discount) {
      var raw = els.discount.value.trim();
      var parsed = parseMoney(raw);
      var invalid = raw !== '' && raw !== '0' && (parsed === null || parsed > totals.subtotal);
      els.discount.classList.toggle('is-invalid', invalid);
      els.discount.setAttribute('aria-invalid', invalid ? 'true' : 'false');
    }

    els.subtotal.textContent = formatMoney(totals.subtotal);
    els.discountAmount.textContent = formatMoney(totals.discount);
    els.delivery.textContent = formatMoney(totals.delivery);
    els.taxRate.textContent = taxRateText();
    els.tax.textContent = formatMoney(totals.tax);
    els.total.textContent = formatMoney(totals.grandTotal);

    if (els.tabTotal) {
      els.tabTotal.textContent = formatMoney(totals.grandTotal);
    }
    if (els.tabCount) {
      var itemCount = 0;
      state.lines.forEach(function (line) {
        itemCount += line.quantity;
      });
      els.tabCount.textContent = localNumber(itemCount);
    }

    if (els.method.value === 'cash' && !state.receivedTouched && !state.locked) {
      els.received.value = totals.grandTotal > 0 ? decimalFromCents(totals.grandTotal) : '';
    }

    updateChange(totals);
  }

  /* ---------------------------------------------------------------- lines */
  function buildLine(line) {
    var row = document.createElement('div');
    row.className = 'pos-line';
    row.setAttribute('data-line-id', String(line.id));

    var main = document.createElement('div');
    main.className = 'pos-line-main';

    var name = document.createElement('span');
    name.className = 'pos-line-name';
    name.textContent = lineName(line);

    var total = document.createElement('span');
    total.className = 'pos-line-total';
    total.textContent = formatMoney(line.price_cents * line.quantity);

    main.appendChild(name);
    main.appendChild(total);

    var controls = document.createElement('div');
    controls.className = 'pos-line-controls';

    var qty = document.createElement('span');
    qty.className = 'pos-qty';

    var decrease = document.createElement('button');
    decrease.type = 'button';
    decrease.setAttribute('data-pos-qty', '-1');
    decrease.setAttribute('aria-label', (labels.decrease || 'Decrease') + ' ' + lineName(line));
    decrease.textContent = '−';

    var count = document.createElement('span');
    count.textContent = localNumber(line.quantity);

    var increase = document.createElement('button');
    increase.type = 'button';
    increase.setAttribute('data-pos-qty', '1');
    increase.setAttribute('aria-label', (labels.increase || 'Increase') + ' ' + lineName(line));
    increase.textContent = '+';

    qty.appendChild(decrease);
    qty.appendChild(count);
    qty.appendChild(increase);

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'pos-line-remove';
    remove.setAttribute('data-pos-remove', '1');
    remove.textContent = labels.remove || 'Remove';

    controls.appendChild(qty);
    controls.appendChild(remove);

    row.appendChild(main);
    row.appendChild(controls);

    return row;
  }

  function renderLines() {
    els.lines.innerHTML = '';

    if (state.lines.length === 0) {
      if (emptyTemplate) {
        els.lines.appendChild(emptyTemplate.cloneNode(true));
      }
      return;
    }

    state.lines.forEach(function (line) {
      els.lines.appendChild(buildLine(line));
    });
  }

  function findLine(id) {
    for (var i = 0; i < state.lines.length; i++) {
      if (state.lines[i].id === id) {
        return i;
      }
    }
    return -1;
  }

  function addItem(tile) {
    if (state.locked || state.busy) {
      return;
    }

    var id = parseInt(tile.getAttribute('data-item-id'), 10);
    if (!id) {
      return;
    }

    var index = findLine(id);
    if (index === -1) {
      state.lines.push({
        id: id,
        name_bn: tile.getAttribute('data-name-bn') || '',
        name_en: tile.getAttribute('data-name-en') || '',
        price_cents: parseInt(tile.getAttribute('data-price'), 10) || 0,
        quantity: 1
      });
    } else if (state.lines[index].quantity < 99) {
      state.lines[index].quantity += 1;
    } else {
      return;
    }

    renderLines();
    updateTotals();
  }

  function changeQuantity(id, step) {
    if (state.locked || state.busy) {
      return;
    }

    var index = findLine(id);
    if (index === -1) {
      return;
    }

    var next = state.lines[index].quantity + step;
    if (next < 1) {
      state.lines.splice(index, 1);
    } else {
      state.lines[index].quantity = Math.min(99, next);
    }

    renderLines();
    updateTotals();
  }

  function removeLine(id) {
    if (state.locked || state.busy) {
      return;
    }

    var index = findLine(id);
    if (index !== -1) {
      state.lines.splice(index, 1);
      renderLines();
      updateTotals();
    }
  }

  /* --------------------------------------------------------------- filter */
  function applyFilter() {
    var query = els.search ? els.search.value.trim().toLowerCase() : '';
    var cat = state.activeCat;
    var visible = 0;

    tiles.forEach(function (tile) {
      var matchCat = cat === 0 || parseInt(tile.getAttribute('data-cat'), 10) === cat;
      var haystack = tile.getAttribute('data-search') || '';
      var matchText = query === '' || haystack.indexOf(query) !== -1;
      var show = matchCat && matchText;
      tile.hidden = !show;
      if (show) {
        visible += 1;
      }
    });

    if (noMatchNode) {
      noMatchNode.remove();
      noMatchNode = null;
    }

    if (visible === 0 && tiles.length > 0) {
      noMatchNode = document.createElement('p');
      noMatchNode.className = 'pos-empty';
      noMatchNode.style.gridColumn = '1 / -1';
      noMatchNode.textContent = messages.noMatches || 'No items match this search.';
      els.grid.appendChild(noMatchNode);
    }
  }

  function setCategory(cat) {
    state.activeCat = cat;

    if (els.cats) {
      els.cats.querySelectorAll('.pos-cat').forEach(function (chip) {
        var active = parseInt(chip.getAttribute('data-cat'), 10) === cat;
        chip.classList.toggle('is-active', active);
        chip.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }
    if (els.category) {
      els.category.value = String(cat);
    }

    applyFilter();
  }

  /* ---------------------------------------------------------------- state */
  function setBusy(busy) {
    state.busy = busy;
    refreshButtons();
  }

  function refreshButtons() {
    if (els.complete) {
      els.complete.disabled = state.busy || state.locked;
    }
    if (els.generate) {
      els.generate.disabled = state.busy || state.locked;
    }
    if (els.save) {
      els.save.disabled = state.busy || state.locked;
    }
    if (els.print) {
      els.print.disabled = state.busy || !state.receiptUrl;
    }
    if (els.newBill) {
      els.newBill.disabled = state.busy;
    }
  }

  function setEditable(editable) {
    [els.type, els.table, els.customer, els.phone, els.address, els.notes, els.discount,
      els.method, els.received, els.reference].forEach(function (field) {
      if (field) {
        field.disabled = !editable;
      }
    });

    tiles.forEach(function (tile) {
      tile.disabled = !editable;
    });
  }

  function lockUi() {
    state.locked = true;
    setEditable(false);
    refreshButtons();
  }

  function unlockUi() {
    state.locked = false;
    setEditable(true);
    refreshButtons();
  }

  function resetBill() {
    state.lines = [];
    state.resumeId = 0;
    state.orderId = 0;
    state.orderNumber = '';
    state.receiptUrl = '';
    state.key = newKey();
    state.receivedTouched = false;

    els.type.value = 'dine_in';
    if (els.table) { els.table.value = ''; }
    if (els.customer) { els.customer.value = ''; }
    if (els.phone) { els.phone.value = ''; }
    if (els.address) { els.address.value = ''; }
    if (els.notes) { els.notes.value = ''; }
    if (els.discount) { els.discount.value = ''; }
    if (els.method) { els.method.value = 'cash'; }
    if (els.received) { els.received.value = ''; }
    if (els.reference) { els.reference.value = ''; }
    if (els.billNumber) { els.billNumber.textContent = config.emptyBill || ''; }

    unlockUi();
    syncFields();
    renderLines();
    updateTotals();

    if (window.history && window.history.replaceState) {
      try {
        window.history.replaceState({}, '', window.location.pathname);
      } catch (error) { /* ignore */ }
    }
  }

  /* ---------------------------------------------------------------- submit */
  function buildPayload(action) {
    return {
      action: action,
      resume_id: state.resumeId,
      idempotency_key: state.key,
      items: state.lines.map(function (line) {
        return { id: line.id, quantity: line.quantity };
      }),
      order_type: els.type.value,
      table_number: els.table ? els.table.value.trim() : '',
      customer_name: els.customer ? els.customer.value.trim() : '',
      customer_phone: els.phone ? els.phone.value.trim() : '',
      delivery_address: els.address ? els.address.value.trim() : '',
      notes: els.notes ? els.notes.value.trim() : '',
      discount: els.discount ? els.discount.value.trim() : '',
      payment_method: els.method ? els.method.value : 'cash',
      amount_received: els.received ? els.received.value.trim() : '',
      reference: els.reference ? els.reference.value.trim() : ''
    };
  }

  function validateBill() {
    if (state.lines.length === 0) {
      toast(messages.empty || 'Add at least one item.', 'error');
      return false;
    }

    var raw = els.discount ? els.discount.value.trim() : '';
    if (raw !== '' && raw !== '0') {
      var parsed = parseMoney(raw);
      if (parsed === null || parsed > computeTotals().subtotal) {
        toast(messages.amount || 'Check the amount.', 'error');
        if (els.discount) { els.discount.focus(); }
        return false;
      }
    }

    return true;
  }

  function validatePayment(totals) {
    if (!validateBill()) {
      return false;
    }

    var method = els.method.value;

    if (isReferenceMethod(method) && els.reference.value.trim() === '') {
      toast(messages.reference || 'Add a payment reference.', 'error');
      els.reference.focus();
      return false;
    }

    if (method === 'cash') {
      var received = parseMoney(els.received.value);
      if (received === null || received <= 0) {
        toast(messages.amount || 'Enter the amount received.', 'error');
        els.received.focus();
        return false;
      }
    }

    return true;
  }

  function applyOrder(order, receiptUrl) {
    if (!order) {
      return;
    }

    if (order.id) {
      state.orderId = order.id;
      if (!state.resumeId) {
        state.resumeId = order.id;
      }
    }
    if (order.number) {
      state.orderNumber = order.number;
      if (els.billNumber) {
        els.billNumber.textContent = order.number;
      }
    }

    if (receiptUrl) {
      state.receiptUrl = receiptUrl;
    } else if (state.orderId) {
      state.receiptUrl = receiptUrlFor(state.orderId);
    }
  }

  function handleResult(action, payload) {
    var data = payload || {};

    applyOrder(data.order, data.receipt_url);

    if (data.order && data.order.payment_status && data.order.payment_status !== 'unpaid') {
      lockUi();
    }

    if (!data.ok) {
      var failure = data.error === 'csrf'
        ? (messages.csrf || 'Your session expired. Reload the page.')
        : (data.message || messages.generic || 'Something went wrong.');
      toast(failure, 'error');
      refreshButtons();
      return;
    }

    toast(data.message || 'Saved', 'success');

    if (action === 'complete_payment') {
      lockUi();
    }

    refreshButtons();
  }

  function submit(action) {
    if (state.busy || state.locked) {
      return;
    }

    var totals = computeTotals();

    if (action === 'complete_payment' && !validatePayment(totals)) {
      return;
    }
    if (action !== 'complete_payment' && !validateBill()) {
      return;
    }

    setBusy(true);

    fetch(config.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': DH.csrf || '',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: JSON.stringify(buildPayload(action))
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, message: messages.generic || 'Unexpected response.' };
      });
    }).then(function (data) {
      handleResult(action, data);
    }).catch(function () {
      toast(messages.generic || 'Network error. Please try again.', 'error');
    }).finally(function () {
      setBusy(false);
    });
  }

  function printReceipt() {
    if (!state.receiptUrl) {
      return;
    }
    var win = window.open(state.receiptUrl, '_blank');
    if (!win) {
      window.location.href = state.receiptUrl;
    }
  }

  /* ---------------------------------------------------------------- events */
  els.grid.addEventListener('click', function (event) {
    var tile = event.target.closest ? event.target.closest('.pos-item') : null;
    if (tile) {
      addItem(tile);
    }
  });

  els.lines.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-pos-qty], [data-pos-remove]') : null;
    if (!button) {
      return;
    }

    var row = button.closest('.pos-line');
    var id = row ? parseInt(row.getAttribute('data-line-id'), 10) : 0;
    if (!id) {
      return;
    }

    if (button.hasAttribute('data-pos-remove')) {
      removeLine(id);
    } else {
      changeQuantity(id, parseInt(button.getAttribute('data-pos-qty'), 10) || 0);
    }
  });

  if (els.search) {
    els.search.addEventListener('input', applyFilter);
  }

  if (els.category) {
    els.category.addEventListener('change', function () {
      setCategory(parseInt(els.category.value, 10) || 0);
    });
  }

  if (els.cats) {
    els.cats.addEventListener('click', function (event) {
      var chip = event.target.closest ? event.target.closest('.pos-cat') : null;
      if (chip) {
        setCategory(parseInt(chip.getAttribute('data-cat'), 10) || 0);
      }
    });
  }

  if (els.tabs.length && els.shell) {
    els.tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var view = tab.getAttribute('data-pos-view') === 'bill' ? 'bill' : 'catalog';
        els.shell.setAttribute('data-view', view);
        els.tabs.forEach(function (other) {
          var active = other === tab;
          other.classList.toggle('is-active', active);
          other.setAttribute('aria-selected', active ? 'true' : 'false');
        });
      });
    });
  }

  document.querySelectorAll('.lang-switch a').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (state.lines.length === 0 && !state.orderId) {
        return;
      }
      event.preventDefault();
      confirmAction(messages.confirmLeave || 'Discard the current bill?').then(function (ok) {
        if (ok) {
          window.location.href = link.href;
        }
      });
    });
  });

  if (els.refresh) {
    els.refresh.addEventListener('click', function () {
      if (state.lines.length === 0 && !state.orderId) {
        window.location.reload();
        return;
      }
      confirmAction(messages.confirmRefresh || 'Reload the menu?').then(function (ok) {
        if (ok) {
          window.location.reload();
        }
      });
    });
  }

  els.type.addEventListener('change', function () {
    syncFields();
    updateTotals();
  });

  if (els.method) {
    els.method.addEventListener('change', function () {
      state.receivedTouched = false;
      syncFields();
      updateTotals();
    });
  }

  if (els.received) {
    els.received.addEventListener('input', function () {
      state.receivedTouched = true;
      updateChange(computeTotals());
    });
    els.received.addEventListener('blur', function () {
      cleanMoneyField(els.received);
      updateChange(computeTotals());
    });
  }

  if (els.discount) {
    els.discount.addEventListener('input', updateTotals);
    els.discount.addEventListener('blur', function () {
      cleanMoneyField(els.discount);
      updateTotals();
    });
  }

  if (els.complete) {
    els.complete.addEventListener('click', function () {
      if (state.busy || state.locked) {
        return;
      }
      if (!validatePayment(computeTotals())) {
        return;
      }
      confirmAction(messages.confirmPayment || 'Record this payment?').then(function (ok) {
        if (ok) {
          submit('complete_payment');
        }
      });
    });
  }

  if (els.generate) {
    els.generate.addEventListener('click', function () {
      submit('generate_bill');
    });
  }

  if (els.save) {
    els.save.addEventListener('click', function () {
      submit('save_pending');
    });
  }

  if (els.print) {
    els.print.addEventListener('click', printReceipt);
  }

  if (els.newBill) {
    els.newBill.addEventListener('click', function () {
      if (state.busy) {
        return;
      }
      if (state.lines.length === 0 && !state.orderId) {
        resetBill();
        return;
      }
      confirmAction(messages.confirmNew || 'Start a new bill?').then(function (ok) {
        if (ok) {
          resetBill();
        }
      });
    });
  }

  /* ---------------------------------------------------------------- start */
  if (els.type) {
    els.type.value = meta.order_type || 'dine_in';
  }
  if (els.table) {
    els.table.value = meta.table_number || '';
  }
  if (els.customer) {
    els.customer.value = meta.customer_name || '';
  }
  if (els.phone) {
    els.phone.value = meta.customer_phone || '';
  }
  if (els.address) {
    els.address.value = meta.delivery_address || '';
  }
  if (els.notes) {
    els.notes.value = meta.notes || '';
  }
  if (els.discount) {
    els.discount.value = meta.discount && meta.discount !== '0.00' ? meta.discount : '';
  }

  syncFields();
  setCategory(0);
  renderLines();
  updateTotals();
  refreshButtons();
})();
