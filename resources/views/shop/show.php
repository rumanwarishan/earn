<?php
$cashbackAmount = $product['cashback_type'] === 'percentage'
    ? bcdiv(bcmul((string) $product['display_price'], (string) $product['cashback_value'], 4), '100', 2)
    : (string) $product['cashback_value'];
$cashbackLabel = $product['cashback_type'] === 'percentage' ? rtrim(rtrim((string) $product['cashback_value'], '0'), '.') . '%' : money($product['cashback_value']);
?>
<div class="glass-card mb-3" style="overflow:hidden;">
  <div style="aspect-ratio:1.4/1;background:var(--surface-2);">
    <?php if ($product['image_path']): ?>
      <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
    <?php else: ?>
      <div class="flex items-center justify-center" style="height:100%;font-size:48px;">🛒</div>
    <?php endif; ?>
  </div>
  <div style="padding:20px;">
    <span class="badge badge-muted mb-3"><?= e($product['marketplace_name']) ?></span>
    <h2 class="mt-2"><?= e($product['name']) ?></h2>
    <div class="flex items-center gap-3 mt-3">
      <span style="font-size:24px;font-weight:750;"><?= money($product['display_price']) ?></span>
      <?php if ($product['original_price'] && bccomp((string) $product['original_price'], (string) $product['display_price'], 2) > 0): ?>
        <span class="price-original" style="font-size:15px;"><?= money($product['original_price']) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($product['cashback_enabled']): ?>
      <div class="glass-panel mt-3" style="padding:14px;">
        <span class="badge badge-emerald"><?= $cashbackLabel ?> cashback</span>
        <div class="mt-2" style="font-size:14px;">You'll earn <strong style="color:var(--emerald);"><?= money($cashbackAmount) ?></strong> cashback on this purchase.</div>
      </div>
    <?php endif; ?>
    <?php if ($product['short_description']): ?><p class="mt-3"><?= e($product['short_description']) ?></p><?php endif; ?>
    <a class="btn btn-primary mt-4" href="/shop/go/<?= (int) $product['id'] ?>" target="_blank" rel="noopener">Shop on <?= e($product['marketplace_name']) ?></a>
    <div class="text-muted mt-2" style="font-size:11.5px;text-align:center;">You'll be redirected to <?= e($product['marketplace_name']) ?> to complete your purchase. Cashback is credited once your order is confirmed.</div>
  </div>
</div>

<?php if ($product['description']): ?>
<div class="glass-card" style="padding:20px;">
  <h3 class="mb-3" style="font-size:15px;">Description</h3>
  <p style="line-height:1.7;white-space:pre-line;"><?= e($product['description']) ?></p>
</div>
<?php endif; ?>
