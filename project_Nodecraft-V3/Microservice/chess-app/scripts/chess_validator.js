'use strict';

/**
 * chess_validator.js
 * Dipanggil oleh ChessValidator.php via exec():
 *   node chess_validator.js '{"fen":"...","from":"e2","to":"e4","promotion":null}'
 *   node chess_validator.js '{"fen":"...","action":"legal_moves"}'
 *
 * Output: JSON ke stdout
 * Exit code 0 = sukses, 1 = error
 */

// ── Inline ChessEngine (copy dari Engine/ChessEngine.js) ──────────────────

const PIECES = {
  EMPTY:0, W_PAWN:1, W_KNIGHT:2, W_BISHOP:3, W_ROOK:4, W_QUEEN:5, W_KING:6,
  B_PAWN:-1, B_KNIGHT:-2, B_BISHOP:-3, B_ROOK:-4, B_QUEEN:-5, B_KING:-6,
};
const COLORS = { WHITE:'white', BLACK:'black' };
const PIECE_SYMBOLS = {
  '1':'P','2':'N','3':'B','4':'R','5':'Q','6':'K',
  '-1':'p','-2':'n','-3':'b','-4':'r','-5':'q','-6':'k','0':'.',
};
const FEN_PIECE_MAP = {
  'P':1,'N':2,'B':3,'R':4,'Q':5,'K':6,
  'p':-1,'n':-2,'b':-3,'r':-4,'q':-5,'k':-6,
};
const INITIAL_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

const indexToCoord = (i) => ({ row: Math.floor(i/8), col: i%8 });
const coordToIndex = (r,c) => r*8+c;
const indexToAlg   = (i) => { const {row,col}=indexToCoord(i); return String.fromCharCode(97+col)+(8-row); };
const algToIndex   = (a) => { const col=a.charCodeAt(0)-97; const row=8-parseInt(a[1]); return coordToIndex(row,col); };
const isValidCoord = (r,c) => r>=0&&r<8&&c>=0&&c<8;
const isEmpty      = (p) => p===0;
const sameColor    = (a,b) => (a>0&&b>0)||(a<0&&b<0);
const pieceColor   = (p) => p>0?COLORS.WHITE:p<0?COLORS.BLACK:null;
const absPiece     = (p) => Math.abs(p);

class ChessEngine {
  constructor(fen=INITIAL_FEN) {
    this.board=new Array(64).fill(0);
    this.turn=COLORS.WHITE;
    this.castlingRights={K:false,Q:false,k:false,q:false};
    this.enPassantTarget=null;
    this.halfMoveClock=0;
    this.fullMoveNumber=1;
    this.moveHistory=[];
    this.positionHistory=[];
    this.status='active';
    this.loadFEN(fen);
  }

  loadFEN(fen) {
    const parts=fen.trim().split(/\s+/);
    const [pp,ac,ca,ep,hm,fm]=parts;
    this.board.fill(0);
    let idx=0;
    for(const ch of pp){
      if(ch==='/') continue;
      if(ch>='1'&&ch<='8'){idx+=parseInt(ch);}
      else{this.board[idx++]=FEN_PIECE_MAP[ch];}
    }
    this.turn=ac==='w'?COLORS.WHITE:COLORS.BLACK;
    this.castlingRights={K:ca.includes('K'),Q:ca.includes('Q'),k:ca.includes('k'),q:ca.includes('q')};
    this.enPassantTarget=(ep&&ep!=='-')?algToIndex(ep):null;
    this.halfMoveClock=hm?parseInt(hm):0;
    this.fullMoveNumber=fm?parseInt(fm):1;
    this.moveHistory=[];
    this.positionHistory=[this.getFEN()];
    this.status='active';
    this._updateStatus();
  }

  getFEN() {
    let fen='';
    for(let r=0;r<8;r++){
      let e=0;
      for(let c=0;c<8;c++){
        const p=this.board[coordToIndex(r,c)];
        if(p===0){e++;}else{if(e>0){fen+=e;e=0;}fen+=PIECE_SYMBOLS[p];}
      }
      if(e>0)fen+=e;
      if(r<7)fen+='/';
    }
    fen+=' '+(this.turn===COLORS.WHITE?'w':'b');
    const ca=[this.castlingRights.K?'K':'',this.castlingRights.Q?'Q':'',this.castlingRights.k?'k':'',this.castlingRights.q?'q':''].join('')||'-';
    fen+=' '+ca;
    fen+=' '+(this.enPassantTarget!==null?indexToAlg(this.enPassantTarget):'-');
    fen+=' '+this.halfMoveClock+' '+this.fullMoveNumber;
    return fen;
  }

  _pseudoMoves(fromIdx) {
    const piece=this.board[fromIdx];
    if(piece===0)return[];
    const{row,col}=indexToCoord(fromIdx);
    const color=pieceColor(piece);
    const type=absPiece(piece);
    const moves=[];
    const addMove=(tr,tc,flags={})=>{
      if(!isValidCoord(tr,tc))return;
      const ti=coordToIndex(tr,tc);
      const tgt=this.board[ti];
      if(sameColor(piece,tgt))return;
      moves.push({from:fromIdx,to:ti,piece,captured:tgt,...flags});
    };
    switch(type){
      case 1:{
        const dir=color===COLORS.WHITE?-1:1;
        const start=color===COLORS.WHITE?6:1;
        const promRow=color===COLORS.WHITE?0:7;
        const oneStep=coordToIndex(row+dir,col);
        if(isValidCoord(row+dir,col)&&isEmpty(this.board[oneStep])){
          const toRow=row+dir;
          if(toRow===promRow){
            for(const pr of[5,4,3,2])moves.push({from:fromIdx,to:oneStep,piece,captured:0,promotion:color===COLORS.WHITE?pr:-pr});
          }else{
            moves.push({from:fromIdx,to:oneStep,piece,captured:0});
          }
          if(row===start){
            const twoStep=coordToIndex(row+dir*2,col);
            if(isEmpty(this.board[twoStep]))moves.push({from:fromIdx,to:twoStep,piece,captured:0,doublePush:true});
          }
        }
        for(const dc of[-1,1]){
          if(!isValidCoord(row+dir,col+dc))continue;
          const ti=coordToIndex(row+dir,col+dc);
          const tgt=this.board[ti];
          const toRow=row+dir;
          if(!isEmpty(tgt)&&!sameColor(piece,tgt)){
            if(toRow===promRow){for(const pr of[5,4,3,2])moves.push({from:fromIdx,to:ti,piece,captured:tgt,promotion:color===COLORS.WHITE?pr:-pr});}
            else{moves.push({from:fromIdx,to:ti,piece,captured:tgt});}
          }
          if(this.enPassantTarget===ti){
            const epCapIdx=coordToIndex(row,col+dc);
            moves.push({from:fromIdx,to:ti,piece,captured:this.board[epCapIdx],enPassant:true,epCapture:epCapIdx});
          }
        }
        break;
      }
      case 2:
        for(const[dr,dc]of[[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]])addMove(row+dr,col+dc);
        break;
      case 3:
        for(const[dr,dc]of[[-1,-1],[-1,1],[1,-1],[1,1]]){
          for(let i=1;i<8;i++){
            const nr=row+dr*i,nc=col+dc*i;
            if(!isValidCoord(nr,nc))break;
            const ti=coordToIndex(nr,nc),tgt=this.board[ti];
            if(sameColor(piece,tgt))break;
            moves.push({from:fromIdx,to:ti,piece,captured:tgt});
            if(!isEmpty(tgt))break;
          }
        }
        break;
      case 4:
        for(const[dr,dc]of[[-1,0],[1,0],[0,-1],[0,1]]){
          for(let i=1;i<8;i++){
            const nr=row+dr*i,nc=col+dc*i;
            if(!isValidCoord(nr,nc))break;
            const ti=coordToIndex(nr,nc),tgt=this.board[ti];
            if(sameColor(piece,tgt))break;
            moves.push({from:fromIdx,to:ti,piece,captured:tgt});
            if(!isEmpty(tgt))break;
          }
        }
        break;
      case 5:
        for(const[dr,dc]of[[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]]){
          for(let i=1;i<8;i++){
            const nr=row+dr*i,nc=col+dc*i;
            if(!isValidCoord(nr,nc))break;
            const ti=coordToIndex(nr,nc),tgt=this.board[ti];
            if(sameColor(piece,tgt))break;
            moves.push({from:fromIdx,to:ti,piece,captured:tgt});
            if(!isEmpty(tgt))break;
          }
        }
        break;
      case 6:
        for(const[dr,dc]of[[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]])addMove(row+dr,col+dc);
        if(color===COLORS.WHITE){
          if(this.castlingRights.K&&isEmpty(this.board[62])&&isEmpty(this.board[61])&&!this._isSquareAttacked(60,COLORS.BLACK)&&!this._isSquareAttacked(61,COLORS.BLACK)&&!this._isSquareAttacked(62,COLORS.BLACK))
            moves.push({from:fromIdx,to:62,piece,captured:0,castle:'K'});
          if(this.castlingRights.Q&&isEmpty(this.board[59])&&isEmpty(this.board[58])&&isEmpty(this.board[57])&&!this._isSquareAttacked(60,COLORS.BLACK)&&!this._isSquareAttacked(59,COLORS.BLACK)&&!this._isSquareAttacked(58,COLORS.BLACK))
            moves.push({from:fromIdx,to:58,piece,captured:0,castle:'Q'});
        }else{
          if(this.castlingRights.k&&isEmpty(this.board[6])&&isEmpty(this.board[5])&&!this._isSquareAttacked(4,COLORS.WHITE)&&!this._isSquareAttacked(5,COLORS.WHITE)&&!this._isSquareAttacked(6,COLORS.WHITE))
            moves.push({from:fromIdx,to:6,piece,captured:0,castle:'k'});
          if(this.castlingRights.q&&isEmpty(this.board[3])&&isEmpty(this.board[2])&&isEmpty(this.board[1])&&!this._isSquareAttacked(4,COLORS.WHITE)&&!this._isSquareAttacked(3,COLORS.WHITE)&&!this._isSquareAttacked(2,COLORS.WHITE))
            moves.push({from:fromIdx,to:2,piece,captured:0,castle:'q'});
        }
        break;
    }
    return moves;
  }

  _pseudoMovesAttack(fromIdx){
    const piece=this.board[fromIdx];
    if(piece===0)return[];
    const{row,col}=indexToCoord(fromIdx);
    const color=pieceColor(piece);
    const type=absPiece(piece);
    const moves=[];
    const addMove=(tr,tc)=>{
      if(!isValidCoord(tr,tc))return;
      const ti=coordToIndex(tr,tc);
      if(!sameColor(piece,this.board[ti]))moves.push({from:fromIdx,to:ti});
    };
    switch(type){
      case 1:{const dir=color===COLORS.WHITE?-1:1;for(const dc of[-1,1])addMove(row+dir,col+dc);break;}
      case 2:for(const[dr,dc]of[[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]])addMove(row+dr,col+dc);break;
      case 3:for(const[dr,dc]of[[-1,-1],[-1,1],[1,-1],[1,1]]){for(let i=1;i<8;i++){const nr=row+dr*i,nc=col+dc*i;if(!isValidCoord(nr,nc))break;const ti=coordToIndex(nr,nc);if(sameColor(piece,this.board[ti]))break;moves.push({from:fromIdx,to:ti});if(!isEmpty(this.board[ti]))break;}}break;
      case 4:for(const[dr,dc]of[[-1,0],[1,0],[0,-1],[0,1]]){for(let i=1;i<8;i++){const nr=row+dr*i,nc=col+dc*i;if(!isValidCoord(nr,nc))break;const ti=coordToIndex(nr,nc);if(sameColor(piece,this.board[ti]))break;moves.push({from:fromIdx,to:ti});if(!isEmpty(this.board[ti]))break;}}break;
      case 5:for(const[dr,dc]of[[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]]){for(let i=1;i<8;i++){const nr=row+dr*i,nc=col+dc*i;if(!isValidCoord(nr,nc))break;const ti=coordToIndex(nr,nc);if(sameColor(piece,this.board[ti]))break;moves.push({from:fromIdx,to:ti});if(!isEmpty(this.board[ti]))break;}}break;
      case 6:for(const[dr,dc]of[[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]])addMove(row+dr,col+dc);break;
    }
    return moves;
  }

  _isSquareAttacked(idx,attackerColor){
    for(let i=0;i<64;i++){
      const p=this.board[i];
      if(p===0||pieceColor(p)!==attackerColor)continue;
      if(this._pseudoMovesAttack(i).some(m=>m.to===idx))return true;
    }
    return false;
  }

  _findKing(color){const t=color===COLORS.WHITE?6:-6;return this.board.indexOf(t);}
  _isInCheck(color){const k=this._findKing(color);if(k===-1)return false;return this._isSquareAttacked(k,color===COLORS.WHITE?COLORS.BLACK:COLORS.WHITE);}

  getLegalMoves(color=null){
    const c=color||this.turn;
    const legal=[];
    for(let i=0;i<64;i++){
      const p=this.board[i];
      if(p===0||pieceColor(p)!==c)continue;
      for(const mv of this._pseudoMoves(i)){
        const undo=this._applyMoveTemp(mv);
        const inCheck=this._isInCheck(c);
        this._undoMoveTemp(undo);
        if(!inCheck)legal.push(mv);
      }
    }
    return legal;
  }

  _applyMoveTemp(move){
    const undo={board:[...this.board],turn:this.turn,castlingRights:{...this.castlingRights},enPassantTarget:this.enPassantTarget,halfMoveClock:this.halfMoveClock,fullMoveNumber:this.fullMoveNumber};
    this._executeMove(move);
    return undo;
  }
  _undoMoveTemp(undo){this.board=undo.board;this.turn=undo.turn;this.castlingRights=undo.castlingRights;this.enPassantTarget=undo.enPassantTarget;this.halfMoveClock=undo.halfMoveClock;this.fullMoveNumber=undo.fullMoveNumber;}

  _executeMove(move){
    const{from,to,piece,captured,promotion,enPassant,epCapture,castle,doublePush}=move;
    this.board[from]=0;
    if(enPassant&&epCapture!==undefined)this.board[epCapture]=0;
    this.board[to]=promotion!==undefined?promotion:piece;
    if(castle){
      if(castle==='K'){this.board[63]=0;this.board[61]=4;}
      else if(castle==='Q'){this.board[56]=0;this.board[59]=4;}
      else if(castle==='k'){this.board[7]=0;this.board[5]=-4;}
      else if(castle==='q'){this.board[0]=0;this.board[3]=-4;}
    }
    this.enPassantTarget=doublePush?coordToIndex(indexToCoord(to).row+(pieceColor(piece)===COLORS.WHITE?1:-1),indexToCoord(to).col):null;
    if(absPiece(piece)===6){if(pieceColor(piece)===COLORS.WHITE){this.castlingRights.K=false;this.castlingRights.Q=false;}else{this.castlingRights.k=false;this.castlingRights.q=false;}}
    if(from===63||to===63)this.castlingRights.K=false;
    if(from===56||to===56)this.castlingRights.Q=false;
    if(from===7||to===7)this.castlingRights.k=false;
    if(from===0||to===0)this.castlingRights.q=false;
    if(absPiece(piece)===1||captured!==0)this.halfMoveClock=0;else this.halfMoveClock++;
    if(this.turn===COLORS.BLACK)this.fullMoveNumber++;
    this.turn=this.turn===COLORS.WHITE?COLORS.BLACK:COLORS.WHITE;
  }

  _updateStatus(){
    const legal=this.getLegalMoves();
    const inCheck=this._isInCheck(this.turn);
    if(legal.length===0){this.status=inCheck?'checkmate':'stalemate';return;}
    if(inCheck){this.status='check';return;}
    if(this.halfMoveClock>=100){this.status='draw';return;}
    if(this._isInsufficientMaterial()){this.status='draw';return;}
    this.status='active';
  }

  _isInsufficientMaterial(){
    const pieces=this.board.filter(p=>p!==0);
    if(pieces.length===2)return true;
    if(pieces.length===3)return pieces.some(p=>absPiece(p)===3||absPiece(p)===2);
    return false;
  }

  _buildSAN(move){
    const{from,to,piece,captured,promotion,castle,enPassant}=move;
    if(castle==='K'||castle==='k')return'O-O';
    if(castle==='Q'||castle==='q')return'O-O-O';
    const type=absPiece(piece);
    const toAlg=indexToAlg(to);
    let san='';
    if(type===1){
      san=(captured||enPassant)?indexToAlg(from)[0]+'x'+toAlg:toAlg;
      if(promotion)san+='='+PIECE_SYMBOLS[absPiece(promotion)];
    }else{
      san=PIECE_SYMBOLS[type];
      if(captured)san+='x';
      san+=toAlg;
    }
    if(this.status==='checkmate')san+='#';
    else if(this.status==='check')san+='+';
    return san;
  }

  makeMove(moveStr, promoPiece=null){
    if(this.status!=='active'&&this.status!=='check')return{success:false,error:'Game sudah selesai'};
    let fromIdx,toIdx,promo;
    if(typeof moveStr==='string'){
      fromIdx=algToIndex(moveStr.slice(0,2));
      toIdx=algToIndex(moveStr.slice(2,4));
      const pc=moveStr[4];
      if(pc){const m={'q':5,'r':4,'b':3,'n':2};promo=m[pc.toLowerCase()];if(this.turn===COLORS.BLACK)promo=-promo;}
    }else{fromIdx=moveStr.from;toIdx=moveStr.to;promo=moveStr.promotion;}
    const legal=this.getLegalMoves();
    const sel=legal.find(m=>{
      if(m.from!==fromIdx||m.to!==toIdx)return false;
      if(m.promotion!==undefined){if(promo!=null)return Math.abs(m.promotion)===Math.abs(promo);return Math.abs(m.promotion)===5;}
      return true;
    });
    if(!sel)return{success:false,error:`Langkah tidak legal: ${indexToAlg(fromIdx)}-${indexToAlg(toIdx)}`};
    this._executeMove(sel);
    this._updateStatus();
    const san=this._buildSAN(sel);
    return{
      success:true,
      san,
      fen:this.getFEN(),
      is_check:this.status==='check',
      is_checkmate:this.status==='checkmate',
      is_stalemate:this.status==='stalemate',
      is_draw:this.status==='draw',
      captured:sel.captured?PIECE_SYMBOLS[Math.abs(sel.captured)]:null,
      promotion:sel.promotion?PIECE_SYMBOLS[Math.abs(sel.promotion)]:null,
      castle:sel.castle||null,
      legal:true,
    };
  }

  getLegalMovesFormatted(){
    return this.getLegalMoves().map(m=>({
      from:indexToAlg(m.from),
      to:indexToAlg(m.to),
      san:this._buildSAN(m),
    }));
  }
}

// ── Entry point ───────────────────────────────────────────────────────────

const raw = process.argv[2];
if (!raw) {
  process.stdout.write(JSON.stringify({ error: 'Input JSON diperlukan sebagai argument' }));
  process.exit(1);
}

let input;
try {
  input = JSON.parse(raw);
} catch (e) {
  process.stdout.write(JSON.stringify({ error: 'JSON tidak valid: ' + e.message }));
  process.exit(1);
}

try {
  const engine = new ChessEngine(input.fen || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');

  if (input.action === 'legal_moves') {
    process.stdout.write(JSON.stringify({ moves: engine.getLegalMovesFormatted() }));
    process.exit(0);
  }

  // Default: validate & apply move
  const result = engine.makeMove(input.from + input.to + (input.promotion || ''));
  process.stdout.write(JSON.stringify(result));
  process.exit(result.success ? 0 : 1);

} catch (e) {
  process.stdout.write(JSON.stringify({ error: e.message }));
  process.exit(1);
}
