<?php $flashSuccess = success_message(); $flashErrors = errors(); ?>
<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($flashErrors)): ?>
  <div class="alert alert-error">
    <?php foreach ($flashErrors as $err): ?>
      <div><?= e($err) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
