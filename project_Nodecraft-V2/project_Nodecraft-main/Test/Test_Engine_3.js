const engine = new ChessEngine();

engine.applyMove({ from: "e2", to: "e4" });

const savedState = engine.getState();

// simulasi server mati
const newEngine = new ChessEngine();
newEngine.loadState(savedState.fen);

console.log("Recovered State:", newEngine.getState());