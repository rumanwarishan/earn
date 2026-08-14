<h2 class="mb-3">Notifications</h2>
<div class="glass-card">
  <?php if (!$notifications): ?>
    <div class="empty-state"><div class="icon">🔔</div>You're all caught up.</div>
  <?php else: ?>
    <?php foreach ($notifications as $n): ?>
      <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div style="font-size:13.5px;font-weight:600;"><?= e($n['title']) ?></div>
        <div class="text-muted" style="font-size:12.5px;margin-top:3px;"><?= e($n['message']) ?></div>
        <div class="text-muted" style="font-size:11px;margin-top:5px;"><?= e(time_ago($n['created_at'])) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
