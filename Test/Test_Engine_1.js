import { ChessEngine } from "./ChessEngine.js";

const engine = new ChessEngine();

// cek state awal
console.log("Initial State:", engine.getState());

// coba langkah valid
const move1 = { from: "e2", to: "e4" };
console.log("Valid Move?", engine.validateMove(move1));

engine.applyMove(move1);
console.log("After Move:", engine.getState());

// langkah illegal
const move2 = { from: "e2", to: "e5" };
console.log("Invalid Move?", engine.validateMove(move2));

// cek status game
console.log("Game Status:", engine.getStatus());