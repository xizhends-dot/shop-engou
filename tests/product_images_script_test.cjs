const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const php = fs.readFileSync(path.join(__dirname, '../admin/edit.php'), 'utf8');
const script = php.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/<\?= json_encode\(\$token\) \?>/g, '"test-token"');
new vm.Script(script);
console.log('PASS: image manager JavaScript syntax');
// 実際の並べ替え関数を簡易DOMで検証する。
const move = script.match(/function moveImage\(x, y\) \{[\s\S]*?\n  \}/)[0];
const mgr = {
  children: [],
  insertBefore(item, before) {
    this.children.splice(this.children.indexOf(item), 1);
    const index = before ? this.children.indexOf(before) : this.children.length;
    this.children.splice(index, 0, item);
  }
};
const nodes = ['a', 'b', 'c'].map(id => ({id, parentNode: mgr, closest() {return this;}, get nextSibling() {return mgr.children[mgr.children.indexOf(this) + 1] || null;}}));
mgr.children = [...nodes];
let target = nodes[2];
let refreshes = 0;
const ctx = {mgr, dragged: nodes[0], document: {elementFromPoint() {return target;}}, refresh() {refreshes++;}};
vm.createContext(ctx);
vm.runInContext(move + '\nmoveImage(0, 0);', ctx);
assert.deepEqual(mgr.children.map(n => n.id), ['b', 'c', 'a']);
console.log('PASS: move first image to last; submitted DOM order updated');
target = nodes[1];
vm.runInContext('moveImage(0, 0)', ctx);
assert.deepEqual(mgr.children.map(n => n.id), ['a', 'b', 'c']);
console.log('PASS: move last image to first');
target = nodes[0];
vm.runInContext('moveImage(0, 0)', ctx);
assert.equal(refreshes, 2);
console.log('PASS: ignore dragging over itself');
target = {closest() {return {parentNode: {}};}};
vm.runInContext('moveImage(0, 0)', ctx);
assert.equal(refreshes, 2);
console.log('PASS: ignore targets outside image manager');
