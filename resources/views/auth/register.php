<h2 class="mb-3">Create your account</h2>
<p class="mb-3">Registration is invitation-only. Enter the invitation code you received to join.</p>
<form method="POST" action="/register">
  <?= csrf_field() ?>
  <div class="field">
    <label>Full name</label>
    <input class="input" type="text" name="full_name" value="<?= old('full_name') ?>" required maxlength="150">
  </div>
  <div class="field">
    <label>Email</label>
    <input class="input" type="email" name="email" value="<?= old('email') ?>" required maxlength="190">
  </div>
  <div class="field">
    <label>Password</label>
    <input class="input" type="password" name="password" required minlength="8">
    <div class="field-hint">At least 8 characters.</div>
  </div>
  <div class="field">
    <label>Confirm password</label>
    <input class="input" type="password" name="confirm_password" required minlength="8">
  </div>
  <div class="field">
    <label>Invitation code</label>
    <input class="input mono" type="text" name="invitation_code" value="<?= e($prefillCode ?? '') ?>" required maxlength="20" style="text-transform:uppercase;">
  </div>
  <button class="btn btn-primary mt-2" type="submit">Create account</button>
</form>
<p class="text-center mt-4">Already have an account? <a href="/login" style="color:var(--orange);font-weight:600;">Log in</a></p>
