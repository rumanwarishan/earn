<?php
$cashbackAmount = $product['cashback_type'] === 'percentage'
    ? bcdiv(bcmul((string) $product['display_price'], (string) $product['cashback_value'], 4), '100', 2)
    : (string) $product['cashback_value'];
$cashbackLabel = $product['cashback_type'] === 'percentage' ? rtrim(rtrim((string) $product['cashback_value'], '0'), '.') . '%' : money($product['cashback_value']);
$isDropship = $product['fulfillment_type'] === 'dropship';
$outOfStock = $isDropship && $product['stock_quantity'] !== null && (int) $product['stock_quantity'] <= 0;
$canAfford = $isDropship && $spendable !== null && bccomp($spendable, (string) $product['display_price'], 2) >= 0;
?>
<div class="glass-card mb-3" style="overflow:hidden;">
  <div style="aspect-ratio:1.4/1;background:var(--surface-2);">
    <?php if ($product['image_path']): ?>
      <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
    <?php endif; ?>
    <div class="flex items-center justify-center" style="height:100%;font-size:48px;<?= $product['image_path'] ? 'display:none;' : '' ?>">🛒</div>
  </div>
  <div style="padding:20px;">
    <span class="badge badge-muted mb-3"><?= $isDropship ? 'Ships from ' . e($product['marketplace_name']) : e($product['marketplace_name']) ?></span>
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

    <?php if ($isDropship): ?>
      <?php if ($outOfStock): ?>
        <div class="alert alert-error mt-3">This product is currently out of stock.</div>
      <?php elseif (!$user): ?>
        <a class="btn btn-primary mt-4" href="/login">Log in to buy with wallet</a>
      <?php else: ?>
        <div class="glass-panel mt-3" style="padding:14px;">
          <div class="text-muted" style="font-size:12px;">Your wallet balance</div>
          <div style="font-size:20px;font-weight:700;"><?= money($spendable) ?></div>
          <?php if (!$canAfford): ?><div class="text-muted mt-1" style="font-size:12px;color:var(--danger);">Insufficient balance. <a href="/wallet/deposit">Deposit funds</a> to buy this item.</div><?php endif; ?>
          <?php if ($product['stock_quantity'] !== null): ?><div class="text-muted mt-1" style="font-size:12px;"><?= (int) $product['stock_quantity'] ?> left in stock</div><?php endif; ?>
        </div>
        <form method="POST" action="/shop/<?= (int) $product['id'] ?>/buy" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="return_to" value="/shop/<?= e($product['slug']) ?>">
          <div class="field">
            <label>Full name</label>
            <input class="input" type="text" name="shipping_name" value="<?= old('shipping_name') ?>" required maxlength="150">
          </div>
          <div class="field">
            <label>Phone</label>
            <input class="input" type="text" name="shipping_phone" value="<?= old('shipping_phone') ?>" required maxlength="30">
          </div>
          <div class="field">
            <label>Address line 1</label>
            <input class="input" type="text" name="shipping_address_line1" value="<?= old('shipping_address_line1') ?>" required maxlength="190">
          </div>
          <div class="field">
            <label>Address line 2 (optional)</label>
            <input class="input" type="text" name="shipping_address_line2" value="<?= old('shipping_address_line2') ?>" maxlength="190">
          </div>
          <div class="flex gap-2">
            <div class="field" style="flex:1;">
              <label>City</label>
              <input class="input" type="text" name="shipping_city" value="<?= old('shipping_city') ?>" required maxlength="100">
            </div>
            <div class="field" style="flex:1;">
              <label>State</label>
              <input class="input" type="text" name="shipping_state" value="<?= old('shipping_state') ?>" required maxlength="100">
            </div>
          </div>
          <div class="flex gap-2">
            <div class="field" style="flex:1;">
              <label>Postal code</label>
              <input class="input" type="text" name="shipping_postal_code" value="<?= old('shipping_postal_code') ?>" required maxlength="20">
            </div>
            <div class="field" style="flex:1;">
              <label>Country</label>
              <input class="input" type="text" name="shipping_country" value="<?= old('shipping_country') ?>" required maxlength="100">
            </div>
          </div>
          <button class="btn btn-primary mt-2" type="submit" <?= $canAfford ? '' : 'disabled' ?>>Buy with wallet - <?= money($product['display_price']) ?></button>
          <div class="text-muted mt-2" style="font-size:11.5px;text-align:center;">Paid instantly from your wallet balance. We'll ship this order directly to you and notify you with tracking once it's on the way.</div>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <a class="btn btn-primary mt-4" href="/shop/go/<?= (int) $product['id'] ?>" target="_blank" rel="noopener">Shop on <?= e($product['marketplace_name']) ?></a>
      <div class="text-muted mt-2" style="font-size:11.5px;text-align:center;">You'll be redirected to <?= e($product['marketplace_name']) ?> to complete your purchase. Cashback is credited once your order is confirmed.</div>
    <?php endif; ?>
  </div>
</div>

<?php if ($product['description']): ?>
<div class="glass-card" style="padding:20px;">
  <h3 class="mb-3" style="font-size:15px;">Description</h3>
  <p style="line-height:1.7;white-space:pre-line;"><?= e($product['description']) ?></p>
</div>
<?php endif; ?>
