<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle ?? (string) setting('site_name', 'Billions Earn')) ?></title>
  <meta name="robots" content="noindex">
  <link rel="icon" type="image/png" href="<?= public_asset('favicon.png') ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body" style="padding-bottom:40px;">
  <div class="ambient-bg"></div>
  <div id="toast-stack"></div>
  <div class="auth-glow auth-glow-a"></div>
  <div class="auth-glow auth-glow-b"></div>
  <div class="container" style="padding-top:56px;max-width:420px;position:relative;">
    <div class="text-center mb-3">
      <a href="/" class="flex items-center gap-2" style="justify-content:center;font-weight:700;font-size:19px;">
        <img class="brand-mark lg" src="<?= asset('img/logo-mark.png') ?>" alt=""><?= e((string) setting('site_name', 'Billions Earn')) ?>
      </a>
    </div>
    <div class="glass-card auth-card glow-border" style="padding:28px 24px;">
      <?= view('partials.flash') ?>
      <?= $content ?>
    </div>
  </div>
  <script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
