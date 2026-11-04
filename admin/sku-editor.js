(function () {
  'use strict';
  var root = document.getElementById('skuRows');
  if (!root) return;
  var template = document.getElementById('skuRowTemplate');
  var add = document.getElementById('skuAdd');
  var empty = document.getElementById('skuEmpty');
  var status = document.getElementById('skuStatus');
  var hint = document.getElementById('skuPriceHint');
  var min = document.getElementById('price');
  var max = document.getElementById('price_max');
  var index = root.children.length;
  function update() {
    var rows = Array.from(root.querySelectorAll('.adm-sku-row'));
    var active = rows.filter(function (row) {
      return Array.from(row.querySelectorAll('input')).some(function (input) { return input.value.trim() !== '' || input.validity.badInput; });
    });
    rows.forEach(function (row) {
      row.querySelectorAll('input').forEach(function (input) { input.required = active.indexOf(row) >= 0; });
    });
    min.readOnly = max.readOnly = active.length > 0;
    min.required = active.length === 0;
    hint.textContent = active.length ? hint.dataset.auto : hint.dataset.manual;
    empty.hidden = rows.length > 0;
    add.disabled = rows.length >= 100;
    var prices = [];
    var complete = active.every(function (row) {
      var inputs = Array.from(row.querySelectorAll('input'));
      var price = row.querySelector('[data-sku-field="price"]');
      if (!/^[0-9]+$/.test(price.value) || !inputs.every(function (input) { return input.value.trim() !== '' && input.validity.valid; })) return false;
      prices.push(Number(price.value));
      return true;
    });
    if (active.length && complete) {
      min.value = String(Math.min.apply(null, prices));
      var upper = Math.max.apply(null, prices);
      max.value = upper === Number(min.value) ? '' : String(upper);
    }
    status.textContent = active.length && !complete ? status.dataset.incomplete : (rows.length >= 100 ? status.dataset.limit : '');
  }
  add.addEventListener('click', function () {
    if (root.children.length >= 100) return;
    var fragment = template.content.cloneNode(true);
    fragment.querySelectorAll('[name], [id], label[for]').forEach(function (el) {
      ['name', 'id', 'for'].forEach(function (attr) {
        if (el.hasAttribute(attr)) el.setAttribute(attr, el.getAttribute(attr).replace(/__INDEX__/g, String(index)));
      });
    });
    index++;
    root.appendChild(fragment);
    update();
    root.lastElementChild.querySelector('input').focus();
  });
  root.addEventListener('click', function (e) {
    var remove = e.target.closest('.adm-sku-remove');
    if (!remove) return;
    remove.closest('.adm-sku-row').remove();
    update();
    add.focus();
  });
  root.addEventListener('input', update);
  update();
})();
