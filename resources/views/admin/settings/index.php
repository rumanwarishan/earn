<h2 class="mb-3">Platform settings</h2>

<form method="POST" action="/admin/settings" class="glass-card mb-3" style="padding:24px;max-width:640px;">
  <?= csrf_field() ?>
  <h3 class="mb-3" style="font-size:14px;">General</h3>
  <div class="field"><label>Site name</label><input class="input" type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>"></div>
  <div class="field"><label>Support email</label><input class="input" type="email" name="support_email" value="<?= e($settings['support_email'] ?? '') ?>"></div>
  <div class="field"><label>Currency</label><input class="input" type="text" name="currency" value="<?= e($settings['currency'] ?? 'USD') ?>"></div>
  <div class="field"><label>Timezone</label><input class="input" type="text" name="timezone" value="<?= e($settings['timezone'] ?? 'UTC') ?>"></div>

  <h3 class="mb-3 mt-4" style="font-size:14px;">Deposits</h3>
  <div class="field"><label>BTC deposit address</label><input class="input mono" type="text" name="btc_deposit_address" value="<?= e($settings['btc_deposit_address'] ?? '') ?>"></div>
  <div class="field"><label>Network note</label><input class="input" type="text" name="btc_deposit_network_note" value="<?= e($settings['btc_deposit_network_note'] ?? '') ?>"></div>
  <div class="field"><label>Minimum deposit (USD)</label><input class="input" type="text" name="min_deposit_usd" value="<?= e((string) ($settings['min_deposit_usd'] ?? '50')) ?>"></div>

  <h3 class="mb-3 mt-4" style="font-size:14px;">Withdrawals</h3>
  <div class="field"><label>Minimum withdrawal (USD)</label><input class="input" type="text" name="min_withdrawal_usd" value="<?= e((string) ($settings['min_withdrawal_usd'] ?? '1000')) ?>"></div>
  <div class="field"><label>Maximum withdrawal (USD)</label><input class="input" type="text" name="max_withdrawal_usd" value="<?= e((string) ($settings['max_withdrawal_usd'] ?? '50000')) ?>"></div>

  <h3 class="mb-3 mt-4" style="font-size:14px;">Referral &amp; bonuses</h3>
  <div class="field"><label>Welcome bonus amount (USD)</label><input class="input" type="text" name="welcome_bonus_amount" value="<?= e((string) ($settings['welcome_bonus_amount'] ?? '20')) ?>"></div>
  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="welcome_bonus_enabled" <?= !empty($settings['welcome_bonus_enabled']) ? 'checked' : '' ?>> Welcome bonus enabled</label>

  <h3 class="mb-3 mt-4" style="font-size:14px;">Platform</h3>
  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="registration_enabled" <?= !empty($settings['registration_enabled']) ? 'checked' : '' ?>> Registration enabled</label>
  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="chatbot_enabled" <?= !empty($settings['chatbot_enabled']) ? 'checked' : '' ?>> Chatbot enabled</label>
  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="maintenance_mode" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>> Maintenance mode</label>

  <button class="btn btn-primary" type="submit">Save settings</button>
</form>

<h3 class="mb-3">Referral levels</h3>
<div class="glass-card mb-3">
  <?php foreach ($referralLevels as $rl): ?>
    <form method="POST" action="/admin/settings/referral-levels/<?= (int) $rl['id'] ?>" class="flex items-center gap-2" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);flex-wrap:wrap;">
      <?= csrf_field() ?>
      <span style="font-weight:600;min-width:70px;">Level <?= (int) $rl['level'] ?></span>
      <select class="input" name="bonus_type" style="width:auto;">
        <option value="fixed" <?= $rl['bonus_type'] === 'fixed' ? 'selected' : '' ?>>Fixed</option>
        <option value="percentage" <?= $rl['bonus_type'] === 'percentage' ? 'selected' : '' ?>>Percentage</option>
      </select>
      <input class="input" type="text" name="bonus_amount" value="<?= e((string) $rl['bonus_amount']) ?>" style="width:100px;" title="Bonus amount">
      <input class="input" type="text" name="min_qualifying_deposit" value="<?= e((string) $rl['min_qualifying_deposit']) ?>" style="width:120px;" title="Min. qualifying deposit">
      <label class="flex items-center gap-2"><input type="checkbox" name="is_active" <?= $rl['is_active'] ? 'checked' : '' ?>> Active</label>
      <button class="btn btn-sm btn-secondary" type="submit">Save</button>
    </form>
  <?php endforeach; ?>
</div>
<form method="POST" action="/admin/settings/referral-levels"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">+ Add level</button></form>
