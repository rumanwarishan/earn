<a href="/admin/users" class="text-muted" style="font-size:13px;">&larr; Back to users</a>
<h2 class="mt-3 mb-3"><?= e($user['full_name']) ?>'s wallet</h2>

<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Deposited</div><div class="value"><?= money($wallet['deposited_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Cashback</div><div class="value"><?= money($wallet['cashback_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Referral</div><div class="value"><?= money($wallet['referral_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Reserved</div><div class="value"><?= money($wallet['reserved_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Pending cashback</div><div class="value"><?= money($wallet['pending_cashback_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total earned</div><div class="value"><?= money($wallet['total_earned']) ?></div></div>
</div>

<?php if ($wallet['is_frozen']): ?><div class="alert alert-error mb-3">This wallet is frozen. The user cannot deposit, withdraw, or spend funds.</div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;" class="mb-3">
  <form method="POST" action="/admin/wallets/<?= (int) $user['id'] ?>/adjust" class="glass-card" style="padding:20px;">
    <?= csrf_field() ?>
    <h3 class="mb-3" style="font-size:14px;">Manual adjustment</h3>
    <div class="field">
      <label>Direction</label>
      <select class="input" name="direction">
        <option value="credit">Credit (add funds)</option>
        <option value="debit">Debit (remove funds)</option>
      </select>
    </div>
    <div class="field">
      <label>Balance</label>
      <select class="input" name="balance_field">
        <option value="deposited">Deposited</option>
        <option value="cashback">Cashback</option>
        <option value="referral">Referral</option>
      </select>
    </div>
    <div class="field"><label>Amount (USD)</label><input class="input" type="text" name="amount" required pattern="\d+(\.\d{1,2})?"></div>
    <div class="field"><label>Reason (required, shown to user)</label><input class="input" type="text" name="reason" required maxlength="500"></div>
    <button class="btn btn-primary" type="submit" onclick="return confirm('Apply this manual wallet adjustment?')">Apply adjustment</button>
  </form>

  <form method="POST" action="/admin/wallets/<?= (int) $user['id'] ?>/toggle-freeze" class="glass-card" style="padding:20px;">
    <?= csrf_field() ?>
    <h3 class="mb-3" style="font-size:14px;"><?= $wallet['is_frozen'] ? 'Unfreeze wallet' : 'Freeze wallet' ?></h3>
    <p class="mb-3">Freezing blocks new deposits and withdrawals for this user until unfrozen.</p>
    <button class="btn <?= $wallet['is_frozen'] ? 'btn-primary' : 'btn-danger' ?>" type="submit" onclick="return confirm('Are you sure?')">
      <?= $wallet['is_frozen'] ? 'Unfreeze' : 'Freeze' ?> wallet
    </button>
  </form>
</div>

<div class="section-head"><h3>Ledger history</h3></div>
<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>Type</th><th>Field</th><th>Amount</th><th>Balance after</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($entries as $tx): ?>
        <tr>
          <td><?= e(ledger_label($tx['type'])) ?></td>
          <td><?= e($tx['balance_field']) ?></td>
          <td style="color:<?= bccomp($tx['amount'], '0', 2) >= 0 ? 'var(--emerald)' : 'var(--danger)' ?>;">
            <?= bccomp($tx['amount'], '0', 2) >= 0 ? '+' : '' ?><?= money($tx['amount']) ?>
          </td>
          <td><?= money($tx['resulting_balance']) ?></td>
          <td><span class="badge <?= status_badge_class($tx['status']) ?>"><?= e($tx['status']) ?></span></td>
          <td><?= e(time_ago($tx['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
