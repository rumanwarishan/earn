<a href="/admin/orders" class="text-muted" style="font-size:13px;">&larr; Back to orders</a>
<h2 class="mt-3 mb-3">Record an order</h2>
<div class="glass-panel mb-3" style="padding:14px;font-size:12.5px;color:var(--text-mid);">
  Only record orders you have verified against the marketplace. Never fabricate purchases - cashback is real money.
</div>
<form method="POST" action="/admin/orders" class="glass-card" style="padding:24px;max-width:520px;">
  <?= csrf_field() ?>
  <div class="field">
    <label>User</label>
    <select class="input" name="user_id" required>
      <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['email']) ?>)</option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label>Product</label>
    <select class="input" name="product_id" required>
      <?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label>External order reference (optional)</label><input class="input" type="text" name="external_order_reference"></div>
  <div class="field"><label>Notes</label><input class="input" type="text" name="notes" maxlength="500"></div>
  <button class="btn btn-primary" type="submit">Record order</button>
</form>
