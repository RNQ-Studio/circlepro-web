# 04 — Architecture Plan

> **Project**: Manahpro — Platform Tata Kelola Tournament & Scoring Panahan
> **Versi**: 1.0
> **Tanggal**: 2026-05-25
> **Referensi**: [01-PRD.md](./01-PRD.md), [02-user-stories.md](./02-user-stories.md), [03-erd-konseptual.md](./03-erd-konseptual.md), [00-offline-first-analysis.md](./00-offline-first-analysis.md)

---

## Proses Berpikir: Pertanyaan Pemantik

### Komponen mana yang menjadi Single Point of Failure (SPOF)?

Sebelum mendesain arsitektur, saya harus jujur tentang realitas **solo developer + single server**:

| Komponen | SPOF? | Dampak Jika Down | Probabilitas |
|----------|-------|-------------------|-------------|
| **PostgreSQL** | ✅ Ya | Seluruh sistem mati — tidak bisa read/write | Rendah (mature software) |
| **Redis** | ✅ Ya | Leaderboard mati, queue berhenti, session hilang | Rendah-Medium |
| **Laravel App** | ✅ Ya | API tidak bisa diakses | Rendah (di-manage supervisor/systemd) |
| **Nginx** | ✅ Ya | Tidak bisa serve traffic | Sangat Rendah |
| **Server fisik/VM** | ✅ Ya | SEMUANYA mati | Tergantung provider |

**Kenyataan pahit**: Di deployment awal single-server, **semuanya adalah SPOF**. Ini bukan kelemahan arsitektur — ini constraint yang realistis untuk solo developer. Yang bisa saya lakukan:
1. **Minimize downtime** via auto-restart, health checks, monitoring
2. **Minimize data loss** via backup otomatis, WAL archiving
3. **Design for graceful degradation** — jika satu komponen down, yang lain tetap berfungsi sebaik mungkin

### Jika Redis down, apa yang terjadi?

Ini pertanyaan kritis karena Redis dipakai untuk 4 hal berbeda:

| Fungsi Redis | Jika Redis Down | Graceful Degradation |
|-------------|-----------------|---------------------|
| **Leaderboard cache** | Penonton tidak bisa lihat leaderboard real-time | Fallback: query aggregasi langsung dari PostgreSQL. Lambat (2-5 detik) tapi benar. Rate-limit ke 1 req/5 detik per client. |
| **Queue driver** | Job FCM notification, leaderboard update tertunda | Fallback: Laravel `sync` driver (process inline). Atau switch ke `database` queue driver. |
| **Session/cache** | Session Filament admin hilang, harus re-login | Fallback: `file` cache driver. Admin re-login. |
| **Rate limiting** | Throttle tidak berfungsi | Fallback: in-memory rate limiter (per-process, bukan shared). Atau disable throttle temporarily. |

**Strategi**: Config `CACHE_DRIVER`, `QUEUE_CONNECTION`, dan `SESSION_DRIVER` harus bisa di-switch via environment variable tanpa deploy ulang. Laravel sudah support ini secara native.

**Implementasi graceful degradation untuk leaderboard:**

```php
// LeaderboardService.php — pseudocode konseptual
public function getLeaderboard(int $categoryId): Collection
{
    try {
        return $this->getFromRedis($categoryId); // Primary: Redis sorted set
    } catch (RedisException $e) {
        Log::warning('Redis down, falling back to DB query', ['category' => $categoryId]);
        return Cache::store('file')->remember(
            "leaderboard:{$categoryId}",
            now()->addSeconds(10),
            fn () => $this->calculateFromDatabase($categoryId) // Fallback: aggregate query
        );
    }
}
```

> **Catatan jujur**: Fallback ini TIDAK akan menahan 5.000 concurrent users. Jika Redis down saat tournament aktif dengan 5.000 penonton, leaderboard akan lambat. Ini acceptable karena: (1) Redis down saat tournament = probabilitas sangat rendah, (2) Scorer tetap bisa input skor (scoring tidak bergantung pada Redis), (3) Lebih baik leaderboard lambat daripada error 500.

---

## 1. Gambaran Arsitektur Sistem

### 1.1 Diagram Arsitektur

```
┌─────────────────────────────────────────────────────────────────────────┐
│                            INTERNET                                      │
└─────────────┬───────────────────────────────┬───────────────────────────┘
              │                               │
              ▼                               ▼
┌─────────────────────┐        ┌───────────────────────────┐
│   Mobile App        │        │   Filament Admin Panel    │
│   (Flutter)         │        │   (Web Browser)           │
│                     │        │                           │
│ ┌─────────────────┐ │        └─────────────┬─────────────┘
│ │ Scoring Module  │ │                      │
│ │ ┌─────────────┐ │ │                      │
│ │ │ Local Queue │ │ │                      │
│ │ │ (SQLite)    │ │ │                      │
│ │ └─────────────┘ │ │                      │
│ └─────────────────┘ │                      │
└─────────┬───────────┘                      │
          │ HTTPS + Bearer Token             │ HTTPS + Session
          │ (REST API)                       │ (Filament)
          ▼                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         NGINX (Reverse Proxy + SSL)                      │
│                         Rate Limiting Layer 1                            │
└─────────────────────────────────┬───────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         LARAVEL APPLICATION                              │
│                                                                          │
│  ┌─────────────────┐     ┌────────────────────┐    ┌─────────────────┐  │
│  │   API Routes    │     │   Web Routes       │    │   Console       │  │
│  │   /api/v1/*     │     │   /admin (Filament)│    │   (Scheduler)   │  │
│  │   guard: api    │     │   guard: web       │    │                 │  │
│  └────────┬────────┘     └────────┬───────────┘    └────────┬────────┘  │
│           │                       │                          │           │
│           ▼                       ▼                          ▼           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                     MIDDLEWARE STACK                             │    │
│  │  ┌────────────┐ ┌────────────┐ ┌──────────────┐ ┌───────────┐  │    │
│  │  │ Auth       │ │ Throttle   │ │ ForceJSON    │ │ Tournament│  │    │
│  │  │ (Passport) │ │ (Redis)    │ │              │ │ StatusGate│  │    │
│  │  └────────────┘ └────────────┘ └──────────────┘ └───────────┘  │    │
│  └──────────────────────────┬──────────────────────────────────────┘    │
│                             ▼                                           │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                     CONTROLLER LAYER (thin)                      │   │
│  │                                                                  │   │
│  │  ScoreController    LeaderboardController    TournamentController│   │
│  │  ClubController     MemberController         BracketController  │   │
│  │  AuthController     NotificationController                      │   │
│  └──────────────────────────┬───────────────────────────────────────┘   │
│                             ▼                                           │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                     SERVICE LAYER (domain logic)                 │   │
│  │                                                                  │   │
│  │  ScoringService ◄── paling kompleks                             │   │
│  │    ├── validateAndPersistScore()                                 │   │
│  │    ├── checkIdempotency(clientRef)                               │   │
│  │    ├── detectConflict(participantId, endNumber)                  │   │
│  │    └── resolveDispute(entryId, resolution)                      │   │
│  │                                                                  │   │
│  │  LeaderboardService                                              │   │
│  │    ├── getLeaderboard(categoryId) ◄── Redis primary, DB fallback│   │
│  │    ├── updateScore(participantId, delta)                         │   │
│  │    └── rebuildLeaderboard(categoryId)                            │   │
│  │                                                                  │   │
│  │  EliminationService                                              │   │
│  │    ├── generateBracket(categoryId)                               │   │
│  │    ├── recordSetScore(matchId, setData)                          │   │
│  │    └── advanceWinner(matchId)                                    │   │
│  │                                                                  │   │
│  │  TournamentService, ClubService, MemberService, ...              │   │
│  └──────────────────────────┬───────────────────────────────────────┘   │
│                             ▼                                           │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                     EVENT / LISTENER LAYER                       │   │
│  │                                                                  │   │
│  │  ScorePersistedEvent ──► UpdateLeaderboardListener (Redis)       │   │
│  │                     ──► CheckConflictListener                    │   │
│  │                     ──► NotifyAdminOnDisputeListener             │   │
│  │                                                                  │   │
│  │  MatchCompletedEvent ──► AdvanceWinnerListener                   │   │
│  │                      ──► UpdateBracketCacheListener              │   │
│  │                                                                  │   │
│  │  ParticipantVerifiedEvent ──► NotifyAthleteListener              │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
└────────────────┬──────────────────┬──────────────────┬──────────────────┘
                 │                  │                  │
                 ▼                  ▼                  ▼
          ┌──────────┐      ┌──────────┐       ┌──────────────┐
          │PostgreSQL│      │  Redis   │       │   Firebase   │
          │          │      │          │       │   (FCM)      │
          │ Source   │      │ Cache    │       │   Push Notif │
          │ of Truth │      │ Queue    │       └──────────────┘
          │          │      │ Leaderb. │
          │          │      │ Throttle │       ┌──────────────┐
          └──────────┘      └──────────┘       │     GCS      │
                                               │   (Storage)  │
                                               └──────────────┘
```

### 1.2 Prinsip Arsitektur

| Prinsip | Implementasi |
|---------|-------------|
| **PostgreSQL = Single Source of Truth** | Semua data definitif ada di PostgreSQL. Redis = derived cache. Jika diverge, PostgreSQL yang benar. |
| **Thin Controller, Fat Service** | Controller: validasi input + return response. Service: business logic. Konsisten dengan starter. |
| **Event-driven untuk side effects** | Setelah score persist → event → listener update Redis, cek conflict, kirim notifikasi. Decoupled. |
| **Fail-open pada non-critical path** | Jika Redis/FCM down, scoring tetap jalan. Leaderboard degrade. Notifikasi tertunda. |
| **Fail-closed pada critical path** | Jika PostgreSQL down, scoring gagal dengan error jelas — lebih baik gagal daripada data hilang. |

---

## 2. Pemisahan Read Path & Write Path

### 2.1 Analisis: Apakah Read/Write Separation (CQRS) Diperlukan?

**Proses berpikir**:

CQRS formal (Command Query Responsibility Segregation) dengan event sourcing adalah salah satu pattern paling over-engineered yang sering diterapkan prematur. Sebelum memutuskan, saya harus tanya: **apakah read dan write pattern-nya cukup berbeda untuk justify pemisahan formal?**

| Aspek | Write Path (Scoring) | Read Path (Leaderboard) |
|-------|---------------------|------------------------|
| Volume | ~50-100 writes/menit peak | 1.000-5.000 reads/detik peak |
| Rasio | 1 | ~1.000-3.000x lebih banyak |
| Latency requirement | <300ms acceptable | <500ms required |
| Data shape | Detailed (per arrow, per end) | Aggregated (total, ranking) |
| Consistency | Strong (skor HARUS benar) | Eventual OK (5 detik stale OK) |

**Rasio read:write = ~1.000-3.000:1**. Ini memang berbeda drastis. TAPI — apakah ini justify CQRS formal?

**Jawaban: TIDAK untuk CQRS formal. YA untuk pemisahan informal via caching layer.**

Alasan:
- CQRS formal = read model terpisah, event store, projection — overkill untuk solo developer
- Yang dibutuhkan: **Redis sebagai read-optimized cache** di depan PostgreSQL. Ini "poor man's CQRS" yang sudah cukup.
- Write tetap ke PostgreSQL langsung. Read leaderboard dari Redis. Consistency dijaga oleh event listener yang update Redis setelah write.

### 2.2 Write Path — Scoring Flow

```
Mobile App (Scorer)
    │
    │ POST /api/v1/scores
    │ {client_ref, participant_id, end_number, arrows, device_submitted_at}
    │
    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ ScoreController                                                      │
│  1. Validate request (FormRequest)                                   │
│  2. Check auth + scorer assignment (Policy)                          │
│  3. Call ScoringService->submitScore()                                │
└───────────────────────────┬─────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│ ScoringService::submitScore()                                        │
│                                                                      │
│  1. Idempotency check: SELECT WHERE client_ref = ?                   │
│     ├── EXISTS → return existing entry (200 OK, no-op)               │
│     └── NOT EXISTS → continue                                        │
│                                                                      │
│  2. DB::transaction {                                                │
│       a. Validate business rules:                                    │
│          - participant exists and is verified + checked in            │
│          - end_number valid (1..total_ends)                           │
│          - arrows count = category.arrows_per_end                    │
│          - arrow values valid (0-10, X, M)                           │
│          - scorer has assignment for this target                     │
│                                                                      │
│       b. Calculate derived fields:                                   │
│          - end_total = SUM(arrow_values) where X=10, M=0             │
│          - x_count = COUNT(X in arrows)                              │
│                                                                      │
│       c. INSERT score_entry                                          │
│     }                                                                │
│                                                                      │
│  3. Dispatch ScorePersistedEvent (async, queued)                     │
│                                                                      │
│  4. Return score_entry                                               │
└───────────────────────────┬─────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│ ScorePersistedEvent Listeners (async via queue)                      │
│                                                                      │
│  Listener 1: UpdateLeaderboardListener                               │
│    → Redis ZADD leaderboard:{categoryId} {newTotal} {participantId}  │
│    → Redis DEL leaderboard_cache:{categoryId} (invalidate formatted) │
│                                                                      │
│  Listener 2: CheckConflictListener                                   │
│    → Query: ada entry lain untuk (participant_id, end_number)        │
│      dengan entry_role berbeda?                                      │
│    → Jika ada dan arrows sama → mark both CONFIRMED                  │
│    → Jika ada dan arrows beda → mark both DISPUTED                   │
│      → Dispatch DisputeDetectedEvent                                 │
│                                                                      │
│  Listener 3: NotifyOnDisputeListener (conditional)                   │
│    → Kirim push notif ke admin tournament + chief judge               │
└─────────────────────────────────────────────────────────────────────┘
```

> **Keputusan: Event listeners dijalankan via queue (async)**, bukan synchronous. Alasan: Scorer tidak boleh menunggu Redis update + conflict check + notification selesai. Response harus cepat (<300ms). Side effects bisa eventual (< beberapa detik).
>
> **Risiko**: Jika queue worker down, listeners tidak diproses → leaderboard tidak update, conflict tidak terdeteksi. **Mitigasi**: Supervisor/systemd auto-restart queue worker. Health check monitor queue size. Cron job safety net recalculate leaderboard tiap 30 detik.

### 2.3 Read Path — Leaderboard Flow

```
Mobile App / Browser (Penonton)
    │
    │ GET /api/v1/tournaments/{id}/categories/{id}/leaderboard
    │ (Public — no auth required)
    │
    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ LeaderboardController                                                │
│  1. Throttle: 60 req/menit per IP (unauthenticated)                  │
│  2. Call LeaderboardService->getLeaderboard()                        │
└───────────────────────────┬─────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│ LeaderboardService::getLeaderboard()                                 │
│                                                                      │
│  Layer 1: Application Cache (in-process, TTL 2 detik)                │
│    → Prevent stampede: jika 1.000 request datang bersamaan,          │
│      hanya 1 yang hit Redis, sisanya serve dari memory               │
│                                                                      │
│  Layer 2: Redis Cache (formatted response, TTL 5 detik)              │
│    → Cached JSON response siap kirim                                 │
│    → Key: leaderboard_cache:{categoryId}                             │
│                                                                      │
│  Layer 3: Redis Sorted Set (raw data, always up-to-date)             │
│    → ZREVRANGE leaderboard:{categoryId} 0 -1 WITHSCORES              │
│    → Enrich dengan nama, club dari separate hash/DB query            │
│    → Format → simpan di Layer 2                                      │
│                                                                      │
│  Layer 4: PostgreSQL (fallback — kalau Redis mati)                   │
│    → SELECT participant_id, SUM(end_total) as total                  │
│      FROM score_entries                                              │
│      WHERE tournament_category_id = ? AND entry_role = 'primary'     │
│        AND status IN ('confirmed', 'provisional')                    │
│      GROUP BY participant_id                                         │
│      ORDER BY total DESC, x_count DESC                               │
│    → Lambat (2-5 detik) tapi benar                                   │
│                                                                      │
│  Return: Array of {rank, participant_name, club_name, total_score,   │
│          x_count, last_end_total, ends_completed}                    │
└─────────────────────────────────────────────────────────────────────┘
```

**Multi-layer caching breakdown:**

```
Request ──► [In-Process Cache] ──miss──► [Redis Formatted] ──miss──► [Redis Sorted Set] ──miss──► [PostgreSQL]
                2 sec TTL                    5 sec TTL               Always fresh              Fallback only
                
Typical flow (5.000 concurrent):
  - 4.950 requests served from in-process cache (sub-millisecond)
  - 50 requests hit Redis formatted cache (1-2ms)
  - 0-1 request rebuild from Redis sorted set (5-10ms)
  - 0 requests hit PostgreSQL (unless Redis is down)
```

> **Keputusan: In-process cache (TTL 2 detik)** adalah kunci untuk menahan 5.000 concurrent. Tanpa ini, 5.000 req/detik semua ke Redis = Redis bisa overwhelmed. Dengan in-process cache, setiap PHP-FPM process hanya hit Redis sekali per 2 detik. Dengan 50 FPM workers, itu = 25 Redis hits/detik. Sangat ringan.
>
> **Implementasi**: Laravel `Cache::store('array')` atau manual static variable di service. Scope per-process, per-request cycle.

---

## 3. Strategi Caching (Redis) — Analisis Mendalam

### 3.1 Apa yang Di-Cache, TTL, dan Invalidation

| Data | Cache Key Pattern | TTL | Invalidation Strategy | Risiko Jika Strategi Salah |
|------|------------------|-----|----------------------|---------------------------|
| **Leaderboard (formatted)** | `leaderboard_cache:{categoryId}` | 5 detik | DEL saat score baru masuk (via event listener) | Stale data >5 detik → penonton lihat ranking lama. Low risk — masih acceptable. |
| **Leaderboard (sorted set)** | `leaderboard:{categoryId}` | Tidak expire — always maintained | ZADD/ZINCRBY saat score baru. Full rebuild via cron tiap 30 detik sebagai safety net | Jika ZADD gagal tanpa retry → skor tidak masuk ranking. **HIGH RISK** → mitigasi: cron safety net |
| **Bracket eliminasi** | `bracket:{categoryId}` | 30 detik | DEL saat match completed | Stale bracket → penonton lihat bracket lama. Low risk — bracket jarang berubah |
| **Tournament list** | `tournaments:active` | 60 detik | DEL saat tournament status berubah | Penonton lihat list lama 60 detik. Negligible risk. |
| **Participant metadata** | `participant:{id}` | 5 menit | DEL saat data berubah | Nama/club salah di leaderboard. Low risk — data ini jarang berubah saat tournament. |
| **Tournament category config** | `category:{id}` | 10 menit | DEL saat admin edit | Scorer lihat config lama. Low risk — config tidak berubah saat ongoing. |
| **Rate limiting counters** | `throttle:*` | Auto-expire (Laravel) | Automatic | Jika hilang → throttle reset. Low risk. |

### 3.2 Cache Warming Strategy

```
Saat tournament status berubah ke "ongoing":
  1. Pre-load leaderboard sorted set (kosong, siap ZADD)
  2. Pre-load participant metadata ke Redis hash
  3. Pre-load bracket data (jika eliminasi)
  
Saat server restart / Redis restart:
  1. Cron job: php artisan leaderboard:rebuild --all-active
     → Iterate semua tournament ongoing
     → Aggregate score_entries → ZADD ke Redis
     → Estimasi waktu: <10 detik untuk 500 peserta
```

### 3.3 Risiko Strategi Caching & Mitigasi

| Risiko | Probabilitas | Dampak | Mitigasi |
|--------|-------------|--------|----------|
| **Redis OOM (Out of Memory)** | Low | Leaderboard mati, queue stuck | Monitor memory usage. Set maxmemory policy `allkeys-lru`. Estimasi: semua cache Manahpro < 50MB — jauh dari limit |
| **Cache stampede** (banyak request saat cache expire bersamaan) | Medium saat tournament | Response spike | In-process cache + cache lock (`Cache::lock`) saat rebuild |
| **Stale cache setelah manual score override** | Medium | Leaderboard salah setelah admin override skor | Event listener untuk score override HARUS invalidate cache. Checklist di testing. |
| **Redis data loss (restart tanpa persistence)** | Medium | Leaderboard hilang sampai rebuild | Konfigurasi Redis AOF persistence. Cron rebuild sebagai safety net. |

---

## 4. Teknologi Real-Time: SSE vs WebSocket vs Polling

### 4.1 Analisis Mendalam

Ini keputusan yang sering salah karena developer tergoda oleh "real-time" tanpa memikirkan konsekuensi operasional.

| Kriteria | WebSocket | SSE (Server-Sent Events) | Short Polling | Long Polling |
|----------|-----------|--------------------------|---------------|-------------|
| **Directionality** | Bidirectional | Server → Client only | Client → Server | Client → Server |
| **Persistent connection** | Ya | Ya | Tidak | Partial |
| **Laravel native support** | ❌ Butuh Reverb/Pusher/Soketi | ⚠️ Butuh custom, bukan bawaan | ✅ Standard HTTP | ⚠️ Possible tapi awkward |
| **Infra complexity** | 🔴 Tinggi — dedicated WS server, memory per connection | 🟡 Medium — perlu streaming response | 🟢 Rendah — standard HTTP | 🟡 Medium — long-lived connections |
| **Connection count 5K** | 5K persistent connections = ~500MB RAM + dedicated process | 5K persistent connections = similar RAM issue | 5K × 1 req/5 detik = 1K req/detik | Sama seperti persistent |
| **Proxy/CDN friendly** | ❌ Butuh WS-aware proxy | ⚠️ Beberapa proxy putus SSE | ✅ Normal HTTP | ⚠️ Timeout issues |
| **Mobile battery** | 🟡 Persistent connection drain battery | 🟡 Sama | 🟢 Request-based, bisa adaptive | 🟡 Sama |
| **Offline recovery** | Harus reconnect + sync state | Harus reconnect | Otomatis pada request berikutnya | Harus reconnect |
| **Solo dev maintenance** | 🔴 Debugging WS = pain. State management. Memory leaks. | 🟡 Simpler tapi still stateful | 🟢 Stateless. Standard debugging. | 🟡 Moderate |

### 4.2 Rekomendasi: SHORT POLLING dengan Adaptive Interval

**Tingkat keyakinan: 90%.**

**Saya sadar ini terdengar "mundur" dibanding WebSocket — tapi dengarkan reasoning-nya:**

**Kenapa BUKAN WebSocket:**
1. Laravel + WebSocket = Reverb (baru, Laravel 11+) atau Pusher/Soketi (third-party). Semua menambah komponen infra yang harus di-maintain.
2. 5.000 persistent WS connections = dedicated WS server + significant memory. Solo developer harus debug memory leaks, reconnection logic, stale connections.
3. Untuk leaderboard yang hanya butuh update tiap 3-5 detik, WebSocket adalah **overkill**. Kita tidak mengirim data sub-second.
4. Mobile app + WebSocket = battery drain concern. Flutter WS library harus handle reconnect, background state, etc.

**Kenapa BUKAN SSE:**
1. SSE masih persistent connection — sama resource concern dengan WS untuk 5.000 connections.
2. PHP + SSE = awkward. PHP-FPM not designed for long-lived connections. Harus pakai Octane atau custom setup.
3. Benefit SSE over polling = realtime push. Tapi kita sudah accept 5 detik staleness — jadi push tidak diperlukan.

**Kenapa SHORT POLLING (dengan smart caching):**
1. **Zero infrastructure tambahan.** Standard HTTP endpoint. Nginx + PHP-FPM yang sudah ada.
2. **Multi-layer cache sudah didesain** (in-process 2s → Redis 5s). 5.000 concurrent poll = sebagian besar served dari cache. Actual DB/Redis load minimal.
3. **Adaptive interval**: App poll setiap 5 detik saat tournament active. Setiap 30 detik saat idle. Setiap 5 menit saat app di background. Client-controlled — server tidak perlu track connections.
4. **Debuggable.** `curl` bisa test. Log bisa trace. No state management server-side.
5. **Offline-friendly.** Saat koneksi putus, polling otomatis gagal dan retry. Tidak perlu reconnect logic.

**Implementasi polling yang efisien:**

```
Client: GET /api/v1/leaderboard/{categoryId}?last_updated={timestamp}

Server response:
{
  "data": [...leaderboard...],
  "meta": {
    "updated_at": "2026-05-25T10:30:05Z",
    "next_poll_seconds": 5,        // Server bisa adjust interval
    "tournament_status": "ongoing"  // Client bisa stop polling kalau "completed"
  }
}

Optimization: 
  - Jika client mengirim last_updated dan data belum berubah → return 304 Not Modified (zero body)
  - ETag header untuk conditional request
  - Server mengontrol poll interval via response → bisa throttle saat beban tinggi
```

**Capacity calculation:**

```
5.000 penonton × 1 request / 5 detik = 1.000 req/detik
Dengan in-process cache (2s TTL):
  - 50 PHP-FPM workers
  - Setiap worker cache hit → 0ms overhead
  - Setiap 2 detik, 1 request per worker rebuild dari Redis
  - Redis hits: 50 workers / 2 seconds = 25 req/detik ke Redis
  - PostgreSQL hits: 0 (unless Redis down)
  
Result: 1.000 req/detik served dengan <1ms response (cache hit)
         25 req/detik ke Redis (trivial)
         0 req/detik ke PostgreSQL
         
Ini bisa jalan di single VPS 4 core / 8GB RAM.
```

> **Kapan harus reconsider?** Jika requirement berubah ke sub-second update (misal: streaming skor arrow-by-arrow saat archer menembak), polling tidak cukup. Tapi untuk leaderboard ranking update, 5 detik interval lebih dari cukup.

### 4.3 Fallback Plan: Jika Polling Ternyata Tidak Cukup

Jika di masa depan butuh true real-time (misal: fitur komentar live, streaming skor arrow-by-arrow):

1. **Phase 1 (sekarang)**: Polling + Cache → proving ground
2. **Phase 2 (jika dibutuhkan)**: Tambah Laravel Reverb/SSE untuk specific use case (misal: bracket live update). Leaderboard tetap polling.
3. **Phase 3 (sangat tidak mungkin dibutuhkan)**: Full WebSocket via dedicated service

Arsitektur polling tidak menghalangi migrasi ke SSE/WS nanti — endpoint yang sama bisa dipakai, tinggal tambah push channel.

---

## 5. Offline Sync Flow — Detail Arsitektur

### 5.1 Client-Side (Flutter — Konseptual)

```
┌─────────────────────────────────────────────────────────────────┐
│                    FLUTTER APP — SCORING MODULE                  │
│                                                                  │
│  ┌───────────────┐                                              │
│  │ Scoring UI    │──── onSubmit ────┐                           │
│  │ (Arrow Input) │                  │                           │
│  └───────────────┘                  ▼                           │
│                            ┌────────────────┐                   │
│                            │ Connectivity   │                   │
│                            │ Check          │                   │
│                            └───┬────────┬───┘                   │
│                            YES │        │ NO                    │
│                                │        │                       │
│                    ┌───────────▼┐  ┌────▼──────────┐           │
│                    │ POST /API  │  │ Save to Local │           │
│                    │ directly   │  │ Queue (SQLite)│           │
│                    └───────┬────┘  └────┬──────────┘           │
│                            │            │                       │
│                       ┌────▼────┐       │                       │
│                       │ Success?│       │                       │
│                       └─┬────┬──┘       │                       │
│                     YES │    │ NO       │                       │
│                         │    │          │                       │
│                  ┌──────▼┐ ┌─▼──────────▼──┐                   │
│                  │ Done  │ │ Save to Queue  │                   │
│                  │ ✓     │ │ (retry later)  │                   │
│                  └───────┘ └───────┬────────┘                   │
│                                    │                            │
│  ┌─────────────────────────────────▼────────────────────────┐  │
│  │ SYNC MANAGER (Background)                                │  │
│  │                                                          │  │
│  │ - Monitor connectivity state                             │  │
│  │ - On connectivity restored:                              │  │
│  │   1. Read queue ordered by device_submitted_at            │  │
│  │   2. For each entry: POST /api/v1/scores                 │  │
│  │   3. If 200: mark synced, remove from queue              │  │
│  │   4. If 409 (conflict): mark conflict, notify UI         │  │
│  │   5. If 5xx: retry with exponential backoff              │  │
│  │      (1s → 2s → 4s → 8s → 16s → max 30s)               │  │
│  │   6. If 422 (validation): mark failed, notify UI         │  │
│  │                                                          │  │
│  │ Queue entry format:                                      │  │
│  │ {                                                        │  │
│  │   client_ref: "uuid-v4",                                 │  │
│  │   participant_id: "...",                                  │  │
│  │   end_number: 3,                                         │  │
│  │   arrows: [10, 9, 8, "X", 7, "M"],                      │  │
│  │   device_submitted_at: "2026-05-25T10:30:00+07:00",      │  │
│  │   sync_status: "pending|synced|failed",                  │  │
│  │   retry_count: 0,                                        │  │
│  │   created_at: "..."                                      │  │
│  │ }                                                        │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ UI INDICATORS                                            │  │
│  │                                                          │  │
│  │ 🟢 Online — skor terkirim langsung                       │  │
│  │ 🟡 Offline — skor disimpan lokal (X menunggu sync)       │  │
│  │ 🔴 Sync gagal — perlu perhatian (lihat detail)           │  │
│  │ 🔵 Syncing... — sedang mengirim X skor                   │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 Server-Side Idempotency Handling

```php
// ScoringService::submitScore() — pseudocode konseptual

public function submitScore(ScoreSubmissionDTO $data): ScoreEntry
{
    // 1. Idempotency check — SEBELUM transaction
    $existing = ScoreEntry::where('client_ref', $data->clientRef)->first();
    if ($existing) {
        // Sudah pernah diproses — return tanpa insert (idempotent)
        return $existing;
    }

    // 2. Transaction — atomic write
    return DB::transaction(function () use ($data) {
        // 2a. Validate business rules
        $participant = $this->validateParticipant($data->participantId);
        $category = $participant->tournamentCategory;
        $this->validateScorerAssignment($data->scorerId, $participant);
        $this->validateEndNumber($data->endNumber, $category->total_ends);
        $this->validateArrows($data->arrows, $category->arrows_per_end);

        // 2b. Check duplicate end (same participant, same end, same role)
        $duplicate = ScoreEntry::where([
            'participant_id' => $data->participantId,
            'end_number' => $data->endNumber,
            'entry_role' => $data->entryRole,
        ])->whereNotIn('status', ['overridden'])->exists();
        
        if ($duplicate) {
            throw new DuplicateEndException(
                "End {$data->endNumber} sudah diinput untuk peserta ini"
            );
        }

        // 2c. Persist
        $entry = ScoreEntry::create([
            'tournament_category_id' => $category->id,
            'participant_id' => $data->participantId,
            'end_number' => $data->endNumber,
            'arrows' => $data->arrows,
            'end_total' => $this->calculateEndTotal($data->arrows),
            'x_count' => $this->calculateXCount($data->arrows),
            'scorer_id' => $data->scorerId,
            'entry_role' => $data->entryRole,
            'client_ref' => $data->clientRef,
            'device_submitted_at' => $data->deviceSubmittedAt,
            'status' => 'pending_validation',
        ]);

        return $entry;
    });

    // 3. Event dispatch SETELAH transaction commit (di controller atau via model observer)
    // ScorePersistedEvent::dispatch($entry);
}
```

> **Catatan kritis**: Idempotency check (`client_ref` lookup) dilakukan SEBELUM transaction, bukan di dalamnya. Ini mengurangi lock contention — kalau sudah ada, langsung return tanpa acquire transaction lock.

---

## 6. Deployment Architecture

### 6.1 Phase 1 — Single Server (Go-Live)

```
┌─────────────────────────────────────────────────────────────────┐
│                    VPS / Cloud VM (4 core, 8GB RAM)              │
│                    Ubuntu 22.04 LTS                              │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Nginx                                                     │  │
│  │  - SSL termination (Let's Encrypt)                        │  │
│  │  - Reverse proxy to PHP-FPM                               │  │
│  │  - Static file serving                                    │  │
│  │  - Rate limiting (Layer 1)                                │  │
│  │  - Gzip compression                                       │  │
│  └───────────────────────────┬───────────────────────────────┘  │
│                              │                                   │
│  ┌───────────────────────────▼───────────────────────────────┐  │
│  │ PHP-FPM (8.3)                                             │  │
│  │  - 50 workers (pm = dynamic, min 10, max 50)              │  │
│  │  - OPcache enabled                                        │  │
│  │  - Laravel Octane (optional, evaluate jika performa kurang)│ │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌───────────────┐  ┌───────────────┐  ┌─────────────────────┐ │
│  │ PostgreSQL 16 │  │ Redis 7       │  │ Supervisor          │ │
│  │               │  │               │  │  - queue:work ×2    │ │
│  │ max_conn=200  │  │ maxmemory     │  │  - scheduler        │ │
│  │ shared_buf=2GB│  │ = 512MB       │  │                     │ │
│  │ WAL archiving │  │ AOF enabled   │  │                     │ │
│  └───────────────┘  └───────────────┘  └─────────────────────┘ │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Automated Backup                                          │  │
│  │  - pg_dump daily → GCS / S3 (off-site)                    │  │
│  │  - Redis RDB snapshot daily                               │  │
│  │  - WAL continuous archiving (point-in-time recovery)      │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Monitoring                                                │  │
│  │  - Health check endpoint: GET /api/health                 │  │
│  │  - UptimeRobot / similar (external ping)                  │  │
│  │  - Laravel log → file (logrotate)                         │  │
│  │  - Redis MONITOR (periodic, not continuous)               │  │
│  │  - PostgreSQL pg_stat_statements                          │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 6.2 Server Sizing Justification

| Resource | Alokasi | Justifikasi |
|----------|---------|-------------|
| **CPU (4 core)** | Nginx: 1 core, PHP-FPM: 2 core, PG+Redis: 1 core | 1.000 req/detik polling + 50-100 writes/menit = moderate CPU |
| **RAM (8GB)** | PG shared_buffers: 2GB, PG work_mem: 256MB, Redis: 512MB, PHP-FPM 50 workers × 64MB: 3.2GB, OS: ~1GB | PHP-FPM dominan. 50 workers cukup untuk 1.000 concurrent requests. |
| **Disk** | 50GB SSD | DB + assets. Score data < 1GB per tahun. Assets (images) di GCS. |
| **Bandwidth** | ~100Mbps | 5.000 users × 2KB response × 1 req/5s = 2MB/s = 16Mbps peak. Sangat cukup. |

> **Peringatan untuk solo developer**: Server sizing ini untuk **single tournament concurrent**. Jika dua tournament besar berjalan bersamaan (10.000 total concurrent), pertimbangkan upgrade ke 8 core / 16GB atau pisahkan PostgreSQL ke server sendiri.

### 6.3 Phase 2 — Scaling (Jika Dibutuhkan)

Tidak perlu diimplementasikan sekarang — ini road map jika scale naik.

```
Phase 2a: Pisahkan Database
  App Server ──── PG Server (managed: RDS/Cloud SQL)
                  Redis Server (managed: ElastiCache/Memorystore)
  
Phase 2b: Horizontal App Scaling  
  Load Balancer
  ├── App Server 1
  ├── App Server 2
  └── App Server 3
  → Shared: PG, Redis, GCS

Phase 2c: Read Replica (jika DB jadi bottleneck)
  PG Primary (write) ──── PG Replica (read: leaderboard fallback)
```

---

## 7. API Design Conventions

### 7.1 Endpoint Structure

```
Base URL: /api/v1

# Auth (sudah ada dari starter)
POST   /auth/register
POST   /auth/login
POST   /auth/refresh
POST   /auth/logout
GET    /auth/me

# Clubs
GET    /clubs                          # List (public)
POST   /clubs                          # Register new club
GET    /clubs/{club}                   # Detail
PUT    /clubs/{club}                   # Update profile
GET    /clubs/{club}/members           # List members

# Members
POST   /clubs/{club}/members/join      # Request join
PUT    /clubs/{club}/members/{member}  # Update member status
GET    /members/me/kta                 # Get my KTA

# Tournaments
GET    /tournaments                    # List (public, filterable)
POST   /tournaments                    # Create tournament
GET    /tournaments/{tournament}       # Detail
PUT    /tournaments/{tournament}       # Update

# Tournament Categories
GET    /tournaments/{tournament}/categories
POST   /tournaments/{tournament}/categories

# Participants
POST   /tournaments/{tournament}/register         # Atlet mendaftar
GET    /tournaments/{tournament}/participants      # List peserta
PUT    /tournaments/{tournament}/participants/{id} # Verify/reject

# Targets / Bantalan
POST   /tournaments/{tournament}/targets/generate  # Auto-assign
GET    /tournaments/{tournament}/targets            # List assignment

# Scoring ← CRITICAL PATH
POST   /scores                                     # Submit score (scorer)
GET    /scores/my-target                            # Scorer: lihat bantalan saya
PUT    /scores/{entry}/correct                      # Koreksi skor
POST   /scores/disputes/{entry}/resolve             # Resolve dispute

# Leaderboard ← HOT PATH (public, cached)
GET    /tournaments/{tournament}/categories/{cat}/leaderboard

# Elimination
POST   /tournaments/{tournament}/categories/{cat}/bracket/generate
GET    /tournaments/{tournament}/categories/{cat}/bracket
POST   /elimination/matches/{match}/sets            # Input set score
```

### 7.2 Scoring Endpoint — Detail Contract

```
POST /api/v1/scores

Headers:
  Authorization: Bearer {token}
  Content-Type: application/json
  X-Idempotency-Key: {client_ref}   // Optional header — juga diterima di body

Body:
{
  "client_ref": "550e8400-e29b-41d4-a716-446655440000",
  "participant_id": "...",
  "end_number": 3,
  "arrows": [10, 9, 8, "X", 7, "M"],
  "entry_role": "primary",
  "device_submitted_at": "2026-05-25T10:30:00+07:00"
}

Response 201 (Created):
{
  "success": true,
  "message": "Score submitted successfully",
  "data": {
    "id": "...",
    "end_number": 3,
    "arrows": [10, 9, 8, "X", 7, "M"],
    "end_total": 44,
    "x_count": 1,
    "status": "pending_validation",
    "sync_status": "synced",
    "server_received_at": "2026-05-25T03:30:01Z"
  }
}

Response 200 (Idempotent — already exists):
{
  "success": true,
  "message": "Score already submitted (idempotent)",
  "data": { ... existing entry ... }
}

Response 409 (Conflict — duplicate end, different client_ref):
{
  "success": false,
  "message": "End 3 already submitted for this participant",
  "code": "SCORE_DUPLICATE_END"
}
```

---

## 8. Security Architecture

| Layer | Mekanisme | Detail |
|-------|-----------|--------|
| **Transport** | HTTPS (TLS 1.2+) | SSL termination di Nginx. Force HTTPS di production. |
| **Authentication** | Passport OAuth2 | Access token (8 jam) + Refresh token (30 hari). Sudah ada. |
| **Authorization** | Spatie RBAC + Pivot scoping | Role check + contextual check (misal: scorer hanya bisa input skor bantalan yang di-assign) |
| **Input validation** | FormRequest per endpoint | Validasi ketat: tipe data, range, format. Reject early. |
| **Rate limiting** | Nginx (Layer 1) + Laravel Throttle (Layer 2) | Auth endpoints: 6/menit. Scoring: 120/menit. Leaderboard public: 60/menit per IP. |
| **SQL injection** | Eloquent parameterized queries | Tidak ada raw query tanpa binding. |
| **Data integrity** | DB constraints + Application validation | Unique constraints, FK constraints, check constraints di DB. Business rules di service layer. |
| **Audit** | Spatie Activitylog + custom scoring audit | Semua perubahan data kritis tercatat dengan aktor dan timestamp. |

---

## 9. Catatan Konsistensi dengan Dokumen Sebelumnya

| Keputusan Arsitektur | Referensi Dokumen Sebelumnya | Status |
|---------------------|-------------------------------|--------|
| Redis sorted set untuk leaderboard, bukan tabel snapshot | ERD §Keputusan Arsitektur Scoring, Pertanyaan #3 Opsi D | ✅ Diimplementasikan di Read Path |
| Event-driven side effects (score → leaderboard update) | ERD §Implikasi ke Arsitektur | ✅ Event/Listener layer |
| Idempotency check sebelum transaction | Offline Analysis §C1 | ✅ Detail di ScoringService pseudocode |
| Dual-entry comparison di listener | ERD, User Stories US-SC-04 | ✅ CheckConflictListener |
| Scorer scoped ke bantalan via pivot | ERD §scorer_assignments | ✅ Policy check di scoring endpoint |
| Tournament state machine | ERD §tournaments | ✅ TournamentStatusGate middleware |
| Polling (bukan WebSocket/SSE) | PRD constraint: solo developer, 5s stale acceptable | ✅ §4 dengan full justifikasi |
| Graceful degradation jika Redis down | PRD constraint performa | ✅ §Pertanyaan Pemantik + multi-layer cache fallback |
| In-process cache untuk stampede protection | PRD T-05: 5.000 concurrent | ✅ Layer 1 di Read Path |

### Implikasi ke Dokumen Selanjutnya

| Keputusan | Implikasi di Timeline (05) | Implikasi di Risk (06) | Implikasi di Testing (07) |
|-----------|---------------------------|----------------------|--------------------------|
| Multi-layer caching | Implementasi caching = bagian Phase 3 | Risiko: cache invalidation bug | Test: leaderboard staleness test |
| Polling + adaptive interval | Mobile dev effort dikurangi (vs WS) | Risiko: polling overload jika interval terlalu pendek | Load test: 5.000 virtual users polling |
| Event/Listener pattern | Implementasi event system = bagian Phase 3 | Risiko: listener gagal silently | Test: event listener coverage |
| Single server deployment | Setup infra = Phase 1 | Risiko: SPOF | Test: failover scenario |
| Offline sync flow | Mobile + backend = Phase 3 | Risiko: data loss saat sync | Test: offline-online cycle |
