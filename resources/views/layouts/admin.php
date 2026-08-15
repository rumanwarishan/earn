<?php $admin = \App\Core\Session::get('admin'); $path = $_SERVER['REQUEST_URI'] ?? ''; function adminActive(string $p, string $cur): string { return str_starts_with($cur, $p) ? 'active' : ''; } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Admin') ?> · Billions Earn Admin</title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body style="padding-bottom:0;">
  <div id="toast-stack"></div>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a href="/admin/dashboard" class="brand" style="padding:18px 20px;"><span class="brand-mark"></span>Earn Admin</a>
      <nav class="admin-nav">
        <a class="<?= adminActive('/admin/dashboard', $path) ?>" href="/admin/dashboard">📊 Dashboard</a>
        <a class="<?= adminActive('/admin/users', $path) ?>" href="/admin/users">👥 Users</a>
        <a class="<?= adminActive('/admin/deposits', $path) ?>" href="/admin/deposits">💰 Deposits</a>
        <a class="<?= adminActive('/admin/withdrawals', $path) ?>" href="/admin/withdrawals">🏦 Withdrawals</a>
        <a class="<?= adminActive('/admin/wallets', $path) ?>" href="/admin/wallets">📒 Wallet Ledger</a>
        <a class="<?= adminActive('/admin/products', $path) ?>" href="/admin/products">🛍️ Products</a>
        <a class="<?= adminActive('/admin/orders', $path) ?>" href="/admin/orders">📦 Orders</a>
        <a class="<?= adminActive('/admin/membership-levels', $path) ?>" href="/admin/membership-levels">🏅 Membership</a>
        <a class="<?= adminActive('/admin/marketplaces', $path) ?>" href="/admin/marketplaces">🏬 Marketplaces</a>
        <a class="<?= adminActive('/admin/settings', $path) ?>" href="/admin/settings">⚙️ Settings</a>
        <a class="<?= adminActive('/admin/audit-logs', $path) ?>" href="/admin/audit-logs">🧾 Audit Logs</a>
      </nav>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <div style="font-weight:600;"><?= e($pageTitle ?? 'Dashboard') ?></div>
        <div class="flex items-center gap-3">
          <span class="text-muted" style="font-size:13px;"><?= e($admin['name'] ?? '') ?> · <?= e($admin['role_name'] ?? '') ?></span>
          <form method="POST" action="/admin/logout"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Log out</button></form>
        </div>
      </header>
      <main class="admin-content">
        <?= view('partials.flash') ?>
        <?= $content ?>
      </main>
    </div>
  </div>
  <script src="/assets/js/app.js" defer></script>
</body>
</html>
