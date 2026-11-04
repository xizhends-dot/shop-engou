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
  var groupRoot = document.getElementById('skuGroupRows');
  var groupAdd = document.getElementById('skuGroupAdd');
  var groupStatus = document.getElementById('skuGroupStatus');
  var generate = document.getElementById('skuGenerate');
  var messages = JSON.parse(document.getElementById('skuEditorMessages').textContent);
  var index = root.children.length;
  var groupIndex = groupRoot.children.length;
  var groups = [];
  var groupsValid = true;
  function cloneTemplate(source, token, value) {
    var fragment = source.content.cloneNode(true);
    fragment.querySelectorAll('*').forEach(function (el) {
      ['name', 'id', 'for', 'data-row-index', 'data-group-key'].forEach(function (attr) {
        if (el.hasAttribute(attr)) el.setAttribute(attr, el.getAttribute(attr).split(token).join(String(value)));
      });
    });
    return fragment;
  }
  function readGroups() {
    groups = [];
    groupsValid = true;
    var names = new Set();
    groupRoot.querySelectorAll('.adm-sku-group-row').forEach(function (row) {
      var name = row.querySelector('.js-sku-group-name');
      var options = row.querySelector('.js-sku-group-options');
      var title = name.value.trim();
      var choices = options.value.split(/[\r\n,，、]+/).map(function (s) { return s.trim(); }).filter(Boolean);
      name.setCustomValidity(''); options.setCustomValidity('');
      name.required = options.required = Boolean(title || choices.length);
      if (!title && !choices.length) return;
      var good = title && Array.from(title).length <= 60 && !names.has(title) && choices.length > 0 && choices.length <= 30 && new Set(choices).size === choices.length && choices.every(function (s) { return Array.from(s).length <= 100; });
      if (!good) { groupsValid = false; options.setCustomValidity(messages.invalid); }
      names.add(title);
      groups.push({key:row.dataset.groupKey, name:title, options:choices});
    });
    if (groups.length > 3) groupsValid = false;
    groupAdd.disabled = groupRoot.children.length >= 3;
    generate.disabled = !groups.length || !groupsValid;
  }
  function syncOptions(row) {
    var target = row.querySelector('.adm-sku-options');
    var previous = new Map();
    target.querySelectorAll('select').forEach(function (s) { previous.set(s.dataset.groupKey, s.value); });
    target.textContent = '';
    target.hidden = !groups.length;
    groups.forEach(function (group, gi) {
      var field = document.createElement('div'); field.className = 'adm-sku-field';
      var label = document.createElement('label'); label.textContent = group.name;
      var select = document.createElement('select');
      select.id = 'sku-' + row.dataset.rowIndex + '-option-' + gi;
      select.name = 'skus[' + row.dataset.rowIndex + '][options][' + gi + ']';
      select.dataset.groupKey = group.key; label.htmlFor = select.id;
      select.add(new Option(messages.choose, ''));
      var selected = previous.get(group.key) || '';
      group.options.forEach(function (choice) { select.add(new Option(choice, choice)); });
      if (selected && group.options.indexOf(selected) < 0) select.add(new Option(selected + ' (' + messages.missing + ')', selected));
      select.value = selected;
      field.appendChild(label); field.appendChild(select); target.appendChild(field);
    });
    row.querySelector('[data-sku-field="name"]').maxLength = groups.length ? 400 : 200;
  }
  function update() {
    var rows = Array.from(root.querySelectorAll('.adm-sku-row'));
    var active = rows.filter(function (row) {
      return Array.from(row.querySelectorAll('input, select')).some(function (input) { return input.value.trim() !== '' || input.validity.badInput; });
    });
    if (groups.length && !active.length) min.setCustomValidity(messages.no_groups);
    else min.setCustomValidity('');
    var combinations = new Set();
    rows.forEach(function (row) {
      row.querySelectorAll('input, select').forEach(function (input) {
        input.required = active.indexOf(row) >= 0 && !(groups.length && input.dataset.skuField === 'name');
        input.setCustomValidity('');
      });
      var selectors = Array.from(row.querySelectorAll('select'));
      selectors.forEach(function (select, gi) {
        if (select.value && (!groups[gi] || groups[gi].options.indexOf(select.value) < 0)) select.setCustomValidity(messages.missing);
      });
      if (groups.length && selectors.length === groups.length && selectors.every(function (s) { return s.value !== ''; })) {
        var key = JSON.stringify(selectors.map(function (s) { return s.value; }));
        if (combinations.has(key)) selectors[0].setCustomValidity(messages.duplicate);
        combinations.add(key);
      }
    });
    min.readOnly = max.readOnly = active.length > 0;
    min.required = active.length === 0 && !groups.length;
    hint.textContent = active.length ? hint.dataset.auto : hint.dataset.manual;
    empty.hidden = rows.length > 0;
    add.disabled = rows.length >= 100;
    var prices = [];
    var complete = groupsValid && active.every(function (row) {
      var inputs = Array.from(row.querySelectorAll('input, select'));
      var price = row.querySelector('[data-sku-field="price"]');
      if (!/^[0-9]+$/.test(price.value) || !inputs.every(function (input) { return input.validity.valid; })) return false;
      prices.push(Number(price.value)); return true;
    });
    if (active.length && complete) {
      min.value = String(Math.min.apply(null, prices));
      var upper = Math.max.apply(null, prices);
      max.value = upper === Number(min.value) ? '' : String(upper);
    }
    status.textContent = active.length && !complete ? status.dataset.incomplete : (rows.length >= 100 ? status.dataset.limit : '');
  }
  function addRow(values) {
    root.appendChild(cloneTemplate(template, '__INDEX__', index++));
    var row = root.lastElementChild;
    syncOptions(row);
    if (values) {
      ['id','name','price'].forEach(function (key) { row.querySelector('[data-sku-field="' + key + '"]').value = values[key]; });
      row.querySelectorAll('select').forEach(function (select, i) { select.value = values.options[i]; });
    }
    return row;
  }
  add.addEventListener('click', function () {
    if (root.children.length >= 100) return;
    var row = addRow(); update(); row.querySelector('input').focus();
  });
  root.addEventListener('click', function (e) {
    var remove = e.target.closest('.adm-sku-remove'); if (!remove) return;
    remove.closest('.adm-sku-row').remove(); update(); add.focus();
  });
  root.addEventListener('input', update);
  root.addEventListener('change', update);
  groupAdd.addEventListener('click', function () {
    if (groupRoot.children.length >= 3) return;
    groupRoot.appendChild(cloneTemplate(document.getElementById('skuGroupTemplate'), '__GROUP__', groupIndex++));
    readGroups(); groupRoot.lastElementChild.querySelector('input').focus();
  });
  function groupsChanged() {
    readGroups(); root.querySelectorAll('.adm-sku-row').forEach(syncOptions); update();
    groupStatus.textContent = groupsValid ? messages.changed : messages.invalid;
  }
  groupRoot.addEventListener('change', groupsChanged);
  groupRoot.addEventListener('click', function (e) {
    var remove = e.target.closest('.js-sku-group-remove'); if (!remove) return;
    remove.closest('.adm-sku-group-row').remove(); groupsChanged(); groupAdd.focus();
  });
  generate.addEventListener('click', function () {
    readGroups();
    if (!groups.length || !groupsValid) { groupStatus.textContent = messages.no_groups; return; }
    var count = groups.reduce(function (total, group) { return total * group.options.length; }, 1);
    if (count > 100) { groupStatus.textContent = messages.too_many; return; }
    var combos = [[]];
    groups.forEach(function (group) {
      var next = []; combos.forEach(function (values) { group.options.forEach(function (value) { next.push(values.concat(value)); }); }); combos = next;
    });
    var existing = new Set(); var ids = new Set();
    root.querySelectorAll('.adm-sku-row').forEach(function (row) {
      existing.add(JSON.stringify(Array.from(row.querySelectorAll('select')).map(function (s) { return s.value; })));
      ids.add(row.querySelector('[data-sku-field="id"]').value.toLowerCase());
    });
    var missing = combos.filter(function (values) { return !existing.has(JSON.stringify(values)); });
    if (root.children.length + missing.length > 100) { groupStatus.textContent = messages.too_many; return; }
    var serial = 1;
    missing.forEach(function (values) {
      var id; do { id = 'SKU-' + serial++; } while (ids.has(id.toLowerCase())); ids.add(id.toLowerCase());
      addRow({id:id, name:values.join(' / '), price:'', options:values});
    });
    update(); groupStatus.textContent = messages.generated.replace('{n}', String(missing.length));
    if (missing.length) root.lastElementChild.querySelector('[data-sku-field="price"]').focus();
  });
  readGroups(); root.querySelectorAll('.adm-sku-row').forEach(syncOptions); update();
})();
