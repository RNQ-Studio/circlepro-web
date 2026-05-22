# WORK SESSIONS

Rencana pembagian sesi kerja untuk implementasi Laravel Starter via Claude Code. Tiap sesi dirancang muat dalam **~5 jam** (kuota Claude Pro) dan menghasilkan deliverable yang dapat diverifikasi.

**Cara pakai dokumen ini:**
- Kerjakan sesi **berurutan**; hormati bagian *Dependency*.
- Di awal tiap sesi, baca [README.md](../README.md), [ARCHITECTURE.md](ARCHITECTURE.md), dan [MODULES.md](MODULES.md) untuk konteks.
- Di akhir tiap sesi, **commit DAN push** sesuai [CONTRIBUTING.md](../CONTRIBUTING.md), lalu perbarui checklist *Deliverable*.
- Tanda ⚠️ = keputusan teknis yang perlu difinalisasi/diverifikasi saat sesi berjalan.

---

## Sesi 1 — Fondasi Proyek & Database Awal 🟥

**Tujuan:** Menyiapkan proyek Laravel yang bersih, struktur direktori, koneksi PostgreSQL, skema database awal, dan API Response standard. Akhir sesi: proyek bisa `migrate --seed` dan punya endpoint health-check.

**Dependency:** — (sesi pertama).

**Tugas:**
1. **Verifikasi versi** stable terbaru Laravel, Filament, Passport, spatie/laravel-permission ⚠️. Kunci versi di `composer.json`.
2. `composer create-project laravel/laravel .` (atau setup di repo kosong ini). Generate app key.
3. Konfigurasi `.env` + `.env.example` untuk **PostgreSQL** (host, port 5432, db, user, password). Set `DB_CONNECTION=pgsql`.
4. Buat struktur direktori sesuai [ARCHITECTURE.md §3](ARCHITECTURE.md): `app/Services/`, `app/Support/`, `app/Http/Controllers/Api/V1/`, `Requests/Api/V1/`, `Resources/Api/V1/`.
5. Pasang tooling dasar: **Laravel Pint** + konfigurasi, **Larastan** + konfigurasi baseline.
6. **API Response standard:**
   - Buat `app/Support/ApiResponse.php` (builder success/error + envelope sesuai [ARCHITECTURE.md §7](ARCHITECTURE.md)).
   - Buat middleware `ForceJsonResponse` dan daftarkan pada grup route `api`.
   - Konfigurasi exception handler agar 401/403/404/422/500 keluar sebagai JSON konsisten.
7. **Skema database awal (migrasi):**
   - `users` (sesuaikan: tambah kolom `status`/`is_active` bila perlu).
   - Publish & jalankan migrasi **spatie/laravel-permission** (roles, permissions, pivot).
   - Tabel contoh **data master** (mis. `categories`) sebagai template (id, name, slug, is_active, timestamps, softDeletes).
   - Tabel sistem Passport (akan ditangani di Sesi 2 saat `passport:install`, tapi siapkan dependency-nya).
8. **Seeder & factory dasar:** `DatabaseSeeder`, `AdminUserSeeder` (placeholder admin), factory untuk `User` dan `Category`.
9. Endpoint health-check: `GET /api/v1/health` → `{ success: true, data: { status: "ok" } }` (untuk verifikasi envelope & routing).

**Output / Deliverable:** ✅ **SELESAI** (2026-05-22)
- [x] `composer install && php artisan migrate --seed` berjalan tanpa error pada PostgreSQL.
- [x] `GET /api/v1/health` mengembalikan envelope JSON standar (404 route tak dikenal juga ber-envelope).
- [x] Struktur direktori sesuai ARCHITECTURE.md.
- [x] Pint & Larastan jalan tanpa error (Larastan level 5; `composer analyse` pakai `--memory-limit=1G`).
- [x] `.env.example` lengkap.
- [x] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** `composer.json`, `.env.example`, `config/database.php` & `config/permission.php`, `app/Support/ApiResponse.php`, `app/Http/Middleware/ForceJsonResponse.php`, `bootstrap/app.php` (exception rendering + routing + middleware), `routes/api.php`, `app/Http/Controllers/Api/V1/HealthController.php`, migrasi awal (users +`is_active`, permission tables, categories), `app/Models/{User,Category}.php`, `database/factories/CategoryFactory.php`, seeder (`AdminUserSeeder`, `CategorySeeder`, `DatabaseSeeder`), `pint.json`, `phpstan.neon`, `phpunit.xml` (test DB pgsql), tests (`HealthTest`, `DatabaseSmokeTest`).

> **Catatan implementasi:** versi terpasang **Laravel 13.11** (Laravel 13 sudah stable). Test memakai database PostgreSQL terpisah `laravel_starter_test` (bukan sqlite `:memory:`) karena PHP lokal tanpa driver SQLite. PHP yang dipakai `C:\php8.3.6` (default PATH masih 7.4).

---

## Sesi 2 — Sistem Autentikasi (Passport API + Session Back-office) 🟥

**Tujuan:** Auth API berbasis Passport untuk Flutter + setup panel Filament dengan login session + fondasi RBAC.

**Dependency:** Sesi 1 selesai.

**Tugas:**
1. **Passport setup:**
   - Install Passport, jalankan `passport:install` (generate keys & client).
   - Konfigurasi guard `api` → driver `passport` di `config/auth.php`.
   - ⚠️ **Pilih grant flow** untuk Flutter (Password Grant vs Authorization Code + PKCE). Verifikasi status Password Grant di versi Passport terpasang; dokumentasikan keputusan.
2. **Endpoint Auth API** (`app/Http/Controllers/Api/V1/AuthController.php` + Form Requests + `AuthService`):
   - `POST /api/v1/auth/login` → access_token + refresh_token + expires_in.
   - `POST /api/v1/auth/refresh`.
   - `POST /api/v1/auth/logout` (revoke token).
   - `GET /api/v1/auth/me` (profil user terautentikasi).
   - Terapkan `throttle` pada login.
3. **Filament panel:**
   - Install Filament, generate panel `/admin` (`AdminPanelProvider`).
   - Login session bawaan Filament aktif.
   - Implement `User::canAccessPanel()` (batasi ke role yang berhak).
4. **RBAC fondasi (spatie):**
   - Tambah trait `HasRoles` ke model `User`.
   - `RolePermissionSeeder`: definisikan role default (`super-admin`, `admin`, `staff`) + permission awal.
   - ⚠️ **Finalisasi strategi multi-guard** (web vs api) — lihat [ARCHITECTURE.md §5.3](ARCHITECTURE.md). Uji `$user->can()` di kedua jalur.
   - `Gate::before` untuk `super-admin` bypass.
   - Update `AdminUserSeeder` agar admin dapat role `super-admin`.
5. **Tests:** feature test untuk login/refresh/logout/me + test akses panel (boleh vs tidak boleh).

**Output / Deliverable:** ✅ **SELESAI** (2026-05-22)
- [x] Flutter dapat login → menerima token → akses endpoint terproteksi → refresh → logout (diverifikasi via HTTP & feature test).
- [x] Login back-office di `/admin` berfungsi (`/admin`→302→`/admin/login` 200); hanya role berhak (`PANEL_ROLES`) + user aktif yang bisa masuk.
- [x] Role & permission default ter-seed; `super-admin` bypass via `Gate::before` berfungsi.
- [x] Keputusan grant flow (Password Grant, proxy) & multi-guard (`web`) terdokumentasi di [ARCHITECTURE.md §5](ARCHITECTURE.md).
- [x] Tests auth hijau (16 tests total: AuthTest + PanelAccessTest).
- [x] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** `config/auth.php` (guard `api`), `config/passport.php` (+`password_client`), `app/Providers/AppServiceProvider.php` (enablePasswordGrant + token lifetimes + Gate::before), `app/Http/Controllers/Api/V1/AuthController.php`, `LoginRequest`/`RefreshTokenRequest`, `app/Services/Auth/AuthService.php`, `app/Http/Resources/Api/V1/UserResource.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Models/User.php` (HasApiTokens + OAuthenticatable + FilamentUser), `RolePermissionSeeder`, `AdminUserSeeder`, `routes/api.php`, `.env(.example)`, tests.

> **Catatan implementasi:** Passport **13**, Filament **5**. Grant flow = **Password Grant** (opt-in via `enablePasswordGrant()`). Logout me-revoke access + refresh token (`AccessToken::revoke()` + revoke `RefreshToken` by `access_token_id`). Catatan test: revoke diverifikasi lewat state DB karena auth guard cache antar-request dalam satu proses test; perilaku runtime benar (diverifikasi via HTTP).

---

## Sesi 3 — User & Role Management (API + Back-office) 🟥

**Tujuan:** Manajemen user dan role/permission lengkap di back-office, plus endpoint profil & (opsional) user API.

**Dependency:** Sesi 2 selesai.

**Tugas:**
1. **Back-office — User Management (Filament Resource):**
   - CRUD `User` (list, create, edit, delete) dengan search & filter.
   - Field assign role (relasi spatie) di form.
   - Resource policy via RBAC (`viewAny`, `create`, dll.) baca dari permission.
   - Opsi status aktif/nonaktif (nice-to-have).
2. **Back-office — Role Management (Filament Resource):**
   - CRUD `Role` + assign permission ke role (multi-select).
   - (Nice-to-have) CRUD Permission — atau kelola via seeder.
3. **API — Profil & User:**
   - `PUT /api/v1/auth/me` (update profil sendiri) + `POST /api/v1/auth/change-password`.
   - (Opsional, di balik permission) endpoint user admin: list/show user via API.
   - Buat `UserResource` (API Resource) untuk transformasi konsisten.
4. **Authorization enforcement:** Policy untuk `User`/`Role` dipakai baik di API maupun Filament.
5. **Tests:** feature test CRUD user/role (back-office) + profil API + pengecekan permission (403 untuk yang tak berhak).

**Output / Deliverable:** ✅ **SELESAI** (2026-05-22)
- [x] Admin mengelola user & role sepenuhnya dari `/admin`.
- [x] Assign role/permission berfungsi & langsung berdampak ke authorization.
- [x] Endpoint profil API berfungsi; user lain tak bisa mengubah profil orang lain.
- [x] Tests hijau (26 tests total).
- [x] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** `app/Filament/Resources/Users/*`, `app/Filament/Resources/Roles/*`, `app/Policies/UserPolicy.php`, `RolePolicy.php`, `app/Http/Resources/Api/V1/UserResource.php`, update `AuthController`, `UpdateProfileRequest`, `ChangePasswordRequest`, `routes/api.php`, tests (`ProfileTest`, `UserRoleManagementTest`).

> **Catatan implementasi:** User/Role dikelola lewat Filament resource yang memakai policy RBAC. Endpoint profil ditambah di bawah `auth:api`: `PUT /api/v1/auth/me` dan `POST /api/v1/auth/change-password`. Password user tetap di-hash oleh cast model `User`.

---

## Sesi 4 — Data Master: CRUD Generik (API + Back-office) 🟥

**Tujuan:** Membangun pola CRUD data master yang lengkap dan dapat direplikasi, menggunakan entitas contoh (mis. `Category`), di API maupun back-office.

**Dependency:** Sesi 3 selesai (pola auth & RBAC sudah stabil).

**Tugas:**
1. **API CRUD `Category`** (`app/Http/Controllers/Api/V1/CategoryController.php`):
   - `GET /api/v1/categories` (list + pagination + filter/sort via **spatie/laravel-query-builder**, whitelist field).
   - `GET /api/v1/categories/{id}`, `POST`, `PUT`, `DELETE`.
   - Form Requests (`StoreCategoryRequest`, `UpdateCategoryRequest`).
   - `CategoryResource` (API Resource).
   - Service hanya jika ada logika nyata; CRUD trivial boleh langsung Eloquent (sesuai [ARCHITECTURE.md §2](ARCHITECTURE.md)).
   - Authorization via `CategoryPolicy`.
2. **Back-office CRUD `Category`** (Filament Resource): mirror dari API, dengan search/filter/sort & soft delete (jika diaktifkan).
3. **Dokumentasi pola "Menambah Data Master Baru":**
   - Tulis panduan langkah-demi-langkah (migrasi → model → policy → API controller/requests/resource → Filament resource → tests) di `docs/` atau README.
   - Tujuan: dev/sesi berikutnya bisa menyalin pola dengan cepat.
   - Implementasi: [DATA_MASTER_PATTERN.md](DATA_MASTER_PATTERN.md).
4. **Tests:** feature test API CRUD (termasuk filter/sort & authorization) + smoke test Filament resource.

**Output / Deliverable:**
- [x] CRUD `Category` berfungsi penuh di API & back-office, dengan filter/sort/pagination konsisten.
- [x] Authorization (RBAC) ditegakkan di kedua jalur.
- [x] Dokumentasi pola data master tersedia & jelas.
- [x] Tests hijau.
- [x] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** migrasi/model `Category` (jika belum lengkap dari Sesi 1), `CategoryController`, Form Requests, `CategoryResource`, `CategoryPolicy`, `app/Filament/Resources/CategoryResource.php`, dokumentasi pola, tests.

---

## Sesi 5 — Polish, Tooling & Nice-to-have 🟨

**Tujuan:** Mematangkan starter: dashboard, kualitas kode, dokumentasi, dan fitur nice-to-have terpilih.

**Dependency:** Sesi 1–4 selesai.

**Tugas (pilih sesuai prioritas; tidak semua wajib):**
1. **Dashboard Filament** sederhana (widget: jumlah user, jumlah per role, dll.).
2. **Branding panel** (nama app, logo, warna).
3. **Auth nice-to-have:** password reset (API + back-office), email verification.
4. **Kualitas:** lengkapi feature/unit tests hingga cakupan modul inti memadai; pastikan Pint & Larastan bersih.
5. **API docs dengan Scramble** (OpenAPI otomatis untuk tim Flutter):
   - Install `dedoc/scramble` sebagai dependency dokumentasi API.
   - Publish/atur konfigurasi Scramble agar hanya mendokumentasikan route `api/v1`.
   - Set metadata OpenAPI: title `Laravel Starter API`, version awal, server URL lokal, dan deskripsi singkat.
   - Konfigurasi security scheme **Bearer token** untuk endpoint Passport (`Authorization: Bearer <token>`).
   - Pastikan endpoint dokumentasi tersedia, misalnya `GET /docs/api` (UI) dan `GET /docs/api.json` (OpenAPI JSON).
   - Pastikan schema request terbaca dari Form Request (`LoginRequest`, `StoreCategoryRequest`, dll.).
   - Pastikan response utama terbaca dari API Resource/envelope standar; tambah PHPDoc/attribute hanya bila Scramble tidak bisa infer otomatis.
   - Tambahkan smoke test untuk `/docs/api` dan `/docs/api.json` pada environment yang sesuai.
   - Update README dengan cara membuka dokumentasi API dan cara share OpenAPI JSON ke tim Flutter/Postman.
6. **Finalisasi README** bagian "Cara Menjalankan" dengan langkah & kredensial seeder konkret.
7. **CI** (opsional): GitHub Actions untuk lint + test.

**Output / Deliverable:**
- [x] Dashboard & branding back-office tampil.
- [x] Dokumentasi "Cara Menjalankan" lengkap & teruji dari nol.
- [x] Suite test hijau; Pint & Larastan bersih.
- [x] API docs Scramble aktif dan OpenAPI JSON bisa diakses.
- [ ] (Opsional) CI aktif.
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** widget Filament, konfigurasi panel, fitur auth tambahan, README, konfigurasi Scramble, tests dokumentasi API, (opsional) `.github/workflows/ci.yml`.

---

## Sesi 6 — Seeder Wilayah: Negara & Wilayah Indonesia 🟨

**Tujuan:** Menyediakan data master wilayah — semua negara + provinsi/state + kota untuk seluruh dunia, dan data wilayah Indonesia lengkap hingga kelurahan/desa — dalam **satu tabel `regions` self-referencing**, untuk kebutuhan form alamat di app Flutter.

**Dependency:** Sesi 1 selesai (PostgreSQL running); mengikuti pola data master dari Sesi 4.

**Sumber Data:**
- **Seluruh dunia** (sampai kota): [`dr5hn/countries-states-cities-database`](https://github.com/dr5hn/countries-states-cities-database) — Country → State → City
- **Indonesia** (sampai kelurahan): [`emsifa/api-wilayah-indonesia`](https://github.com/emsifa/api-wilayah-indonesia) — Provinsi → Kabupaten/Kota → Kecamatan → Kelurahan/Desa

**Skema Database — tabel tunggal `regions` (self-referencing):**

```
regions
  id            bigint PK
  parent_id     bigint FK → regions.id (nullable — null berarti negara/root)
  type          enum: country | state | city | district | village
  code          varchar nullable  -- ISO2 untuk negara; kode BPS untuk wilayah Indonesia
  name          varchar
  phone_code    varchar nullable  -- diisi untuk type=country; null untuk level lain
  meta          jsonb nullable    -- data ekstra per-type (iso3, currency, emoji, dll.)
  created_at / updated_at
```

Index: `parent_id`, `type`, `code`, `phone_code`, composite `(type, code)`.

**Mapping type per sumber:**

| type | Asal data | Estimasi record |
|------|-----------|-----------------|
| `country` | dr5hn | ±250 |
| `state` | dr5hn (non-ID) + emsifa (ID) | ±5 000 |
| `city` | dr5hn (non-ID) + emsifa kabupaten/kota (ID) | ±150 000 |
| `district` | emsifa kecamatan — Indonesia saja | ±7 200 |
| `village` | emsifa kelurahan/desa — Indonesia saja | ±83 000 |

**Kolom `meta` (jsonb) — contoh isi per type:**
- `country`: `{ iso3, capital, currency, currency_symbol, region, subregion, emoji, latitude, longitude }`
- `state`: `{ latitude, longitude }` (opsional)
- `city` non-ID: `{ latitude, longitude }` (opsional)
- `city` Indonesia: `{ type: "kabupaten"|"kota" }` ⚠️ bedakan dari enum `type` tabel

**Tugas:**
1. **Migrasi** tabel `regions` dengan kolom di atas; tambahkan index yang diperlukan.
2. **Model `Region`** dengan relasi self-referencing:
   - `parent(): BelongsTo` → `Region`
   - `children(): HasMany` → `Region`
   - Scope helper: `scopeCountries()`, `scopeStates()`, `scopeCities()`, dll.
3. **Command `php artisan regions:download`:**
   - Download JSON dari kedua sumber dan simpan ke `storage/app/regions/` (cache lokal — gitignore folder ini).
   - File dr5hn: `countries.json`, `states.json` (raw GitHub `json/`) + `cities.json` dari release gzip terbaru.
   - File emsifa: `provinces.json` + loop per-provinsi untuk `regencies/{id}.json`, `districts/{id}.json`, `villages/{id}.json` via `https://emsifa.github.io/api-wilayah-indonesia/api/`.
   - Tampilkan progress; lewati file yang sudah ada (idempoten).
4. **Seeder** — semua insert ke tabel `regions`, dijalankan lewat `RegionSeeder` sebagai orchestrator:
   - `CountrySeeder` — insert negara dari dr5hn (`type=country`, `meta` berisi iso3/phone_code/dll.).
   - `StateSeeder` — insert state dr5hn (non-ID) + provinsi emsifa untuk Indonesia (`type=state`, `parent_id` → id negara bersangkutan).
   - `CitySeeder` — insert cities dr5hn (non-ID) + kabupaten/kota emsifa (ID) (`type=city`, `parent_id` → id state).
   - `DistrictSeeder` — insert kecamatan emsifa (`type=district`, `parent_id` → id city Indonesia, ±7 200 record).
   - `VillageSeeder` — insert kelurahan/desa emsifa (`type=village`, `parent_id` → id district, ±83 000 record) dengan **chunked bulk insert** (chunk 500–1 000) ⚠️.
   - Setiap seeder menyimpan mapping `kode_sumber → id` di memori untuk resolve `parent_id` tanpa query per-record.
5. **Daftarkan** di `DatabaseSeeder` dengan guard `SEED_REGIONS=true` di `.env` supaya `migrate --seed` biasa tidak memuat ±245 000 record secara default.
6. **Command `php artisan regions:seed`** — shortcut dengan progress bar per-level; lebih ergonomis dari `db:seed --class=RegionSeeder`.
7. **(Opsional) Endpoint API read-only** untuk lookup cascading di form Flutter:
   - `GET /api/v1/regions?type=country`
   - `GET /api/v1/regions?parent_id={id}` (universal — satu endpoint untuk semua level)
8. **Tests:**
   - Smoke test seeder: assert count per type sesuai estimasi. ⚠️ Skip otomatis jika file JSON belum di-download.
   - Test relasi: `Region::countries()->first()->children` mengembalikan states; chain sampai ke village untuk data Indonesia.

**Output / Deliverable:** ✅ **SELESAI** (2026-05-23)
- [x] Migrasi tabel `regions` berjalan tanpa error.
- [x] `php artisan regions:download` berhasil mengambil semua file JSON.
- [x] `php artisan regions:seed` berhasil; count per type sesuai estimasi.
- [x] Relasi self-referencing dapat di-traverse via Eloquent hingga level village (Indonesia).
- [x] `DatabaseSeeder` normal tidak memuat region kecuali `SEED_REGIONS=true`.
- [x] Tests hijau (minimal smoke test count + relasi).
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** migrasi (`regions`), model `Region`, `app/Console/Commands/RegionsDownloadCommand.php`, `app/Console/Commands/RegionsSeedCommand.php`, seeder (CountrySeeder, StateSeeder, CitySeeder, DistrictSeeder, VillageSeeder, RegionSeeder), update `DatabaseSeeder` + `.env.example` (`SEED_REGIONS`), `storage/app/regions/.gitkeep` + `.gitignore` entry, (opsional) RegionController + routes, tests.

---

## Sesi 7 — Device Tracking (Fondasi Push Notification) 🟨

**Tujuan:** Menyimpan spesifikasi device mobile yang mengakses API — platform, OS, versi app, dan push token — sebagai fondasi untuk push notification, analytics, dan keamanan (deteksi device tak dikenal).

**Dependency:** Sesi 2 selesai (Passport auth berjalan).

**Tugas:**
1. **Migrasi tabel `user_devices`:**
   - `id` (ULID/UUID PK), `user_id` (FK → users), `device_id` (varchar — UUID dari sisi mobile, unique per user+device), `platform` (enum: `android` | `ios` | `web`), `os_version`, `app_version`, `device_name`, `push_token` (nullable — FCM/APNs token), `last_active_at` (timestamp nullable), timestamps.
   - Index: `user_id`, `device_id`, `push_token` (partial, not null).
2. **Model `UserDevice`** dengan relasi ke `User`:
   - `user(): BelongsTo`.
   - Scope: `scopeWithPushToken()` untuk query device yang bisa menerima notif.
3. **Integrasi ke login flow:**
   - Tambahkan field opsional ke `LoginRequest`: `device_id`, `platform`, `os_version`, `app_version`, `device_name`, `push_token`.
   - `AuthService::login()` melakukan `upsert` ke `user_devices` berdasarkan `user_id + device_id` setelah token dibuat.
4. **Integrasi ke logout:**
   - Saat logout, set `push_token = null` pada device yang bersangkutan agar tidak menerima notif saat tidak login.
5. **Middleware `UpdateDeviceActivity`** (opsional, ringan):
   - Update `last_active_at` pada device aktif setiap N menit (pakai cache untuk throttle update-nya, hindari write per-request).
6. **Back-office — Device list** di Filament (read-only, di bawah user detail):
   - Tampilkan device list per user: platform, versi app, last active, push token status.
7. **Tests:** feature test login dengan dan tanpa device info; upsert saat login ulang dari device sama; push_token di-nullify saat logout.

**Output / Deliverable:**
- [ ] Tabel `user_devices` ter-migrasi.
- [ ] Login dari mobile dapat menyertakan device info dan tersimpan.
- [ ] Logout menghapus push token di device terkait.
- [ ] `UserDevice::scopeWithPushToken()` bisa langsung dipakai sesi push notification.
- [ ] Tests hijau.
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** migrasi `user_devices`, `app/Models/UserDevice.php`, update `User.php` (relasi `devices()`), update `LoginRequest`, update `AuthService`, (opsional) `UpdateDeviceActivity` middleware, Filament user detail panel, tests.

---

## Sesi 8 — Force Update & App Config 🟨

**Tujuan:** Dua mekanisme penting untuk kontrol app mobile dari server: paksa update versi lama, dan distribusi konfigurasi dinamis (maintenance mode, feature flags, link T&C, dll.) tanpa release app baru.

**Dependency:** Sesi 2 selesai (auth berjalan); Sesi 7 dianjurkan (tersedia `app_version` di device).

**Tugas:**
1. **Force Update — tabel `app_versions`:**
   - `id`, `platform` (enum: `android` | `ios`), `min_version` (varchar — versi minimum yang masih diizinkan, e.g. `2.1.0`), `latest_version` (varchar), `force_update` (boolean), `store_url` (varchar), `release_notes` (text nullable), timestamps.
   - Seed data awal untuk android & ios.
2. **Model `AppVersion`** + Filament resource (CRUD oleh admin).
3. **Endpoint Force Update:**
   - `GET /api/v1/app/version?platform=android` → `{ min_version, latest_version, force_update, store_url, release_notes }`.
   - Endpoint `@unauthenticated` (mobile cek ini sebelum login).
   - Rate limit longgar (tidak butuh per-user throttle ketat).
4. **App Config — tabel `app_configs`:**
   - `id`, `key` (varchar unique), `value` (text), `type` (enum: `string` | `boolean` | `integer` | `json`), `description` (text nullable), timestamps.
   - Seed default keys: `maintenance_mode` (boolean), `maintenance_message` (string), `tos_url`, `privacy_url`, `support_email`.
5. **Model `AppConfig`** dengan helper cast otomatis berdasarkan `type` + cache (cache busted saat update):
   - `AppConfig::get(key, default)` — static helper.
6. **Endpoint App Config:**
   - `GET /api/v1/app/config` → semua key publik sebagai flat object `{ key: value, ... }`.
   - `@unauthenticated`.
7. **Middleware `CheckMaintenance`:** jika `maintenance_mode = true`, semua endpoint API (kecuali config & version) kembalikan 503 dengan pesan dari `maintenance_message`.
8. **Filament resource** untuk CRUD `AppVersion` dan `AppConfig` (admin dapat update tanpa deploy).
9. **Tests:** force update response per platform; app config cast tipe; 503 saat maintenance mode aktif.

**Output / Deliverable:**
- [ ] Mobile dapat cek versi minimum sebelum login.
- [ ] Admin dapat set force update dari back-office tanpa deploy.
- [ ] Config dinamis dapat di-fetch mobile saat startup.
- [ ] Maintenance mode bekerja via middleware.
- [ ] Tests hijau.
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** migrasi `app_versions` & `app_configs`, `AppVersion.php`, `AppConfig.php`, Filament resources, `app/Http/Controllers/Api/V1/AppController.php`, `app/Http/Middleware/CheckMaintenance.php`, update `bootstrap/app.php`, routes, tests.

---

## Sesi 9 — File Upload & Media Management 🟨

**Tujuan:** Pola upload file yang aman dan konsisten — avatar user, serta template yang dapat direplikasi untuk entitas lain — dengan dukungan storage lokal (dev) dan S3-compatible (prod).

**Dependency:** Sesi 2 selesai (auth); ikuti pola data master dari Sesi 4.

**Tugas:**
1. **Konfigurasi Storage:**
   - Setup disk `public` (lokal dev) dan disk `s3` (prod) di `config/filesystems.php`.
   - Tambahkan `.env.example` keys: `FILESYSTEM_DISK`, `AWS_*` / `DO_SPACES_*`.
   - Jalankan `php artisan storage:link` untuk disk lokal.
2. **Avatar upload untuk User:**
   - Tambahkan kolom `avatar` (varchar nullable) ke tabel `users` via migrasi baru.
   - Endpoint `POST /api/v1/auth/avatar` (multipart/form-data, field `avatar`).
   - Validasi: max 2MB, mime: jpeg/png/webp.
   - Simpan ke `avatars/{userId}/` dengan nama unik; hapus file lama saat update.
   - Kembalikan URL publik di `UserResource`.
3. **Helper `FileUploadService`** (reusable):
   - `upload(UploadedFile, folder, disk): string` → simpan dan kembalikan path.
   - `delete(path, disk): void`.
   - Gunakan disk dari config secara default; mudah di-mock di test.
4. **Filament — avatar di User resource:** field upload/preview di form edit user.
5. **Pola untuk entitas lain:** dokumentasikan di [DATA_MASTER_PATTERN.md](DATA_MASTER_PATTERN.md) cara menambah kolom file ke model lain menggunakan `FileUploadService`.
6. **Tests:** upload avatar (valid & invalid mime/size); avatar lama terhapus saat update; URL muncul di response `me`.

**Output / Deliverable:**
- [ ] Endpoint avatar upload berfungsi, disimpan ke storage, URL dikembalikan.
- [ ] Pindah disk cukup ubah env `FILESYSTEM_DISK`.
- [ ] `FileUploadService` siap dipakai modul lain.
- [ ] Tests hijau.
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** migrasi add `avatar` ke `users`, `app/Services/FileUploadService.php`, update `AuthController` (endpoint avatar), `AvatarRequest`, update `UserResource`, update Filament User resource, update `.env.example`, update `DATA_MASTER_PATTERN.md`, tests.

---

## Sesi 10 — Push Notification (FCM) & Notification History 🟨

**Tujuan:** Infrastruktur pengiriman push notification ke device mobile via FCM, plus riwayat notifikasi yang bisa ditampilkan di app (notification center/inbox).

**Dependency:** Sesi 7 selesai (`user_devices` + `push_token` tersedia).

**Tugas:**
1. **FCM Setup:**
   - Install package FCM (mis. `kreait/laravel-firebase` atau `laravel-notification-channels/fcm`). ⚠️ Pilih berdasarkan kompatibilitas Laravel 13.
   - Konfigurasi credential: service account JSON path / project ID di `.env.example`.
2. **Tabel `notifications` (riwayat):**
   - `id` (ULID), `user_id` (FK), `title`, `body`, `data` (jsonb nullable — payload untuk deep link), `type` (varchar — kategori notif, e.g. `promo`, `system`, `order`), `read_at` (timestamp nullable), `sent_at` (timestamp nullable), `failed_at` (timestamp nullable).
   - Index: `user_id`, `read_at`, `type`.
3. **Model `Notification`** (custom, bukan Laravel built-in Notification tabel):
   - Scope: `scopeUnread()`, `scopeByType()`.
4. **`PushNotificationService`:**
   - `send(User|Collection $users, string $title, string $body, array $data = []): void`
   - Ambil push token aktif dari `user_devices` (via `scopeWithPushToken()`).
   - Kirim via FCM; log hasil; update `sent_at` atau `failed_at` di record `notifications`.
   - Jika token invalid (FCM error `UNREGISTERED`), nullify token di `user_devices`.
5. **Endpoint Notification API:**
   - `GET /api/v1/notifications` (list + pagination, unread first).
   - `POST /api/v1/notifications/{id}/read` (tandai baca).
   - `POST /api/v1/notifications/read-all`.
   - `GET /api/v1/notifications/unread-count` (badge counter).
6. **Filament — kirim notifikasi manual** (form sederhana: pilih user/semua, isi title/body, kirim via `PushNotificationService`).
7. **Tests:** kirim notif ke user; token invalid di-nullify; endpoint mark-read; unread count akurat. Mock FCM di tests.

**Output / Deliverable:**
- [ ] Push notification dapat dikirim ke satu atau banyak user.
- [ ] Token invalid otomatis dibersihkan.
- [ ] App dapat fetch riwayat notifikasi dan menampilkan badge count.
- [ ] Admin dapat kirim notifikasi manual dari back-office.
- [ ] Tests hijau (dengan FCM di-mock).
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** migrasi `notifications`, `app/Models/Notification.php`, `app/Services/PushNotificationService.php`, `app/Http/Controllers/Api/V1/NotificationController.php`, routes, Filament page "Send Notification", update `.env.example`, tests.

---

## Sesi 11 — OTP & Verifikasi Nomor HP 🟨

**Tujuan:** Autentikasi / verifikasi berbasis nomor telepon — umum di app mobile Indonesia — dengan flow OTP (6 digit, TTL pendek, rate-limited).

**Dependency:** Sesi 2 selesai (auth); Sesi 10 dianjurkan (bisa kirim OTP via notif, bukan hanya SMS).

**Tugas:**
1. **Tambah kolom `phone`** ke tabel `users` (varchar nullable, unique) via migrasi baru.
2. **Tabel `otp_codes`:**
   - `id`, `phone` (varchar), `code` (varchar 6 digit, hashed), `purpose` (enum: `login` | `register` | `verify_phone` | `reset_password`), `expires_at` (timestamp), `used_at` (timestamp nullable), `ip_address` (varchar nullable), timestamps.
   - Index: `phone`, `expires_at`.
3. **`OtpService`:**
   - `generate(phone, purpose): OtpCode` — generate kode 6 digit, hash & simpan, TTL 5 menit, bersihkan kode lama.
   - `verify(phone, code, purpose): bool` — bandingkan hash, cek TTL, tandai `used_at`. Tolak kode yang sudah dipakai.
   - Rate limit built-in: max 3 request OTP per nomor per 10 menit (via cache counter).
4. **SMS Provider:** gunakan facade/interface agar provider bisa diganti. ⚠️ Pilih antara Twilio, Vonage, atau provider lokal (Zenziva, Nusasms). Sesi ini buat abstraksi + implementasi dummy (log ke `laravel.log`) yang bisa diganti provider nyata.
5. **Endpoint OTP API:**
   - `POST /api/v1/auth/otp/send` — kirim OTP ke `phone` (`@unauthenticated`; throttle ketat).
   - `POST /api/v1/auth/otp/verify` — verifikasi kode; jika purpose `login`, kembalikan token Passport; jika `verify_phone`, update `phone_verified_at` user.
   - `POST /api/v1/auth/phone` (authenticated) — update nomor HP sendiri + trigger OTP verifikasi.
6. **Kolom tambahan `phone_verified_at`** (timestamp nullable) di `users`.
7. **Tests:** generate OTP; expired OTP ditolak; kode yang sudah dipakai ditolak; rate limit bekerja; login via OTP mengembalikan token.

**Output / Deliverable:**
- [ ] User dapat login atau verifikasi HP via OTP.
- [ ] Rate limit mencegah brute force.
- [ ] Provider SMS dapat diganti tanpa ubah logika OTP.
- [ ] Tests hijau.
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** migrasi add `phone` + `phone_verified_at` ke `users`, migrasi `otp_codes`, `app/Models/OtpCode.php`, `app/Services/OtpService.php`, `app/Services/Sms/SmsInterface.php`, `app/Services/Sms/LogSmsProvider.php`, `app/Http/Controllers/Api/V1/OtpController.php`, routes, update `.env.example`, tests.

---

## Catatan Lintas-Sesi

- **Patuhi [CONTRIBUTING.md](../CONTRIBUTING.md)** untuk konvensi branch, commit, dan push.
- **Akhir setiap sesi wajib commit DAN push** ke `origin`. Idealnya push juga beberapa kali di tengah sesi, bukan menumpuk di akhir.
- **Quality gate sebelum commit:** `vendor/bin/pint`, `vendor/bin/phpstan analyse`, `php artisan test` harus bersih (lihat CONTRIBUTING.md §3).
- **Commit kecil & deskriptif** (Conventional Commits) per tugas; jangan satu commit besar di akhir sesi.
- **Migrasi forward-only** setelah di-share: jangan edit migrasi lama yang sudah dijalankan orang lain — buat migrasi baru.
- **Selalu update `.env.example`** saat menambah konfigurasi baru.
- **Keputusan ⚠️** yang difinalisasi dalam sesi harus dicatat balik ke [ARCHITECTURE.md](ARCHITECTURE.md)/[README.md](../README.md) agar dokumen tetap jadi sumber kebenaran.
- **Jangan over-engineer** — patuhi [ARCHITECTURE.md §9](ARCHITECTURE.md).
- Jika satu sesi melebihi ~5 jam, **pecah** dan catat sisa tugas sebagai sub-sesi (mis. "Sesi 3b").
