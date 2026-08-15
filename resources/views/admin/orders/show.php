<a href="/admin/orders" class="text-muted" style="font-size:13px;">&larr; Back to orders</a>
<h2 class="mt-3 mb-3">Order #<?= (int) $order['id'] ?></h2>

<div class="glass-card mb-3" style="padding:20px;">
  <div class="stat-grid">
    <div><div class="text-muted" style="font-size:11.5px;">User</div><div style="font-weight:600;"><?= e($order['full_name']) ?> (<?= e($order['email']) ?>)</div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Product</div><div><?= e($order['product_name']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Price</div><div style="font-weight:700;"><?= money($order['product_price']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Cashback</div><div><?= money($order['cashback_amount']) ?> (<?= e($order['cashback_status']) ?>)</div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Status</div><span class="badge <?= status_badge_class($order['status']) ?>"><?= e($order['status']) ?></span></div>
    <div><div class="text-muted" style="font-size:11.5px;">Payment</div><span class="badge <?= $order['payment_source'] === 'wallet' ? 'badge-cyan' : 'badge-muted' ?>"><?= $order['payment_source'] === 'wallet' ? '💳 Wallet' : 'Affiliate' ?></span></div>
  </div>
</div>

<?php if ($order['fulfillment_type'] === 'dropship'): ?>
<div class="glass-card mb-3" style="padding:20px;">
  <h3 class="mb-3" style="font-size:14px;">📦 Shipping address</h3>
  <div class="stat-grid">
    <div><div class="text-muted" style="font-size:11.5px;">Name</div><div><?= e((string) $order['shipping_name']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Phone</div><div><?= e((string) $order['shipping_phone']) ?></div></div>
    <div style="grid-column:1/-1;">
      <div class="text-muted" style="font-size:11.5px;">Address</div>
      <div>
        <?= e((string) $order['shipping_address_line1']) ?><?php if ($order['shipping_address_line2']): ?>, <?= e((string) $order['shipping_address_line2']) ?><?php endif; ?><br>
        <?= e((string) $order['shipping_city']) ?>, <?= e((string) $order['shipping_state']) ?> <?= e((string) $order['shipping_postal_code']) ?><br>
        <?= e((string) $order['shipping_country']) ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<form method="POST" action="/admin/orders/<?= (int) $order['id'] ?>/status" class="glass-card mb-3" style="padding:20px;">
  <?= csrf_field() ?>
  <h3 class="mb-3" style="font-size:14px;">Update status</h3>
  <div class="flex gap-2">
    <select class="input" name="status" style="width:auto;">
      <?php foreach (['pending','tracking','confirmed','completed','cancelled','refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <input class="input" type="text" name="notes" placeholder="Notes (optional)" style="flex:1;">
    <button class="btn btn-primary btn-sm" type="submit">Update</button>
  </div>
</form>

<?php if ($order['cashback_status'] === 'credited'): ?>
<form method="POST" action="/admin/orders/<?= (int) $order['id'] ?>/reverse-cashback" class="glass-card" style="padding:20px;">
  <?= csrf_field() ?>
  <h3 class="mb-3" style="font-size:14px;color:var(--danger);">Reverse cashback</h3>
  <div class="flex gap-2">
    <input class="input" type="text" name="reason" required placeholder="Reason (required)" style="flex:1;">
    <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Reverse this cashback? This creates a compensating ledger entry.')">Reverse</button>
  </div>
</form>
<?php endif; ?>
