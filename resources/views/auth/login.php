<h2 class="mb-3">Welcome back</h2>
<form method="POST" action="/login">
  <?= csrf_field() ?>
  <div class="field">
    <label>Email</label>
    <input class="input" type="email" name="email" value="<?= old('email') ?>" required autofocus>
  </div>
  <div class="field">
    <label>Password</label>
    <input class="input" type="password" name="password" required>
  </div>
  <div class="text-center mt-2 mb-3"><a href="/forgot-password" style="color:var(--text-mid);font-size:13px;">Forgot password?</a></div>
  <button class="btn btn-primary" type="submit">Log in</button>
</form>
<p class="text-center mt-4">New here? <a href="/register" style="color:var(--cyan);font-weight:600;">Create an account</a></p>
