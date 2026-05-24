# Action Plan & Roadmap Perbaikan

Dokumen ini mengompilasikan seluruh temuan hasil audit menjadi peta jalan (**roadmap**) perbaikan konkret yang terbagi menjadi beberapa fase pengerjaan (sprint) yang dapat ditindaklanjuti secara taktis.

---

## A. Temuan Kritis (Selesaikan Sebelum Digunakan)

| # | Temuan | File / Lokasi | Dampak | Estimasi Effort |
|---|--------|---------------|--------|-----------------|
| 1 | 🔥 **Masking Error Passport proxy di AuthService** | [AuthService.php:L144](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/app/Services/Auth/AuthService.php#L144) | Developer kesulitan menganalisis error Passport (dianggap password salah, padahal miskonfigurasi key/secret). | **S** (1-2 Jam) |
| 2 | 🔥 **Ketiadaan Enforcement HTTPS di Produksi** | [AppServiceProvider.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/app/Providers/AppServiceProvider.php) | Token API rentan disadap saat transit data di environment production jika server melayani rute HTTP biasa. | **S** (30 Menit) |

---

## B. Perbaikan Penting (Sprint 1 — 1-2 Minggu)

| # | Item Perbaikan | Alasan Prioritas | Estimasi Effort |
|---|----------------|------------------|-----------------|
| 1 | **Peningkatan Wrapper API Response** | Menghapus duplikasi kode pembentuk metadata pagination di setiap Controller index (dukung `AnonymousResourceCollection`). | **S** (2-4 Jam) |
| 2 | **Integrasi Docker / Laravel Sail** | Menyediakan environment Docker pgsql & redis secara instan, mempermudah onboarding developer baru tanpa instalasi manual. | **M** (1-2 Hari) |
| 3 | **Pembuatan Berkas Context AI (`CLAUDE.md`)** | Memberikan panduan cepat bagi AI Agent (Claude/Cursor) tentang perintah run test, lint, format koding, dan arsitektur proyek. | **S** (2 Jam) |
| 4 | **Auto-create database testing** | Menjamin test suite berjalan hijau seketika setelah script `composer setup` dijalankan tanpa perlu membuat db test manual. | **S** (2 Jam) |

---

## C. Peningkatan (Sprint 2 — 2-4 Minggu)

| # | Item Peningkatan | Manfaat Utama | Estimasi Effort |
|---|-------------------|---------------|-----------------|
| 1 | **UI/UX Branding Kustom Filament** | Panel admin terlihat sangat premium dan tidak generik (integrasi logo kustom, dark-mode, dan primary color indigo). | **S** (1 Hari) |
| 2 | **Fixture Regional Mini untuk Testing** | Test seeder wilayah berjalan di bawah 1 detik di server CI (GitHub Actions) tanpa bergantung pada unduhan file JSON eksternal. | **M** (2-3 Hari) |
| 3 | **Model Factories Lengkap** | Mempermudah pembuatan data dummy deklaratif untuk seluruh unit testing di masa depan. | **M** (2 Hari) |
| 4 | **Dukungan Standard RFC 7807** | Format error API mengikuti standar industri global, memudahkan auto-parsing pada pustaka Flutter klien. | **M** (2 Hari) |

---

## D. Nice to Have (Backlog)

1. **Integrasi 2FA di Filament Admin**: Opsi keamanan lapis kedua menggunakan Google Authenticator khusus bagi role sensitif (`super-admin`).
2. **Global Search Lintas-Model Filament**: Input pencarian global di bagian header admin panel untuk mempercepat temuan record user, roles, dan categories.
3. **Audit Trail / Activity Log**: Mencatat rekaman perubahan data (siapa mengubah apa dan kapan) memanfaatkan `spatie/laravel-activitylog`.
4. **Export & Import Data**: Action cepat pada tabel Filament untuk mengekspor data ke berkas Excel/CSV atau mengimpor data secara massal.

---

## E. Berkas yang Harus Dibuat (Beserta Outline)

### 1. `CLAUDE.md` (Root Folder)
- **Outline**:
  - **Project Metadata**: Nama, Framework (Laravel 13), Engine (Passport, Filament).
  - **Build & Test Commands**: Perintah running test murni (`php artisan test`), running Pint formatting (`vendor/bin/pint`), running PHPStan (`phpstan analyse`).
  - **Coding Conventions**: Penggunaan strict types, strict model attributes, penolakan pola Repository global, modularisasi form/table Filament schemas.

### 2. `docs/deployment.md`
- **Outline**:
  - **Server Requirements**: PHP 8.3+, PostgreSQL 16+.
  - **Env Configuration**: Panduan inisialisasi Passport Private/Public Keys menggunakan environment variables (`PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`) untuk server serverless.
  - **Firebase Credentials Configuration**: Cara mounting berkas `firebase-service-account.json` secara aman.
  - **App Build Commands**: Urutan run di pipeline deployment produksi.

### 3. `tests/Fixtures/regions/` (Folder baru)
- **Outline**:
  - `countries.json`: Dummy data berisi 2-3 negara (termasuk Indonesia ID dan US).
  - `provinces.json`: Dummy data berisi 2 provinsi Indonesia.
  - `regencies.json` / `districts.json` / `villages.json`: Berkas hierarki mini untuk pengujian integrasi regional yang cepat.

---

## F. Estimasi Total Effort

Untuk membawa proyek **Laravel Starter** ini dari status saat ini menjadi status **100% Production-Ready Starter** yang super aman, rapi, terdokumentasi lengkap, dan AI-friendly, dibutuhkan total waktu pengerjaan:

- **Sprint 1 (Fondasi & Kritis)**: ± 3 Hari Kerja
- **Sprint 2 (Peningkatan & DX)**: ± 6 Hari Kerja
- **TOTAL ESTIMASI EFFORT**: **± 9 Hari Kerja (1.5 - 2 Minggu)**
