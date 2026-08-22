<div class="admin-toolbar">
  <h2>Billions Flight</h2>
  <a class="btn btn-secondary btn-sm" href="/admin/game/rounds">Round history</a>
  <a class="btn btn-secondary btn-sm" href="/admin/game/settings">Settings</a>
</div>
<div class="glass-panel mb-3" style="padding:12px 16px;font-size:12.5px;color:var(--text-mid);">
  Figures below are <strong>B$</strong> (Billions Store Currency) unless noted. B$ becomes real money only when a user exchanges it to their wallet - see the exchange total below.
</div>

<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Total players</div><div class="value"><?= (int) $stats['total_players'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Active players (24h)</div><div class="value"><?= (int) $stats['active_players'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Rounds played</div><div class="value"><?= (int) $stats['rounds_played'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Today's games</div><div class="value"><?= (int) $stats['todays_games'] ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total B$ played</div><div class="value"><?= gp($stats['total_played']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total B$ won</div><div class="value" style="color:var(--emerald);"><?= gp($stats['total_won']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total B$ lost</div><div class="value" style="color:var(--danger);"><?= gp($stats['total_lost']) ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Total exchanged to wallet</div><div class="value"><?= gp($stats['total_exchanged_bs']) ?></div><div class="text-muted mt-1" style="font-size:11px;"><?= money($stats['total_exchanged_usd']) ?> credited</div></div>
</div>

<div class="section-head"><h3>Active round</h3></div>
<div class="glass-card mb-3" style="padding:16px;">
  <?php if (!$activeRound): ?>
    <span class="text-muted">No active round right now (one will start on the next player request).</span>
  <?php else: ?>
    <div class="flex items-center gap-3" style="flex-wrap:wrap;">
      <span class="badge <?= $activeRound['status'] === 'running' ? 'badge-emerald' : 'badge-amber' ?>"><?= e(ucfirst($activeRound['status'])) ?></span>
      <span class="mono text-muted" style="font-size:12px;"><?= e($activeRound['round_uuid']) ?></span>
      <span class="text-muted" style="font-size:12.5px;">Started: <?= $activeRound['started_at'] ? e($activeRound['started_at']) : 'not yet' ?></span>
    </div>
  <?php endif; ?>
</div>

<div class="section-head"><h3>Manual B$ adjustment</h3></div>
<form method="POST" action="/admin/game-points/adjust" class="glass-card mb-3" style="padding:20px;max-width:520px;">
  <?= csrf_field() ?>
  <div class="field"><label>User email</label><input class="input" type="email" name="email" required></div>
  <div class="field"><label>Amount (B$)</label><input class="input" type="text" name="amount" placeholder="100 to grant, -100 to revoke" required></div>
  <div class="field"><label>Reason</label><input class="input" type="text" name="reason" required maxlength="255"></div>
  <button class="btn btn-primary" type="submit">Apply adjustment</button>
  <div class="text-muted mt-2" style="font-size:11.5px;">Every adjustment is permanently logged with the admin, amount, reason, and before/after balance - see Audit Logs.</div>
</form>
