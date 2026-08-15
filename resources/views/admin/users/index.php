<form method="GET" action="/admin/users" class="admin-toolbar">
  <input class="input" type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name, email, or referral code...">
  <button class="btn btn-secondary btn-sm" type="submit">Search</button>
</form>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>Name</th><th>Email</th><th>Level</th><th>Verified</th><th>Status</th><th>Wallet total</th><th>Joined</th><th></th></tr></thead>
    <tbody>
      <?php if (!$users): ?><tr><td colspan="8" class="text-muted">No users found.</td></tr><?php endif; ?>
      <?php foreach ($users as $u): ?>
        <?php $total = bcadd(bcadd((string) $u['deposited_balance'], (string) $u['cashback_balance'], 2), (string) $u['referral_balance'], 2); ?>
        <tr>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['level_name'] ?? '—') ?></td>
          <td><?= $u['email_verified_at'] ? '✅' : '—' ?></td>
          <td><span class="badge <?= status_badge_class($u['status']) ?>"><?= e($u['status']) ?></span></td>
          <td><?= money($total) ?></td>
          <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
          <td><a class="btn btn-sm btn-secondary" href="/admin/users/<?= (int) $u['id'] ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
