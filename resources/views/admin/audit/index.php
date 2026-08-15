<form method="GET" action="/admin/audit-logs" class="admin-toolbar">
  <input class="input" type="text" name="action" value="<?= e($action) ?>" placeholder="Filter by action (e.g. deposit.approved)">
  <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
</form>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>Actor</th><th>Action</th><th>Target</th><th>Reason</th><th>IP</th><th>Date</th></tr></thead>
    <tbody>
      <?php if (!$logs): ?><tr><td colspan="6" class="text-muted">No audit log entries found.</td></tr><?php endif; ?>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= e($l['actor_type']) ?> #<?= (int) ($l['actor_id'] ?? 0) ?></td>
          <td class="mono" style="font-size:12px;"><?= e($l['action']) ?></td>
          <td><?= e($l['target_type'] ?? '') ?> <?= $l['target_id'] ? '#' . (int) $l['target_id'] : '' ?></td>
          <td class="text-muted"><?= e($l['reason'] ?? '') ?></td>
          <td class="mono" style="font-size:12px;"><?= e($l['ip_address'] ?? '') ?></td>
          <td><?= e(time_ago($l['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
