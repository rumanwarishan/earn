<div class="admin-toolbar">
  <?php foreach (['pending'=>'Pending','approved'=>'Approved','processing'=>'Processing','paid'=>'Paid','completed'=>'Completed','rejected'=>'Rejected','all'=>'All'] as $key => $label): ?>
    <a class="tab <?= $status === $key ? 'active' : '' ?>" href="/admin/withdrawals?status=<?= $key ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>User</th><th>Amount</th><th>BTC address</th><th>Status</th><th>Requested</th><th></th></tr></thead>
    <tbody>
      <?php if (!$withdrawals): ?><tr><td colspan="6" class="text-muted">No withdrawals found.</td></tr><?php endif; ?>
      <?php foreach ($withdrawals as $w): ?>
        <tr>
          <td><?= e($w['full_name']) ?><br><span class="text-muted"><?= e($w['email']) ?></span></td>
          <td><?= money($w['amount_usd']) ?></td>
          <td class="mono"><?= e(substr($w['destination_btc_address'], 0, 14)) ?>&hellip;</td>
          <td><span class="badge <?= status_badge_class($w['status']) ?>"><?= e($w['status']) ?></span></td>
          <td><?= e(time_ago($w['created_at'])) ?></td>
          <td><a class="btn btn-sm btn-secondary" href="/admin/withdrawals/<?= (int) $w['id'] ?>">Review</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
