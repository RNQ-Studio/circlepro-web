# Prompt: Analisis Use Case Project Manahpro

> Jalankan prompt ini di **Claude Code** dari root direktori project Anda (satu level di atas folder `manahpro`).

---

## PROMPT

```
Kamu adalah software analyst berpengalaman. Tugasmu adalah menganalisis project Laravel + PostgreSQL yang ada di direktori `manahpro` secara menyeluruh dan mendalam, lalu mendokumentasikan semua use case yang ditemukan ke dalam file `docs/manahpro/usecases.md`.

---

## FASE 1 — EKSPLORASI & PEMAHAMAN PROJECT

Lakukan langkah-langkah berikut secara berurutan:

1. **Struktur project**
   - Baca `manahpro/README.md` jika ada
   - Lihat struktur direktori: `app/`, `routes/`, `database/migrations/`, `database/seeders/`, `app/Models/`, `app/Http/Controllers/`, `app/Http/Requests/`, `app/Policies/`

2. **Routing**
   - Baca `manahpro/routes/web.php` dan `manahpro/routes/api.php`
   - Catat semua endpoint, method HTTP, controller yang dipanggil, dan middleware yang digunakan (terutama `auth`, `role`, `permission`, dll)

3. **Models & Relasi**
   - Baca semua file di `manahpro/app/Models/`
   - Identifikasi entitas utama, relasi antar model (`hasMany`, `belongsTo`, `belongsToMany`, dll), dan kolom-kolom penting

4. **Controllers & Logic**
   - Baca semua file di `manahpro/app/Http/Controllers/`
   - Identifikasi setiap method (index, store, show, update, destroy, dll) dan apa yang dilakukan masing-masing

5. **Migrations & Schema**
   - Baca semua file di `manahpro/database/migrations/`
   - Identifikasi tabel-tabel, kolom penting, dan foreign key untuk memahami domain bisnis

6. **Policies & Authorization**
   - Baca `manahpro/app/Policies/` jika ada
   - Identifikasi siapa (role/actor) yang bisa melakukan apa

7. **Form Requests**
   - Baca `manahpro/app/Http/Requests/` jika ada
   - Identifikasi validasi bisnis yang berlaku

---

## FASE 2 — ANALISIS USE CASE

Setelah eksplorasi selesai, lakukan:

- Identifikasi semua **actor** yang terlibat (misal: Admin, User, Manager, Guest, System, dll) berdasarkan middleware, policy, dan role yang ditemukan
- Kelompokkan use case berdasarkan **modul/fitur** (misal: Autentikasi, Manajemen User, Proyek, Laporan, dll)
- Untuk setiap use case, tentukan:
  - **Actor**: siapa yang melakukan
  - **Use Case**: nama aksi (gunakan format "Kata Kerja + Objek", misal: "Membuat Proyek Baru")
  - **Deskripsi**: penjelasan singkat apa yang terjadi, kondisi/syarat penting, dan hasil akhirnya

---

## FASE 3 — TULIS DOKUMENTASI

Buat file `docs/manahpro/usecases.md` dengan struktur berikut:

---

```markdown
# Use Case Documentation — Manahpro

## Ringkasan Project

[Tulis 3–5 kalimat tentang apa project ini, domain bisnisnya, dan tujuan utamanya berdasarkan hasil analisis.]

## Tech Stack
- **Backend**: Laravel (PHP)
- **Database**: PostgreSQL
- **[Tambahkan lainnya jika ditemukan, misal: queue, storage, API third-party]**

## Daftar Actor

| Actor | Deskripsi |
|-------|-----------|
| [Actor 1] | [Peran singkat] |
| [Actor 2] | [Peran singkat] |
| ... | ... |

---

## Ringkasan Use Case (Overview Table)

| No | Modul | Actor | Use Case | Deskripsi Singkat |
|----|-------|-------|----------|-------------------|
| UC-01 | [Modul] | [Actor] | [Nama Use Case] | [1 kalimat] |
| UC-02 | ... | ... | ... | ... |

---

## Detail Use Case per Modul

### Modul: [Nama Modul 1]

| Actor | Use Case | Deskripsi |
|-------|----------|-----------|
| [Actor] | [Nama UC] | [Penjelasan detail: apa yang dilakukan, syarat/kondisi, dan hasil akhir] |

### Modul: [Nama Modul 2]

| Actor | Use Case | Deskripsi |
|-------|----------|-----------|
| ... | ... | ... |

---

## Catatan Temuan Tambahan

[Tulis temuan menarik seperti: fitur yang belum lengkap, logika bisnis kompleks, integrasi eksternal, atau hal-hal yang perlu klarifikasi lebih lanjut dari tim.]
```

---

## ATURAN PENTING

- Jangan berasumsi — base semua temuan pada kode yang benar-benar ada di direktori `manahpro`
- Jika ada file yang tidak bisa dibaca atau direktori kosong, catat di bagian "Catatan Temuan Tambahan"
- Buat direktori `docs/manahpro/` jika belum ada
- Gunakan Bahasa Indonesia untuk seluruh dokumentasi
- Pastikan nomor UC berurutan dan konsisten antara tabel ringkasan dan detail
```