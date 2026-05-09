/**
 * NodeChess Engine - Test Suite
 * ==============================
 * Fase 1: Validasi Chess Engine
 * Jalankan: node ChessEngineTest.js
 */

'use strict';

const {
  ChessEngine, PIECES, COLORS, GAME_STATUS, INITIAL_FEN,
  indexToAlg, algToIndex,
} = require('../Engine/ChessEngine');

// ─────────────────────────────────────────────
// TEST RUNNER SEDERHANA
// ─────────────────────────────────────────────

let passed = 0;
let failed = 0;
const results = [];

function test(name, fn) {
  try {
    fn();
    passed++;
    results.push({ status: '✅', name });
  } catch (e) {
    failed++;
    results.push({ status: '❌', name, error: e.message });
  }
}

function assert(condition, msg) {
  if (!condition) throw new Error(msg || 'Assertion gagal');
}

function assertEqual(a, b, msg) {
  if (a !== b) throw new Error(msg || `Expected ${b}, got ${a}`);
}

// ─────────────────────────────────────────────
// TEST GROUP 1: Inisialisasi & FEN
// ─────────────────────────────────────────────

test('Inisialisasi posisi awal dari FEN standar', () => {
  const engine = new ChessEngine();
  assertEqual(engine.board[0],  -4, 'a8 harus Black Rook');
  assertEqual(engine.board[4],  -6, 'e8 harus Black King');
  assertEqual(engine.board[60],  6, 'e1 harus White King');
  assertEqual(engine.board[63],  4, 'h1 harus White Rook');
  assertEqual(engine.turn, COLORS.WHITE, 'Giliran awal harus Putih');
  assertEqual(engine.status, GAME_STATUS.ACTIVE, 'Status awal harus ACTIVE');
});

test('Generate FEN dari posisi awal harus sama dengan input', () => {
  const engine = new ChessEngine(INITIAL_FEN);
  assertEqual(engine.getFEN(), INITIAL_FEN, 'FEN harus identik');
});

test('Load FEN posisi custom', () => {
  const fen = 'r1bqkb1r/pppp1ppp/2n2n2/4p3/2B1P3/5N2/PPPP1PPP/RNBQK2R w KQkq - 4 4';
  const engine = new ChessEngine(fen);
  assertEqual(engine.getFEN(), fen, 'FEN custom harus di-load dengan benar');
});

test('Konversi index <-> algebraic notation', () => {
  assertEqual(indexToAlg(0),  'a8', 'Index 0 harus a8');
  assertEqual(indexToAlg(63), 'h1', 'Index 63 harus h1');
  assertEqual(indexToAlg(36), 'e4', 'Index 36 harus e4');
  assertEqual(algToIndex('a8'),  0,  'a8 harus index 0');
  assertEqual(algToIndex('e4'),  36, 'e4 harus index 36');
});

// ─────────────────────────────────────────────
// TEST GROUP 2: Legal Moves - Posisi Awal
// ─────────────────────────────────────────────

test('Jumlah legal moves di posisi awal harus 20', () => {
  const engine = new ChessEngine();
  const moves  = engine.getLegalMoves();
  assertEqual(moves.length, 20, `Harus 20 moves, dapat ${moves.length}`);
});

test('Langkah e2-e4 harus legal dari posisi awal', () => {
  const engine = new ChessEngine();
  const result = engine.makeMove('e2e4');
  assert(result.success, 'e2e4 harus berhasil');
  assertEqual(result.move.from, 'e2', 'From harus e2');
  assertEqual(result.move.to,   'e4', 'To harus e4');
});

test('Setelah e2e4, giliran harus berganti ke Hitam', () => {
  const engine = new ChessEngine();
  engine.makeMove('e2e4');
  assertEqual(engine.turn, COLORS.BLACK, 'Giliran harus Hitam');
});

test('Langkah tidak legal harus ditolak', () => {
  const engine = new ChessEngine();
  const result = engine.makeMove('e2e5'); // Pion tidak bisa maju 3
  assert(!result.success, 'e2e5 harus ditolak');
  assert(result.error, 'Harus ada pesan error');
});

test('Langkah pion hitam e7e5 harus legal', () => {
  const engine = new ChessEngine();
  engine.makeMove('e2e4');
  const result = engine.makeMove('e7e5');
  assert(result.success, 'e7e5 harus berhasil');
});

// ─────────────────────────────────────────────
// TEST GROUP 3: Castling
// ─────────────────────────────────────────────

test('Castling kingside putih (O-O) harus berfungsi', () => {
  // Bersihkan antara king dan rook
  const fen = 'rnbqk2r/pppp1ppp/5n2/2b1p3/2B1P3/5N2/PPPP1PPP/RNBQK2R w KQkq - 4 4';
  const engine = new ChessEngine(fen);
  const result = engine.makeMove('e1g1');
  assert(result.success, 'Castling kingside harus berhasil');
  assertEqual(result.move.castle, 'K', 'Harus castling kingside');
  assertEqual(engine.board[62], 6,  'King harus di g1 (index 62)');
  assertEqual(engine.board[61], 4,  'Rook harus di f1 (index 61)');
});

test('Castling queenside putih (O-O-O) harus berfungsi', () => {
  const fen = 'r3kbnr/pppqpppp/2np4/4b3/4P3/3B1N2/PPPPQPPP/RNB1K2R w KQkq - 2 7';
  const engine = new ChessEngine(fen);
  // Bersihkan queenside
  const fen2 = 'r3kbnr/ppp1pppp/2np4/4b3/4P3/2NB1N2/PPPBQPPP/R3K2R w KQkq - 2 7';
  const engine2 = new ChessEngine(fen2);
  const result = engine2.makeMove('e1c1');
  assert(result.success, 'Castling queenside harus berhasil');
  assertEqual(result.move.castle, 'Q', 'Harus castling queenside');
});

test('Castling hak hilang setelah raja bergerak', () => {
  const fen = 'rnbqk2r/pppp1ppp/5n2/4p3/4P3/5N2/PPPP1PPP/RNBQK2R w KQkq - 2 4';
  const engine = new ChessEngine(fen);
  engine.makeMove('e1e2'); // Raja maju
  assert(!engine.castlingRights.K, 'Hak kingside harus hilang');
  assert(!engine.castlingRights.Q, 'Hak queenside harus hilang');
});

// ─────────────────────────────────────────────
// TEST GROUP 4: En Passant
// ─────────────────────────────────────────────

test('En passant harus terdeteksi setelah double push', () => {
  const engine = new ChessEngine();
  engine.makeMove('e2e4');
  engine.makeMove('a7a6');
  engine.makeMove('e4e5');
  engine.makeMove('d7d5'); // Double push → en passant tersedia
  assert(engine.enPassantTarget !== null, 'En passant target harus ada');
  const result = engine.makeMove('e5d6'); // En passant capture
  assert(result.success, 'En passant harus berhasil');
  assert(result.move.enPassant, 'Harus flag enPassant');
});

// ─────────────────────────────────────────────
// TEST GROUP 5: Promosi
// ─────────────────────────────────────────────

test('Promosi pion ke queen harus berfungsi', () => {
  const fen = '8/P7/8/8/8/8/8/4K2k w - - 0 1';
  const engine = new ChessEngine(fen);
  const result = engine.makeMove('a7a8q');
  assert(result.success, 'Promosi ke queen harus berhasil');
  assertEqual(result.move.promotion, 'Q', 'Harus promosi ke Queen');
  assertEqual(engine.board[0], 5, 'Kotak a8 harus berisi White Queen');
});

test('Promosi pion ke rook harus berfungsi', () => {
  const fen = '8/P7/8/8/8/8/8/4K2k w - - 0 1';
  const engine = new ChessEngine(fen);
  const result = engine.makeMove('a7a8r');
  assert(result.success, 'Promosi ke rook harus berhasil');
  assertEqual(engine.board[0], 4, 'Kotak a8 harus berisi White Rook');
});

// ─────────────────────────────────────────────
// TEST GROUP 6: Check, Checkmate, Stalemate
// ─────────────────────────────────────────────

test('Deteksi check harus berfungsi', () => {
  const fen = 'rnbqkbnr/ppp2ppp/3p4/1B2p3/4P3/5N2/PPPP1PPP/RNBQK2R b KQkq - 1 3';
  const engine = new ChessEngine(fen);
  assertEqual(engine.status, GAME_STATUS.CHECK, 'Harus deteksi CHECK');
});

test('Scholar\'s mate (checkmate dalam 4 langkah)', () => {
  const engine = new ChessEngine();
  engine.makeMove('e2e4');
  engine.makeMove('e7e5');
  engine.makeMove('f1c4');
  engine.makeMove('b8c6');
  engine.makeMove('d1h5');
  engine.makeMove('a7a6');
  engine.makeMove('h5f7');
  assertEqual(engine.status, GAME_STATUS.CHECKMATE, 'Harus CHECKMATE (Scholar\'s mate)');
});

test('Fool\'s mate (checkmate paling cepat)', () => {
  const engine = new ChessEngine();
  engine.makeMove('f2f3');
  engine.makeMove('e7e5');
  engine.makeMove('g2g4');
  engine.makeMove('d8h4');
  assertEqual(engine.status, GAME_STATUS.CHECKMATE, 'Harus CHECKMATE (Fool\'s mate)');
});

test('Stalemate harus terdeteksi', () => {
  // Posisi stalemate klasik
  const fen = '5k2/5P2/5K2/8/8/8/8/8 b - - 0 1';
  const engine = new ChessEngine(fen);
  assertEqual(engine.getLegalMoves().length, 0, 'Harus 0 legal moves');
  engine._updateStatus();
  assertEqual(engine.status, GAME_STATUS.STALEMATE, 'Harus STALEMATE');
});

// ─────────────────────────────────────────────
// TEST GROUP 7: Draw Conditions
// ─────────────────────────────────────────────

test('Fifty-move rule harus trigger draw', () => {
  const engine = new ChessEngine();
  engine.halfMoveClock = 100;
  engine._updateStatus();
  assertEqual(engine.status, GAME_STATUS.DRAW, 'Harus DRAW (50-move rule)');
});

test('Insufficient material (Raja vs Raja) harus draw', () => {
  const fen = '4k3/8/8/8/8/8/8/4K3 w - - 0 1';
  const engine = new ChessEngine(fen);
  engine._updateStatus();
  assertEqual(engine.status, GAME_STATUS.DRAW, 'Harus DRAW (insufficient material)');
});

// ─────────────────────────────────────────────
// TEST GROUP 8: State & History (MongoDB Ready)
// ─────────────────────────────────────────────

test('getState() harus mengembalikan objek lengkap', () => {
  const engine = new ChessEngine();
  engine.makeMove('e2e4');
  const state = engine.getState();

  assert(state.fen,            'State harus punya FEN');
  assert(state.board,          'State harus punya board');
  assert(state.turn,           'State harus punya turn');
  assert(state.status,         'State harus punya status');
  assert(state.castlingRights, 'State harus punya castlingRights');
});

test('Move history harus terekam untuk replay', () => {
  const engine = new ChessEngine();
  engine.makeMove('e2e4');
  engine.makeMove('e7e5');
  engine.makeMove('g1f3');

  const history = engine.getMoveHistory();
  assertEqual(history.length, 3, 'Harus 3 langkah');
  assert(history[0].fenAfter, 'Setiap langkah harus punya fenAfter (untuk recovery)');
  assert(history[0].timestamp, 'Setiap langkah harus punya timestamp');
});

test('getBoard2D() harus mengembalikan array 8x8', () => {
  const engine = new ChessEngine();
  const board2D = engine.getBoard2D();
  assertEqual(board2D.length, 8, 'Harus 8 baris');
  assertEqual(board2D[0].length, 8, 'Harus 8 kolom');
});

test('getHighlightSquares() untuk pion e2 harus mengembalikan e3 dan e4', () => {
  const engine  = new ChessEngine();
  const e2Idx   = algToIndex('e2'); // 52
  const squares = engine.getHighlightSquares(e2Idx);
  const algs    = squares.map(indexToAlg).sort();
  assert(algs.includes('e3'), 'e3 harus bisa dijangkau');
  assert(algs.includes('e4'), 'e4 harus bisa dijangkau');
  assertEqual(algs.length, 2, 'Pion e2 hanya bisa ke e3 dan e4');
});

test('FEN harus bisa digunakan untuk recovery (load ulang)', () => {
  const engine = new ChessEngine();
  engine.makeMove('e2e4');
  engine.makeMove('e7e5');
  engine.makeMove('g1f3');

  const midFen = engine.getFEN();

  // Simulasikan recovery: load ulang dari FEN yang tersimpan
  const recovered = new ChessEngine(midFen);
  assertEqual(recovered.getFEN(), midFen, 'Recovery FEN harus identik');
  assertEqual(recovered.turn, engine.turn, 'Turn harus sama setelah recovery');
});

// ─────────────────────────────────────────────
// TEST GROUP 9: Resign & Draw Declaration
// ─────────────────────────────────────────────

test('Resign harus mengubah status menjadi RESIGNED', () => {
  const engine = new ChessEngine();
  const result = engine.resign(COLORS.WHITE);
  assert(result.success, 'Resign harus sukses');
  assertEqual(engine.status, GAME_STATUS.RESIGNED, 'Status harus RESIGNED');
});

test('Declare draw harus mengubah status menjadi DRAW', () => {
  const engine = new ChessEngine();
  const result = engine.declareDraw();
  assert(result.success, 'Declare draw harus sukses');
  assertEqual(engine.status, GAME_STATUS.DRAW, 'Status harus DRAW');
});

test('Tidak bisa membuat langkah setelah permainan berakhir', () => {
  const engine = new ChessEngine();
  engine.resign(COLORS.WHITE);
  const result = engine.makeMove('e2e4');
  assert(!result.success, 'Langkah setelah resign harus ditolak');
});

// ─────────────────────────────────────────────
// TEST GROUP 10: WebSocket State Sync Simulation
// ─────────────────────────────────────────────

test('State WebSocket payload harus serializable (JSON-safe)', () => {
  const engine = new ChessEngine();
  engine.makeMove('e2e4');
  const state = engine.getState();
  const json  = JSON.stringify(state);
  const parsed = JSON.parse(json);
  assertEqual(parsed.turn, COLORS.WHITE === COLORS.BLACK ? COLORS.BLACK : COLORS.BLACK,
    'JSON parse harus bisa dikonsumsi ulang');
});

test('Full game sequence Ruy Lopez opening tanpa error', () => {
  const engine = new ChessEngine();
  const moves = ['e2e4','e7e5','g1f3','b8c6','f1b5','a7a6','b5a4','g8f6'];
  for (const m of moves) {
    const r = engine.makeMove(m);
    assert(r.success, `Langkah ${m} harus berhasil: ${r.error}`);
  }
  assertEqual(engine.moveHistory.length, 8, 'Harus 8 langkah terekam');
});

// ─────────────────────────────────────────────
// HASIL TEST
// ─────────────────────────────────────────────

console.log('\n═══════════════════════════════════════════════');
console.log('       NODECHESS ENGINE - TEST SUITE FASE 1     ');
console.log('═══════════════════════════════════════════════\n');

for (const r of results) {
  const err = r.error ? ` → ${r.error}` : '';
  console.log(`  ${r.status}  ${r.name}${err}`);
}

console.log('\n───────────────────────────────────────────────');
console.log(`  Total  : ${passed + failed} test`);
console.log(`  Lulus  : ${passed} ✅`);
console.log(`  Gagal  : ${failed} ❌`);
console.log('───────────────────────────────────────────────\n');

if (failed > 0) {
  process.exit(1);
} else {
  console.log('  🎉 Semua test lulus! Engine siap untuk Fase 2.\n');
}
