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
- **Separation of concerns** — Controller tipis, logika bisnis di **Service layer**, akses data via **Eloquent** (tanpa Repository pattern — lihat [architecture.md](docs/architecture.md)).
- **Konsistensi kontrak** — Semua response API mengikuti satu format JSON standar (envelope + error format konsisten).
- **Single source of truth untuk authorization** — Satu sistem RBAC (spatie) dipakai bersama oleh API guard dan web guard.
- **Hindari over-engineering** — Tidak ada abstraksi spekulatif. Tambahkan layer hanya ketika kebutuhan nyata muncul.
- **Convention over configuration** — Ikuti konvensi Laravel; jangan melawan framework.

---

## Daftar Isi Dokumentasi

| Dokumen | Isi |
|---|---|
| [docs/features.md](docs/features.md) | Katalog dan spesifikasi seluruh fitur sistem terimplementasi secara komprehensif |
| [docs/architecture.md](docs/architecture.md) | Arsitektur sistem, layering, struktur direktori, strategi auth, dan praktik integrasi Flutter ↔ Laravel |
| [docs/data_master_pattern.md](docs/data_master_pattern.md) | Pola standar dan petunjuk replikasi CRUD data master baru berdasarkan contoh `Category` |
| [docs/deployment.md](docs/deployment.md) | Panduan go-live produksi lengkap (Nginx block, Supervisor worker, dan migrasi) |
| [docs/erd/database_erd.md](docs/erd/database_erd.md) | Visualisasi skema database interaktif (Mermaid ERD) dan kamus data (*data dictionary*) |
| [CLAUDE.md](CLAUDE.md) | Panduan perintah cepat (linting, test, static analysis) untuk AI agent dan kolaborator |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Aturan kontribusi repositori, manajemen Git Flow, konvensi Conventional Commits, dan quality gate |
| [SECURITY.md](SECURITY.md) | Kebijakan pelaporan privat celah keamanan sensitif beserta SLA respons |

---

## Panduan Memulai Cepat (Onboarding Guide)

Jika Anda baru saja melakukan *clone* pada project ini, ikuti langkah-langkah di bawah ini untuk menyalakan lingkungan pengembangan lokal Anda kurang dari 5 menit.

---

### Opsi A: Instalasi Menggunakan Tooling Lokal (Makefile / Composer)

> **Prasyarat**: Anda wajib memiliki **PHP 8.3+**, **Composer 2**, **PostgreSQL 14+**, dan **Node 20+** terpasang di sistem operasi lokal Anda.
> *Catatan Windows*: Jika PATH default Anda masih menggunakan PHP lama, Anda dapat mengarahkan path php secara manual atau menggunakan binary PHP 8.3 spesifik (mis. `C:\php8.3.6\php.exe`).

```bash
# Langkah 1: Jalankan setup otomatis dependensi & aset
make setup

# Langkah 2: Buat database di PostgreSQL lokal Anda
# (Isi kredensial PostgreSQL di berkas .env Anda terlebih dahulu)
# DB_CONNECTION=pgsql
# DB_DATABASE=laravel_starter
# DB_USERNAME=<user>
# DB_PASSWORD=<password>

# Langkah 3: Jalankan migrasi, seeder default, dan kunci Passport
make fresh

# Langkah 4: Jalankan server dev lokal (Concurrently: serve, queue, logs, vite)
make dev
```

---

### Opsi B: Instalasi Menggunakan Docker (Laravel Sail)

Bagi Anda yang ingin menginisialisasi lingkungan pengembangan instan tanpa perlu memasang PHP, PostgreSQL, Node, atau Redis secara lokal di mesin Anda, Anda dapat menggunakan kontainerisasi **Laravel Sail** yang sudah terintegrasi.

> **Prasyarat**: Aplikasi **Docker Desktop** telah terpasang dan berjalan di sistem Anda.

```bash
# Langkah 1: Persiapan Environment
cp .env.example .env

# Langkah 2: Nyalakan kontainer Docker secara latar belakang (background)
# (Proses build awal image akan memakan waktu beberapa menit)
./vendor/bin/sail up -d

# Langkah 3: Unduh dependensi composer di dalam kontainer
./vendor/bin/sail composer install

# Langkah 4: Generate key aplikasi & kunci Passport
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan passport:keys

# Langkah 5: Jalankan migrasi database & seeders
./vendor/bin/sail artisan migrate:fresh --seed

# Langkah 6: Build aset frontend
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Setelah kontainer berjalan, aplikasi dapat diakses di `http://localhost`. Anda juga dapat memantau kotak masuk email pengujian (Mailpit) di `http://localhost:8025`.
Untuk menghentikan kontainer, jalankan `./vendor/bin/sail down`.

---

### ⚠️ Langkah Wajib Pasca Inisialisasi (Passport Client Setup)

Endpoint login API (`POST /api/v1/auth/login`) bergantung sepenuhnya pada Laravel Passport Password Client. Pada instalasi/clone baru, Anda **WAJIB** membuat Password Client baru dengan perintah:

```bash
# Untuk Setup Lokal:
php artisan passport:client --password

# Untuk Setup Docker Sail:
./vendor/bin/sail artisan passport:client --password
```

Setelah menjalankan perintah tersebut, Anda akan mendapatkan output **Client ID** dan **Client Secret**. Salin kedua nilai tersebut dan masukkan ke dalam variabel lingkungan berikut di file `.env`:
```env
PASSPORT_PASSWORD_CLIENT_ID=<Client-ID-Anda>
PASSPORT_PASSWORD_CLIENT_SECRET=<Client-Secret-Anda>
```
> [!WARNING]
> Jika langkah Passport Client ini terlewatkan, seluruh endpoint Login API akan mengembalikan error kegagalan autentikasi atau *internal server error*.

---

### 🇮🇩 Database Wilayah Geografis Indonesia (Opsional & Offline)

Starter ini dilengkapi data administratif Indonesia (~245.000 data parent-child). Agar proses inisialisasi lokal Anda lancar dan tidak terhambat request HTTP eksternal yang lambat, seeding dilakukan secara **offline** menggunakan JSON fixtures lokal di storage.
Jalankan perintah berikut untuk mengunduh berkas wilayah secara offline ke storage Anda sebelum melakukan seed region:

```bash
# Untuk Setup Lokal:
php artisan regions:download

# Untuk Setup Docker Sail:
./vendor/bin/sail artisan regions:download
```
*Untuk mengaktifkan seeder wilayah saat `make fresh`, ubah variabel di `.env` menjadi: `SEED_REGIONS=true`.*

---

### 🔑 Akun Default untuk Login Pengembang

Setelah database berhasil diinisialisasi, Anda dapat menggunakan kredensial bawaan (*seeder*) berikut untuk masuk ke sistem:

| Username / Email | Password | Role Akses (Spatie) | Tujuan Login |
|---|---|---|---|
| `admin@example.com` | `password` | `super-admin` | Panel Admin `/admin` & Login API |

> [!NOTE]
> Peran default `super-admin` memiliki bypass otorisasi penuh melalui `Gate::before`. Anda dapat menambahkan pengguna baru atau menetapkan peran `admin` atau `staff` lainnya secara visual langsung melalui Panel Admin Filament di menu **Users**.

---

### 🧪 Verifikasi Koneksi & Menjalankan Tes

* **Verifikasi API**: Buka `GET /api/v1/health` → harus mengembalikan envelope JSON sukses `{ "success": true, "message": "OK", ... }`.
* **Dokumentasi API**: Scramble menyediakan dokumentasi interaktif di `/docs/api` dan OpenAPI JSON di `/docs/api.json` saat berada di environment `local`.
* **Testing Database**: Pengujian secara default berjalan di database PostgreSQL terpisah bernama `laravel_starter_test`.
  * *Setup Lokal*: Buat database test terlebih dahulu di pgsql dengan `createdb laravel_starter_test`.
  * *Setup Docker Sail*: Database test sudah terbuat secara otomatis.
* **Menjalankan Tes & Linting**:
  ```bash
  # Menjalankan seluruh test suite
  make test

  # Menjalankan formater koding otomatis (Pint)
  make lint

  # Menjalankan static analysis (PHPStan/Larastan)
  make analyse

  # Menjalankan quality gate penuh sebelum melakukan git push
  make quality
  ```

> [!TIP]
> **Fallback ke SQLite In-Memory:**
> Jika Anda belum memasang PostgreSQL test atau ingin menjalankan tes secara super cepat menggunakan driver SQLite `:memory:`, Anda cukup menghapus komentar (uncomment) bagian SQLite di berkas `phpunit.xml` atau mengatur `DB_CONNECTION=sqlite` dan `DB_DATABASE=:memory:` di berkas `.env.testing`.

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

✅ **Sesi 5 selesai** — Polish & Finalisasi: Branding back-office premium, pembersihan dokumentasi usang, standardisasi quality gate, linter, dan penerbitan katalog fitur lengkap di [docs/features.md](docs/features.md).

> Catatan dev lokal: untuk produksi gunakan user PostgreSQL khusus least-privilege (bukan `postgres` superuser).
