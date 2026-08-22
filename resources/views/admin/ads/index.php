<div class="admin-toolbar">
  <h2>Watch &amp; Earn</h2>
  <a class="btn btn-primary btn-sm" href="/admin/ads/new">+ New advertisement</a>
</div>

<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Total advertisements</div><div class="value"><?= (int) $stats['total_ads'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Active</div><div class="value"><?= (int) $stats['active_ads'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total watches completed</div><div class="value"><?= (int) $stats['total_completions'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total rewards paid</div><div class="value"><?= money($stats['total_paid']) ?></div></div>
</div>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>Image</th><th>Advertisement</th><th>Type</th><th>Reward</th><th>Watch time</th><th>Completions</th><th>Paid</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php if (!$ads): ?><tr><td colspan="9" class="text-muted">No advertisements yet.</td></tr><?php endif; ?>
      <?php foreach ($ads as $a): ?>
        <?php $videoId = $a['type'] === 'video' && !empty($a['destination_url']) ? youtube_video_id((string) $a['destination_url']) : null; ?>
        <tr>
          <td>
            <?php if (!empty($a['image_path'])): ?>
              <img class="admin-thumb" src="<?= e($a['image_path']) ?>" alt="" loading="lazy" onerror="this.outerHTML='<span class=&quot;admin-thumb admin-thumb-missing&quot; title=&quot;File missing on server&quot;>⚠</span>';">
            <?php elseif ($videoId !== null): ?>
              <img class="admin-thumb" src="<?= e(youtube_thumbnail_url($videoId)) ?>" alt="" loading="lazy" onerror="this.outerHTML='<span class=&quot;admin-thumb admin-thumb-missing&quot; title=&quot;YouTube thumbnail unavailable&quot;>⚠</span>';">
            <?php elseif ($a['type'] === 'video' && !empty($a['destination_url'])): ?>
              <span class="admin-thumb admin-thumb-missing" title="Couldn't extract a video ID from this URL - edit and re-save it">⚠</span>
            <?php else: ?>
              <span class="admin-thumb-empty" title="No image uploaded">—</span>
            <?php endif; ?>
          </td>
          <td class="cell-truncate" title="<?= e($a['title']) ?>"><?= e($a['title']) ?></td>
          <td><span class="badge badge-muted"><?= e(ucfirst($a['type'])) ?></span></td>
          <td><?= money($a['reward_amount']) ?></td>
          <td><?= (int) $a['watch_seconds'] ?>s</td>
          <td><?= (int) $a['completion_count'] ?><?= $a['max_completions'] !== null ? ' / ' . (int) $a['max_completions'] : '' ?></td>
          <td><?= money($a['total_paid']) ?></td>
          <td><span class="badge <?= $a['is_active'] ? 'badge-emerald' : 'badge-muted' ?>"><?= $a['is_active'] ? 'Active' : 'Disabled' ?></span></td>
          <td class="flex gap-2">
            <a class="btn btn-sm btn-secondary" href="/admin/ads/<?= (int) $a['id'] ?>/edit">Edit</a>
            <form method="POST" action="/admin/ads/<?= (int) $a['id'] ?>/toggle"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= $a['is_active'] ? 'Disable' : 'Enable' ?></button></form>
            <form method="POST" action="/admin/ads/<?= (int) $a['id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this advertisement?')">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
