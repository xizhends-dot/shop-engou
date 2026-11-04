(function () {
  'use strict';

  // 価格・属性・SKUの状態を一つの問い合わせ更新処理に集約する。
  var inquiry = document.getElementById('inquiryBtn');
  var attrs = document.getElementById('dAttrs');
  var skuOptions = document.getElementById('skuOptions');
  var price = document.getElementById('productPrice');
  var status = document.getElementById('skuStatus');
  var reset = document.getElementById('skuReset');
  var dataNode = document.getElementById('skuData');
  var radios = skuOptions && !dataNode ? skuOptions.querySelectorAll('input[name="product_sku"]') : [];
  var buttons = skuOptions && dataNode ? skuOptions.querySelectorAll('.sku-group__option') : [];
  var initialPrice = price ? price.cloneNode(true) : null;
  var initialStatus = status ? status.textContent : '';
  var groupData = readGroupData();

  function validPrice(value) {
    return /^(0|[1-9][0-9]{0,9})$/.test(String(value)) && Number(value) <= 2147483647;
  }

  function readGroupData() {
    if (!dataNode) return null;
    try {
      var data = JSON.parse(dataNode.textContent);
      if (!data || !Array.isArray(data.groups) || !data.groups.length || !Array.isArray(data.skus)) return null;
      if (!data.groups.every(function (group) {
        return group && typeof group.name === 'string' && Array.isArray(group.options) && group.options.length
          && group.options.every(function (option) { return typeof option === 'string' && option !== ''; });
      })) return null;
      if (!data.skus.length || !data.skus.every(function (sku) {
        return sku && typeof sku.id === 'string' && typeof sku.name === 'string' && validPrice(sku.price)
          && Array.isArray(sku.options) && sku.options.length === data.groups.length
          && sku.options.every(function (option, index) { return data.groups[index].options.indexOf(option) !== -1; });
      })) return null;
      return data;
    } catch (error) {
      return null;
    }
  }

  function buttonOption(button) {
    var groupIndex = Number(button.dataset.groupIndex);
    var optionIndex = Number(button.dataset.optionIndex);
    var group = groupData && groupData.groups[groupIndex];
    return group && group.options[optionIndex] !== undefined ? group.options[optionIndex] : null;
  }

  // aria-pressed を状態の基準にし、履歴から復元された画面とも同期する。
  function selectedOptions() {
    if (!groupData) return [];
    return groupData.groups.map(function (group, index) {
      var value = null;
      buttons.forEach(function (button) {
        if (Number(button.dataset.groupIndex) === index && button.getAttribute('aria-pressed') === 'true') {
          value = buttonOption(button);
        }
      });
      return value;
    });
  }

  function matchesSelections(sku, selections, ignoredIndex) {
    return selections.every(function (value, index) {
      return index === ignoredIndex || value === null || sku.options[index] === value;
    });
  }

  function selectedSku(selections, matches) {
    if (dataNode) {
      return groupData && selections.every(function (value) { return value !== null; }) && matches.length === 1 ? matches[0] : null;
    }
    if (!skuOptions) return null;
    var radio = skuOptions.querySelector('input[name="product_sku"]:checked');
    if (!radio || !validPrice(radio.dataset.skuPrice)) return null;
    return { id: radio.value, name: radio.dataset.skuName, price: radio.dataset.skuPrice };
  }

  function formatPrice(value) {
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function updateInquiry(sku, selections) {
    if (!inquiry) return;
    var lines = [];
    if (groupData) {
      selections.forEach(function (value, index) {
        if (value !== null) lines.push(groupData.groups[index].name + '：' + value);
      });
      if (lines.length && !sku) lines.push('組み合わせ：未確定');
    }
    if (sku) {
      lines.push('SKU番号：' + sku.id);
      lines.push('仕様名：' + sku.name);
      lines.push('価格（税込）：¥' + formatPrice(sku.price));
    } else if (!skuOptions && attrs) {
      attrs.querySelectorAll('.d-attr').forEach(function (group) {
        var selected = group.querySelector('.d-attr-chip.selected');
        if (selected) lines.push(group.dataset.attrGroup + '：' + selected.dataset.val);
      });
    }
    var name = inquiry.dataset.productName;
    var id = inquiry.dataset.productId;
    var subject = '【商品お問い合わせ】' + name + '（商品番号: ' + id + '）';
    var body = '商品名：' + name + '\n商品番号：' + id + '\n';
    if (lines.length) body += '\nご希望の仕様：\n' + lines.join('\n') + '\n';
    body += '\nお問い合わせ内容：\n';
    inquiry.href = 'mailto:' + inquiry.dataset.inquiryEmail
      + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
  }

  function appendAmount(amount) {
    var yen = document.createElement('span');
    yen.className = 'yen';
    yen.textContent = '¥';
    price.appendChild(yen);
    price.appendChild(document.createTextNode(formatPrice(amount)));
  }

  function renderPrice(sku, matches) {
    if (!skuOptions || !price || !initialPrice) return;
    // HTML文字列を組み立てず、初期価格のノードまたは安全なテキストを使用する。
    price.textContent = '';
    if (sku || groupData) {
      var amounts = sku ? [Number(sku.price)] : matches.map(function (item) { return Number(item.price); });
      if (!amounts.length) {
        price.textContent = '該当する組み合わせがありません';
        return;
      }
      var minimum = Math.min.apply(null, amounts);
      var maximum = Math.max.apply(null, amounts);
      appendAmount(minimum);
      if (maximum > minimum) {
        price.appendChild(document.createTextNode(' ～ '));
        price.appendChild(document.createElement('wbr'));
        appendAmount(maximum);
      }
      var tax = document.createElement('span');
      tax.className = 'tax';
      tax.textContent = '（税込）';
      price.appendChild(tax);
    } else {
      initialPrice.childNodes.forEach(function (node) { price.appendChild(node.cloneNode(true)); });
    }
  }

  function updateSelection() {
    var selections = selectedOptions();
    var matches = groupData ? groupData.skus.filter(function (sku) { return matchesSelections(sku, selections); }) : [];
    var sku = selectedSku(selections, matches);
    var hasSelection = selections.some(function (value) { return value !== null; })
      || Array.prototype.some.call(radios, function (radio) { return radio.checked; });
    buttons.forEach(function (button) {
      var groupIndex = Number(button.dataset.groupIndex);
      var value = buttonOption(button);
      var pressed = value !== null && selections[groupIndex] === value;
      var available = groupData && groupData.skus.some(function (item) {
        return item.options[groupIndex] === value && matchesSelections(item, selections, groupIndex);
      });
      button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
      // 選択済みは常に解除できる。ほかの項目と組み合わせられない候補だけ無効にする。
      button.disabled = !pressed && !available;
    });
    renderPrice(sku, matches);
    if (status) {
      var message = sku ? '選択中：' + sku.name : initialStatus;
      if (dataNode && !groupData) {
        message = '仕様情報を読み込めませんでした。お問い合わせください。';
      } else if (groupData && hasSelection && !sku) {
        var chosen = [];
        var remaining = [];
        selections.forEach(function (value, index) {
          if (value === null) remaining.push(groupData.groups[index].name);
          else chosen.push(groupData.groups[index].name + '：' + value);
        });
        message = '選択中：' + chosen.join(' / ') + '。';
        message += matches.length && remaining.length
          ? remaining.join('・') + 'を選択してください（該当する組み合わせの価格を表示）。'
          : '組み合わせを確定できません。選択を解除して変更してください。';
      }
      status.textContent = message;
    }
    if (reset) reset.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
    updateInquiry(sku, selections);
  }

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      if (button.disabled || !groupData) return;
      var pressed = button.getAttribute('aria-pressed') === 'true';
      buttons.forEach(function (item) {
        if (item.dataset.groupIndex === button.dataset.groupIndex) item.setAttribute('aria-pressed', 'false');
      });
      button.setAttribute('aria-pressed', pressed ? 'false' : 'true');
      updateSelection();
    });
  });
  radios.forEach(function (radio) { radio.addEventListener('change', updateSelection); });
  if (reset) {
    reset.addEventListener('click', function () {
      if (reset.getAttribute('aria-disabled') === 'true') return;
      radios.forEach(function (radio) { radio.checked = false; });
      buttons.forEach(function (button) { button.setAttribute('aria-pressed', 'false'); });
      updateSelection();
    });
  }

  // SKUがない商品の従来の属性選択を維持する。
  if (!skuOptions && attrs) {
    attrs.querySelectorAll('.d-attr').forEach(function (group) {
      group.querySelectorAll('.d-attr-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
          group.querySelectorAll('.d-attr-chip').forEach(function (item) { item.classList.remove('selected'); });
          chip.classList.add('selected');
          updateSelection();
        });
      });
    });
  }

  updateSelection();
  window.addEventListener('pageshow', updateSelection);
})();
