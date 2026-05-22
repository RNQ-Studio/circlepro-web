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
| [CONTRIBUTING.md](CONTRIBUTING.md) | Panduan branch, konvensi commit (Conventional Commits), quality gate, & push |

---

## Cara Menjalankan

> Prasyarat: **PHP 8.3+**, **Composer 2**, **PostgreSQL 14+**, dan **Node 20+**.

```bash
# 1. Install dependency
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Isi kredensial PostgreSQL di .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD),
#    buat database-nya, lalu migrasi + seed
php artisan migrate --seed

# 4. Build assets & jalankan
npm run build
php artisan serve
```

Cek koneksi: buka `GET /api/v1/health` → harus mengembalikan envelope JSON `{ "success": true, ... }`.

**Akun admin default (seeder):** `admin@example.com` / `password` (role `super-admin`). Login back-office di `/admin`.

**Setup Passport (sekali, atau di clone/CI baru):**

```bash
php artisan passport:keys                       # generate storage/oauth-*.key (gitignored)
php artisan passport:client --password          # isi PASSPORT_PASSWORD_CLIENT_ID/SECRET di .env
```

**Endpoint Auth API (Flutter):** `POST /api/v1/auth/login`, `POST /api/v1/auth/refresh`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`. Lihat [docs/ARCHITECTURE.md §5](docs/ARCHITECTURE.md).

**Testing:** test berjalan di database PostgreSQL terpisah `laravel_starter_test` (lihat `phpunit.xml`). Buat database tersebut sekali, lalu:

```bash
composer test       # php artisan test
composer lint       # pint (format)
composer analyse    # phpstan (Larastan), memory limit 1G
```


---

## Status Proyek

✅ **Sesi 1 selesai** — fondasi: Laravel 13 + PostgreSQL, struktur direktori, API Response standard, tooling (Pint/Larastan), migrasi awal, seeder, endpoint `GET /api/v1/health`.

✅ **Sesi 2 selesai** — Auth: Passport (Password Grant) untuk API + login session Filament `/admin`, RBAC (spatie) dengan role `super-admin`/`admin`/`staff` & `super-admin` bypass. Endpoint login/refresh/logout/me.

✅ **Sesi 3 selesai** — User & Role management: CRUD user/role di Filament, assign role/permission, policy RBAC, endpoint profil API (`PUT /auth/me`, `POST /auth/change-password`), dan test otorisasi.

⏭️ **Berikutnya: Sesi 4** — Data Master CRUD generik (API + back-office) dengan contoh `Category`. Lihat [docs/WORK_SESSIONS.md](docs/WORK_SESSIONS.md).

> Catatan dev lokal: untuk produksi gunakan user PostgreSQL khusus least-privilege (bukan `postgres` superuser).
