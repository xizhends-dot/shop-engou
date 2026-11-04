<?php
/** SKU編集UI。$skuRows と翻訳ヘルパーを利用する。 */
function admin_sku_row($row, $index) {
    $key = (string)$index;
    ?>
    <div class="adm-sku-row">
      <div class="adm-sku-field">
        <label for="sku-<?= $key ?>-id"><?= htmlspecialchars(__('sku.id')) ?></label>
        <input type="text" id="sku-<?= $key ?>-id" name="skus[<?= $key ?>][id]" data-sku-field="id" value="<?= htmlspecialchars((string)($row['id'] ?? '')) ?>" maxlength="64" pattern="[A-Za-z0-9_-]{1,64}" placeholder="A">
      </div>
      <div class="adm-sku-field">
        <label for="sku-<?= $key ?>-name"><?= htmlspecialchars(__('sku.name')) ?></label>
        <input type="text" id="sku-<?= $key ?>-name" name="skus[<?= $key ?>][name]" data-sku-field="name" value="<?= htmlspecialchars((string)($row['name'] ?? '')) ?>" maxlength="200" placeholder="<?= htmlspecialchars(__('sku.name_ph')) ?>">
      </div>
      <div class="adm-sku-field">
        <label for="sku-<?= $key ?>-price"><?= htmlspecialchars(__('sku.price')) ?></label>
        <input type="number" id="sku-<?= $key ?>-price" name="skus[<?= $key ?>][price]" data-sku-field="price" value="<?= htmlspecialchars((string)($row['price'] ?? '')) ?>" min="0" max="2147483647" step="1" placeholder="1000">
      </div>
      <button type="button" class="adm-btn adm-btn-sm adm-sku-remove" aria-label="<?= htmlspecialchars(__('sku.remove')) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span><?= htmlspecialchars(__('sku.remove')) ?></span></button>
    </div>
    <?php
}
?>
<section class="adm-sku-editor" id="skuEditor" aria-labelledby="skuEditorTitle">
  <div class="adm-sku-heading"><h3 id="skuEditorTitle"><?= htmlspecialchars(__('sku.title')) ?></h3><span>SKU</span></div>
  <p class="adm-note"><?= htmlspecialchars(__('sku.hint')) ?></p>
  <div id="skuRows"><?php foreach ($skuRows as $i => $row) { admin_sku_row($row, $i); } ?></div>
  <p class="adm-note" id="skuEmpty" <?= $skuRows ? 'hidden' : '' ?>><?= htmlspecialchars(__('sku.empty')) ?></p>
  <button type="button" class="adm-btn" id="skuAdd"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?= htmlspecialchars(__('sku.add')) ?></button>
  <p class="adm-sku-status" id="skuStatus" role="status" data-incomplete="<?= htmlspecialchars(__('sku.incomplete')) ?>" data-limit="<?= htmlspecialchars(__('sku.limit')) ?>"></p>
</section>
<template id="skuRowTemplate"><?php admin_sku_row([], '__INDEX__'); ?></template>
<script src="sku-editor.js?v=<?= (int)filemtime(__DIR__ . '/sku-editor.js') ?>" defer></script>
