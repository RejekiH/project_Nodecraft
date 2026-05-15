'use strict';

/**
 * RoomService WebSocket Server
 * ============================
 * Menangani realtime sync langkah catur antar dua pemain.
 *
 * Integrasi dengan Laravel:
 *   - Verifikasi JWT via GET /api/internal/ws/verify-token
 *   - Submit move via POST /api/internal/gameplay/session/{id}/move
 *   - Get board state via GET /api/internal/gameplay/session/{id}/board
 *   - Resign via POST /api/internal/gameplay/session/{id}/resign
 *   - Draw via POST /api/internal/gameplay/session/{id}/draw
 *
 * Jalankan:
 *   node websocket-server.js
 *   PORT=6001 LARAVEL_URL=http://localhost:8000 node websocket-server.js
 *
 * Supervisor config (/etc/supervisor/conf.d/chess-ws.conf):
 *   [program:chess-ws]
 *   command=node /var/www/chess-app/websocket-server.js
 *   autostart=true
 *   autorestart=true
 *   stderr_logfile=/var/log/supervisor/chess-ws.err.log
 *   stdout_logfile=/var/log/supervisor/chess-ws.out.log
 */

const { Server }  = require('socket.io');
const http        = require('http');
const https       = require('https');
const { URL }     = require('url');

const PORT        = parseInt(process.env.PORT || '6001');
const LARAVEL_URL = (process.env.LARAVEL_URL || 'http://localhost:8000').replace(/\/$/, '');
const INTERNAL_KEY = process.env.INTERNAL_API_KEY || '';
const CORS_ORIGIN  = process.env.CORS_ORIGIN || '*';

// ── HTTP helper (panggil Laravel internal API) ───────────────────────────

function laravelRequest(method, path, body = null) {
  return new Promise((resolve, reject) => {
    const parsed  = new URL(LARAVEL_URL + path);
    const isHttps = parsed.protocol === 'https:';
    const lib     = isHttps ? https : http;

    const bodyStr = body ? JSON.stringify(body) : null;
    const options = {
      hostname : parsed.hostname,
      port     : parsed.port || (isHttps ? 443 : 80),
      path     : parsed.pathname + parsed.search,
      method,
      headers  : {
        'Content-Type'  : 'application/json',
        'Accept'        : 'application/json',
        'X-Internal-Key': INTERNAL_KEY,
      },
    };
    if (bodyStr) options.headers['Content-Length'] = Buffer.byteLength(bodyStr);

    const req = lib.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, body: JSON.parse(data) });
        } catch {
          resolve({ status: res.statusCode, body: data });
        }
      });
    });

    req.on('error', reject);
    if (bodyStr) req.write(bodyStr);
    req.end();
  });
}

// ── State in-memory ──────────────────────────────────────────────────────
//
// rooms[roomId] = {
//   sessionId : string,
//   players   : { socketId: { userId, color, sequenceNo } },
//   sequence  : number,          // sequence counter global per room
//   moveBuffer: [],              // buffer untuk reconnect (last 20 moves)
// }

const rooms = {};

function getOrCreateRoom(roomId) {
  if (!rooms[roomId]) {
    rooms[roomId] = { sessionId: null, players: {}, sequence: 0, moveBuffer: [] };
  }
  return rooms[roomId];
}

function getPlayerColor(room, userId) {
  for (const [, p] of Object.entries(room.players)) {
    if (p.userId === userId) return p.color;
  }
  return null;
}

function broadcastToRoom(io, roomId, event, data, excludeSocket = null) {
  for (const [sid, p] of Object.entries(rooms[roomId]?.players || {})) {
    if (sid === excludeSocket) continue;
    io.to(sid).emit(event, data);
  }
}

// ── Server setup ─────────────────────────────────────────────────────────

const httpServer = http.createServer((req, res) => {
  // Health check endpoint untuk load balancer
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', service: 'chess-websocket', rooms: Object.keys(rooms).length }));
    return;
  }
  res.writeHead(404);
  res.end('Not found');
});

const io = new Server(httpServer, {
  cors: {
    origin: CORS_ORIGIN,
    methods: ['GET', 'POST'],
    credentials: true,
  },
  pingTimeout  : 20000,
  pingInterval : 10000,
});

// ── Middleware: verifikasi JWT sebelum connect ────────────────────────────

io.use(async (socket, next) => {
  const token = socket.handshake.auth?.token || socket.handshake.headers?.authorization?.replace('Bearer ', '');

  if (!token) return next(new Error('TOKEN_REQUIRED'));

  try {
    const res = await laravelRequest('POST', '/api/internal/user/verify', { token });
    if (res.status !== 200 || !res.body?.data?.valid) {
      return next(new Error('TOKEN_INVALID'));
    }
    socket.userId   = res.body.data.user_id;
    socket.username = res.body.data.username;
    next();
  } catch (err) {
    console.error('[WS] JWT verify error:', err.message);
    next(new Error('AUTH_SERVICE_UNAVAILABLE'));
  }
});

// ── Event handlers ────────────────────────────────────────────────────────

io.on('connection', (socket) => {
  console.log(`[WS] Connected: ${socket.id} (user: ${socket.userId})`);

  // ── joinRoom ────────────────────────────────────────────────────────────
  //
  // Client kirim: { roomId, sessionId, color }
  // Server reply: stateUpdate | errorWs
  //
  socket.on('joinRoom', async ({ roomId, sessionId, color }) => {
    if (!roomId || !sessionId || !color) {
      return socket.emit('errorWs', { code: 'INVALID_PAYLOAD', message: 'roomId, sessionId, color diperlukan' });
    }

    socket.join(roomId);

    const room = getOrCreateRoom(roomId);
    room.sessionId = sessionId;
    room.players[socket.id] = { userId: socket.userId, color, sequenceNo: 0 };

    console.log(`[WS] ${socket.username} joined room ${roomId} as ${color}`);

    // Ambil state board terkini dari Laravel
    try {
      const res = await laravelRequest('GET', `/api/internal/gameplay/session/${sessionId}/board`);
      if (res.status === 200) {
        socket.emit('stateUpdate', {
          ...res.body.data,
          sequence: room.sequence,
        });
      }
    } catch (err) {
      console.error('[WS] getBoard error:', err.message);
    }

    // Beritahu lawan bahwa player sudah join
    broadcastToRoom(io, roomId, 'playerJoined', {
      userId  : socket.userId,
      username: socket.username,
      color,
    }, socket.id);
  });

  // ── move ────────────────────────────────────────────────────────────────
  //
  // Client kirim: { roomId, from, to, promotion?, timeSpentMs? }
  // Server reply semua client di room: stateUpdate | errorWs
  //
  socket.on('move', async ({ roomId, from, to, promotion = null, timeSpentMs = 0 }) => {
    const room = rooms[roomId];
    if (!room) return socket.emit('errorWs', { code: 'ROOM_NOT_FOUND', message: 'Room tidak ditemukan' });

    const playerInfo = room.players[socket.id];
    if (!playerInfo) return socket.emit('errorWs', { code: 'NOT_IN_ROOM', message: 'Anda tidak ada di room ini' });

    // Kirim move ke Laravel untuk diproses dan divalidasi
    let res;
    try {
      res = await laravelRequest('POST', `/api/internal/gameplay/session/${room.sessionId}/move`, {
        user_id      : socket.userId,
        from,
        to,
        promotion,
        time_spent_ms: timeSpentMs,
      });
    } catch (err) {
      console.error('[WS] submitMove error:', err.message);
      return socket.emit('errorWs', { code: 'SERVER_ERROR', message: 'Server error saat memproses move' });
    }

    if (res.status !== 200) {
      const errMsg = res.body?.error?.message || 'Move tidak valid';
      return socket.emit('errorWs', { code: 'MOVE_REJECTED', message: errMsg });
    }

    // Move berhasil — tambah sequence dan broadcast ke semua
    room.sequence++;
    const payload = {
      ...res.body.data.session,
      move    : res.body.data.move,
      game_over: res.body.data.game_over,
      sequence: room.sequence,
    };

    // Simpan ke move buffer untuk reconnect (max 20 move terakhir)
    room.moveBuffer.push({ sequence: room.sequence, move: res.body.data.move });
    if (room.moveBuffer.length > 20) room.moveBuffer.shift();

    // Broadcast ke semua pemain di room
    io.to(roomId).emit('stateUpdate', payload);

    // Kalau game over, bersihkan room setelah 5 detik
    if (res.body.data.game_over) {
      setTimeout(() => { delete rooms[roomId]; }, 5000);
    }
  });

  // ── reconnect ───────────────────────────────────────────────────────────
  //
  // Client kirim: { roomId, sessionId, color, lastSequence }
  // Server reply: missedMoves (move yang terlewat sejak lastSequence)
  //
  socket.on('reconnect', async ({ roomId, sessionId, color, lastSequence = 0 }) => {
    socket.join(roomId);

    const room = getOrCreateRoom(roomId);
    room.sessionId = sessionId;
    room.players[socket.id] = { userId: socket.userId, color, sequenceNo: lastSequence };

    console.log(`[WS] ${socket.username} reconnected to room ${roomId} (last seq: ${lastSequence})`);

    // Kirim move yang terlewat dari buffer
    const missed = room.moveBuffer.filter(m => m.sequence > lastSequence);

    if (missed.length > 0) {
      socket.emit('missedMoves', { moves: missed, sequence: room.sequence });
    } else {
      // Tidak ada di buffer — kirim full state terbaru
      try {
        const res = await laravelRequest('GET', `/api/internal/gameplay/session/${sessionId}/board`);
        if (res.status === 200) {
          socket.emit('stateUpdate', { ...res.body.data, sequence: room.sequence });
        }
      } catch (err) {
        console.error('[WS] reconnect getBoard error:', err.message);
      }
    }

    broadcastToRoom(io, roomId, 'playerReconnected', {
      userId  : socket.userId,
      username: socket.username,
      color,
    }, socket.id);
  });

  // ── resign ──────────────────────────────────────────────────────────────
  socket.on('resign', async ({ roomId }) => {
    const room = rooms[roomId];
    if (!room) return socket.emit('errorWs', { code: 'ROOM_NOT_FOUND', message: 'Room tidak ditemukan' });

    try {
      const res = await laravelRequest('POST', `/api/internal/gameplay/session/${room.sessionId}/resign`, {
        user_id: socket.userId,
      });

      if (res.status === 200) {
        room.sequence++;
        io.to(roomId).emit('stateUpdate', { ...res.body.data, sequence: room.sequence });
        setTimeout(() => { delete rooms[roomId]; }, 5000);
      } else {
        socket.emit('errorWs', { code: 'RESIGN_FAILED', message: res.body?.error?.message || 'Gagal resign' });
      }
    } catch (err) {
      socket.emit('errorWs', { code: 'SERVER_ERROR', message: err.message });
    }
  });

  // ── offerDraw / acceptDraw ──────────────────────────────────────────────
  socket.on('offerDraw', ({ roomId }) => {
    const room = rooms[roomId];
    if (!room) return;
    const player = room.players[socket.id];
    broadcastToRoom(io, roomId, 'drawOffered', {
      from    : socket.userId,
      username: socket.username,
      color   : player?.color,
    }, socket.id);
  });

  socket.on('acceptDraw', async ({ roomId }) => {
    const room = rooms[roomId];
    if (!room) return socket.emit('errorWs', { code: 'ROOM_NOT_FOUND', message: 'Room tidak ditemukan' });

    try {
      const res = await laravelRequest('POST', `/api/internal/gameplay/session/${room.sessionId}/draw`);
      if (res.status === 200) {
        room.sequence++;
        io.to(roomId).emit('stateUpdate', { ...res.body.data, sequence: room.sequence });
        setTimeout(() => { delete rooms[roomId]; }, 5000);
      } else {
        socket.emit('errorWs', { code: 'DRAW_FAILED', message: res.body?.error?.message || 'Gagal accept draw' });
      }
    } catch (err) {
      socket.emit('errorWs', { code: 'SERVER_ERROR', message: err.message });
    }
  });

  socket.on('declineDraw', ({ roomId }) => {
    broadcastToRoom(io, roomId, 'drawDeclined', {
      from: socket.userId, username: socket.username,
    }, socket.id);
  });

  // ── requestRematch ──────────────────────────────────────────────────────
  socket.on('requestRematch', ({ roomId }) => {
    const room = rooms[roomId];
    if (!room) return;
    broadcastToRoom(io, roomId, 'rematchRequested', {
      from: socket.userId, username: socket.username,
    }, socket.id);
  });

  socket.on('acceptRematch', ({ roomId }) => {
    broadcastToRoom(io, roomId, 'rematchAccepted', {
      from: socket.userId, username: socket.username,
    }, socket.id);
  });

  socket.on('declineRematch', ({ roomId }) => {
    broadcastToRoom(io, roomId, 'rematchDeclined', {
      from: socket.userId, username: socket.username,
    }, socket.id);
  });

  // ── chat ────────────────────────────────────────────────────────────────
  socket.on('chat', ({ roomId, message }) => {
    if (!message || typeof message !== 'string') return;
    const clean = message.trim().slice(0, 200);
    if (!clean) return;

    io.to(roomId).emit('chatMessage', {
      from     : socket.userId,
      username : socket.username,
      message  : clean,
      timestamp: Date.now(),
    });
  });

  // ── disconnect ──────────────────────────────────────────────────────────
  socket.on('disconnect', (reason) => {
    console.log(`[WS] Disconnected: ${socket.id} (${socket.userId}) — ${reason}`);

    for (const [roomId, room] of Object.entries(rooms)) {
      if (room.players[socket.id]) {
        const { color } = room.players[socket.id];
        delete room.players[socket.id];

        broadcastToRoom(io, roomId, 'playerDisconnected', {
          userId  : socket.userId,
          username: socket.username,
          color,
        });

        // Kalau room kosong hapus setelah 60 detik (toleransi reconnect)
        if (Object.keys(room.players).length === 0) {
          setTimeout(() => {
            if (rooms[roomId] && Object.keys(rooms[roomId].players).length === 0) {
              delete rooms[roomId];
              console.log(`[WS] Room ${roomId} dihapus (kosong)`);
            }
          }, 60000);
        }

        break;
      }
    }
  });
});

// ── Start ─────────────────────────────────────────────────────────────────

httpServer.listen(PORT, () => {
  console.log(`[WS] Chess WebSocket server running on port ${PORT}`);
  console.log(`[WS] Laravel URL: ${LARAVEL_URL}`);
  console.log(`[WS] CORS origin: ${CORS_ORIGIN}`);
});
