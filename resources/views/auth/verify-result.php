<div class="text-center">
  <?php if ($ok): ?>
    <div style="font-size:38px;margin-bottom:12px;">✅</div>
    <h2 class="mb-3">Email verified!</h2>
    <p>Your account is now active. You can log in and start exploring Billions Earn.</p>
    <a class="btn btn-primary mt-4" href="/login">Log in</a>
  <?php else: ?>
    <div style="font-size:38px;margin-bottom:12px;">⚠️</div>
    <h2 class="mb-3">Link invalid or expired</h2>
    <p>This verification link is no longer valid. Please request a new one.</p>
    <a class="btn btn-primary mt-4" href="/login">Back to login</a>
  <?php endif; ?>
</div>
