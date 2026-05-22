# Laravel Starter — API Backend & Back-office

Starter project berbasis **Laravel + PostgreSQL** yang dirancang sebagai fondasi untuk dua kebutuhan sekaligus:

1. **API backend** untuk aplikasi mobile **Flutter** (token-based auth via OAuth2).
2. **Back-office web UI** untuk manajemen internal — user management, role & permission, dan data master (session-based auth via Filament).

Tujuannya adalah menyediakan kerangka kerja yang bersih, konsisten, dan siap dikembangkan, tanpa over-engineering, sehingga tim bisa langsung fokus membangun fitur bisnis.

---

## Stack Teknologi

| Komponen | Pilihan | Versi yang Direkomendasikan |
|---|---|---|
| Framework | Laravel | `12.x` (verifikasi `13.x` jika sudah stable saat implementasi ⚠️) |
| Bahasa | PHP | `8.3+` (8.4 didukung) |
| Database | PostgreSQL | `16` atau `17` |
| API Auth | Laravel Passport | `12.x` (OAuth2 server) |
| Back-office UI | Filament | `3.x` (verifikasi `4.x` jika sudah stable ⚠️) |
| RBAC | spatie/laravel-permission | `6.x` |
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

## Cara Menjalankan (placeholder — diisi saat implementasi)

```bash
# 1. Clone & install dependency
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Konfigurasi koneksi PostgreSQL di .env, lalu migrasi + seed
php artisan migrate --seed

# 4. Setup Passport (generate encryption keys & client)
php artisan passport:install

# 5. Build assets & jalankan
npm run build
php artisan serve
```

> Bagian ini akan dilengkapi dengan detail konkret (URL back-office, kredensial seeder default, dll.) di akhir Sesi 1–2.

---

## Status Proyek

🚧 **Tahap planning.** Belum ada kode implementasi. Lihat [docs/WORK_SESSIONS.md](docs/WORK_SESSIONS.md) untuk roadmap implementasi bertahap.
