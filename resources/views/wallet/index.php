<?php $spendable = \App\Services\WalletService::spendableBalance($wallet); ?>
<h2 class="mb-3">Wallet</h2>

<div class="glass-card mb-3 glow-border-soft" style="padding:20px;">
  <div class="text-muted" style="font-size:12.5px;">Total balance</div>
  <div style="font-size:28px;font-weight:750;margin:4px 0 16px;"><?= money($spendable) ?></div>
  <div class="stat-grid">
    <div><div class="text-muted" style="font-size:11.5px;">Deposited</div><div style="font-weight:700;"><?= money($wallet['deposited_balance']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Cashback</div><div style="font-weight:700;"><?= money($wallet['cashback_balance']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Referral</div><div style="font-weight:700;"><?= money($wallet['referral_balance']) ?></div></div>
    <div><div class="text-muted" style="font-size:11.5px;">Pending</div><div style="font-weight:700;"><?= money($wallet['pending_cashback_balance']) ?></div></div>
  </div>
  <div class="flex gap-2 mt-4">
    <a class="btn btn-primary" href="/wallet/deposit">Deposit</a>
    <a class="btn btn-secondary" href="/wallet/withdraw">Withdraw</a>
  </div>
</div>

<div class="glass-card mb-3" style="padding:16px 20px;">
  <div class="flex items-center justify-between">
    <div>
      <div class="text-muted" style="font-size:11.5px;">Billions Flight B$ balance</div>
      <div style="font-size:18px;font-weight:750;"><?= gp($bsBalance) ?></div>
    </div>
    <a class="btn btn-secondary btn-sm" href="/game">Exchange to wallet</a>
  </div>
</div>

<div class="tabs mb-3">
  <?php foreach (['all'=>'All','deposits'=>'Deposits','cashback'=>'Cashback','referral'=>'Referral','withdrawals'=>'Withdrawals'] as $key => $label): ?>
    <a class="tab <?= $tab === $key ? 'active' : '' ?>" href="/wallet?tab=<?= $key ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="glass-card">
  <?php if (!$entries): ?>
    <div class="empty-state"><div class="icon">📭</div>No transactions in this category yet.</div>
  <?php else: ?>
    <?php foreach ($entries as $tx): ?>
      <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div>
          <div style="font-size:13.5px;font-weight:600;"><?= e(ledger_label($tx['type'])) ?></div>
          <div class="text-muted" style="font-size:11.5px;"><?= e(date('M j, Y g:ia', strtotime($tx['created_at']))) ?></div>
          <?php if ($tx['description']): ?><div class="text-muted" style="font-size:11.5px;"><?= e($tx['description']) ?></div><?php endif; ?>
        </div>
        <div style="text-align:right;">
          <div style="font-weight:700;color:<?= bccomp($tx['amount'], '0', 2) >= 0 ? 'var(--emerald)' : 'var(--danger)' ?>;">
            <?= bccomp($tx['amount'], '0', 2) >= 0 ? '+' : '' ?><?= money($tx['amount']) ?>
          </div>
          <span class="badge <?= status_badge_class($tx['status']) ?>"><?= e($tx['status']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($page > 1 || $hasMore): ?>
<div class="flex justify-between mt-3">
  <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="/wallet?tab=<?= $tab ?>&page=<?= $page - 1 ?>">Previous</a><?php else: ?><span></span><?php endif; ?>
  <?php if ($hasMore): ?><a class="btn btn-ghost btn-sm" href="/wallet?tab=<?= $tab ?>&page=<?= $page + 1 ?>">Next</a><?php endif; ?>
</div>
<?php endif; ?>
