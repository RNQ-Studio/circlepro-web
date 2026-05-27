# 07 — Testing Plan

> **Project**: Manahpro — Platform Tata Kelola Tournament & Scoring Panahan
> **Versi**: 1.0
> **Tanggal**: 2026-05-25
> **Referensi**: Seluruh dokumen 00–06

---

## Proses Berpikir: Pertanyaan Pemantik

### Bagaimana kamu tahu sistem siap untuk tournament pertama?

Ini bukan pertanyaan retoris — ini pertanyaan yang harus dijawab dengan **bukti terukur**, bukan perasaan.

Saya perlu membedakan tiga level kesiapan:

| Level | Definisi | Bagaimana Tahu |
|-------|---------|----------------|
| **Fungsional** | Semua fitur MUST bekerja correct | Semua feature test pass, 0 failing test |
| **Performant** | Sistem menahan beban nyata | Load test lulus target (p95 <500ms / 3.000 concurrent) |
| **Resilient** | Sistem tahan terhadap kegagalan | Offline-sync test 0% data loss, graceful degradation verified, backup restore tested |

Sistem siap jika **ketiga level terpenuhi**. Fungsional saja tidak cukup — sistem yang correct tapi lambat akan gagal di lapangan. Sistem yang cepat tapi tidak tahan offline akan gagal di daerah sinyal buruk.

### Apa satu kondisi yang jika gagal, kamu akan tunda go-live?

**Jawaban: Jika load test leaderboard gagal di 2.000 concurrent users (bukan 5.000 — tapi bahkan 2.000).**

Kenapa ini, bukan yang lain?

- Bug fungsional bisa di-hotfix dalam hitungan jam
- Offline-sync bug bisa di-workaround dengan score sheet kertas
- RBAC bug bisa di-mitigasi dengan akun terbatas

Tapi **leaderboard yang tidak bisa menahan beban** berarti seluruh value proposition platform — "live scoring" — runtuh. Dan ini bukan sesuatu yang bisa di-hotfix di hari-H. Ini masalah arsitektur yang butuh waktu minggu-an untuk diperbaiki.

Jika leaderboard gagal di 2.000 concurrent: **TUNDA GO-LIVE**, fix arsitektur caching, re-test, baru launch.

---

## 1. Strategi Testing — Overview

### 1.1 Piramida Testing Manahpro

```
                    ┌───────────────┐
                    │   Manual /    │   ← Sedikit: staging tournament simulation
                    │   Exploratory │      Dilakukan 1-2 kali sebelum go-live
                    ├───────────────┤
                    │  Load / Perf  │   ← Targeted: leaderboard, scoring, spike
                    │    Testing    │      Dilakukan di Phase 4
                    ├───────────────┤
                    │   Feature     │   ← Banyak: setiap endpoint, setiap flow
                    │   Tests       │      HTTP test via Laravel TestCase
                    │   (API)       │      Target: 85%+ coverage core modules
                    ├───────────────┤
                    │  Unit Tests   │   ← Focused: service layer, scoring logic,
                    │               │      bracket generation, leaderboard calc
                    └───────────────┘      Target: 90%+ coverage critical services
```

### 1.2 Prinsip Testing

| Prinsip | Implementasi |
|---------|-------------|
| **Test what matters most** | Scoring engine dan leaderboard = coverage tertinggi. CRUD club = coverage standard. |
| **Automate first, manual second** | Solo developer tidak punya waktu untuk manual regression testing. Semua yang bisa di-automate, harus. |
| **Test against real database** | PHPUnit dikonfigurasi pakai PostgreSQL (sudah ada di starter). Bukan SQLite. Behavior PG (partial indexes, JSONB, UNIQUE constraints) harus ditest di environment yang sama. |
| **Test the unhappy path** | Happy path biasanya benar. Yang berbahaya: duplicate submission, concurrent conflict, edge case arrow values (X, M, 0). |
| **Load test before go-live, not after** | Tidak ada "kita lihat nanti di production". Load test adalah gate wajib Phase 4. |

### 1.3 Tools

| Tool | Purpose | Justifikasi |
|------|---------|-------------|
| **PHPUnit** (bawaan Laravel) | Unit + Feature test | Standard Laravel, sudah configured di starter |
| **Laravel TestCase** | HTTP endpoint testing | `$this->postJson()`, `$this->assertStatus()`, dll |
| **Factories + Seeders** | Test data generation | Eloquent factories untuk semua entitas |
| **k6** (Grafana) | Load testing | Open source, scriptable di JavaScript, lightweight. Alternatif: Locust (Python). |
| **Laravel Pint** | Code style | Sudah ada — CI gate |
| **Larastan** (PHPStan) | Static analysis | Sudah ada — CI gate. Level 5 minimum. |
| **`php artisan test --coverage`** | Coverage report | Verify coverage thresholds |

---

## 2. Unit Tests — Service Layer

### 2.1 ScoringService Tests

Ini adalah test paling kritis di seluruh project. ScoringService menangani: idempotency, validation, persistence, conflict detection.

| Test Case | Input | Expected Outcome | Prioritas |
|-----------|-------|-------------------|-----------|
| **Submit skor valid — happy path** | Valid participant, end_number=3, arrows=[10,9,8,X,7,M] | Entry created, status=pending_validation, end_total=44, x_count=1 | MUST |
| **Idempotent submit — same client_ref** | Submit dengan client_ref yang sudah ada | Return existing entry tanpa insert baru, HTTP 200 (bukan 201) | MUST |
| **Duplicate end — different client_ref** | End_number=3 sudah ada untuk participant+role ini, tapi client_ref berbeda | Exception: DuplicateEndException | MUST |
| **Arrow value validation — X** | arrows=[X, X, 10, 9, 8, 7] | end_total=54, x_count=2 (X dihitung sebagai 10) | MUST |
| **Arrow value validation — M** | arrows=[M, 10, 9, 8, 7, 6] | end_total=40, x_count=0 (M dihitung sebagai 0) | MUST |
| **Arrow value validation — out of range** | arrows=[11, 9, 8, 7, 6, 5] | ValidationException: arrow value 11 invalid | MUST |
| **Arrow count mismatch** | Category arrows_per_end=6, tapi kirim 5 arrows | ValidationException: expected 6 arrows | MUST |
| **End number out of range** | end_number=0 atau end_number > category.total_ends | ValidationException | MUST |
| **Participant not verified** | participant.status != 'verified' | BusinessException: participant not eligible | MUST |
| **Participant not checked in** | participant.checked_in = false | BusinessException: participant not checked in | SHOULD |
| **Scorer not assigned to target** | Scorer tidak punya assignment ke bantalan peserta ini | AuthorizationException (403) | MUST |
| **Conflict detection — same scores** | Primary dan validator submit arrows identik | Both entries → status=confirmed | MUST |
| **Conflict detection — different scores** | Primary=[10,9,8,7,6,5] Validator=[10,9,8,7,6,6] | Both entries → status=disputed | MUST |
| **Score correction — pending entry** | Edit entry yang status=pending_validation | New entry created, old entry linked via previous_entry_id, correction logged | MUST |
| **Score correction — confirmed entry** | Edit entry yang status=confirmed | Rejected: cannot edit confirmed score | MUST |
| **Score correction — disputed entry** | Edit entry yang status=disputed | Rejected: score under dispute | MUST |
| **Provisional status — timeout** | Hanya primary submit, validator belum, timeout elapsed | Status → provisional (skor masuk leaderboard) | SHOULD |

```php
// Contoh test — pseudocode
class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_valid_score_creates_entry(): void
    {
        // Arrange
        $tournament = Tournament::factory()->ongoing()->create();
        $category = TournamentCategory::factory()
            ->for($tournament)
            ->create(['arrows_per_end' => 6, 'total_ends' => 10]);
        $participant = TournamentParticipant::factory()
            ->for($category)
            ->verified()
            ->checkedIn()
            ->create();
        $scorer = User::factory()->create();
        ScorerAssignment::factory()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $scorer->id,
            'target_labels' => [$participant->target->target_label],
        ]);

        // Act
        $entry = $this->scoringService->submitScore(new ScoreSubmissionDTO(
            clientRef: Str::uuid(),
            participantId: $participant->id,
            endNumber: 3,
            arrows: [10, 9, 8, 'X', 7, 'M'],
            entryRole: 'primary',
            scorerId: $scorer->id,
            deviceSubmittedAt: now(),
        ));

        // Assert
        $this->assertDatabaseHas('score_entries', [
            'id' => $entry->id,
            'end_total' => 44,
            'x_count' => 1,
            'status' => 'pending_validation',
        ]);
    }

    public function test_idempotent_submit_returns_existing_entry(): void
    {
        // Arrange
        $clientRef = Str::uuid();
        $existing = ScoreEntry::factory()->create(['client_ref' => $clientRef]);

        // Act
        $result = $this->scoringService->submitScore(new ScoreSubmissionDTO(
            clientRef: $clientRef,
            // ... same data ...
        ));

        // Assert
        $this->assertEquals($existing->id, $result->id);
        $this->assertDatabaseCount('score_entries', 1); // Tidak ada entry baru
    }

    public function test_conflict_detected_when_arrows_differ(): void
    {
        // Arrange — primary sudah submit
        $primary = ScoreEntry::factory()->create([
            'participant_id' => $participant->id,
            'end_number' => 3,
            'entry_role' => 'primary',
            'arrows' => [10, 9, 8, 7, 6, 5],
            'status' => 'pending_validation',
        ]);

        // Act — validator submit skor berbeda
        $validator = $this->scoringService->submitScore(new ScoreSubmissionDTO(
            // ... same participant, end, but different arrows ...
            arrows: [10, 9, 8, 7, 6, 6], // arrow ke-6 beda: 5 vs 6
            entryRole: 'validator',
        ));

        // Assert
        $this->assertEquals('disputed', $primary->fresh()->status);
        $this->assertEquals('disputed', $validator->status);
    }
}
```

### 2.2 LeaderboardService Tests

| Test Case | Input | Expected Outcome | Prioritas |
|-----------|-------|-------------------|-----------|
| **Leaderboard calculation — basic** | 3 peserta, masing-masing 5 rambahan | Ranking benar: peserta dengan total tertinggi di posisi 1 | MUST |
| **X-count tiebreak** | 2 peserta total skor sama, beda x_count | Peserta dengan x_count lebih tinggi = ranking lebih atas | MUST |
| **Exclude overridden entries** | Ada entry status=overridden | Entry tersebut TIDAK masuk perhitungan leaderboard | MUST |
| **Include provisional entries** | Ada entry status=provisional | Entry tersebut MASUK perhitungan leaderboard | MUST |
| **Disputed entries — use primary** | Ada entry disputed | Gunakan skor primary scorer untuk leaderboard (sampai resolved) | MUST |
| **Redis fallback — Redis down** | Redis connection exception | Leaderboard tetap bisa di-retrieve dari PostgreSQL | MUST |
| **Leaderboard rebuild** | Semua cache dihapus | Rebuild dari score_entries menghasilkan ranking identik | MUST |
| **Empty leaderboard** | Tournament baru, belum ada skor | Return array kosong, bukan error | MUST |
| **Only primary entries counted** | Ada primary dan validator entries | Hanya primary yang masuk leaderboard | MUST |

### 2.3 EliminationService Tests

| Test Case | Input | Expected Outcome | Prioritas |
|-----------|-------|-------------------|-----------|
| **Generate bracket — 32 peserta** | 32 peserta, sorted by qualification rank | 16 matches round 1: 1v32, 2v31, ..., 16v17 | MUST |
| **Generate bracket — 16 peserta** | 16 peserta | 8 matches round 1 | MUST |
| **Generate bracket — 24 peserta (non-power-of-2)** | 24 peserta | 8 bye (top 8 langsung ke round 2), 8 matches round 1 | MUST |
| **Generate bracket — 30 peserta** | 30 peserta | 2 bye (top 2 langsung), 14 matches round 1 | MUST |
| **Set point calculation — win** | Archer A: 28, Archer B: 25 | A gets 2 set points, B gets 0 | MUST |
| **Set point calculation — draw** | Archer A: 27, Archer B: 27 | Both get 1 set point | MUST |
| **Match win condition** | A reaches 6 set points | Match complete, A = winner | MUST |
| **Shoot-off trigger** | After 5 sets, both at 5-5 set points | Shoot-off required | MUST |
| **Winner advancement** | Match completed | Winner's ID populated in next_match | MUST |
| **Bronze match generation** | Semi-final completed | Bronze medal match created with semi-final losers | MUST |

---

## 3. Feature Tests — API Endpoints

### 3.1 Pendekatan

Setiap endpoint API harus punya minimal feature test yang memverifikasi:
1. **Happy path** — request valid → response benar
2. **Auth guard** — request tanpa token → 401
3. **RBAC guard** — request dengan role salah → 403
4. **Validation** — request dengan data invalid → 422 dengan error detail
5. **Edge case** — boundary conditions, empty data, etc.

### 3.2 Test Matrix per Modul

#### Auth Module (sudah ada dari starter — extend jika perlu)

| Endpoint | Happy | Auth | Validation | Edge Case |
|----------|:-----:|:----:|:----------:|:---------:|
| POST /auth/register | ✅ | n/a | ✅ duplicate email | ✅ weak password |
| POST /auth/login | ✅ | n/a | ✅ wrong password | ✅ inactive account |
| POST /auth/refresh | ✅ | ✅ | ✅ expired token | |
| POST /auth/logout | ✅ | ✅ | | |
| GET /auth/me | ✅ | ✅ | | |

#### Club Module

| Endpoint | Happy | Auth | RBAC | Validation | Edge Case |
|----------|:-----:|:----:|:----:|:----------:|:---------:|
| GET /clubs | ✅ | n/a (public) | | ✅ filter invalid | ✅ empty list |
| POST /clubs | ✅ | ✅ | ✅ must be athlete | ✅ missing fields | ✅ duplicate name |
| GET /clubs/{id} | ✅ | n/a | | | ✅ not found |
| PUT /clubs/{id} | ✅ | ✅ | ✅ must be club admin | ✅ | ✅ edit other club |
| POST /clubs/{id}/members/join | ✅ | ✅ | | | ✅ already member |
| PUT /clubs/{id}/members/{mid} | ✅ | ✅ | ✅ must be club admin | ✅ | ✅ approve/reject |

#### Tournament Module

| Endpoint | Happy | Auth | RBAC | Validation | Edge Case |
|----------|:-----:|:----:|:----:|:----------:|:---------:|
| GET /tournaments | ✅ | n/a | | ✅ filter | ✅ empty |
| POST /tournaments | ✅ | ✅ | ✅ admin-tournament | ✅ missing fields | |
| PUT /tournaments/{id} | ✅ | ✅ | ✅ tournament official | ✅ | ✅ edit after started |
| POST /tournaments/{id}/categories | ✅ | ✅ | ✅ | ✅ | ✅ add during ongoing |
| POST /tournaments/{id}/register | ✅ | ✅ | ✅ athlete | ✅ | ✅ quota full, deadline passed |
| PUT /tournaments/{id}/participants/{pid} | ✅ | ✅ | ✅ admin | ✅ | ✅ verify tanpa bukti bayar |
| POST /tournaments/{id}/targets/generate | ✅ | ✅ | ✅ admin | | ✅ no verified participants |

#### Scoring Module ⚠️ — Test paling detail

| Endpoint | Happy | Auth | RBAC | Validation | Edge Case | Offline |
|----------|:-----:|:----:|:----:|:----------:|:---------:|:-------:|
| POST /scores | ✅ | ✅ | ✅ scorer assigned | ✅ arrows invalid | ✅ idempotent | ✅ retry |
| POST /scores (validator) | ✅ | ✅ | ✅ | | ✅ conflict detect | |
| PUT /scores/{id}/correct | ✅ | ✅ | ✅ | ✅ | ✅ edit confirmed → reject | |
| POST /scores/disputes/{id}/resolve | ✅ | ✅ | ✅ admin/chief judge | ✅ | ✅ already resolved | |
| GET /scores/my-target | ✅ | ✅ | ✅ | | ✅ no assignments | |

#### Leaderboard Module

| Endpoint | Happy | Auth | Rate Limit | Edge Case |
|----------|:-----:|:----:|:----------:|:---------:|
| GET /leaderboard/{catId} | ✅ | n/a (public) | ✅ verify throttle | ✅ empty, ✅ 304 Not Modified |
| GET /bracket/{catId} | ✅ | n/a | ✅ | ✅ bracket not generated yet |

### 3.3 RBAC Matrix Test

Test otomatis yang iterate semua kombinasi role × endpoint dan verify respons:

```php
// Konsep: RBAC matrix test
class RbacMatrixTest extends TestCase
{
    /**
     * Data provider: [role, endpoint, method, expected_status]
     */
    public static function rbacMatrix(): array
    {
        return [
            // Scorer CANNOT create tournament
            ['scorer', 'POST /api/v1/tournaments', [], 403],
            
            // Athlete CANNOT submit score
            ['athlete', 'POST /api/v1/scores', [...], 403],
            
            // Club admin A CANNOT edit Club B
            ['admin-club-A', 'PUT /api/v1/clubs/{club_B}', [...], 403],
            
            // Scorer A CANNOT submit score to Scorer B's target
            ['scorer-A', 'POST /api/v1/scores', ['target_of_B'], 403],
            
            // Unauthenticated CAN access leaderboard
            [null, 'GET /api/v1/leaderboard/1', [], 200],
            
            // Unauthenticated CANNOT submit score
            [null, 'POST /api/v1/scores', [...], 401],
            
            // ... extend for all critical combinations
        ];
    }

    /**
     * @dataProvider rbacMatrix
     */
    public function test_rbac_enforcement(
        ?string $role, string $endpoint, array $data, int $expectedStatus
    ): void {
        $user = $role ? $this->createUserWithRole($role) : null;
        
        $response = $user 
            ? $this->actingAs($user, 'api')->json(...$this->parseEndpoint($endpoint), $data)
            : $this->json(...$this->parseEndpoint($endpoint), $data);
        
        $response->assertStatus($expectedStatus);
    }
}
```

> **Target**: Minimal 30 kombinasi role × endpoint di RBAC matrix test. Setiap role harus punya minimal 5 test case "ini TIDAK boleh diakses oleh role ini."

---

## 4. Load Testing — Skenario & Target

### 4.1 Environment Load Test

| Aspek | Detail |
|-------|--------|
| **Server** | Production-equivalent VM (4 core, 8GB RAM) atau production server sendiri |
| **Database** | Seeded: 1 tournament, 500 peserta, 30.000 score entries, leaderboard populated |
| **Redis** | Populated: leaderboard sorted set, cached responses |
| **Tool** | k6 (Grafana Labs) — scriptable, lightweight, reporting |
| **Network** | k6 dijalankan dari VPS berbeda (bukan localhost) untuk simulate real latency |

### 4.2 Skenario Load Test

#### Skenario 1: Leaderboard Sustained Load

```javascript
// k6 script — konseptual
export const options = {
    scenarios: {
        leaderboard_sustained: {
            executor: 'constant-vus',
            vus: 3000,
            duration: '5m',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1000'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    const res = http.get(`${BASE_URL}/api/v1/tournaments/${TOURNAMENT_ID}/categories/${CAT_ID}/leaderboard`);
    check(res, {
        'status is 200': (r) => r.status === 200,
        'has data': (r) => JSON.parse(r.body).data.length > 0,
    });
    sleep(randomIntBetween(3, 7)); // Simulate real user poll interval
}
```

| Metric | Target | Red Flag |
|--------|--------|----------|
| p95 response time | <500ms | >1.000ms |
| p99 response time | <1.000ms | >3.000ms |
| Error rate | <1% | >5% |
| Throughput | >500 req/s | <200 req/s |

#### Skenario 2: Scoring Write Under Read Load

Simulasi realistis: 50 scorer menginput skor bersamaan SEMENTARA 3.000 penonton polling leaderboard.

```javascript
export const options = {
    scenarios: {
        scorers: {
            executor: 'constant-vus',
            vus: 50,
            duration: '5m',
            exec: 'scorerFlow',
        },
        spectators: {
            executor: 'constant-vus',
            vus: 3000,
            duration: '5m',
            exec: 'spectatorFlow',
        },
    },
};

export function scorerFlow() {
    const payload = JSON.stringify({
        client_ref: uuidv4(),
        participant_id: randomParticipant(),
        end_number: randomIntBetween(1, 10),
        arrows: randomArrows(6),
        entry_role: 'primary',
        device_submitted_at: new Date().toISOString(),
    });
    
    const res = http.post(`${BASE_URL}/api/v1/scores`, payload, {
        headers: { 
            'Authorization': `Bearer ${SCORER_TOKEN}`,
            'Content-Type': 'application/json',
        },
    });
    
    check(res, {
        'score submitted': (r) => r.status === 201 || r.status === 200,
    });
    sleep(randomIntBetween(15, 45)); // Scorer submit setiap 15-45 detik
}

export function spectatorFlow() {
    const res = http.get(`${BASE_URL}/api/v1/tournaments/${TOURNAMENT_ID}/categories/${CAT_ID}/leaderboard`);
    check(res, {
        'leaderboard ok': (r) => r.status === 200,
    });
    sleep(randomIntBetween(3, 7));
}
```

| Metric | Target | Red Flag |
|--------|--------|----------|
| Scoring p95 | <300ms | >1.000ms |
| Leaderboard p95 (under write load) | <500ms | >1.500ms |
| Score error rate | 0% | >0% (skor TIDAK BOLEH gagal) |
| Leaderboard reflects new score | <10 detik | >30 detik |

#### Skenario 3: Spike Test (Flash Crowd)

Simulasi: 0 → 5.000 users dalam 30 detik (misalnya saat babak final dimulai dan link leaderboard dishare di WhatsApp).

```javascript
export const options = {
    scenarios: {
        spike: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 5000 },  // Ramp up
                { duration: '2m', target: 5000 },    // Sustain
                { duration: '30s', target: 0 },      // Ramp down
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<1000'],  // Lebih lenient saat spike
        http_req_failed: ['rate<0.05'],      // Allow 5% error during spike
    },
};
```

| Metric | Target | Red Flag |
|--------|--------|----------|
| p95 during ramp-up | <1.000ms | >3.000ms |
| p95 during sustain | <500ms (harus stabilize) | >1.500ms |
| Error rate during ramp-up | <5% | >20% |
| Recovery time after spike | <30 detik | >2 menit |

#### Skenario 4: Idempotency Stress Test

50 client mengirim request yang SAMA (same client_ref) secara concurrent — verify hanya 1 entry yang tersimpan.

| Metric | Target |
|--------|--------|
| Database entries created | Exactly 1 |
| All responses | 200 atau 201 (tidak ada 500) |
| No duplicate data | Verified via COUNT query |

### 4.3 Kapan Load Test Dianggap LULUS

| Gate | Kondisi | Mandatory? |
|------|---------|-----------|
| Skenario 1 lulus | p95 <500ms, error <1% pada 3.000 VU | ✅ WAJIB |
| Skenario 2 lulus | Scoring 0% error, leaderboard update <10 detik | ✅ WAJIB |
| Skenario 3 lulus | Recovery <30 detik setelah spike | ✅ WAJIB |
| Skenario 4 lulus | Exactly 1 entry, 0 duplicate | ✅ WAJIB |
| Skenario 1 pada 5.000 VU | p95 <500ms | ⚠️ STRETCH — nice to have |

---

## 5. Skenario Test Offline Sync

### 5.1 Test Matrix Offline-Online

Ini test yang dilakukan di **device fisik** (bukan emulator) karena perilaku network di emulator tidak selalu representatif.

| # | Skenario | Steps | Expected Result |
|---|----------|-------|-----------------|
| OS-01 | **Basic offline submit → sync** | 1. Scorer online, input 1 skor → berhasil. 2. Aktifkan airplane mode. 3. Input 3 skor. 4. Matikan airplane mode. | 3 skor auto-sync, muncul di leaderboard, status "synced" di UI |
| OS-02 | **Rapid offline/online toggle** | 1. Input skor. 2. Airplane mode ON. 3. Input skor. 4. Airplane mode OFF (3 detik). 5. Airplane mode ON. 6. Input skor. 7. Airplane mode OFF. | Semua skor ter-sync. Tidak ada duplikat. Urutan benar. |
| OS-03 | **Duplicate submission (retry)** | 1. Input skor saat online. 2. Force-kill app sebelum response diterima. 3. Buka app → queue retry. | Server return 200 (idempotent). Tidak ada duplikat di DB. |
| OS-04 | **Long offline (30 menit)** | 1. Airplane mode ON. 2. Input 10 skor selama 30 menit. 3. Airplane mode OFF. | 10 skor sync berurutan by device_submitted_at. Semua synced. |
| OS-05 | **Server error during sync** | 1. Offline, input 3 skor. 2. Online, tapi server return 500 untuk request pertama. | Retry pertama gagal → retry dengan backoff. Skor kedua dan ketiga berhasil. Skor pertama eventually synced. |
| OS-06 | **Validation error during sync** | 1. Offline, input skor dengan end_number yang sudah ada (misal dari scorer lain). | Server return 409. Entry ditandai "failed" di UI. User dinotifikasi. Skor lain tetap sync. |
| OS-07 | **Battery/app kill during sync** | 1. Offline, input 5 skor. 2. Online, sync mulai. 3. Kill app saat skor ke-3 sedang syncing. 4. Buka app. | Skor 1-2 sudah synced. Skor 3 mungkin synced (idempotent retry safe). Skor 4-5 masih di queue. Resume sync. |
| OS-08 | **Clock drift di device** | 1. Set waktu device 2 jam ke depan. 2. Input skor. 3. Sync. | Server terima dengan device_submitted_at yang "masa depan". server_received_at tetap akurat. Tidak ada error. |

### 5.2 Acceptance Criteria Offline Sync

| Criteria | Target | Cara Verifikasi |
|----------|--------|-----------------|
| Data loss rate | **0%** | Audit: COUNT skor di server = COUNT skor yang di-input di device |
| Duplicate rate | **0%** | Query: SELECT client_ref, COUNT(*) GROUP BY client_ref HAVING COUNT > 1 = empty |
| Sync time (setelah online) | **<30 detik untuk 10 entry** | Stopwatch manual |
| Retry mechanism | **Exponential backoff berfungsi** | Log analysis: retry interval meningkat (1s, 2s, 4s, ...) |
| UI indicator accuracy | **Status selalu benar** | Visual check: pending saat offline, syncing saat online, synced setelah berhasil |

---

## 6. Pre-Launch Checklist

Checklist ini di-review **1 minggu sebelum tournament pertama**. Semua item WAJIB ✅ sebelum go-live.

### 6.1 Fungsionalitas

| # | Item | Verified By | Status |
|---|------|-------------|--------|
| F-01 | Semua unit tests pass | `php artisan test --testsuite=Unit` | ☐ |
| F-02 | Semua feature tests pass | `php artisan test --testsuite=Feature` | ☐ |
| F-03 | Zero failing tests | CI pipeline green | ☐ |
| F-04 | Static analysis pass (Larastan level 5) | `composer analyse` | ☐ |
| F-05 | Code style pass (Pint) | `composer lint` | ☐ |
| F-06 | Test coverage: ScoringService ≥90% | Coverage report | ☐ |
| F-07 | Test coverage: LeaderboardService ≥90% | Coverage report | ☐ |
| F-08 | Test coverage: EliminationService ≥85% | Coverage report | ☐ |
| F-09 | Tournament end-to-end simulation pass | Manual test di staging | ☐ |
| F-10 | RBAC matrix test: ≥30 combinations pass | `php artisan test --filter=RbacMatrix` | ☐ |

### 6.2 Performa

| # | Item | Verified By | Status |
|---|------|-------------|--------|
| P-01 | Load test Skenario 1 lulus (3.000 VU leaderboard) | k6 report | ☐ |
| P-02 | Load test Skenario 2 lulus (scoring + leaderboard) | k6 report | ☐ |
| P-03 | Load test Skenario 3 lulus (spike test) | k6 report | ☐ |
| P-04 | Load test Skenario 4 lulus (idempotency stress) | k6 report | ☐ |
| P-05 | No slow queries (>100ms) di production DB | pg_stat_statements | ☐ |
| P-06 | Redis memory usage <50% maxmemory | redis-cli INFO memory | ☐ |
| P-07 | PHP-FPM tidak pernah exhaust workers saat load test | FPM status page | ☐ |

### 6.3 Offline Sync

| # | Item | Verified By | Status |
|---|------|-------------|--------|
| O-01 | Offline sync test OS-01 s/d OS-06 pass | Manual test di 2 device fisik | ☐ |
| O-02 | 0% data loss dalam 50 siklus offline/online | Audit count | ☐ |
| O-03 | 0% duplicate dalam 50 siklus | DB query | ☐ |
| O-04 | Sync indicator di UI akurat | Visual check | ☐ |

### 6.4 Infrastruktur & Operasional

| # | Item | Verified By | Status |
|---|------|-------------|--------|
| I-01 | Production server running dan healthy | Health check endpoint | ☐ |
| I-02 | SSL certificate valid dan auto-renew configured | curl https + certbot status | ☐ |
| I-03 | Automated backup berjalan (pg_dump daily) | Check backup file di GCS/S3 | ☐ |
| I-04 | Backup RESTORE tested — data intact | Restore ke server test, verify data | ☐ |
| I-05 | Queue worker running via Supervisor | supervisorctl status | ☐ |
| I-06 | Scheduler running (cron) | `crontab -l`, verify recent execution | ☐ |
| I-07 | Monitoring aktif (UptimeRobot / equivalent) | Check alert history | ☐ |
| I-08 | Log rotation configured | logrotate config + verify | ☐ |
| I-09 | Firewall configured (hanya 80, 443, SSH) | `ufw status` | ☐ |
| I-10 | Runbook operasional tersedia | Dokumen di repo | ☐ |

### 6.5 Security

| # | Item | Verified By | Status |
|---|------|-------------|--------|
| S-01 | `composer audit` — zero high/critical vulnerabilities | Terminal | ☐ |
| S-02 | APP_DEBUG=false di production | .env check | ☐ |
| S-03 | APP_KEY unique (bukan default) | .env check | ☐ |
| S-04 | Passport keys generated (bukan dari repo) | Check file existence | ☐ |
| S-05 | CORS configured restrictively | config/cors.php review | ☐ |
| S-06 | Rate limiting aktif dan verified | Test: hit endpoint >limit → 429 | ☐ |
| S-07 | No sensitive data di error response (production) | Test: trigger error → verify no stack trace | ☐ |
| S-08 | Database credentials tidak di-commit | .env in .gitignore, verify | ☐ |

---

## 7. Template Bug Report

Template untuk digunakan saat menemukan bug selama development dan testing.

```markdown
## Bug Report

### Identitas
- **ID**: BUG-{nomor}
- **Reporter**: {nama}
- **Tanggal**: {tanggal}
- **Severity**: P0 (Critical) / P1 (High) / P2 (Medium) / P3 (Low)
- **Status**: Open / In Progress / Fixed / Verified / Closed

### Deskripsi
{Satu kalimat yang menjelaskan bug}

### Steps to Reproduce
1. {Step 1}
2. {Step 2}
3. {Step 3}

### Expected Behavior
{Apa yang seharusnya terjadi}

### Actual Behavior
{Apa yang sebenarnya terjadi}

### Environment
- Server: {production / staging / local}
- App Version: {versi}
- Device: {jika mobile: model + OS version}
- Browser: {jika web: browser + version}

### Evidence
- Screenshot/recording: {attach}
- Log snippet: {paste}
- API request/response: {paste}

### Impact
- {Berapa user yang terdampak?}
- {Apakah ada workaround?}
- {Apakah blocking tournament?}

### Root Cause (diisi saat fixing)
{Analisis penyebab}

### Fix
{Deskripsi fix + commit hash}

### Regression Test
{Test case yang ditambahkan untuk mencegah recurrence}
```

### Severity Definitions

| Severity | Definisi | Response Time | Contoh |
|----------|---------|---------------|--------|
| **P0 — Critical** | Sistem down, data loss, scoring tidak bisa jalan | Fix dalam jam (hotfix) | Score entry hilang, leaderboard crash, 500 error semua endpoint |
| **P1 — High** | Fitur inti broken tapi ada workaround | Fix dalam 1 hari | Bracket salah generate, conflict tidak terdeteksi, RBAC bypass |
| **P2 — Medium** | Fitur non-inti broken atau UX buruk | Fix dalam 1 minggu | Notifikasi tidak terkirim, pagination salah, filter tidak bekerja |
| **P3 — Low** | Cosmetic, typo, minor inconvenience | Fix di sprint berikutnya | Typo di response message, format tanggal tidak konsisten |

---

## 8. Catatan Konsistensi dengan Dokumen Sebelumnya

| Test Area | Referensi Dokumen | Verifikasi |
|-----------|-------------------|------------|
| Idempotency test (client_ref) | Offline Analysis §C1, ERD §score_entries | Unit test + load test Skenario 4 |
| Conflict detection test | User Stories US-SC-04, ERD §status flow | Unit test ScoringService |
| Leaderboard perf <500ms | PRD T-05, Architecture §2.3 | Load test Skenario 1 |
| Graceful degradation (Redis down) | Architecture §Pertanyaan Pemantik | Feature test: Redis fallback |
| RBAC scoped roles (scorer→bantalan) | ERD §scorer_assignments, US-AT-06 | RBAC matrix test |
| Provisional score di leaderboard | US-SC-04 AC4, ERD provisional status | Unit test LeaderboardService |
| Score correction → new entry | US-SC-03, ERD §score_corrections | Unit test + feature test |
| Bracket generation (bye handling) | US-AT-05 AC4, ERD §elimination | Unit test EliminationService |
| Offline-sync 0% data loss | PRD T-02, Risk R-06 | Offline test OS-01 s/d OS-07 |
| Go-live gate: load test mandatory | PRD §7.3 Red Flags, Risk R-01 | Pre-launch checklist P-01 s/d P-04 |
| Score sheet kertas sebagai backup | Risk R-06 kontigensi | Operational procedure (di luar scope testing) |
