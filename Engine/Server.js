const { Server } = require("socket.io");
const { ChessEngine } = require("../../Engine/ChessEngine");

const io = new Server(3000, {
  cors: { origin: "*" }
});

const games = {}; // gameId → GameSession

io.on("connection", (socket) => {
  console.log("Client connected:", socket.id);

  // JOIN GAME
  socket.on("joinGame", ({ gameId }) => {
    socket.join(gameId);

    if (!games[gameId]) {
      games[gameId] = new GameSession(gameId);
    }

    const game = games[gameId];

    // assign warna (maks 2 player)
    if (!Object.values(game.players).includes("white")) {
      game.players[socket.id] = "white";
      console.log(socket.id, "=> WHITE");
    } else if (!Object.values(game.players).includes("black")) {
      game.players[socket.id] = "black";
      console.log(socket.id, "=> BLACK");
    } else {
      socket.emit("errorMove", "Room penuh");
      return;
    }

    // kirim state awal
    socket.emit("stateUpdate", game.engine.getState());
  });

  // MOVE
  socket.on("move", async ({ gameId, move }) => {
    const game = games[gameId];
    if (!game) return;

    const result = await game.processMove(socket, move, io);

    if (!result.success) {
      socket.emit("errorMove", result.error);
    }
  });

  // DISCONNECT
  socket.on("disconnect", () => {
    console.log("Disconnected:", socket.id);

    for (const gameId in games) {
      const game = games[gameId];

      if (game.players[socket.id]) {
        console.log("Remove player:", socket.id);
        delete game.players[socket.id];
      }
    }
  });
});


// ===============================
// GAME SESSION (LOCK + QUEUE)
// ===============================
class GameSession {
  constructor(gameId) {
    this.gameId = gameId;
    this.engine = new ChessEngine();

    this.locked = false;
    this.queue = [];

    this.players = {}; // socket.id → "white" / "black"
  }

  async processMove(socket, move, io) {
    return new Promise((resolve) => {
      this.queue.push({ socket, move, resolve });
      this.runQueue(io);
    });
  }

  runQueue(io) {
    if (this.locked) return;
    if (this.queue.length === 0) return;

    this.locked = true;

    const { socket, move, resolve } = this.queue.shift();

    try {
      const result = this.executeMove(socket, move, io);
      resolve(result);
    } catch (err) {
      resolve({ success: false, error: err.message });
    }

    this.locked = false;

    setImmediate(() => this.runQueue(io));
  }

  executeMove(socket, move, io) {
    const playerColor = this.players[socket.id];

    console.log("\n=== MOVE DEBUG ===");
    console.log("Input:", move);
    console.log("Player:", socket.id);
    console.log("Color:", playerColor);
    console.log("Turn:", this.engine.turn);

    if (!playerColor) {
      throw new Error("Player tidak terdaftar");
    }

    if (playerColor !== this.engine.turn) {
      throw new Error("Bukan giliran anda");
    }

    // 🔥 PENTING: kirim move TANPA diubah
    const result = this.engine.makeMove(move);

    if (!result.success) {
      throw new Error(result.error);
    }

    const state = this.engine.getState();

    // broadcast ke semua player
    io.to(this.gameId).emit("stateUpdate", state);

    return { success: true };
  }
}