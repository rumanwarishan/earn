<h2 class="mb-3">Reset your password</h2>
<p class="mb-3">Enter your account email and we'll send you a link to reset your password.</p>
<form method="POST" action="/forgot-password">
  <?= csrf_field() ?>
  <div class="field">
    <label>Email</label>
    <input class="input" type="email" name="email" required autofocus>
  </div>
  <button class="btn btn-primary" type="submit">Send reset link</button>
</form>
<p class="text-center mt-4"><a href="/login" style="color:var(--orange);font-weight:600;">Back to login</a></p>
