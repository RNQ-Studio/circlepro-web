# 08 — Monetization Strategy & Revenue Projection

> **Project**: Manahpro — Platform Tata Kelola Tournament & Scoring Panahan
> **Versi**: 1.0
> **Tanggal**: 2026-05-25
> **Referensi**: Seluruh dokumen 00–07

---

## Proses Berpikir: Pertanyaan Pemantik

### Siapa yang mau bayar, dan untuk apa?

Sebelum menentukan harga, saya harus identifikasi **siapa saja yang mendapat value** dari platform ini, dan **value apa yang mereka terima** — karena orang hanya mau bayar untuk value yang mereka rasakan langsung.

| Stakeholder | Value yang Diterima | Willingness to Pay | Alasan |
|-------------|--------------------|--------------------|--------|
| **Panitia Tournament** | Digitalisasi scoring, leaderboard real-time, eliminasi otomatis, paperless | 🟢 **Tinggi** | Menghemat 2-4 jam kerja manual per tournament. Mengurangi human error. Meningkatkan prestige event. |
| **Admin Club** | Manajemen anggota terpusat, KTA digital, iuran tracking, prestasi | 🟡 **Sedang** | Value jelas tapi bisa dilakukan manual (spreadsheet). Bayar jika harganya rasional. |
| **Atlet / Anggota** | Akses skor, KTA digital, profil prestasi, daftar tournament mudah | 🔴 **Rendah** | Atlet individu sensitif harga. Fitur yang mereka pakai bisa gratis. |
| **Penonton** | Leaderboard real-time, bracket eliminasi | 🔴 **Sangat Rendah** | Ekspektasi: gratis. Penonton = traffic, bukan revenue source langsung. |
| **Pengurus Organisasi** | Dashboard monitoring club, laporan wilayah | 🟡 **Sedang** | Butuh jika platform sudah jadi standar. Bisa disatukan dengan fee organisasi. |

**Kesimpulan: Revenue utama datang dari 2 sumber:**
1. **Panitia Tournament** — paling tinggi willingness to pay karena value paling langsung
2. **Club (via Admin Club)** — recurring revenue dari subscription manajemen club

**Penonton dan atlet individual = gratis.** Mereka adalah **enabler** bagi value platform — semakin banyak penonton, semakin prestige tournament, semakin mau panitia bayar.

### Berapa harga yang pantas untuk ekosistem panahan Indonesia?

Ini pertanyaan kritis. Panahan tradisional Indonesia bukan olahraga "kaya" — komunitas ini didominasi pegiat akar rumput. Pricing harus **terjangkau** tapi tetap **sustainable** untuk solo developer.

Benchmark informal:

| Platform Serupa | Harga | Catatan |
|----------------|-------|---------|
| iAnseo (international archery scoring) | Gratis / donasi | Open source, tidak ada model bisnis jelas |
| Scorebird / Archery Scoring Pro | $2-5/bulan per user | Pasar internasional, beda purchasing power |
| Platform sport scoring lokal (Indonesia) | Rp50.000-300.000/event | Model per-event, bukan subscription |
| SaaS manajemen organisasi Indonesia | Rp100.000-500.000/bulan | Untuk organisasi kecil-menengah |
| WhatsApp / Google Sheets (kompetitor utama!) | Gratis | **Ini musuh sebenarnya — convenience harus worth it** |

**Insight kritis**: Kompetitor utama Manahpro bukan platform scoring lain — tapi **WhatsApp group + Google Sheets + kertas**. Harga harus cukup rendah sehingga effort pindah ke platform worth it.

### Apa yang terjadi jika model monetisasi salah?

| Skenario Salah | Konsekuensi | Reversibility |
|---------------|-------------|---------------|
| Harga terlalu mahal → adopsi rendah | Platform sepi, tidak ada network effect | 🟢 Bisa turunkan harga — tapi trust sudah rusak |
| Harga terlalu murah → revenue tidak cukup | Developer tidak bisa maintain, platform mati pelan-pelan | 🟡 Bisa naikkan harga, tapi user resist |
| Monetisasi terlalu awal → user kabur | Sebelum trust terbangun, user enggan bayar | 🔴 Sulit — first impression buruk |
| Monetisasi terlalu lambat → kebiasaan "gratis" | User menganggap semua harus gratis selamanya | 🟡 Bisa, tapi perlu komunikasi kuat |

**Strategi**: Mulai **freemium** — fitur inti gratis untuk membangun adopsi. Monetisasi dimulai setelah **minimal 3 tournament berhasil** (validasi value). Pricing dikenalkan bertahap, bukan big bang.

---

## 1. Model Bisnis: Freemium + Transaction-Based Hybrid

### 1.1 Filosofi Pricing

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    MANAHPRO MONETIZATION PYRAMID                         │
│                                                                          │
│                         ┌──────────┐                                    │
│                         │Enterprise│  ← Kustomisasi untuk induk         │
│                         │ (Custom) │     organisasi besar               │
│                        ┌┴──────────┴┐                                   │
│                        │  Pro Club   │ ← Subscription bulanan           │
│                        │  Rp150.000  │   untuk club serius              │
│                       ┌┴────────────┴┐                                  │
│                       │ Pro Tournament│ ← Fee per-event                 │
│                       │ Rp500.000-2jt │   untuk panitia                 │
│                      ┌┴──────────────┴┐                                 │
│                      │   FREE TIER     │ ← Gratis untuk semua           │
│                      │   (Sebagian     │   Bangun adopsi & trust        │
│                      │    besar user)  │   Scoring, leaderboard,        │
│                      │                 │   club basic                    │
│                      └─────────────────┘                                │
│                                                                          │
│   80% user di FREE tier = traffic & network effect                      │
│   15% user di Pro = revenue recurring                                   │
│   5% user di Enterprise = revenue premium                               │
└─────────────────────────────────────────────────────────────────────────┘
```

**Prinsip**:
1. **Fitur yang membuat platform bernilai untuk semua → GRATIS** (scoring, leaderboard publik, registrasi tournament)
2. **Fitur yang memberikan value ekstra untuk pengelola → BERBAYAR** (analytics, branding, fitur admin lanjutan)
3. **Kustomisasi dan SLA → PREMIUM** (dedicated support, custom tournament format)

### 1.2 Kenapa Bukan Full Paid?

| Pertimbangan | Justifikasi Freemium |
|-------------|---------------------|
| Network effect | Platform scoring hanya bernilai jika banyak yang pakai. Paywall di awal membunuh adopsi. |
| Kompetitor: gratis (WA + Sheets) | Harus ada insentif kuat untuk pindah. Gratis = lower barrier to try. |
| Komunitas akar rumput | Banyak club panahan tradisional yang budget IT-nya nol. Mereka harus bisa pakai. |
| Upsell natural | Club yang sudah merasakan free tier akan lebih mau bayar Pro karena sudah tahu value-nya. |

---

## 2. Tiering & Pricing Detail

### 2.1 Tier 1: FREE (Gratis Selamanya)

**Target**: Semua user — atlet, penonton, club kecil, panitia tournament kecil.

| Fitur | Batasan |
|-------|---------|
| ✅ Registrasi & Login | Unlimited |
| ✅ Profil atlet + KTA digital | 1 club membership |
| ✅ Daftar tournament sebagai peserta | Unlimited |
| ✅ Lihat leaderboard & bracket (publik) | Unlimited |
| ✅ Club basic (CRUD, max 50 anggota) | 1 club per user |
| ✅ Tournament basic (max 2 kategori, max 100 peserta) | Max 3 tournament aktif/bulan |
| ✅ Scoring input (wasit) | Unlimited — **tidak pernah di-gate** |
| ✅ Leaderboard real-time | Unlimited |
| ✅ Bagan eliminasi otomatis | Untuk tournament basic |
| ❌ Analytics & statistik lanjutan | — |
| ❌ Custom branding tournament | — |
| ❌ Export data ke Excel | — |
| ❌ Multi-kategori tournament (>2) | — |
| ❌ Anggota club >50 | — |

> **Keputusan kritis: Scoring TIDAK PERNAH di-paywall.** Jika wasit tidak bisa input skor karena belum bayar, trust hancur saat tournament berlangsung. Scoring = public good platform.

### 2.2 Tier 2: PRO CLUB — Rp 150.000/bulan

**Target**: Club panahan aktif dengan 30+ anggota yang butuh manajemen serius.

| Fitur Pro Club | Detail |
|---------------|--------|
| ✅ Semua fitur FREE | — |
| ✅ Anggota club **unlimited** | Tanpa batas 30 |
| ✅ Iuran & pembayaran tracking | Dashboard keuangan club |
| ✅ Prestasi & sertifikasi management | Portfolio anggota lengkap |
| ✅ Tingkat kemahiran (Mastery Level) | Penilaian & graduation tracking |
| ✅ Rencana kegiatan & acara club | Agenda, latber, absensi |
| ✅ Export data anggota ke Excel | CSV/XLSX |
| ✅ Logo & branding club di profil | Custom styling |
| ✅ Statistik anggota (grafik perkembangan) | Dashboard analytics |
| ✅ Multi-admin club | Tambah co-admin |
| ✅ Priority support (WhatsApp group) | Respons <24 jam |

**Pricing justification**:
- Rp 150.000/bulan ≈ Rp 5.000/hari — lebih murah dari satu porsi nasi goreng
- Setara dengan iuran 1 anggota/bulan di kebanyakan club
- Club dengan 50 anggota: Rp 3.000/anggota/bulan — sangat terjangkau

**Alternatif: Paket Tahunan** → Rp 1.500.000/tahun (diskon 2 bulan = 16.7% off)

### 2.3 Tier 3: PRO TOURNAMENT — Fee Per-Event

**Target**: Panitia yang menyelenggarakan tournament menengah–besar.

| Skala Tournament | Harga | Fitur Tambahan |
|-----------------|-------|----------------|
| **Small** (≤100 peserta, ≤3 kategori) | Rp 500.000/event | Multi-kategori, bantalan otomatis, leaderboard custom |
| **Medium** (101-300 peserta, ≤8 kategori) | Rp 1.000.000/event | + Export Excel, + branding logo di leaderboard, + QR check-in |
| **Large** (301-500 peserta, unlimited kategori) | Rp 2.000.000/event | + Dedicated scoring dashboard, + priority support selama event, + post-tournament analytics report |
| **Extra Large** (500+ peserta) | Custom (mulai Rp 3.000.000) | + Custom kebutuhan, + standby developer saat event |

**Pricing justification**:
- Tournament kecil biasanya charge peserta Rp 50.000-150.000/orang
- Tournament 100 peserta × Rp 100.000 = Rp 10.000.000 pemasukan panitia
- Rp 500.000 = **5% dari pemasukan** — sangat wajar untuk digitalisasi penuh
- Tournament besar (500 peserta × Rp 150.000) = Rp 75.000.000 pemasukan. Fee Rp 2.000.000 = **2.7%** — murah.

**Value proposition per rupiah:**

```
Tanpa Manahpro (manual):
├── 4 orang petugas leaderboard × 8 jam × Rp 100.000/orang    = Rp   3.200.000
├── Print score sheet 500 lembar × Rp 2.000                    = Rp   1.000.000
├── Risiko kesalahan hitung → protes → reputasi rusak           = ??? (tak ternilai)
├── Waktu compile leaderboard per rambahan: 30-60 menit         = Penonton bosan, pergi
└── Total estimasi biaya manual                                 ≈ Rp   4.200.000+

Dengan Manahpro Pro Tournament (Medium):
├── Fee platform                                                = Rp   1.000.000
├── Leaderboard real-time otomatis                              = ✅ 3 detik
├── Eliminasi bracket otomatis                                  = ✅ Instant
├── Risiko human error → mendekati nol                          = ✅
└── Saving estimasi                                             ≈ Rp   3.200.000+
```

### 2.4 Tier 4: ENTERPRISE — Custom Pricing

**Target**: Organisasi induk (PERPANI Pusat/Pengprov, Pordasi) yang ingin mengadopsi platform sebagai standar resmi.

| Fitur Enterprise | Detail |
|-----------------|--------|
| ✅ Semua fitur Pro Club + Pro Tournament | Unlimited |
| ✅ Dashboard Pengurus (Pengprov/Pengda) | Multi-club monitoring, laporan wilayah |
| ✅ Branding kustom (white-label light) | Logo organisasi, warna tema, domain subdomain |
| ✅ SLA uptime 99.9% | Jaminan tertulis |
| ✅ Priority support + dedicated WhatsApp | Respons <4 jam |
| ✅ Onboarding & training | Pelatihan panitia, wasit, admin club |
| ✅ Data export & API access | Integrasi dengan sistem organisasi |
| ✅ Custom tournament format | Aturan khusus regional/tradisional |

**Pricing**: Negosiasi per kontrak. Estimasi range:
- Pengprov (1 provinsi): Rp 2.000.000–5.000.000/bulan
- PERPANI Pusat / adopsi nasional: Rp 10.000.000–25.000.000/bulan
- Kontrak tahunan diskon: 10-20%

---

## 3. Revenue Streams — Ringkasan

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     REVENUE STREAMS MANAHPRO                             │
│                                                                          │
│  ┌─────────────────────────┐  ┌──────────────────────────────────────┐  │
│  │ RECURRING (Bulanan)     │  │ TRANSACTIONAL (Per-Event)             │  │
│  │                         │  │                                       │  │
│  │  Pro Club Subscription  │  │  Pro Tournament Fee                   │  │
│  │  Rp 150.000/bln/club   │  │  Rp 500.000 – 3.000.000/event       │  │
│  │                         │  │                                       │  │
│  │  Enterprise Contract    │  │                                       │  │
│  │  Rp 2jt – 25jt/bln     │  │                                       │  │
│  └─────────────────────────┘  └──────────────────────────────────────┘  │
│                                                                          │
│  ┌─────────────────────────┐  ┌──────────────────────────────────────┐  │
│  │ VALUE-ADDED (Opsional)  │  │ FUTURE (Belum diimplementasi)        │  │
│  │                         │  │                                       │  │
│  │  • Custom branding fee  │  │  • Iklan sponsor di leaderboard      │  │
│  │    Rp 200.000/event     │  │  • Premium atlet profile              │  │
│  │  • Data analytics       │  │  • Marketplace commission (jika ada) │  │
│  │    report per-event     │  │  • API access untuk third-party      │  │
│  │    Rp 100.000/report    │  │                                       │  │
│  └─────────────────────────┘  └──────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

### 3.1 Detail Value-Added Services

| Service | Harga | Deskripsi |
|---------|-------|-----------|
| **Custom branding di leaderboard** | Rp 200.000/event | Logo sponsor/penyelenggara tampil di leaderboard publik — prestige value |
| **Post-tournament analytics report** | Rp 100.000/report | PDF report: statistik peserta, distribusi skor, grafik performa per club, medali per daerah |
| **Certificate generation** | Rp 50.000/batch | Generate sertifikat digital (PDF) untuk juara/peserta — template kustom |
| **SMS/WA blast notification** | Rp 100.000/500 pesan | Notifikasi jadwal, hasil, pengumuman via gateway (biaya terpisah dari FCM push) |
| **Historical data access** | Rp 50.000/bulan | Akses riwayat skor atlet lintas tournament, profil statistik lengkap |

---

## 4. Proyeksi Revenue — Tiga Skenario

### 4.1 Asumsi Dasar

| Parameter | Nilai | Sumber |
|-----------|-------|--------|
| Jumlah club panahan aktif di Indonesia (estimasi) | 500–1.000 club | Data informal komunitas panahan |
| Jumlah tournament panahan/tahun (nasional) | 200–400 event | Estimasi: rata-rata 1 event/minggu nasional, termasuk regional |
| Rata-rata peserta per tournament | 100–300 orang | Berdasarkan data codebase existing |
| Waktu go-to-market (setelah go-live) | Bulan ke-3 mulai monetisasi | Bulan 1-2: free adoption |
| Churn rate bulanan (Pro Club) | 5-10% | Estimasi SaaS Indonesia umumnya |
| Conversion rate Free → Pro Club | 5-15% | Standard SaaS freemium |
| Conversion rate Free → Pro Tournament | 10-30% | Event-based, lebih tinggi karena value langsung |

### 4.2 Timeline Adopsi & Revenue

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    ADOPTION & REVENUE TIMELINE                           │
│                                                                          │
│  Bulan 1-3        Bulan 4-6        Bulan 7-12       Bulan 13-24        │
│  ──────────       ──────────       ──────────       ──────────          │
│  FREE ONLY        MONETIZE         GROWTH            MATURE             │
│                   START                                                   │
│  • 3 tournament   • Pricing        • Word-of-mouth  • Standar de facto  │
│    uji coba         dikenalkan       mendorong        di beberapa       │
│  • 10-20 club     • Pro Club         adopsi           provinsi          │
│    onboarding       pertama        • Enterprise     • Revenue stabil    │
│  • Validasi       • Pro Tournament   prospecting                        │
│    product-         fee mulai      • Referral                           │
│    market fit       berlaku          program                            │
│                                                                          │
│  Revenue:         Revenue:         Revenue:          Revenue:            │
│  Rp 0             Rp 1-3 jt/bln   Rp 5-15 jt/bln  Rp 20-50 jt/bln   │
└─────────────────────────────────────────────────────────────────────────┘
```

### 4.3 Skenario Konservatif (Pesimistis)

**Asumsi**: Adopsi lambat, hanya komunitas terdekat yang pakai. Tidak ada enterprise deal.

| Bulan ke- | Pro Club | Tournament/bulan | Revenue Pro Club | Revenue Tournament | Revenue Lain | **Total/Bulan** |
|-----------|----------|-------------------|-----------------|-------------------|-------------|----------------|
| 1-3 | 0 | 1-2 (gratis) | Rp 0 | Rp 0 | Rp 0 | **Rp 0** |
| 4 | 3 | 2 | Rp 450.000 | Rp 1.000.000 | Rp 0 | **Rp 1.450.000** |
| 5 | 5 | 2 | Rp 750.000 | Rp 1.000.000 | Rp 100.000 | **Rp 1.850.000** |
| 6 | 8 | 3 | Rp 1.200.000 | Rp 2.000.000 | Rp 200.000 | **Rp 3.400.000** |
| 7-9 | 12 | 3 | Rp 1.800.000 | Rp 2.500.000 | Rp 300.000 | **Rp 4.600.000** |
| 10-12 | 18 | 4 | Rp 2.700.000 | Rp 3.500.000 | Rp 400.000 | **Rp 6.600.000** |

**Revenue Tahunan (Konservatif): ± Rp 42.000.000 – 50.000.000/tahun**

> Ini belum cukup untuk full-time. Solo developer harus punya income lain. Tapi platform terus tumbuh.

### 4.4 Skenario Moderat (Realistis)

**Asumsi**: Adopsi sehat dari berjejaring di 3-5 provinsi aktif. 1 enterprise deal kecil.

| Bulan ke- | Pro Club | Tournament/bulan | Enterprise | Revenue Pro Club | Revenue Tournament | Revenue Enterprise | Revenue Lain | **Total/Bulan** |
|-----------|----------|-------------------|-----------|-----------------|-------------------|-------------------|-------------|----------------|
| 1-3 | 0 | 2-3 (gratis) | 0 | Rp 0 | Rp 0 | Rp 0 | Rp 0 | **Rp 0** |
| 4 | 5 | 3 | 0 | Rp 750.000 | Rp 2.000.000 | Rp 0 | Rp 200.000 | **Rp 2.950.000** |
| 5 | 10 | 4 | 0 | Rp 1.500.000 | Rp 3.500.000 | Rp 0 | Rp 300.000 | **Rp 5.300.000** |
| 6 | 18 | 5 | 0 | Rp 2.700.000 | Rp 4.500.000 | Rp 0 | Rp 500.000 | **Rp 7.700.000** |
| 7-9 | 30 | 6 | 1 | Rp 4.500.000 | Rp 5.500.000 | Rp 3.000.000 | Rp 800.000 | **Rp 13.800.000** |
| 10-12 | 50 | 8 | 1 | Rp 7.500.000 | Rp 8.000.000 | Rp 3.000.000 | Rp 1.200.000 | **Rp 19.700.000** |

**Revenue Tahunan (Moderat): ± Rp 110.000.000 – 140.000.000/tahun**

> Mulai viable sebagai income utama di bulan ke-9–10 jika biaya hidup ~Rp 10-15 juta/bulan.

### 4.5 Skenario Optimis

**Asumsi**: Diadopsi sebagai standar resmi oleh 1-2 organisasi induk (Pengprov). Word-of-mouth kuat. 2-3 enterprise deals.

| Bulan ke- | Pro Club | Tournament/bulan | Enterprise | Revenue Pro Club | Revenue Tournament | Revenue Enterprise | Revenue Lain | **Total/Bulan** |
|-----------|----------|-------------------|-----------|-----------------|-------------------|-------------------|-------------|----------------|
| 1-3 | 0 | 3-5 (gratis) | 0 | Rp 0 | Rp 0 | Rp 0 | Rp 0 | **Rp 0** |
| 4 | 10 | 5 | 0 | Rp 1.500.000 | Rp 4.000.000 | Rp 0 | Rp 500.000 | **Rp 6.000.000** |
| 5 | 20 | 8 | 1 | Rp 3.000.000 | Rp 7.000.000 | Rp 3.000.000 | Rp 800.000 | **Rp 13.800.000** |
| 6 | 35 | 10 | 1 | Rp 5.250.000 | Rp 10.000.000 | Rp 5.000.000 | Rp 1.500.000 | **Rp 21.750.000** |
| 7-9 | 60 | 12 | 2 | Rp 9.000.000 | Rp 14.000.000 | Rp 8.000.000 | Rp 2.500.000 | **Rp 33.500.000** |
| 10-12 | 100 | 15 | 3 | Rp 15.000.000 | Rp 18.000.000 | Rp 15.000.000 | Rp 3.000.000 | **Rp 51.000.000** |

**Revenue Tahunan (Optimis): ± Rp 250.000.000 – 350.000.000/tahun**

> Sangat viable sebagai full-time income. Bahkan bisa mulai hire 1 developer tambahan.

### 4.6 Ringkasan Proyeksi Revenue

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    PROYEKSI REVENUE BULANAN (Bulan ke-12)                │
│                                                                          │
│  Konservatif      │████████████               │  Rp  6.600.000/bln     │
│                   │                            │  Rp 50 jt/thn          │
│                   │                            │                         │
│  Moderat          │██████████████████████████  │  Rp 19.700.000/bln     │
│                   │                            │  Rp 140 jt/thn         │
│                   │                            │                         │
│  Optimis          │████████████████████████████│  Rp 51.000.000/bln     │
│                   │████████████████            │  Rp 350 jt/thn         │
│                   │                            │                         │
│                   └────────────────────────────┘                         │
│                   0     10jt    20jt    30jt    40jt    50jt             │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Struktur Biaya — Break-Even Analysis

### 5.1 Fixed Cost Bulanan

| Item | Biaya/Bulan | Catatan |
|------|------------|---------|
| VPS Production (4 core, 8GB) | Rp 400.000 – 800.000 | DigitalOcean / Contabo / Biznet Gio |
| Domain + SSL | Rp 50.000 | Let's Encrypt gratis, domain ~Rp 150.000/tahun |
| Redis Cloud (jika tidak self-host) | Rp 0 (self-host) | Termasuk di VPS |
| GCS Storage | Rp 50.000 – 200.000 | Tergantung volume upload |
| Firebase (FCM) | Rp 0 | Free tier cukup untuk skala ini |
| Email transactional (Mailgun/Resend) | Rp 0 – 100.000 | Free tier biasanya cukup |
| Monitoring (UptimeRobot + Sentry) | Rp 0 | Free tier |
| **Total Fixed Cost** | **Rp 500.000 – 1.200.000** | |

### 5.2 Variable Cost

| Item | Biaya | Trigger |
|------|-------|---------|
| Bandwidth overage | Rp 0 – 200.000 | Saat tournament besar (5.000+ viewer) |
| SMS/WA gateway | Rp 100.000 – 500.000/bulan | Jika value-added WA blast aktif |
| Server scale-up saat tournament | Rp 200.000 – 500.000 | Temporary upgrade untuk event besar |

### 5.3 Break-Even Point

```
Fixed Cost   = Rp 800.000/bulan (rata-rata)
Variable Cost = Rp 200.000/bulan (rata-rata)
────────────────────────────────────────────
Total Cost   = Rp 1.000.000/bulan

Break-Even (cover infra only):
  → 7 Pro Club subscriptions (7 × Rp 150.000 = Rp 1.050.000)
  → ATAU 2 Small Tournament fees (2 × Rp 500.000 = Rp 1.000.000)
  → ATAU kombinasi keduanya

Break-Even timeline:
  Konservatif: Bulan ke-4 (sudah cover infra cost)
  Moderat:     Bulan ke-4 (sudah cover infra cost)
  Optimis:     Bulan ke-4 (sudah cover infra cost)
  
✅ Infra cost sangat rendah — break-even mudah dicapai.
```

**Break-Even "Full Income" (Target: Rp 15.000.000/bulan untuk solo developer)**:

```
Target Monthly Income = Rp 15.000.000
Total Cost            = Rp  1.000.000
────────────────────────────────────────
Target Revenue        = Rp 16.000.000/bulan

Bisa dicapai dengan:
  → 50 Pro Club (Rp 7.500.000) + 6 Medium Tournament (Rp 6.000.000) + value-added (Rp 2.500.000)
  → ATAU 30 Pro Club + 5 tournament + 1 Enterprise deal kecil
  
Timeline:
  Konservatif: Belum tercapai di tahun pertama
  Moderat:     Bulan ke-10–11
  Optimis:     Bulan ke-6–7
```

---

## 6. Strategi Go-to-Market untuk Monetisasi

### 6.1 Phase 1: Adoption (Bulan 1-3) — GRATIS TOTAL

| Aktivitas | Detail |
|-----------|--------|
| Target 3-5 tournament gratis | Jadikan sebagai "pilot project" — gratis, tapi minta izin pakai nama event sebagai referensi |
| Onboard 10-20 club | Personal approach ke club-club yang sudah kenal via codebase lama |
| Collect testimonial | Screenshot leaderboard real-time, feedback panitia, foto wasit pakai app |
| Build case study | "Tournament X berhasil dijalankan 100% digital, 0 error scoring, leaderboard real-time" |
| Jangan bicara harga | Fokus 100% pada adopsi dan trust. Pricing akan diperkenalkan di Phase 2. |

### 6.2 Phase 2: Soft Monetization (Bulan 4-6)

| Aktivitas | Detail |
|-----------|--------|
| Perkenalkan Pro Club | Komunikasi personal ke admin club yang sudah aktif: "Fitur baru khusus untuk club serius" |
| Perkenalkan Pro Tournament | Saat ada tournament baru: "Untuk event dengan >100 peserta, kami punya paket Pro yang mencakup..." |
| Grandfather clause | Club/panitia yang sudah pakai di Phase 1 → dapat diskon 50% selama 3 bulan pertama |
| Free trial Pro Club | 1 bulan gratis Pro Club untuk semua existing club |
| Transparansi | Blog post / WA group: "Kenapa kami mulai mengenakan biaya — untuk sustainability platform" |

### 6.3 Phase 3: Growth (Bulan 7-12)

| Aktivitas | Detail |
|-----------|--------|
| Referral program | Pro Club yang mereferensikan 3 club baru → gratis 1 bulan |
| Tournament partner | Potongan harga untuk event yang mau pasang "Powered by Manahpro" |
| Enterprise outreach | Approach Pengprov/Pengda — demo, presentasi, pilot di event resmi |
| Pricing experimentation | A/B test harga di daerah berbeda (sensitivitas harga beda per wilayah) |
| Content marketing | Share statistik tournament, leaderboard highlight, prestasi atlet — organic reach |

### 6.4 Phase 4: Maturity (Bulan 13+)

| Aktivitas | Detail |
|-----------|--------|
| Annual contract | Tawarkan kontrak tahunan dengan diskon signifikan |
| Expand feature | Fitur premium baru berdasarkan feedback (analytics, certificate, dll) |
| API monetization | Third-party mau akses data → API key berbayar |
| Hire & scale | Jika revenue stabil Rp 30jt+/bulan → hire 1 developer, fokus growth |

---

## 7. Risiko Monetisasi & Mitigasi

| Risiko | Kemungkinan | Dampak | Mitigasi |
|--------|-------------|--------|----------|
| **Club menolak bayar Pro** — merasa fitur free cukup | H | M | Pastikan fitur Pro benar-benar menyelesaikan pain point (bukan filler). Survei kebutuhan sebelum finalisasi fitur Pro. |
| **Panitia tournament pindah ke kompetitor/manual** setelah harga diperkenalkan | M | H | Pricing harus jelas menunjukkan ROI (saving vs biaya manual). Offer trial. Grandfather existing users. |
| **Enterprise deal gagal** — organisasi induk tidak mau adopsi | H | M | Jangan bergantung pada enterprise revenue di tahun pertama. Enterprise = bonus, bukan fondasi. |
| **Piracy / self-host** — codebase bocor dan di-deploy sendiri | L | H | Kode bukan open source. Tapi mitigasi sejati: **value bukan di kode, tapi di support + maintenance + update**. Self-host berarti self-maintain. |
| **Free tier dieksploitasi** — club besar dengan 100 anggota tetap pakai free | M | M | Free tier genuinely terbatas (30 anggota). Limit di-enforce via kode. Graceful message: "Upgrade ke Pro untuk menambah anggota." |
| **Pricing terlalu mahal untuk daerah tertentu** | M | M | Regional pricing / subsidi silang. Atau: diskon "club baru pertama kali" 50%. |

---

## 8. Metrics & KPI Monetisasi

### 8.1 North Star Metric

> **Monthly Recurring Revenue (MRR)** — Total revenue recurring dari Pro Club + Enterprise per bulan.
>
> Target: MRR Rp 10.000.000 di bulan ke-12.

### 8.2 KPI Dashboard

| KPI | Definisi | Target Bulan ke-12 | Cara Ukur |
|-----|---------|-------------------|-----------|
| **MRR** | Revenue recurring bulanan | Rp 10.000.000 | Sum(Pro Club fees) + Sum(Enterprise fees) |
| **Total Revenue** | Semua revenue termasuk transactional | Rp 20.000.000 | MRR + Tournament fees + Value-added |
| **Paying Clubs** | Jumlah club Pro aktif | 50 clubs | COUNT WHERE subscription_status = 'active' |
| **Free-to-Pro Conversion** | % club free yang upgrade | 10% | Paying / Total Registered |
| **Tournament Revenue per Event** | Rata-rata fee per tournament | Rp 1.000.000 | Sum(tournament fees) / Count(paid events) |
| **Churn Rate (Pro Club)** | % Pro Club yang cancel per bulan | <8% | Cancelled / Active per bulan |
| **ARPU (Average Revenue Per User/Club)** | Revenue rata-rata per entitas paying | Rp 200.000 | Total Revenue / Total Paying Entities |
| **Customer Acquisition Cost (CAC)** | Biaya akuisisi 1 paying customer | <Rp 100.000 | Marketing spend / New paying customers |
| **LTV (Lifetime Value)** | Estimasi total revenue per customer | Rp 1.800.000 | ARPU × Avg months retained |
| **LTV:CAC Ratio** | Efisiensi akuisisi | >3:1 | Healthy if >3 |

### 8.3 Revenue Composition Target (Bulan ke-12)

```
Target: Total Revenue Rp 20.000.000/bulan

┌────────────────────────────────────────────┐
│  Pro Club Subscription         38%         │
│  ████████████████████                      │
│  Rp 7.500.000                              │
│                                            │
│  Pro Tournament Fee            40%         │
│  █████████████████████                     │
│  Rp 8.000.000                              │
│                                            │
│  Enterprise                    15%         │
│  ████████                                  │
│  Rp 3.000.000                              │
│                                            │
│  Value-Added Services          7%          │
│  ████                                      │
│  Rp 1.500.000                              │
└────────────────────────────────────────────┘
```

---

## 9. Implementasi Teknis Monetisasi

### 9.1 Apa yang Perlu Dibangun

| Komponen | Prioritas | Effort | Keterangan |
|----------|-----------|--------|------------|
| **Subscription management** (club Pro) | MUST | 3-5 hari | Status subscription, expiry, grace period |
| **Feature gating middleware** | MUST | 2-3 hari | Check subscription tier sebelum akses fitur Pro |
| **Payment verification** (manual transfer) | MUST | 1-2 hari | Admin verify bukti bayar, update subscription |
| **Tournament fee tracking** | MUST | 1-2 hari | Fee per-event, invoicing sederhana |
| **Subscription dashboard (Filament)** | SHOULD | 2-3 hari | Admin lihat semua subscribers, revenue, expiring |
| **Revenue reporting** | SHOULD | 1-2 hari | Dashboard total revenue, breakdown per stream |
| **Payment gateway integration** | COULD (Later) | 5-7 hari | Midtrans/Xendit — nanti jika volume tinggi |
| **Invoice PDF generation** | COULD | 1-2 hari | Auto-generate invoice untuk pembayaran |

> **Keputusan: Pembayaran di Phase 1 monetisasi = manual (transfer bank + verifikasi admin).** Alasan: volume masih rendah, payment gateway ada fee per transaksi + effort integrasi. Saat volume >50 transaksi/bulan, baru integrate payment gateway.

### 9.2 Database Schema Tambahan (Konseptual)

```sql
-- Subscription untuk Pro Club
CREATE TABLE club_subscriptions (
    id UUID PRIMARY KEY,
    club_id UUID REFERENCES clubs(id),
    plan ENUM('free', 'pro', 'enterprise'),
    status ENUM('active', 'expired', 'cancelled', 'grace_period'),
    started_at TIMESTAMP WITH TIME ZONE,
    expires_at TIMESTAMP WITH TIME ZONE,
    amount DECIMAL(12,2),
    payment_proof_asset_id UUID REFERENCES assets(id) NULLABLE,
    verified_by UUID REFERENCES users(id) NULLABLE,
    verified_at TIMESTAMP WITH TIME ZONE NULLABLE,
    notes TEXT NULLABLE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Fee per tournament
CREATE TABLE tournament_fees (
    id UUID PRIMARY KEY,
    tournament_id UUID REFERENCES tournaments(id),
    plan ENUM('free', 'small', 'medium', 'large', 'extra_large', 'custom'),
    amount DECIMAL(12,2),
    status ENUM('pending', 'paid', 'verified', 'waived'),
    payment_proof_asset_id UUID REFERENCES assets(id) NULLABLE,
    verified_by UUID REFERENCES users(id) NULLABLE,
    verified_at TIMESTAMP WITH TIME ZONE NULLABLE,
    invoice_number VARCHAR(50) UNIQUE NULLABLE,
    notes TEXT NULLABLE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

### 9.3 Feature Gating — Konsep Middleware

```php
// CheckSubscription Middleware — pseudocode
class CheckSubscription
{
    public function handle(Request $request, Closure $next, string $requiredPlan): Response
    {
        $club = $request->route('club') ?? $request->user()->activeClub;
        
        if (!$club) {
            return response()->json([
                'message' => 'Club tidak ditemukan.',
            ], 404);
        }

        $subscription = $club->activeSubscription;
        
        // Free tier: semua bisa akses fitur free
        if ($requiredPlan === 'free') {
            return $next($request);
        }
        
        // Check plan level
        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'message' => 'Fitur ini memerlukan langganan Pro Club.',
                'required_plan' => $requiredPlan,
                'upgrade_url' => route('subscription.upgrade'),
            ], 403);
        }
        
        if (!$subscription->coversFeature($requiredPlan)) {
            return response()->json([
                'message' => "Fitur ini memerlukan paket {$requiredPlan}.",
                'current_plan' => $subscription->plan,
                'required_plan' => $requiredPlan,
            ], 403);
        }
        
        return $next($request);
    }
}

// Usage di routes:
Route::middleware(['auth:api', 'subscription:pro'])->group(function () {
    Route::get('/clubs/{club}/analytics', [ClubAnalyticsController::class, 'index']);
    Route::get('/clubs/{club}/export', [ClubExportController::class, 'members']);
    // ... fitur Pro lainnya
});
```

---

## 10. Catatan Konsistensi dengan Dokumen Sebelumnya

| Keputusan Monetisasi | Referensi Dokumen | Konsistensi |
|---------------------|-------------------|-------------|
| Scoring GRATIS untuk semua tier | PRD §3.1 — Scoring = MUST, inti platform | ✅ Scoring tidak pernah di-paywall |
| Payment gateway OUT of scope awal | PRD §3.2 — Payment gateway integration = out of scope | ✅ Manual verification dulu |
| Club management sebagai revenue stream | PRD §3.1 — Club management = MUST | ✅ Basic free, advanced (analytics, export) = Pro |
| Tournament fee per-event | PRD §3.1 — Tournament management = MUST | ✅ Basic tournament free, multi-kategori = Pro |
| Export Excel = fitur berbayar | PRD §4 — Import/Export = COULD | ✅ Dijadikan value-add di tier Pro |
| Enterprise pricing untuk Pengurus/Pengprov | Aktor §6 — Pengurus scoped ke region | ✅ Dashboard pengurus = Enterprise feature |
| Feature gating via middleware | Architecture §1.2 — Middleware Stack | ✅ Tambah `CheckSubscription` middleware |
| Schema tambahan (subscriptions, fees) | ERD §1 — Mengikuti pattern existing | ✅ UUID, soft delete, timestamps konsisten |
| Tidak membangun fitur khusus monetisasi di Phase 1-3 | Timeline §Phase 1-3 | ✅ Monetisasi dibangun SETELAH Phase 5 (post go-live) |

### Implikasi ke Dokumen Selanjutnya

| Keputusan | Implikasi |
|-----------|-----------|
| Freemium model | **ERD**: Tambah tabel `club_subscriptions`, `tournament_fees`. **Architecture**: Tambah `CheckSubscription` middleware. **Timeline**: Monetisasi = Phase 6 (post go-live, ~2 minggu development). |
| Manual payment verification | **Filament**: Tambah resource untuk subscription management + payment verification. |
| Feature gating | **API**: Response 403 harus informatif (plan required, upgrade URL). **Mobile**: Handle upgrade prompt di Flutter. |
| Revenue tracking | **Filament**: Dashboard revenue sederhana (total MRR, breakdown, trend). |

---

## 11. Keputusan Final — Pertanyaan Terbuka (Resolved)

### Q1: Trial Period Pro Club → ✅ 14 Hari Trial Gratis

**Keputusan**: Setiap club yang upgrade ke Pro mendapat **14 hari trial gratis** sebelum pembayaran pertama.

**Analisis**:

| Aspek | Detail |
|-------|--------|
| **Kenapa 14 hari (bukan 7 atau 30)?** | 14 hari = cukup untuk merasakan fitur (export data, analytics, unlimited anggota) tanpa terlalu lama sehingga user "lupa" bahwa mereka sedang trial. 7 hari terlalu singkat untuk club yang aktifnya mingguan. 30 hari terlalu lama — user bisa exploit trial terus-menerus. |
| **Konversi yang diharapkan** | Standard SaaS: 15-25% trial → paid. Untuk niche market panahan: estimasi konservatif **10-15%**. |
| **Risiko** | User membuat club baru untuk trial ulang. **Mitigasi**: Trial tied ke `user_id`, bukan `club_id`. Satu user hanya bisa trial 1x. |

**Implikasi teknis**:

```
club_subscriptions.plan = 'pro_trial'
club_subscriptions.trial_ends_at = NOW() + 14 days
club_subscriptions.status = 'trial' → otomatis 'expired' setelah 14 hari jika tidak bayar
```

- Perlu **reminder notification** di hari ke-10 dan ke-13: "Trial Pro Club Anda berakhir dalam X hari."
- Setelah trial expired, fitur Pro **di-lock** tapi data **tidak dihapus** — user bisa upgrade kapan saja untuk mengakses kembali.

---

### Q2: Regional Pricing → ✅ Flat Pricing Nasional

**Keputusan**: Satu harga untuk seluruh Indonesia. Tidak ada diferensiasi harga per wilayah.

**Analisis**:

| Aspek | Detail |
|-------|--------|
| **Kenapa flat?** | (1) Simplicity operasional — solo developer tidak punya bandwidth mengelola tiered pricing per region. (2) Menghindari konflik: "Kenapa club di Jawa lebih murah dari club di Bali?" (3) Rp 150.000/bulan sudah cukup terjangkau untuk hampir semua daerah. |
| **Trade-off yang diterima** | Beberapa daerah terpencil mungkin merasa mahal. Tapi volume user dari daerah tersebut terlalu kecil untuk justify kompleksitas pricing berbeda. |
| **Kapan reconsider?** | Jika data menunjukkan churn rate signifikan lebih tinggi di wilayah tertentu (>15%), pertimbangkan **diskon ad-hoc** (bukan tiered pricing) untuk wilayah tersebut. |

**Implikasi teknis**: Minimal — tidak perlu tabel harga per region. Harga di-hardcode/config. Diskon ad-hoc bisa ditangani via field `discount_percentage` di `club_subscriptions` tanpa mengubah arsitektur.

---

### Q3: Diskon Tournament untuk Pro Club → ✅ Tidak Ada Diskon Bundling

**Keputusan**: Fee Pro Club dan fee Pro Tournament adalah **produk terpisah**. Tidak ada diskon silang.

**Analisis**:

Jawaban user mengungkap insight penting tentang aliran uang:

```
Aliran uang TOURNAMENT:
  Peserta ──► Panitia Tournament (biaya registrasi peserta)
                    │
                    └──► Manahpro (fee platform, dibayar panitia ke developer)

Aliran uang CLUB:
  Anggota ──► Club (iuran bulanan)
                    │
                    └──► Manahpro (fee Pro Club, dibayar admin club ke developer)
```

| Aspek | Detail |
|-------|--------|
| **Kenapa tidak diskon?** | Fee tournament dibayar **panitia ke Manahpro** — ini biaya penggunaan platform. Memberi diskon 20% berarti Manahpro menanggung Rp 100.000-400.000 per event sebagai "subsidi". Untuk solo developer, ini cost langsung yang mengurangi margin. |
| **Apakah ada alternatif loyalty benefit?** | Ya — sebagai gantinya, Pro Club bisa mendapatkan **benefit non-finansial**: (1) Logo club tampil di halaman leaderboard tournament yang diselenggarakan, (2) Statistik anggota mereka yang ikut tournament otomatis masuk profil club, (3) Priority listing saat mendaftarkan anggota ke tournament. Benefit ini zero-cost bagi developer tapi bernilai bagi club. |
| **Risiko** | Panitia yang juga Pro Club merasa "bayar dua kali". **Mitigasi**: Komunikasi jelas bahwa Pro Club = manajemen club, Pro Tournament = digitalisasi event. Value proposition berbeda. |

**Implikasi teknis**: Tidak ada relasi/join antara `club_subscriptions` dan `tournament_fees`. Keduanya independen. Lebih sederhana.

---

### Q4: Kapan Mulai Monetisasi → ✅ Setelah 3 Tournament Sukses

**Keputusan**: Pricing diperkenalkan **setelah minimal 3 tournament berhasil dijalankan** menggunakan platform Manahpro, bukan berdasarkan tanggal kalender.

**Analisis**:

| Aspek | Detail |
|-------|--------|
| **Kenapa milestone-based (bukan time-based)?** | 3 tournament sukses = **bukti nyata** bahwa platform bekerja. Ini memberi confidence saat memperkenalkan harga: "Platform ini sudah terbukti di 3 event, X peserta, Y skor di-input, 0% data loss." Berbeda dengan "sudah 4 bulan" yang mungkin baru 1 tournament (atau 0). |
| **Definisi "sukses"** | Tournament dianggap sukses jika memenuhi **semua**: (1) Scoring berjalan end-to-end tanpa insiden kritis (P0/P1), (2) Leaderboard real-time tersedia selama event, (3) 0% data loss pada skor, (4) Feedback panitia positif (bersedia jadi referensi). |
| **Estimasi waktu** | Jika rata-rata 1-2 tournament/bulan: milestone tercapai di **bulan ke-2 sampai ke-4**. Selaras dengan estimasi di proyeksi revenue. |
| **Risiko** | Jika tournament jarang (1 per 2 bulan), monetisasi bisa tertunda 6+ bulan. **Mitigasi**: Aktif cari tournament untuk pilot — jangan tunggu organik. Target: 3 tournament dalam 3 bulan pertama. |

**Implikasi ke timeline**:

```
Go-Live ──► Tournament #1 ──► Tournament #2 ──► Tournament #3 ──► MONETISASI
  (Minggu 25)   (Minggu 27)     (Minggu 29-31)    (Minggu 33-35)    (Minggu 35+)
                                                                        │
                                                                  Pro Club trial
                                                                  mulai ditawarkan
                                                                        │
                                                                  Tournament #4
                                                                  = event BERBAYAR
                                                                  pertama
```

**Checklist sebelum monetisasi dimulai**:

| # | Item | Status |
|---|------|--------|
| M-01 | 3 tournament sukses tercatat (log + feedback) | ☐ |
| M-02 | Minimal 10 club terdaftar dan aktif | ☐ |
| M-03 | Fitur subscription management + feature gating siap deploy | ☐ |
| M-04 | Landing page pricing / FAQ tersedia | ☐ |
| M-05 | Komunikasi ke existing users tentang perubahan | ☐ |

---

### Q5: Batas Free Tier → ✅ Lebih Longgar (Prioritaskan Adopsi)

**Keputusan**: Free tier dibuat **longgar** di tahap awal untuk memaksimalkan adopsi. Limit bisa diperketat nanti berdasarkan data.

**Analisis**:

| Aspek | Detail |
|-------|--------|
| **Kenapa longgar?** | Platform masih di tahap membangun **network effect**. Semakin banyak club dan tournament yang pakai (meskipun gratis), semakin bernilai platform ini. Limit ketat di awal = bunuh network effect sebelum terbentuk. |
| **Risiko longgar** | Free user tidak pernah upgrade karena merasa cukup. **Mitigasi**: Limit longgar **tapi bukan unlimited** — ada ceiling yang cukup untuk club kecil, tapi tidak cukup untuk club besar yang serius. |
| **Kapan perketat?** | Setelah **50 club aktif** terdaftar dan **conversion rate** bisa diukur. Jika conversion <5%, pertimbangkan perketat limit. Jika conversion >10%, limit sudah tepat. |

**Free tier limit (revisi — lebih longgar)**:

| Fitur | Limit Awal (Longgar) | Pertimbangan Perketat Nanti |
|-------|--------------------|---------------------------|
| Anggota club | **50 anggota** (naik dari 30) | Turunkan ke 30 jika conversion terlalu rendah |
| Tournament per bulan | **3 tournament aktif** (naik dari 2) | Turunkan ke 2 |
| Kategori per tournament | **2 kategori** (naik dari 1) | Turunkan ke 1 |
| Peserta per tournament | **100 peserta** (naik dari 50) | Turunkan ke 50 |
| Scoring & leaderboard | **Unlimited** (tidak berubah) | **TIDAK PERNAH dibatasi** |
| Bagan eliminasi | **Tersedia** | Bisa dipindahkan ke Pro jika data menunjukkan ini driver utama |
| Export Excel | **Tidak tersedia** | Tetap Pro — ini motivator upgrade yang kuat |
| Analytics/statistik | **Tidak tersedia** | Tetap Pro |

**Implikasi teknis**: Feature gating perlu membaca limit dari config/database, bukan hardcode — agar bisa diubah tanpa deploy:

```php
// config/subscription_limits.php
return [
    'free' => [
        'max_club_members' => (int) env('FREE_MAX_CLUB_MEMBERS', 50),
        'max_active_tournaments' => (int) env('FREE_MAX_TOURNAMENTS', 3),
        'max_categories_per_tournament' => (int) env('FREE_MAX_CATEGORIES', 2),
        'max_participants_per_tournament' => (int) env('FREE_MAX_PARTICIPANTS', 100),
    ],
    'pro' => [
        'max_club_members' => null, // unlimited
        'max_active_tournaments' => null,
        'max_categories_per_tournament' => null,
        'max_participants_per_tournament' => null,
    ],
];
```

Limit di `.env` = bisa adjust tanpa redeploy. Cukup clear config cache.

---

### Ringkasan Keputusan Final

| # | Pertanyaan | Keputusan | Alasan Utama |
|---|-----------|-----------|-------------|
| Q1 | Trial period | **14 hari gratis**, 1x per user | Menurunkan barrier, standar SaaS |
| Q2 | Regional pricing | **Flat nasional** | Simplicity, Rp 150k sudah terjangkau |
| Q3 | Diskon bundling Club + Tournament | **Tidak ada diskon**, berikan benefit non-finansial | Beda aliran uang, menghindari cost tambahan |
| Q4 | Kapan monetisasi | **Setelah 3 tournament sukses** | Milestone-based, bukan time-based — credibility dulu |
| Q5 | Free tier limit | **Longgar** (50 anggota, 3 tournament, 2 kategori, 100 peserta) | Prioritas adopsi & network effect |
