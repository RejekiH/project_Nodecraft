const io = require("socket.io-client");
const readline = require("readline");

const gameId = "game-1";

const player = process.argv[2] || "white"; // jalankan: node simulate.js white

const socket = io("http://localhost:3000");

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

// CONNECT
socket.on("connect", () => {
  console.log(`Connected sebagai ${player}`);
  socket.emit("joinGame", { gameId });
});

// STATE
socket.on("stateUpdate", (state) => {
  console.log("\nSTATE:");
  console.log(state.fen);
});

// ERROR
socket.on("errorMove", (err) => {
  console.log("❌ ERROR:", err);
});

// VALIDASI INPUT
function isValidMove(move) {
  return /^[a-h][1-8][a-h][1-8]$/.test(move);
}

// INPUT LOOP
function askMove() {
  rl.question("Masukkan langkah (e2e4): ", (move) => {
    if (!isValidMove(move)) {
      console.log("❌ Format salah");
      return askMove();
    }

    socket.emit("move", { gameId, move });
    askMove();
  });
}

askMove();