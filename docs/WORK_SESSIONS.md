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
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

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
5. **API docs** (opsional): Scribe/OpenAPI untuk tim Flutter.
6. **Finalisasi README** bagian "Cara Menjalankan" dengan langkah & kredensial seeder konkret.
7. **CI** (opsional): GitHub Actions untuk lint + test.

**Output / Deliverable:**
- [x] Dashboard & branding back-office tampil.
- [x] Dokumentasi "Cara Menjalankan" lengkap & teruji dari nol.
- [x] Suite test hijau; Pint & Larastan bersih.
- [ ] (Opsional) API docs & CI aktif.
- [ ] **Di-commit & di-push** ke `origin` sesuai [CONTRIBUTING.md](../CONTRIBUTING.md).

**File dibuat/diubah:** widget Filament, konfigurasi panel, fitur auth tambahan, README, (opsional) `.github/workflows/ci.yml`, file docs API.

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
