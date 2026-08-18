<?php $spendable = \App\Services\WalletService::spendableBalance($wallet); ?>

<div class="flex items-center justify-between mb-3">
  <div>
    <div class="text-muted" style="font-size:13px;">Hello,</div>
    <h2><?= e($profile['full_name']) ?></h2>
  </div>
  <?php if ($profile['level_name'] ?? null): ?>
    <span class="membership-badge membership-<?= e($profile['level_slug']) ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.7-5.1 4.6 1.5 6.8L12 16.9l-6.2 3.5 1.5-6.8L2.2 9l6.9-.7L12 2z"/></svg>
      <?= e($profile['level_name']) ?>
    </span>
  <?php endif; ?>
</div>

<div class="glass-card mb-3 glow-border-soft" style="padding:20px;background:linear-gradient(135deg, rgba(24,17,13,0.92), rgba(16,12,10,0.94));">
  <div class="text-muted" style="font-size:12.5px;">Wallet balance</div>
  <div style="font-size:30px;font-weight:750;margin:4px 0 14px;"><?= money($spendable) ?></div>
  <div class="quick-actions">
    <a class="quick-action" href="/wallet/deposit">
      <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11"/><path d="M7 10l5 5 5-5"/><path d="M4 19h16"/></svg></span>
      Deposit
    </a>
    <a class="quick-action" href="/wallet/withdraw">
      <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V9"/><path d="M7 14l5-5 5 5"/><path d="M4 5h16"/></svg></span>
      Withdraw
    </a>
    <a class="quick-action" href="/shop">
      <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1.2 11.2a1 1 0 01-1 .8H8.2a1 1 0 01-1-.8L6 8z"/><path d="M9 8a3 3 0 016 0"/></svg></span>
      Shop
    </a>
    <a class="quick-action" href="/referral">
      <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c0-3.6 2.6-6 5.5-6s5.5 2.4 5.5 6"/><path d="M17.5 7.5v6M14.5 10.5h6"/></svg></span>
      Invite
    </a>
  </div>
</div>

<?php if ($dailyTasks): ?>
<div class="section-head"><h3>Daily task</h3><a href="/tasks">All tasks</a></div>
<?php foreach ($dailyTasks as $task): ?>
  <div class="glass-card glow-ring mb-3" style="padding:18px;">
    <div class="flex items-center justify-between">
      <div style="padding-right:12px;">
        <div style="font-size:14px;font-weight:650;"><?= e($task['title']) ?></div>
        <?php if ($task['description']): ?><div class="text-muted mt-1" style="font-size:12px;"><?= e($task['description']) ?></div><?php endif; ?>
        <div class="mt-2" style="font-size:13px;color:var(--emerald);font-weight:700;">+<?= money($task['reward_amount']) ?> bonus</div>
      </div>
      <?php if ($task['is_claimable']): ?>
        <form method="POST" action="/tasks/<?= (int) $task['id'] ?>/claim">
          <?= csrf_field() ?>
          <input type="hidden" name="return_to" value="/dashboard">
          <button class="btn btn-primary btn-sm" type="submit">Claim</button>
        </form>
      <?php else: ?>
        <span class="badge badge-emerald">Done today</span>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<div class="stat-grid mb-3">
  <div class="glass-panel stat-tile"><div class="label">Available</div><div class="value"><?= money($spendable) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Deposited</div><div class="value"><?= money($wallet['deposited_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Cashback</div><div class="value"><?= money($wallet['cashback_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Referral</div><div class="value"><?= money($wallet['referral_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Pending cashback</div><div class="value"><?= money($wallet['pending_cashback_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Withdrawable</div><div class="value"><?= money($spendable) ?></div></div>
</div>

<div class="stat-grid mb-3">
  <div class="glass-panel stat-tile"><div class="label">Today's cashback</div><div class="value"><?= money($todayCashback) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total earned</div><div class="value"><?= money($wallet['total_earned']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total tasks</div><div class="value"><?= (int) $totalOrders ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Direct referrals</div><div class="value"><?= (int) $directReferrals ?></div></div>
</div>

<?php if ($nextLevel): ?>
<div class="glass-card mb-3" style="padding:18px;">
  <div class="flex items-center justify-between mb-2">
    <div style="font-weight:600;font-size:13.5px;">Progress to <?= e($nextLevel['name']) ?></div>
    <span class="badge badge-cyan"><?= money($nextLevel['min_total_deposited']) ?> deposited</span>
  </div>
  <?php $pct = $nextLevel['min_total_deposited'] > 0 ? min(100, (float) $wallet['total_deposited'] / (float) $nextLevel['min_total_deposited'] * 100) : 0; ?>
  <div style="height:8px;border-radius:999px;background:var(--surface-2);overflow:hidden;">
    <div style="height:100%;width:<?= (int) $pct ?>%;background:linear-gradient(90deg, var(--orange), var(--orange-hi));"></div>
  </div>
</div>
<?php endif; ?>

<div class="section-head">
  <h3>Featured cashback</h3>
  <a href="/shop">See all</a>
</div>
<?php if ($featuredProducts): ?>
<div class="product-grid mb-3">
  <?php foreach ($featuredProducts as $p): ?>
    <?= view('partials.product-card', ['product' => $p]) ?>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state"><div class="icon">🛍️</div>No featured products yet.</div>
<?php endif; ?>

<div class="section-head">
  <h3>Recent activity</h3>
  <a href="/wallet">See all</a>
</div>
<div class="glass-card">
  <?php if (!$recentActivity): ?>
    <div class="empty-state"><div class="icon">📭</div>No activity yet.</div>
  <?php else: ?>
    <?php foreach ($recentActivity as $tx): ?>
      <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div>
          <div style="font-size:13.5px;font-weight:600;"><?= e(ledger_label($tx['type'])) ?></div>
          <div class="text-muted" style="font-size:11.5px;"><?= e(time_ago($tx['created_at'])) ?></div>
        </div>
        <div style="font-weight:700;color:<?= bccomp($tx['amount'], '0', 2) >= 0 ? 'var(--emerald)' : 'var(--danger)' ?>;">
          <?= bccomp($tx['amount'], '0', 2) >= 0 ? '+' : '' ?><?= money($tx['amount']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
