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
    <a class="nav-item <?= navActive('/orders', $current) ?>" href="/orders">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M8 7V5a4 4 0 018 0v2"/></svg>
      Orders
    </a>
    <a class="nav-item <?= navActive('/profile', $current) ?>" href="/profile">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.5 4-7 8-7s8 2.5 8 7"/></svg>
      Profile
    </a>
  </div>
</nav>
