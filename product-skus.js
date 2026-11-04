(function () {
  'use strict';

  // 価格・属性・SKUの状態を一つの問い合わせ更新処理に集約する。
  var inquiry = document.getElementById('inquiryBtn');
  var attrs = document.getElementById('dAttrs');
  var skuOptions = document.getElementById('skuOptions');
  var price = document.getElementById('productPrice');
  var status = document.getElementById('skuStatus');
  var reset = document.getElementById('skuReset');
  var radios = skuOptions ? skuOptions.querySelectorAll('input[name="product_sku"]') : [];
  var initialPrice = price ? price.cloneNode(true) : null;
  var initialStatus = status ? status.textContent : '';

  function selectedSku() {
    if (!skuOptions) return null;
    var radio = skuOptions.querySelector('input[name="product_sku"]:checked');
    // データ属性を数値として利用する前に、保存可能な整数範囲を確認する。
    if (!radio || !/^(0|[1-9][0-9]{0,9})$/.test(radio.dataset.skuPrice)
        || Number(radio.dataset.skuPrice) > 2147483647) return null;
    return { id: radio.value, name: radio.dataset.skuName, price: radio.dataset.skuPrice };
  }

  function formatPrice(value) {
    return value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function updateInquiry(sku) {
    if (!inquiry) return;
    var lines = [];
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

  function updateSelection() {
    var sku = selectedSku();
    if (skuOptions && price && initialPrice) {
      // HTML文字列を組み立てず、初期価格のノードまたは安全なテキストを使用する。
      price.textContent = '';
      if (sku) {
        var yen = document.createElement('span');
        yen.className = 'yen';
        yen.textContent = '¥';
        var tax = document.createElement('span');
        tax.className = 'tax';
        tax.textContent = '（税込）';
        price.appendChild(yen);
        price.appendChild(document.createTextNode(formatPrice(sku.price)));
        price.appendChild(tax);
      } else {
        initialPrice.childNodes.forEach(function (node) { price.appendChild(node.cloneNode(true)); });
      }
    }
    if (status) status.textContent = sku ? '選択中：' + sku.name + '（SKU番号：' + sku.id + '）' : initialStatus;
    if (reset) reset.setAttribute('aria-disabled', sku ? 'false' : 'true');
    updateInquiry(sku);
  }

  radios.forEach(function (radio) { radio.addEventListener('change', updateSelection); });
  if (reset) {
    reset.addEventListener('click', function () {
      if (reset.getAttribute('aria-disabled') === 'true') return;
      radios.forEach(function (radio) { radio.checked = false; });
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
  // 戻る操作でブラウザーが復元したradioの状態にも価格とメールを合わせる。
  window.addEventListener('pageshow', updateSelection);
})();
