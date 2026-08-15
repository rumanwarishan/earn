<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle ?? (string) setting('site_name', 'Billions Earn')) ?></title>
  <meta name="robots" content="noindex">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body" style="padding-bottom:40px;">
  <div id="toast-stack"></div>
  <div class="auth-glow auth-glow-a"></div>
  <div class="auth-glow auth-glow-b"></div>
  <div class="container" style="padding-top:56px;max-width:420px;position:relative;">
    <div class="text-center mb-3">
      <a href="/" class="flex items-center gap-2" style="justify-content:center;font-weight:700;font-size:19px;">
        <img class="brand-mark lg" src="/assets/img/logo-mark.svg" alt=""><?= e((string) setting('site_name', 'Billions Earn')) ?>
      </a>
    </div>
    <div class="glass-card auth-card" style="padding:28px 24px;">
      <?= view('partials.flash') ?>
      <?= $content ?>
    </div>
  </div>
  <script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
