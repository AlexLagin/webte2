// main.js
window.addEventListener('load', () => {
  // ---- Track margins & sizes ----
  const marginH    = 40;
  const marginV    = 40;
  const trackWidth = 200;

  // ---- WebSocket ----
  const wsProtocol   = location.protocol === 'https:' ? 'wss:' : 'ws:';
  const ws           = new WebSocket(`${wsProtocol}//${location.host}/race`);
  const otherPlayers = {};   // keyed by playerIndex
  let yourIndex      = null;

  ws.addEventListener('open', () => console.log('WebSocket connected'));

  // ---- Ukládání finish časů ----
  let finishTimes = {};

  ws.addEventListener('message', ev => {
    const msg = JSON.parse(ev.data);
    const p   = trackParams();

    switch (msg.type) {
      case 'joined':
        yourIndex = msg.data.playerIndex;
        return;

      case 'start':
        lapsToDo       = msg.data.laps;
        currentLap     = 1;
        lapsEl.textContent = `1 / ${lapsToDo} kôl`;
        // pozice na startu
        {
          const yStartTop = p.cy - p.centerR;
          const offsetX   = yourIndex === 0 ? -15 : 15;
          player.x = p.cx + offsetX;
          player.y = yStartTop;
        }
        render();
        return;

      case 'state':
        Object.values(msg.data).forEach(pl => {
          if (pl.playerIndex !== yourIndex) {
            otherPlayers[pl.playerIndex] = {
              x:     pl.x,
              y:     pl.y,
              name:  pl.name,
              color: pl.color,
              lap:   pl.lap
            };
          }
        });
        render();
        return;

      case 'player-move':
        const d = msg.data;
        if (d.index !== yourIndex) {
          otherPlayers[d.index] = {
            // z normalizovaných souřadnic zpět na pixely:
            x:     d.x * canvas.width,
            y:     d.y * canvas.height,
            name:  d.name,
            color: d.color,
            lap:   d.lap
          };
          render();
        }
        return;

      case 'playerFinish':
        finishTimes[msg.data.name] = msg.data.time;
        showFinishModal(false);
        return;

      case 'finish':
        msg.data.players.forEach(pl => {
          finishTimes[pl.name] = pl.time;
        });
        showFinishModal(true);
        return;
    }
  });

  // ---- DOM refs ----
  const menu       = document.getElementById('menu');
  const form       = document.getElementById('startForm');
  const nameInput  = document.getElementById('nameInput');
  const lapsSelect = document.getElementById('lapsSelect');
  const teamSelect = document.getElementById('teamSelect');
  const helpButton = document.getElementById('helpButton');
  const helpModal  = document.getElementById('helpModal');
  const closeHelp  = document.getElementById('closeHelp');
  const timerEl    = document.getElementById('timer');
  const lapsEl     = document.getElementById('lapsCounter');
  const canvas     = document.getElementById('trackCanvas');
  const ctx        = canvas.getContext('2d');
  const modal      = document.getElementById('modal');
  const modalMsg   = document.getElementById('modalMessage');
  const closeBtn   = document.getElementById('closeModal');

  // ---- Help modal ----
  if (helpButton && helpModal) {
    helpButton.addEventListener('click', () => helpModal.style.display = 'flex');
  }
  if (closeHelp && helpModal) {
    closeHelp.addEventListener('click', () => helpModal.style.display = 'none');
  }

  // ---- Finish modal close ----
  if (closeBtn && menu) {
    closeBtn.addEventListener('click', () => {
      modal.style.display = 'none';
      menu.style.display  = 'flex';
      render();
    });
  }

  // ---- Show finish modal ----
  function showFinishModal(isFinal) {
    let html = '';
    Object.keys(finishTimes).forEach(name => {
      html += `<div><strong>${name}:</strong> ${formatTime(finishTimes[name]*1000)}</div>`;
    });
    if (!isFinal) {
      const allNames = [player.name, ...Object.values(otherPlayers).map(o => o.name)];
      allNames.forEach(n => {
        if (finishTimes[n] == null) {
          html += `<div><strong>${n}:</strong> nedokončil závod</div>`;
        }
      });
    }
    modalMsg.innerHTML = html;
    modal.style.display = 'flex';
  }

  // ---- Canvas & timer ----
  function resizeCanvas() {
    canvas.width  = canvas.clientWidth;
    canvas.height = canvas.clientHeight;
    render();
  }
  window.addEventListener('resize', resizeCanvas);
  resizeCanvas();

  let startTime, timerInterval, penaltyTime = 0;
  function formatTime(ms) {
    const total = ms / 1000;
    const m     = Math.floor(total / 60);
    const s     = String(Math.floor(total % 60)).padStart(2, '0');
    const cs    = String(Math.floor((ms % 1000) / 10)).padStart(2, '0');
    return `${m}:${s}.${cs}`;
  }
  function startTimer() {
    startTime   = Date.now();
    penaltyTime = 0;
    timerInterval = setInterval(() => {
      const elapsed = Date.now() - startTime + penaltyTime;
      timerEl.textContent = `Čas: ${formatTime(elapsed)}`;
    }, 33);
  }
  function stopTimer() {
    clearInterval(timerInterval);
  }

  // ---- Track geometry ----
  function trackParams() {
    const W = canvas.width, H = canvas.height;
    const outerR  = (H - marginV * 2) / 2;
    const innerR  = outerR - trackWidth;
    const centerR = (outerR + innerR) / 2;
    const cy      = marginV + outerR;
    const leftCx  = marginH + outerR;
    const rightCx = W - marginH - outerR;
    const cx      = W / 2;
    return { outerR, innerR, centerR, cy, leftCx, rightCx, cx };
  }

  // ---- State & input ----
  let started=false, finished=false, bottomCrossed=false;
  let outsideFlag=false, currentLap=1, lapsToDo=0;
  const player={ x:0, y:0, speed:4, name:'', color:'#fff' };
  const keys={};

  window.addEventListener('keydown', e => keys[e.key] = true);
  window.addEventListener('keyup',   e => keys[e.key] = false);

  // ---- Start form ----
  form.addEventListener('submit', e => {
    e.preventDefault();
    player.name  = nameInput.value.trim() || `Hráč${yourIndex}`;
    lapsToDo     = parseInt(lapsSelect.value, 10);
    player.color = teamSelect.value;
    started      = true;
    finished     = false;
    bottomCrossed= false;
    outsideFlag  = false;
    currentLap   = 1;
    penaltyTime  = 0;
    finishTimes  = {};
    timerEl.textContent = 'Čas: 00:00.00';
    lapsEl.textContent  = `1 / ${lapsToDo} kôl`;
    menu.style.display  = 'none';

    const p      = trackParams();
    player.x = p.cx;
    player.y = p.cy - p.centerR;
    render();
    startTimer();

    ws.send(JSON.stringify({
      type:  'join',
      name:  player.name,
      laps:  lapsToDo,
      color: player.color
    }));
    // pošleme první MOVE jako relativní
    ws.send(JSON.stringify({
      type:  'move',
      index: yourIndex,
      name:  player.name,
      color: player.color,
      x:     player.x / canvas.width,
      y:     player.y / canvas.height,
      lap:   currentLap
    }));

    last = performance.now();
    requestAnimationFrame(frame);
  });

  // ---- Movement, laps & penalty ----
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

  function update(dt) {
    if (!started || finished) return;
    const prevX = player.x;
    const boost = keys.Shift ? 1.5 : 1;
    const v     = player.speed * boost * dt;
    if (keys.ArrowLeft)  player.x -= v;
    if (keys.ArrowRight) player.x += v;
    if (keys.ArrowUp)    player.y -= v;
    if (keys.ArrowDown)  player.y += v;

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
        lapsEl.textContent = `${currentLap} / ${lapsToDo} kôl`;
      } else {
        finished = true;
        stopTimer();
      }
    }

    const inside = isInsideTrack(player.x, player.y);
    if (!inside && !outsideFlag) {
      outsideFlag = true;
      penaltyTime += 5000;
    }
    if (inside && outsideFlag) outsideFlag = false;

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

  // ---- Drawing ----
  function drawTrack() {
    const { outerR, innerR, centerR, cy, leftCx, rightCx, cx } = trackParams();
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // 1) Asfaltový pás (vonkajší)
    ctx.fillStyle = '#555';
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - outerR);
    ctx.lineTo(rightCx, cy - outerR);
    ctx.arc(rightCx, cy, outerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + outerR);
    ctx.arc(leftCx, cy, outerR,  Math.PI/2, -Math.PI/2);
    ctx.closePath();
    ctx.fill();

    // 2) Vnútorný výrez (destination-out)
    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - innerR);
    ctx.lineTo(rightCx, cy - innerR);
    ctx.arc(rightCx, cy, innerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + innerR);
    ctx.arc(leftCx, cy, innerR,  Math.PI/2, -Math.PI/2);
    ctx.closePath();
    ctx.fill();
    ctx.globalCompositeOperation = 'source-over';

    // 3) Obrysy (hranice)
    ctx.lineWidth   = 4;
    ctx.strokeStyle = '#222';
    // vonkajší obrys
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - outerR);
    ctx.lineTo(rightCx, cy - outerR);
    ctx.arc(rightCx, cy, outerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + outerR);
    ctx.arc(leftCx, cy, outerR,  Math.PI/2, -Math.PI/2);
    ctx.closePath();
    ctx.stroke();
    // vnútorný obrys
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - innerR);
    ctx.lineTo(rightCx, cy - innerR);
    ctx.arc(rightCx, cy, innerR, -Math.PI/2, Math.PI/92);
    ctx.lineTo(leftCx, cy + innerR);
    ctx.arc(leftCx, cy, innerR,  Math.PI/92, -Math.PI/2);
    ctx.closePath();
    ctx.stroke();

    // 4) Stredová prerušovaná čiara
    ctx.setLineDash([30,20]);
    ctx.lineWidth   = 2;
    ctx.strokeStyle = '#fff';
    ctx.beginPath();
    ctx.moveTo(leftCx, cy - centerR);
    ctx.lineTo(rightCx, cy - centerR);
    ctx.arc(rightCx, cy, centerR, -Math.PI/2, Math.PI/2);
    ctx.lineTo(leftCx, cy + centerR);
    ctx.arc(leftCx, cy, centerR,  Math.PI/2, -Math.PI/92);
    ctx.closePath();
    ctx.stroke();
    ctx.setLineDash([]);

    // 5) Štartovacia čiara (hore)
    ctx.lineWidth   = 6;
    ctx.strokeStyle = '#ff0';
    ctx.beginPath();
    ctx.moveTo(cx, cy - outerR);
    ctx.lineTo(cx, cy - innerR);
    ctx.stroke();

    // 6) Dolný checkpoint
    ctx.beginPath();
    ctx.moveTo(cx, cy + innerR);
    ctx.lineTo(cx, cy + outerR);
    ctx.stroke();
  }

  function drawPlayer() {
    if (!started) return;
    ctx.fillStyle = player.color;
    ctx.beginPath();
    ctx.arc(player.x, player.y, 10, 0, 2 * Math.PI);
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.font = '14px sans-serif';
    ctx.fillText(player.name, player.x + 12, player.y - 12);
  }

  function drawOthers() {
    if (!started) return;
    Object.values(otherPlayers).forEach(p => {
      ctx.fillStyle = p.color;
      ctx.beginPath();
      ctx.arc(p.x, p.y, 10, 0, 2 * Math.PI);
      ctx.fill();
      ctx.fillStyle = '#fff';
      ctx.font = '14px sans-serif';
      ctx.fillText(p.name, p.x + 12, p.y - 12);
    });
  }

  function render() {
    drawTrack();
    drawPlayer();
    drawOthers();
  }

  // ---- Main loop ----
  let last = performance.now();
  function frame(now) {
    const dt = (now - last) / 16.67;
    last = now;
    update(dt);
    render();
    if (!finished) requestAnimationFrame(frame);
  }

  requestAnimationFrame(frame);
});
