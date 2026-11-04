/* 実行: node tests/sku_front_behavior.js。外部依存なしで画面の状態遷移を検証する。 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '..', 'product-skus.js'), 'utf8');

class Element {
  constructor(text = '') {
    this.dataset = {};
    this.attributes = {};
    this.events = {};
    this.childNodes = [];
    this.ownText = text;
    this.checked = false;
    this.className = '';
    this.value = '';
  }
  get textContent() { return this.ownText + this.childNodes.map(node => node.textContent).join(''); }
  set textContent(value) { this.ownText = value; this.childNodes = []; }
  set innerHTML(value) { throw new Error('Product content must never be passed to innerHTML: ' + value); }
  get classList() {
    return {
      add: name => { this.className = Array.from(new Set(this.className.split(' ').concat(name))).join(' ').trim(); },
      remove: name => { this.className = this.className.split(' ').filter(item => item !== name).join(' '); },
      contains: name => this.className.split(' ').includes(name),
    };
  }
  appendChild(node) { this.childNodes.push(node); return node; }
  cloneNode(deep) {
    const clone = new Element(this.ownText);
    clone.className = this.className;
    if (deep) clone.childNodes = this.childNodes.map(node => node.cloneNode(true));
    return clone;
  }
  addEventListener(event, listener) { (this.events[event] ||= []).push(listener); }
  dispatch(event) { (this.events[event] || []).forEach(listener => listener({ target: this })); }
  setAttribute(name, value) { this.attributes[name] = value; }
  getAttribute(name) { return this.attributes[name] ?? null; }
}

function setup({ skus, groups, legacy = false, preselected = -1, preselectedOptions = [], rawData } = {}) {
  const elements = {};
  const inquiry = elements.inquiryBtn = new Element();
  Object.assign(inquiry.dataset, {
    inquiryEmail: 'test@example.test', productId: 'PRODUCT-01',
    productName: '商品 "引用" </script><script>alert("product")</script>',
  });
  const price = elements.productPrice = new Element();
  ['¥', '1,000', ' ～ ', '¥', '1,500', '（税込）'].forEach(text => price.appendChild(new Element(text)));
  const initialPrice = price.textContent;
  const window = new Element();
  const radios = [];
  const buttons = [];
  if (skus) {
    const options = elements.skuOptions = new Element();
    if (groups) {
      elements.skuData = new Element(rawData === undefined ? JSON.stringify({ groups, skus }) : rawData);
      groups.forEach((group, groupIndex) => {
        group.options.forEach((option, optionIndex) => {
          const button = new Element(option);
          const priceLabel = new Element();
          button.appendChild(priceLabel);
          button.querySelector = selector => {
            assert.equal(selector, '.sku-group__price');
            return priceLabel;
          };
          button.dataset = { groupIndex: String(groupIndex), optionIndex: String(optionIndex) };
          button.setAttribute('aria-pressed', preselectedOptions[groupIndex] === option ? 'true' : 'false');
          buttons.push(button);
        });
      });
    }
    skus.forEach((sku, index) => {
      const radio = new Element();
      radio.value = sku.id;
      radio.dataset = { skuName: sku.name, skuPrice: String(sku.price) };
      radio.checked = index === preselected;
      radios.push(radio);
    });
    options.querySelectorAll = selector => {
      if (selector === '.sku-group__option') return buttons;
      assert.equal(selector, 'input[name="product_sku"]');
      assert(!groups, 'Grouped selections must never query legacy radios.');
      return radios;
    };
    options.querySelector = selector => {
      assert(!groups, 'Grouped selections must never read a legacy selected SKU.');
      assert.equal(selector, 'input[name="product_sku"]:checked');
      return radios.find(radio => radio.checked) || null;
    };
    elements.skuStatus = new Element(groups
      ? '未選択・すべての組み合わせの価格を表示。各項目を選択してください。'
      : '未選択・すべての仕様の価格を表示');
    elements.skuReset = new Element();
    elements.skuReset.setAttribute('aria-disabled', 'true');
  }
  const chips = [];
  if (legacy) {
    const attrs = elements.dAttrs = new Element();
    const group = new Element();
    group.dataset.attrGroup = '色';
    ['白', '黒 "特別"'].forEach((value, index) => {
      const chip = new Element(value);
      chip.dataset.val = value;
      chip.className = 'd-attr-chip' + (index === 0 ? ' selected' : '');
      chips.push(chip);
    });
    attrs.querySelectorAll = () => [group];
    group.querySelectorAll = () => chips;
    group.querySelector = () => chips.find(chip => chip.classList.contains('selected')) || null;
  }
  const document = {
    getElementById: id => elements[id] || null,
    createElement: () => new Element(),
    createTextNode: text => new Element(text),
  };
  vm.runInNewContext(source, { document, window }, { filename: 'product-skus.js' });
  return {
    elements, inquiry, price, initialPrice, radios, buttons, chips, window,
    body: () => new URL(inquiry.href).searchParams.get('body'),
    option(groupIndex, value) { return buttons.find(button => Number(button.dataset.groupIndex) === groupIndex && button.ownText === value); },
    optionPrice(groupIndex, value) { return this.option(groupIndex, value).querySelector('.sku-group__price').textContent; },
    choose(groupIndex, value) { this.option(groupIndex, value).dispatch('click'); },
    select(index) {
      radios.forEach((radio, position) => { radio.checked = index === position; });
      radios[index].dispatch('change');
    },
    reset() { elements.skuReset.dispatch('click'); },
  };
}

const hostile = '白 / S "引用" </script><script>alert("sku")</script> & <b>色</b>';
const skus = [{ id: 'A', name: hostile, price: 1000 }, { id: 'B', name: '黒 / M', price: 1500 }];
const view = setup({ skus });
assert.equal(view.price.textContent, view.initialPrice);
assert(!view.body().includes('SKU番号'));
assert(view.body().includes('商品番号：PRODUCT-01'));
assert.equal(view.elements.skuReset.getAttribute('aria-disabled'), 'true');
view.reset();
assert.equal(view.price.textContent, view.initialPrice);
view.select(0);
assert.equal(view.price.textContent, '¥1,000（税込）');
assert(view.body().includes('SKU番号：A\n仕様名：' + hostile + '\n価格（税込）：¥1,000'));
assert(view.body().includes('商品番号：PRODUCT-01'));
assert(view.elements.skuStatus.textContent.includes(hostile));
assert.equal(view.elements.skuReset.getAttribute('aria-disabled'), 'false');
assert.equal(new URL(view.inquiry.href).searchParams.get('subject'), '【商品お問い合わせ】' + view.inquiry.dataset.productName + '（商品番号: PRODUCT-01）');
view.select(1);
assert.equal(view.price.textContent, '¥1,500（税込）');
assert(view.body().includes('SKU番号：B\n仕様名：黒 / M\n価格（税込）：¥1,500'));
assert(!view.body().includes(hostile));
assert(!view.body().includes('SKU番号：A'));
view.reset();
assert.equal(view.price.textContent, view.initialPrice);
assert(!view.radios.some(radio => radio.checked));
assert(!view.body().includes('SKU番号') && !view.body().includes('価格（税込）') && !view.body().includes(hostile));
assert.equal(view.elements.skuReset.getAttribute('aria-disabled'), 'true');
view.select(0);
view.reset();
assert.equal(view.price.textContent, view.initialPrice, 'Repeated reset must retain the full range.');

const boundary = setup({ skus: [{ id: 'FREE', name: '無料', price: 0 }, { id: 'MAX', name: '上限', price: 2147483647 }] });
boundary.select(0);
assert.equal(boundary.price.textContent, '¥0（税込）');
boundary.select(1);
assert.equal(boundary.price.textContent, '¥2,147,483,647（税込）');
for (const invalid of ['-1', '1.5', '1e3', 'NaN', 'Infinity', '2147483648', '1000<script>']) {
  boundary.radios[1].dataset.skuPrice = invalid;
  boundary.select(1);
  assert.equal(boundary.price.textContent, boundary.initialPrice, 'Invalid data price: ' + invalid);
  assert(!boundary.body().includes('SKU番号'), 'Invalid price cannot enter the inquiry.');
}

const restored = setup({ skus, preselected: 1 });
assert.equal(restored.price.textContent, '¥1,500（税込）', 'Browser-restored selection must synchronize on initialization.');
restored.radios[1].checked = false;
restored.radios[0].checked = true;
restored.window.dispatch('pageshow');
assert.equal(restored.price.textContent, '¥1,000（税込）');
assert(restored.body().includes('SKU番号：A'));

const legacy = setup({ legacy: true });
assert(legacy.body().includes('色：白'));
legacy.chips[1].dispatch('click');
assert(legacy.body().includes('色：黒 "特別"'));
assert(!legacy.body().includes('色：白'));
assert.equal(legacy.price.textContent, legacy.initialPrice, 'Legacy attribute clicks must not change prices.');
const noAttrs = setup();
assert(noAttrs.body().includes('商品番号：PRODUCT-01'));
assert(!noAttrs.body().includes('ご希望の仕様'));

function assertNoVisibleSkuId(view, candidates) {
  const visible = view.price.textContent + view.elements.skuStatus.textContent + view.buttons.map(button => button.textContent).join(' ');
  candidates.forEach(sku => assert(!visible.includes(sku.id), 'Visible state must not expose SKU id: ' + sku.id));
  assert(!visible.includes('SKU番号'));
}

const groups = [
  { name: 'カラー', options: ['RED', 'BLUE', 'BLACK', 'UNAVAILABLE'] },
  { name: 'サイズ', options: ['S', 'M', 'L'] },
];
const combinations = [
  { id: 'sku-red-s', name: 'RED / S', price: 1000, options: ['RED', 'S'] },
  { id: 'sku-red-m', name: 'RED / M', price: 1200, options: ['RED', 'M'] },
  { id: 'sku-blue-m', name: 'BLUE / M', price: 1500, options: ['BLUE', 'M'] },
  { id: 'sku-black-l', name: 'BLACK / L', price: 2000, options: ['BLACK', 'L'] },
];
const grouped = setup({ groups, skus: combinations, legacy: true });
assert.equal(grouped.price.textContent, '¥1,000 ～ ¥2,000（税込）');
assert.equal(grouped.optionPrice(0, 'RED'), '¥1,000 ～ ¥1,200（税込）');
assert.equal(grouped.option(0, 'RED').querySelector('.sku-group__price').childNodes[0].className, 'tax');
assert.equal(grouped.optionPrice(0, 'BLUE'), '¥1,500（税込）');
assert.equal(grouped.optionPrice(1, 'M'), '¥1,200 ～ ¥1,500（税込）');
assert.equal(grouped.optionPrice(0, 'UNAVAILABLE'), '—');
assert(grouped.option(0, 'UNAVAILABLE').disabled, 'An option without any SKU must be disabled initially.');
assert(!grouped.body().includes('SKU番号') && !grouped.body().includes('色：'), 'Grouped SKU must not read unrelated attributes.');
grouped.choose(0, 'RED');
assert.equal(grouped.price.textContent, '¥1,000 ～ ¥1,200（税込）');
assert.equal(grouped.optionPrice(1, 'S'), '¥1,000（税込）');
assert.equal(grouped.optionPrice(1, 'M'), '¥1,200（税込）');
assert.equal(grouped.optionPrice(1, 'L'), '—');
assert.equal(grouped.option(0, 'RED').getAttribute('aria-pressed'), 'true');
assert(grouped.elements.skuStatus.textContent.includes('サイズを選択してください'));
assert(grouped.option(1, 'L').disabled && !grouped.option(1, 'S').disabled && !grouped.option(1, 'M').disabled);
assert(grouped.body().includes('カラー：RED') && grouped.body().includes('組み合わせ：未確定'));
assert(!grouped.body().includes('SKU番号') && !grouped.body().includes('価格（税込）'));
grouped.choose(1, 'L');
assert.equal(grouped.option(1, 'L').getAttribute('aria-pressed'), 'false', 'Disabled options cannot change state.');
grouped.choose(1, 'S');
assert.equal(grouped.price.textContent, '¥1,000（税込）');
assert(grouped.body().includes('カラー：RED\nサイズ：S\nSKU番号：sku-red-s\n仕様名：RED / S\n価格（税込）：¥1,000'));
assert(grouped.option(0, 'BLUE').disabled && grouped.option(0, 'BLACK').disabled);
assert(!grouped.option(1, 'M').disabled, 'Availability must ignore selection in the same group.');
assertNoVisibleSkuId(grouped, combinations);
grouped.choose(1, 'M');
assert.equal(grouped.price.textContent, '¥1,200（税込）');
assert(!grouped.option(0, 'BLUE').disabled);
grouped.choose(0, 'BLUE');
assert.equal(grouped.price.textContent, '¥1,500（税込）');
assert(grouped.body().includes('SKU番号：sku-blue-m') && !grouped.body().includes('sku-red-m'));
grouped.choose(1, 'M');
assert.equal(grouped.option(1, 'M').getAttribute('aria-pressed'), 'false', 'Clicking a selected option must cancel it.');
assert.equal(grouped.price.textContent, '¥1,500（税込）');
assert(!grouped.body().includes('SKU番号'), 'Even a single matching SKU is unconfirmed until every group is selected.');
assert(!grouped.body().includes('価格（税込）'));
assert(grouped.elements.skuStatus.textContent.includes('サイズを選択してください'));
assert(!grouped.option(0, 'BLACK').disabled, 'Cancelling another group must unlock alternative combinations.');
grouped.choose(0, 'BLACK');
grouped.choose(1, 'L');
assert.equal(grouped.price.textContent, '¥2,000（税込）');
assert(grouped.body().includes('SKU番号：sku-black-l'));
assertNoVisibleSkuId(grouped, combinations);
grouped.reset();
assert.equal(grouped.price.textContent, '¥1,000 ～ ¥2,000（税込）');
assert(grouped.buttons.every(button => button.getAttribute('aria-pressed') === 'false'));
assert(!grouped.body().includes('ご希望の仕様'));
assert.equal(grouped.elements.skuReset.getAttribute('aria-disabled'), 'true');

// サイズから選ぶ経路と、履歴復元時の価格・可用候補・メールの再同期。
grouped.choose(1, 'M');
assert.equal(grouped.price.textContent, '¥1,200 ～ ¥1,500（税込）');
assert(grouped.elements.skuStatus.textContent.includes('カラーを選択してください'));
assert(grouped.option(0, 'BLACK').disabled);
grouped.choose(0, 'RED');
grouped.buttons.forEach(button => button.setAttribute('aria-pressed', 'false'));
grouped.option(0, 'BLACK').setAttribute('aria-pressed', 'true');
grouped.option(1, 'L').setAttribute('aria-pressed', 'true');
grouped.window.dispatch('pageshow');
assert.equal(grouped.price.textContent, '¥2,000（税込）');
assert(!grouped.option(0, 'BLACK').disabled && !grouped.option(1, 'L').disabled);
assert(grouped.option(0, 'RED').disabled && grouped.body().includes('SKU番号：sku-black-l'));
const restoredGroups = setup({ groups, skus: combinations, preselectedOptions: ['RED', 'M'], preselected: 3 });
assert.equal(restoredGroups.price.textContent, '¥1,200（税込）');
assert(restoredGroups.body().includes('SKU番号：sku-red-m') && !restoredGroups.body().includes('sku-black-l'));
assertNoVisibleSkuId(restoredGroups, combinations);

// 保存された画面が不整合でも SKU を偽らず、押した選択を解除して回復できる。
grouped.option(0, 'BLACK').setAttribute('aria-pressed', 'false');
grouped.option(0, 'RED').setAttribute('aria-pressed', 'true');
grouped.window.dispatch('pageshow');
assert.equal(grouped.price.textContent, '該当する組み合わせがありません');
assert(!grouped.body().includes('SKU番号'));
assert(!grouped.option(1, 'L').disabled);
grouped.choose(1, 'L');
assert.equal(grouped.price.textContent, '¥1,000 ～ ¥1,200（税込）');

const specialGroups = [{ name: '色 & "引用"', options: [hostile] }, { name: 'サイズ', options: ['0', 'M'] }];
const specialSkus = [
  { id: 'special-free', name: hostile, price: 0, options: [hostile, '0'] },
  { id: 'special-max', name: '最大', price: 2147483647, options: [hostile, 'M'] },
];
const special = setup({ groups: specialGroups, skus: specialSkus });
special.choose(0, hostile);
special.choose(1, '0');
assert.equal(special.price.textContent, '¥0（税込）');
assert(special.elements.skuStatus.textContent.includes(hostile));
assert(special.body().includes('色 & "引用"：' + hostile));
special.choose(1, 'M');
assert.equal(special.price.textContent, '¥2,147,483,647（税込）');
assertNoVisibleSkuId(special, specialSkus);
const three = setup({
  groups: [...groups.slice(0, 2), { name: '素材', options: ['COTTON', 'WOOL'] }],
  skus: combinations.map(sku => ({ ...sku, options: [...sku.options, 'COTTON'] })),
});
three.choose(0, 'RED');
three.choose(1, 'S');
assert(!three.body().includes('SKU番号'));
assert(three.elements.skuStatus.textContent.includes('素材を選択してください'));
three.choose(2, 'COTTON');
assert(three.body().includes('SKU番号：sku-red-s'));
assert(three.option(2, 'WOOL').disabled);

const ambiguous = setup({ groups, skus: [...combinations, { ...combinations[0], id: 'duplicate' }], preselectedOptions: ['RED', 'S'] });
assert(!ambiguous.body().includes('SKU番号'), 'A duplicate combination must never impersonate a unique SKU.');
assert(ambiguous.elements.skuStatus.textContent.includes('確定できません'));
for (const rawData of ['{bad json', '{}', 'null', JSON.stringify({ groups, skus: [{ ...combinations[0], price: -1 }] })]) {
  const broken = setup({ groups, skus: combinations, rawData });
  assert(broken.buttons.every(button => button.disabled));
  assert(!broken.body().includes('SKU番号'));
  assert(broken.elements.skuStatus.textContent.includes('読み込めません'));
}

console.log('SKU frontend behavior: all flat, grouped and legacy assertions passed.');
