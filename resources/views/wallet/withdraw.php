<h2 class="mb-3">Withdraw funds</h2>

<div class="glass-card mb-3" style="padding:20px;">
  <div class="text-muted" style="font-size:12.5px;">Withdrawable balance</div>
  <div style="font-size:26px;font-weight:750;margin:4px 0;"><?= money($spendable) ?></div>
  <div class="text-muted" style="font-size:12px;">Minimum <?= money($minWithdrawal) ?> · Maximum <?= money($maxWithdrawal) ?></div>
</div>

<?php if (bccomp($spendable, $minWithdrawal, 2) < 0): ?>
  <div class="alert alert-error">You need at least <?= money($minWithdrawal) ?> withdrawable balance to request a withdrawal. Your current balance is <?= money($spendable) ?>.</div>
<?php else: ?>
<form method="POST" action="/wallet/withdraw" class="glass-card mb-3" style="padding:20px;">
  <?= csrf_field() ?>
  <div class="field">
    <label>Amount (USD)</label>
    <input class="input" type="text" name="amount" placeholder="1000.00" required pattern="\d+(\.\d{1,2})?">
  </div>
  <div class="field">
    <label>Your BTC withdrawal address</label>
    <input class="input mono" type="text" name="btc_address" placeholder="bc1..." required>
    <div class="field-hint">Double-check this address. Payments cannot be reversed. Never share your private key or seed phrase - we will never ask for it.</div>
  </div>
  <button class="btn btn-primary" type="submit">Request withdrawal</button>
</form>
<?php endif; ?>

<div class="glass-panel mb-3" style="padding:16px;font-size:13px;color:var(--text-mid);">
  Withdrawals are manually reviewed and processed by an admin: <strong>Requested → Approved → Processing → Paid → Completed</strong>.
  You'll be notified at every step.
</div>

<div class="section-head"><h3>Recent withdrawals</h3></div>
<div class="glass-card">
  <?php if (!$recentWithdrawals): ?>
    <div class="empty-state"><div class="icon">🏦</div>No withdrawals yet.</div>
  <?php else: ?>
    <?php foreach ($recentWithdrawals as $w): ?>
      <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div>
          <div style="font-size:13.5px;font-weight:600;"><?= money($w['amount_usd']) ?></div>
          <div class="text-muted mono" style="font-size:11px;"><?= e(substr($w['destination_btc_address'], 0, 14)) ?>&hellip;</div>
          <?php if ($w['status'] === 'rejected' && $w['rejection_reason']): ?><div class="text-muted" style="font-size:11.5px;">Reason: <?= e($w['rejection_reason']) ?></div><?php endif; ?>
        </div>
        <div style="text-align:right;">
          <span class="badge <?= status_badge_class($w['status']) ?>"><?= e($w['status']) ?></span>
          <?php if ($w['status'] === 'pending'): ?>
            <form method="POST" action="/wallet/withdraw/<?= (int) $w['id'] ?>/cancel" class="mt-2">
              <?= csrf_field() ?>
              <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Cancel this withdrawal request?')">Cancel</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
