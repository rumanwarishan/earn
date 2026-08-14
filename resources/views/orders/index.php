<h2 class="mb-3">Your orders</h2>

<div class="glass-panel mb-3" style="padding:14px;font-size:12.5px;color:var(--text-mid);">
  Orders are confirmed and tracked manually by our team once your marketplace purchase is verified. Cashback is credited once an order is marked completed.
</div>

<?php if (!$orders): ?>
  <div class="empty-state"><div class="icon">📦</div>No orders yet. <a href="/shop" style="color:var(--cyan);">Start shopping</a> to earn cashback.</div>
<?php else: ?>
<div class="glass-card">
  <?php foreach ($orders as $o): ?>
    <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
      <div>
        <div style="font-size:13.5px;font-weight:600;"><?= e($o['product_name']) ?></div>
        <div class="text-muted" style="font-size:11.5px;"><?= e($o['marketplace_name']) ?> · <?= e(date('M j, Y', strtotime($o['created_at']))) ?></div>
      </div>
      <div style="text-align:right;">
        <div style="font-weight:700;"><?= money($o['product_price']) ?></div>
        <span class="badge <?= status_badge_class($o['status']) ?>"><?= e($o['status']) ?></span>
        <?php if ($o['cashback_amount'] > 0): ?>
          <div class="text-muted mt-2" style="font-size:11px;">Cashback: <?= money($o['cashback_amount']) ?> (<?= e($o['cashback_status']) ?>)</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
