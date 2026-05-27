# Analisis Offline-First — Fondasi Keputusan Arsitektur

> **Status**: Dokumen keputusan arsitektur — menjadi referensi untuk seluruh dokumen planning (01–07).
> **Tanggal**: 2026-05-25
> **Penulis**: Architecture Review

---

## Proses Berpikir Awal

Sebelum melompat ke kesimpulan, saya perlu menantang satu asumsi mendasar: **"apakah offline-first benar-benar dibutuhkan, atau ini solusi yang sedang mencari masalah?"**

Konteksnya spesifik: tournament panahan. Tempat-tempat tournament sering berada di lapangan terbuka — stadion, lapangan outdoor, area pedesaan. Sinyal seluler bisa sangat buruk. Ini bukan asumsi hipotetis; ini realitas lapangan yang saya harus anggap serius.

Tetapi "offline-first" adalah spektrum, bukan binary. Ada perbedaan besar antara:
- **Offline-first penuh** (seperti CouchDB/PouchDB — semua data lokal, sync bilateral)
- **Offline-tolerant** (queue operasi, retry saat koneksi kembali)
- **Online-only dengan graceful degradation** (tampilkan pesan error yang baik)

Pertanyaan yang tepat bukan "apakah kita butuh offline?" melainkan **"untuk siapa, untuk operasi apa, dan seberapa kritis?"**

---

## Bagian A — Pemetaan Kebutuhan per Aktor

### 1. Wasit / Scorer ⚠️ KRITIS

| Aspek | Analisis |
|-------|---------|
| **Operasi utama** | Input skor per arrow (write-heavy), validasi skor |
| **Lokasi kerja** | Di lapangan, di samping bantalan — area paling mungkin sinyal buruk |
| **Konsekuensi jika offline tanpa mitigasi** | **FATAL** — tournament terhenti. Peserta menunggu. Jadwal berantakan. Reputasi platform hancur di hari pertama. |
| **Volume data per operasi** | Sangat kecil (beberapa byte: archer_id, arrow_scores, rambahan/end, timestamp) |
| **Frekuensi** | Setiap 2-5 menit per bantalan (setiap rambahan selesai) |
| **Kebutuhan offline** | **WAJIB** — bukan optional |

**Analisis mendalam**: Seorang wasit di bantalan menangani ~6 archer per bantalan, setiap rambahan 3-6 arrow. Dalam satu hari tournament, bisa ada 20-40 rambahan. Jika koneksi putus 10 menit di jam sibuk, itu berarti 2-5 rambahan data yang harus bisa di-buffer di client dan dikirim saat koneksi kembali.

**Edge case kritis**: 
- Dua wasit di bantalan yang sama (wasit utama + validator) sama-sama offline, lalu submit data yang berbeda saat koneksi kembali.
- Wasit menginput skor, koneksi putus, dia koreksi skor di device, koneksi kembali — mana yang dianggap valid?

### 2. Admin Tournament

| Aspek | Analisis |
|-------|---------|
| **Operasi utama** | Setup tournament (sebelum hari-H), generate bantalan, kelola bagan aduan, verifikasi peserta |
| **Lokasi kerja** | Biasanya di meja panitia — lebih mungkin ada WiFi/sinyal |
| **Konsekuensi jika offline** | **MEDIUM** — Setup bisa dilakukan sebelumnya. Saat hari-H, yang kritis hanya generate bagan aduan (perlu data skor final kualifikasi). |
| **Kebutuhan offline** | **TIDAK WAJIB** untuk setup. **NICE-TO-HAVE** untuk operasi hari-H. |

**Pemikiran kritis**: Admin tournament biasanya punya laptop + koneksi lebih stabil. Yang perlu dipersiapkan: jika koneksi admin putus saat harus generate bagan aduan dari hasil kualifikasi, ini blocking. Tapi ini bisa dimitigasi dengan cara lain (hotspot cadangan, pre-compute).

### 3. Atlet / Anggota Club

| Aspek | Analisis |
|-------|---------|
| **Operasi utama** | Lihat jadwal, cek skor sendiri, daftar tournament, lihat profil |
| **Lokasi kerja** | Di lapangan (saat bertanding) atau di mana saja |
| **Konsekuensi jika offline** | **LOW** — Tidak ada write-operation kritis. Mereka menunggu giliran menembak, bukan menginput data. |
| **Kebutuhan offline** | **TIDAK WAJIB** — Read cache di mobile app sudah cukup. |

**Nuansa penting**: Atlet yang baru selesai menembak ingin lihat skornya. Jika app tidak bisa load, ini frustrasi tapi bukan show-stopper. Mereka bisa lihat skor di papan fisik atau tanya wasit.

### 4. Penonton / Publik

| Aspek | Analisis |
|-------|---------|
| **Operasi utama** | Lihat live leaderboard, lihat jadwal, lihat hasil |
| **Lokasi kerja** | Dari mana saja — termasuk di tribun stadion |
| **Konsekuensi jika offline** | **MINIMAL** — Mereka hanya consume data. Tidak ada dampak operasional tournament. |
| **Kebutuhan offline** | **TIDAK PERLU** — Standard HTTP caching cukup. |

**Catatan performa**: Volume tertinggi (1.000-5.000 concurrent) datang dari aktor ini. Masalahnya bukan offline — masalahnya **scalability read path**. Ini akan dibahas mendalam di dokumen arsitektur.

### 5. Super Admin

| Aspek | Analisis |
|-------|---------|
| **Operasi utama** | Konfigurasi platform, audit, manajemen global |
| **Lokasi kerja** | Kantor/remote — koneksi stabil |
| **Konsekuensi jika offline** | **NEGLIGIBLE** — Operasinya tidak time-sensitive terhadap tournament. |
| **Kebutuhan offline** | **TIDAK PERLU** |

### Ringkasan Bagian A

```
┌─────────────────────┬──────────────────┬──────────────────────────┐
│ Aktor               │ Urgensi Offline  │ Justifikasi              │
├─────────────────────┼──────────────────┼──────────────────────────┤
│ Wasit/Scorer        │ ██████████ WAJIB │ Write path, sinyal buruk │
│ Admin Tournament    │ ████░░░░░░ LOW   │ Bisa mitigasi lain       │
│ Atlet               │ ███░░░░░░░ LOW   │ Read-only, non-blocking  │
│ Penonton            │ █░░░░░░░░░ NONE  │ Pure read, caching       │
│ Super Admin         │ ░░░░░░░░░░ NONE  │ Non-time-sensitive       │
└─────────────────────┴──────────────────┴──────────────────────────┘
```

**Kesimpulan Bagian A**: Hanya **Wasit/Scorer** yang benar-benar memerlukan kemampuan offline. Untuk aktor lain, ini nice-to-have yang tidak worth the complexity.

---

## Bagian B — Evaluasi Dua Pendekatan

### Pendekatan 1: Offline-First Penuh

**Definisi**: Seluruh data domain (skor, peserta, bantalan) disimpan di local database di device. Semua operasi dilakukan di local-first, lalu di-sync ke server. Server dan client bisa diverge dan di-reconcile.

**Apa yang didapat:**
- Pengalaman pengguna seamless — app selalu responsif
- Zero downtime dependency untuk input skor
- Data integrity di level client

**Apa yang harus dikorbankan:**

| Pengorbanan | Detail | Dampak untuk Solo Developer |
|-------------|--------|---------------------------|
| **Kompleksitas sync engine** | Harus bangun conflict resolution, version vectors, merge logic | ⛔ 3-6 bulan kerja tambahan |
| **Dual source of truth** | Setiap entitas harus punya state lokal + remote + conflict state | ⛔ Bug surface area berlipat |
| **Testing** | Harus test: offline→online, online→offline, conflict, partial sync, data corruption | ⛔ Test matrix meledak |
| **Schema evolution** | Perubahan database harus di-handle di client DAN server | ⛔ Setiap migrasi jadi 2x effort |
| **Client complexity** | Mobile app harus punya SQLite/Hive + sync daemon + conflict UI | ⛔ Mobile dev effort naik drastis |

**Teknologi yang diperlukan:**
- Client: SQLite/Hive + sync engine kustom, ATAU CouchDB/PouchDB (tapi ini bukan stack Laravel)
- Server: Endpoint sync/merge, conflict detection, tombstone tracking
- Protocol: Kemungkinan perlu sesuatu seperti CRDT atau OT

**Verdict untuk solo developer**: ⛔ **TIDAK REALISTIS.** Ini project sendiri yang bisa makan 6+ bulan. Untuk full offline-first yang benar, perusahaan besar pun struggle (lihat: Google Docs offline mode butuh tim dedicated bertahun-tahun).

### Pendekatan 2: Offline-Tolerant (Queue Lokal + Retry + Sync)

**Definisi**: App tetap online-first, TAPI untuk operasi kritis (input skor wasit), mobile app mem-buffer operasi di queue lokal jika koneksi gagal, dan auto-retry saat koneksi kembali. Server tetap single source of truth.

**Apa yang didapat:**

| Benefit | Detail |
|---------|--------|
| **Skor tidak hilang** | Arrow scores di-queue di device, auto-sync saat online |
| **Wasit tetap kerja** | UI tetap responsif, input skor tanpa blocking |
| **Implementasi terfokus** | Hanya perlu queue + retry untuk 1-2 endpoint kritis |
| **Server tetap simple** | Server tidak perlu merge engine — hanya perlu idempotent endpoints |
| **Testable** | Skenario test terbatas dan jelas |

**Apa yang harus dikorbankan:**

| Pengorbanan | Detail | Severitas |
|-------------|--------|-----------|
| **Leaderboard delayed** | Saat wasit offline, leaderboard publik tidak update real-time | ⚠️ MEDIUM — tapi publik tidak tahu ada delay |
| **Partial view** | Wasit tidak bisa lihat skor bantalan lain saat offline | ⚠️ LOW — wasit hanya perlu lihat bantalan sendiri |
| **Conflict scope terbatas** | Jika dua wasit input skor yang berbeda untuk arrow yang sama, server harus pilih salah satu | ⚠️ MEDIUM — bisa mitigasi dengan desain |
| **Bukan true offline-first** | App tidak fully functional offline — hanya scoring yang bisa | ✅ Acceptable |

**Implementasi yang diperlukan:**

```
┌─────────────────────────────────────────────────────────────┐
│                    MOBILE APP (Flutter)                       │
│                                                              │
│  ┌─────────────┐    ┌──────────────┐    ┌────────────────┐  │
│  │ Scoring UI  │───►│ Local Queue  │───►│ Sync Manager   │  │
│  │             │    │ (SQLite/Hive)│    │ (retry + dedup)│  │
│  └─────────────┘    └──────────────┘    └───────┬────────┘  │
│                                                  │           │
└──────────────────────────────────────────────────┼───────────┘
                                                   │ HTTPS
                                                   ▼
┌──────────────────────────────────────────────────────────────┐
│                    LARAVEL API                                │
│                                                              │
│  ┌────────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │ Score Endpoint │───►│ Idempotency  │───►│ Score Service │ │
│  │ (POST /scores) │    │ Check        │    │ (validate +  │ │
│  │                │    │ (client_ref) │    │  persist)    │ │
│  └────────────────┘    └──────────────┘    └──────────────┘ │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**Estimasi effort tambahan:**
- Mobile: ~2-3 minggu (queue manager + connectivity listener + retry logic)
- Backend: ~1 minggu (idempotency key handling + conflict detection endpoint)
- Testing: ~1 minggu (skenario offline-online transition)
- **Total: ~4-5 minggu** — sangat manageable untuk solo developer

### Batas Kecukupan Offline-Tolerant

**Di mana cukup:**
- ✅ Wasit input skor saat sinyal putus 5-30 menit
- ✅ Auto-sync tanpa intervensi manual saat sinyal kembali
- ✅ Tidak ada data skor yang hilang
- ✅ Leaderboard otomatis update setelah sync

**Di mana TIDAK cukup (dan kenapa ini acceptable):**
- ❌ Wasit tidak bisa lihat leaderboard real-time saat offline → **Acceptable**: wasit tidak butuh leaderboard, dia butuh input skor
- ❌ Admin tidak bisa generate bagan aduan saat offline → **Acceptable**: ini operasi rare, bisa tunggu koneksi
- ❌ Jika offline berlangsung >2 jam, queue bisa besar → **Acceptable**: ini edge case ekstrem yang bisa di-handle dengan UX warning

---

## Bagian C — Implikasi ke Arsitektur Backend

Jika kita menerapkan **offline-tolerant untuk wasit**, berikut perubahan konseptual di sisi server:

### C1. Idempotency — Membedakan Data Baru vs Retry

**Masalah**: Client retry skor yang sama karena tidak dapat konfirmasi response. Server harus tahu: "ini skor baru atau duplikat?"

**Solusi: Client-Generated Reference ID (Idempotency Key)**

```
Setiap submission skor dari client menyertakan:
{
  "client_ref": "uuid-v4-generated-by-client",
  "target_id": 42,        // bantalan
  "archer_id": 101,
  "end_number": 3,        // rambahan ke-3
  "arrows": [10, 9, 8, 10, 7, 9],
  "submitted_at": "2026-05-25T10:30:00+07:00"  // waktu input di device
}
```

**Di server:**
1. Cek apakah `client_ref` sudah ada di database
2. Jika **sudah ada**: return response sukses tanpa insert ulang (idempotent)
3. Jika **belum ada**: validasi, simpan, return sukses

**Desain database:**
```sql
-- Kolom client_ref di tabel scores/score_submissions
client_ref UUID UNIQUE NOT NULL,
device_submitted_at TIMESTAMP WITH TIME ZONE NOT NULL,
server_received_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
sync_status VARCHAR(20) DEFAULT 'synced'  -- 'synced', 'conflict', 'rejected'
```

**Risiko jika ini salah**: Jika idempotency key tidak di-enforce dengan benar (misal: unique constraint dihapus saat migrasi), skor bisa terduplikasi. Ini bisa mengacaukan total skor archer. **Mitigasi**: unique constraint di level database + validasi di service layer + monitoring duplikat.

### C2. Conflict Resolution — Dua Wasit Input Data Berbeda

**Skenario**: Bantalan 01, Archer A, Rambahan 3.
- Wasit 1 (scorer) input: [10, 9, 8, 10, 7, 9] (offline, submit 10:35)
- Wasit 2 (validator) input: [10, 9, 8, 10, 8, 9] (offline, submit 10:36)
- Arrow ke-5 berbeda: 7 vs 8

**Ini bukan edge case — ini business rule panahan**: Dua wasit SEHARUSNYA input skor yang sama karena mereka melihat target yang sama. Perbedaan berarti ada kesalahan manusia yang harus di-resolve oleh chief judge.

**Strategi yang saya rekomendasikan: Flag & Escalate**

```
┌────────────────────────────────────────────────────────────┐
│                   CONFLICT RESOLUTION FLOW                  │
│                                                            │
│  Wasit 1 submit ──►┐                                      │
│                     ├──► Server membandingkan              │
│  Wasit 2 submit ──►┘                                      │
│                          │                                 │
│                    ┌─────┴──────┐                          │
│                    │  Match?    │                          │
│                    └─────┬──────┘                          │
│                     YES  │  NO                             │
│                    ┌─────┴──────┐                          │
│              Auto-confirm   Flag as DISPUTED               │
│              score          + notify admin/chief judge      │
│                             + hold from leaderboard        │
│                                    │                       │
│                              Chief Judge resolves          │
│                              via admin panel               │
│                                    │                       │
│                              Score confirmed               │
│                              + leaderboard updated         │
└────────────────────────────────────────────────────────────┘
```

**Implikasi backend:**
- Tabel `score_entries` perlu field: `status` (pending/confirmed/disputed/overridden)
- Setiap entry menyimpan `scorer_id` — siapa yang input
- Jika ada 2 entry untuk (target_id, archer_id, end_number) yang nilainya berbeda → status = 'disputed'
- API endpoint untuk chief judge/admin: resolve dispute, pilih skor yang benar

### C3. Ordering & Consistency

**Masalah**: Skor dari wasit yang offline bisa sampai ke server out-of-order. Rambahan 5 tiba sebelum Rambahan 3.

**Implikasi**: Server TIDAK boleh asumsikan data datang berurutan. Setiap entry harus self-contained:
- `end_number` (rambahan ke berapa) — eksplisit, bukan derived
- `device_submitted_at` — timestamp dari device wasit (untuk audit trail)
- `server_received_at` — timestamp saat server terima (untuk ordering saat out-of-order)

**Leaderboard calculation**: Selalu dihitung dari semua skor yang ada, bukan incremental. Ini lebih aman meskipun sedikit lebih berat secara komputasi — dan kita bisa cache hasilnya di Redis.

### C4. Dampak ke Arsitektur API

| Aspek | Tanpa Offline-Tolerant | Dengan Offline-Tolerant |
|-------|----------------------|------------------------|
| **Score endpoint** | Simple POST | POST + idempotency check + conflict detection |
| **Database schema** | Basic score table | + client_ref, device_submitted_at, sync_status, scorer_id |
| **Validation flow** | Single-pass | Dual-entry comparison (scorer + validator) |
| **Admin panel** | Lihat skor | + Dispute resolution UI |
| **Event/notification** | Score saved | + Conflict alert ke admin |
| **API response** | `{ success: true }` | + `sync_status`, `conflicts: [...]` jika ada |

---

## Bagian D — Rekomendasi Final

### Rekomendasi: OFFLINE-TOLERANT untuk Wasit, ONLINE-ONLY untuk yang lain

**Tingkat keyakinan: 85% — saya cukup yakin, tapi tidak 100%.**

**Kenapa 85% dan bukan 100%?** Ada satu skenario yang saya belum sepenuhnya yakin:

> Jika tournament besar (500+ peserta, 50+ bantalan) dan SEMUA area lapangan kehilangan sinyal seluler selama 1+ jam — apakah offline-tolerant cukup? 

Secara teknis, ya — queue di device bisa buffer ribuan entry. Tapi secara UX, admin yang menunggu skor masuk untuk generate bagan aduan eliminasi akan frustasi. Ini bukan masalah teknis; ini masalah **ekspektasi manusia** yang sulit di-solve dengan kode.

**Mitigasi untuk skenario ini**: Sediakan WiFi lokal di area panitia + lapangan sebagai backup. Ini solusi infrastruktur, bukan software.

### Apa yang saya rekomendasikan TIDAK dilakukan:

1. **❌ Jangan bangun sync engine kustom** — ROI tidak sebanding untuk solo developer
2. **❌ Jangan pakai CouchDB/PouchDB** — Ini mengubah seluruh stack dan arsitektur
3. **❌ Jangan buat offline mode untuk penonton** — Mereka bisa refresh nanti
4. **❌ Jangan buat offline mode untuk admin panel** — Admin harus punya koneksi

### Apa yang saya rekomendasikan DILAKUKAN:

1. **✅ Queue lokal di mobile app (Flutter)** untuk scoring endpoint
2. **✅ Idempotency key (client_ref)** di setiap score submission
3. **✅ Conflict detection** di server saat dua wasit submit skor berbeda
4. **✅ Dispute resolution workflow** untuk admin/chief judge
5. **✅ device_submitted_at timestamp** untuk audit trail dan ordering
6. **✅ Connectivity indicator** di app wasit — biar wasit tahu status koneksi
7. **✅ Background sync** dengan exponential backoff retry

### Dampak ke Seluruh Dokumen Planning

Keputusan ini akan mewarnai:

| Dokumen | Dampak |
|---------|--------|
| **01-PRD** | Constraint: offline-tolerant (bukan offline-first). Scope: hanya scoring endpoint. |
| **02-User Stories** | Tambah story: wasit input skor saat offline, conflict resolution |
| **03-ERD** | Tambah field: client_ref, device_submitted_at, sync_status, scorer_id di tabel scoring |
| **04-Architecture** | Sync flow diagram, idempotency layer, conflict detection service |
| **05-Timeline** | Tambah ~4-5 minggu untuk implementasi offline-tolerant |
| **06-Risk** | Risiko: data loss saat sync, conflict yang tidak ter-resolve, queue overflow |
| **07-Testing** | Skenario test: offline→online, duplicate submission, conflict detection |

---

## Asumsi yang Saya Buat (Eksplisit)

1. **Mobile app dibangun dengan Flutter** — berdasarkan dokumen arsitektur existing yang menyebut Flutter
2. **Satu wasit per bantalan sebagai primary scorer**, bisa ada wasit kedua sebagai validator — berdasarkan use case UC-12
3. **Tournament panahan Indonesia** — lapangan terbuka, sinyal seluler tidak reliable
4. **Solo developer** — tidak ada tim mobile terpisah; offline queue di Flutter harus dibangun sendiri juga
5. **PostgreSQL sebagai single source of truth** — tidak ada distributed database di client
6. **Redis tersedia** — untuk caching leaderboard (sudah ada di compose.yaml)
7. **Volume skor per tournament**: ~200-500 peserta × 20-40 rambahan × 3-6 arrow = 12.000-120.000 entry skor per tournament. Ini bukan big data — PostgreSQL bisa handle ini tanpa masalah.

---

> **Catatan untuk solo developer**: Offline-tolerant ini menambah ~4-5 minggu ke timeline, tapi ini BUKAN optional. Tanpa ini, tournament pertama di lapangan dengan sinyal buruk akan menjadi bencana reputasi. Investasi ini worth it.
