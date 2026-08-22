<h2 class="mb-3">Invite &amp; earn</h2>

<div class="glass-card text-center mb-3 glow-border" style="padding:24px;">
  <div class="qr-frame glow-border">
    <img src="<?= $qrDataUri ?>" alt="Referral QR code">
  </div>
  <div class="text-muted" style="font-size:12.5px;">Your referral code</div>
  <div class="mono" style="font-size:22px;font-weight:750;letter-spacing:0.06em;margin:4px 0 16px;"><?= e($referralCode) ?></div>
  <div class="glass-panel mono" style="padding:12px;word-break:break-all;font-size:12.5px;margin-bottom:12px;"><?= e($referralLink) ?></div>
  <div class="flex gap-2">
    <button class="btn btn-secondary" data-copy="<?= e($referralLink) ?>" data-copy-label="Referral link">Copy link</button>
    <button class="btn btn-primary" onclick="if(navigator.share){navigator.share({title:'Join Billions Earn',url:'<?= e($referralLink) ?>'})}else{copyToClipboard('<?= e($referralLink) ?>','Referral link')}">Share</button>
  </div>
</div>

<div class="stat-grid mb-3">
  <div class="glass-panel stat-tile"><div class="label">Direct referrals</div><div class="value"><?= (int) $directCount ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total team</div><div class="value"><?= (int) $totalTeamCount ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Referral earnings</div><div class="value"><?= money($totalReferralEarnings) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Welcome bonus</div><div class="value"><?= money($welcomeBonus) ?></div></div>
</div>

<div class="glass-panel mb-3" style="padding:16px;font-size:13px;color:var(--text-mid);">
  <div style="font-weight:600;color:var(--text-hi);margin-bottom:8px;">How referral rewards work</div>
  <ul style="margin:0;padding-left:18px;line-height:1.9;">
    <li>New members get a <strong><?= money($welcomeBonus) ?></strong> welcome bonus when they join with your code.</li>
    <?php foreach ($levels as $l): ?>
      <li>You earn <strong><?= $l['bonus_type'] === 'percentage' ? rtrim(rtrim((string) $l['bonus_amount'], '0'), '.') . '%' : money($l['bonus_amount']) ?></strong>
        when your level <?= (int) $l['level'] ?> referral makes a qualifying deposit of at least <?= money($l['min_qualifying_deposit']) ?>.</li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="section-head"><h3>Tasks</h3><a href="/tasks" style="color:var(--orange);font-size:12.5px;">See all</a></div>
<div class="glass-card glow-border mb-3">
  <?php if (!$tasks): ?>
    <div class="empty-state"><div class="icon">✅</div>No tasks available right now.</div>
  <?php else: ?>
    <?php foreach ($tasks as $task): ?>
      <?php
        $alreadyClaimed = $task['type'] === 'daily' ? (int) $task['completed_today'] > 0 : (int) $task['completed_ever'] > 0;
        $hasProgress = $task['progress_count'] !== null;
      ?>
      <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div style="padding-right:12px;">
          <div style="font-size:13.5px;font-weight:600;"><?= e($task['title']) ?></div>
          <?php if ($task['description']): ?><div class="text-muted" style="font-size:11.5px;margin-top:2px;"><?= e($task['description']) ?></div><?php endif; ?>
          <div class="mt-2" style="font-size:12.5px;color:var(--emerald);font-weight:600;">+<?= money($task['reward_amount']) ?></div>
          <?php if ($hasProgress && !$alreadyClaimed): ?>
            <div class="text-muted mt-1" style="font-size:11px;"><?= min((int) $task['progress_count'], (int) $task['criteria_target']) ?> / <?= (int) $task['criteria_target'] ?> completed</div>
          <?php endif; ?>
        </div>
        <?php if ($alreadyClaimed): ?>
          <span class="badge badge-emerald">Done<?= $task['type'] === 'daily' ? ' today' : '' ?></span>
        <?php elseif ($task['criteria_key'] === 'social_share' && $task['is_claimable']): ?>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
            <div class="flex gap-2">
              <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= e($referralLink) ?>" data-copy-label="Invite link" data-reveal-claim="shareClaimRef<?= (int) $task['id'] ?>">Copy link</button>
              <button type="button" class="btn btn-primary btn-sm" data-share-link="<?= e($referralLink) ?>" data-reveal-claim="shareClaimRef<?= (int) $task['id'] ?>">Share</button>
            </div>
            <form id="shareClaimRef<?= (int) $task['id'] ?>" method="POST" action="/tasks/<?= (int) $task['id'] ?>/claim" style="display:none;">
              <?= csrf_field() ?>
              <button class="btn btn-primary btn-sm" type="submit">Claim reward</button>
            </form>
          </div>
        <?php elseif ($task['is_claimable']): ?>
          <form method="POST" action="/tasks/<?= (int) $task['id'] ?>/claim">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-sm" type="submit">Claim</button>
          </form>
        <?php else: ?>
          <span class="badge badge-muted">In progress</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="section-head"><h3>Your team</h3></div>
<div class="glass-card">
  <?php if (!$team): ?>
    <div class="empty-state"><div class="icon">👥</div>No referrals yet. Share your link to start earning!</div>
  <?php else: ?>
    <?php foreach ($team as $member): ?>
      <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div>
          <div style="font-size:13.5px;font-weight:600;"><?= e($member['full_name']) ?></div>
          <div class="text-muted" style="font-size:11.5px;">Joined <?= e(date('M j, Y', strtotime($member['created_at']))) ?></div>
        </div>
        <div style="text-align:right;">
          <?php if ($member['level_name']): ?><span class="membership-badge membership-<?= e($member['level_slug']) ?>"><?= e($member['level_name']) ?></span><?php endif; ?>
          <div class="text-muted mt-2" style="font-size:11px;"><?= (int) $member['deposit_count'] ?> deposit(s)</div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
