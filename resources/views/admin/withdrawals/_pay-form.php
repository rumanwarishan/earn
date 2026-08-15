<form method="POST" action="/admin/withdrawals/<?= (int) $withdrawal['id'] ?>/paid" class="glass-card" style="padding:20px;">
  <?= csrf_field() ?>
  <h3 class="mb-3" style="font-size:14px;color:var(--emerald);">Record BTC payment</h3>
  <div class="field"><label>BTC amount paid</label><input class="input mono" type="text" name="btc_amount_paid" required pattern="\d+(\.\d{1,8})?" placeholder="0.00000000"></div>
  <div class="field"><label>BTC/USD rate used</label><input class="input" type="text" name="btc_rate_used" pattern="\d+(\.\d{1,2})?" placeholder="e.g. 60000.00"></div>
  <div class="field"><label>Payout transaction hash</label><input class="input mono" type="text" name="payout_txid" required></div>
  <div class="field"><label>Notes (optional)</label><input class="input" type="text" name="notes" maxlength="500"></div>
  <button class="btn btn-primary" type="submit" onclick="return confirm('Confirm this BTC payment has been sent?')">Mark paid</button>
</form>
