<?php
$cashbackAmount = $product['cashback_type'] === 'percentage'
    ? bcdiv(bcmul((string) $product['display_price'], (string) $product['cashback_value'], 4), '100', 2)
    : (string) $product['cashback_value'];
$cashbackLabel = $product['cashback_type'] === 'percentage' ? rtrim(rtrim((string) $product['cashback_value'], '0'), '.') . '%' : money($product['cashback_value']);
?>
<a class="glass-card product-card" href="/shop/<?= e($product['slug']) ?>">
  <div class="img-wrap">
    <?php if (!empty($product['image_path'])): ?>
      <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
    <?php else: ?>
      <div class="flex items-center justify-center" style="height:100%;font-size:28px;">🛒</div>
    <?php endif; ?>
  </div>
  <div class="body">
    <span class="badge badge-muted"><?= e($product['marketplace_name'] ?? '') ?></span>
    <div class="title"><?= e($product['name']) ?></div>
    <div class="price-row">
      <span class="price"><?= money($product['display_price']) ?></span>
      <?php if (!empty($product['original_price']) && bccomp((string) $product['original_price'], (string) $product['display_price'], 2) > 0): ?>
        <span class="price-original"><?= money($product['original_price']) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($product['cashback_enabled']): ?>
      <span class="badge badge-emerald mb-3"><?= $cashbackLabel ?> cashback · earn <?= money($cashbackAmount) ?></span>
    <?php endif; ?>
    <div class="btn btn-primary btn-sm w-full mt-2" style="text-align:center;">Shop Now</div>
  </div>
</a>
