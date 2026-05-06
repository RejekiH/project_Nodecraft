/**
 * MongoDB Index Setup - User Service
 * =====================================
 * Jalankan script ini sekali saat setup awal:
 *
 *   mongosh nodechess_users < database/indexes.js
 *
 * Atau copy-paste ke MongoDB Compass / mongosh shell.
 *
 * WAJIB dijalankan sebelum menjalankan service!
 */

// ─── Pilih database ─────────────────────────────────────────────────────────
use('nodechess_users');

print('=== Membuat indexes untuk collection: users ===\n');

// ─── Index 1: username (unique, case-insensitive) ────────────────────────────
db.users.createIndex(
  { username: 1 },
  {
    unique: true,
    collation: { locale: 'en', strength: 2 }, // case-insensitive
    name: 'idx_username_unique',
    background: true,
  }
);
print('✓ idx_username_unique');

// ─── Index 2: email (unique, case-insensitive) ───────────────────────────────
db.users.createIndex(
  { email: 1 },
  {
    unique: true,
    collation: { locale: 'en', strength: 2 },
    name: 'idx_email_unique',
    background: true,
  }
);
print('✓ idx_email_unique');

// ─── Index 3: rating descending (untuk leaderboard) ─────────────────────────
db.users.createIndex(
  { rating: -1 },
  {
    name: 'idx_rating_desc',
    background: true,
  }
);
print('✓ idx_rating_desc');

// ─── Index 4: status (untuk mencari user online) ────────────────────────────
db.users.createIndex(
  { status: 1 },
  {
    name: 'idx_status',
    background: true,
  }
);
print('✓ idx_status');

// ─── Index 5: composite (rating + status) untuk leaderboard filter ───────────
db.users.createIndex(
  { status: 1, rating: -1 },
  {
    name: 'idx_status_rating',
    background: true,
  }
);
print('✓ idx_status_rating');

// ─── Verifikasi ─────────────────────────────────────────────────────────────
print('\n=== Indexes yang terbuat: ===');
db.users.getIndexes().forEach(idx => print(`- ${idx.name}`));

print('\n=== Setup selesai! ===\n');

// ─── Optional: Insert user admin untuk testing ──────────────────────────────
// Uncomment jika ingin seed data awal:
/*
const bcryptHash = '$2b$12$HASH_DARI_PASSWORD_admin123'; // generate: php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"

db.users.insertOne({
  username: 'admin',
  email: 'admin@nodechess.local',
  password: bcryptHash,
  rating: 0,
  wins: 0,
  losses: 0,
  draws: 0,
  status: 'offline',
  last_match_preview: null,
  created_at: new Date(),
  updated_at: new Date(),
});
print('✓ Admin user dibuat (username: admin)');
*/
