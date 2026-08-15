<div class="admin-toolbar">
  <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $key => $label): ?>
    <a class="tab <?= $status === $key ? 'active' : '' ?>" href="/admin/deposits?status=<?= $key ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>User</th><th>BTC amount</th><th>TXID</th><th>USD credited</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
      <?php if (!$deposits): ?><tr><td colspan="7" class="text-muted">No deposits found.</td></tr><?php endif; ?>
      <?php foreach ($deposits as $d): ?>
        <tr>
          <td><?= e($d['full_name']) ?><br><span class="text-muted"><?= e($d['email']) ?></span></td>
          <td><?= btc_amount($d['btc_amount_claimed']) ?></td>
          <td class="mono"><?= e(substr($d['txid'], 0, 14)) ?>&hellip;</td>
          <td><?= $d['usd_amount_credited'] ? money($d['usd_amount_credited']) : '—' ?></td>
          <td><span class="badge <?= status_badge_class($d['status']) ?>"><?= e($d['status']) ?></span></td>
          <td><?= e(time_ago($d['created_at'])) ?></td>
          <td><a class="btn btn-sm btn-secondary" href="/admin/deposits/<?= (int) $d['id'] ?>">Review</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
