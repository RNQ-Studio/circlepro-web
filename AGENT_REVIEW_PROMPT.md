# 🤖 Agent Task: Deep Review — Laravel Starter Project

## Identitas Task
- **Tipe Task**: Analisa & Audit Mendalam (Deep Review)
- **Target**: Laravel Starter Project (Livewire + REST API + Filament Admin Panel)
- **Use Case Target**: SaaS / Multi-tenant & Mobile Backend (API)
- **Output**: Dokumen review lengkap di folder `docs/review/`
- **Bahasa Output**: Bahasa Indonesia
- **Compatible dengan**: Claude, Gemini, GPT (tidak ada tool/MCP khusus diperlukan)

---

## 🎯 Misi Utama

Kamu adalah **Senior Laravel Architect** sekaligus **AI Agent Consultant**. Tugasmu adalah melakukan audit mendalam terhadap Laravel starter project ini dan menghasilkan serangkaian dokumen review terstruktur di folder `docs/review/`.

Tujuan audit:
1. Menilai apakah project ini **siap digunakan sebagai starter project** untuk project baru
2. Menilai apakah project ini **AI Agent Friendly** (mudah dipahami, dimodifikasi, dan dikembangkan oleh AI Agent)
3. Menilai apakah sudah mengikuti **best practice Laravel modern**
4. Menilai kelengkapan **dokumentasi dan template dokumen**
5. Menilai kelengkapan **fitur & fungsi generic** yang umum dibutuhkan

---

## 📋 Langkah-langkah Eksekusi (Ikuti Urutan Ini)

### LANGKAH 1 — Persiapan Folder Output

Sebelum mulai analisa apapun, buat struktur folder berikut jika belum ada:

```
docs/
└── review/
    ├── 00_SUMMARY.md
    ├── 01_starter_readiness.md
    ├── 02_ai_agent_friendliness.md
    ├── 03_best_practice.md
    ├── 04_documentation_completeness.md
    ├── 05_feature_completeness.md
    ├── 06_priority_areas.md
    └── 07_action_plan.md
```

Jika folder `docs/` belum ada, buat dari root project. Konfirmasi pembuatan folder sebelum melanjutkan.

---

### LANGKAH 2 — Mapping Struktur Project

Lakukan eksplorasi menyeluruh terhadap struktur project. Petakan:

- Seluruh struktur direktori (minimal 3 level dalam)
- Semua file konfigurasi penting (`composer.json`, `package.json`, `.env.example`, `config/`, dll)
- Semua route yang terdaftar (`routes/web.php`, `routes/api.php`, `routes/api/v*/`)
- Semua Model beserta relasi dan trait yang digunakan
- Semua Service, Repository, Action class jika ada
- Semua Livewire Component
- Semua Filament Resource, Panel, Widget
- Semua Middleware
- Semua test file
- File `README.md` dan dokumentasi lain yang sudah ada

Tuliskan hasil mapping ini sebagai bagian dari `00_SUMMARY.md`.

---

### LANGKAH 3 — Eksekusi 7 Dokumen Review

Buat setiap dokumen berikut dengan format yang ditentukan.

---

#### 📄 `00_SUMMARY.md` — Ringkasan Eksekutif

```markdown
# Ringkasan Eksekutif Review

## Informasi Project
- Nama Project:
- Laravel Version:
- PHP Version:
- Tanggal Review:
- Direview oleh: [nama AI Agent]

## Scorecard Keseluruhan

| Kategori                      | Skor (1-10) | Status         |
|-------------------------------|-------------|----------------|
| Kesiapan sebagai Starter      |             |                |
| AI Agent Friendliness         |             |                |
| Best Practice Laravel         |             |                |
| Kelengkapan Dokumentasi       |             |                |
| Kelengkapan Fitur Generic     |             |                |
| **TOTAL RATA-RATA**           |             |                |

## Temuan Kritis (Wajib Diperbaiki)
[Daftar 3-5 temuan paling kritis]

## Kelebihan Menonjol
[Daftar hal-hal yang sudah sangat baik]

## Rekomendasi Utama
[Top 5 rekomendasi prioritas]

## Struktur Project (Hasil Mapping)
[Tampilkan struktur direktori hasil eksplorasi]
```

---

#### 📄 `01_starter_readiness.md` — Kesiapan sebagai Starter Project

Nilai dan dokumentasikan setiap poin berikut:

**A. Setup & Instalasi**
- [ ] Apakah `.env.example` lengkap dan terdokumentasi dengan baik?
- [ ] Apakah ada `README.md` dengan instruksi instalasi yang jelas?
- [ ] Apakah ada `Makefile` atau script setup otomatis?
- [ ] Apakah `composer.json` dan `package.json` bersih (tidak ada package tidak terpakai)?
- [ ] Apakah ada konfigurasi Docker/Sail untuk development?

**B. Database & Migrations**
- [ ] Apakah semua migration sudah terurut dan konsisten?
- [ ] Apakah ada Seeder yang berguna untuk development?
- [ ] Apakah ada Factory untuk semua Model utama?
- [ ] Apakah migration menggunakan tipe kolom yang tepat?

**C. Konfigurasi Awal**
- [ ] Apakah ada konfigurasi timezone yang benar?
- [ ] Apakah ada konfigurasi locale/bahasa?
- [ ] Apakah ada konfigurasi cors yang benar untuk API?
- [ ] Apakah ada konfigurasi cache, queue, session yang siap pakai?

**D. Keamanan Dasar**
- [ ] Apakah `.gitignore` sudah mencakup semua file sensitif?
- [ ] Apakah tidak ada credential hardcoded di codebase?
- [ ] Apakah ada rate limiting di route API?
- [ ] Apakah HTTPS enforced di production config?

Untuk setiap poin: tulis **status** (✅ Ada / ⚠️ Sebagian / ❌ Tidak Ada), **temuan spesifik** (file dan baris jika relevan), dan **rekomendasi**.

Sertakan **Skor Akhir: X/10** dengan justifikasi.

---

#### 📄 `02_ai_agent_friendliness.md` — AI Agent Friendliness

Nilai seberapa mudah project ini dipahami, dimodifikasi, dan dikembangkan oleh AI Agent (Claude, Gemini, GPT, Cursor, dll).

**A. Keterbacaan Kode (Code Readability)**
- [ ] Apakah penamaan class, method, variabel konsisten dan deskriptif?
- [ ] Apakah ada komentar/docblock pada method-method kompleks?
- [ ] Apakah struktur folder logis dan mudah diprediksi?
- [ ] Apakah tidak ada "magic" yang tidak terdokumentasi?

**B. Dokumentasi untuk AI Context**
- [ ] Apakah ada file `CLAUDE.md` / `AGENTS.md` / `GEMINI.md` (atau equivalent)?
- [ ] Apakah ada file `ARCHITECTURE.md` yang menjelaskan desain sistem?
- [ ] Apakah ada komentar pada setiap route group yang menjelaskan tujuannya?
- [ ] Apakah ada ERD atau dokumentasi database schema?
- [ ] Apakah ada `CONVENTIONS.md` yang menjelaskan konvensi koding project?

**C. Predictability & Consistency**
- [ ] Apakah semua API response mengikuti format yang sama?
- [ ] Apakah semua error handling konsisten?
- [ ] Apakah naming convention konsisten (snake_case, camelCase, PascalCase di tempat yang tepat)?
- [ ] Apakah struktur Filament Resource konsisten satu sama lain?
- [ ] Apakah semua Livewire component mengikuti pola yang sama?

**D. Kemudahan Generate Kode Baru**
- [ ] Apakah ada stub/template untuk membuat Resource, Component, Service baru?
- [ ] Apakah ada contoh implementasi lengkap (CRUD) yang bisa dijadikan referensi?
- [ ] Apakah ada script artisan custom yang membantu generate boilerplate?
- [ ] Apakah dependency antar komponen minimal dan jelas?

**E. Testing sebagai Safety Net untuk AI**
- [ ] Apakah ada test yang cukup sehingga AI bisa bermodifikasi dengan aman?
- [ ] Apakah ada test untuk happy path dan edge case?

Sertakan **Skor Akhir: X/10** dan rekomendasi spesifik file apa yang perlu dibuat/diubah.

---

#### 📄 `03_best_practice.md` — Laravel Best Practice

Audit mendalam per area prioritas:

**A. Authentication & Authorization (PRIORITAS TINGGI)**
- [ ] Apakah menggunakan Laravel Sanctum dengan benar untuk API?
- [ ] Apakah token expiry dikonfigurasi?
- [ ] Apakah ada implementasi Refresh Token?
- [ ] Apakah ada sistem Role & Permission? (Spatie? Custom?)
- [ ] Apakah Permission di-cache dengan benar?
- [ ] Apakah Gate dan Policy digunakan konsisten?
- [ ] Apakah ada pemisahan yang jelas antara auth web (Livewire) dan auth API (Sanctum)?
- [ ] Apakah ada fitur: email verification, password reset, 2FA?
- Temuan spesifik pada file: `config/sanctum.php`, `app/Http/Middleware/`, `app/Policies/`

**B. Multi-tenancy (PRIORITAS TINGGI)**
- [ ] Apakah menggunakan package multi-tenancy? (Stancl/Tenancy? Custom?)
- [ ] Apakah ada isolasi data antar tenant yang kuat?
- [ ] Apakah ada global scope untuk filter data per tenant secara otomatis?
- [ ] Apakah database per-tenant atau shared database dengan tenant_id?
- [ ] Apakah ada proteksi cross-tenant data leakage?
- [ ] Apakah ada tenant onboarding flow?
- [ ] Apakah Filament panel mendukung multi-tenancy?
- Temuan spesifik pada file terkait tenancy

**C. API Versioning & Response Structure (PRIORITAS TINGGI)**
- [ ] Apakah ada versioning pada route API? (`/api/v1/`, `/api/v2/`)
- [ ] Apakah menggunakan API Resource (JsonResource) untuk semua response?
- [ ] Apakah format response konsisten? (data, message, status, meta, errors)
- [ ] Apakah ada wrapper response standar?
- [ ] Apakah pagination mengikuti format standar (JSON:API atau custom konsisten)?
- [ ] Apakah error response mengikuti format RFC 7807 atau format konsisten lainnya?
- [ ] Apakah ada dokumentasi API? (Swagger/OpenAPI/Scribe?)
- Tampilkan contoh format response yang ditemukan

**D. Filament Panel & Resource (PRIORITAS TINGGI)**
- [ ] Apakah Filament sudah dikonfigurasi dengan benar?
- [ ] Apakah ada Resource untuk semua Model utama?
- [ ] Apakah ada Custom Page jika diperlukan?
- [ ] Apakah ada Widget di Dashboard?
- [ ] Apakah Shield (Filament Shield) atau sistem permission Filament sudah dikonfigurasi?
- [ ] Apakah branding Filament (logo, warna) sudah dikustomisasi?
- [ ] Apakah ada pemisahan panel untuk Super Admin vs Tenant Admin?
- Temuan spesifik per Resource yang ditemukan

**E. Testing Setup (PRIORITAS TINGGI)**
- [ ] Apakah menggunakan Pest atau PHPUnit?
- [ ] Apakah ada Feature Test untuk semua endpoint API?
- [ ] Apakah ada Unit Test untuk Service/Action class?
- [ ] Apakah ada test untuk Livewire component?
- [ ] Apakah ada database testing strategy? (RefreshDatabase? Transactions?)
- [ ] Apakah ada Factory untuk semua Model?
- [ ] Apakah CI akan menjalankan test otomatis?
- Hitung coverage estimate berdasarkan jumlah test vs jumlah endpoint/komponen

**F. Code Architecture**
- [ ] Apakah ada pemisahan concern yang jelas? (Controller tipis, logika di Service/Action)
- [ ] Apakah menggunakan Repository Pattern? Jika ya, konsistenkah?
- [ ] Apakah ada Form Request untuk semua validasi input?
- [ ] Apakah Event & Listener digunakan untuk side effects?
- [ ] Apakah Job/Queue digunakan untuk proses berat?

Untuk setiap sub-area: tulis temuan, contoh kode bermasalah (jika ada), dan rekomendasi perbaikan spesifik.

Sertakan **Skor Akhir: X/10** per area dan overall.

---

#### 📄 `04_documentation_completeness.md` — Kelengkapan Dokumentasi

Audit semua dokumentasi yang ada dan yang seharusnya ada:

**A. Dokumentasi yang Diperiksa**

Periksa keberadaan dan kualitas file-file berikut:

| Dokumen | Ada? | Kualitas (1-5) | Catatan |
|---------|------|----------------|---------|
| `README.md` | | | |
| `CONTRIBUTING.md` | | | |
| `CHANGELOG.md` | | | |
| `SECURITY.md` | | | |
| `LICENSE` | | | |
| `ARCHITECTURE.md` atau `docs/architecture.md` | | | |
| `CLAUDE.md` / `AGENTS.md` (AI context file) | | | |
| `CONVENTIONS.md` | | | |
| `docs/api/` (dokumentasi API) | | | |
| `docs/erd/` atau ERD diagram | | | |
| `docs/deployment.md` | | | |
| `docs/environment.md` | | | |
| `.env.example` (terdokumentasi) | | | |

**B. Template Dokumen untuk Project Baru**

Apakah tersedia template untuk:
- [ ] Template issue/bug report
- [ ] Template feature request
- [ ] Template pull request
- [ ] Template dokumen spesifikasi fitur
- [ ] Template API endpoint documentation

**C. Komentar dalam Kode**
- Nilai kualitas komentar di kode (berlebihan? kurang? tepat sasaran?)
- Apakah ada docblock pada class dan method penting?

**D. Rekomendasi Dokumen yang Harus Dibuat**

Buatkan daftar prioritas dokumen yang perlu dibuat, urut dari paling penting, beserta outline singkatnya.

Sertakan **Skor Akhir: X/10**.

---

#### 📄 `05_feature_completeness.md` — Kelengkapan Fitur Generic

Audit apakah fitur-fitur berikut sudah tersedia, siap pakai, atau perlu dibangun dari nol:

**A. Autentikasi & User Management**
| Fitur | Status | Catatan |
|-------|--------|---------|
| Register & Login | | |
| Email Verification | | |
| Password Reset | | |
| Ubah Password | | |
| Ubah Profile | | |
| Upload Avatar | | |
| Two-Factor Authentication (2FA) | | |
| Social Login (Google, dll) | | |
| Remember Me / Session Management | | |
| Logout dari semua device | | |

**B. Multi-tenancy & Subscription**
| Fitur | Status | Catatan |
|-------|--------|---------|
| Tenant Registration / Onboarding | | |
| Tenant Settings | | |
| Subscription / Plan management | | |
| Billing integration (Midtrans/Stripe) | | |
| Usage limits per plan | | |
| Tenant user invitation | | |

**C. Role & Permission**
| Fitur | Status | Catatan |
|-------|--------|---------|
| Role CRUD | | |
| Permission CRUD | | |
| Assign role ke user | | |
| Permission per route/menu | | |
| Super admin bypass | | |

**D. API untuk Mobile**
| Fitur | Status | Catatan |
|-------|--------|---------|
| Login API (Sanctum token) | | |
| Refresh token / token expiry | | |
| Logout API | | |
| Push notification setup | | |
| File upload via API | | |
| Pagination standar | | |
| API rate limiting | | |
| API response format konsisten | | |

**E. Filament Admin**
| Fitur | Status | Catatan |
|-------|--------|---------|
| Dashboard dengan statistik | | |
| User management | | |
| Role & Permission management | | |
| Settings / Konfigurasi app | | |
| Activity log | | |
| Media/file manager | | |
| Notification center | | |

**F. Utilitas & Helper**
| Fitur | Status | Catatan |
|-------|--------|---------|
| Logging (structured) | | |
| Activity Log (Spatie) | | |
| Media Library (Spatie) | | |
| Notifikasi (email, database, broadcast) | | |
| Export (Excel/PDF) | | |
| Import data | | |
| Soft Delete pada Model utama | | |
| Global Search | | |

Status: ✅ Lengkap / ⚠️ Sebagian / ❌ Belum Ada / 🔲 Tidak Relevan

Sertakan **Skor Akhir: X/10** dan daftar fitur yang paling mendesak untuk ditambahkan.

---

#### 📄 `06_priority_areas.md` — Deep Dive Area Prioritas

Dokumen ini berisi analisa sangat mendalam untuk 5 area prioritas. Untuk setiap area:
1. Tampilkan kode/konfigurasi yang ditemukan (snippet relevan)
2. Identifikasi masalah spesifik dengan referensi file dan baris
3. Berikan contoh kode perbaikan yang konkret
4. Beri estimasi effort perbaikan (S/M/L/XL)

Area yang dianalisa:
- **Auth & Authorization** — lihat panduan di `03_best_practice.md` bagian A
- **Multi-tenancy** — lihat panduan di `03_best_practice.md` bagian B
- **API Versioning & Response** — lihat panduan di `03_best_practice.md` bagian C
- **Filament Panel** — lihat panduan di `03_best_practice.md` bagian D
- **Testing Setup** — lihat panduan di `03_best_practice.md` bagian E

---

#### 📄 `07_action_plan.md` — Action Plan & Roadmap Perbaikan

Kompilasi semua temuan menjadi action plan yang actionable:

**A. Temuan Kritis (Selesaikan Sebelum Digunakan)**

| # | Temuan | File/Lokasi | Dampak | Estimasi Effort |
|---|--------|-------------|--------|----------------|
| 1 | | | | |

**B. Perbaikan Penting (Sprint 1 — 1-2 Minggu)**

| # | Item | Alasan Prioritas | Estimasi Effort |
|---|------|-----------------|----------------|
| 1 | | | |

**C. Peningkatan (Sprint 2 — 2-4 Minggu)**

| # | Item | Manfaat | Estimasi Effort |
|---|------|---------|----------------|
| 1 | | | |

**D. Nice to Have (Backlog)**

Daftar fitur/perbaikan yang bagus tapi tidak mendesak.

**E. File yang Harus Dibuat (dengan outline)**

Untuk setiap file dokumen yang direkomendasikan, berikan outline lengkap isinya agar developer atau AI Agent berikutnya bisa langsung membuatnya.

**F. Estimasi Total Effort**

Berikan estimasi total waktu untuk membawa project ini ke kondisi "production-ready starter".

---

## 📌 Aturan Penulisan Output

1. **Setiap dokumen harus berdiri sendiri** — bisa dibaca tanpa membaca dokumen lain
2. **Gunakan referensi file spesifik** — selalu sebut nama file dan baris ketika membahas temuan
3. **Berikan contoh kode konkret** — jangan hanya teori, tunjukkan kode yang benar
4. **Gunakan emoji status**: ✅ Baik / ⚠️ Perlu Perhatian / ❌ Masalah / 💡 Rekomendasi / 🔥 Kritis
5. **Bahasa Indonesia** — seluruh output dalam Bahasa Indonesia
6. **Jangan skip bagian** — jika sebuah fitur tidak ditemukan, tuliskan eksplisit "Tidak ditemukan"
7. **Akhiri setiap dokumen** dengan tabel ringkasan dan skor

---

## ⚠️ Batasan & Catatan

- Jangan modifikasi file apapun selain membuat file baru di `docs/review/`
- Jika ada file yang tidak bisa diakses, catat di `00_SUMMARY.md` dan lanjutkan
- Jika menemukan security issue serius, tandai dengan 🔥 **SECURITY** di awal kalimat
- Setelah semua dokumen selesai, tampilkan pesan: `✅ Review selesai. Semua dokumen tersimpan di docs/review/`

---

## 🚀 Mulai Sekarang

Mulai dengan **LANGKAH 1** (buat folder), lalu **LANGKAH 2** (mapping project), kemudian buat ketujuh dokumen secara berurutan. Konfirmasi setiap dokumen selesai dibuat sebelum lanjut ke berikutnya.

**Selamat mengaudit!**
