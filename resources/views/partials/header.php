<?php $unread = isset($unreadCount) ? (int) $unreadCount : 0; $__path = $_SERVER['REQUEST_URI'] ?? ''; function __navActive(string $p, string $cur): string { return str_starts_with($cur, $p) ? 'active' : ''; } ?>
<header class="app-header">
  <div class="app-header-inner">
    <a class="brand" href="/dashboard"><img class="brand-mark" src="<?= asset('img/logo-mark.png') ?>" alt=""><?= e((string) setting('site_name', 'Billions Earn')) ?></a>
    <nav class="desktop-nav">
      <a class="<?= __navActive('/dashboard', $__path) ?>" href="/dashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>Home</a>
      <a class="<?= __navActive('/shop', $__path) ?>" href="/shop">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8a3 3 0 016 0"/></svg>Shop</a>
      <a class="<?= __navActive('/wallet', $__path) ?>" href="/wallet">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M16 13h.01"/></svg>Wallet</a>
      <a class="<?= (__navActive('/referral', $__path) || __navActive('/tasks', $__path)) ? 'active' : '' ?>" href="/referral">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3"/><path d="M5 21c0-4 3-6 7-6s7 2 7 6"/></svg>Rewards</a>
      <a class="<?= __navActive('/watch-and-earn', $__path) ?>" href="/watch-and-earn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="5" width="19" height="13" rx="2.5"/><path d="M10 9.5l4.5 2.5-4.5 2.5v-5z" fill="currentColor" stroke="none"/></svg>Watch &amp; Earn</a>
      <a class="<?= __navActive('/game', $__path) ?>" href="/game">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15l3-8a3 3 0 013-2h6a3 3 0 013 2l3 8"/><path d="M3 15a2.5 2.5 0 002.5 3h13a2.5 2.5 0 002.5-3"/><circle cx="9" cy="14" r="1" fill="currentColor" stroke="none"/><path d="M14 12h3"/></svg>Game</a>
    </nav>
    <div class="flex gap-2" style="margin-left:auto;">
      <a href="/notifications" class="icon-btn" aria-label="Notifications">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/></svg>
        <?php if ($unread > 0): ?><span class="dot"></span><?php endif; ?>
      </a>
      <a href="/profile" class="icon-btn" aria-label="Profile">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.5 4-7 8-7s8 2.5 8 7"/></svg>
      </a>
    </div>
  </div>
</header>
