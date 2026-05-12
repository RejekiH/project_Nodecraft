/**
 * NodeChess Engine - Core Chess Logic
 * =====================================
 * Fase 1: Chess Engine
 * Compatible dengan: Laravel Backend (Fase 2), MongoDB (Fase 3)
 *
 * Fitur:
 * - Representasi papan 8x8 (array 64 elemen)
 * - Validasi langkah semua bidak (termasuk en passant, castling, promosi)
 * - Deteksi check, checkmate, stalemate, draw
 * - Generate FEN string (untuk disimpan ke MongoDB)
 * - Move history (untuk database recovery & replay)
 * - State synchronization ready (untuk WebSocket Fase 2)
 */

'use strict';

// ─────────────────────────────────────────────
// KONSTANTA BIDAK
// ─────────────────────────────────────────────
const PIECES = {
  EMPTY:  0,
  // Putih (positif)
  W_PAWN:   1,
  W_KNIGHT: 2,
  W_BISHOP: 3,
  W_ROOK:   4,
  W_QUEEN:  5,
  W_KING:   6,
  // Hitam (negatif)
  B_PAWN:  -1,
  B_KNIGHT:-2,
  B_BISHOP:-3,
  B_ROOK:  -4,
  B_QUEEN: -5,
  B_KING:  -6,
};

const COLORS = { WHITE: 'white', BLACK: 'black' };

const PIECE_SYMBOLS = {
  '1':  'P', '2':  'N', '3':  'B', '4':  'R', '5':  'Q', '6':  'K',
  '-1': 'p', '-2': 'n', '-3': 'b', '-4': 'r', '-5': 'q', '-6': 'k',
  '0':  '.',
};

const FEN_PIECE_MAP = {
  'P': 1, 'N': 2, 'B': 3, 'R': 4, 'Q': 5, 'K': 6,
  'p':-1, 'n':-2, 'b':-3, 'r':-4, 'q':-5, 'k':-6,
};

const GAME_STATUS = {
  ACTIVE:     'active',
  CHECK:      'check',
  CHECKMATE:  'checkmate',
  STALEMATE:  'stalemate',
  DRAW:       'draw',
  RESIGNED:   'resigned',
  TIMEOUT:    'timeout',
  PAUSED:     'paused',   // disconnect handling
};

// FEN posisi awal standar
const INITIAL_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

// ─────────────────────────────────────────────
// UTILITAS KOORDINAT
// ─────────────────────────────────────────────

/** index 0..63 → { row: 0..7, col: 0..7 } (row 0 = rank 8) */
const indexToCoord = (idx) => ({ row: Math.floor(idx / 8), col: idx % 8 });

/** { row, col } → index 0..63 */
const coordToIndex = (row, col) => row * 8 + col;

/** index → algebraic notation (e.g. 0 → "a8", 63 → "h1") */
const indexToAlg = (idx) => {
  const { row, col } = indexToCoord(idx);
  return String.fromCharCode(97 + col) + (8 - row);
};

/** algebraic → index (e.g. "e4" → 36) */
const algToIndex = (alg) => {
  const col = alg.charCodeAt(0) - 97;
  const row = 8 - parseInt(alg[1]);
  return coordToIndex(row, col);
};

const isValidCoord = (row, col) => row >= 0 && row < 8 && col >= 0 && col < 8;

const isWhitePiece = (p) => p > 0;
const isBlackPiece = (p) => p < 0;
const isEmpty      = (p) => p === 0;
const sameColor    = (a, b) => (a > 0 && b > 0) || (a < 0 && b < 0);
const pieceColor   = (p) => p > 0 ? COLORS.WHITE : p < 0 ? COLORS.BLACK : null;
const absPiece     = (p) => Math.abs(p);

// ─────────────────────────────────────────────
// KELAS UTAMA: ChessEngine
// ─────────────────────────────────────────────

class ChessEngine {
  constructor(fen = INITIAL_FEN) {
    // State papan (array 64, index 0 = a8, index 63 = h1)
    this.board = new Array(64).fill(PIECES.EMPTY);

    // Giliran saat ini
    this.turn = COLORS.WHITE;

    // Hak castling: { K: bool, Q: bool, k: bool, q: bool }
    this.castlingRights = { K: false, Q: false, k: false, q: false };

    // Kotak en passant target (index atau null)
    this.enPassantTarget = null;

    // Halfmove clock (untuk aturan 50 langkah)
    this.halfMoveClock = 0;

    // Fullmove number
    this.fullMoveNumber = 1;

    // Riwayat langkah (untuk recovery & replay)
    this.moveHistory = [];

    // Riwayat posisi FEN (untuk deteksi repetisi)
    this.positionHistory = [];

    // Status permainan
    this.status = GAME_STATUS.ACTIVE;

    // Load posisi dari FEN
    this.loadFEN(fen);
  }

  //helper
    _hasRookAt(idx, piece) {
      return this.board[idx] === piece;
  }
  
  // ───────────────────────────────────────────
  // FEN PARSER & GENERATOR
  // ───────────────────────────────────────────

  /**
   * Parse FEN string dan set state engine
   * FEN: "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1"
   */
  loadFEN(fen) {
    const parts = fen.trim().split(/\s+/);
    if (parts.length < 4) throw new Error(`FEN tidak valid: ${fen}`);

    const [piecePlacement, activeColor, castling, enPassant, halfMove, fullMove] = parts;

    // Parse piece placement
    this.board.fill(PIECES.EMPTY);
    let idx = 0;
    for (const ch of piecePlacement) {
      if (ch === '/') continue;
      if (ch >= '1' && ch <= '8') {
        idx += parseInt(ch);
      } else {
        if (!(ch in FEN_PIECE_MAP)) throw new Error(`Karakter FEN tidak dikenal: ${ch}`);
        this.board[idx++] = FEN_PIECE_MAP[ch];
      }
    }

    // Giliran
    this.turn = activeColor === 'w' ? COLORS.WHITE : COLORS.BLACK;

    // Castling rights
    this.castlingRights = {
      K: castling.includes('K'),
      Q: castling.includes('Q'),
      k: castling.includes('k'),
      q: castling.includes('q'),
    };

    // En passant
    this.enPassantTarget = (enPassant && enPassant !== '-') ? algToIndex(enPassant) : null;

    // Clocks
    this.halfMoveClock  = halfMove  ? parseInt(halfMove)  : 0;
    this.fullMoveNumber = fullMove  ? parseInt(fullMove)  : 1;

    // Reset history
    this.moveHistory    = [];
    this.positionHistory = [this.getFEN()];
    this.status = GAME_STATUS.ACTIVE;
    this._updateStatus();
  }

  /**
   * Generate FEN string dari state saat ini
   * Digunakan untuk: MongoDB storage, state sync, recovery
   */
  getFEN() {
    let fen = '';

    // Piece placement
    for (let row = 0; row < 8; row++) {
      let empty = 0;
      for (let col = 0; col < 8; col++) {
        const piece = this.board[coordToIndex(row, col)];
        if (piece === PIECES.EMPTY) {
          empty++;
        } else {
          if (empty > 0) { fen += empty; empty = 0; }
          fen += PIECE_SYMBOLS[piece];
        }
      }
      if (empty > 0) fen += empty;
      if (row < 7) fen += '/';
    }

    // Active color
    fen += ' ' + (this.turn === COLORS.WHITE ? 'w' : 'b');

    // Castling
    const castle = [
      this.castlingRights.K ? 'K' : '',
      this.castlingRights.Q ? 'Q' : '',
      this.castlingRights.k ? 'k' : '',
      this.castlingRights.q ? 'q' : '',
    ].join('') || '-';
    fen += ' ' + castle;

    // En passant
    fen += ' ' + (this.enPassantTarget !== null ? indexToAlg(this.enPassantTarget) : '-');

    // Clocks
    fen += ' ' + this.halfMoveClock;
    fen += ' ' + this.fullMoveNumber;

    return fen;
  }

  // ───────────────────────────────────────────
  // LEGAL MOVE GENERATION
  // ───────────────────────────────────────────

  /**
   * Generate semua pseudo-legal moves untuk piece di index
   * (belum mempertimbangkan apakah raja dalam check setelah bergerak)
   */
  _pseudoMoves(fromIdx) {
    const piece = this.board[fromIdx];
    if (piece === PIECES.EMPTY) return [];

    const { row, col } = indexToCoord(fromIdx);
    const color = pieceColor(piece);
    const type  = absPiece(piece);
    const moves = [];

    const addMove = (toRow, toCol, flags = {}) => {
      if (!isValidCoord(toRow, toCol)) return;
      const toIdx = coordToIndex(toRow, toCol);
      const target = this.board[toIdx];
      if (sameColor(piece, target)) return;
      moves.push({ from: fromIdx, to: toIdx, piece, captured: target, ...flags });
    };

    switch (type) {
      // ── PAWN ──
      case 1: {
        const dir   = color === COLORS.WHITE ? -1 : 1;
        const start = color === COLORS.WHITE ?  6 : 1;
        const promRow = color === COLORS.WHITE ? 0 : 7;

        // Langkah maju 1
        const oneStep = coordToIndex(row + dir, col);
        if (isValidCoord(row + dir, col) && isEmpty(this.board[oneStep])) {
          const toRow = row + dir;
          if (toRow === promRow) {
            // Promosi
            for (const promo of [5, 4, 3, 2]) { // Q, R, B, N
              moves.push({ from: fromIdx, to: oneStep, piece, captured: 0,
                           promotion: color === COLORS.WHITE ? promo : -promo });
            }
          } else {
            moves.push({ from: fromIdx, to: oneStep, piece, captured: 0 });
          }

          // Langkah maju 2 dari posisi awal
          if (row === start) {
            const twoStep = coordToIndex(row + dir * 2, col);
            if (isEmpty(this.board[twoStep])) {
              moves.push({ from: fromIdx, to: twoStep, piece, captured: 0, doublePush: true });
            }
          }
        }

        // Capture diagonal
        for (const dc of [-1, 1]) {
          if (!isValidCoord(row + dir, col + dc)) continue;
          const toIdx = coordToIndex(row + dir, col + dc);
          const target = this.board[toIdx];
          const toRow  = row + dir;

          if (!isEmpty(target) && !sameColor(piece, target)) {
            if (toRow === promRow) {
              for (const promo of [5, 4, 3, 2]) {
                moves.push({ from: fromIdx, to: toIdx, piece, captured: target,
                             promotion: color === COLORS.WHITE ? promo : -promo });
              }
            } else {
              moves.push({ from: fromIdx, to: toIdx, piece, captured: target });
            }
          }

          // En passant
          if (this.enPassantTarget === toIdx) {
            const epCapIdx = coordToIndex(row, col + dc); // posisi pion yang ditangkap
            moves.push({ from: fromIdx, to: toIdx, piece, captured: this.board[epCapIdx],
                         enPassant: true, epCapture: epCapIdx });
          }
        }
        break;
      }

      // ── KNIGHT ──
      case 2: {
        for (const [dr, dc] of [[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]]) {
          addMove(row + dr, col + dc);
        }
        break;
      }

      // ── BISHOP ──
      case 3: {
        for (const [dr, dc] of [[-1,-1],[-1,1],[1,-1],[1,1]]) {
          for (let i = 1; i < 8; i++) {
            const nr = row + dr * i, nc = col + dc * i;
            if (!isValidCoord(nr, nc)) break;
            const toIdx  = coordToIndex(nr, nc);
            const target = this.board[toIdx];
            if (sameColor(piece, target)) break;
            moves.push({ from: fromIdx, to: toIdx, piece, captured: target });
            if (!isEmpty(target)) break;
          }
        }
        break;
      }

      // ── ROOK ──
      case 4: {
        for (const [dr, dc] of [[-1,0],[1,0],[0,-1],[0,1]]) {
          for (let i = 1; i < 8; i++) {
            const nr = row + dr * i, nc = col + dc * i;
            if (!isValidCoord(nr, nc)) break;
            const toIdx  = coordToIndex(nr, nc);
            const target = this.board[toIdx];
            if (sameColor(piece, target)) break;
            moves.push({ from: fromIdx, to: toIdx, piece, captured: target });
            if (!isEmpty(target)) break;
          }
        }
        break;
      }

      // ── QUEEN ──
      case 5: {
        for (const [dr, dc] of [[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]]) {
          for (let i = 1; i < 8; i++) {
            const nr = row + dr * i, nc = col + dc * i;
            if (!isValidCoord(nr, nc)) break;
            const toIdx  = coordToIndex(nr, nc);
            const target = this.board[toIdx];
            if (sameColor(piece, target)) break;
            moves.push({ from: fromIdx, to: toIdx, piece, captured: target });
            if (!isEmpty(target)) break;
          }
        }
        break;
      }

      // ── KING ──
      case 6: {
        for (const [dr, dc] of [[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]]) {
          addMove(row + dr, col + dc);
        }

        // Castling
        if (color === COLORS.WHITE) {
          // Kingside
          if (this.castlingRights.K &&
              isEmpty(this.board[62]) && isEmpty(this.board[61]) &&
              !this._isSquareAttacked(60, COLORS.BLACK) &&
              !this._isSquareAttacked(61, COLORS.BLACK) &&
              !this._isSquareAttacked(62, COLORS.BLACK)) {
            moves.push({ from: fromIdx, to: 62, piece, captured: 0, castle: 'K' });
          }
          // Queenside
          if (this.castlingRights.Q &&
              isEmpty(this.board[59]) && isEmpty(this.board[58]) && isEmpty(this.board[57]) &&
              !this._isSquareAttacked(60, COLORS.BLACK) &&
              !this._isSquareAttacked(59, COLORS.BLACK) &&
              !this._isSquareAttacked(58, COLORS.BLACK)) {
            moves.push({ from: fromIdx, to: 58, piece, captured: 0, castle: 'Q' });
          }
        } else {
          // Kingside
          if (this.castlingRights.k &&
              isEmpty(this.board[6]) && isEmpty(this.board[5]) &&
              !this._isSquareAttacked(4, COLORS.WHITE) &&
              !this._isSquareAttacked(5, COLORS.WHITE) &&
              !this._isSquareAttacked(6, COLORS.WHITE)) {
            moves.push({ from: fromIdx, to: 6, piece, captured: 0, castle: 'k' });
          }
          // Queenside
          if (this.castlingRights.q &&
              isEmpty(this.board[3]) && isEmpty(this.board[2]) && isEmpty(this.board[1]) &&
              !this._isSquareAttacked(4, COLORS.WHITE) &&
              !this._isSquareAttacked(3, COLORS.WHITE) &&
              !this._isSquareAttacked(2, COLORS.WHITE)) {
            moves.push({ from: fromIdx, to: 2, piece, captured: 0, castle: 'q' });
          }
        }
        break;
      }
    }

    return moves;
  }

  /**
   * Cek apakah kotak (idx) diserang oleh warna (attackerColor)
   */
  _isSquareAttacked(idx, attackerColor) {
    for (let i = 0; i < 64; i++) {
      const p = this.board[i];
      if (p === PIECES.EMPTY || pieceColor(p) !== attackerColor) continue;
      const pMoves = this._pseudoMovesAttack(i);
      if (pMoves.some(m => m.to === idx)) return true;
    }
    return false;
  }

  /**
   * Versi pseudo-move tanpa castling (hindari rekursi tak terbatas)
   */
  _pseudoMovesAttack(fromIdx) {
    const piece = this.board[fromIdx];
    if (piece === PIECES.EMPTY) return [];

    const { row, col } = indexToCoord(fromIdx);
    const color = pieceColor(piece);
    const type  = absPiece(piece);
    const moves = [];

    const addMove = (toRow, toCol) => {
      if (!isValidCoord(toRow, toCol)) return;
      const toIdx = coordToIndex(toRow, toCol);
      if (!sameColor(piece, this.board[toIdx])) {
        moves.push({ from: fromIdx, to: toIdx });
      }
    };

    switch (type) {
      case 1: { // Pawn - hanya capture diagonal untuk attack check
        const dir = color === COLORS.WHITE ? -1 : 1;
        for (const dc of [-1, 1]) addMove(row + dir, col + dc);
        break;
      }
      case 2: {
        for (const [dr, dc] of [[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]])
          addMove(row + dr, col + dc);
        break;
      }
      case 3: {
        for (const [dr, dc] of [[-1,-1],[-1,1],[1,-1],[1,1]]) {
          for (let i = 1; i < 8; i++) {
            const nr = row + dr * i, nc = col + dc * i;
            if (!isValidCoord(nr, nc)) break;
            const toIdx = coordToIndex(nr, nc);
            if (sameColor(piece, this.board[toIdx])) break;
            moves.push({ from: fromIdx, to: toIdx });
            if (!isEmpty(this.board[toIdx])) break;
          }
        }
        break;
      }
      case 4: {
        for (const [dr, dc] of [[-1,0],[1,0],[0,-1],[0,1]]) {
          for (let i = 1; i < 8; i++) {
            const nr = row + dr * i, nc = col + dc * i;
            if (!isValidCoord(nr, nc)) break;
            const toIdx = coordToIndex(nr, nc);
            if (sameColor(piece, this.board[toIdx])) break;
            moves.push({ from: fromIdx, to: toIdx });
            if (!isEmpty(this.board[toIdx])) break;
          }
        }
        break;
      }
      case 5: {
        for (const [dr, dc] of [[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]]) {
          for (let i = 1; i < 8; i++) {
            const nr = row + dr * i, nc = col + dc * i;
            if (!isValidCoord(nr, nc)) break;
            const toIdx = coordToIndex(nr, nc);
            if (sameColor(piece, this.board[toIdx])) break;
            moves.push({ from: fromIdx, to: toIdx });
            if (!isEmpty(this.board[toIdx])) break;
          }
        }
        break;
      }
      case 6: {
        for (const [dr, dc] of [[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]])
          addMove(row + dr, col + dc);
        break;
      }
    }

    return moves;
  }

  /**
   * Temukan posisi raja dari warna tertentu
   */
  _findKing(color) {
    const target = color === COLORS.WHITE ? PIECES.W_KING : PIECES.B_KING;
    return this.board.indexOf(target);
  }

  /**
   * Cek apakah raja warna tertentu sedang dalam check
   */
  _isInCheck(color) {
    const kingIdx = this._findKing(color);
    if (kingIdx === -1) return false;
    const attacker = color === COLORS.WHITE ? COLORS.BLACK : COLORS.WHITE;
    return this._isSquareAttacked(kingIdx, attacker);
  }

  /**
   * Generate SEMUA legal moves untuk warna saat ini
   */
  getLegalMoves(color = null) {
    const c = color || this.turn;
    const legalMoves = [];

    for (let i = 0; i < 64; i++) {
      const piece = this.board[i];
      if (piece === PIECES.EMPTY || pieceColor(piece) !== c) continue;

      const pseudos = this._pseudoMoves(i);
      for (const move of pseudos) {
        // Coba langkah, cek apakah raja masih aman
        const undoInfo = this._applyMoveTemp(move);
        const inCheck  = this._isInCheck(c);
        this._undoMoveTemp(undoInfo);

        if (!inCheck) legalMoves.push(move);
      }
    }

    return legalMoves;
  }

  /**
   * Legal moves untuk piece spesifik di suatu kotak
   */
  getLegalMovesFor(fromIdx) {
    return this.getLegalMoves().filter(m => m.from === fromIdx);
  }

  // ───────────────────────────────────────────
  // APPLY / UNDO MOVE (sementara, untuk legal check)
  // ───────────────────────────────────────────

  _applyMoveTemp(move) {
    const undo = {
      board:           [...this.board],
      turn:            this.turn,
      castlingRights:  { ...this.castlingRights },
      enPassantTarget: this.enPassantTarget,
      halfMoveClock:   this.halfMoveClock,
      fullMoveNumber:  this.fullMoveNumber,
    };

    this._executeMove(move);
    return undo;
  }

  _undoMoveTemp(undo) {
    this.board           = undo.board;
    this.turn            = undo.turn;
    this.castlingRights  = undo.castlingRights;
    this.enPassantTarget = undo.enPassantTarget;
    this.halfMoveClock   = undo.halfMoveClock;
    this.fullMoveNumber  = undo.fullMoveNumber;
  }

  /**
   * Eksekusi move ke board (tanpa validasi legal - internal use)
   */
  _executeMove(move) {
    const { from, to, piece, captured, promotion, enPassant, epCapture, castle, doublePush } = move;

    // Hapus piece dari posisi asal
    this.board[from] = PIECES.EMPTY;

    // En passant: hapus pion yang ditangkap
    if (enPassant && epCapture !== undefined) {
      this.board[epCapture] = PIECES.EMPTY;
    }

    // Tempatkan piece (atau promosi)
    this.board[to] = promotion !== undefined ? promotion : piece;

    // Castling: pindahkan rook
    if (castle) {
      switch (castle) {
        case 'K': this.board[63] = PIECES.EMPTY; this.board[61] = PIECES.W_ROOK; break;
        case 'Q': this.board[56] = PIECES.EMPTY; this.board[59] = PIECES.W_ROOK; break;
        case 'k': this.board[7]  = PIECES.EMPTY; this.board[5]  = PIECES.B_ROOK; break;
        case 'q': this.board[0]  = PIECES.EMPTY; this.board[3]  = PIECES.B_ROOK; break;
      }
    }

    // Update en passant target
    this.enPassantTarget = doublePush
      ? coordToIndex(indexToCoord(to).row + (pieceColor(piece) === COLORS.WHITE ? 1 : -1),
                     indexToCoord(to).col)
      : null;

    // Update castling rights
    if (absPiece(piece) === 6) {
      if (pieceColor(piece) === COLORS.WHITE) { this.castlingRights.K = false; this.castlingRights.Q = false; }
      else                                    { this.castlingRights.k = false; this.castlingRights.q = false; }
    }
    if (from === 63 || to === 63) this.castlingRights.K = false;
    if (from === 56 || to === 56) this.castlingRights.Q = false;
    if (from === 7  || to === 7)  this.castlingRights.k = false;
    if (from === 0  || to === 0)  this.castlingRights.q = false;

    // Halfmove clock
    if (absPiece(piece) === 1 || captured !== 0) this.halfMoveClock = 0;
    else this.halfMoveClock++;

    // Ganti giliran
    if (this.turn === COLORS.BLACK) this.fullMoveNumber++;
    this.turn = this.turn === COLORS.WHITE ? COLORS.BLACK : COLORS.WHITE;
  }

  // ───────────────────────────────────────────
  // PUBLIC: MAKE MOVE
  // ───────────────────────────────────────────

  /**
   * Buat langkah dari notasi algebraic atau objek move
   * @param {string|object} moveInput - e.g. "e2e4", "e7e8q", atau { from: 52, to: 36 }
   * @param {string|null} promotionPiece - 'q', 'r', 'b', 'n' (jika promosi)
   * @returns {object} Hasil: { success, move, status, error }
   */
  makeMove(moveInput, promotionPiece = null) {
    if (this.status !== GAME_STATUS.ACTIVE && this.status !== GAME_STATUS.CHECK) {
      return { success: false, error: `Permainan sudah selesai: ${this.status}` };
    }

    let fromIdx, toIdx, promoPiece;

    if (typeof moveInput === 'string') {
      // Parse: "e2e4" atau "e7e8q"
      if (moveInput.length < 4) return { success: false, error: 'Format langkah tidak valid' };
      fromIdx = algToIndex(moveInput.slice(0, 2));
      toIdx   = algToIndex(moveInput.slice(2, 4));
      const promoChar = moveInput[4];
      if (promoChar) {
        const promoMap = { q: 5, r: 4, b: 3, n: 2 };
        promoPiece = promoMap[promoChar.toLowerCase()];
        if (this.turn === COLORS.BLACK) promoPiece = -promoPiece;
      }
    } else {
      fromIdx   = moveInput.from;
      toIdx     = moveInput.to;
      promoPiece = moveInput.promotion;
    }

    // Cari di legal moves
    const legal = this.getLegalMoves();
    let selectedMove = legal.find(m => {
      if (m.from !== fromIdx || m.to !== toIdx) return false;
      if (m.promotion !== undefined) {
        // Jika promosi, cocokkan piece promosi
        if (promoPiece !== undefined && promoPiece !== null) {
          return Math.abs(m.promotion) === Math.abs(promoPiece);
        }
        // Default: queen
        return Math.abs(m.promotion) === 5;
      }
      return true;
    });

    if (!selectedMove) {
      return { success: false, error: `Langkah tidak legal: ${indexToAlg(fromIdx)}-${indexToAlg(toIdx)}` };
    }

    // Simpan state sebelum bergerak (untuk history)
    const fenBefore = this.getFEN();

    // Eksekusi
    this._executeMove(selectedMove);

    // Update status
    this._updateStatus();

    // Cache FEN setelah move
    const fenAfter = this.getFEN();

    // Build move record (SEKARANG status sudah benar)
    const moveRecord = {
      moveNumber:  Math.ceil(this.moveHistory.length / 2) + 1,
      color:       selectedMove.piece > 0 ? COLORS.WHITE : COLORS.BLACK,
      from:        indexToAlg(fromIdx),
      to:          indexToAlg(toIdx),
      piece:       PIECE_SYMBOLS[Math.abs(selectedMove.piece)],
      captured:    selectedMove.captured ? PIECE_SYMBOLS[Math.abs(selectedMove.captured)] : null,
      promotion:   selectedMove.promotion ? PIECE_SYMBOLS[Math.abs(selectedMove.promotion)] : null,
      castle:      selectedMove.castle || null,
      enPassant:   selectedMove.enPassant || false,
      san:         this._buildSAN(selectedMove, fenBefore),
      fenAfter:    fenAfter,
      statusAfter: this.status,
      timestamp:   Date.now(),
    };

    // Simpan history
    this.moveHistory.push(moveRecord);

    // Simpan posisi (pakai short FEN)
    const shortFen = fenAfter.split(' ').slice(0, 4).join(' ');
    this.positionHistory.push(shortFen);

    // Return hasil
    return { success: true, move: moveRecord, status: this.status };
  }

  // ───────────────────────────────────────────
  // STATUS DETECTION
  // ───────────────────────────────────────────

  _updateStatus() {
    const legalMoves = this.getLegalMoves();
    const inCheck    = this._isInCheck(this.turn);

    if (legalMoves.length === 0) {
      this.status = inCheck ? GAME_STATUS.CHECKMATE : GAME_STATUS.STALEMATE;
      return;
    }

    if (inCheck) {
      this.status = GAME_STATUS.CHECK;
      return;
    }

    // Fifty-move rule
    if (this.halfMoveClock >= 100) {
      this.status = GAME_STATUS.DRAW;
      return;
    }

    // Insufficient material
    if (this._isInsufficientMaterial()) {
      this.status = GAME_STATUS.DRAW;
      return;
    }

    // Threefold repetition
    const currentFEN = this.getFEN().split(' ').slice(0, 4).join(' ');
    const count = this.positionHistory.filter(f =>
      f.split(' ').slice(0, 4).join(' ') === currentFEN
    ).length;
    if (count >= 3) {
      this.status = GAME_STATUS.DRAW;
      return;
    }

    this.status = GAME_STATUS.ACTIVE;
  }

  /**
   * Cek materi tidak cukup untuk checkmate
   */
  _isInsufficientMaterial() {
    const pieces = this.board.filter(p => p !== PIECES.EMPTY);
    if (pieces.length === 2) return true; // Raja vs Raja

    if (pieces.length === 3) {
      // Raja vs Raja + Bishop atau Raja vs Raja + Knight
      return pieces.some(p => absPiece(p) === 3 || absPiece(p) === 2);
    }

    if (pieces.length === 4) {
      // Raja + Bishop vs Raja + Bishop (bishop sama warna)
      const bishops = pieces.filter(p => absPiece(p) === 3);
      if (bishops.length === 2) {
        const b1Idx = this.board.indexOf(bishops[0]);
        const b2Idx = this.board.indexOf(bishops[1]);
        const { row: r1, col: c1 } = indexToCoord(b1Idx);
        const { row: r2, col: c2 } = indexToCoord(b2Idx);
        return (r1 + c1) % 2 === (r2 + c2) % 2;
      }
    }

    return false;
  }

  // ───────────────────────────────────────────
  // SAN (Standard Algebraic Notation) BUILDER
  // ───────────────────────────────────────────

  _buildSAN(move, fenBefore) {
    const { from, to, piece, captured, promotion, castle, enPassant } = move;

    if (castle === 'K' || castle === 'k') return 'O-O';
    if (castle === 'Q' || castle === 'q') return 'O-O-O';

    const pieceType = absPiece(piece);
    const toAlg = indexToAlg(to);
    let san = '';

    if (pieceType === 1) {
      // Pawn
      if (captured || enPassant) {
        san = indexToAlg(from)[0] + 'x' + toAlg;
      } else {
        san = toAlg;
      }
      if (promotion) {
        san += '=' + PIECE_SYMBOLS[absPiece(promotion)];
      }
    } else {
      const pieceLetter = PIECE_SYMBOLS[pieceType];
      san = pieceLetter;

      // Disambiguasi jika perlu
      const ambig = this._getAmbiguousMoves(move, fenBefore);
      if (ambig.sameFile && ambig.sameRank) san += indexToAlg(from);
      else if (ambig.sameFile) san += indexToAlg(from)[1]; // rank
      else if (ambig.sameRank) san += indexToAlg(from)[0]; // file

      if (captured) san += 'x';
      san += toAlg;
    }

    // Check / Checkmate suffix
    if (this.status === GAME_STATUS.CHECKMATE) san += '#';
    else if (this.status === GAME_STATUS.CHECK) san += '+';

    return san;
  }

  _getAmbiguousMoves(move, fenBefore) {
    // Sederhanakan: cek apakah ada piece sejenis yang bisa ke kotak yang sama
    const saved = this._applyMoveTemp({ ...move, from: move.from }); // dummy
    this._undoMoveTemp(saved);

    const engine2 = new ChessEngine(fenBefore);
    const others  = engine2.getLegalMoves().filter(m =>
      m.to === move.to &&
      m.from !== move.from &&
      Math.abs(m.piece) === Math.abs(move.piece)
    );

    if (others.length === 0) return { sameFile: false, sameRank: false };

    const { row: fr, col: fc } = indexToCoord(move.from);
    const sameFile = others.some(m => indexToCoord(m.from).col === fc);
    const sameRank = others.some(m => indexToCoord(m.from).row === fr);
    return { sameFile, sameRank };
  }

  // ───────────────────────────────────────────
  // PUBLIC: STATE & INFORMASI
  // ───────────────────────────────────────────

  /** Return state lengkap untuk dikirim via WebSocket / disimpan ke MongoDB */
  getState() {
    return {
      fen:             this.getFEN(),
      board:           [...this.board],
      turn:            this.turn,
      status:          this.status,
      castlingRights:  { ...this.castlingRights },
      enPassantTarget: this.enPassantTarget,
      halfMoveClock:   this.halfMoveClock,
      fullMoveNumber:  this.fullMoveNumber,
      moveCount:       this.moveHistory.length,
      inCheck:         this._isInCheck(this.turn),
      legalMoveCount:  this.getLegalMoves().length,
    };
  }

  static applyMoveFromFEN(fen, move) {
    const engine = new ChessEngine(fen);
    const result = engine.makeMove(move);

    if (!result.success) {
      return result;
    }

    return {
      success: true,
      fen: engine.getFEN(),
      move: result.move,
      status: engine.status
    };
  }

  /** Return board sebagai array 2D 8x8 (lebih mudah untuk frontend) */
  getBoard2D() {
    const board2D = [];
    for (let row = 0; row < 8; row++) {
      board2D.push(this.board.slice(row * 8, row * 8 + 8));
    }
    return board2D;
  }

  /** Return daftar kotak yang bisa dijangkau dari fromIdx (untuk highlight frontend) */
  getHighlightSquares(fromIdx) {
    return this.getLegalMovesFor(fromIdx).map(m => m.to);
  }

  /** Return semua langkah dalam format notasi for MongoDB storage */
  getMoveHistory() {
    return this.moveHistory;
  }

  /** Resign */
  resign(color) {
    if (color && this.turn !== color) {
      return { success: false, error: 'Bukan giliran Anda' };
    }
    this.status = GAME_STATUS.RESIGNED;
    return { success: true, status: this.status, resignedBy: color };
  }

  /** Offer / accept draw */
  declareDraw() {
    this.status = GAME_STATUS.DRAW;
    return { success: true, status: this.status };
  }

  /** Print board ke console (debug) */
  printBoard() {
    let out = '  a b c d e f g h\n';
    for (let row = 0; row < 8; row++) {
      out += (8 - row) + ' ';
      for (let col = 0; col < 8; col++) {
        out += PIECE_SYMBOLS[this.board[coordToIndex(row, col)]] + ' ';
      }
      out += (8 - row) + '\n';
    }
    out += '  a b c d e f g h';
    console.log(out);
    return out;
  }
}

// ─────────────────────────────────────────────
// EXPORT
// ─────────────────────────────────────────────

module.exports = {
  ChessEngine,
  PIECES,
  COLORS,
  GAME_STATUS,
  INITIAL_FEN,
  PIECE_SYMBOLS,
  indexToAlg,
  algToIndex,
  indexToCoord,
  coordToIndex,
};
