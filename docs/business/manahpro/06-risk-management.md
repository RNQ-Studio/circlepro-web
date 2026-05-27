# 06 — Risk Management

> **Project**: Manahpro — Platform Tata Kelola Tournament & Scoring Panahan
> **Versi**: 1.0
> **Tanggal**: 2026-05-25
> **Referensi**: Seluruh dokumen 00–05

---

## Proses Berpikir: Pertanyaan Pemantik

### Risiko mana yang paling mungkin terjadi di hari pertama tournament?

Saya akan berpikir dari perspektif "D-Day skenario" — apa yang paling mungkin salah saat tournament pertama dijalankan di lapangan?

1. **Koneksi internet buruk di lapangan** — Hampir pasti terjadi. Itulah kenapa offline-tolerant dibangun.
2. **Wasit bingung pakai app** — Faktor manusia. App harus intuitif, tapi tetap perlu briefing.
3. **Spike traffic saat babak final** — Penonton berduyun-duyun lihat leaderboard saat momen kritis.
4. **Bug di scoring edge case** — Misalnya: skor X (ten + inner ring) tidak terhitung benar di tie-break.
5. **Redis restart tanpa warning** — Leaderboard hilang sementara.

### Risiko mana yang paling sulit dimitigasi sendirian?

1. **Bus factor = 1** — Jika developer sakit saat tournament, TIDAK ADA orang lain yang bisa fix bug. Ini risiko eksistensial yang tidak bisa sepenuhnya dimitigasi.
2. **Concurrent data race condition** — Bug yang hanya muncul saat load tinggi dan sulit direproduksi di lokal.
3. **Server hardware failure** — Di luar kontrol developer. Mitigasi = backup + recovery plan.

---

## Framework Penilaian

| Level | Kemungkinan (L) | Dampak (D) |
|-------|-----------------|------------|
| **H** (High) | >60% terjadi dalam 6 bulan pertama | Operasi tournament terganggu berat, data loss, atau reputasi rusak |
| **M** (Medium) | 20–60% terjadi | Fungsionalitas terganggu sebagian, workaround tersedia |
| **L** (Low) | <20% terjadi | Ketidaknyamanan minor, tidak mempengaruhi operasi inti |

**Risk Score** = Kemungkinan × Dampak (H×H = Critical, H×M atau M×H = High, dst.)

---

## Daftar Risiko

### Kategori 1: Performa & Scalability

#### R-01: Leaderboard Tidak Mampu Handle 5.000 Concurrent Users

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **M** — Arsitektur caching sudah didesain, tapi belum terbukti di production |
| **Dampak** | **H** — Penonton tidak bisa lihat ranking. Value proposition platform hilang. |
| **Risk Score** | 🔴 **HIGH** |
| **Mitigasi** | Multi-layer cache (in-process → Redis → PostgreSQL fallback). Load test wajib di Phase 4 dengan target 3.000-5.000 concurrent. Adaptive polling interval — server bisa naikkan interval saat beban tinggi. |
| **Kontingensi** | Jika load test gagal: (1) Upgrade server (4→8 core), (2) Naikkan cache TTL ke 10-15 detik, (3) Implement CDN-level caching untuk leaderboard static response. Worst case: API leaderboard sementara redirect ke halaman statis yang di-update periodik. |

#### R-02: Redis Out of Memory atau Crash Saat Tournament

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **L** — Estimasi total cache Manahpro <50MB, jauh dari limit |
| **Dampak** | **H** — Leaderboard mati, queue berhenti, rate limiting hilang |
| **Risk Score** | 🟡 **MEDIUM** |
| **Mitigasi** | Konfigurasi `maxmemory 512mb` + `maxmemory-policy allkeys-lru`. Redis AOF persistence. Monitor memory usage via cron. Cron rebuild leaderboard tiap 30 detik sebagai safety net. |
| **Kontingensi** | Graceful degradation: leaderboard fallback ke PostgreSQL query (lambat tapi benar). Queue fallback ke `database` driver. Session fallback ke `file` driver. Semua switchable via `.env` tanpa deploy. |

#### R-03: Database Connection Pool Exhaustion Saat Peak

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **M** — 50 PHP-FPM workers × long queries bisa exhaust PostgreSQL max_connections |
| **Dampak** | **H** — API timeout, scoring gagal, 500 errors |
| **Risk Score** | 🔴 **HIGH** |
| **Mitigasi** | PostgreSQL `max_connections = 200`. PHP-FPM `max_children = 50`. Laravel `DB_POOL` config. Optimasi query: pastikan tidak ada query >100ms tanpa index. Connection pooling via PgBouncer (Phase 2 jika dibutuhkan). |
| **Kontingensi** | Saat terjadi: restart PHP-FPM (membebaskan semua connections). Jangka pendek: naikkan max_connections. Jangka panjang: install PgBouncer. |

#### R-04: Spike Traffic Saat Babak Final (Flash Crowd)

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **H** — Babak final/semi-final SELALU menarik perhatian lebih |
| **Dampak** | **M** — Leaderboard/bracket lambat, tapi scoring tetap jalan |
| **Risk Score** | 🔴 **HIGH** |
| **Mitigasi** | In-process cache mencegah thundering herd. Nginx `limit_req` sebagai Layer 1 rate limit. Polling interval di-control server: naikkan dari 5→10 detik saat CPU >80%. |
| **Kontingensi** | Jika server overwhelmed: Nginx return cached response dari disk (stale but available). Aktifkan Nginx proxy_cache untuk endpoint leaderboard (bypass PHP). |

#### R-05: Slow Query Muncul di Production yang Tidak Terdeteksi di Dev

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **H** — Hampir pasti terjadi. Dataset dev kecil, production besar. Query planner berperilaku beda. |
| **Dampak** | **M** — Endpoint lambat, bisa cascade ke connection pool exhaustion |
| **Risk Score** | 🔴 **HIGH** |
| **Mitigasi** | Seed database dev dengan data realistis (500 peserta, 30.000 score entries). Aktifkan `pg_stat_statements` di production. Log slow queries (>100ms). EXPLAIN ANALYZE pada semua query scoring dan leaderboard di Phase 4. |
| **Kontingensi** | Hotfix: tambah index. Jika query unfixable: cache result di Redis. |

---

### Kategori 2: Offline Sync & Conflict

#### R-06: Data Loss Saat Offline Sync (Skor Hilang)

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **L** — Idempotency key + local queue seharusnya mencegah ini |
| **Dampak** | **H** — Skor hilang = hasil tournament salah. Trust hancur. Protes peserta. |
| **Risk Score** | 🟡 **MEDIUM** |
| **Mitigasi** | Client queue persist di SQLite (bukan in-memory). client_ref UNIQUE constraint di DB. Retry dengan exponential backoff. Sync status indicator di UI wasit. Test: 100 siklus offline/online, verify 0% data loss. |
| **Kontingensi** | Jika data loss terdeteksi: cross-reference dengan score sheet fisik (kertas backup yang HARUS tetap digunakan di tournament). Manual input skor yang hilang via admin panel. |

> **Peringatan keras**: Meskipun sistem digital didesain tahan offline, **WAJIB ada score sheet kertas sebagai backup fisik**. Ini bukan karena sistem tidak dipercaya — ini karena sistem digital apapun bisa gagal, dan hasil tournament terlalu penting untuk bergantung pada satu medium.

#### R-07: Conflict Scoring Tidak Ter-Resolve Tepat Waktu

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **M** — Tergantung kesiapan chief judge menggunakan sistem |
| **Dampak** | **M** — Leaderboard menampilkan skor "provisional" terlalu lama, ranking bisa berubah drastis saat resolved |
| **Risk Score** | 🟡 **MEDIUM** |
| **Mitigasi** | Push notification ke admin/chief judge saat dispute terdeteksi. Dispute dashboard dengan badge "pending >10 menit". Provisional score tetap dihitung di leaderboard (transparansi). |
| **Kontingensi** | Jika chief judge tidak bisa resolve via app: resolve via Filament admin panel. Jika itu juga gagal: manual override oleh Super Admin dengan audit trail lengkap. |

---

### Kategori 3: Teknis

#### R-08: Bug di Eliminasi Bracket Generation (Penyisihan Salah)

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **M** — Logika bracket (bye handling, seeding) kompleks dan edge case banyak |
| **Dampak** | **H** — Atlet yang harusnya menang tersisih karena bracket salah. Bencana kompetitif. |
| **Risk Score** | 🔴 **HIGH** |
| **Mitigasi** | Unit test exhaustive: 8, 16, 32 peserta. Test edge case: jumlah peserta ganjil, tie di kualifikasi. Verifikasi manual oleh admin sebelum bracket di-publish. Fitur "regenerate bracket" jika admin deteksi kesalahan sebelum aduan dimulai. |
| **Kontingensi** | Jika bracket salah terdeteksi setelah aduan dimulai: admin manual override bracket via Filament. Semua perubahan di-audit. Worst case: tournament fallback ke bracket manual (panitia tentukan via musyawarah). |

#### R-09: Idempotency Key Collision (UUID Clash)

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **L** — UUID v4 collision probability ~10^-37. Praktis tidak mungkin. |
| **Dampak** | **H** — Skor baru ditolak karena dianggap duplikat |
| **Risk Score** | 🟢 **LOW** |
| **Mitigasi** | UUID v4 sudah cukup. UNIQUE constraint di DB memastikan integritas. Jika collision terjadi (praktis mustahil): server return 409, client generate UUID baru dan retry. |
| **Kontingensi** | Investigate root cause — kemungkinan besar bug di UUID generator library, bukan collision asli. |

#### R-10: Clock Drift di Device Wasit (device_submitted_at Tidak Akurat)

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **M** — Smartphone dengan waktu manual (bukan auto-sync) bisa drift menit-jam |
| **Dampak** | **L** — Audit trail timestamp salah, tapi skor itu sendiri tidak terpengaruh |
| **Risk Score** | 🟢 **LOW** |
| **Mitigasi** | `server_received_at` (DB DEFAULT NOW()) selalu akurat — ini yang dipakai untuk ordering. `device_submitted_at` hanya untuk audit trail/debugging. Dokumentasikan: "untuk akurasi terbaik, pastikan waktu device auto-sync." |
| **Kontingensi** | Jika ordering jadi masalah: gunakan `server_received_at` sebagai canonical ordering, bukan device time. |

---

### Kategori 4: Keamanan

#### R-11: Unauthorized Score Manipulation (Wasit Palsu)

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **L** — Butuh access token + role scorer + assignment ke bantalan |
| **Dampak** | **H** — Skor dimanipulasi, hasil tournament tidak valid |
| **Risk Score** | 🟡 **MEDIUM** |
| **Mitigasi** | Tiga layer auth: (1) Valid access token, (2) Role = scorer, (3) scorer_assignment exists untuk bantalan target. Audit trail: semua score entries tercatat siapa yang input. Token lifetime 8 jam (tidak persist antar hari). |
| **Kontingensi** | Jika terdeteksi post-tournament: rollback skor dari scorer palsu via audit trail. Ban account. Recalculate leaderboard dari skor yang valid saja. |

#### R-12: Brute Force / DDoS pada Leaderboard Endpoint

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **M** — Endpoint publik (tanpa auth) = target mudah |
| **Dampak** | **M** — Server overload, leaderboard tidak bisa diakses penonton legitimate |
| **Risk Score** | 🟡 **MEDIUM** |
| **Mitigasi** | Nginx rate limiting: 60 req/menit per IP. Laravel throttle: backup rate limit. In-process cache: actual server load minimal meski banyak request. Cloudflare (jika dibutuhkan): DDoS protection gratis tier. |
| **Kontingensi** | Saat terjadi: block IP di Nginx. Aktifkan Cloudflare proxy. Worst case: sementara buat leaderboard memerlukan auth (break public access, tapi stop DDoS). |

---

### Kategori 5: Operasional

#### R-13: Bus Factor = 1 (Solo Developer Tidak Available)

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **M** — Sakit, darurat keluarga, burnout — semua realistis |
| **Dampak** | **H** — Jika terjadi saat tournament: tidak ada orang yang bisa fix bug atau restart server |
| **Risk Score** | 🔴 **HIGH** |
| **Mitigasi** | Dokumentasi operasional: runbook "cara restart semua services", "cara manual override skor", "cara check log". Auto-restart via Supervisor/systemd. Monitoring + alerting ke WhatsApp/Telegram. Automated backup — bahkan tanpa intervensi, data aman. Pertimbangkan: ajak 1 orang teknis (bisa junior) yang punya akses server dan bisa follow runbook. |
| **Kontingensi** | Jika developer benar-benar tidak available saat tournament: (1) Sistem berjalan otomatis (no active maintenance needed untuk happy path), (2) Jika ada error: admin menggunakan score sheet kertas, (3) Data di-input manual setelah developer kembali. |

#### R-14: Server Hardware Failure

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **L** — Cloud provider SLA biasanya 99.9% |
| **Dampak** | **H** — Semua sistem mati |
| **Risk Score** | 🟡 **MEDIUM** |
| **Mitigasi** | Daily backup ke off-site storage (GCS/S3). WAL continuous archiving untuk point-in-time recovery. Documented recovery procedure: provision server baru → restore backup → DNS switch. Estimasi recovery time: 1-2 jam. |
| **Kontingensi** | Saat terjadi: (1) Provision server baru dari cloud provider, (2) Restore dari backup terakhir, (3) Update DNS. Data loss = maksimal 1 hari (daily backup) atau minimal (jika WAL archiving aktif). |

#### R-15: Scope Creep dari Panitia Tournament

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **H** — Panitia SELALU punya "satu fitur lagi yang penting banget" |
| **Dampak** | **M** — Timeline molor, developer terdistract dari fitur inti |
| **Risk Score** | 🔴 **HIGH** |
| **Mitigasi** | PRD dan MoSCoW sudah tegas. Semua request baru harus diclassify: MUST (benar-benar blocking tournament?) vs SHOULD/COULD (bisa post-launch). Rule: **TIDAK ADA fitur baru ditambahkan setelah Phase 3 dimulai** kecuali ini benar-benar show-stopper. |
| **Kontingensi** | Jika request benar-benar blocking: evaluate effort. Jika <2 hari, bisa masuk. Jika >2 hari, tunda ke post-launch. Komunikasikan secara jelas ke panitia. |

#### R-16: Burnout Solo Developer di Phase 3

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **H** — 8-10 minggu development paling kompleks tanpa rekan diskusi |
| **Dampak** | **H** — Quality turun, bug naik, timeline molor, mungkin give up |
| **Risk Score** | 🔴 **CRITICAL** |
| **Mitigasi** | Jadwal 1 hari "no-code" per 2 minggu. Track velocity — jika drop 2 sprint berturut, take 3-day break. Scoring engine dipecah kecil-kecil (fondasi → idempotency → leaderboard → eliminasi) agar ada "wins" setiap 1-2 minggu. Cari code review buddy (barter/gratis). |
| **Kontingensi** | Jika burnout parah: (1) Prioritaskan scoring kualifikasi + leaderboard saja (tunda eliminasi), (2) Tournament pertama pakai bracket manual, (3) Hire freelancer untuk Filament admin panel agar workload berkurang. |

---

### Kategori 6: Data & Compliance

#### R-17: Data Korupsi pada Score Entries

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **L** — Validasi berlapis (client + server + DB constraint) |
| **Dampak** | **H** — Hasil tournament tidak valid |
| **Risk Score** | 🟡 **MEDIUM** |
| **Mitigasi** | Validasi 3 layer: (1) Client-side validation sebelum submit, (2) FormRequest validation di server, (3) DB CHECK constraint pada arrow values. Score entries immutable — tidak ada UPDATE, hanya INSERT. Audit trail lengkap. |
| **Kontingensi** | Jika korupsi terdeteksi: (1) Identify entry yang corrupt via audit trail, (2) Mark sebagai `overridden`, (3) Input skor benar dari score sheet fisik, (4) Rebuild leaderboard dari data yang bersih. |

#### R-18: Privacy Data Anggota

| Aspek | Detail |
|-------|--------|
| **Kemungkinan** | **L** — RBAC membatasi akses, tapi bug bisa expose data |
| **Dampak** | **M** — Data pribadi (email, phone, alamat) bocor |
| **Risk Score** | 🟢 **LOW** |
| **Mitigasi** | API Resource layer: hanya expose field yang diperlukan per context. Profil publik atlet: hanya nama, club, foto, level. Data pribadi hanya visible ke: pemilik akun, admin club-nya, admin tournament (untuk verifikasi), Super Admin. RBAC test coverage tinggi. |
| **Kontingensi** | Jika breach: (1) Identify scope data yang terekspos, (2) Fix bug, (3) Notifikasi user terdampak, (4) Audit semua endpoint untuk masalah serupa. |

---

## Risk Heatmap

```
                    DAMPAK
                Low        Medium       High
           ┌──────────┬──────────┬──────────┐
    High   │          │ R-15     │ R-01,R-04│
           │          │ scope    │ R-05,R-16│
Kemungkinan│          │ creep    │ perf/burn│
           ├──────────┼──────────┼──────────┤
    Medium │ R-10     │ R-07,R-12│ R-03,R-08│
           │ clock    │ conflict │ DB pool, │
           │ drift    │ DDoS     │ bracket  │
           │          │          │ R-11,R-13│
           ├──────────┼──────────┼──────────┤
    Low    │ R-09     │ R-18     │ R-02,R-06│
           │ UUID     │ privacy  │ R-14,R-17│
           │          │          │ Redis,   │
           │          │          │ data loss│
           └──────────┴──────────┴──────────┘
```

**Top 5 Risks (by score):**

| Rank | Risk | Score | Status |
|------|------|-------|--------|
| 1 | R-16: Burnout solo developer | 🔴 CRITICAL | Paling sulit dimitigasi — faktor manusia |
| 2 | R-01: Leaderboard perf 5K concurrent | 🔴 HIGH | Arsitektur sudah address, perlu load test verification |
| 3 | R-04: Spike traffic babak final | 🔴 HIGH | Mitigasi via caching, perlu spike test |
| 4 | R-05: Slow query di production | 🔴 HIGH | Mitigasi: realistic seeding + profiling |
| 5 | R-13: Bus factor = 1 | 🔴 HIGH | Mitigasi: runbook + auto-restart + backup |

---

## Catatan Konsistensi

| Risiko | Referensi Arsitektur (04) | Referensi Timeline (05) |
|--------|---------------------------|------------------------|
| R-01: Leaderboard perf | §2.3 Read Path multi-layer cache | Phase 4: Load Testing wajib |
| R-02: Redis crash | §Pertanyaan Pemantik: graceful degradation | Phase 4: Failover test |
| R-06: Data loss offline | §5 Offline Sync Flow | Phase 3: Offline-tolerant testing |
| R-08: Bracket bug | — | Phase 3 Minggu 15-16: Eliminasi tests |
| R-13: Bus factor | §6.1 Monitoring + Supervisor auto-restart | Contingency plan: runbook |
| R-16: Burnout | — | Phase 3 buffer: 2 minggu + "no-code day" recommendation |
