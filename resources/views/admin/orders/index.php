<div class="admin-toolbar">
  <?php foreach (['all'=>'All','pending'=>'Pending','tracking'=>'Tracking','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled','refunded'=>'Refunded'] as $key => $label): ?>
    <a class="tab <?= $status === $key ? 'active' : '' ?>" href="/admin/orders?status=<?= $key ?>"><?= $label ?></a>
  <?php endforeach; ?>
  <a class="btn btn-primary btn-sm" href="/admin/orders/new">+ Record order</a>
</div>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>User</th><th>Product</th><th>Price</th><th>Payment</th><th>Cashback</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php if (!$orders): ?><tr><td colspan="8" class="text-muted">No orders found.</td></tr><?php endif; ?>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= e($o['full_name']) ?></td>
          <td class="cell-truncate" title="<?= e($o['product_name']) ?> (<?= e($o['marketplace_name']) ?>)"><?= e($o['product_name']) ?> <span class="text-muted">(<?= e($o['marketplace_name']) ?>)</span></td>
          <td><?= money($o['product_price']) ?></td>
          <td><span class="badge <?= $o['payment_source'] === 'wallet' ? 'badge-cyan' : 'badge-muted' ?>"><?= $o['payment_source'] === 'wallet' ? '💳 Wallet' : 'Affiliate' ?></span></td>
          <td><?= money($o['cashback_amount']) ?> <span class="badge <?= status_badge_class($o['cashback_status']) ?>"><?= e($o['cashback_status']) ?></span></td>
          <td><span class="badge <?= status_badge_class($o['status']) ?>"><?= e($o['status']) ?></span></td>
          <td><?= e(time_ago($o['created_at'])) ?></td>
          <td><a class="btn btn-sm btn-secondary" href="/admin/orders/<?= (int) $o['id'] ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
