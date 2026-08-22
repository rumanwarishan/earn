<div class="flight-top flex items-center justify-between mb-3">
  <div>
    <div class="text-muted" style="font-size:12px;">B$ Balance</div>
    <div class="flight-gp-balance" id="gpBalance"><?= gp($balance) ?></div>
    <span class="badge badge-emerald mt-1" style="display:inline-block;">REDEEMABLE FOR USD</span>
  </div>
  <div style="text-align:right;">
    <button class="btn btn-secondary btn-sm" type="button" id="dailyBonusBtn"<?= $settings['daily_bonus_enabled'] ? '' : ' style="display:none;"' ?>>Daily Bonus</button>
    <div class="mt-2">
      <a href="/wallet" class="text-muted flight-wallet-link" style="font-size:11px;">
        Financial Wallet
        <svg class="flight-wallet-plane" viewBox="0 0 24 24" width="14" height="14" fill="none" aria-hidden="true">
          <path d="M2 12.5l8-1 4.5-7 2 .5-2.5 6.8 6-.3 2.5 1.5-2.5 1.5-6-.3 2.5 6.8-2 .5-4.5-7-8-1z" fill="currentColor"/>
        </svg>
      </a>
    </div>
  </div>
</div>

<div class="section-head" style="margin-top:0;"><h3>Convert</h3></div>
<div class="glass-card swap-card mb-3" id="swapCard">
  <div class="swap-rate-badge" id="swapRateBadge">1 USD = B$1.00</div>
  <form method="POST" action="/game/topup" id="swapForm">
    <?= csrf_field() ?>
    <div class="swap-row">
      <div class="swap-label">You send</div>
      <div class="swap-input-row">
        <input class="swap-amount" type="text" id="swapFromAmount" name="amount" inputmode="decimal" placeholder="0.00" autocomplete="off">
        <span class="swap-currency" id="swapFromCurrency">USD</span>
      </div>
      <div class="swap-hint" id="swapFromHint"></div>
    </div>

    <button type="button" class="swap-flip-btn" id="swapFlipBtn" aria-label="Swap direction">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
        <path d="M7 16V4M7 4L3.5 7.5M7 4l3.5 3.5M17 8v12M17 20l3.5-3.5M17 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <div class="swap-row">
      <div class="swap-label">You receive</div>
      <div class="swap-input-row">
        <input class="swap-amount" type="text" id="swapToAmount" readonly tabindex="-1" placeholder="0.00">
        <span class="swap-currency" id="swapToCurrency">B$</span>
      </div>
      <div class="swap-hint" id="swapToHint"></div>
    </div>

    <button class="btn btn-primary w-full swap-submit-btn" type="submit" id="swapSubmitBtn">Convert to B$</button>
    <div class="text-muted mt-2" style="font-size:11px;" id="swapLimitHint"></div>
  </form>
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
      <label>Entry (B$)</label>
      <input class="input" type="number" id="stakeInput" step="0.01"
             min="<?= e((string) $settings['minimum_entry']) ?>" max="<?= e((string) $settings['maximum_entry']) ?>"
             value="<?= e((string) $settings['minimum_entry']) ?>">
      <div class="field-hint">Min <?= gp($settings['minimum_entry']) ?> &middot; Max <?= gp($settings['maximum_entry']) ?></div>
    </div>
    <div class="text-muted mb-2" style="font-size:12.5px;" id="potentialResult">Potential result: <?= gp($settings['minimum_entry']) ?> &times; current multiplier</div>
    <button class="btn btn-primary w-full flight-join-btn" type="button" id="joinBtn">JOIN ROUND</button>
  </div>

  <button class="btn btn-primary w-full flight-claim-btn" type="button" id="claimBtn" style="display:none;">
    CLAIM <span id="claimAmount">B$0.00</span>
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
  B$ is Billions Flight's in-game currency. Play with B$ as much as you like - your financial wallet is never touched by joining, winning, or losing a round. Use the Convert card above to move funds either way between your wallet and B$, at the current admin-set rate, subject to the minimum amount and daily limit shown.
</div>

<script>
window.FLIGHT_SETTINGS = <?= json_encode([
  'minimum_entry' => $settings['minimum_entry'],
  'maximum_entry' => $settings['maximum_entry'],
]) ?>;
window.SWAP_SETTINGS = <?= json_encode([
  'topup_enabled' => (bool) $settings['topup_enabled'],
  'topup_rate' => $settings['topup_rate'],
  'min_topup_amount' => $settings['min_topup_amount'],
  'max_topup_per_day' => $settings['max_topup_per_day'],
  'exchange_enabled' => (bool) $settings['exchange_enabled'],
  'exchange_rate' => $settings['exchange_rate'],
  'min_exchange_amount' => $settings['min_exchange_amount'],
  'max_exchange_per_day' => $settings['max_exchange_per_day'],
  'wallet_balance' => $walletBalance,
  'bs_balance' => $balance,
]) ?>;
</script>
<script src="<?= asset('js/game.js') ?>" defer></script>
