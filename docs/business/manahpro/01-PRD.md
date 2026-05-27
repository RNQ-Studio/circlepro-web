# 01 — Product Requirements Document (PRD)

> **Project**: Manahpro — Platform Tata Kelola Tournament & Scoring Panahan
> **Versi**: 1.0
> **Tanggal**: 2026-05-25
> **Referensi keputusan**: [00-offline-first-analysis.md](./00-offline-first-analysis.md)

---

## 1. Latar Belakang & Konteks Bisnis

### 1.1 Masalah yang Diselesaikan

Ekosistem panahan tradisional di Indonesia saat ini mengandalkan proses manual dan semi-digital yang fragmented:

| Masalah | Dampak |
|---------|--------|
| **Pencatatan skor manual** (kertas score sheet) | Rawan kesalahan hitung, lambat, tidak bisa diakses publik real-time |
| **Pengelolaan klub terdesentralisasi** | Data anggota, iuran, prestasi tersebar di spreadsheet dan grup WhatsApp |
| **Leaderboard manual** | Panitia harus kompilasi skor dari seluruh bantalan, hitung ranking, ketik ulang — proses ini bisa makan waktu 30-60 menit setelah setiap rambahan |
| **Bagan eliminasi (aduan) manual** | Generate bracket Olympic Round rentan error dan lambat |
| **Tidak ada standarisasi** | Setiap penyelenggara tournament punya format sendiri — sulit membandingkan performa atlet lintas event |

### 1.2 Mengapa Sekarang

Manahpro bukan ide baru — ini rebuild arsitektur dari sistem yang sudah berjalan dan dipakai di event-event besar panahan Indonesia. Codebase existing membuktikan product-market fit, tetapi arsitekturnya perlu dibangun ulang agar:
- Mampu menangani 1.000–5.000 concurrent users saat live scoring
- Memiliki ketahanan terhadap koneksi buruk di lapangan (offline-tolerant)
- Terstruktur untuk maintainability jangka panjang sebagai produk matang

### 1.3 Konteks Teknis

| Aspek | Keputusan |
|-------|-----------|
| **Stack** | PHP / Laravel (latest stable), PostgreSQL |
| **Client** | Mobile app Flutter (REST API consumer) |
| **Auth** | Laravel Passport OAuth2 (Password Grant, proxy pattern) — sudah ada |
| **Back-office** | Filament v3 — sudah ada |
| **RBAC** | spatie/laravel-permission — sudah ada |
| **Cache & Queue** | Redis — sudah di compose.yaml |
| **Tim** | Solo developer |
| **Skala awal** | Nasional, berpotensi multi-region |

---

## 2. Tujuan Produk (Terukur)

Setiap tujuan harus bisa diverifikasi — bukan aspirasi samar.

### 2.1 Tujuan Fungsional

| ID | Tujuan | Metrik Keberhasilan | Cara Verifikasi |
|----|--------|--------------------|--------------------|
| T-01 | Digitalisasi penuh siklus tournament panahan | 100% proses tournament (pendaftaran → scoring → leaderboard → eliminasi → pemenang) bisa dijalankan tanpa kertas | Jalankan 1 tournament end-to-end di staging |
| T-02 | Input skor tahan koneksi buruk | Skor yang di-input wasit saat offline tersimpan dan ter-sync dalam <30 detik setelah koneksi kembali, 0% data loss | Test dengan airplane mode on/off di device fisik |
| T-03 | Leaderboard real-time | Leaderboard update dalam <3 detik setelah skor di-submit (kondisi online) | Load test + stopwatch manual |
| T-04 | Manajemen klub & anggota terpusat | Admin klub bisa kelola seluruh lifecycle anggota (daftar, verifikasi, iuran, prestasi) via satu platform | Walkthrough semua flow dengan test user |

### 2.2 Tujuan Non-Fungsional (Performa & Reliability)

| ID | Tujuan | Metrik | Cara Verifikasi |
|----|--------|--------|-----------------|
| T-05 | Handle 1.000–5.000 concurrent users pada leaderboard | p95 response time <500ms untuk GET leaderboard pada 5.000 concurrent | Load test dengan k6/Locust |
| T-06 | API response time general | p95 <300ms untuk endpoint non-leaderboard | APM monitoring / load test |
| T-07 | Uptime saat tournament | 99.5% uptime selama window tournament (8 jam) | Monitoring + post-mortem review |
| T-08 | Zero data loss pada skor | 0 skor hilang dalam kondisi apapun (termasuk offline, server restart) | Audit: bandingkan jumlah skor di server vs score sheet fisik |

### 2.3 Pertanyaan Pemantik: "Apa yang terjadi jika tournament berlangsung dan sistem down 5 menit?"

**Jawaban yang jujur:**

Ini tergantung *kapan* 5 menit itu terjadi:

| Skenario | Dampak | Mitigasi |
|----------|--------|----------|
| **Down saat rambahan kualifikasi** | Wasit tetap input skor (offline-tolerant queue). Penonton tidak bisa lihat leaderboard 5 menit. **Dampak: LOW.** | Queue lokal di device wasit. Leaderboard terakhir di-cache di Redis/CDN — penonton lihat data stale tapi masih ada. |
| **Down saat generate bagan eliminasi** | Admin tidak bisa trigger generate bracket. Tournament menunggu. **Dampak: MEDIUM.** | Pre-compute bracket begitu skor kualifikasi selesai. Admin bisa retry. Worst case: admin generate manual dari data yang sudah di-download. |
| **Down saat final/aduan** | Skor aduan tidak bisa di-input dan penonton tidak bisa lihat. **Dampak: HIGH** — tapi ini skenario yang sama dengan offline, dan sudah di-cover oleh queue lokal. | Sama seperti skenario offline. Wasit tetap input, sync saat kembali. |

**Kesimpulan**: 5 menit downtime saat tournament **bukan fatal** jika offline-tolerant sudah diimplementasi untuk wasit. Tapi ini **memalukan** untuk produk yang klaim real-time. Oleh karena itu, target 99.5% uptime selama window tournament (= maks ~2.4 menit downtime per 8 jam) adalah realistis dan cukup.

**Apakah ini masuk definisi sukses?** Ya — T-07 (uptime 99.5% saat tournament) secara eksplisit mengcover ini.

---

## 3. Scope & Out-of-Scope

### 3.1 Dalam Scope (IN)

| Modul | Deskripsi | Prioritas |
|-------|-----------|-----------|
| **Autentikasi & Otorisasi** | Login, register, RBAC multi-role, OAuth2 token management | MUST |
| **Manajemen Club** | CRUD club, profil, logo, informasi pengurus, lokasi latihan | MUST |
| **Manajemen Anggota** | Pendaftaran anggota, verifikasi, KTA digital, mutasi antar club | MUST |
| **Iuran & Pembayaran Club** | Pencatatan iuran, verifikasi pembayaran, status keanggotaan | MUST |
| **Manajemen Tournament** | Buat tournament, kategori lomba, jadwal, kuota, pendaftaran peserta | MUST |
| **Pengaturan Bantalan & Shoot Order** | Mapping peserta ke bantalan, urutan menembak | MUST |
| **Sistem Scoring** | Input skor per arrow (wasit), validasi skor, idempotency, offline-tolerant queue | MUST |
| **Live Leaderboard** | Ranking real-time kualifikasi, cached, high-concurrency read | MUST |
| **Bagan Eliminasi (Aduan)** | Generate bracket Olympic Round, skor set point, shoot-off | MUST |
| **Manajemen Regu (Tim/Beregu)** | Pembentukan regu dari individu, ranking regu | SHOULD |
| **Absensi Tournament** | Check-in kehadiran peserta di hari-H | SHOULD |
| **Tingkat Kemahiran (Mastery Level)** | Penilaian dan pencatatan level kemahiran anggota club | SHOULD |
| **Prestasi & Sertifikasi** | Rekam jejak prestasi dan sertifikasi anggota | SHOULD |
| **Notifikasi** | Push notification (jadwal, hasil, pengumuman) via FCM | SHOULD |
| **Import/Export Data** | Upload/download data peserta dan hasil via Excel | COULD |
| **Rencana Kegiatan Club** | Agenda kerja dan kegiatan club per tahun | COULD |
| **Acara Club (Latber)** | Event internal club, absensi latihan | COULD |

### 3.2 Di Luar Scope (OUT) — Tegas

Berikut fitur yang **TIDAK** akan dibangun, direncanakan, atau dipertimbangkan dalam arsitektur ini:

| Fitur yang TIDAK dibangun | Alasan |
|---------------------------|--------|
| Donasi sosial | Bukan domain inti |
| Marketplace / merchant shop | Bukan domain inti |
| Stories / social media features | Bukan domain inti |
| Watchlist emiten saham | Bukan domain inti |
| Sayembara / kontes terpisah | Bukan domain inti |
| Hisab jumal / kalkulator numerologi | Bukan domain inti |
| Lokasi & kunjungan (check-in) | Bukan domain inti |
| Kotak saran | Bukan domain inti |
| Dompet digital / wallet | Bukan domain inti |
| Payment gateway integration | Verifikasi manual cukup untuk skala awal |
| Multi-language support | Pasar Indonesia only |
| Web client untuk penonton | Scope: mobile app + admin panel saja |

> **Prinsip**: Arsitektur, database schema, dan API tidak akan didesain untuk mengakomodasi fitur di luar scope. Tidak ada "placeholder untuk nanti". Fokus total pada domain inti.

---

## 4. Prioritas Fitur — MoSCoW

### MUST Have (Harus ada untuk go-live tournament pertama)

Tanpa fitur-fitur ini, tournament tidak bisa dijalankan secara digital sama sekali.

| # | Fitur | Justifikasi |
|---|-------|-------------|
| M1 | Autentikasi (login, register, token management) | Semua aktor butuh akses ter-autentikasi |
| M2 | RBAC (Super Admin, Admin Club, Admin Tournament, Scorer, Pengurus, Atlet) | Setiap aktor punya hak akses berbeda |
| M3 | CRUD Club + Profil | Fondasi organisasi — atlet harus terdaftar di club |
| M4 | CRUD Anggota Club + Verifikasi + KTA | Peserta tournament harus terverifikasi sebagai anggota |
| M5 | CRUD Tournament + Kategori Lomba | Inti: membuat dan mengonfigurasi tournament |
| M6 | Pendaftaran Peserta Tournament | Atlet harus bisa daftar ke kategori lomba |
| M7 | Verifikasi Peserta | Admin harus bisa validasi kelengkapan pendaftaran |
| M8 | Pengaturan Bantalan + Shoot Order | Mapping peserta ke target untuk hari-H |
| M9 | Input Skor (Scoring) + Offline-Tolerant | **KRITIS** — inti dari platform, harus tahan koneksi buruk |
| M10 | Validasi Skor (dual-entry scorer + validator) | Integritas data skor harus dijamin |
| M11 | Live Leaderboard (cached, high-concurrency) | **KRITIS** — value proposition utama untuk penonton |
| M12 | Bagan Eliminasi / Aduan (Olympic Round) | Tournament panahan standard memerlukan bracket eliminasi |
| M13 | Dispute Resolution (conflict handling skor) | Konsekuensi dari offline-tolerant — harus ada mekanisme resolve |

### SHOULD Have (Penting tapi tournament bisa jalan tanpa ini di v1)

| # | Fitur | Justifikasi |
|---|-------|-------------|
| S1 | Manajemen Regu (Beregu) | Beberapa tournament punya kategori beregu |
| S2 | Absensi Tournament (check-in hari-H) | Validasi kehadiran — bisa manual dulu |
| S3 | Tingkat Kemahiran (Mastery Level) | Value-add untuk club management |
| S4 | Prestasi & Sertifikasi | Rekam jejak — bisa manual dulu |
| S5 | Notifikasi Push (FCM) | Infrastruktur sudah ada di starter, tinggal integrasi |
| S6 | Iuran & Pembayaran Club | Pencatatan keuangan club |
| S7 | Historis skor per atlet lintas tournament | Analytics untuk atlet |
| S8 | Klaim hadiah (bebungah) | Bisa manual dulu |

### COULD Have (Nice-to-have, dibangun jika waktu tersedia)

| # | Fitur | Justifikasi |
|---|-------|-------------|
| C1 | Import/Export Excel | Kenyamanan admin — bisa manual dulu |
| C2 | Rencana Kegiatan Club | Organisasi internal — bisa pakai tools lain |
| C3 | Acara Club (Latber + Absensi) | Operasional club — bisa pakai tools lain |
| C4 | Dashboard statistik tournament | Reporting — bisa query manual dulu |
| C5 | Profil publik atlet (read-only) | Social feature ringan |

### WON'T Have (Tidak akan dibangun — period)

Semua item di bagian "Out of Scope" (§3.2).

---

## 5. Constraint Teknis & Keputusan Arsitektur Terikat

### 5.1 Keputusan dari Analisis Offline-First

> **Referensi**: [00-offline-first-analysis.md](./00-offline-first-analysis.md) — Bagian D

| Keputusan | Detail | Dampak |
|-----------|--------|--------|
| **Pendekatan: Offline-Tolerant** | Bukan offline-first penuh. Queue lokal di mobile untuk scoring, sync saat koneksi kembali. Server tetap single source of truth. | Menambah ~4-5 minggu ke timeline, tapi non-negotiable untuk reliability di lapangan |
| **Scope offline: Scoring wasit saja** | Hanya endpoint input skor yang di-support offline. Aktor lain: online-only. | Membatasi kompleksitas — tidak perlu sync engine generik |
| **Idempotency key (client_ref)** | Setiap submission skor menyertakan UUID dari client. Server deduplikasi berdasarkan ini. | Perlu kolom `client_ref UNIQUE` di tabel scoring |
| **Conflict resolution: Flag & Escalate** | Jika dua wasit submit skor berbeda untuk archer+rambahan yang sama, sistem flag sebagai disputed dan eskalasi ke admin/chief judge. | Perlu status flow (pending → confirmed/disputed → resolved) dan UI di admin panel |
| **Timestamp dari device** | `device_submitted_at` disimpan terpisah dari `server_received_at` untuk audit trail. | Perlu handling timezone dan clock drift di device |

### 5.2 Constraint Performa

| Constraint | Angka | Justifikasi |
|-----------|-------|-------------|
| Concurrent users leaderboard | 1.000–5.000 | Realitas: penonton + atlet + officials saat babak kritis |
| p95 response leaderboard | <500ms | UX minimum: penonton pull-to-refresh harus terasa "cepat" |
| p95 response general API | <300ms | Standard industry untuk mobile API |
| Scoring write throughput | ~50-100 writes/menit peak | 50 bantalan × 1 submission per 30-60 detik per bantalan |
| Leaderboard recalculation | <2 detik | Harus cepat agar terasa "live" |

### 5.3 Constraint Infrastruktur

| Constraint | Detail |
|-----------|--------|
| Single server deployment (awal) | Solo developer = single VPS/VM. Scaling horizontal nanti. |
| PostgreSQL single instance | Tidak ada read replica di awal. Read/write separation via caching layer (Redis), bukan database replication. |
| Redis wajib | Untuk: session cache, leaderboard cache, queue driver, rate limiting |
| No WebSocket server terpisah | Terlalu berat untuk solo dev. Pilih SSE (Server-Sent Events) atau polling untuk real-time leaderboard. Keputusan final di dokumen arsitektur. |

### 5.4 Constraint Organisasional

| Constraint | Implikasi |
|-----------|-----------|
| Solo developer | Tidak ada code review dari peer. Harus andalkan automated testing + static analysis. |
| Solo developer | Tidak bisa maintain infrastruktur kompleks. Pilih managed services di mana bisa. |
| Solo developer | Jika sakit/unavailable saat tournament, sistem harus bisa berjalan tanpa intervensi manual. |
| Tidak ada tim mobile terpisah | Offline queue di Flutter harus dibangun oleh developer yang sama. Backend API harus didesain untuk memudahkan client implementation. |

---

## 6. Aktor Sistem

| Aktor | Deskripsi | Operasi Utama | Volume |
|-------|-----------|--------------|--------|
| **Super Admin** | Pemilik/pengelola platform | Konfigurasi global, audit, manajemen user | Sangat rendah (1-2 orang) |
| **Admin Club** | Pengelola club panahan | CRUD club, kelola anggota, iuran, prestasi | Rendah (~50-200 club) |
| **Pengurus** | Pengurus organisasi (Pengprov/Pengda) | Monitor club di wilayahnya, validasi pendaftaran club, lihat laporan | Rendah (~10-30 orang) |
| **Admin Tournament** | Penyelenggara tournament | Setup tournament, kategori, bantalan, verifikasi peserta, bagan aduan | Rendah (1-5 per tournament) |
| **Scorer (Wasit)** | Pencatat skor di lapangan | Input skor per arrow, **write path kritis, butuh offline-tolerant** | Medium (~20-50 per tournament) |
| **Atlet / Anggota** | Pegiat panahan terdaftar | Daftar tournament, lihat skor, profil, KTA | Medium-High (~200-2.000 per tournament) |
| **Penonton / Publik** | Pemantau tournament | Lihat leaderboard, jadwal, hasil | **Tertinggi** (1.000-5.000 concurrent) |

### Hierarki Akses

```
Super Admin
├── Pengurus (Pengprov/Pengda)
│   └── Monitor club di wilayahnya
├── Admin Club
│   └── Kelola anggota club-nya
├── Admin Tournament
│   ├── Scorer (di-assign ke tournament tertentu)
│   └── Kelola tournament-nya
└── Atlet / Anggota
    └── Akses data diri + tournament yang diikuti

Penonton (tidak perlu login untuk read leaderboard publik)
```

---

## 7. Definisi Sukses — Konkret & Terverifikasi

### 7.1 Checklist Go-Live (HARUS terpenuhi sebelum tournament pertama)

| # | Kriteria | Cara Verifikasi | Pass/Fail |
|---|----------|-----------------|-----------|
| GL-01 | Tournament end-to-end bisa dijalankan di staging tanpa kertas | Simulasi tournament 1 hari dengan 10 peserta | ☐ |
| GL-02 | Scoring offline-tolerant berjalan: input skor saat airplane mode, sync saat online, 0% data loss | Test di 3 device fisik berbeda, masing-masing 10 siklus offline/online | ☐ |
| GL-03 | Leaderboard response <500ms pada 3.000 concurrent users | Load test dengan k6, 3.000 virtual users, 5 menit sustained | ☐ |
| GL-04 | Conflict detection berjalan: dua wasit input skor berbeda → status disputed | Test manual: 2 device, submit skor berbeda untuk archer+rambahan sama | ☐ |
| GL-05 | RBAC berfungsi: setiap role hanya bisa akses fitur sesuai hak | Automated test per role, cek 403 untuk akses yang tidak diizinkan | ☐ |
| GL-06 | Bagan eliminasi (aduan) ter-generate dengan benar dari hasil kualifikasi | Test dengan 32 peserta, verifikasi bracket 1v16, 2v15, ..., 8v9 | ☐ |
| GL-07 | Zero critical/high severity bugs | Bug triage: tidak ada bug P0/P1 yang open | ☐ |
| GL-08 | Backup & recovery terverifikasi | Restore database dari backup, verifikasi data intact | ☐ |
| GL-09 | Monitoring aktif | Health check endpoint merespon, alerting terkonfigurasi | ☐ |
| GL-10 | Dokumentasi API tersedia | Endpoint list + request/response contoh untuk tim mobile | ☐ |

### 7.2 Definisi Sukses Post-Launch (3 bulan pertama)

| Kriteria | Target |
|----------|--------|
| Tournament berhasil dijalankan tanpa insiden kritis | ≥3 tournament |
| Data loss pada skor | 0% |
| Uptime saat window tournament | ≥99.5% |
| Club terdaftar dan aktif | ≥20 club |
| Pengguna terdaftar | ≥500 |
| Feedback negatif terkait performa | <5% dari total feedback |

### 7.3 Red Flags — Kapan TUNDA Go-Live

> **Aturan keras**: Jika salah satu kondisi berikut terjadi, go-live DITUNDA sampai resolved.

1. **Load test gagal**: p95 leaderboard >1 detik pada 2.000 concurrent — server tidak siap
2. **Data loss pada test offline-sync**: meskipun 1 skor hilang dalam 100 siklus test — trust issue fatal
3. **Bagan eliminasi generate salah**: bracket salah = atlet yang harusnya menang tersisih — bencana kompetitif
4. **Tidak ada backup yang pernah di-test restore**: kalau belum pernah restore backup, anggap backup tidak ada

---

## 8. Asumsi Eksplisit

| # | Asumsi | Risiko Jika Salah |
|---|--------|-------------------|
| A1 | Tournament panahan Indonesia mengikuti format standar (kualifikasi → eliminasi Olympic Round) | Jika ada format non-standard, bagan aduan harus di-extend |
| A2 | Satu tournament berjalan 1-3 hari | Jika lebih lama, perlu pertimbangkan session management dan data volume |
| A3 | Maksimal ~500 peserta per tournament | Jika lebih, perlu re-evaluasi performa scoring dan leaderboard |
| A4 | Wasit menggunakan smartphone Android/iOS | Jika ada wasit yang tidak punya smartphone, butuh fallback manual |
| A5 | Ada koneksi internet (meskipun intermittent) di lokasi tournament | Jika benar-benar zero connectivity, offline-tolerant tidak cukup — butuh offline-first |
| A6 | Admin tournament bersedia melakukan setup (kategori, bantalan) sebelum hari-H | Jika setup dilakukan on-the-spot, UX admin harus lebih streamlined |
| A7 | Satu archer menembak 3 atau 6 arrow per rambahan (sesuai aturan FITA/WA) | Jika variasi lain (misal panahan tradisional dengan aturan khusus), schema skor harus fleksibel |
| A8 | Verifikasi pembayaran tournament dilakukan manual oleh admin (bukan payment gateway) | Jika volume peserta sangat tinggi, verifikasi manual jadi bottleneck |
| A9 | Satu club bisa mengirim banyak peserta ke satu tournament | Ini mempengaruhi relasi data: peserta terkait ke club DAN ke tournament |

---

## 9. Diagram Konteks Sistem (Level 0)

```
                         ┌─────────────────────────────┐
                         │        MANAHPRO API          │
                         │    (Laravel + PostgreSQL)     │
                         │                              │
    ┌────────┐  REST API │  ┌───────────────────────┐  │
    │ Mobile ├───────────┤  │  Authentication       │  │
    │  App   │◄──────────┤  │  Club Management      │  │  ┌───────────┐
    │(Flutter│  JSON     │  │  Member Management    │  ├──┤ PostgreSQL│
    │  )     │           │  │  Tournament Mgmt      │  │  └───────────┘
    └────────┘           │  │  Scoring Engine        │  │
                         │  │  Leaderboard           │  │  ┌───────────┐
    ┌────────┐  Web UI   │  │  Elimination Bracket   │  ├──┤   Redis   │
    │Filament├───────────┤  │  Notification          │  │  └───────────┘
    │ Admin  │◄──────────┤  └───────────────────────┘  │
    │ Panel  │  Session  │                              │  ┌───────────┐
    └────────┘           │                              ├──┤ Firebase  │
                         │                              │  │ (FCM/Auth)│
    ┌────────┐  REST API │                              │  └───────────┘
    │Penonton├───────────┤                              │
    │ (Read  │◄──────────┤                              │  ┌───────────┐
    │  Only) │  JSON     │                              ├──┤   GCS     │
    └────────┘           │                              │  │ (Storage) │
                         └─────────────────────────────┘  └───────────┘
```

### Batas Sistem

| Di dalam sistem | Di luar sistem |
|-----------------|----------------|
| Semua business logic tournament & club | Device/OS mobile (Flutter handles) |
| Data persistence (PostgreSQL) | Konektivitas jaringan (di luar kontrol) |
| Caching layer (Redis) | Infrastruktur WiFi lapangan |
| File storage (GCS) | Aturan resmi panahan (input dari organisasi) |
| Push notification dispatch (FCM) | Payment gateway |
| Admin panel (Filament) | SMS gateway (OTP) |

---

## 10. Catatan untuk Dokumen Selanjutnya

Keputusan dalam PRD ini yang harus konsisten di seluruh dokumen:

| Keputusan PRD | Implikasi di Dokumen Lain |
|--------------|---------------------------|
| Offline-tolerant scoring only (bukan offline-first) | **ERD**: field `client_ref`, `device_submitted_at`, `sync_status`. **Arsitektur**: idempotency layer, conflict detection. **Testing**: skenario offline-online. |
| MoSCoW prioritas | **Timeline**: MUST selesai di Phase 2-3, SHOULD di Phase 3-4, COULD post-launch. **User Stories**: fokus MUST dulu. |
| 1.000–5.000 concurrent users leaderboard | **Arsitektur**: caching strategy wajib. **Testing**: load test wajib sebelum go-live. **Risk**: performa adalah risiko #1. |
| Solo developer constraint | **Timeline**: realistis, tidak aggressive. **Risk**: bus factor = 1. **Testing**: automation-first, bukan manual-first. |
| Server = single source of truth | **ERD**: tidak ada distributed data model. **Arsitektur**: tidak ada CRDT/merge engine. |
| Penonton tidak perlu login | **User Stories**: endpoint leaderboard publik. **Arsitektur**: rate limiting untuk unauthenticated requests. |
| Conflict resolution = Flag & Escalate | **User Stories**: story untuk chief judge. **ERD**: status field di scoring. **Arsitektur**: notification ke admin saat conflict. |
