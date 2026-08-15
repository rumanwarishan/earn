<h2 class="mb-3">Deposit BTC</h2>

<?php if (!$address): ?>
  <div class="alert alert-error">Deposits are temporarily unavailable. Please check back soon.</div>
<?php else: ?>

<div class="glass-card text-center mb-3 glow-ring" style="padding:24px;">
  <div class="qr-frame glow-ring">
    <img src="<?= $qrDataUri ?>" alt="BTC deposit QR code">
  </div>
  <div class="glass-panel mono" style="padding:12px;word-break:break-all;font-size:13px;margin-top:16px;"><?= e($address) ?></div>
  <button class="btn btn-secondary mt-3" data-copy="<?= e($address) ?>" data-copy-label="BTC address">Copy address</button>
</div>

<div class="glass-panel mb-3" style="padding:16px;font-size:13.5px;color:var(--text-mid);">
  <div style="font-weight:600;color:var(--text-hi);margin-bottom:8px;">How it works</div>
  <ol style="margin:0;padding-left:18px;line-height:1.9;">
    <li>Send BTC to the address above.</li>
    <li>Double-check the address before sending - it cannot be reversed.</li>
    <li>Submit your transaction hash (TXID) below.</li>
    <li>An admin manually verifies your transaction.</li>
    <li>Once approved, the USD equivalent is credited to your wallet.</li>
  </ol>
  <?php if ($minDeposit): ?><div class="mt-2">Minimum deposit: <strong><?= money($minDeposit) ?></strong> equivalent.</div><?php endif; ?>
  <?php if ($networkNote): ?><div class="mt-2"><?= e($networkNote) ?></div><?php endif; ?>
  <div class="mt-2">Your deposit is not confirmed until an admin approves it - never treat a broadcast transaction as credited funds.</div>
</div>

<form method="POST" action="/wallet/deposit" class="glass-card mb-3 glow-ring" style="padding:20px;">
  <?= csrf_field() ?>
  <div class="field">
    <label>BTC amount sent</label>
    <input class="input mono" type="text" name="btc_amount_claimed" placeholder="0.00000000" required pattern="\d+(\.\d{1,8})?">
  </div>
  <div class="field">
    <label>Transaction hash (TXID)</label>
    <input class="input mono" type="text" name="txid" placeholder="e.g. 4a5e1e4baab89f3a32518a88..." required>
  </div>
  <button class="btn btn-primary" type="submit">I have sent BTC</button>
</form>

<?php endif; ?>

<div class="section-head"><h3>Recent deposits</h3></div>
<div class="glass-card">
  <?php if (!$recentDeposits): ?>
    <div class="empty-state"><div class="icon">💰</div>No deposits yet.</div>
  <?php else: ?>
    <?php foreach ($recentDeposits as $d): ?>
      <div class="flex items-center justify-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
        <div>
          <div style="font-size:13.5px;font-weight:600;"><?= btc_amount($d['btc_amount_claimed']) ?></div>
          <div class="text-muted mono" style="font-size:11px;"><?= e(substr($d['txid'], 0, 18)) ?>&hellip;</div>
        </div>
        <div style="text-align:right;">
          <?php if ($d['usd_amount_credited']): ?><div style="font-weight:700;"><?= money($d['usd_amount_credited']) ?></div><?php endif; ?>
          <span class="badge <?= status_badge_class($d['status']) ?>"><?= e($d['status']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
