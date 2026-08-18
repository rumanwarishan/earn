<h2 class="mb-3">Tasks</h2>

<?php
$welcomeTasks = array_filter($tasks, fn ($t) => $t['type'] === 'welcome');
$dailyTasks = array_filter($tasks, fn ($t) => $t['type'] === 'daily');
$oneTimeTasks = array_filter($tasks, fn ($t) => $t['type'] === 'one_time');
?>

<?php if ($tasks): ?>
<div class="section" style="padding-top:0;">
  <?php foreach (['Welcome' => $welcomeTasks, 'Daily' => $dailyTasks, 'Bonus' => $oneTimeTasks] as $label => $group): ?>
    <?php if (!$group): continue; endif; ?>
    <div class="section-head"><h3><?= e($label) ?> tasks</h3></div>
    <div class="glass-card glow-border mb-3">
      <?php foreach ($group as $task): ?>
        <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
          <div style="padding-right:12px;">
            <div style="font-size:13.5px;font-weight:600;"><?= e($task['title']) ?></div>
            <?php if ($task['description']): ?><div class="text-muted" style="font-size:11.5px;margin-top:2px;"><?= e($task['description']) ?></div><?php endif; ?>
            <div class="mt-2" style="font-size:12.5px;color:var(--emerald);font-weight:600;">+<?= money($task['reward_amount']) ?></div>
          </div>
          <?php if ($task['is_claimable']): ?>
            <form method="POST" action="/tasks/<?= (int) $task['id'] ?>/claim">
              <?= csrf_field() ?>
              <button class="btn btn-primary btn-sm" type="submit">Claim</button>
            </form>
          <?php else: ?>
            <span class="badge badge-emerald">Done<?= $task['type'] === 'daily' ? ' today' : '' ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="section-head"><h3>Purchase history</h3></div>
<div class="glass-panel mb-3" style="padding:14px;font-size:12.5px;color:var(--text-mid);">
  Purchases made with your wallet balance are confirmed instantly. Affiliate marketplace orders are confirmed and tracked manually by our team once verified. Cashback is credited once an order is confirmed.
</div>

<?php if (!$orders): ?>
  <div class="empty-state"><div class="icon">📦</div>No purchases yet. <a href="/shop" style="color:var(--orange);">Start shopping</a> to earn cashback.</div>
<?php else: ?>
<div class="glass-card">
  <?php foreach ($orders as $o): ?>
    <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
      <div>
        <div style="font-size:13.5px;font-weight:600;"><?= e($o['product_name']) ?></div>
        <div class="text-muted" style="font-size:11.5px;">
          <?= e($o['marketplace_name']) ?> · <?= $o['payment_source'] === 'wallet' ? 'Wallet purchase' : 'Affiliate' ?> · <?= e(date('M j, Y', strtotime($o['created_at']))) ?>
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-weight:700;"><?= money($o['product_price']) ?></div>
        <span class="badge <?= status_badge_class($o['status']) ?>"><?= e($o['status']) ?></span>
        <?php if ($o['cashback_amount'] > 0): ?>
          <div class="text-muted mt-2" style="font-size:11px;">Cashback: <?= money($o['cashback_amount']) ?> (<?= e($o['cashback_status']) ?>)</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
