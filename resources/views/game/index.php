<div class="flight-top flex items-center justify-between mb-3">
  <div>
    <div class="text-muted" style="font-size:12px;">Virtual Game Points</div>
    <div class="flight-gp-balance" id="gpBalance"><?= gp($balance) ?></div>
    <span class="badge badge-emerald mt-1" style="display:inline-block;">FUN GAME &middot; NOT REAL MONEY</span>
  </div>
  <div style="text-align:right;">
    <button class="btn btn-secondary btn-sm" type="button" id="dailyBonusBtn"<?= $settings['daily_bonus_enabled'] ? '' : ' style="display:none;"' ?>>Daily Bonus</button>
    <div class="mt-2"><a href="/wallet" class="text-muted" style="font-size:11px;">Financial Wallet &rarr;</a></div>
  </div>
</div>

<div class="glass-card glow-border flight-stage" id="flightStage" style="padding:0;">
  <div class="flight-multiplier" id="flightMultiplier">1.00x</div>
  <canvas id="flightCanvas"></canvas>
  <div class="flight-overlay flight-countdown" id="flightCountdown" style="display:none;">
    <div class="flight-countdown-number" id="flightCountdownNumber">3</div>
    <div class="text-muted" style="font-size:12px;">Get ready...</div>
  </div>
  <div class="flight-overlay flight-crashed-overlay" id="flightCrashedOverlay" style="display:none;">
    <div class="flight-crashed-label">CRASHED</div>
    <div class="flight-crashed-mult" id="flightCrashedMult">1.00x</div>
  </div>
</div>

<div class="glass-card flight-controls mt-3" style="padding:18px;">
  <div id="entryRow">
    <div class="field" style="margin-bottom:10px;">
      <label>Entry (GP)</label>
      <input class="input" type="number" id="stakeInput" step="0.01"
             min="<?= e((string) $settings['minimum_entry']) ?>" max="<?= e((string) $settings['maximum_entry']) ?>"
             value="<?= e((string) $settings['minimum_entry']) ?>">
      <div class="field-hint">Min <?= gp($settings['minimum_entry']) ?> &middot; Max <?= gp($settings['maximum_entry']) ?></div>
    </div>
    <div class="text-muted mb-2" style="font-size:12.5px;" id="potentialResult">Potential result: <?= gp($settings['minimum_entry']) ?> &times; current multiplier</div>
    <button class="btn btn-primary w-full flight-join-btn" type="button" id="joinBtn">JOIN ROUND</button>
  </div>

  <button class="btn btn-primary w-full flight-claim-btn" type="button" id="claimBtn" style="display:none;">
    CLAIM <span id="claimAmount">0.00</span> GP
  </button>

  <div id="waitingMsg" class="text-muted text-center" style="display:none;font-size:13px;padding:8px 0;">You're in! Waiting for takeoff&hellip;</div>
  <div id="spectateMsg" class="text-muted text-center" style="display:none;font-size:13px;padding:8px 0;">Round in progress &mdash; join the next flight below.</div>
  <div id="roundResult" class="text-center" style="display:none;padding:8px 0;font-weight:700;"></div>
  <div id="connectionMsg" class="alert alert-error mt-2" style="display:none;">Connection interrupted. Reconnecting&hellip;</div>
</div>

<div class="section-head"><h3>Recent flights</h3></div>
<div class="flight-history-pills mb-3" id="recentRoundsPills">
  <?php foreach ($recentRounds as $r): $m = (float) $r['crash_multiplier']; ?>
    <span class="mult-pill <?= $m >= 2 ? 'mult-pill-good' : ($m < 1.2 ? 'mult-pill-bad' : '') ?>"><?= number_format($m, 2) ?>x</span>
  <?php endforeach; ?>
  <?php if (!$recentRounds): ?><span class="text-muted" style="font-size:12.5px;">No flights yet.</span><?php endif; ?>
</div>

<div class="section-head"><h3>My game history</h3></div>
<div class="glass-card mb-3">
  <?php if (!$history): ?>
    <div class="empty-state"><div class="icon">&#9992;</div>No games played yet. Join a round to get started.</div>
  <?php else: ?>
    <?php foreach ($history as $h): ?>
      <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div>
          <div style="font-size:13.5px;font-weight:600;">Entry: <?= gp($h['stake']) ?></div>
          <div class="text-muted" style="font-size:11.5px;">
            <?= $h['status'] === 'cashed_out' ? 'Claimed at ' . number_format((float) $h['cashout_multiplier'], 2) . 'x' : 'Crashed at ' . number_format((float) $h['crash_multiplier'], 2) . 'x' ?>
          </div>
        </div>
        <div style="font-weight:700;color:<?= $h['status'] === 'cashed_out' ? 'var(--emerald)' : 'var(--danger)' ?>;">
          <?= $h['status'] === 'cashed_out' ? '+' . gp($h['payout']) : 'Lost' ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="glass-panel mb-3" style="padding:14px;font-size:12px;color:var(--text-mid);">
  Game Points (GP) are virtual and used only inside Billions Flight for entertainment. They have no cash value and cannot be deposited, withdrawn, transferred, or converted to USD/BTC/USDT. Your financial wallet is never affected by this game.
</div>

<script>window.FLIGHT_SETTINGS = <?= json_encode(['minimum_entry' => $settings['minimum_entry'], 'maximum_entry' => $settings['maximum_entry']]) ?>;</script>
<script src="<?= asset('js/game.js') ?>" defer></script>
