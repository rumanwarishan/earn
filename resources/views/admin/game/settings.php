<div class="admin-toolbar">
  <h2>Billions Flight settings</h2>
  <a class="btn btn-secondary btn-sm" href="/admin/game">Back to dashboard</a>
</div>

<form method="POST" action="/admin/game/settings" class="glass-card mb-3" style="padding:24px;max-width:640px;">
  <?= csrf_field() ?>
  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="enabled" <?= !empty($settings['enabled']) ? 'checked' : '' ?>> Game enabled</label>
  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="maintenance_mode" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>> Maintenance mode (hides entry, existing rounds still resolve)</label>

  <h3 class="mb-3 mt-4" style="font-size:14px;">Entry limits</h3>
  <div class="field"><label>Minimum entry (GP)</label><input class="input" type="text" name="minimum_entry" value="<?= e((string) $settings['minimum_entry']) ?>"></div>
  <div class="field"><label>Maximum entry (GP)</label><input class="input" type="text" name="maximum_entry" value="<?= e((string) $settings['maximum_entry']) ?>"></div>

  <h3 class="mb-3 mt-4" style="font-size:14px;">Round pacing</h3>
  <div class="field"><label>Countdown seconds</label><input class="input" type="text" name="countdown_seconds" value="<?= e((string) $settings['countdown_seconds']) ?>"></div>
  <div class="field"><label>Crash display duration (seconds)</label><input class="input" type="text" name="round_grace_seconds" value="<?= e((string) $settings['round_grace_seconds']) ?>"></div>
  <div class="field"><label>Growth rate</label><input class="input" type="text" name="growth_rate" value="<?= e((string) $settings['growth_rate']) ?>"><div class="field-hint">Higher = multiplier rises faster. Multiplier = e^(rate &times; seconds).</div></div>

  <h3 class="mb-3 mt-4" style="font-size:14px;">New players &amp; daily bonus</h3>
  <div class="field"><label>Starting Game Points balance</label><input class="input" type="text" name="starting_balance" value="<?= e((string) $settings['starting_balance']) ?>"></div>
  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="daily_bonus_enabled" <?= !empty($settings['daily_bonus_enabled']) ? 'checked' : '' ?>> Daily free Game Points bonus enabled</label>
  <div class="field"><label>Daily bonus amount (GP)</label><input class="input" type="text" name="daily_bonus_amount" value="<?= e((string) $settings['daily_bonus_amount']) ?>"></div>
  <div class="field"><label>Maximum daily bonus claims per day</label><input class="input" type="text" name="daily_bonus_max_per_day" value="<?= e((string) $settings['daily_bonus_max_per_day']) ?>"></div>

  <button class="btn btn-primary" type="submit">Save settings</button>
</form>
