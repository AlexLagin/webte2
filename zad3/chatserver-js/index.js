const express   = require('express');
const http      = require('http');
const path      = require('path');
const WebSocket = require('ws');

const PORT      = 8081;
const TICK_RATE = 20;
const games     = [];

const app    = express();
app.use(express.static(path.join(__dirname, 'public')));
const server = http.createServer(app);

const wss = new WebSocket.Server({ server, path: '/race' });

wss.on('connection', ws => {
  ws.isAlive = true;
  ws.on('pong', () => ws.isAlive = true);

  // najdi nebo vytvoř hru
  let game = games.find(g => !g.started && g.players.length < 2);
  if (!game) {
    game = {
      id:       games.length + 1,
      players:  [],
      state:    {},
      laps:     3,
      started:  false,
      finished: false,
      results:  {}
    };
    games.push(game);
  }

  const idx = game.players.length;
  game.players.push(ws);
  ws.gameId      = game.id;
  ws.playerIndex = idx;

  // pozdrav nového klienta
  ws.send(JSON.stringify({
    type: 'joined',
    data: { playerIndex: idx, gameId: game.id }
  }));

  // start když jsou 2 hráči
  if (game.players.length === 2) {
    game.started           = true;
    game.results.startTime = Date.now();
    game.state = {
      p0: { x:0, y:0, speed:0, lap:0, name:null, color:null, input:null },
      p1: { x:0, y:3, speed:0, lap:0, name:null, color:null, input:null }
    };
    game.players.forEach((client, i) => {
      client.send(JSON.stringify({
        type: 'start',
        data: { laps: game.laps, yourIndex: i }
      }));
    });
    game.interval = setInterval(() => gameLoop(game), 1000 / TICK_RATE);
  }

  ws.on('message', raw => {
    let m;
    try { m = JSON.parse(raw); } catch { return; }

    // hráč posílá své jméno, barvu a počet kol
    if (m.type === 'join') {
      const key = 'p' + ws.playerIndex;
      game.laps = m.laps || game.laps;
      if (game.state[key]) {
        game.state[key].name  = m.name;
        game.state[key].color = m.color;
      }
      return;
    }

    // hráč posílá pohyb → přepošli všem ostatním včetně jejich indexu
    if (m.type === 'move') {
      const payload = {
        type: 'player-move',
        data: {
          index: ws.playerIndex,
          name:  m.name,
          color: m.color,
          x:     m.x,
          y:     m.y,
          lap:   m.lap
        }
      };
      game.players.forEach(c => {
        if (c !== ws && c.readyState === WebSocket.OPEN) {
          c.send(JSON.stringify(payload));
        }
      });
      return;
    }

    // (přesunuto ze starého kódu — nepotřebné pro tuto úpravu)
    if (m.type === 'input') {
      const key = 'p' + ws.playerIndex;
      if (game.state[key]) game.state[key].input = m.data;
      return;
    }
  });

  ws.on('close', () => {
    clearInterval(game.interval);
    game.finished = true;
    const i = games.findIndex(g => g.id === game.id);
    if (i !== -1) games.splice(i,1);
  });
});

function gameLoop(game) {
  const dt = 1 / TICK_RATE;
  ['p0','p1'].forEach(key => {
    const p = game.state[key];
    if (!p.input) return;
    p.speed += p.input.throttle * dt * 5;
    p.x     += p.speed * dt;
    if (p.x >= 100) {
      p.x   -= 100;
      p.lap += 1;
      if (p.lap > game.laps && game.results[key] == null) {
        const t = (Date.now() - game.results.startTime) / 1000;
        game.results[key] = t;
        // okamžitě pošli zprávu, že tento hráč dokončil
        game.players.forEach(c => {
          if (c.readyState === WebSocket.OPEN) {
            c.send(JSON.stringify({
              type: 'playerFinish',
              data: { name: p.name, time: t }
            }));
          }
        });
      }
    }
    if (p.y < 0 || p.y > 3) {
      p.x = Math.max(0, p.x - 5);
    }
  });

  // průběžný broadcast stavu (nejsou důležité pro zobrazení soupeře, ale lze ponechat)
  const payload = { type:'state', data: game.state };
  game.players.forEach(c => {
    if (c.readyState === WebSocket.OPEN) {
      c.send(JSON.stringify(payload));
    }
  });

  // když oba dokončí, pošli finish všem
  const r0 = game.results.p0, r1 = game.results.p1;
  if (!game.finished && r0 != null && r1 != null) {
    clearInterval(game.interval);
    game.finished = true;
    const finishMsg = {
      type: 'finish',
      data: {
        players: [
          { name: game.state.p0.name, time: r0 },
          { name: game.state.p1.name, time: r1 }
        ]
      }
    };
    game.players.forEach(c => {
      if (c.readyState === WebSocket.OPEN) {
        c.send(JSON.stringify(finishMsg));
      }
    });
  }
}

setInterval(() => {
  wss.clients.forEach(ws => {
    if (!ws.isAlive) return ws.terminate();
    ws.isAlive = false;
    ws.ping();
  });
}, 30000);

server.listen(PORT, () => {
  console.log(`Server beží na http://localhost:${PORT}`);
});
