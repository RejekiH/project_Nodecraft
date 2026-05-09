const engine = new ChessEngine();

const moves = [
  { from: "e2", to: "e4" },
  { from: "e7", to: "e5" },
  { from: "g1", to: "f3" }
];

// apply semua move
moves.forEach(move => engine.applyMove(move));

const state1 = engine.getState();

// rebuild dari awal
const engine2 = new ChessEngine();

moves.forEach(move => engine2.applyMove(move));

const state2 = engine2.getState();

console.log("State Consistent?", JSON.stringify(state1) === JSON.stringify(state2));