<a href="/admin/deposits" class="text-muted" style="font-size:13px;">&larr; Back to deposits</a>
<h2 class="mt-3 mb-3">Deposit #<?= (int) $deposit['id'] ?></h2>

<div class="glass-card mb-3" style="padding:20px;">
  <div class="stat-grid">
    <div><div class="text-muted" style="font-size:11.5px;">User</div><div style="font-weight:600;"><?= e($deposit['full_name']) ?> (<?= e($deposit['email']) ?>)</div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Status</div><span class="badge <?= status_badge_class($deposit['status']) ?>"><?= e($deposit['status']) ?></span></div>
    <div><div class="text-muted" style="font-size:11.5px;">BTC amount claimed</div><div style="font-weight:600;" class="mono"><?= btc_amount($deposit['btc_amount_claimed']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">TXID</div><div class="mono" style="word-break:break-all;font-size:12.5px;"><?= e($deposit['txid']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Address shown</div><div class="mono" style="word-break:break-all;font-size:12.5px;"><?= e($deposit['btc_address_shown']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Submitted</div><div><?= e(date('M j, Y g:ia', strtotime($deposit['created_at']))) ?></div></div>
    <?php if ($deposit['usd_amount_credited']): ?>
      <div><div class="text-muted" style="font-size:11.5px;">USD credited</div><div style="font-weight:700;color:var(--emerald);"><?= money($deposit['usd_amount_credited']) ?></div></div>
      <div><div class="text-muted" style="font-size:11.5px;">Rate used</div><div><?= money($deposit['btc_usd_rate']) ?> / BTC</div></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($deposit['status'] === 'pending'): ?>
<div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
  <form method="POST" action="/admin/deposits/<?= (int) $deposit['id'] ?>/approve" class="glass-card" style="padding:20px;">
    <?= csrf_field() ?>
    <h3 class="mb-3" style="font-size:14px;color:var(--emerald);">Approve deposit</h3>
    <div class="field">
      <label>BTC/USD rate at approval</label>
      <input class="input" type="text" name="btc_usd_rate" placeholder="e.g. 118000.00" required pattern="\d+(\.\d{1,2})?">
      <div class="field-hint">USD credited = BTC amount × rate. This is permanently recorded and won't change later.</div>
    </div>
    <div class="field"><label>Notes (optional)</label><input class="input" type="text" name="notes" maxlength="500"></div>
    <button class="btn btn-primary" type="submit" onclick="return confirm('Approve this deposit and credit the user\'s wallet?')">Approve &amp; credit</button>
  </form>
  <form method="POST" action="/admin/deposits/<?= (int) $deposit['id'] ?>/reject" class="glass-card" style="padding:20px;">
    <?= csrf_field() ?>
    <h3 class="mb-3" style="font-size:14px;color:var(--danger);">Reject deposit</h3>
    <div class="field"><label>Reason (required)</label><input class="input" type="text" name="reason" required maxlength="500"></div>
    <button class="btn btn-danger" type="submit" onclick="return confirm('Reject this deposit?')">Reject</button>
  </form>
</div>
<?php endif; ?>

<div class="section-head mt-4"><h3>Review history</h3></div>
<div class="glass-card">
  <?php if (!$reviews): ?><div class="empty-state">No review actions yet.</div><?php endif; ?>
  <?php foreach ($reviews as $r): ?>
    <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
      <div style="font-size:13px;"><strong><?= e($r['admin_name'] ?? 'Admin') ?></strong> — <?= e($r['action']) ?></div>
      <?php if ($r['notes']): ?><div class="text-muted" style="font-size:12.5px;"><?= e($r['notes']) ?></div><?php endif; ?>
      <div class="text-muted" style="font-size:11px;"><?= e(time_ago($r['created_at'])) ?></div>
    </div>
  <?php endforeach; ?>
</div>
