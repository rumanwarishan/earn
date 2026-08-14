<h2 class="mb-3">Set a new password</h2>
<form method="POST" action="/reset-password">
  <?= csrf_field() ?>
  <input type="hidden" name="uid" value="<?= (int) $uid ?>">
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="field">
    <label>New password</label>
    <input class="input" type="password" name="password" required minlength="8" autofocus>
  </div>
  <button class="btn btn-primary" type="submit">Reset password</button>
</form>
