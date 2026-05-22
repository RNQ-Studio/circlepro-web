# MODULES

Daftar modul & fitur Laravel Starter beserta prioritas. Prioritas memandu urutan implementasi di [WORK_SESSIONS.md](WORK_SESSIONS.md).

**Legenda prioritas:**
- 🟥 **Core** — fondasi, wajib ada di starter.
- 🟨 **Nice-to-have** — meningkatkan kualitas; dikerjakan setelah core stabil.
- ⬜ **Optional** — hanya jika kebutuhan muncul; jangan dibangun spekulatif.

---

## 1. Auth & Authorization 🟥

**Tujuan:** Menyediakan dua jalur auth (API token untuk Flutter, session untuk back-office) di atas satu sistem RBAC.

| Fitur | Prioritas | Catatan |
|---|---|---|
| API auth via Passport (login, refresh, logout, me) | 🟥 Core | Grant flow difinalisasi di Sesi 2 ⚠️ |
| Back-office login (session via Filament) | 🟥 Core | UI bawaan Filament |
| Pembatasan akses panel (`canAccessPanel`) | 🟥 Core | Hanya role tertentu boleh masuk `/admin` |
| Rate limiting endpoint auth | 🟥 Core | Cegah brute-force |
| Password reset (API + back-office) | 🟨 Nice-to-have | Email-based |
| Email verification | 🟨 Nice-to-have | |
| Two-factor auth (2FA) | ⬜ Optional | |
| Social login (OAuth provider) | ⬜ Optional | |

---

## 2. User Management 🟥

**Tujuan:** CRUD user lengkap di back-office + endpoint profil di API.

| Fitur | Prioritas | Catatan |
|---|---|---|
| CRUD user di back-office (Filament Resource) | 🟥 Core | List, create, edit, delete, search, filter |
| Assign role ke user (UI) | 🟥 Core | Terintegrasi spatie + Filament |
| Endpoint profil API (`GET/PUT /me`) | 🟥 Core | User mengelola profil sendiri |
| Aktivasi / deaktivasi user (status) | 🟨 Nice-to-have | Soft-disable tanpa hapus |
| Soft delete + restore | 🟨 Nice-to-have | |
| Audit/log aktivitas user | ⬜ Optional | Pertimbangkan `spatie/laravel-activitylog` |
| Avatar / foto profil | ⬜ Optional | |

---

## 3. Role & Permission Management 🟥

**Tujuan:** RBAC fleksibel berbasis `spatie/laravel-permission`, dikelola dari back-office.

| Fitur | Prioritas | Catatan |
|---|---|---|
| Model & migrasi roles/permissions (spatie) | 🟥 Core | Via publish migration spatie |
| Seeder role & permission default | 🟥 Core | mis. `super-admin`, `admin`, `staff` |
| CRUD role di back-office | 🟥 Core | Assign permission ke role |
| CRUD permission di back-office | 🟨 Nice-to-have | Sering cukup dikelola via seeder/code |
| Penegakan permission di API (Policy/middleware) | 🟥 Core | Konsistensi multi-guard ⚠️ |
| `super-admin` bypass (Gate::before) | 🟥 Core | Satu role full-access |

> ⚠️ **Multi-guard:** Pastikan permission konsisten antara guard `web` (Filament) dan `api` (Passport). Lihat ARCHITECTURE.md §5.3.

---

## 4. Data Master (struktur generik) 🟥

**Tujuan:** Pola CRUD generik yang dapat direplikasi untuk berbagai entitas data master (mis. Category, Region, Unit, Status, dll.). Bertindak sebagai **template/contoh** alih-alih entitas bisnis spesifik.

| Fitur | Prioritas | Catatan |
|---|---|---|
| Contoh entitas master (mis. `Category`) sebagai template | 🟥 Core | Lengkap: migrasi, model, service (jika perlu), API, Filament Resource |
| API CRUD generik (list+filter, show, store, update, destroy) | 🟥 Core | Pakai spatie/laravel-query-builder untuk filter/sort |
| Back-office CRUD (Filament Resource) | 🟥 Core | Mirror dari API |
| Pagination + search + sort konsisten | 🟥 Core | Selaras API Response standard |
| Soft delete pada master data | 🟨 Nice-to-have | |
| Import/export (CSV/Excel) | ⬜ Optional | Filament punya action import/export |
| Relasi antar-master (parent/child) | ⬜ Optional | Contohkan satu relasi bila perlu |

**Deliverable kunci:** dokumentasi pola "cara menambah entitas data master baru" sehingga sesi/dev berikutnya bisa menyalin pola dengan cepat.

---

## 5. API Response Standard 🟥

**Tujuan:** Format JSON konsisten untuk semua endpoint (lihat ARCHITECTURE.md §7).

| Fitur | Prioritas | Catatan |
|---|---|---|
| Helper `ApiResponse` (success/error builder) | 🟥 Core | `app/Support/ApiResponse.php` |
| Exception handler terpusat → JSON konsisten | 🟥 Core | 401/403/404/422/500 |
| Format pagination di `meta` | 🟥 Core | |
| Kode error internal (machine-readable) | 🟨 Nice-to-have | mis. `AUTH_INVALID_CREDENTIALS` |
| Force-JSON middleware untuk route API | 🟥 Core | Hindari response HTML pada error |

---

## 6. Back-office UI (Filament) 🟥

**Tujuan:** Panel admin internal yang cepat dibangun di atas Filament.

| Fitur | Prioritas | Catatan |
|---|---|---|
| Setup Filament panel `/admin` | 🟥 Core | Sesi 1/2 |
| Integrasi RBAC dengan Filament resource policies | 🟥 Core | `canViewAny`, dll. baca dari spatie |
| Dashboard sederhana (widget statistik) | 🟨 Nice-to-have | Jumlah user, dll. |
| Branding (logo, warna, nama app) | 🟨 Nice-to-have | |
| Notifikasi & activity feed | ⬜ Optional | |
| Global search di panel | ⬜ Optional | |

---

## 7. Cross-cutting & Tooling 🟨

| Fitur | Prioritas | Catatan |
|---|---|---|
| Laravel Pint (code style) | 🟨 Nice-to-have | Konfigurasi di Sesi 1 |
| Larastan (static analysis) | 🟨 Nice-to-have | |
| Database factories & seeders | 🟥 Core | Untuk dev & testing |
| Feature tests (API auth, CRUD) | 🟨 Nice-to-have | Minimal smoke test per modul |
| `.env.example` lengkap | 🟥 Core | Termasuk konfigurasi PostgreSQL & Passport |
| CI (lint + test) | ⬜ Optional | GitHub Actions bila repo di GitHub |
| API docs (Scribe/OpenAPI) | ⬜ Optional | Membantu tim Flutter |

---

## Ringkasan Urutan Implementasi (high-level)

1. **Fondasi** → Setup, struktur direktori, migrasi awal, API Response standard. *(Core)*
2. **Auth** → Passport API + session back-office + RBAC. *(Core)*
3. **User & Role management** → API + Filament. *(Core)*
4. **Data Master generik** → API + Filament + dokumentasi pola. *(Core)*
5. **Polish & tooling** → tests, lint, dashboard, nice-to-have. *(Nice-to-have)*

Detail per-sesi ada di [WORK_SESSIONS.md](WORK_SESSIONS.md).
