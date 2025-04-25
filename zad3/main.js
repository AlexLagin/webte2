// public/main.js
window.addEventListener('load', () => {
  // ---- Konštanty trate ----
  const marginH    = 40;
  const marginV    = 40;
  const trackWidth = 200;

  // ---- Stav hry ----
  let started       = false;
  let finished      = false;
  let bottomCrossed = false;
  let outsideFlag   = false;
  let currentLap    = 1;
  let lapsToDo      = 0;
  let penaltyTime   = 0;      // v ms
  let timerStarted  = false;
  let lastResults   = [];

  // ---- Stav boostu ----
  const maxBoost          = 100;
  let boost               = maxBoost;
  const boostUseRate      = 50;  // jednotiek/s pri držaní Shift
  const boostRechargeRate = 10;  // jednotiek/s inak

  // ---- Freeze po kolízii ----
  let freezeTime = 0; // v sekundách

  // ---- Sledovanie kláves ----
  const keys = {};
  window.addEventListener('keydown', e => keys[e.key] = true);
  window.addEventListener('keyup',   e => keys[e.key] = false);

  // ---- Hráč a súperi ----
  const player       = { x: null, y: null, speed: 4, name: '', color: '#fff' };
  const otherPlayers = {};
  const finishTimes  = {};

  // ---- DOM referencie ----
  const timerEl    = document.getElementById('timer');
  const lapsEl     = document.getElementById('lapsCounter');
  const penaltyEl  = document.getElementById('penalty');
  const boostBarEl = document.getElementById('boostBar');
  const form       = document.getElementById('startForm');
  const nameInput  = document.getElementById('nameInput');
  const lapsSelect = document.getElementById('lapsSelect');
  const teamSelect = document.getElementById('teamSelect');
  const helpBtn    = document.getElementById('helpButton');
  const helpModal  = document.getElementById('helpModal');
  const closeHelp  = document.getElementById('closeHelp');
  const canvas     = document.getElementById('trackCanvas');
  const ctx        = canvas.getContext('2d');
  const waiting    = document.getElementById('waiting');
  const modal      = document.getElementById('modal');
  const closeMod   = document.getElementById('closeModal');
  const modalMsg   = document.getElementById('modalMessage');
  const restartBtn = document.getElementById('restartButton');

  // ---- HELP modal handlers ----
  helpBtn.addEventListener('click', () => {
    helpModal.style.display = 'flex';
  });
  closeHelp.addEventListener('click', () => {
    helpModal.style.display = 'none';
  });

  // ---- FINISH modal handlers ----
  closeMod.addEventListener('click', () => {
    modal.style.display = 'none';
  });
  restartBtn.addEventListener('click', () => {
    window.location.reload();
  });

  // ---- WebSocket ----
  const proto      = location.protocol === 'https:' ? 'wss:' : 'ws:';
  const ws         = new WebSocket(`${proto}//${location.host}/race`);
  let yourIndex   = null;
  let pendingJoin = null;

  ws.addEventListener('open', () => {
    if (pendingJoin) {
      ws.send(JSON.stringify(pendingJoin));
      pendingJoin = null;
    }
  });

  ws.addEventListener('message', ev => {
    const msg = JSON.parse(ev.data);
    switch (msg.type) {
      case 'joined':
        yourIndex = msg.data.playerIndex;
        if (yourIndex > 0) lapsSelect.disabled = true;
        break;
      case 'start':
        handleStart(msg.data);
        break;
      case 'player-move':
        handlePlayerMove(msg.data);
        break;
      case 'playerFinish':
        finishTimes[msg.data.name] = msg.data.time;
        break;
      case 'finish':
        handleFinish(msg.data.players);
        break;
    }
  });

  // ==== FORM SUBMIT ==== //
  form.addEventListener('submit', e => {
    e.preventDefault();
    player.name  = nameInput.value.trim() || 'Hráč';
    player.color = teamSelect.value;

    waiting.style.display = 'flex';
    const payload = {
      type:  'join',
      name:  player.name,
      laps:  +lapsSelect.value,
      color: player.color
    };
    if (ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify(payload));
    } else {
      pendingJoin = payload;
    }
  });

  // ==== HANDLERY ==== //
  function handleStart(data) {
    // použijeme laps z data od servera
    lapsToDo      = data.laps;
    currentLap    = 1;
    started       = true;
    finished      = false;
    bottomCrossed = false;
    outsideFlag   = false;
    penaltyTime   = 0;
    timerStarted  = false;
    boost         = maxBoost;
    freezeTime    = 0;
    lastResults   = [];
    Object.keys(finishTimes).forEach(k => delete finishTimes[k]);
    updateHUD();
    updateBoostBar();

    // synchronizácia UI
    lapsSelect.value    = data.laps;
    lapsSelect.disabled = true;

    waiting.style.display = 'none';

    // pozície na štarte
    const p     = trackParams();
    const baseX = p.cx + 20;
    const baseY = p.cy - p.centerR;
    const gap   = 50;

    data.players.forEach((pl, i) => {
      const x = baseX;
      const y = i === 0 ? baseY - gap : baseY + gap;
      if (i === data.yourIndex) {
        player.x     = x;
        player.y     = y;
        player.name  = pl.name;
        player.color = pl.color;
      } else {
        otherPlayers[i] = { x, y, name: pl.name, color: pl.color, lap: 1 };
      }
    });

    render();
  }

  function handlePlayerMove(d) {
    if (d.index === yourIndex) return;
    otherPlayers[d.index] = {
      x:     d.x * canvas.width,
      y:     d.y * canvas.height,
      name:  d.name,
      color: d.color,
      lap:   d.lap
    };
    render();
  }

  function handleFinish(players) {
    lastResults = players;
    players.forEach(pl => finishTimes[pl.name] = pl.time);
    const winner = players.reduce((a, b) => a.time < b.time ? a : b).name;
    modal.querySelector('.content h2').textContent = `Víťaz: ${winner}`;
    modalMsg.innerHTML = lastResults
      .map(pl => `<div><strong>${pl.name}:</strong> ${formatTime(pl.time * 1000)}</div>`)
      .join('');
    modal.style.display = 'flex';
  }

  // ==== CANVAS RESIZE ==== //
  function resizeCanvas() {
    canvas.width  = canvas.clientWidth;
    canvas.height = canvas.clientHeight;
    render();
  }
  window.addEventListener('resize', resizeCanvas);
  resizeCanvas();

  // ==== TIMER & HUD ==== //
  let startTime, timerInt;
  function formatTime(ms) {
    const total = ms / 1000;
    const m     = Math.floor(total / 60);
    const s     = String(Math.floor(total % 60)).padStart(2, '0');
    const cs    = String(Math.floor((ms % 1000) / 10)).padStart(2, '0');
    return `${m}:${s}.${cs}`;
  }
  function startTimer() {
    startTime = Date.now();
    timerInt  = setInterval(() => {
      const elapsed = Date.now() - startTime + penaltyTime;
      timerEl.textContent = `Čas: ${formatTime(elapsed)}`;
      updateHUD();
    }, 100);
  }
  function stopTimer() {
    clearInterval(timerInt);
  }

  function updateHUD() {
    lapsEl.textContent    = `${currentLap} / ${lapsToDo} kôl`;
    penaltyEl.textContent = `Penalizácia: ${Math.floor(penaltyTime / 1000)} s`;
  }

  function updateBoostBar() {
    const pct = Math.max(0, Math.min(1, boost / maxBoost)) * 100;
    boostBarEl.style.width = `${pct}%`;
  }

  // ==== VYKRESĽOVACIE FUNKCIE ==== //
  function trackParams() {
    const W       = canvas.width;
    const H       = canvas.height;
    const outerR  = (H - marginV * 2) / 2;
    const innerR  = outerR - trackWidth;
    const centerR = (outerR + innerR) / 2;
    const cy      = marginV + outerR;
    const leftCx  = marginH + outerR;
    const rightCx = W - marginH - outerR;
    const cx      = W / 2;
    return { outerR, innerR, centerR, cy, leftCx, rightCx, cx };
  }

  function drawTrack() {
    const { outerR, innerR, centerR, cy, leftCx, rightCx, cx } = trackParams();
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // asfalt
    ctx.fillStyle = '#555';
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - outerR);
    ctx.lineTo(rightCx, cy - outerR);
    ctx.arc(rightCx, cy, outerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + outerR);
    ctx.arc(leftCx, cy, outerR, Math.PI/2, -Math.PI/2);
    ctx.closePath();
    ctx.fill();

    // vnútorný výrez
    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - innerR);
    ctx.lineTo(rightCx, cy - innerR);
    ctx.arc(rightCx, cy, innerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + innerR);
    ctx.arc(leftCx, cy, innerR, Math.PI/2, -Math.PI/2);
    ctx.closePath();
    ctx.fill();
    ctx.globalCompositeOperation = 'source-over';

    // vonkajší obrys
    ctx.lineWidth   = 4;
    ctx.strokeStyle = '#222';
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - outerR);
    ctx.lineTo(rightCx, cy - outerR);
    ctx.arc(rightCx, cy, outerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + outerR);
    ctx.arc(leftCx, cy, outerR, Math.PI/2, -Math.PI/2);
    ctx.closePath();
    ctx.stroke();

    // vnútorný obrys
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - innerR);
    ctx.lineTo(rightCx, cy - innerR);
    ctx.arc(rightCx, cy, innerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + innerR);
    ctx.arc(leftCx, cy, innerR, Math.PI/2, -Math.PI/2);
    ctx.closePath();
    ctx.stroke();

    // prerušovaná čiara
    ctx.setLineDash([30,20]);
    ctx.lineWidth   = 2;
    ctx.strokeStyle = '#fff';
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - centerR);
    ctx.lineTo(rightCx, cy - centerR);
    ctx.arc(rightCx, cy, centerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + centerR);
    ctx.arc(leftCx, cy, centerR, Math.PI/2, -Math.PI/2);
    ctx.closePath();
    ctx.stroke();
    ctx.setLineDash([]);

    // štartovacie žlté čiary
    ctx.lineWidth   = 6;
    ctx.strokeStyle = '#ff0';
    ctx.beginPath();
    ctx.moveTo(cx, cy - outerR);
    ctx.lineTo(cx, cy - innerR);
    ctx.moveTo(cx, cy + innerR);
    ctx.lineTo(cx, cy + outerR);
    ctx.stroke();
  }

  function drawPlayer() {
    if (player.x === null || player.y === null) return;
    ctx.fillStyle = player.color;
    ctx.beginPath();
    ctx.arc(player.x, player.y, 10, 0, 2*Math.PI);
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.font      = '14px sans-serif';
    ctx.fillText(player.name, player.x + 12, player.y - 12);
  }

  function drawOthers() {
    if (!started) return;
    Object.values(otherPlayers).forEach(p => {
      ctx.fillStyle = p.color;
      ctx.beginPath();
      ctx.arc(p.x, p.y, 10, 0, 2*Math.PI);
      ctx.fill();
      ctx.fillStyle = '#fff';
      ctx.font      = '14px sans-serif';
      ctx.fillText(p.name, p.x + 12, p.y - 12);
    });
  }

  function render() {
    drawTrack();
    drawPlayer();
    drawOthers();
  }

  // ==== HERNÝ LOOP ==== //
  let last = performance.now();
  (function frame(now) {
    const dt = (now - last) / 1000;
    last     = now;
    if (started && !finished) update(dt);
    render();
    requestAnimationFrame(frame);
  })();

  function update(dt) {
    // ak sme zamrznutí, odrátavame cooldown a blokujeme pohyb
    if (freezeTime > 0) {
      freezeTime -= dt;
      return;
    }

    // spustenie stopiek
    if (!timerStarted &&
        (keys.ArrowLeft || keys.ArrowRight || keys.ArrowUp || keys.ArrowDown)) {
      startTimer();
      timerStarted = true;
    }

    // BOOST
    let usingBoost = false;
    if (keys.Shift && boost > 0) {
      usingBoost = true;
      boost      = Math.max(0, boost - boostUseRate * dt);
    } else if (!keys.Shift) {
      boost = Math.min(maxBoost, boost + boostRechargeRate * dt);
    }
    updateBoostBar();

    // pohyb
    const prevX     = player.x;
    const speedMult = usingBoost ? 1.5 : 1;
    const v         = player.speed * speedMult * dt * 60;
    let newX        = player.x;
    let newY        = player.y;
    const moving    = keys.ArrowLeft || keys.ArrowRight || keys.ArrowUp || keys.ArrowDown;
    if (keys.ArrowLeft)  newX -= v;
    if (keys.ArrowRight) newX += v;
    if (keys.ArrowUp)    newY -= v;
    if (keys.ArrowDown)  newY += v;

    // detekcia kolízie medzi guličkami (polomer 10px)
    if (moving) {
      for (const p of Object.values(otherPlayers)) {
        const dx = newX - p.x;
        const dy = newY - p.y;
        if (Math.hypot(dx, dy) < 20) {
          // tento hráč spôsobil kolíziu – freeze na 3s
          freezeTime = 3;
          return;
        }
      }
    }

    // kolízie s dráhou a penalizácia
    if (isInsideTrack(newX, newY)) {
      player.x    = newX;
      player.y    = newY;
      outsideFlag = false;
    } else if (!outsideFlag) {
      outsideFlag = true;
      penaltyTime += 5000;
      updateHUD();
    }

    // detekcia kola
    const { innerR, outerR, cy, cx } = trackParams();
    if (!bottomCrossed &&
        ((prevX < cx && player.x >= cx) || (prevX > cx && player.x <= cx)) &&
         player.y >= cy + innerR && player.y <= cy + outerR) {
      bottomCrossed = true;
    }
    if (bottomCrossed &&
        ((prevX < cx && player.x >= cx) || (prevX > cx && player.x <= cx)) &&
         player.y <= cy - innerR && player.y >= cy - outerR) {
      bottomCrossed = false;
      if (currentLap < lapsToDo) {
        currentLap++;
      } else {
        finished = true;
        stopTimer();
        const ft = (Date.now() - startTime + penaltyTime) / 1000;
        if (ws.readyState === WebSocket.OPEN) {
          ws.send(JSON.stringify({
            type:  'finish',
            index: yourIndex,
            time:  ft,
            name:  player.name
          }));
        }
      }
      updateHUD();
    }

    // broadcast pohybu
    if (ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({
        type:  'move',
        index: yourIndex,
        name:  player.name,
        color: player.color,
        x:     player.x / canvas.width,
        y:     player.y / canvas.height,
        lap:   currentLap
      }));
    }
  }

  function isInsideTrack(x, y) {
    const { innerR, outerR, cy, leftCx, rightCx, cx } = trackParams();
    if (x >= leftCx && x <= rightCx) {
      return (y >= cy - outerR && y <= cy - innerR) ||
             (y >= cy + innerR && y <= cy + outerR);
    }
    const centerX = x < cx ? leftCx : rightCx;
    const d       = Math.hypot(x - centerX, y - cy);
    return d >= innerR && d <= outerR;
  }

  function showFinishModal(winner) {
    modal.querySelector('.content h2').textContent = `Víťaz: ${winner}`;
    modalMsg.innerHTML = lastResults
      .map(pl => `<div><strong>${pl.name}:</strong> ${formatTime(pl.time * 1000)}</div>`)
      .join('');
    modal.style.display = 'flex';
  }
});
