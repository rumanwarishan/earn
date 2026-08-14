<h2 class="mb-3">Profile</h2>

<div class="glass-card mb-3" style="padding:22px;text-align:center;">
  <div style="width:64px;height:64px;border-radius:50%;margin:0 auto 12px;background:linear-gradient(135deg, var(--cyan), var(--violet));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:22px;">
    <?= e(mb_strtoupper(mb_substr($profile['full_name'], 0, 1))) ?>
  </div>
  <div style="font-weight:700;font-size:17px;"><?= e($profile['full_name']) ?></div>
  <div class="text-muted" style="font-size:13px;margin-bottom:8px;"><?= e($profile['email']) ?></div>
  <?php if ($profile['level_name']): ?><span class="membership-badge membership-<?= e($profile['level_slug']) ?>"><?= e($profile['level_name']) ?></span><?php endif; ?>
</div>

<div class="glass-card mb-3">
  <a href="/wallet" class="flex items-center justify-between" style="padding:16px;border-bottom:1px solid rgba(255,255,255,0.05);"><span>💰 Wallet</span><span class="text-muted">›</span></a>
  <a href="/orders" class="flex items-center justify-between" style="padding:16px;border-bottom:1px solid rgba(255,255,255,0.05);"><span>📦 Orders</span><span class="text-muted">›</span></a>
  <a href="/referral" class="flex items-center justify-between" style="padding:16px;border-bottom:1px solid rgba(255,255,255,0.05);"><span>🎁 Referrals</span><span class="text-muted">›</span></a>
  <a href="/notifications" class="flex items-center justify-between" style="padding:16px;"><span>🔔 Notifications</span><span class="text-muted">›</span></a>
</div>

<div class="glass-card mb-3" style="padding:20px;">
  <h3 class="mb-3" style="font-size:14px;">Change password</h3>
  <form method="POST" action="/profile/password">
    <?= csrf_field() ?>
    <div class="field"><label>Current password</label><input class="input" type="password" name="current_password" required></div>
    <div class="field"><label>New password</label><input class="input" type="password" name="new_password" required minlength="8"></div>
    <div class="field"><label>Confirm new password</label><input class="input" type="password" name="confirm_password" required minlength="8"></div>
    <button class="btn btn-primary" type="submit">Update password</button>
  </form>
</div>

<div class="glass-panel mb-3" style="padding:16px;font-size:12.5px;color:var(--text-mid);">
  🔒 We will never ask for your private key, seed phrase, or wallet password. Only ever share your public BTC address for deposits/withdrawals.
</div>

<form method="POST" action="/logout">
  <?= csrf_field() ?>
  <button class="btn btn-danger" type="submit">Log out</button>
</form>
