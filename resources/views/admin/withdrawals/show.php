<a href="/admin/withdrawals" class="text-muted" style="font-size:13px;">&larr; Back to withdrawals</a>
<h2 class="mt-3 mb-3">Withdrawal #<?= (int) $withdrawal['id'] ?></h2>

<div class="glass-card mb-3" style="padding:20px;">
  <div class="stat-grid">
    <div><div class="text-muted" style="font-size:11.5px;">User</div><div style="font-weight:600;"><?= e($withdrawal['full_name']) ?> (<?= e($withdrawal['email']) ?>)</div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Status</div><span class="badge <?= status_badge_class($withdrawal['status']) ?>"><?= e($withdrawal['status']) ?></span></div>
    <div><div class="text-muted" style="font-size:11.5px;">Amount</div><div style="font-weight:700;"><?= money($withdrawal['amount_usd']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Destination address</div><div class="mono" style="word-break:break-all;font-size:12.5px;"><?= e($withdrawal['destination_btc_address']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Requested</div><div><?= e(date('M j, Y g:ia', strtotime($withdrawal['created_at']))) ?></div></div>
    <?php if ($withdrawal['payout_txid']): ?>
      <div><div class="text-muted" style="font-size:11.5px;">Payout TXID</div><div class="mono" style="word-break:break-all;font-size:12.5px;"><?= e($withdrawal['payout_txid']) ?></div></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($withdrawal['status'] === 'pending'): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
  <form method="POST" action="/admin/withdrawals/<?= (int) $withdrawal['id'] ?>/approve" class="glass-card" style="padding:20px;">
    <?= csrf_field() ?>
    <h3 class="mb-3" style="font-size:14px;color:var(--emerald);">Approve</h3>
    <div class="field"><label>Notes (optional)</label><input class="input" type="text" name="notes" maxlength="500"></div>
    <button class="btn btn-primary" type="submit">Approve</button>
  </form>
  <form method="POST" action="/admin/withdrawals/<?= (int) $withdrawal['id'] ?>/reject" class="glass-card" style="padding:20px;">
    <?= csrf_field() ?>
    <h3 class="mb-3" style="font-size:14px;color:var(--danger);">Reject &amp; refund</h3>
    <div class="field"><label>Reason (required)</label><input class="input" type="text" name="reason" required maxlength="500"></div>
    <button class="btn btn-danger" type="submit" onclick="return confirm('Reject and return funds to the user\'s wallet?')">Reject</button>
  </form>
</div>
<?php elseif ($withdrawal['status'] === 'approved'): ?>
  <form method="POST" action="/admin/withdrawals/<?= (int) $withdrawal['id'] ?>/processing" class="glass-card mb-3" style="padding:20px;">
    <?= csrf_field() ?>
    <h3 class="mb-3" style="font-size:14px;">Mark as processing</h3>
    <div class="field"><label>Notes (optional)</label><input class="input" type="text" name="notes" maxlength="500"></div>
    <button class="btn btn-secondary" type="submit">Mark processing</button>
  </form>
  <?= view('admin.withdrawals._pay-form', ['withdrawal' => $withdrawal]) ?>
<?php elseif ($withdrawal['status'] === 'processing'): ?>
  <?= view('admin.withdrawals._pay-form', ['withdrawal' => $withdrawal]) ?>
<?php elseif ($withdrawal['status'] === 'paid'): ?>
  <form method="POST" action="/admin/withdrawals/<?= (int) $withdrawal['id'] ?>/completed" class="glass-card" style="padding:20px;">
    <?= csrf_field() ?>
    <h3 class="mb-3" style="font-size:14px;color:var(--emerald);">Mark completed</h3>
    <button class="btn btn-primary" type="submit">Mark completed</button>
  </form>
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
