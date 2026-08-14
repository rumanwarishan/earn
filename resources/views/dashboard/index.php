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

<div class="glass-card mb-3" style="padding:20px;background:linear-gradient(135deg, rgba(62,203,255,0.10), rgba(155,107,255,0.10));">
  <div class="text-muted" style="font-size:12.5px;">Wallet balance</div>
  <div style="font-size:30px;font-weight:750;margin:4px 0 14px;"><?= money($spendable) ?></div>
  <div class="quick-actions">
    <a class="quick-action" href="/wallet/deposit"><span class="qa-icon">💰</span>Deposit</a>
    <a class="quick-action" href="/wallet/withdraw"><span class="qa-icon">🏦</span>Withdraw</a>
    <a class="quick-action" href="/shop"><span class="qa-icon">🛍️</span>Shop</a>
    <a class="quick-action" href="/referral"><span class="qa-icon">🎁</span>Invite</a>
  </div>
</div>

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
  <div class="glass-panel stat-tile"><div class="label">Total orders</div><div class="value"><?= (int) $totalOrders ?></div></div>
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
    <div style="height:100%;width:<?= (int) $pct ?>%;background:linear-gradient(90deg, var(--cyan), var(--violet));"></div>
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
