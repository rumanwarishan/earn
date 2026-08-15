<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle ?? (string) setting('site_name', 'Billions Earn')) ?></title>
  <meta name="robots" content="<?= $robots ?? 'noindex' ?>">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
  <div class="ambient-bg"></div>
  <div id="toast-stack"></div>
  <?= view('partials.header', ['unreadCount' => $unreadCount ?? 0]) ?>
  <main class="container" style="padding-top:18px;">
    <?= view('partials.flash') ?>
    <?= $content ?>
  </main>
  <?= view('partials.bottom-nav') ?>
  <?= view('partials.chatbot') ?>
  <script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
  <script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
