<?php
/** SKU編集UI。$skuRows / $skuGroupRows と翻訳ヘルパーを利用する。 */
function admin_sku_row($row, $index, $groups = []) {
    $key = (string)$index;
    ?>
    <div class="adm-sku-row<?= $groups ? ' has-groups' : '' ?>" data-row-index="<?= $key ?>">
      <div class="adm-sku-field adm-sku-id-field">
        <label for="sku-<?= $key ?>-id"><?= htmlspecialchars(__('sku.id')) ?></label>
        <input type="text" id="sku-<?= $key ?>-id" name="skus[<?= $key ?>][id]" data-sku-field="id" value="<?= htmlspecialchars((string)($row['id'] ?? '')) ?>" maxlength="64" pattern="[A-Za-z0-9_-]{1,64}" placeholder="A">
      </div>
      <div class="adm-sku-field adm-sku-name-field">
        <label for="sku-<?= $key ?>-name"><?= htmlspecialchars(__('sku.name')) ?></label>
        <input type="text" id="sku-<?= $key ?>-name" name="skus[<?= $key ?>][name]" data-sku-field="name" value="<?= htmlspecialchars((string)($row['name'] ?? '')) ?>" maxlength="<?= $groups ? 400 : 200 ?>" placeholder="<?= htmlspecialchars(__('sku.name_ph')) ?>">
      </div>
      <div class="adm-sku-options" <?= !$groups ? 'hidden' : '' ?>>
      <?php foreach ($groups as $gi => $group): $selected = (string)($row['options'][$gi] ?? ''); ?>
        <div class="adm-sku-field">
          <label for="sku-<?= $key ?>-option-<?= $gi ?>"><?= htmlspecialchars($group['name']) ?></label>
          <select id="sku-<?= $key ?>-option-<?= $gi ?>" name="skus[<?= $key ?>][options][<?= $gi ?>]" data-group-key="<?= $gi ?>">
            <option value=""><?= htmlspecialchars(__('sku.choose_option')) ?></option>
            <?php if ($selected !== '' && !in_array($selected, $group['options'], true)): ?><option selected value="<?= htmlspecialchars($selected) ?>"><?= htmlspecialchars($selected) ?></option><?php endif; ?>
            <?php foreach ($group['options'] as $option): ?><option value="<?= htmlspecialchars($option) ?>" <?= $selected === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endforeach; ?>
      </div>
      <div class="adm-sku-field adm-sku-price-field">
        <label for="sku-<?= $key ?>-price"><?= htmlspecialchars(__('sku.price')) ?></label>
        <input type="number" id="sku-<?= $key ?>-price" name="skus[<?= $key ?>][price]" data-sku-field="price" value="<?= htmlspecialchars((string)($row['price'] ?? '')) ?>" min="0" max="2147483647" step="1" placeholder="1000">
      </div>
      <button type="button" class="adm-btn adm-btn-sm adm-sku-remove" aria-label="<?= htmlspecialchars(__('sku.remove')) ?>" title="<?= htmlspecialchars(__('sku.remove')) ?>"><span aria-hidden="true">×</span></button>
    </div>
    <?php
}
function admin_sku_group_row($group, $index) {
    ?>
    <div class="adm-sku-group-row" data-group-key="<?= $index ?>">
      <div class="adm-sku-field">
        <label for="sku-group-<?= $index ?>-name"><?= htmlspecialchars(__('sku.group_name')) ?></label>
        <input id="sku-group-<?= $index ?>-name" name="sku_groups[<?= $index ?>][name]" class="js-sku-group-name" value="<?= htmlspecialchars($group['name'] ?? '') ?>" maxlength="60" placeholder="カラー">
      </div>
      <div class="adm-sku-field">
        <label for="sku-group-<?= $index ?>-options"><?= htmlspecialchars(__('sku.group_options')) ?></label>
        <textarea id="sku-group-<?= $index ?>-options" name="sku_groups[<?= $index ?>][options_text]" class="js-sku-group-options" rows="1" placeholder="RED, BLUE, BLACK"><?= htmlspecialchars(implode(', ', $group['options'] ?? [])) ?></textarea>
      </div>
      <button type="button" class="adm-btn adm-btn-sm js-sku-group-remove" aria-label="<?= htmlspecialchars(__('sku.remove')) ?>" title="<?= htmlspecialchars(__('sku.remove')) ?>"><span aria-hidden="true">×</span></button>
    </div>
    <?php
}
?>
<section class="adm-sku-editor" id="skuEditor" aria-labelledby="skuEditorTitle">
  <div class="adm-sku-heading">
    <h3 id="skuEditorTitle"><?= htmlspecialchars(__('sku.title')) ?></h3>
    <details class="adm-sku-help">
      <summary><?= htmlspecialchars(__('sku.help')) ?></summary>
      <div class="adm-sku-help-body">
        <p><?= htmlspecialchars(__('sku.hint')) ?></p>
        <p><?= htmlspecialchars(__('sku.groups_hint')) ?></p>
        <p><?= htmlspecialchars(__('sku.generate_hint')) ?></p>
      </div>
    </details>
  </div>
  <div class="adm-sku-groups">
    <div class="adm-sku-heading"><h4><?= htmlspecialchars(__('sku.groups_title')) ?></h4><button type="button" class="adm-btn adm-btn-sm" id="skuGroupAdd">+ <?= htmlspecialchars(__('sku.group_add')) ?></button></div>
    <div id="skuGroupRows"><?php foreach ($skuGroupRows as $gi => $group) { admin_sku_group_row($group, $gi); } ?></div>
    <p id="skuGroupStatus" class="adm-sku-status" role="status"></p>
  </div>
  <div class="adm-sku-heading"><h4><?= htmlspecialchars(__('sku.combinations_title')) ?></h4><div class="adm-sku-actions"><button type="button" class="adm-btn adm-btn-sm" id="skuGenerate"><?= htmlspecialchars(__('sku.generate')) ?></button><button type="button" class="adm-btn adm-btn-sm" id="skuAdd">+ <?= htmlspecialchars(__('sku.add')) ?></button></div></div>
  <div id="skuRows"><?php foreach ($skuRows as $i => $row) { admin_sku_row($row, $i, $skuGroupRows); } ?></div>
  <p class="adm-note" id="skuEmpty" <?= $skuRows ? 'hidden' : '' ?>><?= htmlspecialchars(__('sku.empty')) ?></p>
  <p class="adm-sku-status" id="skuStatus" role="status" data-incomplete="<?= htmlspecialchars(__('sku.incomplete')) ?>" data-limit="<?= htmlspecialchars(__('sku.limit')) ?>"></p>
</section>
<template id="skuRowTemplate"><?php admin_sku_row([], '__INDEX__'); ?></template>
<template id="skuGroupTemplate"><?php admin_sku_group_row([], '__GROUP__'); ?></template>
<script type="application/json" id="skuEditorMessages"><?= json_encode(array_combine(['invalid','choose','duplicate','generated','too_many','changed','missing','no_groups'], array_map(function ($key) { return __('sku.' . $key); }, ['groups_invalid','choose_option','err_combination','generated','generate_limit','groups_changed','option_missing','groups_required'])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="sku-editor.js?v=<?= (int)filemtime(__DIR__ . '/sku-editor.js') ?>" defer></script>
