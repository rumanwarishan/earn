<?php if (!(bool) setting('chatbot_enabled', true)) { return; } ?>
<div class="chat-fab" id="chatFab" role="button" aria-label="Open assistant">
  <div class="chat-fab-ring"></div>
  <div class="chat-fab-core">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#04060f" stroke-width="2.2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
  </div>
</div>
<div class="chat-window glass-card glow-ring" id="chatWindow">
  <div class="chat-header">
    <div class="flex items-center gap-2">
      <img class="brand-mark sm" src="<?= asset('img/logo-mark.svg') ?>" alt="">
      <div>
        <div style="font-weight:700;font-size:13.5px;">Billie</div>
        <div style="font-size:11px;color:var(--text-lo);">Billions Earn Assistant</div>
      </div>
    </div>
    <span class="icon-btn" id="chatClose" style="width:30px;height:30px;">&times;</span>
  </div>
  <div class="chat-body" id="chatBody"></div>
  <div class="chat-quick" id="chatQuick"></div>
  <div class="chat-input-row">
    <input class="input" id="chatInput" placeholder="Ask about deposits, withdrawals, cashback..." />
    <button class="btn btn-primary btn-sm" id="chatSend">Send</button>
  </div>
</div>
<script src="<?= asset('js/chatbot.js') ?>" defer></script>
