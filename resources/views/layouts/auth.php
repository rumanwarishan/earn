<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle ?? (string) setting('site_name', 'Billions Earn')) ?></title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body style="padding-bottom:40px;">
  <div id="toast-stack"></div>
  <div class="container" style="padding-top:56px;max-width:420px;">
    <div class="text-center mb-3">
      <a href="/" class="flex items-center gap-2" style="justify-content:center;font-weight:700;font-size:19px;">
        <span class="brand-mark"></span><?= e((string) setting('site_name', 'Billions Earn')) ?>
      </a>
    </div>
    <div class="glass-card" style="padding:28px 24px;">
      <?= view('partials.flash') ?>
      <?= $content ?>
    </div>
  </div>
  <script src="/assets/js/app.js" defer></script>
</body>
</html>
