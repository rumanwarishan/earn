<a href="/admin/products" class="text-muted" style="font-size:13px;">&larr; Back to products</a>
<h2 class="mt-3 mb-3"><?= $product ? 'Edit product' : 'New product' ?></h2>

<form method="POST" action="<?= $product ? '/admin/products/' . (int) $product['id'] : '/admin/products' ?>" enctype="multipart/form-data" class="glass-card" style="padding:24px;max-width:640px;">
  <?= csrf_field() ?>
  <div class="field"><label>Name</label><input class="input" type="text" name="name" required value="<?= e($product['name'] ?? '') ?>"></div>
  <div class="field"><label>Short description</label><input class="input" type="text" name="short_description" maxlength="500" value="<?= e($product['short_description'] ?? '') ?>"></div>
  <div class="field"><label>Full description</label><textarea class="input" name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea></div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div class="field">
      <label>Marketplace</label>
      <select class="input" name="marketplace_id" required>
        <?php foreach ($marketplaces as $mk): ?>
          <option value="<?= (int) $mk['id'] ?>" <?= ($product['marketplace_id'] ?? null) == $mk['id'] ? 'selected' : '' ?>><?= e($mk['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Category</label>
      <select class="input" name="category_id">
        <option value="">— None —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= ($product['category_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <?php $fulfillmentType = $product['fulfillment_type'] ?? 'affiliate'; ?>
  <div class="field">
    <label>Fulfillment type</label>
    <select class="input" name="fulfillment_type" id="fulfillment_type" onchange="document.getElementById('affiliate_fields').style.display = this.value === 'affiliate' ? '' : 'none'; document.getElementById('stock_field').style.display = this.value === 'dropship' ? '' : 'none';">
      <option value="affiliate" <?= $fulfillmentType === 'affiliate' ? 'selected' : '' ?>>Affiliate link (redirect to external store)</option>
      <option value="dropship" <?= $fulfillmentType === 'dropship' ? 'selected' : '' ?>>Dropship (customers buy with wallet balance)</option>
    </select>
    <div class="field-hint">Dropship products are paid instantly from the customer's wallet, and you fulfill/ship the order yourself from the admin panel.</div>
  </div>

  <div id="affiliate_fields" style="<?= $fulfillmentType === 'dropship' ? 'display:none;' : '' ?>">
    <div class="field"><label>External product URL</label><input class="input" type="url" name="external_url" value="<?= e($product['external_url'] ?? '') ?>"></div>
    <div class="field"><label>Affiliate/tracking URL (optional)</label><input class="input" type="url" name="affiliate_url" value="<?= e($product['affiliate_url'] ?? '') ?>"></div>
  </div>

  <div id="stock_field" class="field" style="<?= $fulfillmentType === 'dropship' ? '' : 'display:none;' ?>">
    <label>Stock quantity (optional)</label>
    <input class="input" type="text" name="stock_quantity" inputmode="numeric" pattern="\d*" value="<?= e((string) ($product['stock_quantity'] ?? '')) ?>">
    <div class="field-hint">Leave blank for unlimited stock. Decrements by 1 on each wallet purchase.</div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div class="field"><label>Original price</label><input class="input" type="text" name="original_price" value="<?= e((string) ($product['original_price'] ?? '')) ?>"></div>
    <div class="field"><label>Display price</label><input class="input" type="text" name="display_price" required value="<?= e((string) ($product['display_price'] ?? '')) ?>"></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div class="field">
      <label>Cashback type</label>
      <select class="input" name="cashback_type">
        <option value="percentage" <?= ($product['cashback_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentage</option>
        <option value="fixed" <?= ($product['cashback_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed amount</option>
      </select>
    </div>
    <div class="field"><label>Cashback value</label><input class="input" type="text" name="cashback_value" value="<?= e((string) ($product['cashback_value'] ?? '5')) ?>"></div>
  </div>

  <div class="field"><label>Tags (comma-separated)</label><input class="input" type="text" name="tags" value="<?= e($product['tags'] ?? '') ?>"></div>
  <div class="field"><label>Product image</label><input class="input" type="file" name="image" accept="image/*">
    <?php if (!empty($product['image_path'])): ?><img src="<?= e($product['image_path']) ?>" style="max-width:120px;border-radius:8px;margin-top:8px;" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'text-muted',style:'font-size:12px;margin-top:8px;',textContent:'Current image file is missing - upload a new one to replace it.'}))"><?php endif; ?>
  </div>

  <div class="flex gap-3 mb-3">
    <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" <?= !empty($product['is_featured']) ? 'checked' : '' ?>> Featured</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_published" <?= ($product === null || !empty($product['is_published'])) ? 'checked' : '' ?>> Published</label>
  </div>

  <button class="btn btn-primary" type="submit"><?= $product ? 'Save changes' : 'Create product' ?></button>
</form>
