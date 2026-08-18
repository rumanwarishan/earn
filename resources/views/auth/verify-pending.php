<div class="text-center">
  <div style="font-size:38px;margin-bottom:12px;">📧</div>
  <h2 class="mb-3">Check your email</h2>
  <p>We've sent a verification link to your email address. Click it to activate your account, then log in.</p>
  <form method="POST" action="/verify-email/resend" class="mt-4">
    <?= csrf_field() ?>
    <input type="hidden" name="uid" value="<?= (int) ($uid ?? 0) ?>">
    <button class="btn btn-secondary" type="submit">Resend verification email</button>
  </form>
  <p class="mt-4"><a href="/login" style="color:var(--orange);font-weight:600;">Back to login</a></p>
</div>
