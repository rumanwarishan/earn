<a href="/admin/users" class="text-muted" style="font-size:13px;">&larr; Back to users</a>
<h2 class="mt-3 mb-3"><?= e($user['full_name']) ?></h2>

<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Email</div><div class="value" style="font-size:14px;"><?= e($user['email']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Status</div><span class="badge <?= status_badge_class($user['status']) ?>"><?= e($user['status']) ?></span></div>
  <div class="glass-panel stat-tile"><div class="label">Membership</div><div class="value" style="font-size:14px;"><?= e($user['level_name'] ?? '—') ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Referral code</div><div class="value mono" style="font-size:14px;"><?= e($user['referral_code']) ?></div></div>
</div>
<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Deposited</div><div class="value"><?= money($wallet['deposited_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Cashback</div><div class="value"><?= money($wallet['cashback_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Referral</div><div class="value"><?= money($wallet['referral_balance']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Approved deposits</div><div class="value"><?= (int) $approvedDeposits ?></div></div>
</div>

<div class="glass-card mb-3" style="padding:18px;">
  <div><strong>Referred by:</strong> <?= $referrer ? e($referrer['full_name']) . ' (' . e($referrer['email']) . ')' : 'None (bootstrap invitation)' ?></div>
  <div class="mt-2"><strong>Email verified:</strong> <?= $user['email_verified_at'] ? e($user['email_verified_at']) : 'Not verified' ?></div>
  <div class="mt-2"><strong>Last login:</strong> <?= $user['last_login_at'] ? e($user['last_login_at']) . ' from ' . e($user['last_login_ip']) : 'Never' ?></div>
</div>

<div class="flex gap-2 mb-3">
  <a class="btn btn-secondary" href="/admin/wallets/<?= (int) $user['id'] ?>">View wallet &amp; ledger</a>
</div>

<div class="glass-card" style="padding:20px;">
  <h3 class="mb-3" style="font-size:14px;">Account status</h3>
  <form method="POST" action="/admin/users/<?= (int) $user['id'] ?>/status" class="flex gap-2 items-center">
    <?= csrf_field() ?>
    <select class="input" name="status" style="width:auto;">
      <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
    </select>
    <input class="input" type="text" name="reason" placeholder="Reason (optional)" style="width:auto;flex:1;">
    <button class="btn btn-primary btn-sm" type="submit">Update</button>
  </form>
</div>
