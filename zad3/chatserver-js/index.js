// index.js (server)
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
  // heartbeat
  ws.isAlive = true;
  ws.on('pong', () => ws.isAlive = true);

  // find or create a waiting game
  let game = games.find(g => !g.started && g.players.length < 2);
  if (!game) {
    game = {
      id:       games.length + 1,
      players:  [],
      state:    {},
      laps:     3,     // default laps
      started:  false,
      finished: false,
      results:  { p0: null, p1: null },
      interval: null
    };
    games.push(game);
  }

  // assign this socket a player slot
  const idx = game.players.length;
  ws.playerIndex = idx;
  ws.gameId      = game.id;
  game.players.push(ws);
  game.state['p' + idx] = { name: null, color: null };

  // tell the client its index
  ws.send(JSON.stringify({
    type: 'joined',
    data: { playerIndex: idx, gameId: game.id }
  }));

  ws.on('message', raw => {
    let m;
    try { m = JSON.parse(raw); } catch { return; }

    // handle join: name, color, and laps from first player only
    if (m.type === 'join') {
      const key = 'p' + ws.playerIndex;
      game.state[key].name  = m.name;
      game.state[key].color = m.color;
      if (ws.playerIndex === 0) {
        // only first player’s laps selection is used
        game.laps = m.laps || game.laps;
      }

      // when both players are joined & named, start the race
      if (!game.started
          && game.players.length === 2
          && game.state.p0.name
          && game.state.p1.name) {
        game.started           = true;
        game.results.startTime = Date.now();

        // broadcast start to both clients
        game.players.forEach((c, i) => {
          c.send(JSON.stringify({
            type: 'start',
            data: {
              laps:      game.laps,
              yourIndex: i,
              players: [
                { name: game.state.p0.name, color: game.state.p0.color },
                { name: game.state.p1.name, color: game.state.p1.color }
              ]
            }
          }));
        });

        // begin game loop
        game.interval = setInterval(() => gameLoop(game), 1000 / TICK_RATE);
      }
      return;
    }

    // relay movement to the other client
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

    // handle finish from client
    if (m.type === 'finish') {
      game.results['p' + ws.playerIndex] = m.time;

      // inform both players about this finish
      game.players.forEach(c => {
        if (c.readyState === WebSocket.OPEN) {
          c.send(JSON.stringify({
            type: 'playerFinish',
            data: { name: m.name, time: m.time }
          }));
        }
      });

      // if both finished, send the overall finish
      const r0 = game.results.p0, r1 = game.results.p1;
      if (!game.finished && r0 != null && r1 != null) {
        game.finished = true;
        clearInterval(game.interval);
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
      return;
    }
  });

  ws.on('close', () => {
    // clean up: remove this socket from its game
    const g = games.find(g => g.id === ws.gameId);
    if (!g) return;
    g.players = g.players.filter(c => c !== ws);

    if (g.players.length === 0) {
      // no players left, delete the game
      if (g.interval) clearInterval(g.interval);
      const i = games.findIndex(x => x.id === g.id);
      if (i !== -1) games.splice(i, 1);
    } else {
      // one player left: stop the loop
      g.finished = true;
      if (g.interval) clearInterval(g.interval);
    }
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

  // optional broadcast of full state
  const payload = { type: 'state', data: game.state };
  game.players.forEach(c => {
    if (c.readyState === WebSocket.OPEN) {
      c.send(JSON.stringify(payload));
    }
  });

  // final finish check
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

// heartbeat ping/pong
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
