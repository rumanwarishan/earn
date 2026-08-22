(function () {
  var stage = document.getElementById('flightStage');
  if (!stage) return;

  var canvas = document.getElementById('flightCanvas');
  var ctx = canvas.getContext('2d');
  var multEl = document.getElementById('flightMultiplier');
  var countdownEl = document.getElementById('flightCountdown');
  var countdownNumEl = document.getElementById('flightCountdownNumber');
  var crashedOverlay = document.getElementById('flightCrashedOverlay');
  var crashedMultEl = document.getElementById('flightCrashedMult');
  var entryRow = document.getElementById('entryRow');
  var stakeInput = document.getElementById('stakeInput');
  var joinBtn = document.getElementById('joinBtn');
  var claimBtn = document.getElementById('claimBtn');
  var claimAmountEl = document.getElementById('claimAmount');
  var waitingMsg = document.getElementById('waitingMsg');
  var spectateMsg = document.getElementById('spectateMsg');
  var roundResultEl = document.getElementById('roundResult');
  var connectionMsg = document.getElementById('connectionMsg');
  var balanceEl = document.getElementById('gpBalance');
  var potentialResultEl = document.getElementById('potentialResult');
  var dailyBonusBtn = document.getElementById('dailyBonusBtn');
  var recentPillsEl = document.getElementById('recentRoundsPills');

  var dpr = window.devicePixelRatio || 1;
  var latest = null;
  var serverOffsetMs = 0;
  var roundStartMs = null;
  var history = [];
  var joining = false;
  var claiming = false;
  var consecutiveFailures = 0;
  var pollTimer = null;

  function fmtGp(n) {
    return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: Object.assign(
        { 'X-CSRF-Token': window.CSRF_TOKEN || '', 'X-Requested-With': 'XMLHttpRequest' },
        body ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}
      ),
      body: body || undefined,
    }).then(function (r) { return r.json(); });
  }

  function resizeCanvas() {
    dpr = window.devicePixelRatio || 1;
    var rect = canvas.parentElement.getBoundingClientRect();
    canvas.width = Math.max(1, rect.width * dpr);
    canvas.height = Math.max(1, rect.height * dpr);
    canvas.style.width = rect.width + 'px';
    canvas.style.height = rect.height + 'px';
  }
  window.addEventListener('resize', resizeCanvas);
  resizeCanvas();

  function serverNowMs() {
    return Date.now() + serverOffsetMs;
  }

  function currentMultiplier() {
    if (!latest) return 1;
    if (latest.status === 'running' && roundStartMs !== null) {
      var elapsed = Math.max(0, (serverNowMs() - roundStartMs) / 1000);
      var rate = parseFloat(latest.settings.growth_rate) || 0.12;
      return Math.exp(rate * elapsed);
    }
    if (latest.status === 'crashed' || latest.status === 'completed') {
      return parseFloat(latest.crash_multiplier || 1);
    }
    return 1;
  }

  function draw(mult) {
    var w = canvas.width, h = canvas.height;
    ctx.clearRect(0, 0, w, h);

    ctx.strokeStyle = 'rgba(255,255,255,0.05)';
    ctx.lineWidth = 1;
    for (var i = 1; i < 5; i++) {
      var y = h - (h * i / 5);
      ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke();
    }

    if (history.length < 2) return;

    var pad = 22 * dpr;
    var tMax = Math.max(history[history.length - 1].t, 1);
    var mMax = Math.max(mult * 1.06, 1.3);
    var logMax = Math.log(mMax);

    function xFor(t) { return pad + (t / tMax) * (w - pad * 1.6); }
    function yFor(m) { return h - pad - (Math.log(Math.max(m, 1)) / logMax) * (h - pad * 1.8); }

    var pts = [];
    for (var n = 0; n < history.length; n++) pts.push([xFor(history[n].t), yFor(history[n].m)]);

    // Smooth the polyline into a genuine curve (quadratic through midpoints)
    // instead of a jagged straight-segment path - this is what makes the
    // trajectory read as a rising slope rather than a staircase.
    function tracePath() {
      ctx.beginPath();
      ctx.moveTo(pts[0][0], pts[0][1]);
      for (var i = 1; i < pts.length - 1; i++) {
        var mx = (pts[i][0] + pts[i + 1][0]) / 2;
        var my = (pts[i][1] + pts[i + 1][1]) / 2;
        ctx.quadraticCurveTo(pts[i][0], pts[i][1], mx, my);
      }
      var lastPt = pts[pts.length - 1];
      ctx.lineTo(lastPt[0], lastPt[1]);
    }

    var crashedNow = stage.classList.contains('crashed');
    var lineColor = crashedNow ? '#ff5d7a' : '#ff8a3d';

    var grad = ctx.createLinearGradient(0, 0, 0, h);
    grad.addColorStop(0, crashedNow ? 'rgba(255,93,122,0.34)' : 'rgba(255,138,61,0.36)');
    grad.addColorStop(0.55, crashedNow ? 'rgba(255,93,122,0.12)' : 'rgba(255,138,61,0.12)');
    grad.addColorStop(1, 'rgba(255,138,61,0)');

    tracePath();
    ctx.lineTo(pts[pts.length - 1][0], h);
    ctx.lineTo(pts[0][0], h);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();

    tracePath();
    ctx.strokeStyle = lineColor;
    ctx.lineWidth = 3.5 * dpr;
    ctx.shadowColor = lineColor;
    ctx.shadowBlur = 16 * dpr;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.stroke();
    ctx.shadowBlur = 0;

    var last = history[history.length - 1];
    var prevIdx = Math.max(0, history.length - 10);
    var prev = history[prevIdx];
    var lx = xFor(last.t), ly = yFor(last.m);
    var pxr = xFor(prev.t), pyr = yFor(prev.m);
    var angle = Math.atan2(ly - pyr, lx - pxr);

    if (latest && latest.status === 'running' && Math.random() < 0.55) {
      spawnSpark(lx + (Math.random() - 0.5) * 5 * dpr, ly + (Math.random() - 0.5) * 5 * dpr);
    }
    drawSparks(lx, ly, dpr, crashedNow);

    ctx.save();
    ctx.translate(lx, ly);
    ctx.rotate(angle);
    ctx.font = (30 * dpr) + 'px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.shadowColor = crashedNow ? '#ff5d7a' : '#ffb066';
    ctx.shadowBlur = 22 * dpr;
    ctx.fillText('✈️', 0, 0);
    ctx.restore();
  }

  var sparks = [];
  function spawnSpark(x, y) {
    sparks.push({ x: x, y: y, life: 1, r: (2 + Math.random() * 2) * dpr });
    if (sparks.length > 40) sparks.shift();
  }
  function drawSparks(planeX, planeY, dprLocal, crashedNow) {
    for (var i = sparks.length - 1; i >= 0; i--) {
      var s = sparks[i];
      s.life -= 0.035;
      if (s.life <= 0) { sparks.splice(i, 1); continue; }
      ctx.beginPath();
      ctx.fillStyle = (crashedNow ? 'rgba(255,93,122,' : 'rgba(255,176,102,') + (s.life * 0.6) + ')';
      ctx.arc(s.x, s.y, s.r * s.life, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  var rafHandle = null;
  function frame() {
    var mult = currentMultiplier();
    if (latest && latest.status === 'running' && roundStartMs !== null) {
      var elapsed = Math.max(0, (serverNowMs() - roundStartMs) / 1000);
      history.push({ t: elapsed, m: mult });
      if (history.length > 600) history.shift();
    }
    draw(mult);
    multEl.textContent = mult.toFixed(2) + 'x';

    if (claimBtn.style.display !== 'none' && window.__flightMyStake) {
      claimAmountEl.textContent = fmtGp(window.__flightMyStake * mult);
    }
    rafHandle = requestAnimationFrame(frame);
  }
  rafHandle = requestAnimationFrame(frame);

  function resetRoundUi() {
    history = [];
    sparks = [];
    stage.classList.remove('crashed');
    countdownEl.style.display = 'none';
    crashedOverlay.style.display = 'none';
    roundResultEl.style.display = 'none';
    waitingMsg.style.display = 'none';
    spectateMsg.style.display = 'none';
  }

  function updateUi(data) {
    var hasBet = !!data.my_bet;

    if (data.status === 'waiting') {
      countdownEl.style.display = 'flex';
      crashedOverlay.style.display = 'none';
      countdownNumEl.textContent = data.countdown_seconds > 0 ? data.countdown_seconds : 'GO';
      claimBtn.style.display = 'none';
      if (hasBet) {
        entryRow.style.display = 'none';
        waitingMsg.style.display = 'block';
      } else {
        entryRow.style.display = 'block';
        waitingMsg.style.display = 'none';
      }
      spectateMsg.style.display = 'none';
    } else if (data.status === 'running') {
      countdownEl.style.display = 'none';
      crashedOverlay.style.display = 'none';
      entryRow.style.display = 'none';
      waitingMsg.style.display = 'none';
      if (hasBet && data.my_bet.status === 'active') {
        claimBtn.style.display = 'block';
        claimBtn.disabled = false;
        claimBtn.classList.remove('claimed');
        window.__flightMyStake = parseFloat(data.my_bet.stake);
        spectateMsg.style.display = 'none';
      } else {
        claimBtn.style.display = 'none';
        spectateMsg.style.display = hasBet ? 'none' : 'block';
      }
    } else if (data.status === 'crashed' || data.status === 'completed') {
      countdownEl.style.display = 'none';
      stage.classList.add('crashed');
      crashedOverlay.style.display = 'flex';
      crashedMultEl.textContent = parseFloat(data.crash_multiplier).toFixed(2) + 'x';
      claimBtn.style.display = 'none';
      spectateMsg.style.display = 'none';
      waitingMsg.style.display = 'none';
      entryRow.style.display = 'none';

      if (hasBet) {
        roundResultEl.style.display = 'block';
        if (data.my_bet.status === 'cashed_out') {
          roundResultEl.style.color = 'var(--emerald)';
          roundResultEl.textContent = 'CLAIMED AT ' + parseFloat(data.my_bet.cashout_multiplier).toFixed(2) + 'x · +' + fmtGp(data.my_bet.payout) + ' GP';
        } else if (data.my_bet.status === 'lost') {
          roundResultEl.style.color = 'var(--danger)';
          roundResultEl.textContent = 'YOU LOST THIS ROUND';
        }
      } else {
        roundResultEl.style.display = 'none';
      }
    }

    if (data.settings && data.settings.enabled === false) {
      entryRow.style.display = 'none';
      joinBtn.disabled = true;
    }
  }

  function handleState(data) {
    connectionMsg.style.display = 'none';
    consecutiveFailures = 0;

    serverOffsetMs = data.server_time * 1000 - Date.now();
    var roundChanged = !latest || latest.round_uuid !== data.round_uuid;
    var wasRunning = latest && latest.status === 'running';
    latest = data;

    if (balanceEl) balanceEl.textContent = fmtGp(data.balance) + ' GP';

    if (roundChanged) {
      resetRoundUi();
      window.__flightMyStake = data.my_bet ? parseFloat(data.my_bet.stake) : null;
    }

    if (data.status === 'running') {
      if (roundStartMs === null || roundChanged || !wasRunning) {
        roundStartMs = serverNowMs() - (data.elapsed_seconds * 1000);
      }
    } else if (data.status === 'waiting') {
      roundStartMs = null;
    }

    updateUi(data);
  }

  function poll() {
    fetch('/game/state', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) throw new Error('bad status'); return r.json(); })
      .then(function (data) { handleState(data); })
      .catch(function () {
        consecutiveFailures++;
        if (consecutiveFailures >= 2) {
          connectionMsg.style.display = 'block';
          claimBtn.disabled = true;
        }
      })
      .finally(function () {
        pollTimer = setTimeout(poll, 500);
      });
  }
  poll();

  joinBtn.addEventListener('click', function () {
    if (joining) return;
    var stake = stakeInput.value;
    var min = parseFloat(window.FLIGHT_SETTINGS.minimum_entry);
    var max = parseFloat(window.FLIGHT_SETTINGS.maximum_entry);
    var val = parseFloat(stake);
    if (isNaN(val) || val < min || val > max) {
      window.toast && window.toast('Entry must be between ' + min + ' and ' + max + ' GP', 'error');
      return;
    }
    joining = true;
    joinBtn.disabled = true;
    postJson('/game/round/join', 'stake=' + encodeURIComponent(stake)).then(function (data) {
      joining = false;
      joinBtn.disabled = false;
      if (!data.ok) {
        window.toast && window.toast(data.error || 'Could not join this round', 'error');
        return;
      }
      window.__flightMyStake = parseFloat(data.stake);
      if (balanceEl) balanceEl.textContent = fmtGp(data.balance) + ' GP';
      entryRow.style.display = 'none';
      waitingMsg.style.display = 'block';
      window.toast && window.toast('Joined with ' + data.stake + ' GP');
    }).catch(function () {
      joining = false;
      joinBtn.disabled = false;
      window.toast && window.toast('Connection interrupted', 'error');
    });
  });

  claimBtn.addEventListener('click', function () {
    if (claiming || !latest) return;
    claiming = true;
    claimBtn.disabled = true;
    postJson('/game/round/' + encodeURIComponent(latest.round_uuid) + '/cashout').then(function (data) {
      claiming = false;
      if (!data.ok) {
        window.toast && window.toast(data.error || 'Claim failed', 'error');
        claimBtn.disabled = false;
        return;
      }
      claimBtn.classList.add('claimed');
      claimBtn.innerHTML = 'CLAIMED ✓';
      if (balanceEl) balanceEl.textContent = fmtGp(data.balance) + ' GP';
      window.toast && window.toast('Claimed at ' + parseFloat(data.multiplier).toFixed(2) + 'x · +' + fmtGp(data.payout) + ' GP');
    }).catch(function () {
      claiming = false;
      claimBtn.disabled = false;
      window.toast && window.toast('Connection interrupted - checking status...', 'error');
    });
  });

  if (stakeInput && potentialResultEl) {
    stakeInput.addEventListener('input', function () {
      var v = parseFloat(stakeInput.value) || 0;
      potentialResultEl.textContent = 'Potential result: ' + fmtGp(v) + ' GP × current multiplier';
    });
  }

  if (dailyBonusBtn) {
    dailyBonusBtn.addEventListener('click', function () {
      dailyBonusBtn.disabled = true;
      postJson('/game/daily-bonus').then(function (data) {
        if (!data.ok) {
          window.toast && window.toast(data.error || 'Bonus unavailable', 'error');
          dailyBonusBtn.disabled = false;
          return;
        }
        if (balanceEl) balanceEl.textContent = fmtGp(data.resulting_balance) + ' GP';
        window.toast && window.toast('+' + fmtGp(data.amount) + ' GP daily bonus claimed!');
        dailyBonusBtn.style.display = 'none';
      }).catch(function () {
        dailyBonusBtn.disabled = false;
        window.toast && window.toast('Connection interrupted', 'error');
      });
    });
  }
})();
