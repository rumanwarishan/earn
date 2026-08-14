(function () {
  var fab = document.getElementById('chatFab');
  var win = document.getElementById('chatWindow');
  var close = document.getElementById('chatClose');
  var body = document.getElementById('chatBody');
  var quick = document.getElementById('chatQuick');
  var input = document.getElementById('chatInput');
  var send = document.getElementById('chatSend');
  if (!fab || !win) return;

  var sessionId = localStorage.getItem('be_chat_session');
  if (!sessionId) {
    sessionId = 'sess-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
    localStorage.setItem('be_chat_session', sessionId);
  }

  var opened = false;

  function addMessage(sender, html) {
    var el = document.createElement('div');
    el.className = 'chat-msg ' + sender;
    el.innerHTML = html;
    body.appendChild(el);
    body.scrollTop = body.scrollHeight;
  }

  function setQuickReplies(options) {
    quick.innerHTML = '';
    (options || []).forEach(function (opt) {
      var b = document.createElement('button');
      b.textContent = opt;
      b.addEventListener('click', function () { sendMessage(opt); });
      quick.appendChild(b);
    });
  }

  function sendMessage(text) {
    if (!text || !text.trim()) return;
    addMessage('user', escapeHtml(text));
    input.value = '';

    fetch('/api/chatbot/message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
      body: JSON.stringify({ session_id: sessionId, message: text })
    }).then(function (r) { return r.json(); }).then(function (data) {
      addMessage('bot', data.reply_html || escapeHtml(data.reply || "Sorry, I didn't catch that."));
      setQuickReplies(data.quick_replies || []);
    }).catch(function () {
      addMessage('bot', "I'm having trouble responding right now. Please try again shortly.");
    });
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.innerText = s;
    return d.innerHTML;
  }

  fab.addEventListener('click', function () {
    win.classList.toggle('open');
    if (!opened) {
      opened = true;
      addMessage('bot', "Hi! I'm Billie 👋 I can help with deposits, withdrawals, cashback, referrals and orders. What do you need?");
      setQuickReplies(['How do I deposit?', 'How do withdrawals work?', 'How does cashback work?', 'Referral program']);
    }
  });
  close.addEventListener('click', function () { win.classList.remove('open'); });
  send.addEventListener('click', function () { sendMessage(input.value); });
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') sendMessage(input.value); });
})();
