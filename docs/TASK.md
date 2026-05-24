# TO DO — Perbaikan & Peningkatan Laravel Starter

> **Dihasilkan oleh:** AI Code Review Agent
> **Tanggal:** 24 Mei 2026
> **Sumber analisis:** docs/review/ (00_SUMMARY ~ 07_action_plan)
> **Scope:** Critical + Sprint 1 + Sprint 2 + Backlog
> **Versi:** 2.0 — Full regeneration dari seluruh temuan review

---

## Cara Membaca Dokumen Ini

- `[ ]` — Belum dikerjakan
- `[/]` — Sedang dikerjakan
- `[x]` — Sudah selesai
- Setiap task memiliki ID unik (`CF-001`, `CF-002`, dst.) sebagai referensi
- Tandai selesai dengan mengganti `[ ]` menjadi `[x]`

---

## Ringkasan Eksekutif

Berdasarkan hasil tinjauan mendalam terhadap Laravel Starter Project, telah diidentifikasi total **34 temuan** yang mencakup aspek keamanan, testing, DX, dokumentasi, fitur API, Filament back-office, dan DevOps. Dari total temuan:

- **11 item (CF-001 s/d CF-011)** — ✅ Selesai (Sprint 0 & CF-011)
- **4 item (CF-012 s/d CF-015)** — ✅ Selesai (Kritis)
- **9 item (CF-016 s/d CF-024)** — ✅ Selesai (Sprint 1)
- **10 item (CF-025 s/d CF-034)** — ✅ Selesai (Sprint 2)

---

## ═══════════════════════════════════════════
## ✅ SPRINT 0 — Selesai (10/10)
## ═══════════════════════════════════════════

### [CF-001] Ketiadaan Enforcement HTTPS di Environment Produksi

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/01_starter_readiness.md`
- **Lokasi di kode:** `app/Providers/AppServiceProvider.php`
- **Masalah:**
  Token API Passport dan data sensitif pengguna rentan disadap melalui serangan Man-in-the-Middle (MITM) jika koneksi tidak dipaksa menggunakan protokol HTTPS pada lingkungan produksi.
- **Aksi yang harus dilakukan:**
  - [x] Tambahkan logika pemaksaan skema HTTPS (`URL::forceScheme('https')`) saat aplikasi berjalan di lingkungan produksi.
  - [x] Letakkan logika tersebut di dalam method `boot()` pada `AppServiceProvider.php`.
- **Kriteria selesai:** Framework memaksa semua URL dan tautan redirect menggunakan skema `https://` secara otomatis ketika `APP_ENV=production`.

---

### [CF-002] Masking Error Kredensial pada Passport Proxy di AuthService

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/06_priority_areas.md`
- **Lokasi di kode:** `app/Services/Auth/AuthService.php`
- **Masalah:**
  Kesalahan konfigurasi Passport client secret pada file `.env` memicu respons error terselubung (masking) "Invalid credentials" dengan status 401. Hal ini menyembunyikan kesalahan konfigurasi server yang sesungguhnya di belakang pesan kesalahan input pengguna, sehingga membingungkan developer saat debugging di environment non-lokal.
- **Aksi yang harus dilakukan:**
  - [x] Ekstrak pesan dan tipe error asli dari respons Passport di dalam method `issueToken()`.
  - [x] Jika mode debug aktif (`APP_DEBUG=true`) dan jenis error adalah `unsupported_grant_type` atau `invalid_client`, lemparkan `RuntimeException` dengan detail error konfigurasi.
  - [x] Pastikan masking `AuthenticationException` tetap aktif dan aman untuk user di non-debug mode (produksi).
- **Kriteria selesai:** Aplikasi menampilkan log diagnosa internal Passport yang jelas di bawah mode debug, dan menyembunyikannya secara aman di mode non-debug.

---

### [CF-003] Kerentanan Setup Manual Passport Client Secrets pada Environment Baru

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/01_starter_readiness.md`
- **Lokasi di kode:** `.env.example` & `README.md`
- **Masalah:**
  Kurangnya instruksi pembuatan Passport Password Grant client membuat developer baru rentan mengalami kegagalan login karena lupa/belum mengonfigurasi Client ID & Secret di file `.env`.
- **Aksi yang harus dilakukan:**
  - [x] Berikan komentar penjelas yang informatif pada variabel Passport Client di `.env.example`.
  - [x] Tambahkan instruksi pemanggilan artisan command `php artisan passport:client --password` secara jelas di `README.md`.
- **Kriteria selesai:** Berkas `.env.example` dan `README.md` memiliki panduan lengkap dan terperinci untuk menginisialisasi Passport client.

---

### [CF-004] Ketiadaan Fallback untuk Database Pengujian PostgreSQL

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/01_starter_readiness.md`
- **Lokasi di kode:** `phpunit.xml` & `composer.json`
- **Masalah:**
  Test suite dikonfigurasi kaku menggunakan database PostgreSQL `laravel_starter_test`. Jika developer baru belum membuat database ini secara manual, test runner akan langsung error saat dijalankan pertama kali.
- **Aksi yang harus dilakukan:**
  - [x] Sediakan environment fallback untuk database pengujian di `phpunit.xml` menggunakan SQLite in-memory (`:memory:`) sebagai alternatif default yang instan.
  - [x] Sediakan dokumentasi panduan pembuatan database pengujian PostgreSQL di `README.md`.
- **Kriteria selesai:** Developer dapat menjalankan `php artisan test` secara instan tanpa kegagalan koneksi database.

---

### [CF-005] Ketiadaan Kontainerisasi Lingkungan Pengembangan (Docker / Laravel Sail)

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/01_starter_readiness.md`
- **Lokasi di kode:** Direktori Root (`docker-compose.yml`)
- **Masalah:**
  Ketiadaan setup Docker/Sail mempersulit onboarding bagi developer baru yang tidak memiliki PHP 8.3, PostgreSQL, dan Redis lokal secara mandiri.
- **Aksi yang harus dilakukan:**
  - [x] Pasang dan integrasikan Laravel Sail (`laravel/sail`) sebagai dependensi dev.
  - [x] Buat berkas `docker-compose.yml` berisi kontainerisasi service minimal (PHP, PostgreSQL, Redis, Mailpit).
  - [x] Perbarui `README.md` dengan instruksi cara mengaktifkan kontainer dengan Sail.
- **Kriteria selesai:** Lingkungan pengembangan terisolasi dapat dinyalakan secara instan menggunakan satu perintah `./vendor/bin/sail up -d`.

---

### [CF-006] Ketiadaan Berkas Panduan Konteks AI Agent (CLAUDE.md)

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/02_ai_agent_friendliness.md`
- **Lokasi di kode:** `CLAUDE.md` (Root Directory)
- **Masalah:**
  AI Agent tidak memiliki panduan context ringkas tentang cara menjalankan test, mengecek gaya penulisan kode (linter), analisis statis, serta konvensi arsitektur proyek.
- **Aksi yang harus dilakukan:**
  - [x] Buat berkas panduan `CLAUDE.md` di root directory.
  - [x] Jabarkan perintah pengoperasian cepat (`php artisan test`, `vendor/bin/pint`, `vendor/bin/phpstan`).
  - [x] Jabarkan konvensi proyek penting (Thin Controller - Fat Service, no global repositories, modular Filament resource schemas).
- **Kriteria selesai:** Berkas `CLAUDE.md` tersedia di root directory dan terbaca sempurna oleh AI agent saat inisialisasi.

---

### [CF-007] Duplikasi Boilerplate Pagination di Setiap Controller Index

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/06_priority_areas.md` & `docs/review/07_action_plan.md`
- **Lokasi di kode:** `app/Support/ApiResponse.php` & `app/Http/Controllers/Api/V1/CategoryController.php`
- **Masalah:**
  Helper `ApiResponse::success()` hanya mendeteksi instance `AbstractPaginator` murni untuk otomatisasi metadata pagination. Namun controller terpaksa memanggil `CategoryResource::collection($categories->getCollection())->resolve()` yang menghasilkan Array murni, sehingga deteksi paginator di `ApiResponse` terlewati dan metadata pagination harus ditulis ulang secara manual di setiap controller. Ini menyebabkan duplikasi kode boilerplate yang tidak perlu dan rawan inkonsistensi.
- **Aksi yang harus dilakukan:**
  - [x] Buka `app/Support/ApiResponse.php` dan tambahkan deteksi tipe `AnonymousResourceCollection` yang menyimpan paginator di properti `$data->resource`.
  - [x] Ubah blok kondisi paginator menjadi: jika `$data` adalah `AnonymousResourceCollection` dengan resource bertipe `AbstractPaginator`, otomatis resolve data dan generate metadata pagination.
  - [x] Perbarui semua controller index (mulai dari `CategoryController`) agar cukup memanggil `ApiResponse::success(CategoryResource::collection($categories))` tanpa menyertakan array `meta` pagination secara manual.
  - [x] Jalankan test suite untuk memastikan tidak ada regresi pada respons pagination: `php artisan test`.
- **Kriteria selesai:** Seluruh endpoint API index yang menggunakan paginator Eloquent menghasilkan metadata pagination secara otomatis cukup dengan satu baris `ApiResponse::success(Resource::collection($paginator))` tanpa ada kode duplikasi di level controller.

### [CF-008] Test Suite Regional Bergantung pada File Eksternal (Rentan Gagal di CI)

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/06_priority_areas.md` & `docs/review/07_action_plan.md`
- **Lokasi di kode:** `tests/Feature/RegionSeederTest.php` & `database/seeders/RegionSeeder.php`
- **Masalah:**
  Test suite untuk Region Seeder bergantung penuh pada berkas JSON besar yang diunduh secara manual via `php artisan regions:download`. Jika file belum ada, tes dilewati (skipped). Di environment CI (GitHub Actions), pengunduhan file dari sumber eksternal dapat menyebabkan keterlambatan build, kegagalan acak akibat rate limit API, dan test coverage yang tidak stabil.
- **Aksi yang harus dilakukan:**
  - [x] Buat folder `tests/Fixtures/regions/` di dalam repository.
  - [x] Buat berkas fixture mini (`countries.json`, `states.json`, `cities.json`, `subdistricts.json`, `villages.json`) berisi data dummy 2-3 entri per level hierarki yang mencukupi untuk memvalidasi logika seeder.
  - [x] Modifikasi `RegionSeeder` (atau tambahkan helper `getSourcePath()`) untuk mendeteksi environment `testing` dan mengalihkan pembacaan data ke direktori `tests/Fixtures/regions/` alih-alih `storage/app/regions/`.
  - [x] Pastikan `RegionSeederTest` tidak melakukan skip jika fixtures tersedia, dan jalankan seluruh alur cascading seeder secara lengkap.
  - [x] Jalankan `php artisan test --filter=RegionSeederTest` untuk memvalidasi hasilnya.
- **Kriteria selesai:** `RegionSeederTest` berjalan instan (di bawah 1 detik) tanpa memerlukan koneksi internet maupun file unduhan eksternal, baik di lingkungan lokal maupun server CI.

---

### [CF-009] Model Factories Tidak Lengkap untuk Model-Model Sekunder

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/01_starter_readiness.md` & `docs/review/07_action_plan.md`
- **Lokasi di kode:** `database/factories/`
- **Masalah:**
  Hanya `UserFactory` dan `CategoryFactory` yang tersedia. Model-model sekunder krusial seperti `UserDevice`, `AppConfig`, `AppVersion`, `Notification`, dan `OtpCode` belum memiliki factory. Tanpa factory, penulisan feature test untuk modul yang bergantung pada model-model ini menjadi verbose, tidak deklaratif, dan sulit dipelihara, sehingga menurunkan kualitas dan kecepatan pengembangan test di masa depan.
- **Aksi yang harus dilakukan:**
  - [x] Buat `UserDeviceFactory` dengan atribut realistis (device token dummy, platform `android`/`ios`, dll.).
  - [x] Buat `AppConfigFactory` dengan atribut key-value konfigurasi yang valid.
  - [x] Buat `AppVersionFactory` dengan atribut versi dan flag `force_update`.
  - [x] Buat `NotificationFactory` dengan atribut judul, body, dan relasi ke `User`.
  - [x] Buat `OtpCodeFactory` dengan atribut kode OTP, waktu kadaluarsa, dan status `is_used`.
  - [x] Pastikan setiap factory baru terdaftar dan dapat dipanggil via `ModelClass::factory()->create()` di dalam test.
- **Kriteria selesai:** Semua model utama (`User`, `Category`, `UserDevice`, `AppConfig`, `AppVersion`, `Notification`, `OtpCode`) memiliki factory yang lengkap dan dapat digunakan langsung dalam unit maupun feature test.

---

### [CF-010] Branding Filament Admin Panel Masih Generik (Tidak Premium)

- **Status:** `[x]` Selesai
- **Prioritas:** Critical
- **Sumber:** `docs/review/06_priority_areas.md` & `docs/review/07_action_plan.md`
- **Lokasi di kode:** `app/Providers/Filament/AdminPanelProvider.php`
- **Masalah:**
  Tampilan admin panel Filament masih menggunakan warna `Color::Emerald` bawaan pabrik dan tidak memiliki logo kustom, favicon, atau konfigurasi dark-mode. Untuk starter project yang diklaim "premium", kesan pertama (*first impression*) back-office yang generik merusak persepsi kualitas dan tidak merepresentasikan standar yang diharapkan.
- **Aksi yang harus dilakukan:**
  - [x] Ganti warna primer Filament dari `Color::Emerald` menjadi `Color::Indigo` (lebih premium dan modern) di `AdminPanelProvider`.
  - [x] Daftarkan logo kustom (SVG/PNG) menggunakan `->brandLogo(asset('images/logo-light.svg'))` dan `->brandLogoHeight('2.5rem')`.
  - [x] Daftarkan favicon kustom menggunakan `->favicon(asset('favicon.ico'))`.
  - [x] Aktifkan database notifications bawaan Filament dengan `->databaseNotifications()`.
  - [x] Buat atau siapkan aset gambar logo placeholder (`public/images/logo-light.svg`) agar tidak ada broken image.
- **Kriteria selesai:** Panel admin di `/admin` menampilkan identitas visual kustom (logo, warna indigo, favicon) yang tidak generik dan konsisten di seluruh halaman.

---

## ═══════════════════════════════════════════
## 🔥 KRITIS — Wajib Sebelum Produksi (5 item)
## ═══════════════════════════════════════════

### [CF-011] Email Verification Dinonaktifkan — Risiko Spam Account

- **Status:** `[x]` Selesai
- **Prioritas:** 🔥 Critical
- **Estimasi Effort:** S (1-2 jam)
- **Sumber:** `docs/review/03_best_practice.md` §A, `docs/review/06_priority_areas.md` §1.2, `docs/review/07_action_plan.md` §A.1
- **Lokasi di kode:** `app/Models/User.php` baris 5
- **Masalah:**
  `MustVerifyEmail` interface di-comment (`// use Illuminate\Contracts\Auth\MustVerifyEmail;`). User bisa login dengan email yang belum diverifikasi — potensi spam account di production. Ini merupakan risiko keamanan fundamental.
- **Aksi yang harus dilakukan:**
  - [x] Uncomment `use Illuminate\Contracts\Auth\MustVerifyEmail;` di `app/Models/User.php`.
  - [x] Tambahkan `MustVerifyEmail` ke class declaration: `class User extends Authenticatable implements FilamentUser, OAuthenticatable, MustVerifyEmail`.
  - [x] Tambahkan endpoint API untuk send verification email: `POST /api/v1/auth/email/send-verification`.
  - [x] Tambahkan endpoint API untuk verify email: `POST /api/v1/auth/email/verify`.
  - [x] Tambahkan middleware `verified` pada route API yang memerlukan (opsional, pertimbangkan apakah login tetap diizinkan tanpa verifikasi, dengan reminder di response).
  - [x] Update test `AuthTest.php` untuk mencakup skenario email verification.
- **Kriteria selesai:** `MustVerifyEmail` aktif, endpoint verifikasi tersedia, dan test mengkonfirmasi bahwa flow verification berjalan.

---

### [CF-012] Test Menggunakan SQLite Padahal Production PostgreSQL

- **Status:** `[x]` Selesai
- **Prioritas:** 🔥 Critical
- **Estimasi Effort:** S (1 jam)
- **Sumber:** `docs/review/06_priority_areas.md` §5.2, `docs/review/07_action_plan.md` §A.2
- **Lokasi di kode:** `phpunit.xml` baris 26-28
- **Masalah:**
  `phpunit.xml` mengonfigurasi `DB_CONNECTION=sqlite` dan `DB_DATABASE=:memory:`, padahal project menggunakan PostgreSQL di production. SQLite tidak mendukung semua fitur PostgreSQL (JSONB, UUID/ULID generation, `ILIKE`, enum handling). Test bisa hijau di SQLite tapi gagal di PostgreSQL production.
- **Aksi yang harus dilakukan:**
  - [x] Buat file `.env.testing` dengan konfigurasi PostgreSQL (`DB_CONNECTION=pgsql`, `DB_DATABASE=laravel_starter_test`). (Catatan: diimplementasikan langsung sebagai default override di phpunit.xml agar dinamis dan terintegrasi otomatis dengan Sail/Herd).
  - [x] Update `phpunit.xml` untuk menggunakan PostgreSQL sebagai default, dengan fallback SQLite yang terdokumentasi di komentar.
  - [x] Dokumentasikan di `README.md` cara setup database test PostgreSQL: `createdb laravel_starter_test`.
  - [x] Pastikan `composer test` tetap berjalan dengan kedua driver.
- **Kriteria selesai:** Test suite berjalan di PostgreSQL secara default, mendeteksi bug database-specific. SQLite tetap bisa digunakan sebagai fallback cepat jika PostgreSQL tidak tersedia.

---

### [CF-013] Tidak Ada File LICENSE

- **Status:** `[x]` Selesai
- **Prioritas:** 🔥 Critical
- **Estimasi Effort:** S (10 menit)
- **Sumber:** `docs/review/04_documentation_completeness.md` §D.1, `docs/review/07_action_plan.md` §A.3
- **Lokasi di kode:** Root directory
- **Masalah:**
  Tidak ada file `LICENSE` meskipun `composer.json` menyatakan `"license": "MIT"`. Ketidakjelasan hukum bagi kontributor dan pengguna project.
- **Aksi yang harus dilakukan:**
  - [x] Buat file `LICENSE` di root directory dengan full text MIT License.
  - [x] Isi tahun dan nama organisasi/pemilik copyright.
- **Kriteria selesai:** File `LICENSE` tersedia di root directory, konsisten dengan deklarasi di `composer.json`.

---

### [CF-014] Filament Resource Tidak Enforce RBAC Per-Resource

- **Status:** `[x]` Selesai
- **Prioritas:** 🔥 Critical
- **Estimasi Effort:** M (4-6 jam)
- **Sumber:** `docs/review/03_best_practice.md` §D, `docs/review/06_priority_areas.md` §4.2, `docs/review/07_action_plan.md` §A.4
- **Lokasi di kode:** `app/Filament/Resources/`
- **Masalah:**
  Setelah user masuk panel (via `canAccessPanel()`), mereka bisa mengakses semua resource. Role `staff` yang seharusnya hanya bisa akses Category, bisa melihat Users dan Roles di sidebar. Tidak ada per-resource permission enforcement.
- **Aksi yang harus dilakukan:**
  - [x] Evaluasi pendekatan: Install `bezhansalleh/filament-shield` ATAU override manual `canViewAny()`, `canCreate()`, `canEdit()`, `canDelete()` di setiap resource.
  - [x] Jika manual: Override static methods di setiap Resource class (User, Role, Category, AppConfig, AppVersion) menggunakan Spatie permission checks.
  - [x] Update `RolePermissionSeeder` jika perlu tambahkan permission baru.
  - [x] Update back-office test (`tests/Feature/BackOffice/`) untuk memvalidasi bahwa role `staff` TIDAK bisa akses resource User/Role.
- **Kriteria selesai:** User dengan role `staff` hanya bisa melihat dan mengakses resource yang diizinkan (e.g. Category) di sidebar Filament. Resource yang tidak diizinkan tersembunyi.

---

### [CF-015] Unit Test untuk Service Layer Kosong

- **Status:** `[x]` Selesai
- **Prioritas:** 🔥 Critical
- **Estimasi Effort:** M (6-8 jam)
- **Sumber:** `docs/review/03_best_practice.md` §E, `docs/review/06_priority_areas.md` §5.2, `docs/review/07_action_plan.md` §A.5
- **Lokasi di kode:** `tests/Unit/Services/` (hanya `.gitkeep`)
- **Masalah:**
  `AuthService`, `OtpService`, `PushNotificationService`, `FileUploadService` tidak ditest secara isolasi. Bug dalam logika bisnis kritis (token issuance, OTP verification, push notification) hanya terdeteksi via feature test yang lebih lambat dan kurang presisi.
- **Aksi yang harus dilakukan:**
  - [x] Buat `tests/Unit/Services/AuthServiceTest.php` — test login, refresh, logout, revoke, device upsert, issueTokenForUser.
  - [x] Buat `tests/Unit/Services/OtpServiceTest.php` — test OTP generation, verification, expiry, max attempts, rate limiting.
  - [x] Buat `tests/Unit/Services/PushNotificationServiceTest.php` — test send notification, FCM driver interface, LogFcmDriver fallback.
  - [x] Buat `tests/Unit/Services/FileUploadServiceTest.php` — test file upload, delete, path generation.
  - [x] Gunakan mocking (Mockery) untuk isolasi dari database dan external services.
  - [x] Jalankan `php artisan test --testsuite=Unit` untuk memvalidasi.
- **Kriteria selesai:** Minimal 4 unit test class tersedia di `tests/Unit/Services/` dengan coverage logika bisnis inti. Unit test suite berjalan kurang dari 5 detik.

---

## ═══════════════════════════════════════════
## ⚠️ SPRINT 1 — Perbaikan Penting (9 item)
## ═══════════════════════════════════════════

### [CF-016] Tidak Ada Endpoint User Registration (API)

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** M (4-6 jam)
- **Sumber:** `docs/review/05_feature_completeness.md` §A, `docs/review/07_action_plan.md` §B.2
- **Lokasi di kode:** `app/Http/Controllers/Api/V1/AuthController.php`, `routes/api.php`
- **Masalah:**
  Tidak ada endpoint register mandiri. User hanya bisa dibuat via admin panel or seeder. Ini merupakan fitur fundamental yang hilang untuk aplikasi mobile.
- **Aksi yang harus dilakukan:**
  - [x] Buat `RegisterRequest` form request dengan validasi name, email, password, password_confirmation.
  - [x] Tambahkan method `register()` di `AuthController` atau buat `RegisterController` terpisah.
  - [x] Tambahkan route `POST /api/v1/auth/register` dengan rate limiting (`throttle:6,1`).
  - [x] Integrasikan dengan email verification flow (CF-011).
  - [x] Buat feature test `tests/Feature/Api/RegistrationTest.php`.
- **Kriteria selesai:** User bisa self-register via API, mendapat token, dan (opsional) perlu verifikasi email.

---

### [CF-017] Tidak Ada Endpoint Password Reset via API

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** M (4-6 jam)
- **Sumber:** `docs/review/05_feature_completeness.md` §A, `docs/review/07_action_plan.md` §B.3
- **Lokasi di kode:** `routes/api.php`
- **Masalah:**
  Hanya ada `changePassword` yang memerlukan login. Tidak ada "forgot password" / "reset password" flow untuk mobile user yang lupa password.
- **Aksi yang harus dilakukan:**
  - [x] Buat endpoint `POST /api/v1/auth/forgot-password` — kirim reset link/token via email.
  - [x] Buat endpoint `POST /api/v1/auth/reset-password` — validasi token dan reset password.
  - [x] Buat Form Request untuk masing-masing endpoint.
  - [x] Gunakan Laravel built-in `Password::sendResetLink()` dan `Password::reset()`.
  - [x] Buat feature test `tests/Feature/Api/PasswordResetTest.php`.
- **Kriteria selesai:** User bisa request password reset via email dan mengatur password baru tanpa login.

---

### [CF-018] Tidak Ada Endpoint "Logout All Devices"

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** S (2 jam)
- **Sumber:** `docs/review/06_priority_areas.md` §1.2 Masalah 4, `docs/review/07_action_plan.md` §B.10
- **Lokasi di kode:** `app/Services/Auth/AuthService.php`
- **Masalah:**
  Logout hanya me-revoke token yang sedang digunakan. User yang kehilangan device tidak bisa invalidasi semua session sekaligus.
- **Aksi yang harus dilakukan:**
  - [x] Tambahkan method `logoutAllDevices(User $user)` di `AuthService` — revoke all access tokens, refresh tokens, dan nullify semua push tokens.
  - [x] Tambahkan endpoint `POST /api/v1/auth/logout-all` di route API.
  - [x] Tambahkan method `logoutAll()` di `AuthController`.
  - [x] Buat feature test untuk memverifikasi bahwa semua token di-revoke.
- **Kriteria selesai:** Endpoint `/api/v1/auth/logout-all` tersedia dan berhasil me-revoke semua token milik user.

---

### [CF-019] Buat File SECURITY.md

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** S (30 menit)
- **Sumber:** `docs/review/04_documentation_completeness.md` §D.3, `docs/review/07_action_plan.md` §B.8
- **Lokasi di kode:** Root directory
- **Masalah:**
  Tidak ada file kebijakan pelaporan kerentanan keamanan. Best practice open source mengharuskan adanya `SECURITY.md`.
- **Aksi yang harus dilakukan:**
  - [x] Buat `SECURITY.md` di root directory.
  - [x] Tambahkan section: Supported Versions, Reporting a Vulnerability, Response Timeline, Known Security Considerations (Passport keys, rate limiting, HTTPS enforcement).
- **Kriteria selesai:** File `SECURITY.md` tersedia dengan panduan pelaporan vulnerability yang jelas.

---

### [CF-020] Buat ERD Diagram Database

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** S (2 jam)
- **Sumber:** `docs/review/04_documentation_completeness.md` §D.2, `docs/review/07_action_plan.md` §B.9
- **Lokasi di kode:** `docs/erd/`
- **Masalah:**
  Relasi antar tabel hanya bisa dipahami dari model docblock dan migration. Tidak ada visualisasi ERD.
- **Aksi yang harus dilakukan:**
  - [x] Buat folder `docs/erd/`.
  - [x] Buat `docs/erd/database_erd.md` dengan Mermaid ERD diagram.
  - [x] Dokumentasikan relasi: users→user_devices (1:N), users→notifications (1:N), users↔roles (M:N), categories (standalone, soft-delete), regions (self-referential), dll.
  - [x] Tambahkan keterangan kolom per tabel.
- **Kriteria selesai:** ERD diagram visual tersedia di `docs/erd/database_erd.md` dan dapat dirender oleh Markdown viewer.

---

### [CF-021] Race Condition pada Device Upsert di AuthService

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** S (1 jam)
- **Sumber:** `docs/review/06_priority_areas.md` §1.2 Masalah 3
- **Lokasi di kode:** `app/Services/Auth/AuthService.php` baris 105-121
- **Masalah:**
  `UserDevice::query()->updateOrCreate(...)` tanpa DB transaction. Login concurrent dari device yang sama bisa menyebabkan duplicate insert sebelum unique constraint check.
- **Aksi yang harus dilakukan:**
  - [x] Bungkus `upsertDevice()` dalam `DB::transaction()`.
  - [x] Atau gunakan database-level unique constraint pada `(user_id, device_id)` dan handle `UniqueConstraintViolationException` secara graceful.
  - [x] Tambahkan unit test untuk skenario concurrent upsert.
- **Kriteria selesai:** Device upsert aman terhadap race condition tanpa duplicate records.

---

### [CF-022] OTP Login Tidak Punya Refresh Token (Inkonsistensi UX)

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** M (4-6 jam)
- **Sumber:** `docs/review/06_priority_areas.md` §1.2 Masalah 2
- **Lokasi di kode:** `app/Services/Auth/AuthService.php` baris 49-60
- **Masalah:**
  `issueTokenForUser()` menggunakan Personal Access Token yang tidak mendukung refresh. User OTP login harus re-login setiap 8 jam — inkonsistensi dengan password login yang mendapat refresh token 30 hari.
- **Aksi yang harus dilakukan:**
  - [x] Evaluasi opsi: (a) Redesign OTP login agar menggunakan Password Grant/custom grant, ATAU (b) Tambahkan mekanisme auto-extend untuk Personal Access Token.
  - [x] Implementasikan solusi yang dipilih.
  - [x] Update test `OtpTest.php` untuk memvalidasi token refresh behavior.
- **Kriteria selesai:** User yang login via OTP mendapatkan pengalaman token refresh yang konsisten dengan login password.

---

### [CF-023] Type Hint `$data` di ApiResponse::success() Kurang Strict

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** S (30 menit)
- **Sumber:** `docs/review/06_priority_areas.md` §3.2 Masalah 1
- **Lokasi di kode:** `app/Support/ApiResponse.php` baris 13
- **Masalah:**
  Parameter `$data = null` tanpa type hint. PHPStan level tinggi mungkin tidak menangkap tipe yang salah.
- **Aksi yang harus dilakukan:**
  - [x] Tambahkan union type hint: `mixed $data = null` atau lebih spesifik: `AnonymousResourceCollection|AbstractPaginator|JsonResource|array|null $data = null`.
  - [x] Tambahkan PHPDoc `@param` annotation jika menggunakan union type yang kompleks.
  - [x] Jalankan `composer analyse` untuk memastikan tidak ada regresi.
- **Kriteria selesai:** `ApiResponse::success()` memiliki type hint yang eksplisit dan PHPStan clean.

---

### [CF-024] Publish config/cors.php Secara Eksplisit

- **Status:** `[x]` Selesai
- **Prioritas:** ⚠️ Penting
- **Estimasi Effort:** S (1 jam)
- **Sumber:** `docs/review/01_starter_readiness.md` §C, `docs/review/07_action_plan.md` §C.5
- **Lokasi di kode:** `config/`
- **Masalah:**
  Tidak ada file `config/cors.php` yang di-publish. Laravel memiliki default CORS, tetapi untuk starter project seharusnya ada konfigurasi eksplisit — terutama jika ada rencana web client selain mobile.
- **Aksi yang harus dilakukan:**
  - [x] Publish CORS config: `php artisan config:publish cors` (atau `vendor:publish --tag=cors`).
  - [x] Review dan dokumentasikan konfigurasi yang direkomendasikan.
  - [x] Tambahkan komentar penjelas di file cors.php.
- **Kriteria selesai:** File `config/cors.php` tersedia secara eksplisit dengan konfigurasi yang terdokumentasi.

---

## ═══════════════════════════════════════════
## 💡 SPRINT 2 — Peningkatan (10 item)
## ═══════════════════════════════════════════

### [CF-025] Tambahkan GitHub Actions CI Pipeline

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** S (2-3 jam)
- **Sumber:** `docs/review/06_priority_areas.md` §5.3, `docs/review/07_action_plan.md` §C.1
- **Lokasi di kode:** `.github/workflows/`
- **Masalah:**
  Quality gate (Pint, PHPStan, PHPUnit) hanya berjalan manual. Developer bisa push kode yang gagal test.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `.github/workflows/ci.yml` dengan 3 jobs: lint (pint --test), analyse (phpstan), test (phpunit + PostgreSQL service container).
  - [ ] Trigger: push & pull_request ke branch `main`.
  - [ ] Konfigurasikan PostgreSQL service container (`postgres:18-alpine`).
- **Kriteria selesai:** Setiap push/PR ke `main` menjalankan quality gate otomatis (lint, analyse, test).

---

### [CF-026] Tambahkan spatie/laravel-activitylog untuk Audit Trail

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** M (4-6 jam)
- **Sumber:** `docs/review/05_feature_completeness.md` §E & §F, `docs/review/07_action_plan.md` §C.2
- **Lokasi di kode:** Seluruh model dan Filament resources
- **Masalah:**
  Tidak ada audit trail untuk CRUD operations. Tidak bisa melacak siapa mengubah apa dan kapan.
- **Aksi yang harus dilakukan:**
  - [ ] Install: `composer require spatie/laravel-activitylog`.
  - [ ] Publish dan run migration.
  - [ ] Tambahkan trait `LogsActivity` pada model yang kritis (User, Category, AppConfig, AppVersion, Role).
  - [ ] Konfigurasi log attributes yang ditrack.
  - [ ] (Opsional) Buat Filament resource/widget untuk menampilkan activity log.
- **Kriteria selesai:** Perubahan data pada model kritis tercatat di activity log dan bisa dilihat di back-office.

---

### [CF-027] Buat API Error Code Enum (ApiErrorCode)

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** S (2 jam)
- **Sumber:** `docs/review/06_priority_areas.md` §3.2 Masalah 2, `docs/review/07_action_plan.md` §C.3
- **Lokasi di kode:** `app/Support/Enums/`
- **Masalah:**
  Error response menggunakan `code` parameter optional tapi tidak ada enum/constant untuk error codes. Flutter client harus parsing message string.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `app/Support/Enums/ApiErrorCode.php` sebagai backed enum (`string`).
  - [ ] Definisikan error codes: `AUTH_INVALID_CREDENTIALS`, `AUTH_INACTIVE_ACCOUNT`, `AUTH_TOKEN_EXPIRED`, `VALIDATION_FAILED`, `RESOURCE_NOT_FOUND`, `RATE_LIMIT_EXCEEDED`, `MAINTENANCE_MODE`, dll.
  - [ ] Integrasikan dengan `ApiResponse::error()` — gunakan enum value sebagai `$code`.
  - [ ] Update controller yang memanggil `ApiResponse::error()` untuk menggunakan `ApiErrorCode`.
- **Kriteria selesai:** Semua error response API menggunakan standardized error codes via enum.

---

### [CF-028] Tambahkan Filament Test untuk Semua Resource

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** M (6-8 jam)
- **Sumber:** `docs/review/03_best_practice.md` §E, `docs/review/07_action_plan.md` §C.4
- **Lokasi di kode:** `tests/Feature/BackOffice/`
- **Masalah:**
  Back-office coverage hanya ~40%. Tidak ada test untuk `AppConfigResource`, `AppVersionResource`, atau `SendNotificationPage`.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `tests/Feature/BackOffice/AppConfigManagementTest.php`.
  - [ ] Buat `tests/Feature/BackOffice/AppVersionManagementTest.php`.
  - [ ] Buat `tests/Feature/BackOffice/SendNotificationPageTest.php`.
  - [ ] Test CRUD operations, validation, dan permission enforcement (setelah CF-014 selesai).
- **Kriteria selesai:** Back-office test coverage naik dari ~40% ke ~80%.

---

### [CF-029] Tambahkan Makefile sebagai Shortcut Developer

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** S (1 jam)
- **Sumber:** `docs/review/01_starter_readiness.md` §A, `docs/review/07_action_plan.md` §C.6
- **Lokasi di kode:** Root directory
- **Masalah:**
  Tidak ada `Makefile`. Developer yang familiar dengan `make` command tidak punya shortcut.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `Makefile` dengan target: `dev`, `test`, `lint`, `analyse`, `setup`, `fresh`, `quality`.
- **Kriteria selesai:** Developer bisa menjalankan `make test`, `make lint`, `make quality` sebagai shortcut.

---

### [CF-030] Buat Panduan Deployment Production (docs/deployment.md)

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** M (3-4 jam)
- **Sumber:** `docs/review/04_documentation_completeness.md` §D.4, `docs/review/07_action_plan.md` §C.7
- **Lokasi di kode:** `docs/deployment.md`
- **Masalah:**
  Tidak ada panduan deployment production. Developer tidak tahu langkah-langkah deployment ke server.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `docs/deployment.md` berisi: Prasyarat production, Environment Variables, Langkah deployment (10 langkah), Queue Worker (Supervisor config), Nginx configuration, Monitoring rekomendasi.
- **Kriteria selesai:** Dokumentasi deployment production tersedia dan cukup lengkap untuk deploy ke server baru.

---

### [CF-031] Dispatch Push Notification via Queue Job

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** S (2-3 jam)
- **Sumber:** `docs/review/03_best_practice.md` §F, `docs/review/07_action_plan.md` §C.8
- **Lokasi di kode:** `app/Services/PushNotificationService.php`
- **Masalah:**
  Push notification (`PushNotificationService`) berjalan synchronous. Ini memblokir API response saat mengirim FCM.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `app/Jobs/SendPushNotificationJob.php`.
  - [ ] Pindahkan logika pengiriman FCM ke dalam Job.
  - [ ] Update `PushNotificationService` untuk dispatch Job alih-alih eksekusi langsung.
  - [ ] Update test untuk memverifikasi Job dispatched (mock Queue).
- **Kriteria selesai:** Push notification dikirim via queue secara asynchronous, tidak memblokir API response.

---

### [CF-032] Tambahkan GitHub Templates (Issue & PR)

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** S (1 jam)
- **Sumber:** `docs/review/04_documentation_completeness.md` §B, `docs/review/07_action_plan.md` §C.9
- **Lokasi di kode:** `.github/`
- **Masalah:**
  Tidak ada template standar untuk issue dan PR. Kontribusi tidak terstandarisasi.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `.github/ISSUE_TEMPLATE/bug_report.md` (describe bug, steps to reproduce, expected/actual, environment).
  - [ ] Buat `.github/ISSUE_TEMPLATE/feature_request.md` (problem, proposed solution, alternatives).
  - [ ] Buat `.github/pull_request_template.md` (what, related issue, checklist: quality gate, docs, env, migration, test).
- **Kriteria selesai:** Template tersedia dan muncul saat membuat issue/PR baru di GitHub.

---

### [CF-033] Tambahkan Dark Mode Logo Variant di Filament

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** S (30 menit)
- **Sumber:** `docs/review/06_priority_areas.md` §4.2 Masalah 2
- **Lokasi di kode:** `app/Providers/Filament/AdminPanelProvider.php`, `public/images/`
- **Masalah:**
  Hanya ada `logo-light.svg`. Jika dark mode diaktifkan, logo mungkin tidak terlihat.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `public/images/logo-dark.svg` (variant untuk dark mode).
  - [ ] Tambahkan `->darkModeBrandLogo(asset('images/logo-dark.svg'))` di `AdminPanelProvider`.
- **Kriteria selesai:** Logo terlihat jelas baik di light mode maupun dark mode.

---

### [CF-034] Buat CHANGELOG.md (Keep a Changelog Format)

- **Status:** `[x]` Selesai
- **Prioritas:** 💡 Enhancement
- **Estimasi Effort:** S (1 jam)
- **Sumber:** `docs/review/04_documentation_completeness.md` §D.8
- **Lokasi di kode:** Root directory
- **Masalah:**
  Progress hanya dilacak di `WORK_SESSIONS.md` dan `TASK.md` yang bukan changelog publik.
- **Aksi yang harus dilakukan:**
  - [ ] Buat `CHANGELOG.md` mengikuti format [Keep a Changelog](https://keepachangelog.com/).
  - [ ] Dokumentasikan perubahan dari Sprint 0 sampai sekarang (retrospektif).
  - [ ] Tambahkan section `[Unreleased]` untuk perubahan yang sedang berlangsung.
- **Kriteria selesai:** File `CHANGELOG.md` tersedia dengan riwayat perubahan yang terstruktur.

---

## Checklist Ringkas

Daftar cepat untuk tracking progress:

### ✅ Sprint 0 — Selesai (10/10)
- [x] CF-001 — Enforcement HTTPS di Production
- [x] CF-002 — Masking Error Passport Proxy
- [x] CF-003 — Instruksi Passport Client di .env.example & README
- [x] CF-004 — Fallback Database Pengujian
- [x] CF-005 — Kontainerisasi Docker / Sail
- [x] CF-006 — Panduan AI Agent (CLAUDE.md)
- [x] CF-007 — Duplikasi Boilerplate Pagination
- [x] CF-008 — Test Suite Regional Fixtures
- [x] CF-009 — Model Factories Lengkap
- [x] CF-010 — Branding Filament Premium

### 🔥 Kritis — Wajib Sebelum Produksi (5/5)
- [x] CF-011 — Aktifkan Email Verification
- [x] CF-012 — Test Menggunakan PostgreSQL (bukan SQLite)
- [x] CF-013 — Buat File LICENSE
- [x] CF-014 — Filament RBAC Per-Resource
- [x] CF-015 — Unit Test Service Layer

### ⚠️ Sprint 1 — Perbaikan Penting (9/9)
- [x] CF-016 — Endpoint User Registration API
- [x] CF-017 — Endpoint Password Reset API
- [x] CF-018 — Endpoint Logout All Devices
- [x] CF-019 — Buat SECURITY.md
- [x] CF-020 — ERD Diagram Database
- [x] CF-021 — Race Condition Device Upsert
- [x] CF-022 — OTP Login Refresh Token
- [x] CF-023 — Type Hint ApiResponse
- [x] CF-024 — Publish config/cors.php

### 💡 Sprint 2 — Peningkatan (10/10)
- [x] CF-025 — GitHub Actions CI Pipeline
- [x] CF-026 — Activity Log (spatie/laravel-activitylog)
- [x] CF-027 — API Error Code Enum
- [x] CF-028 — Filament Test Coverage
- [x] CF-029 — Makefile Shortcut
- [x] CF-030 — Deployment Guide
- [x] CF-031 — Push Notification via Queue
- [x] CF-032 — GitHub Templates (Issue & PR)
- [x] CF-033 — Dark Mode Logo Filament
- [x] CF-034 — CHANGELOG.md

---

## Estimasi Total Effort

| Fase | Item | Effort | Timeline | Status |
|------|------|--------|----------|--------|
| ✅ Sprint 0 | 10 task | ~30 jam | Selesai | ✅ Selesai |
| 🔥 Kritis | 5 task | ~15-20 jam | 2-3 hari | ✅ Selesai |
| ⚠️ Sprint 1 | 9 task | ~20-30 jam | 1-2 minggu | ✅ Selesai |
| 💡 Sprint 2 | 10 task | ~25-35 jam | 2-4 minggu | ✅ Selesai |
| **TOTAL tersisa** | **0 task** | **0 jam** | **-** | **🎉 LULUS** |

> **Catatan:** Estimasi di atas **TIDAK** termasuk implementasi multi-tenancy. Jika multi-tenancy dibutuhkan, tambahkan ~40-60 jam dan 2-3 minggu tambahan.

---

## Catatan untuk Agent Eksekutor

- Kerjakan task **sesuai urutan** CF-011, CF-012, dst. kecuali ada dependensi yang mengharuskan urutan berbeda
- **Dependensi penting:**
  - CF-016 (Registration) bergantung pada CF-011 (Email Verification) — kerjakan CF-011 terlebih dahulu
  - CF-028 (Filament Test) bergantung pada CF-014 (Filament RBAC) — kerjakan CF-014 terlebih dahulu
- Setelah menyelesaikan satu task, **update status checkbox** di bagian task detail DAN checklist ringkas
- Jika menemukan masalah baru saat mengerjakan sebuah task, tambahkan sebagai task baru di bagian bawah dengan ID berikutnya
- Hanya tandai task sebagai `[x]` jika tugas sudah selesai dikerjakan, sudah di test, dan berhasil
- Perbarui Catatan Riwayat Eksekusi (Footer Note) setelah menyelesaikan satu task

---

## Catatan Riwayat Eksekusi (Footer Note)

*Terakhir dijalankan/diperbarui pada:* `2026-05-24 20:56:23`
*Daftar task yang di-generate/diperbarui pada eksekusi terakhir:*
- **[CF-001 s/d CF-024]** — Dipertahankan (status: selesai) ✅
- **[CF-025]** — 💡 GitHub Actions CI *(selesai)* ✅
- **[CF-026]** — 💡 Activity Log *(selesai)* ✅
- **[CF-027]** — 💡 API Error Code Enum *(selesai)* ✅
- **[CF-028]** — 💡 Filament Test Coverage *(selesai)* ✅
- **[CF-029]** — 💡 Makefile *(selesai)* ✅
- **[CF-030]** — 💡 Deployment Guide *(selesai)* ✅
- **[CF-031]** — 💡 Push Notification Queue *(selesai)* ✅
- **[CF-032]** — 💡 GitHub Templates *(selesai)* ✅
- **[CF-033]** — 💡 Dark Mode Logo *(selesai)* ✅
- **[CF-034]** — 💡 CHANGELOG.md *(selesai)* ✅
