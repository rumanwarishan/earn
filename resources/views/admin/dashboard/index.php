<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Total users</div><div class="value"><?= (int) $stats['total_users'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Verified users</div><div class="value"><?= (int) $stats['verified_users'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">New (7d)</div><div class="value"><?= (int) $stats['new_users_7d'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Products live</div><div class="value"><?= (int) $stats['total_products'] ?></div></div>
</div>
<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Total deposits</div><div class="value"><?= money($stats['total_deposits_usd']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Pending deposits</div><div class="value"><?= (int) $stats['pending_deposits'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total withdrawn</div><div class="value"><?= money($stats['total_withdrawn_usd']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Pending withdrawals</div><div class="value"><?= (int) $stats['pending_withdrawals'] ?></div></div>
</div>
<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Total cashback paid</div><div class="value"><?= money($stats['total_cashback_usd']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Referral bonuses paid</div><div class="value"><?= money($stats['total_referral_usd']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total orders</div><div class="value"><?= (int) $stats['total_orders'] ?></div></div>
</div>

<div class="section-head"><h3>Pending deposits</h3><a href="/admin/deposits">View all</a></div>
<div class="glass-card table-wrap mb-3">
  <table class="data-table">
    <thead><tr><th>User</th><th>BTC amount</th><th>TXID</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
      <?php if (!$pendingDeposits): ?><tr><td colspan="5" class="text-muted">No pending deposits.</td></tr><?php endif; ?>
      <?php foreach ($pendingDeposits as $d): ?>
        <tr>
          <td><?= e($d['full_name']) ?><br><span class="text-muted"><?= e($d['email']) ?></span></td>
          <td><?= btc_amount($d['btc_amount_claimed']) ?></td>
          <td class="mono"><?= e(substr($d['txid'], 0, 16)) ?>&hellip;</td>
          <td><?= e(time_ago($d['created_at'])) ?></td>
          <td><a class="btn btn-sm btn-secondary" href="/admin/deposits/<?= (int) $d['id'] ?>">Review</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="section-head"><h3>Pending withdrawals</h3><a href="/admin/withdrawals">View all</a></div>
<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>User</th><th>Amount</th><th>BTC address</th><th>Requested</th><th></th></tr></thead>
    <tbody>
      <?php if (!$pendingWithdrawals): ?><tr><td colspan="5" class="text-muted">No pending withdrawals.</td></tr><?php endif; ?>
      <?php foreach ($pendingWithdrawals as $w): ?>
        <tr>
          <td><?= e($w['full_name']) ?><br><span class="text-muted"><?= e($w['email']) ?></span></td>
          <td><?= money($w['amount_usd']) ?></td>
          <td class="mono"><?= e(substr($w['destination_btc_address'], 0, 16)) ?>&hellip;</td>
          <td><?= e(time_ago($w['created_at'])) ?></td>
          <td><a class="btn btn-sm btn-secondary" href="/admin/withdrawals/<?= (int) $w['id'] ?>">Review</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
