# 03 — ERD Konseptual & Keputusan Desain Data

> **Project**: Manahpro — Platform Tata Kelola Tournament & Scoring Panahan
> **Versi**: 1.0
> **Tanggal**: 2026-05-25
> **Referensi**: [01-PRD.md](./01-PRD.md), [02-user-stories.md](./02-user-stories.md), [00-offline-first-analysis.md](./00-offline-first-analysis.md)
> **Catatan**: Ini bukan DDL — ini dokumen keputusan desain data. Atribut yang ditampilkan adalah atribut penting secara arsitektur, bukan daftar kolom lengkap.

---

## Proses Berpikir: Pertanyaan Pemantik

Sebelum mendesain entitas, saya harus menjawab tiga pertanyaan fundamental yang akan mewarnai setiap keputusan desain data:

### Data apa yang paling sering DIBACA?

| Data | Frekuensi Baca | Volume Pembaca | Pola Akses |
|------|---------------|----------------|------------|
| **Leaderboard** (ranking + total skor) | Setiap 3-5 detik per penonton | 1.000–5.000 concurrent | Hot read — harus di-cache, bukan query langsung |
| **Daftar tournament** (publik) | Saat buka app | Semua pengguna | Warm read — cache friendly, jarang berubah |
| **Bagan eliminasi** (bracket) | Saat babak aduan | Ratusan-ribuan | Warm read — berubah setelah setiap pertandingan |
| **Skor bantalan** (oleh scorer) | Setiap rambahan | 20-50 scorer | Moderate — terbatas per bantalan |
| **Profil atlet** | Ad-hoc | Rendah | Cold read — bisa langsung dari DB |

**Implikasi desain**: Leaderboard adalah hot path yang tidak boleh di-query langsung dari tabel skor setiap kali. Perlu **denormalisasi atau caching** — keputusan detail di bawah.

### Data apa yang paling KRITIS (tidak boleh hilang)?

| Data | Tingkat Kritis | Konsekuensi Jika Hilang | Strategi Perlindungan |
|------|---------------|------------------------|----------------------|
| **Score entries** | 🔴 KRITIS | Hasil tournament salah, protes peserta, reputasi hancur | Idempotency key, audit trail, soft delete, backup |
| **Tournament participants** | 🔴 KRITIS | Peserta tidak bisa bertanding | Verifikasi status sebelum delete |
| **Elimination matches** (hasil aduan) | 🔴 KRITIS | Bracket salah, pemenang salah | Immutable setelah confirmed |
| **Club memberships** | 🟡 PENTING | KTA invalid, peserta tidak bisa verifikasi | Soft delete, histori mutasi |
| **User accounts** | 🟡 PENTING | Akses hilang | Soft delete |
| **Tournament config** | 🟢 MEDIUM | Bisa di-create ulang | Standard backup |

**Implikasi desain**: Score entries dan elimination matches harus punya audit trail paling ketat. **Tidak boleh ada hard delete pada data scoring.** Update = insert versi baru + simpan versi lama.

### Apakah struktur ini masih masuk akal di 10x scale?

| Skenario Saat Ini | 10x Scale | Implikasi |
|-------------------|-----------|-----------|
| 500 peserta/tournament | 5.000 peserta | Score entries: 5.000 × 40 rambahan = 200.000 entries/tournament → PostgreSQL handle tanpa masalah |
| 50 bantalan | 500 bantalan | Scorer assignment table: trivial |
| 200 club | 2.000 club | Club + member queries: index standar cukup |
| 1 tournament simultan | 10 simultan | Leaderboard cache: perlu partisi per tournament ID |
| 5.000 concurrent read | 50.000 concurrent | **Ini yang kritis** — Redis caching wajib, mungkin perlu CDN/read replica |

**Implikasi desain**: 
- Semua tabel yang berhubungan dengan scoring HARUS di-index dengan benar (tournament_id, participant_id, end_number)
- Leaderboard TIDAK BOLEH dihitung on-the-fly dari raw score saat scale >1.000 peserta
- Semua query harus mempertimbangkan tournament_id sebagai partition key logis (meskipun belum table partitioning)

---

## 1. Daftar Entitas & Atribut Kunci

### 1.1 Modul: Auth & User

#### `users`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | Standar dari starter — anti-enumeration |
| name | string | |
| email | string, unique | Login identifier |
| phone | string, unique, nullable | Login alternatif (OTP) |
| password | string | Hashed (bcrypt) |
| gender | enum(male, female) | Diperlukan untuk kategori lomba |
| birth_date | date | Diperlukan untuk validasi batasan umur kategori |
| address | text, nullable | Domisili |
| avatar_path | string, nullable | Referensi ke asset |
| is_active | boolean, default true | Soft-disable tanpa menghapus data |
| email_verified_at | timestamp, nullable | Verifikasi email |
| region_id | FK → regions | Wilayah domisili — untuk filter Pengurus |
| deleted_at | timestamp, nullable | **Soft delete** |
| created_at, updated_at | timestamps | Standard |

> **Keputusan**: Menggunakan tabel `users` yang sudah ada dari starter. Tidak perlu tabel terpisah untuk "atlet" vs "admin" — perbedaan perilaku dikelola oleh role (spatie). Satu user bisa punya multiple roles.

#### `roles` & `permissions` (spatie — sudah ada)

Role yang akan didefinisikan:

| Role | Guard | Catatan |
|------|-------|---------|
| super-admin | web | Bypass semua permission (Gate::before) |
| admin | web | Sudah ada dari starter |
| pengurus | web | Pengprov/Pengda — scoped ke region |
| admin-club | web | Scoped ke club tertentu |
| admin-tournament | web | Scoped ke tournament tertentu |
| scorer | web | Scoped ke tournament + bantalan tertentu |
| athlete | web | Default role setelah register |

> **Keputusan penting — Scoped Roles**: Role `admin-club`, `admin-tournament`, dan `scorer` bersifat **contextual** — mereka hanya berlaku untuk entitas tertentu. Spatie permission sendiri tidak native support ini. Solusi: **tabel pivot terpisah** (misal `club_admins`, `tournament_officials`, `scorer_assignments`) yang menyimpan relasi user↔entitas. Permission check = role check + pivot check.
>
> **Alternatif yang saya pertimbangkan dan tolak**: Menggunakan Spatie's `model_has_roles` dengan morph relation. Ini technically possible tapi membuat query permission menjadi kompleks dan sulit di-debug. Explicit pivot table lebih jelas dan performant.

---

### 1.2 Modul: Club & Anggota

#### `clubs`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| name | string, unique | Nama club |
| slug | string, unique | URL-friendly identifier |
| description | text, nullable | Profil sejarah club |
| slogan | string, nullable | |
| logo_path | string, nullable | Referensi ke asset |
| region_id | FK → regions | Wilayah domisili club — untuk filtering Pengurus |
| registration_number | string, nullable | Nomor SK pendirian |
| address | text | Alamat sekretariat |
| latitude | decimal(10,7), nullable | Koordinat lokasi latihan |
| longitude | decimal(10,7), nullable | |
| bank_name | string, nullable | Informasi bank club (untuk iuran) |
| bank_account | string, nullable | |
| status | enum(pending, active, rejected, suspended) | Lifecycle club |
| verified_at | timestamp, nullable | Kapan di-approve Super Admin |
| verified_by | FK → users, nullable | Siapa yang approve |
| deleted_at | timestamp, nullable | **Soft delete** |
| created_at, updated_at | timestamps | |

> **Keputusan: Soft delete pada clubs**. Club yang di-suspend/dibubarkan tidak boleh dihapus karena data historis anggota dan prestasi masih direferensikan. `status = suspended` untuk non-aktif sementara, soft delete untuk penghapusan permanen.

#### `club_members`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| club_id | FK → clubs | |
| user_id | FK → users | |
| kta_number | string, unique, nullable | Nomor KTA — di-generate saat approved |
| status | enum(pending, active, inactive, transferred) | Lifecycle keanggotaan |
| joined_at | date, nullable | Tanggal bergabung (approved) |
| left_at | date, nullable | Tanggal keluar/transfer |
| membership_expires_at | date, nullable | Masa aktif iuran |
| mastery_level | enum(beginner, intermediate, advanced, master), default beginner | Tingkat kemahiran |
| notes | text, nullable | Catatan admin |
| deleted_at | timestamp, nullable | **Soft delete** |
| created_at, updated_at | timestamps | |

> **Keputusan: `(club_id, user_id)` BUKAN unique constraint**. Satu user bisa pernah jadi anggota club yang sama, keluar, lalu masuk lagi. Yang unique adalah member aktif: partial unique index `WHERE status = 'active'` pada `(club_id, user_id)`.

> **Keputusan: Satu atlet hanya bisa aktif di satu club**. Partial unique index `WHERE status = 'active'` pada `(user_id)` saja — memastikan tidak ada double-membership aktif. Mutasi = status lama → `transferred`, entry baru di club tujuan.

#### `club_admins` (Pivot — Scoped Role)

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| club_id | FK → clubs | |
| user_id | FK → users | |
| role | enum(owner, admin) | Owner = pembuat club. Admin = ditunjuk. |
| created_at, updated_at | timestamps | |

> **Unique constraint**: `(club_id, user_id)` — satu user satu role per club.

#### `member_dues` (Iuran)

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| club_member_id | FK → club_members | |
| amount | decimal(12,2) | Nominal iuran |
| period_start | date | Awal periode |
| period_end | date | Akhir periode |
| proof_asset_id | FK → assets, nullable | Bukti pembayaran (gambar) |
| status | enum(pending, verified, rejected) | |
| verified_by | FK → users, nullable | Admin yang memverifikasi |
| verified_at | timestamp, nullable | |
| rejection_reason | text, nullable | |
| created_at, updated_at | timestamps | |

#### `member_achievements` (Prestasi)

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| club_member_id | FK → club_members | |
| title | string | Nama event/prestasi |
| event_date | date | |
| rank | string, nullable | Peringkat (misal: "Juara 1") |
| category | string, nullable | Kategori lomba |
| issuer | string, nullable | Penyelenggara |
| proof_asset_id | FK → assets, nullable | Bukti sertifikat/piagam |
| created_at, updated_at | timestamps | |

#### `mastery_assessments` (Penilaian Kemahiran)

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| club_member_id | FK → club_members | |
| target_level | enum(beginner, intermediate, advanced, master) | Level yang diuji |
| distance | integer | Jarak tembak (meter) |
| score | integer | Skor uji |
| passed | boolean | Lulus/tidak |
| assessed_by | FK → users | Penguji |
| assessed_at | timestamp | |
| notes | text, nullable | |
| created_at | timestamp | |

> **Keputusan: Immutable** — assessment tidak boleh diedit setelah dibuat. Jika salah, buat assessment baru. Ini untuk integritas histori penilaian.

---

### 1.3 Modul: Tournament

#### `tournaments`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| title | string | |
| slug | string, unique | |
| description | text, nullable | |
| banner_path | string, nullable | Banner promo |
| handbook_url | string, nullable | Link rundow/THB |
| location_name | string | Nama tempat |
| location_address | text, nullable | |
| latitude | decimal(10,7), nullable | |
| longitude | decimal(10,7), nullable | |
| contact_info | string, nullable | Kontak panitia |
| registration_fee | decimal(12,2), default 0 | Biaya umum |
| max_participants | integer, nullable | Kuota total |
| registration_opens_at | timestamp | |
| registration_closes_at | timestamp | |
| event_start_date | date | Tanggal mulai tournament |
| event_end_date | date | |
| status | enum — lihat state machine di bawah | |
| created_by | FK → users | |
| deleted_at | timestamp, nullable | **Soft delete** |
| created_at, updated_at | timestamps | |

**State machine tournament**:

```
  draft ──► open_registration ──► registration_closed ──► ongoing ──► completed
    │                                                        │
    └──── (bisa kembali ke draft jika belum ada peserta)     │
                                                             ▼
                                                          cancelled
```

> **Keputusan: `status` sebagai enum di database, bukan tabel terpisah**. State machine sederhana dan final — tidak perlu fleksibilitas runtime. Transisi di-enforce di service layer, bukan di database.

#### `tournament_categories`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| tournament_id | FK → tournaments | |
| name | string | Misal: "Barebow Putra Dewasa 20m" |
| bow_type | enum(barebow, horsebow, recurve, compound, traditional, mixed) | Jenis busur |
| gender | enum(male, female, mixed) | |
| age_min | integer, nullable | Batasan umur minimum (tahun) |
| age_max | integer, nullable | Batasan umur maksimum |
| distance | integer | Jarak tembak (meter) |
| arrows_per_end | integer, default 6 | Jumlah arrow per rambahan |
| total_ends | integer, default 6 | Total rambahan kualifikasi |
| max_participants | integer, nullable | Kuota per kategori |
| registration_fee | decimal(12,2), default 0 | Override fee per kategori |
| scoring_type | enum(qualification, elimination, both) | |
| elimination_size | integer, nullable | Ukuran bracket (8, 16, 32) |
| sort_order | integer, default 0 | Urutan tampilan |
| created_at, updated_at | timestamps | |

> **Keputusan: `arrows_per_end` dan `total_ends` disimpan per kategori**, bukan hardcode. Panahan tradisional Indonesia punya variasi aturan: ada yang 3 arrow per rambahan, ada yang 6. Platform harus fleksibel tanpa perubahan kode.

> **Keputusan: `elimination_size` nullable**. Tidak semua kategori punya babak eliminasi. Beberapa tournament hanya kualifikasi murni (ranking berdasarkan total skor).

#### `tournament_participants`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| tournament_id | FK → tournaments | **Denormalisasi** — bisa di-derive dari category, tapi disimpan untuk query performa |
| tournament_category_id | FK → tournament_categories | |
| user_id | FK → users | |
| club_id | FK → clubs, nullable | Club yang diwakili |
| registration_number | string, nullable | Nomor urut pendaftaran |
| kta_number | string, nullable | **Snapshot** KTA saat mendaftar |
| status | enum(pending, verified, rejected, withdrawn, disqualified) | |
| payment_proof_asset_id | FK → assets, nullable | Bukti bayar |
| recommendation_asset_id | FK → assets, nullable | Surat rekomendasi club |
| verified_by | FK → users, nullable | |
| verified_at | timestamp, nullable | |
| rejection_reason | text, nullable | |
| checked_in | boolean, default false | Absensi hari-H |
| checked_in_at | timestamp, nullable | |
| deleted_at | timestamp, nullable | **Soft delete** |
| created_at, updated_at | timestamps | |

> **Keputusan: `tournament_id` didenormalisasi**. Meskipun bisa di-derive dari `tournament_category_id → tournament_id`, menyimpan langsung memperbolehkan query "semua peserta tournament X" tanpa JOIN. Ini hot query untuk admin dan leaderboard.

> **Keputusan: `kta_number` di-snapshot**. Saat mendaftar, KTA saat ini dicatat. Jika anggota pindah club setelah mendaftar, data pendaftarannya tetap mereferensikan club dan KTA saat daftar — bukan data terkini.

> **Keputusan: Unique constraint `(tournament_category_id, user_id)`**. Satu atlet hanya bisa mendaftar satu kali per kategori. Jika ingin pindah kategori, harus withdraw dan daftar ulang.

#### `tournament_officials` (Pivot — Admin Tournament)

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| tournament_id | FK → tournaments | |
| user_id | FK → users | |
| role | enum(director, admin, chief_judge) | |
| created_at, updated_at | timestamps | |

#### `tournament_targets` (Bantalan & Shoot Order)

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| tournament_category_id | FK → tournament_categories | |
| participant_id | FK → tournament_participants | |
| target_label | string | Kode bantalan: "01A", "01B", "02A" |
| shoot_order | integer | Urutan menembak di bantalan (1, 2, 3, 4) |
| created_at, updated_at | timestamps | |

> **Unique constraint**: `(tournament_category_id, target_label, shoot_order)` — satu posisi di bantalan hanya untuk satu archer.

---

### 1.4 Modul: Scoring — ⚠️ KRITIS

Ini adalah bagian paling penting dalam seluruh dokumen. Desain tabel scoring menentukan keberhasilan atau kegagalan platform.

#### Keputusan Arsitektur Scoring

**Pertanyaan desain #1: Bagaimana menyimpan arrow scores?**

| Opsi | Pro | Kontra |
|------|-----|--------|
| **A: Satu kolom per arrow** (`arrow_1`, `arrow_2`, ..., `arrow_6`) | Query individual arrow mudah, validasi per kolom | Tidak fleksibel jika `arrows_per_end` berubah. 3-arrow vs 6-arrow butuh nullable columns |
| **B: JSONB array** (`arrows jsonb`) | Fleksibel untuk jumlah arrow apapun, satu kolom | Query individual arrow lebih kompleks, validasi di application layer |
| **C: Tabel terpisah** (`score_arrows` — 1 baris per arrow) | Normalisasi penuh, granular | Over-normalized, jumlah baris meledak (200.000 entries × 6 arrows = 1.2 juta baris per tournament) |

**Keputusan: Opsi B — JSONB array.**

Alasan:
1. `arrows_per_end` bervariasi antar kategori (3 atau 6) — JSONB menangani ini tanpa schema change
2. Arrow scores hampir selalu di-read dan di-write sebagai satu unit (satu rambahan) — bukan per-arrow
3. PostgreSQL JSONB di-index dengan GIN jika perlu, tapi kita jarang query "cari semua rambahan yang ada arrow 10"
4. Validasi (range 0-10, X, M; jumlah elemen = arrows_per_end) dilakukan di application layer — ini lebih expressif di PHP daripada di CHECK constraint
5. Volume 200.000 entries per tournament sangat manageable untuk PostgreSQL — JSONB tidak jadi bottleneck

**Risiko opsi ini**: Jika di masa depan butuh analytics per-arrow (misal "rata-rata arrow pertama per rambahan"), query JSONB lebih lambat dari kolom terpisah. **Mitigasi**: ETL/materialized view untuk analytics bisa dibangun nanti jika kebutuhan muncul.

---

**Pertanyaan desain #2: Bagaimana menyimpan dual-entry (scorer + validator)?**

| Opsi | Pro | Kontra |
|------|-----|--------|
| **A: Dua baris terpisah** per rambahan (satu per scorer) | Simple, clear separation, natural untuk conflict detection | Leaderboard harus pilih "baris mana yang dipakai" |
| **B: Satu baris + kolom `validator_*`** | Satu baris per rambahan, simpler query | Kolom jadi banyak, tidak natural jika validator belum input |

**Keputusan: Opsi A — Dua baris terpisah.**

Alasan:
1. Scorer dan validator async (berdasarkan keputusan di user stories) — masing-masing punya `client_ref` sendiri
2. Conflict detection natural: query entries WHERE (participant_id, end_number) GROUP BY HAVING COUNT > 1 AND arrows berbeda
3. Masing-masing punya `device_submitted_at` sendiri — penting untuk audit trail
4. Clean: satu baris = satu submission dari satu manusia

**Implikasi**: Perlu kolom `entry_role` (primary_scorer / validator) dan status resolution di level rambahan.

---

**Pertanyaan desain #3: Denormalisasi leaderboard?**

| Opsi | Pro | Kontra |
|------|-----|--------|
| **A: Hitung on-the-fly dari score_entries** | Selalu akurat, no sync issue | 5.000 concurrent × query aggregasi = DB mati |
| **B: Redis sorted set saja** | Sangat cepat, perfect untuk leaderboard | Jika Redis down, leaderboard hilang. Cold start lambat. |
| **C: Tabel `leaderboard_snapshots` + Redis cache** | Fallback jika Redis down, bisa rebuild | Dua sumber data yang harus konsisten |
| **D: Redis sorted set + recalculate dari score_entries on-demand** | Redis = primary read. Recalculate = fallback dan rebuild. Tidak perlu tabel snapshot. | Rebuild lambat jika score_entries besar |

**Keputusan: Opsi D — Redis sorted set sebagai primary read, score_entries sebagai source of truth.**

Alasan:
1. Redis sorted set native mendukung ranking — `ZADD`, `ZREVRANGE`, `ZRANK` — ideal untuk leaderboard
2. Saat skor baru masuk: update Redis `ZINCRBY` atau `ZADD` dengan skor baru → O(log N), sangat cepat
3. Jika Redis down: fallback ke query aggregasi dari score_entries (lambat tapi benar) — ini graceful degradation
4. Tidak perlu tabel snapshot terpisah yang harus dijaga konsistensinya — mengurangi kompleksitas untuk solo developer
5. Cold start (Redis restart): scheduled job rebuild leaderboard dari score_entries. Untuk 500 peserta, ini selesai dalam <5 detik

**Risiko**: Jika logic update Redis dan score_entries tidak atomic (misal: score tersimpan di DB tapi Redis update gagal), leaderboard jadi stale. **Mitigasi**: Redis update dilakukan di event listener setelah score persist. Jika gagal, queue retry. Worst case: cron job periodic recalculate (tiap 30 detik) sebagai safety net.

---

#### `score_entries` — Skor Kualifikasi

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| tournament_category_id | FK → tournament_categories | Untuk scoping |
| participant_id | FK → tournament_participants | |
| end_number | integer | Rambahan ke-N (1-indexed) |
| arrows | jsonb | Array skor: `[10, 9, 8, "X", 7, "M"]`. X = 10 (tapi dicatat terpisah untuk statistik X-count). M = 0 (miss). |
| end_total | integer | **Denormalisasi**: total skor rambahan ini. Dihitung saat insert untuk cegah perhitungan berulang |
| x_count | integer, default 0 | **Denormalisasi**: jumlah X dalam rambahan ini. Penting untuk tie-break |
| scorer_id | FK → users | Siapa yang menginput |
| entry_role | enum(primary, validator) | Primary scorer atau validator |
| client_ref | UUID, **UNIQUE** | **Idempotency key** dari device client |
| device_submitted_at | timestamp with tz | Waktu input di device (untuk offline-tolerant) |
| server_received_at | timestamp with tz, default now() | Waktu server terima |
| status | enum — lihat flow di bawah | |
| previous_entry_id | FK → score_entries, nullable | **Self-reference** jika ini adalah koreksi dari entry sebelumnya |
| created_at, updated_at | timestamps | |

**Status flow score_entry:**

```
  pending_validation ──── (validator submit skor sama) ────► confirmed
        │                                                        │
        │                (validator submit skor beda)            │
        ▼                                                        │
    disputed ──── (chief judge resolve) ────► overridden         │
        │                                                        │
        │         (timeout — validator tidak submit)             │
        ▼                                                        │
  provisional ──── (validator akhirnya submit, sama) ──► confirmed
```

> **Keputusan: `provisional` status**. Jika hanya satu scorer submit dan validator belum input setelah timeout tertentu (configurable, misal 15 menit), skor tetap masuk leaderboard sebagai "provisional". Ini mencegah leaderboard kosong hanya karena validator lambat. Saat validator akhirnya submit dan cocok, status naik ke `confirmed`.

**Index strategy:**

```sql
-- Hot query: semua skor per peserta (untuk leaderboard recalculate)
CREATE INDEX idx_score_entries_participant 
  ON score_entries (participant_id, end_number) 
  WHERE entry_role = 'primary' AND status IN ('confirmed', 'provisional');

-- Hot query: cek idempotency
CREATE UNIQUE INDEX idx_score_entries_client_ref 
  ON score_entries (client_ref);

-- Hot query: cari disputed scores
CREATE INDEX idx_score_entries_disputed 
  ON score_entries (tournament_category_id) 
  WHERE status = 'disputed';

-- Query: skor per bantalan (scorer view)
CREATE INDEX idx_score_entries_category_end 
  ON score_entries (tournament_category_id, end_number);
```

> **Keputusan: Partial indexes**. Sesuai pola yang sudah ada di starter (lihat `assets` table), partial indexes mengurangi ukuran index dan mempercepat query yang paling sering dijalankan.

#### `score_corrections` (Audit Trail Koreksi)

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| original_entry_id | FK → score_entries | Entry yang dikoreksi |
| corrected_entry_id | FK → score_entries | Entry koreksi (entry baru) |
| reason | text | Alasan koreksi |
| corrected_by | FK → users | Siapa yang melakukan koreksi |
| created_at | timestamp | |

> **Keputusan: Koreksi = entry baru, bukan update in-place.** Entry lama direferensikan via `previous_entry_id` di entry baru, dan detail tercatat di `score_corrections`. Ini memastikan audit trail lengkap — kita selalu bisa lihat "skor awal berapa, diubah jadi berapa, oleh siapa, kapan, kenapa."

---

### 1.5 Modul: Eliminasi / Aduan (Olympic Round)

#### `elimination_brackets`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| tournament_category_id | FK → tournament_categories | Satu bracket per kategori |
| bracket_size | integer | 8, 16, 32 |
| status | enum(generated, in_progress, completed) | |
| generated_at | timestamp | |
| generated_by | FK → users | Admin yang trigger generate |
| created_at, updated_at | timestamps | |

#### `elimination_matches`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| bracket_id | FK → elimination_brackets | |
| round_number | integer | 1 = 16 besar, 2 = 8 besar, 3 = semifinal, 4 = final |
| match_number | integer | Nomor pertandingan dalam round |
| match_type | enum(regular, bronze, final) | Tipe pertandingan |
| participant_a_id | FK → tournament_participants, nullable | Nullable karena bye atau belum ditentukan |
| participant_b_id | FK → tournament_participants, nullable | |
| winner_id | FK → tournament_participants, nullable | Null jika belum selesai |
| loser_id | FK → tournament_participants, nullable | |
| participant_a_set_points | integer, default 0 | Running total set point A |
| participant_b_set_points | integer, default 0 | Running total set point B |
| is_bye | boolean, default false | Peserta A menang by default |
| status | enum(pending, in_progress, completed) | |
| next_match_id | FK → elimination_matches, nullable | **Self-reference** — match mana yang pemenang maju ke |
| created_at, updated_at | timestamps | |

> **Keputusan: `participant_a_set_points` dan `participant_b_set_points` didenormalisasi**. Running total bisa di-derive dari elimination_sets, tapi menyimpan langsung di match mempercepat query bracket view (satu query untuk seluruh bracket) dan mengurangi JOIN.

> **Keputusan: `next_match_id` self-reference** membentuk tree structure bracket. Saat match selesai, pemenang dimasukkan ke `participant_a_id` atau `participant_b_id` di `next_match`. Ini lebih fleksibel daripada hardcode posisi bracket.

#### `elimination_sets`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| match_id | FK → elimination_matches | |
| set_number | integer | Set ke-N (1-5, atau 6 untuk shoot-off) |
| participant_a_arrows | jsonb | Arrow scores peserta A |
| participant_b_arrows | jsonb | Arrow scores peserta B |
| participant_a_total | integer | Total skor set peserta A |
| participant_b_total | integer | Total skor set peserta B |
| set_point_a | integer | 0, 1, atau 2 |
| set_point_b | integer | 0, 1, atau 2 |
| is_shootoff | boolean, default false | |
| shootoff_closest_a | decimal(5,2), nullable | Jarak ke pusat (mm) peserta A — untuk shootoff tiebreak |
| shootoff_closest_b | decimal(5,2), nullable | |
| scorer_id | FK → users | |
| client_ref | UUID, UNIQUE | **Idempotency** — sama seperti score_entries |
| device_submitted_at | timestamp with tz | |
| status | enum(pending, confirmed, disputed) | |
| created_at, updated_at | timestamps | |

> **Keputusan: Scoring eliminasi di tabel terpisah dari kualifikasi.** Meskipun ada overlap (keduanya simpan arrow scores), logika bisnisnya berbeda secara fundamental:
> - Kualifikasi: akumulasi total, ranking banyak peserta
> - Eliminasi: head-to-head, set point system, shootoff
> 
> Menggabungkan dalam satu tabel akan memaksa nullable columns dan conditional logic yang membuat kode sulit dipahami. Separasi lebih jelas.

---

### 1.6 Modul: Regu (Beregu)

#### `tournament_teams`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| tournament_category_id | FK → tournament_categories | Kategori beregu |
| name | string | Nama regu (biasanya nama club/daerah) |
| club_id | FK → clubs, nullable | Club yang diwakili |
| region_id | FK → regions, nullable | Daerah yang diwakili |
| total_score | integer, default 0 | **Denormalisasi** dari total skor anggota |
| rank | integer, nullable | Ranking regu |
| created_at, updated_at | timestamps | |

#### `tournament_team_members`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| team_id | FK → tournament_teams | |
| participant_id | FK → tournament_participants | |
| created_at | timestamp | |

> **Keputusan: Skor regu = SUM skor individu anggota.** Denormalisasi `total_score` di `tournament_teams` untuk leaderboard regu. Update saat skor individu anggota berubah (event listener).

---

### 1.7 Modul: Scorer Assignment

#### `scorer_assignments`

| Atribut | Tipe | Keputusan Desain |
|---------|------|-----------------|
| id | UUID | |
| tournament_id | FK → tournaments | |
| user_id | FK → users | Scorer |
| assignment_role | enum(primary, validator) | Peran di bantalan |
| target_labels | jsonb | Array kode bantalan: `["01A","01B","02A","02B"]` |
| assigned_by | FK → users | Admin yang menunjuk |
| created_at, updated_at | timestamps | |

> **Keputusan: `target_labels` sebagai JSONB array, bukan tabel pivot.** Satu scorer biasanya handle 2-5 bantalan bersebelahan. Menyimpan sebagai array lebih ringkas dan query "apakah scorer X boleh input skor bantalan Y?" cukup dengan `target_labels @> '["01A"]'::jsonb`.
>
> **Risiko jika ini salah**: Jika bantalan di-rename/re-assign, JSONB array harus di-update. Tapi bantalan assignment biasanya fixed setelah hari-H dimulai, jadi risiko rendah.

---

## 2. Entity Relationship Diagram (Teks)

```
┌──────────┐      ┌─────────────┐      ┌──────────────┐
│  users   │──1:N─┤ club_members│──N:1─┤    clubs      │
│          │      │             │      │              │
│          │      └──────┬──────┘      └──────┬───────┘
│          │             │                    │
│          │     ┌───────┴──────┐      ┌──────┴───────┐
│          │     │ member_dues  │      │ club_admins  │
│          │     │ member_achvmt│      │ (user, club) │
│          │     │ mastery_assmt│      └──────────────┘
│          │     └──────────────┘
│          │
│          │──1:N─┤ tournament_participants ├──N:1─┤ tournament_categories │
│          │      │                        │      │                      │
│          │      └────────┬───────────────┘      └──────────┬───────────┘
│          │               │                                 │
│          │      ┌────────┴───────────┐            ┌────────┴──────────┐
│          │      │ tournament_targets │            │   tournaments     │
│          │      │ (bantalan + order) │            │                   │
│          │      └────────────────────┘            └───────────────────┘
│          │
│          │──1:N─┤ score_entries      ├──── scoring kualifikasi
│          │      │ (client_ref UNIQUE)│
│          │      └────────────────────┘
│          │
│          │──1:N─┤ scorer_assignments ├──── assignment ke tournament
│          │      └────────────────────┘
│          │
│          │──1:N─┤ tournament_officials ├── admin tournament
│          │      └─────────────────────┘
└──────────┘

┌──────────────────────┐
│ tournament_categories │
│                      │
└──────────┬───────────┘
           │
    ┌──────┴──────────────┐
    │                     │
┌───┴──────────────┐  ┌──┴─────────────────┐
│elimination_bracket│  │tournament_teams    │
│                  │  │                    │
└───┬──────────────┘  └──┬─────────────────┘
    │                    │
┌───┴──────────────┐  ┌──┴─────────────────┐
│elimination_match │  │tournament_team_    │
│ (A vs B, sets)   │  │ members            │
│ next_match_id ───┤  └────────────────────┘
│ (self-ref tree)  │
└───┬──────────────┘
    │
┌───┴──────────────┐
│elimination_sets  │
│ (arrows, points) │
│ client_ref UNIQUE│
└──────────────────┘
```

---

## 3. Keputusan Desain Cross-Cutting

### 3.1 Soft Delete

| Entitas | Soft Delete? | Alasan |
|---------|-------------|--------|
| users | ✅ Ya | Data historis scoring, keanggotaan direferensikan |
| clubs | ✅ Ya | Data historis anggota, prestasi direferensikan |
| club_members | ✅ Ya | Histori keanggotaan harus tersimpan |
| tournaments | ✅ Ya | Data tournament lama = arsip berharga |
| tournament_participants | ✅ Ya | Data pendaftaran = bukti administratif |
| score_entries | ❌ **TIDAK** | **Tidak pernah dihapus.** Koreksi = entry baru. Status = overridden. |
| elimination_matches | ❌ **TIDAK** | **Tidak pernah dihapus.** Immutable setelah completed. |
| elimination_sets | ❌ **TIDAK** | **Tidak pernah dihapus.** |
| member_dues | ✅ Ya | Bukti transaksi |
| member_achievements | ❌ Tidak | Bisa hard delete jika salah input |
| mastery_assessments | ❌ **Immutable** | Tidak diedit, tidak dihapus |

> **Prinsip**: Data scoring dan eliminasi bersifat **append-only / immutable**. Ini bukan pilihan teknis — ini kebutuhan integritas kompetisi. Jika skor bisa dihapus, siapapun bisa memanipulasi hasil tournament.

### 3.2 Audit Trail

| Entitas | Level Audit | Mekanisme |
|---------|-------------|-----------|
| users | Penuh | spatie/activitylog (sudah ada) |
| clubs | Penuh | spatie/activitylog |
| score_entries | **Khusus** | `previous_entry_id` + `score_corrections` table — lebih detail dari activitylog |
| elimination_sets | Standard | spatie/activitylog |
| tournaments | Penuh | spatie/activitylog |
| tournament_participants | Penuh | spatie/activitylog — status changes penting untuk auditability |

> **Keputusan: Scoring punya audit trail TERPISAH dari spatie activitylog.** Alasan: activitylog generik tidak cukup untuk scoring. Kita butuh: skor sebelum koreksi, skor sesudah, siapa yang koreksi, alasan, dan relationship antar entry. Tabel `score_corrections` lebih expressif untuk use case ini.

### 3.3 Timestamps & Timezone

| Keputusan | Detail |
|-----------|--------|
| Storage | Semua timestamp dalam **UTC** di database |
| Client sends | ISO-8601 dengan timezone offset (`2026-05-25T10:30:00+07:00`) |
| Server converts | Ke UTC saat menyimpan |
| `device_submitted_at` | Disimpan as-is (dengan timezone info) — penting untuk mengetahui "jam berapa di device wasit" |
| `server_received_at` | UTC — `DEFAULT NOW()` di database |
| API returns | ISO-8601 UTC — client convert ke local |

### 3.4 UUID vs Auto-Increment

| Keputusan | Detail |
|-----------|--------|
| Primary key | UUID v7 (time-ordered) untuk semua tabel baru |
| Alasan | Sudah jadi standar di starter. Anti-enumeration. Client bisa generate ID sebelum sync (penting untuk offline-tolerant). Ordered by time (v7) sehingga index B-tree efisien. |
| Trade-off | UUID 16 bytes vs bigint 8 bytes — storage lebih besar, tapi untuk volume data Manahpro (ratusan ribu baris, bukan miliaran) ini tidak material |

### 3.5 JSONB Usage Policy

| Kolom JSONB | Justifikasi | Di-index? |
|-------------|-------------|-----------|
| `score_entries.arrows` | Variabel jumlah arrow (3/6 per end) | Tidak — selalu di-read as whole |
| `elimination_sets.participant_a_arrows`, `participant_b_arrows` | Sama — variabel arrows | Tidak |
| `scorer_assignments.target_labels` | Array bantalan per scorer | Ya — GIN `@>` untuk permission check |

> **Policy**: JSONB digunakan HANYA untuk data yang:
> 1. Bervariasi strukturnya antar record (arrows per end), ATAU
> 2. Selalu dibaca/ditulis sebagai satu unit (bukan di-query per elemen)
> 
> JSONB TIDAK digunakan untuk data yang perlu di-filter/sort/join secara rutin. Data relasional tetap di kolom biasa.

---

## 4. Estimasi Volume Data (Per Tournament Besar)

| Tabel | Estimasi Rows per Tournament | Estimasi Rows per Tahun (20 tournament) |
|-------|----------------------------|---------------------------------------|
| tournament_participants | 500 | 10.000 |
| tournament_targets | 500 | 10.000 |
| score_entries | 500 × 40 ends × ~1.5 (scorer+validator) = 30.000 | 600.000 |
| elimination_matches | ~60 (32-bracket per kategori × 2 kategori) | 1.200 |
| elimination_sets | ~300 (60 matches × 5 sets) | 6.000 |
| tournament_teams | ~50 | 1.000 |

> **Kesimpulan**: Bahkan dalam 5 tahun, total rows terbesar (score_entries) akan ~3 juta. PostgreSQL menangani ini **tanpa masalah** dengan indexing yang benar. Tidak perlu partitioning, sharding, atau arsitektur eksotis. Ini konfirmasi bahwa keputusan arsitektur sederhana (single PostgreSQL) benar untuk skala ini.

---

## 5. Catatan Konsistensi dengan Dokumen Sebelumnya

| Keputusan ERD | Referensi | Konsistensi |
|---------------|-----------|-------------|
| `client_ref UUID UNIQUE` di score_entries dan elimination_sets | Analisis Offline §C1 | ✅ Idempotency key dari analisis offline diimplementasikan |
| `device_submitted_at` di score_entries | Analisis Offline §C3 | ✅ Timestamp device untuk ordering dan audit |
| `status` flow (pending → confirmed/disputed → overridden) | Analisis Offline §C2, US-SC-04 | ✅ Flag & Escalate pattern |
| `entry_role` (primary/validator) + dua baris per rambahan | US-SC-01, US-SC-04 | ✅ Dual-entry async validation |
| `scorer_assignments` dengan target_labels | US-AT-06 | ✅ Scorer scoped ke bantalan spesifik |
| Leaderboard via Redis sorted set (bukan tabel) | PRD T-05, T-11 | ✅ Tidak ada tabel leaderboard — Redis = primary, score_entries = rebuild source |
| Denormalisasi `tournament_id` di participants | PRD constraint performa | ✅ Hot query optimization |
| `provisional` status jika validator timeout | US-SC-04 AC4 | ✅ Skor tetap masuk leaderboard meski belum validated |

### Implikasi ke Dokumen Selanjutnya

| Keputusan ERD | Implikasi di Arsitektur (04) |
|---------------|-------------------------------|
| Redis sorted set untuk leaderboard | Perlu arsitektur event-driven: score persist → event → Redis update |
| Scoring append-only + corrections table | Service layer harus implement "edit as new entry" pattern |
| Scoped roles via pivot tables | Middleware/policy harus check pivot, bukan hanya spatie role |
| JSONB arrows | Validasi di PHP, bukan database constraint |
| Dual-entry comparison | Service layer: setelah score persist, cek apakah ada entry counterpart, compare, update status |
| Tournament state machine | Service layer: state transition validation + guard against illegal transitions |
