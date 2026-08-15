<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login · Billions Earn</title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding-bottom:0;">
  <div id="toast-stack"></div>
  <div class="container" style="max-width:380px;">
    <div class="text-center mb-3" style="font-weight:700;font-size:19px;"><span class="brand-mark"></span> Earn Admin</div>
    <div class="glass-card" style="padding:28px 24px;">
      <?= view('partials.flash') ?>
      <h2 class="mb-3">Admin sign in</h2>
      <form method="POST" action="/admin/login">
        <?= csrf_field() ?>
        <div class="field"><label>Email</label><input class="input" type="email" name="email" required autofocus></div>
        <div class="field"><label>Password</label><input class="input" type="password" name="password" required></div>
        <button class="btn btn-primary" type="submit">Log in</button>
      </form>
    </div>
  </div>
</body>
</html>
