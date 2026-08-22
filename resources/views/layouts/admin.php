<?php $admin = \App\Core\Session::get('admin'); $path = $_SERVER['REQUEST_URI'] ?? ''; function adminActive(string $p, string $cur): string { return str_starts_with($cur, $p) ? 'active' : ''; } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Admin') ?> · Billions Earn Admin</title>
  <meta name="robots" content="noindex">
  <link rel="icon" type="image/svg+xml" href="<?= public_asset('favicon.svg') ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body style="padding-bottom:0;">
  <div class="ambient-bg"></div>
  <div id="toast-stack"></div>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a href="/admin/dashboard" class="brand" style="padding:18px 20px;"><img class="brand-mark" src="<?= asset('img/logo-mark.svg') ?>" alt="">Earn Admin</a>
      <nav class="admin-nav">
        <a class="<?= adminActive('/admin/dashboard', $path) ?>" href="/admin/dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="12" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="17" rx="1"/></svg>
          Dashboard</a>
        <a class="<?= adminActive('/admin/users', $path) ?>" href="/admin/users">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.5 2.6-6 6-6s6 2.5 6 6"/><circle cx="17.5" cy="9.2" r="2.6"/><path d="M15.8 14.3c2.7.3 4.7 2.4 4.7 5.4"/></svg>
          Users</a>
        <a class="<?= adminActive('/admin/deposits', $path) ?>" href="/admin/deposits">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11"/><path d="M7 10l5 5 5-5"/><path d="M4 19h16"/></svg>
          Deposits</a>
        <a class="<?= adminActive('/admin/withdrawals', $path) ?>" href="/admin/withdrawals">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V9"/><path d="M7 14l5-5 5 5"/><path d="M4 5h16"/></svg>
          Withdrawals</a>
        <a class="<?= adminActive('/admin/wallets', $path) ?>" href="/admin/wallets">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
          Wallet Ledger</a>
        <a class="<?= adminActive('/admin/products', $path) ?>" href="/admin/products">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8a3 3 0 016 0"/></svg>
          Products</a>
        <a class="<?= adminActive('/admin/orders', $path) ?>" href="/admin/orders">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M8 7V5a4 4 0 018 0v2"/></svg>
          Orders</a>
        <a class="<?= adminActive('/admin/tasks', $path) ?>" href="/admin/tasks">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l2 2 4-4"/><rect x="3" y="4" width="18" height="17" rx="2"/></svg>
          Tasks</a>
        <a class="<?= adminActive('/admin/ads', $path) ?>" href="/admin/ads">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="13" rx="2.5"/><path d="M10 9.5l4.5 2.5-4.5 2.5v-5z" fill="currentColor" stroke="none"/></svg>
          Watch &amp; Earn</a>
        <a class="<?= adminActive('/admin/game', $path) ?>" href="/admin/game">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15l3-8a3 3 0 013-2h6a3 3 0 013 2l3 8"/><path d="M3 15a2.5 2.5 0 002.5 3h13a2.5 2.5 0 002.5-3"/><circle cx="9" cy="14" r="1" fill="currentColor" stroke="none"/><path d="M14 12h3"/></svg>
          Billions Flight</a>
        <a class="<?= adminActive('/admin/membership-levels', $path) ?>" href="/admin/membership-levels">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="M9 13.5L7 21l5-3 5 3-2-7.5"/></svg>
          Membership</a>
        <a class="<?= adminActive('/admin/marketplaces', $path) ?>" href="/admin/marketplaces">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V10l8-6 8 6v11"/><path d="M9 21v-7h6v7"/></svg>
          Marketplaces</a>
        <a class="<?= adminActive('/admin/settings', $path) ?>" href="/admin/settings">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M12 2.5v3M12 18.5v3M4.2 6.2l2.1 2.1M17.7 15.7l2.1 2.1M2.5 12h3M18.5 12h3M4.2 17.8l2.1-2.1M17.7 8.3l2.1-2.1"/></svg>
          Settings</a>
        <a class="<?= adminActive('/admin/audit-logs', $path) ?>" href="/admin/audit-logs">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 9h6M9 13h6"/><path d="M9.5 17l1.5 1.5L14 15"/></svg>
          Audit Logs</a>
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
  <script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
