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

function setup({ skus, legacy = false, preselected = -1 } = {}) {
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
  if (skus) {
    const options = elements.skuOptions = new Element();
    skus.forEach((sku, index) => {
      const radio = new Element();
      radio.value = sku.id;
      radio.dataset = { skuName: sku.name, skuPrice: String(sku.price) };
      radio.checked = index === preselected;
      radios.push(radio);
    });
    options.querySelectorAll = selector => {
      assert.equal(selector, 'input[name="product_sku"]');
      return radios;
    };
    options.querySelector = selector => {
      assert.equal(selector, 'input[name="product_sku"]:checked');
      return radios.find(radio => radio.checked) || null;
    };
    elements.skuStatus = new Element('未選択・すべての仕様の価格を表示');
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
    elements, inquiry, price, initialPrice, radios, chips, window,
    body: () => new URL(inquiry.href).searchParams.get('body'),
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

console.log('SKU frontend behavior: all assertions passed.');
