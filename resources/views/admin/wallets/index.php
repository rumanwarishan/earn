<form method="GET" action="/admin/wallets" class="admin-toolbar">
  <input class="input" type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name or email...">
  <button class="btn btn-secondary btn-sm" type="submit">Search</button>
</form>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>User</th><th>Type</th><th>Field</th><th>Amount</th><th>Balance after</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
      <?php if (!$entries): ?><tr><td colspan="7" class="text-muted">No transactions found.</td></tr><?php endif; ?>
      <?php foreach ($entries as $tx): ?>
        <tr>
          <td><a href="/admin/wallets/<?= (int) $tx['user_id'] ?>"><?= e($tx['full_name']) ?></a></td>
          <td><?= e(ledger_label($tx['type'])) ?></td>
          <td><?= e($tx['balance_field']) ?></td>
          <td style="color:<?= bccomp($tx['amount'], '0', 2) >= 0 ? 'var(--emerald)' : 'var(--danger)' ?>;">
            <?= bccomp($tx['amount'], '0', 2) >= 0 ? '+' : '' ?><?= money($tx['amount']) ?>
          </td>
          <td><?= money($tx['resulting_balance']) ?></td>
          <td><span class="badge <?= status_badge_class($tx['status']) ?>"><?= e($tx['status']) ?></span></td>
          <td><?= e(time_ago($tx['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
