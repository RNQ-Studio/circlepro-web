# ARCHITECTURE

Dokumen ini mendefinisikan arsitektur keseluruhan Laravel Starter: layering, struktur direktori, strategi autentikasi, package, dan best practice integrasi dengan Flutter.

---

## 1. Gambaran Besar Sistem

Satu codebase Laravel melayani dua konsumen melalui dua jalur auth yang berbeda, di atas satu domain logic yang sama.

```
                    ┌──────────────────────────────────────┐
                    │            Flutter Mobile App          │
                    └───────────────────┬────────────────────┘
                                        │  HTTPS + Bearer Token (OAuth2)
                                        ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                              LARAVEL APPLICATION                            │
│                                                                            │
│   ┌─────────────────────────┐         ┌──────────────────────────────┐    │
│   │   API Routes            │         │   Web Routes (Back-office)     │    │
│   │   routes/api.php        │         │   Filament Panel               │    │
│   │   guard: api (Passport) │         │   guard: web (session)         │    │
│   └───────────┬─────────────┘         └───────────────┬──────────────┘    │
│               │                                       │                   │
│               ▼                                       ▼                   │
│   ┌─────────────────────────┐         ┌──────────────────────────────┐    │
│   │   API Controllers       │         │   Filament Resources / Pages   │    │
│   │   (thin)                │         │   (panel-driven)               │    │
│   └───────────┬─────────────┘         └───────────────┬──────────────┘    │
│               │                                       │                   │
│               └───────────────┬───────────────────────┘                   │
│                               ▼                                           │
│               ┌───────────────────────────────────┐                       │
│               │        SERVICE LAYER (Domain)      │  ◄── logika bisnis    │
│               │        app/Services/*              │      bersama          │
│               └───────────────┬───────────────────┘                       │
│                               ▼                                           │
│               ┌───────────────────────────────────┐                       │
│               │        ELOQUENT MODELS             │  ◄── data layer       │
│               │        app/Models/*                │                       │
│               └───────────────┬───────────────────┘                       │
│                               │                                           │
│   Cross-cutting: RBAC (spatie) │ API Response (Resources + envelope)       │
└───────────────────────────────┼──────────────────────────────────────────┘
                                ▼
                    ┌───────────────────────┐
                    │   PostgreSQL 16/17     │
                    └───────────────────────┘
```

**Prinsip kunci:** Service layer adalah tempat logika bisnis. Baik API Controller maupun Filament Resource memanggil Service yang sama bila ada logika non-trivial, sehingga tidak ada duplikasi aturan bisnis antara API dan back-office.

---

## 2. Layer Arsitektur

Alur permintaan: **Routes → Controller → Service → Eloquent Model → Database**

> ⚠️ **Keputusan: Service layer saja, TANPA Repository pattern.**
> Di Laravel, Eloquent sudah berperan sebagai data-access layer yang matang (query builder, relations, scopes). Menambahkan Repository pattern di atasnya umumnya menjadi boilerplate tanpa nilai nyata untuk starter ini. Service memanggil Eloquent langsung.
>
> **Kapan mempertimbangkan Repository nanti?** Jika suatu modul perlu berganti-ganti sumber data (mis. dari DB ke API eksternal) atau butuh isolasi query yang sangat kompleks untuk testing. Saat itu tiba, perkenalkan Repository **per-modul**, bukan sebagai aturan global.

### Tanggung jawab tiap layer

| Layer | Tanggung jawab | Yang TIDAK boleh dilakukan |
|---|---|---|
| **Routes** | Mendefinisikan endpoint, middleware, binding | Tidak ada logika |
| **Controller** (API) | Validasi input (via Form Request), panggil Service, kembalikan API Resource | Tidak ada logika bisnis, tidak ada query langsung yang kompleks |
| **Form Request** | Aturan validasi & authorize() | Tidak ada side-effect |
| **Service** | Logika bisnis, orkestrasi, transaksi DB, memanggil Eloquent | Tidak tahu soal HTTP (request/response) |
| **Eloquent Model** | Skema, relasi, scope, casting, accessor/mutator | Tidak ada logika bisnis lintas-entitas |
| **API Resource** | Transformasi model → JSON konsisten | Tidak ada query (hindari N+1) |
| **Policy** | Aturan authorization per-aksi | — |

**Aturan praktis:**
- Service menerima dan mengembalikan data domain (model / DTO / array), **bukan** `Request`/`Response`.
- Operasi multi-langkah yang harus atomik dibungkus `DB::transaction()` di dalam Service.
- Controller idealnya < 20 baris per method.
- CRUD sederhana boleh langsung Controller → Eloquent **tanpa** Service. Service diperkenalkan ketika ada logika bisnis nyata. Jangan buat Service kosong yang hanya meneruskan panggilan.

---

## 3. Struktur Direktori yang Direkomendasikan

```
laravel-starter/
├── app/
│   ├── Console/
│   ├── Exceptions/
│   │   └── Handler.php              # mapping exception → API response konsisten
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/              # versioning API: controller per versi
│   │   │           ├── AuthController.php
│   │   │           └── UserController.php
│   │   ├── Middleware/
│   │   │   └── ForceJsonResponse.php
│   │   ├── Requests/
│   │   │   └── Api/V1/              # Form Requests untuk validasi
│   │   └── Resources/
│   │       └── Api/V1/              # API Resources (transformasi JSON)
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── Filament/
│   │       └── AdminPanelProvider.php
│   ├── Services/                   # ◄── logika bisnis domain
│   │   ├── Auth/
│   │   ├── User/
│   │   └── ...
│   ├── Support/                    # helper, value objects, enums lintas-domain
│   │   ├── ApiResponse.php         # builder envelope JSON standar
│   │   └── Enums/
│   └── Filament/                   # back-office (auto-generated oleh Filament)
│       ├── Resources/
│       ├── Pages/
│       └── Widgets/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── RolePermissionSeeder.php
│       └── AdminUserSeeder.php
├── routes/
│   ├── api.php                     # API untuk Flutter (prefix /api/v1)
│   ├── web.php                     # minimal — back-office ditangani Filament
│   └── console.php
├── tests/
│   ├── Feature/
│   │   ├── Api/
│   │   └── BackOffice/
│   └── Unit/
│       └── Services/
├── docs/                           # dokumentasi planning (folder ini)
├── .env.example
├── composer.json
└── README.md
```

**Catatan:**
- `app/Services/` dan `app/Support/` dibuat manual (tidak ada bawaan Laravel).
- `app/Filament/` di-generate oleh perintah `php artisan make:filament-*`.
- Versioning API: gunakan namespace `Api\V1` sejak awal agar penambahan `V2` di masa depan tidak merusak konsumen lama.

---

## 4. Pemisahan API Routes vs Web (Back-office) Routes

| Aspek | API (Flutter) | Back-office (Filament) |
|---|---|---|
| File route | `routes/api.php` | dikelola Filament Panel Provider |
| Prefix URL | `/api/v1/...` | `/admin` (default Filament panel) |
| Guard | `api` (Passport) | `web` (session) |
| Auth credential | Bearer access token | session cookie + CSRF |
| Response | JSON (envelope standar) | HTML (Livewire/Filament) |
| State | Stateless | Stateful (session) |
| Middleware utama | `auth:api`, throttle, `force-json` | `auth`, Filament middleware stack |

Kedua jalur **berbagi** Models, Services, Policies, dan sistem RBAC yang sama. Yang berbeda hanya lapisan presentasi & mekanisme auth.

---

## 5. Strategi Autentikasi

### 5.1 API — Laravel Passport (OAuth2) ⚠️

> ⚠️ **Keputusan tercatat: Passport.** Untuk konteks, berikut trade-off vs Sanctum agar bisa dievaluasi ulang bila kebutuhan berubah.

| | **Passport (dipilih)** | Sanctum (alternatif) |
|---|---|---|
| Model | Full OAuth2 server | Token API ringan / SPA |
| Cocok untuk | Third-party clients, OAuth2 grant flows, refresh token standar | First-party mobile/SPA sederhana |
| Kompleksitas | Lebih tinggi (keys, clients, grants) | Rendah |
| Refresh token | Bawaan, standar OAuth2 | Manual |

**Implementasi untuk Flutter (Password Grant / Personal Access — finalisasi di Sesi 2):**
- Gunakan **Password Grant** atau **Personal Access Token** untuk first-party mobile app.
- ⚠️ **Catatan:** Pada Passport versi terbaru, dukungan Password Grant mungkin perlu di-enable secara eksplisit / dianggap deprecated di beberapa rilis OAuth2. Verifikasi di Sesi 2 dan pilih grant flow yang sesuai (Password Grant vs Authorization Code with PKCE untuk mobile).
- Endpoint auth: `POST /api/v1/auth/login` → kembalikan `access_token` + `refresh_token` + `expires_in`.
- Endpoint: `POST /api/v1/auth/refresh`, `POST /api/v1/auth/logout` (revoke token), `GET /api/v1/auth/me`.
- Simpan token di Flutter via **flutter_secure_storage** (jangan SharedPreferences biasa).

### 5.2 Back-office — Session (Filament)

- Filament memakai guard `web` standar Laravel (session + cookie).
- Login UI bawaan Filament; tidak perlu membuat halaman login manual.
- Akses panel dibatasi oleh `User::canAccessPanel()` + RBAC (mis. hanya role `admin`/`staff`).

### 5.3 RBAC bersama — spatie/laravel-permission ⚠️

- Satu sistem permission dipakai oleh kedua guard.
- ⚠️ **Multi-guard:** spatie menyimpan `guard_name` pada role & permission. Tentukan di Sesi 2/3 apakah role didefinisikan untuk guard `web`, `api`, atau keduanya. Rekomendasi: definisikan permission netral dan assign role pada guard `web` (untuk Filament) serta pastikan pengecekan di API memakai guard yang konsisten. Uji `$user->can()` di kedua jalur.
- Permission diperiksa via Policy (API) dan via Filament (`canViewAny`, dll.) — keduanya membaca dari sumber yang sama.

---

## 6. Package Pihak Ketiga yang Direkomendasikan

| Package | Tujuan | Alasan |
|---|---|---|
| `laravel/passport` | OAuth2 API auth | Keputusan tercatat untuk auth API |
| `filament/filament` | Back-office UI | Admin panel cepat, CRUD/RBAC/tabel siap pakai |
| `spatie/laravel-permission` | RBAC | Standar de-facto, matang, terintegrasi baik dengan Filament |
| `spatie/laravel-query-builder` | Filtering/sorting API dari query string | Bikin endpoint list API konsisten & aman (whitelist filter) |
| `laravel/pint` | Code style (PSR-12) | Formatter resmi Laravel, zero-config |
| `larastan/larastan` | Static analysis (PHPStan) | Tangkap bug lebih awal |
| `barryvdh/laravel-ide-helper` (dev) | Autocomplete IDE | DX lebih baik untuk Eloquent magic |
| `nunomaduro/collision` (bawaan) | Error reporting CLI | — |

**Pertimbangkan nanti (jangan dipasang di awal):**
- `laravel/horizon` — hanya jika pakai Redis queue secara serius.
- `laravel/telescope` — debugging di dev/staging (jangan di prod tanpa proteksi).
- `spatie/laravel-medialibrary` — bila ada manajemen file/upload kompleks.

---

## 7. API Response Standard

Semua endpoint API mengembalikan envelope JSON yang konsisten. Detail final di Sesi 1/2 melalui helper `app/Support/ApiResponse.php`.

**Sukses:**
```json
{
  "success": true,
  "message": "OK",
  "data": { },
  "meta": { "pagination": { } }
}
```

**Error:**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] }
}
```

- Validasi gagal → HTTP `422` dengan `errors` per-field.
- Unauthenticated → `401`; Unauthorized (RBAC) → `403`; Not found → `404`.
- Mapping exception → response dilakukan terpusat di `app/Exceptions/Handler.php` sehingga controller tidak perlu try/catch berulang.
- Gunakan **API Resource** untuk membentuk `data`, dan paginator Laravel untuk `meta.pagination`.

---

## 8. Best Practice Integrasi Flutter ↔ Laravel API

- **Versioning sejak awal.** Prefix `/api/v1`. Jangan ubah kontrak v1 secara breaking; tambahkan v2 bila perlu.
- **Kontrak konsisten.** Envelope JSON seragam memudahkan Flutter membuat satu `ApiResponse` model & error handler global.
- **Auth token aman.** Simpan access/refresh token di `flutter_secure_storage`. Implementasikan interceptor (dio) untuk auto-attach Bearer token & auto-refresh saat `401`.
- **Pagination.** Pakai paginator standar Laravel; kirim `meta.pagination` (current_page, last_page, per_page, total). Untuk list panjang/infinite scroll, pertimbangkan cursor pagination.
- **Tanggal & waktu.** Selalu kirim ISO-8601 UTC (`2026-05-22T10:00:00Z`). Konversi timezone di sisi Flutter.
- **Penamaan field JSON.** Pilih satu konvensi (`snake_case` direkomendasikan, sesuai default Laravel) dan konsisten di seluruh API.
- **Validasi terstruktur.** Selalu `422` + `errors` per-field agar Flutter bisa menampilkan error di form yang tepat.
- **Rate limiting.** Terapkan `throttle` pada endpoint auth (mis. login) untuk mencegah brute-force.
- **CORS.** Konfigurasi `config/cors.php` untuk consumer yang relevan (umumnya tidak kritikal untuk mobile native, kritikal bila ada web client).
- **Idempotency & error code.** Pertimbangkan kode error internal (mis. `"code": "AUTH_INVALID_CREDENTIALS"`) di samping HTTP status agar Flutter bisa branching tanpa parsing pesan.
- **File upload.** `multipart/form-data` dengan validasi mime/size; kembalikan URL/identifier file di response.
- **Dokumentasi API.** Pertimbangkan OpenAPI/Scribe (nice-to-have) agar tim Flutter punya kontrak yang jelas.

---

## 9. Hal yang DIHINDARI di Starter Ini

- ❌ **Repository pattern global** — over-engineering di atas Eloquent (lihat §2).
- ❌ **DTO/Mapper di mana-mana** — pakai array/Eloquent secukupnya; perkenalkan DTO hanya saat batas modul benar-benar butuh.
- ❌ **Abstraksi spekulatif** — jangan buat interface + binding "untuk jaga-jaga" tanpa implementasi kedua yang nyata.
- ❌ **Service kosong** — jangan bungkus CRUD trivial dengan Service yang hanya meneruskan call.
- ❌ **Microservices / event sourcing / CQRS** — di luar lingkup starter monolit ini.
- ❌ **Logika bisnis di Controller atau di Blade/Filament view** — tempatnya di Service/Model.
- ❌ **Multiple auth library yang tumpang tindih** — satu untuk API (Passport), satu untuk back-office (session Filament). Jangan campur Sanctum + Passport.
- ❌ **Konfigurasi prod prematur** (Horizon, Telescope di prod, multi-tenancy) sebelum ada kebutuhan nyata.
