const player1 = new ChessEngine();
const player2 = new ChessEngine();

const move = { from: "e2", to: "e4" };

player1.applyMove(move);
player2.applyMove(move);

console.log(
  "Sync?",
  JSON.stringify(player1.getState()) === JSON.stringify(player2.getState())
);