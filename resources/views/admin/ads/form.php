<?php
$adType = $ad['type'] ?? 'image';
$startsAtLocal = !empty($ad['starts_at']) ? date('Y-m-d\TH:i', strtotime((string) $ad['starts_at'])) : '';
$endsAtLocal = !empty($ad['ends_at']) ? date('Y-m-d\TH:i', strtotime((string) $ad['ends_at'])) : '';
$destinationValue = $ad['destination_url'] ?? old('destination_url');
if ($adType === 'video' && !empty($ad['destination_url'])) {
    $destinationValue = 'https://www.youtube.com/watch?v=' . $ad['destination_url'];
}
?>
<a href="/admin/ads" class="text-muted" style="font-size:13px;">&larr; Back to Watch &amp; Earn</a>
<h2 class="mt-3 mb-3"><?= $ad ? 'Edit advertisement' : 'New advertisement' ?></h2>

<form method="POST" action="<?= $ad ? '/admin/ads/' . (int) $ad['id'] : '/admin/ads' ?>" enctype="multipart/form-data" class="glass-card" style="padding:24px;max-width:640px;">
  <?= csrf_field() ?>
  <div class="field"><label>Title</label><input class="input" type="text" name="title" required maxlength="150" value="<?= e($ad['title'] ?? old('title')) ?>"></div>
  <div class="field"><label>Description (optional)</label><textarea class="input" name="description" rows="2" maxlength="500"><?= e($ad['description'] ?? old('description')) ?></textarea></div>

  <div class="field">
    <label>Ad type</label>
    <select class="input" name="type" id="ad_type" onchange="
      document.getElementById('destination_field').style.display = (this.value === 'external' || this.value === 'video') ? '' : 'none';
      document.getElementById('destination_label').textContent = this.value === 'video' ? 'YouTube video URL' : 'Destination URL';
      document.getElementById('destination_hint').style.display = this.value === 'video' ? '' : 'none';
    ">
      <option value="image" <?= $adType === 'image' ? 'selected' : '' ?>>Image / banner</option>
      <option value="external" <?= $adType === 'external' ? 'selected' : '' ?>>External advertisement URL</option>
      <option value="video" <?= $adType === 'video' ? 'selected' : '' ?>>YouTube video</option>
    </select>
    <div class="field-hint">Image ads show the banner while the user watches. External ads also open the destination URL in a new tab. YouTube ads embed the video right in the watch screen.</div>
  </div>

  <div class="field"><label>Banner image<?= $adType !== 'image' ? ' (optional)' : '' ?></label><input class="input" type="file" name="image" accept="image/*">
    <?php if (!empty($ad['image_path'])): ?><img src="<?= e($ad['image_path']) ?>" style="max-width:160px;border-radius:8px;margin-top:8px;display:block;" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'text-muted',style:'font-size:12px;margin-top:8px;',textContent:'Current image file is missing - upload a new one to replace it.'}))"><?php endif; ?>
  </div>

  <div id="destination_field" class="field" style="<?= ($adType === 'external' || $adType === 'video') ? '' : 'display:none;' ?>">
    <label id="destination_label"><?= $adType === 'video' ? 'YouTube video URL' : 'Destination URL' ?></label>
    <input class="input" type="url" name="destination_url" placeholder="https://www.youtube.com/watch?v=..." value="<?= e((string) $destinationValue) ?>">
    <div id="destination_hint" class="field-hint" style="<?= $adType === 'video' ? '' : 'display:none;' ?>">Paste any YouTube link - watch, youtu.be, or shorts. The video ID is extracted automatically.</div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div class="field"><label>Reward amount (USD)</label><input class="input" type="text" name="reward_amount" required value="<?= e((string) ($ad['reward_amount'] ?? (old('reward_amount') ?: '0.25'))) ?>"></div>
    <div class="field"><label>Required watch time (seconds)</label><input class="input" type="text" inputmode="numeric" pattern="\d*" name="watch_seconds" required value="<?= e((string) ($ad['watch_seconds'] ?? (old('watch_seconds') ?: '30'))) ?>"></div>
  </div>

  <div class="field">
    <label>Maximum total completions (optional)</label>
    <input class="input" type="text" inputmode="numeric" pattern="\d*" name="max_completions" value="<?= e((string) ($ad['max_completions'] ?? '')) ?>">
    <div class="field-hint">Leave blank for unlimited. Once this many users have earned the reward, the ad stops appearing.</div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div class="field"><label>Starts at (optional)</label><input class="input" type="datetime-local" name="starts_at" value="<?= e($startsAtLocal) ?>"></div>
    <div class="field"><label>Ends at (optional)</label><input class="input" type="datetime-local" name="ends_at" value="<?= e($endsAtLocal) ?>"></div>
  </div>

  <div class="field"><label>Sort order</label><input class="input" type="text" inputmode="numeric" pattern="\d*" name="sort_order" value="<?= e((string) ($ad['sort_order'] ?? '0')) ?>"></div>

  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="is_active" <?= ($ad === null || !empty($ad['is_active'])) ? 'checked' : '' ?>> Active</label>

  <button class="btn btn-primary" type="submit"><?= $ad ? 'Save changes' : 'Create advertisement' ?></button>
</form>
