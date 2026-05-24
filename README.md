# Laravel Starter — API Backend & Back-office

Starter project berbasis **Laravel + PostgreSQL** yang dirancang sebagai fondasi untuk dua kebutuhan sekaligus:

1. **API backend** untuk aplikasi mobile **Flutter** (token-based auth via OAuth2).
2. **Back-office web UI** untuk manajemen internal — user management, role & permission, dan data master (session-based auth via Filament).

Tujuannya adalah menyediakan kerangka kerja yang bersih, konsisten, dan siap dikembangkan, tanpa over-engineering, sehingga tim bisa langsung fokus membangun fitur bisnis.

---

## Stack Teknologi

| Komponen | Pilihan | Versi yang Direkomendasikan |
|---|---|---|
| Framework | Laravel | `13.x` (terpasang `13.11`) |
| Bahasa | PHP | `8.3+` (8.4 didukung) |
| Database | PostgreSQL | `16` atau `17` |
| API Auth | Laravel Passport | `13.x` (OAuth2, Password Grant) |
| Back-office UI | Filament | `5.x` |
| RBAC | spatie/laravel-permission | `7.x` |
| Cache / Queue (opsional) | Redis | `7.x` |
| PHP runtime lokal | Laravel Herd / Sail / Valet | sesuai OS |

> ⚠️ **Catatan versi:** Semua versi di atas adalah rekomendasi pada saat dokumen dibuat. Di awal Sesi 1, jalankan `composer create-project laravel/laravel` dan cek rilis stable terbaru dari tiap package sebelum mengunci versi di `composer.json`.

---

## Prinsip Desain

- **API-first** — Kontrak API adalah warga kelas satu. Back-office dan mobile adalah dua konsumen yang setara di atas domain logic yang sama.
- **Separation of concerns** — Controller tipis, logika bisnis di **Service layer**, akses data via **Eloquent** (tanpa Repository pattern — lihat [ARCHITECTURE.md](docs/ARCHITECTURE.md)).
- **Konsistensi kontrak** — Semua response API mengikuti satu format JSON standar (envelope + error format konsisten).
- **Single source of truth untuk authorization** — Satu sistem RBAC (spatie) dipakai bersama oleh API guard dan web guard.
- **Hindari over-engineering** — Tidak ada abstraksi spekulatif. Tambahkan layer hanya ketika kebutuhan nyata muncul.
- **Convention over configuration** — Ikuti konvensi Laravel; jangan melawan framework.

---

## Daftar Isi Dokumentasi

| Dokumen | Isi |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Arsitektur sistem, layering, struktur direktori, strategi auth, package, best practice Flutter ↔ Laravel |
| [docs/MODULES.md](docs/MODULES.md) | Daftar modul & fitur starter beserta prioritas (core / nice-to-have) |
| [docs/WORK_SESSIONS.md](docs/WORK_SESSIONS.md) | Rencana pembagian sesi kerja (~5 jam/sesi) untuk implementasi bertahap |
| [docs/DATA_MASTER_PATTERN.md](docs/DATA_MASTER_PATTERN.md) | Pola replikasi CRUD data master baru berdasarkan modul `Category` |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Panduan branch, konvensi commit (Conventional Commits), quality gate, & push |

---

## Cara Menjalankan

### Opsi A: Menjalankan Secara Lokal (Local Environment)

> Prasyarat: **PHP 8.3+**, **Composer 2**, **PostgreSQL 14+**, dan **Node 20+**.
> Di Windows lokal proyek ini pernah memakai `C:\php8.3.6\php.exe` karena PATH default masih PHP 7.4.

```bash
# 1. Install dependency
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Isi kredensial PostgreSQL di .env, lalu buat database aplikasi:
#    DB_CONNECTION=pgsql
#    DB_DATABASE=laravel_starter
#    DB_USERNAME=<user>
#    DB_PASSWORD=<password>
php artisan migrate --seed

# 4. Build assets & jalankan
npm run build
php artisan serve
```

### Opsi B: Menjalankan Menggunakan Docker (Laravel Sail)

Bagi Anda yang ingin menginisialisasi lingkungan pengembangan instan tanpa perlu memasang PHP, PostgreSQL, Node, atau Redis secara lokal di mesin Anda, Anda dapat menggunakan kontainerisasi **Laravel Sail** yang sudah terintegrasi.

> Prasyarat: **Docker Desktop** telah terpasang dan berjalan di sistem Anda.

```bash
# 1. Setup environment
cp .env.example .env

# 2. Nyalakan kontainer Docker (PostgreSQL, Redis, Mailpit, PHP 8.3) di background
# (Jika baru pertama kali, proses build image akan memakan waktu beberapa menit)
./vendor/bin/sail up -d

# 3. Generate key & jalankan migrasi database serta seeder
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# 4. Build assets & jalankan frontend compiler
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Setelah kontainer berjalan, aplikasi dapat diakses di `http://localhost`. Anda juga dapat memantau kotak masuk email pengujian (Mailpit) di `http://localhost:8025`.
Untuk menghentikan kontainer, jalankan `./vendor/bin/sail down`.


Cek koneksi: buka `GET /api/v1/health` → harus mengembalikan envelope JSON `{ "success": true, ... }`.

**Akun admin default (seeder):** `admin@example.com` / `password` (role `super-admin`). Login back-office di `/admin`.

> [!WARNING]
> **PENTING: Konfigurasi Passport Client ID & Client Secret**
> Endpoint login API (`POST /api/v1/auth/login`) bergantung sepenuhnya pada Laravel Passport Password Client. Pada instalasi baru atau clone baru, Anda **WAJIB** menjalankan perintah berikut:
> ```bash
> php artisan passport:keys
> php artisan passport:client --password
> ```
> Setelah menjalankan perintah kedua, Anda akan mendapatkan **Client ID** dan **Client Secret**. Salin kedua nilai tersebut dan masukkan ke dalam variabel lingkungan berikut di file `.env`:
> ```env
> PASSPORT_PASSWORD_CLIENT_ID=<Client-ID-Anda>
> PASSPORT_PASSWORD_CLIENT_SECRET=<Client-Secret-Anda>
> ```
> Jika langkah ini terlewatkan, API Login akan mengembalikan error kegagalan autentikasi atau server error.


**Endpoint Auth API (Flutter):** `POST /api/v1/auth/login`, `POST /api/v1/auth/refresh`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`. Lihat [docs/ARCHITECTURE.md §5](docs/ARCHITECTURE.md).

**Dokumentasi API:** Scramble menyediakan dokumentasi interaktif di `/docs/api` dan OpenAPI JSON di `/docs/api.json`. Endpoint docs hanya terbuka otomatis di `local`; untuk environment lain gunakan Gate `viewApiDocs`. Tim Flutter/Postman dapat memakai URL OpenAPI JSON tersebut sebagai sumber kontrak API.

**Testing:** Pengujian secara default berjalan di database PostgreSQL terpisah bernama `laravel_starter_test` menggunakan konfigurasi di `.env.testing` agar sepenuhnya selaras dengan lingkungan produksi (PostgreSQL).

Jika Anda menggunakan **Laravel Sail (Docker)**, database pengujian `laravel_starter_test` sudah dibuat dan diinisialisasi secara otomatis saat kontainer dijalankan.

Jika Anda menjalankan secara **lokal tanpa Docker/Sail**, Anda **WAJIB** membuat database pengujian tersebut terlebih dahulu sebelum menjalankan pengujian:
```bash
# Untuk PostgreSQL lokal (gunakan terminal/tool pilihan Anda):
createdb laravel_starter_test
```

Setelah database siap, Anda dapat menjalankan test suite dan linter dengan perintah berikut:
```bash
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
```

> [!TIP]
> **Fallback ke SQLite In-Memory:**
> Jika Anda belum memasang PostgreSQL atau ingin menjalankan tes secara super cepat menggunakan driver SQLite `:memory:`, Anda cukup menghapus komentar (uncomment) bagian SQLite di berkas `phpunit.xml` atau mengatur `DB_CONNECTION=sqlite` dan `DB_DATABASE=:memory:` di berkas `.env.testing`.

Jika PATH masih mengarah ke PHP lama di Windows, jalankan quality gate dengan binary PHP 8.3:

```powershell
C:\php8.3.6\php.exe artisan test
C:\php8.3.6\php.exe vendor\bin\pint
C:\php8.3.6\php.exe vendor\bin\phpstan analyse --memory-limit=1G
```


---

## Panduan Pra-Push & Quality Gates (Pre-Push Best Practices)

Untuk menjaga repositori dan pipeline CI GitHub tetap bersih, hijau (lulus), dan bebas dari kegagalan build, setiap pengembang **sangat direkomendasikan** untuk memvalidasi kodenya secara lokal sebelum menjalankan `git push`.

Jalankan rangkaian perintah berikut secara berurutan di terminal lokal Anda sebelum melakukan push:

### 1. Jalankan Linter & Pemformat Kode (Pint)
Merapikan dan memformat kode Anda secara otomatis sesuai standar PSR-12:
```bash
# Melakukan perbaikan otomatis (direkomendasikan)
vendor/bin/pint

# Hanya memeriksa tanpa mengubah (opsional, untuk memastikan)
vendor/bin/pint --test
```

### 2. Jalankan Analisis Kode Statis (PHPStan/Larastan)
Mendeteksi potensi kesalahan pengetikan, parameter salah, atau bug tipe data di level strictness tinggi (Level 5):
```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

### 3. Jalankan Seluruh Test Suite (PHPUnit)
Memastikan tidak ada fitur yang rusak (*zero-regression*) akibat perubahan kode baru Anda:
```bash
# Menggunakan test runner artisan
php artisan test

# ATAU menjalankan PHPUnit secara langsung
vendor/bin/phpunit
```

> [!IMPORTANT]
> **Checklist Utama Sebelum Push:**
> 1. **Pint Pass:** `vendor/bin/pint --test` menghasilkan `Pint passed`.
> 2. **PHPStan Pass:** Analisis statis mengembalikan `[OK] No errors`.
> 3. **Tests Pass:** Seluruh pengujian (Feature & Unit) berwarna hijau (`OK / 100% passed`).
> 4. **Clear Configuration Cache:** Pastikan Anda telah menjalankan `php artisan config:clear` agar variabel lingkungan pengujian di `.env.testing` atau `phpunit.xml` dibaca secara dinamis dan tidak macet di cache konfigurasi.

---

## Status Proyek

✅ **Sesi 1 selesai** — fondasi: Laravel 13 + PostgreSQL, struktur direktori, API Response standard, tooling (Pint/Larastan), migrasi awal, seeder, endpoint `GET /api/v1/health`.

✅ **Sesi 2 selesai** — Auth: Passport (Password Grant) untuk API + login session Filament `/admin`, RBAC (spatie) dengan role `super-admin`/`admin`/`staff` & `super-admin` bypass. Endpoint login/refresh/logout/me.

✅ **Sesi 3 selesai** — User & Role management: CRUD user/role di Filament, assign role/permission, policy RBAC, endpoint profil API (`PUT /auth/me`, `POST /auth/change-password`), dan test otorisasi.

✅ **Sesi 4 selesai** — Data Master CRUD generik: API + back-office `Category`, filter/sort/pagination, policy RBAC, dokumentasi pola data master, dan test.

🟨 **Sesi 5 berjalan** — Polish: dashboard/branding back-office, dokumentasi cara menjalankan, quality gate, dan nice-to-have terpilih. Lihat [docs/WORK_SESSIONS.md](docs/WORK_SESSIONS.md).

> Catatan dev lokal: untuk produksi gunakan user PostgreSQL khusus least-privilege (bukan `postgres` superuser).
