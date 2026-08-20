(function () {
  window.toast = function (message, type) {
    var stack = document.getElementById('toast-stack');
    if (!stack) return;
    var el = document.createElement('div');
    el.className = 'toast';
    el.textContent = message;
    if (type === 'error') el.style.borderColor = 'rgba(255,93,122,0.4)';
    stack.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .3s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 300);
    }, 3200);
  };

  window.copyToClipboard = function (text, label) {
    navigator.clipboard.writeText(text).then(function () {
      window.toast((label || 'Copied') + ' to clipboard');
    }).catch(function () {
      window.toast('Could not copy to clipboard', 'error');
    });
  };

  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      window.copyToClipboard(btn.getAttribute('data-copy'), btn.getAttribute('data-copy-label'));
    });
  });

  document.querySelectorAll('[data-share-link]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-share-link');
      if (navigator.share) {
        navigator.share({ title: 'Join Billions Earn', url: url }).catch(function () {});
      } else {
        window.copyToClipboard(url, 'Invite link');
      }
    });
  });

  // "Share to unlock" tasks: the Claim button stays hidden until the user
  // has clicked a share/copy action at least once.
  document.querySelectorAll('[data-reveal-claim]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = document.getElementById(btn.getAttribute('data-reveal-claim'));
      if (form) form.style.display = 'inline-flex';
    });
  });
})();
