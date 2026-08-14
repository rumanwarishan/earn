<?php $unread = isset($unreadCount) ? (int) $unreadCount : 0; ?>
<header class="app-header">
  <div class="app-header-inner">
    <a class="brand" href="/dashboard"><span class="brand-mark"></span><?= e((string) setting('site_name', 'Billions Earn')) ?></a>
    <div class="flex gap-2">
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
