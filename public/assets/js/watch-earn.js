(function () {
  var modal = document.getElementById('watchAdModal');
  var modalBody = document.getElementById('watchAdBody');
  if (!modal || !modalBody) return;

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.innerText = s || '';
    return d.innerHTML;
  }

  function escapeAttr(s) {
    return (s || '').replace(/"/g, '&quot;');
  }

  function closeModal() {
    modal.style.display = 'none';
    modalBody.innerHTML = '';
  }

  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  function postJson(url) {
    return fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (r) { return r.json(); });
  }

  function openAd(card) {
    var adId = card.getAttribute('data-ad-id');
    var title = card.getAttribute('data-title');
    var reward = card.getAttribute('data-reward');
    var image = card.getAttribute('data-image');
    var destination = card.getAttribute('data-destination');
    var type = card.getAttribute('data-type');

    modalBody.innerHTML =
      '<h3 style="margin-bottom:12px;">' + escapeHtml(title) + '</h3>' +
      (image ? '<img src="' + escapeAttr(image) + '" style="width:100%;border-radius:12px;margin-bottom:14px;">' : '') +
      (type === 'external' && destination
        ? '<a href="' + escapeAttr(destination) + '" target="_blank" rel="noopener" class="btn btn-secondary btn-sm mb-3" style="display:block;text-align:center;">Open advertisement</a>'
        : '') +
      '<div id="watchStatus" class="text-muted" style="text-align:center;margin:14px 0;font-size:13.5px;">Starting&hellip;</div>' +
      '<button class="btn btn-primary" id="watchClaimBtn" type="button" disabled style="width:100%;">Claim +' + escapeHtml(reward) + '</button>' +
      '<button class="btn btn-ghost btn-sm mt-2" id="watchCancelBtn" type="button" style="width:100%;">Cancel</button>';

    modal.style.display = 'flex';

    var statusEl = document.getElementById('watchStatus');
    var claimBtn = document.getElementById('watchClaimBtn');
    var cancelBtn = document.getElementById('watchCancelBtn');
    var timer = null;

    cancelBtn.addEventListener('click', function () {
      if (timer) clearInterval(timer);
      closeModal();
    });

    postJson('/watch-and-earn/' + encodeURIComponent(adId) + '/start').then(function (data) {
      if (!data.ok) {
        statusEl.textContent = data.error || 'This advertisement is unavailable right now.';
        return;
      }

      var sessionUuid = data.session_uuid;
      var remaining = data.watch_seconds;
      statusEl.textContent = 'Please wait ' + remaining + 's…';

      timer = setInterval(function () {
        remaining -= 1;
        if (remaining <= 0) {
          clearInterval(timer);
          statusEl.textContent = 'You can claim your reward now!';
          claimBtn.disabled = false;
        } else {
          statusEl.textContent = 'Please wait ' + remaining + 's…';
        }
      }, 1000);

      claimBtn.addEventListener('click', function () {
        claimBtn.disabled = true;
        claimBtn.textContent = 'Claiming…';

        postJson('/watch-and-earn/session/' + encodeURIComponent(sessionUuid) + '/complete').then(function (result) {
          if (result.ok) {
            statusEl.textContent = 'Reward credited! Refreshing…';
            window.location.reload();
          } else {
            statusEl.textContent = result.error || 'Could not claim your reward.';
            claimBtn.textContent = 'Claim +' + reward;
            claimBtn.disabled = false;
          }
        }).catch(function () {
          statusEl.textContent = 'Network error. Please try again.';
          claimBtn.textContent = 'Claim +' + reward;
          claimBtn.disabled = false;
        });
      });
    }).catch(function () {
      statusEl.textContent = 'Network error. Please try again.';
    });
  }

  document.querySelectorAll('.watch-ad-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.ad-card');
      if (card) openAd(card);
    });
  });
})();
