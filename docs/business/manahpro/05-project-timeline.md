# 05 — Project Timeline

> **Project**: Manahpro — Platform Tata Kelola Tournament & Scoring Panahan
> **Versi**: 1.0
> **Tanggal**: 2026-05-25
> **Referensi**: [01-PRD.md](./01-PRD.md) (MoSCoW priorities), [04-architecture-plan.md](./04-architecture-plan.md) (arsitektur)
> **Asumsi**: Solo developer, full-time (40 jam/minggu), familiar dengan Laravel

---

## Proses Berpikir: Pertanyaan Pemantik

### Di phase mana risiko terbesar bagi solo developer?

| Phase | Risiko | Severity | Alasan |
|-------|--------|----------|--------|
| Phase 1: Foundation | 🟢 Low | Sudah ada starter, banyak yang re-use | Setup infra dan boilerplate = familiar territory |
| Phase 2: Core Modules | 🟡 Medium | CRUD standard, tapi volume banyak | Risk = burnout karena repetitive. Club, member, tournament CRUD banyak tapi straightforward. |
| **Phase 3: Tournament & Scoring** | **🔴 HIGH** | **Logika bisnis paling kompleks** | Scoring engine, eliminasi bracket, offline-tolerant, leaderboard caching — ini semua "unknown unknowns" yang bisa blow up. |
| Phase 4: Load Testing | 🟡 Medium | Mungkin menemukan masalah arsitektur | Jika load test gagal, harus refactor — ini bisa makan waktu tidak terbatas. |
| Phase 5: Deployment | 🟡 Medium | Infra bisa unpredictable | Server config, SSL, backup, monitoring — banyak hal kecil yang bisa salah. |

**Kesimpulan**: **Phase 3 adalah make-or-break**. Jika scoring engine dan leaderboard tidak solid, produk tidak bisa dipakai. Timeline Phase 3 harus paling konservatif.

### Apa yang harus sudah selesai sebelum tournament pertama?

**Semua item MUST dari PRD (M1–M13)**. Secara spesifik:
1. ✅ Auth + RBAC berfungsi penuh
2. ✅ Club + Member management (minimal: CRUD + verifikasi)
3. ✅ Tournament setup (kategori, bantalan, peserta)
4. ✅ Scoring engine (input, idempotency, offline queue)
5. ✅ Dual-entry validation (scorer + validator)
6. ✅ Leaderboard (cached, performant)
7. ✅ Bagan eliminasi (generate, skor aduan)
8. ✅ Dispute resolution (flag + admin resolve)
9. ✅ Load test lulus (p95 <500ms pada 3.000+ concurrent)
10. ✅ Backup & recovery terverifikasi
11. ✅ Monitoring aktif

SHOULD items (S1–S8) **boleh belum ada** untuk tournament pertama — tapi sebaiknya S1 (regu) dan S5 (notifikasi) sudah ready.

---

## Estimasi Effort & Pendekatan

### Filosofi Estimasi

Saya menggunakan pendekatan **"double-then-add-buffer"** untuk estimasi solo developer:

1. Estimasi optimis (jika semua berjalan lancar)
2. Double (karena solo = tidak ada peer review, debug sendiri, context switch)
3. Add 20% buffer (unexpected issues, sakit, burnout recovery)

### Asumsi Kapasitas

| Faktor | Nilai |
|--------|-------|
| Jam kerja/minggu (effective coding) | ~30 jam (dari 40 jam — sisanya: research, debug, admin) |
| Sprint length | 2 minggu |
| Vacation/sick buffer | 2 minggu total selama project |
| Learning curve untuk fitur baru (misal: elimination bracket logic) | Built-in ke estimasi per task |

---

## Timeline Phase-Based

### Ringkasan

```
┌─────────────────────────────────────────────────────────────────────┐
│                        TOTAL: ~22-26 MINGGU                         │
│                                                                      │
│  Phase 1 ░░░░                                                        │
│  Foundation    [3 minggu]                                            │
│                                                                      │
│  Phase 2 ▓▓▓▓▓▓▓▓                                                   │
│  Core Modules  [6-7 minggu]                                          │
│                                                                      │
│  Phase 3 ████████████                                                │
│  Tournament    [8-10 minggu] ← paling besar & berisiko               │
│  & Scoring                                                           │
│                                                                      │
│  Phase 4 ▓▓▓▓                                                        │
│  Hardening     [3-4 minggu]                                          │
│                                                                      │
│  Phase 5 ░░░░                                                        │
│  Deployment    [2-3 minggu]                                          │
│                                                                      │
│                ▲ MVP Ready                    ▲ Go-Live               │
│                (Phase 2 done)                 (Phase 5 done)         │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Phase 1: Foundation & Setup [3 Minggu]

**Tujuan**: Lingkungan dev siap, arsitektur dasar terkonfirmasi, infrastruktur development berjalan.

**Risiko phase ini**: Low — sebagian besar sudah ada dari starter.

| Minggu | Task | Detail | Deliverable | Estimasi |
|--------|------|--------|-------------|----------|
| **1** | **Setup & Migrasi Arsitektur** | | | |
| | Rename project, konfigurasi ulang .env | Dari circlepro-web ke manahpro namespace | Project berjalan di lokal | 0.5 hari |
| | Review & extend RBAC | Tambah role: pengurus, admin-club, admin-tournament, scorer, athlete | Seeder role baru, test | 1 hari |
| | Setup database schema (migrasi awal) | Tabel: clubs, club_members, club_admins berdasarkan ERD | Migrasi berjalan, rollback berjalan | 1.5 hari |
| | API versioning confirmation | Pastikan /api/v1 convention konsisten | Routes terstruktur | 0.5 hari |
| **2** | **Database Schema Lanjutan** | | | |
| | Migrasi tabel tournament | tournaments, tournament_categories, tournament_participants, tournament_targets | Migrasi + model + factory | 2 hari |
| | Migrasi tabel scoring | score_entries, score_corrections, elimination_brackets, elimination_matches, elimination_sets | Migrasi + model + factory + partial indexes | 2.5 hari |
| **3** | **Infrastruktur Pendukung** | | | |
| | Setup Redis integration | Konfigurasi cache, queue, leaderboard sorted set skeleton | Redis berjalan di dev, basic commands work | 1 hari |
| | Setup testing infrastructure | PHPUnit config, test database, factory seeder untuk scoring | Test suite berjalan, sample test pass | 1 hari |
| | Dokumentasi API awal (Scribe/manual) | Endpoint list awal untuk development reference | Dokumen API v0.1 | 1 hari |
| | Code quality gates | Pint + Larastan config, CI pipeline update | CI pipeline green | 0.5 hari |

**Deliverable Phase 1:**
- [x] Project berjalan di lokal dengan semua dependensi
- [x] Semua migrasi database dari ERD ter-implementasi
- [x] RBAC extended dengan role baru
- [x] Redis, queue, testing infrastructure ready
- [x] CI pipeline green

---

### Phase 2: Core Modules [6-7 Minggu]

**Tujuan**: CRUD dan business logic untuk Club, Member, dan Tournament management. Belum termasuk scoring.

**Risiko phase ini**: Medium — volume CRUD banyak, bisa burnout.

**Strategi anti-burnout**: Mulai dari modul paling simpel (Club CRUD), progressively ke yang lebih kompleks (Tournament setup). Setiap modul = selesaikan API + test sebelum pindah ke modul berikutnya.

| Minggu | Task | Detail | Estimasi |
|--------|------|--------|----------|
| **4-5** | **Modul Club & Anggota** | | **2 minggu** |
| | Club CRUD API | Create, Read, Update, Soft Delete. Filter by region, status. | 2 hari |
| | Club registration flow | Submit → pending → Super Admin approve/reject | 1 hari |
| | Club admin pivot (scoped role) | club_admins table, middleware untuk scope-check | 1 hari |
| | Member CRUD API | Join club, approve/reject, KTA generation, mutasi | 3 hari |
| | Member dues (iuran) API | Submit bukti bayar, verify, extend masa aktif | 1 hari |
| | Prestasi & kemahiran API | CRUD achievements, mastery assessments | 1 hari |
| | Tests: Club & Member | Feature tests untuk semua flow, RBAC test per role | 1 hari |
| **6-7** | **Modul Tournament Management** | | **2 minggu** |
| | Tournament CRUD API | Create, update, status transitions (state machine) | 2 hari |
| | Tournament categories API | CRUD kategori lomba, validasi konfigurasi | 1 hari |
| | Participant registration API | Atlet daftar, upload dokumen, status flow | 2 hari |
| | Participant verification API | Admin verify/reject, batch verify | 1 hari |
| | Target/bantalan assignment API | Auto-assign algorithm + manual override | 2 hari |
| | Tournament officials & scorer assignment | Pivot tables, scoped permission | 1 hari |
| | Tests: Tournament | Feature tests semua flow, edge cases (kuota penuh, batas pendaftaran lewat) | 1 hari |
| **8-9** | **Modul Pengurus & Admin Panel** | | **2 minggu** |
| | Pengurus API | Monitor club wilayah, validasi club, lihat anggota | 2 hari |
| | Filament resources: Club, Member | CRUD admin panel untuk Super Admin | 2 hari |
| | Filament resources: Tournament, Participant | Admin panel tournament management | 2 hari |
| | Filament resources: Scorer Assignment, Officials | Admin panel untuk assign scorer | 1 hari |
| | Integration tests + RBAC tests | End-to-end RBAC: setiap role coba akses semua endpoint | 1.5 hari |
| | Bug fixes & refactor | Buffer untuk tech debt dari Phase 2 | 1.5 hari |
| **10** | **Notifikasi & Polish** | | **1 minggu** |
| | FCM notification integration | Tournament events: registration, verification, jadwal | 2 hari |
| | Email notifications | Backup channel: verifikasi, password reset | 1 hari |
| | API documentation update | Scribe generate / manual update untuk semua endpoint Phase 2 | 1 hari |
| | Phase 2 review & stabilization | Bug fix, edge case handling, test coverage check | 1 hari |

**Deliverable Phase 2:**
- [x] Club management end-to-end (CRUD, registration, members, dues)
- [x] Tournament management end-to-end (CRUD, categories, registration, verification, bantalan)
- [x] Pengurus monitoring scoped by region
- [x] Filament admin panel untuk semua entitas
- [x] RBAC fully enforced dan tested
- [x] Push notification infrastructure
- [x] API documentation untuk semua endpoint

> **Milestone**: Setelah Phase 2, platform bisa digunakan untuk **manajemen club dan setup tournament** — meskipun scoring belum ada. Ini berguna untuk onboarding club dan testing dengan panitia tournament.

---

### Phase 3: Tournament Scoring & Elimination [8-10 Minggu]

**Tujuan**: Engine scoring yang production-ready — termasuk offline-tolerant, leaderboard, eliminasi, dan conflict resolution.

**Risiko phase ini**: 🔴 HIGH — ini fase paling kompleks dan paling banyak "unknown unknowns".

**Strategi**: Iterative — scoring dasar dulu (online only), lalu layer idempotency, lalu leaderboard caching, lalu offline-tolerant, lalu eliminasi. Setiap layer harus tested sebelum pindah ke berikutnya.

| Minggu | Task | Detail | Estimasi |
|--------|------|--------|----------|
| **11-12** | **Scoring Engine — Fondasi** | | **2 minggu** |
| | Score submission API (online, happy path) | POST /scores — basic input tanpa idempotency dulu | 2 hari |
| | Idempotency layer | client_ref check, duplicate handling | 1.5 hari |
| | Score validation rules | Arrow range, end count, arrows_per_end, participant eligibility | 1 hari |
| | Dual-entry (scorer + validator) | Dua entry terpisah per rambahan, entry_role distinction | 1.5 hari |
| | Conflict detection | Compare arrows, auto-confirm jika sama, flag disputed jika beda | 2 hari |
| | Score correction flow | Edit as new entry, previous_entry_id, score_corrections table | 1 hari |
| | Unit & feature tests: scoring foundation | Test setiap skenario: valid, duplicate, conflict, correction | 1 hari |
| **13-14** | **Leaderboard & Caching** | | **2 minggu** |
| | Redis sorted set integration | ZADD saat score persist, ZREVRANGE untuk ranking | 1.5 hari |
| | LeaderboardService | Multi-layer cache: in-process → Redis formatted → Redis sorted set → PostgreSQL fallback | 2.5 hari |
| | Leaderboard API endpoint | GET /leaderboard — public, cached, dengan metadata (updated_at, next_poll) | 1 hari |
| | Cache invalidation & rebuild | Event listener update Redis, cron safety net rebuild tiap 30 detik | 1.5 hari |
| | X-count tie-breaking | Ranking secondary sort by X-count (reverse: lebih banyak X = lebih tinggi) | 0.5 hari |
| | Graceful degradation test | Matikan Redis → verify fallback ke PostgreSQL query berjalan | 1 hari |
| | Tests: leaderboard | Accuracy test (leaderboard vs raw score_entries), cache staleness test | 1 hari |
| | Scorer dashboard API | GET /scores/my-target — ringkasan bantalan scorer, status per rambahan | 1 hari |
| **15-16** | **Eliminasi / Aduan (Olympic Round)** | | **2 minggu** |
| | Bracket generation algorithm | Input: ranking kualifikasi. Output: bracket tree (1v32, 2v31, ...) dengan bye handling | 3 hari |
| | Elimination match API | CRUD matches, status transitions | 1 hari |
| | Set scoring API | Input skor per set (3 arrows masing-masing), set point calculation (2-1-0) | 2 hari |
| | Shoot-off handling | Skor seri → 1 arrow each → closest-to-center tiebreak | 1 hari |
| | Winner advancement | Otomatis advance winner ke next_match, update bracket | 1 hari |
| | Bronze medal match | Generate perebutan perunggu dari semifinal losers | 0.5 hari |
| | Bracket API (public read) | GET /bracket — cached, formatted | 0.5 hari |
| | Tests: eliminasi | Full bracket test (32 peserta → final), shoot-off, bye | 1 hari |
| **17-18** | **Offline-Tolerant Integration** | | **2 minggu** |
| | Server: idempotency hardening | Stress test idempotency under concurrent submissions | 1 hari |
| | Server: batch sync endpoint (optional) | POST /scores/batch — submit multiple scores in one request (efficiency saat sync) | 1.5 hari |
| | Server: sync status endpoint | GET /scores/sync-status — client cek status semua pending entries | 0.5 hari |
| | API documentation: scoring endpoints | Detail contract untuk Flutter developer (bisa jadi diri sendiri) | 1 hari |
| | Dispute resolution API & admin UI | POST /scores/disputes/{id}/resolve, Filament page untuk chief judge | 2 hari |
| | Integration testing: scoring end-to-end | Full tournament simulation: 10 peserta, 10 rambahan, conflict scenario | 2 hari |
| | Buffer: unexpected complexity | Phase 3 paling mungkin punya surprises | 2 hari |
| **19-20** | **Regu (Beregu) & Polish** | | **1-2 minggu** |
| | Tournament teams API | Create team, assign members, calculate team score from individual | 2 hari |
| | Team leaderboard | Agregasi skor individu → ranking tim, cached | 1 hari |
| | Absensi / check-in API | Batch check-in peserta, guard scoring agar hanya checked-in bisa di-score | 1 hari |
| | Scoring edge cases & hardening | Semua edge case dari user stories: timeout, partial sync, clock drift | 2 hari |
| | Phase 3 bug fixes & refactor | Accumulated tech debt | 2-3 hari |

**Deliverable Phase 3:**
- [x] Scoring engine production-ready (idempotent, dual-entry, conflict detection)
- [x] Leaderboard cached dan performant (multi-layer cache)
- [x] Eliminasi bracket generation dan scoring (Olympic Round)
- [x] Offline-tolerant support di API (idempotency, batch sync)
- [x] Dispute resolution flow
- [x] Regu/beregu scoring
- [x] Full integration tests

> **⚠️ Peringatan**: Phase 3 diestimasi 8-10 minggu, tapi bisa stretch ke 12 jika elimination bracket logic ternyata lebih kompleks dari ekspektasi (variasi aturan panahan tradisional Indonesia). Jika ini terjadi, **prioritaskan scoring kualifikasi + leaderboard**, dan tunda eliminasi ke post-Phase 3.

---

### Phase 4: Load Testing & Hardening [3-4 Minggu]

**Tujuan**: Verify performa memenuhi target, security audit, bug fixing.

| Minggu | Task | Detail | Estimasi |
|--------|------|--------|----------|
| **21** | **Load Testing** | | **1 minggu** |
| | Setup k6 / Locust | Script load test untuk: leaderboard polling, score submission | 1 hari |
| | Test 1: Leaderboard 1.000 concurrent | Baseline — harus <500ms p95 | 0.5 hari |
| | Test 2: Leaderboard 3.000 concurrent | Target minimum untuk go-live | 0.5 hari |
| | Test 3: Leaderboard 5.000 concurrent | Stretch target | 0.5 hari |
| | Test 4: Scoring write under load | 100 concurrent scorers + 3.000 leaderboard readers | 0.5 hari |
| | Test 5: Spike test | 0 → 5.000 users dalam 30 detik | 0.5 hari |
| | Analyze & fix bottlenecks | Profile slow queries, Redis latency, PHP-FPM tuning | 1.5 hari |
| **22** | **Performance Optimization** | | **1 minggu** |
| | Database optimization | EXPLAIN ANALYZE pada hot queries, index tuning, connection pooling | 2 hari |
| | Cache tuning | TTL adjustment, memory sizing, cache hit ratio monitoring | 1 hari |
| | PHP-FPM tuning | Worker count, memory limit, OPcache hit rate | 0.5 hari |
| | Nginx tuning | Worker connections, keepalive, gzip, rate limiting | 0.5 hari |
| | Re-run load tests | Verify improvement | 1 hari |
| **23** | **Security & Hardening** | | **1 minggu** |
| | RBAC audit | Enumerate semua endpoint × semua role → verify 403 untuk unauthorized | 1.5 hari |
| | Input validation audit | Review semua FormRequest — pastikan tidak ada injection vector | 1 hari |
| | Rate limiting verification | Test brute force scenario, verify throttle berfungsi | 0.5 hari |
| | Dependency audit | `composer audit`, update vulnerable packages | 0.5 hari |
| | Backup & restore test | pg_dump → restore di server baru → verify data intact | 1 hari |
| | Error handling audit | Verify: tidak ada stack trace di production response, semua error ter-log | 0.5 hari |
| **24** (buffer) | **Stabilization** | | **0-1 minggu** |
| | Fix issues found in Phase 4 | Performance regressions, security fixes | As needed |
| | Pre-launch checklist walkthrough | Go through GL-01 to GL-10 dari PRD | 1 hari |
| | Staging tournament simulation | Simulasi tournament 1 hari penuh di staging: 20 peserta, 2 scorer, 5 penonton simulated | 1 hari |

**Deliverable Phase 4:**
- [x] Load test lulus: p95 <500ms pada 3.000+ concurrent
- [x] Zero critical security issues
- [x] Backup & restore verified
- [x] All go-live checklist items addressed

---

### Phase 5: Deployment & Go-Live [2-3 Minggu]

**Tujuan**: Production environment ready, monitoring aktif, go-live tournament pertama.

| Minggu | Task | Detail | Estimasi |
|--------|------|--------|----------|
| **25** | **Production Infrastructure** | | **1 minggu** |
| | Provision production server | VPS 4 core / 8GB RAM, Ubuntu 22.04 | 0.5 hari |
| | Install stack: Nginx, PHP-FPM, PostgreSQL, Redis | Via Ansible/manual — documented script | 1 hari |
| | SSL setup (Let's Encrypt) | HTTPS + auto-renewal | 0.5 hari |
| | Deploy application | Git pull, composer install, migrate, cache warmup | 0.5 hari |
| | Supervisor setup | Queue worker, scheduler | 0.5 hari |
| | Monitoring setup | Health check endpoint, UptimeRobot, log rotation, disk alert | 1 hari |
| | Backup automation | Cron: pg_dump daily → GCS/S3, WAL archiving | 1 hari |
| **26** | **Go-Live Preparation** | | **1 minggu** |
| | Production smoke test | Semua endpoint berjalan di production env | 1 hari |
| | Seed production data | Roles, permissions, regions, Super Admin account | 0.5 hari |
| | DNS & domain setup | Domain pointing, SSL verify | 0.5 hari |
| | Pre-tournament data entry | Club registration, member onboarding dengan panitia tournament pertama | 2 hari |
| | Final load test di production | Verify production server handle target load | 1 hari |
| **27** (buffer) | **Tournament Pertama (Supervised)** | | **1 minggu** |
| | Day 0: Pre-tournament check | Server health, backup verify, monitoring active, hotline ready | 0.5 hari |
| | Day 1-2: LIVE tournament | Developer standby, real-time monitoring, hotfix capability | 2 hari |
| | Day 3: Post-tournament review | Analyze: errors, slow queries, user feedback, data integrity check | 1 hari |
| | Post-mortem & fixes | Address issues found during live tournament | 1.5 hari |

**Deliverable Phase 5:**
- [x] Production server running & monitored
- [x] Tournament pertama berhasil dijalankan
- [x] Post-mortem documented
- [x] Confidence untuk tournament berikutnya

---

## Critical Path

```
Foundation ──► Club/Member ──► Tournament Setup ──► Scoring Engine ──► Leaderboard ──► Eliminasi ──► Load Test ──► Go-Live
                                                        │
                                                   CRITICAL PATH
                                                   (Minggu 11-18)
                                                   
Parallel track (bisa dikerjakan bersamaan):
  - Filament admin panel ← bisa berjalan seiring dengan API development
  - Notifikasi ← bisa ditambahkan kapan saja setelah Phase 2
  - API documentation ← incremental setiap module selesai
```

---

## Contingency Plans

| Skenario | Dampak ke Timeline | Action |
|----------|-------------------|--------|
| Eliminasi bracket terlalu kompleks | +2-3 minggu | Simplifikasi: support hanya 16 dan 32 bracket dulu. Variasi aturan khusus di Phase 6. |
| Load test gagal di Phase 4 | +1-2 minggu | Prioritaskan: caching layer fix dulu. Jika masih gagal, pertimbangkan server upgrade. |
| Burnout di Phase 3 | +2-3 minggu | Izinkan 1 minggu break. Scoring engine terlalu penting untuk dikerjakan saat burned out — bug akan mahal. |
| Requirement baru dari panitia tournament | +1-4 minggu | Classify: MUST (add ke phase yang tepat) vs SHOULD/COULD (backlog). Jangan scope creep. |
| Sakit / unavailable | +1-2 minggu | Buffer 2 minggu sudah di-account. Jika lebih: geser go-live. |

---

## Summary Timeline

| Phase | Durasi | Minggu | Kumulatif |
|-------|--------|--------|-----------|
| Phase 1: Foundation | 3 minggu | 1-3 | 3 minggu |
| Phase 2: Core Modules | 7 minggu | 4-10 | 10 minggu |
| Phase 3: Scoring & Elimination | 10 minggu | 11-20 | 20 minggu |
| Phase 4: Hardening | 4 minggu | 21-24 | 24 minggu |
| Phase 5: Go-Live | 3 minggu | 25-27 | **27 minggu (~6.5 bulan)** |

> **Estimasi total: 22-27 minggu (5.5–6.5 bulan)**
>
> Optimistis: 22 minggu (semua berjalan lancar, tidak ada surprise)
> Realistis: 25 minggu (ada beberapa surprise, buffer terpakai sebagian)
> Pessimistis: 30 minggu (eliminasi kompleks, load test gagal, requirement creep)

> **⚠️ Peringatan jujur untuk solo developer**: 6 bulan intense development tanpa peer review dan tanpa rekan diskusi itu BERAT secara mental. Saya sangat merekomendasikan:
> 1. Jadwalkan 1 hari per 2 minggu untuk "no-code day" — review, refactor, dokumentasi saja
> 2. Cari 1-2 teman developer untuk code review informal (barter review, tidak harus bayar)
> 3. Jika budget memungkinkan, hire freelancer untuk Filament admin panel (CRUD repetitive) agar Anda bisa fokus ke scoring engine
