<div class="admin-toolbar">
  <h2>Flight round history</h2>
  <a class="btn btn-secondary btn-sm" href="/admin/game">Back to dashboard</a>
</div>
<div class="glass-panel mb-3" style="padding:12px 16px;font-size:12.5px;color:var(--text-mid);">
  Completed rounds are immutable - crash points and payouts here can never be edited.
</div>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>Round</th><th>Status</th><th>Crash</th><th>Players</th><th>Total entries</th><th>Total payouts</th><th>Started</th></tr></thead>
    <tbody>
      <?php if (!$rounds): ?><tr><td colspan="7" class="text-muted">No rounds yet.</td></tr><?php endif; ?>
      <?php foreach ($rounds as $r): ?>
        <tr>
          <td class="mono" style="font-size:11.5px;"><?= e(substr($r['round_uuid'], 0, 8)) ?>&hellip;</td>
          <td><span class="badge <?= $r['status'] === 'crashed' || $r['status'] === 'completed' ? 'badge-muted' : ($r['status'] === 'running' ? 'badge-emerald' : 'badge-amber') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          <td><?= number_format((float) $r['crash_multiplier'], 2) ?>x</td>
          <td><?= (int) $r['player_count'] ?></td>
          <td><?= gp($r['total_entries']) ?></td>
          <td><?= gp($r['total_payouts']) ?></td>
          <td class="text-muted" style="font-size:11.5px;"><?= $r['started_at'] ? e($r['started_at']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($page > 1 || $hasMore): ?>
<div class="flex justify-between mt-3">
  <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="/admin/game/rounds?page=<?= $page - 1 ?>">Previous</a><?php else: ?><span></span><?php endif; ?>
  <?php if ($hasMore): ?><a class="btn btn-ghost btn-sm" href="/admin/game/rounds?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
</div>
<?php endif; ?>
