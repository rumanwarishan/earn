<h2 class="mb-2">Watch &amp; Earn</h2>
<p class="text-muted mb-3" style="font-size:13.5px;">Watch available advertisements and earn rewards directly into your Billions Earn wallet.</p>

<div class="glass-card mb-3 glow-border-soft" style="padding:20px;">
  <div class="text-muted" style="font-size:12.5px;">Wallet balance</div>
  <div style="font-size:26px;font-weight:750;margin:4px 0;"><?= money($spendable) ?></div>
</div>

<div class="section-head"><h3>Available advertisements</h3></div>
<?php if (!$ads): ?>
  <div class="empty-state"><div class="icon">📺</div>No advertisements available right now. Check back soon.</div>
<?php else: ?>
<div class="product-grid mb-3">
  <?php foreach ($ads as $ad): ?>
    <?php
      // Re-extract the video ID at render time (not just trusting the stored
      // column) so a thumbnail self-heals even for rows saved before this
      // normalization existed, or in a URL shape the original validation
      // didn't anticipate - see youtube_video_id() in helpers.php.
      $videoId = $ad['type'] === 'video' && !empty($ad['destination_url']) ? youtube_video_id((string) $ad['destination_url']) : null;
      $hasThumb = !empty($ad['image_path']) || $videoId !== null;
    ?>
    <div class="glass-card product-card ad-card"
         data-ad-id="<?= (int) $ad['id'] ?>"
         data-title="<?= e($ad['title']) ?>"
         data-reward="<?= e(money($ad['reward_amount'])) ?>"
         data-watch-seconds="<?= (int) $ad['watch_seconds'] ?>"
         data-image="<?= e((string) ($ad['image_path'] ?? '')) ?>"
         data-destination="<?= e($ad['type'] === 'video' ? (string) ($videoId ?? '') : (string) ($ad['destination_url'] ?? '')) ?>"
         data-type="<?= e($ad['type']) ?>">
      <div class="img-wrap">
        <?php if (!empty($ad['image_path'])): ?>
          <img src="<?= e($ad['image_path']) ?>" alt="<?= e($ad['title']) ?>" loading="lazy" onerror="this.style.display='none';this.parentElement.querySelector('.img-fallback').style.display='flex';">
        <?php elseif ($videoId !== null): ?>
          <img src="<?= e(youtube_thumbnail_url($videoId)) ?>" alt="<?= e($ad['title']) ?>" loading="lazy" onerror="this.style.display='none';this.parentElement.querySelector('.img-fallback').style.display='flex';">
          <span class="ad-play-badge">▶</span>
        <?php endif; ?>
        <div class="img-fallback flex items-center justify-center" style="height:100%;font-size:28px;<?= $hasThumb ? 'display:none;' : '' ?>">📺</div>
      </div>
      <div class="body">
        <div class="title"><?= e($ad['title']) ?></div>
        <?php if ($ad['description']): ?><p class="text-muted" style="font-size:12px;margin:-4px 0 8px;"><?= e($ad['description']) ?></p><?php endif; ?>
        <div class="flex items-center justify-between mb-3">
          <span class="badge badge-emerald">+<?= money($ad['reward_amount']) ?></span>
          <span class="text-muted" style="font-size:11.5px;"><?= (int) $ad['watch_seconds'] ?>s watch</span>
        </div>
        <?php if ($ad['user_completed']): ?>
          <div class="btn btn-secondary btn-sm w-full" style="text-align:center;opacity:0.7;">✓ Reward earned</div>
        <?php else: ?>
          <button class="btn btn-primary btn-sm w-full watch-ad-btn" type="button">Watch Ad</button>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="section-head"><h3>Your earnings</h3></div>
<div class="glass-card mb-3">
  <?php if (!$history): ?>
    <div class="empty-state"><div class="icon">🕓</div>No Watch &amp; Earn rewards yet.</div>
  <?php else: ?>
    <?php foreach ($history as $h): ?>
      <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div>
          <div style="font-size:13.5px;font-weight:600;"><?= e($h['title']) ?></div>
          <div class="text-muted" style="font-size:11.5px;"><?= e(time_ago($h['completed_at'])) ?></div>
        </div>
        <div style="font-weight:700;color:var(--emerald);">+<?= money($h['reward_amount']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="modal-backdrop" id="watchAdModal" style="display:none;">
  <div class="modal-sheet">
    <div id="watchAdBody"></div>
  </div>
</div>

<script src="<?= asset('js/watch-earn.js') ?>" defer></script>
