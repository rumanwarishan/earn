<?php $current = $_SERVER['REQUEST_URI'] ?? ''; function navActive(string $path, string $current): string { return str_starts_with($current, $path) ? 'active' : ''; } ?>
<nav class="bottom-nav">
  <div class="bottom-nav-inner">
    <a class="nav-item <?= navActive('/dashboard', $current) ?>" href="/dashboard">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
      Home
    </a>
    <a class="nav-item <?= navActive('/shop', $current) ?>" href="/shop">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8a3 3 0 016 0"/></svg>
      Shop
    </a>
    <a class="nav-item <?= navActive('/referral', $current) ?>" href="/referral">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3"/><path d="M5 21c0-4 3-6 7-6s7 2 7 6"/></svg>
      Rewards
    </a>
    <a class="nav-item <?= navActive('/tasks', $current) ?>" href="/tasks">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M8 7V5a4 4 0 018 0v2"/></svg>
      Tasks
    </a>
    <a class="nav-item <?= navActive('/watch-and-earn', $current) ?>" href="/watch-and-earn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="5" width="19" height="13" rx="2.5"/><path d="M10 9.5l4.5 2.5-4.5 2.5v-5z" fill="currentColor" stroke="none"/></svg>
      Earn
    </a>
    <a class="nav-item <?= navActive('/game', $current) ?>" href="/game">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15l3-8a3 3 0 013-2h6a3 3 0 013 2l3 8"/><path d="M3 15a2.5 2.5 0 002.5 3h13a2.5 2.5 0 002.5-3"/><circle cx="9" cy="14" r="1" fill="currentColor" stroke="none"/><path d="M14 12h3"/></svg>
      Game
    </a>
    <a class="nav-item <?= navActive('/profile', $current) ?>" href="/profile">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.5 4-7 8-7s8 2.5 8 7"/></svg>
      Profile
    </a>
  </div>
</nav>
