<h2 class="mb-3">Membership levels</h2>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
<?php foreach ($levels as $l): ?>
  <form method="POST" action="/admin/membership-levels/<?= (int) $l['id'] ?>" class="glass-card" style="padding:20px;">
    <?= csrf_field() ?>
    <div class="flex items-center gap-2 mb-3">
      <span class="membership-badge membership-<?= e($l['slug']) ?>"><?= e($l['name']) ?></span>
    </div>
    <div class="field"><label>Name</label><input class="input" type="text" name="name" value="<?= e($l['name']) ?>"></div>
    <div class="field"><label>Min. total deposited (USD)</label><input class="input" type="text" name="min_total_deposited" value="<?= e((string) $l['min_total_deposited']) ?>"></div>
    <div class="field"><label>Min. total purchased (USD)</label><input class="input" type="text" name="min_total_purchased" value="<?= e((string) $l['min_total_purchased']) ?>"></div>
    <div class="field"><label>Cashback multiplier</label><input class="input" type="text" name="cashback_multiplier" value="<?= e((string) $l['cashback_multiplier']) ?>"></div>
    <div class="field"><label>Referral bonus multiplier</label><input class="input" type="text" name="referral_bonus_multiplier" value="<?= e((string) $l['referral_bonus_multiplier']) ?>"></div>
    <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="is_active" <?= $l['is_active'] ? 'checked' : '' ?>> Active</label>
    <button class="btn btn-primary btn-sm" type="submit">Save</button>
  </form>
<?php endforeach; ?>
</div>
