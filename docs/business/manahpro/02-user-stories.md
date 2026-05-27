# 02 — User Stories & Use Case

> **Project**: Manahpro — Platform Tata Kelola Tournament & Scoring Panahan
> **Versi**: 1.0
> **Tanggal**: 2026-05-25
> **Referensi**: [01-PRD.md](./01-PRD.md) (scope & prioritas MoSCoW), [00-offline-first-analysis.md](./00-offline-first-analysis.md) (keputusan offline-tolerant)

---

## Proses Berpikir: Konflik Kepentingan Antar Aktor

Sebelum masuk ke user stories, saya perlu menganalisis satu pertanyaan penting: **apakah ada konflik kepentingan antar aktor, dan bagaimana ini diselesaikan di level story?**

### Konflik 1: Wasit (kecepatan input) vs Sistem (integritas data)

**Wasit ingin**: Input skor secepat mungkin — tap-tap-done. Setiap detik delay berarti antrian archer menunggu di bantalan.

**Sistem butuh**: Validasi ganda (dual-entry scorer + validator) untuk memastikan skor benar.

**Resolusi di level story**: Validasi tidak boleh memblokir input wasit utama (scorer). Flow-nya:
1. Scorer input skor → langsung tersimpan (status: `pending_validation`)
2. Validator input skor secara independen → server membandingkan
3. Jika cocok → auto-confirm. Jika beda → disputed, eskalasi.

**Prinsip**: Scorer TIDAK PERNAH menunggu validator. Validator TIDAK PERNAH menunggu scorer. Keduanya async.

### Konflik 2: Penonton (data real-time) vs Server (beban performa)

**Penonton ingin**: Leaderboard update setiap detik — "live scoring".

**Server constraint**: 5.000 penonton pull setiap detik = 5.000 req/s → terlalu berat.

**Resolusi di level story**: Leaderboard di-cache di Redis, TTL 3-5 detik. Penonton selalu melihat data yang "hampir real-time" (max 5 detik stale). Ini trade-off yang transparan — story ditulis dengan ekspektasi ini.

### Konflik 3: Admin Tournament (fleksibilitas setup) vs Atlet (stabilitas data)

**Admin ingin**: Bisa ubah kategori, pindah bantalan, reschedule kapan saja.

**Atlet butuh**: Data pendaftarannya stabil — tidak tiba-tiba pindah kategori tanpa notifikasi.

**Resolusi di level story**: Perubahan setup SEBELUM tournament start → bebas. SETELAH tournament start → restricted (hanya Super Admin/Admin Tournament dengan flag `force_update`), dan perubahan harus trigger notifikasi ke atlet terdampak.

### Konflik 4: Scorer (input offline) vs Leaderboard (data terkini)

**Realitas**: Saat scorer offline, leaderboard publik tidak menampilkan skor terbaru dari bantalan tersebut.

**Resolusi**: Ini bukan konflik — ini trade-off yang sudah diputuskan di analisis offline. Story ditulis dengan jelas: leaderboard menampilkan "data yang sudah diterima server", bukan "semua data yang sudah di-input wasit".

---

## Konvensi Penulisan

- **Format ID**: `US-{AKTOR}-{NOMOR}` (misal: `US-SC-01` = User Story Scorer #01)
- **Prioritas**: Mengikuti MoSCoW dari PRD (M = MUST, S = SHOULD, C = COULD)
- **Acceptance Criteria (AC)**: Ditulis dalam format Given-When-Then yang bisa langsung dijadikan test case
- **Aktor**: SA = Super Admin, AC = Admin Club, PG = Pengurus, AT = Admin Tournament, SC = Scorer, AN = Atlet/Anggota, PN = Penonton

---

## 1. User Stories — Super Admin (SA)

### US-SA-01: Mengelola Konfigurasi Global Platform [M]

> Sebagai **Super Admin**, saya ingin **mengelola konfigurasi global platform** (mode maintenance, versi minimum app, pengaturan default) **agar** saya bisa mengontrol perilaku platform secara terpusat tanpa deploy ulang.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Super Admin login ke Filament panel | Membuka halaman App Config | Melihat daftar konfigurasi dengan nilai saat ini |
| AC2 | Mode maintenance = off | Super Admin mengaktifkan mode maintenance | Semua API endpoint mengembalikan 503 dengan pesan maintenance, kecuali endpoint health check |
| AC3 | Versi minimum app diubah ke "2.0.0" | Atlet dengan app versi 1.9.0 hit API | API mengembalikan response `FORCE_UPDATE` dengan link download |

---

### US-SA-02: Mengelola Seluruh User dan Role [M]

> Sebagai **Super Admin**, saya ingin **melihat, mengedit, dan menonaktifkan user apapun** serta **mengubah role-nya** agar saya bisa mengelola akses platform secara menyeluruh.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Super Admin di halaman User Management | Mencari user by nama/email/phone | Hasil pencarian tampil dengan filter role dan status |
| AC2 | User target status = active | Super Admin menonaktifkan user | User tidak bisa login (API return `AUTH_INACTIVE_ACCOUNT`), semua token di-revoke |
| AC3 | User target role = Atlet | Super Admin menambahkan role Admin Club | User memiliki dua role dan bisa mengakses fitur kedua role |
| AC4 | Ada user yang di-edit | Super Admin melihat activity log | Perubahan tercatat: siapa yang mengubah, apa yang berubah (old vs new value) |

---

### US-SA-03: Memonitor Seluruh Tournament Aktif [M]

> Sebagai **Super Admin**, saya ingin **melihat daftar dan status semua tournament yang sedang berjalan** agar saya bisa melakukan intervensi jika ada masalah.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Ada 3 tournament aktif | Super Admin buka dashboard | Melihat list tournament dengan status: draft/open_registration/ongoing/completed |
| AC2 | Tournament X sedang ongoing | Super Admin klik detail | Melihat: jumlah peserta, jumlah bantalan, progress scoring (berapa rambahan sudah ter-input), jumlah dispute aktif |
| AC3 | Ada dispute scoring yang belum di-resolve >30 menit | Super Admin buka dashboard | Melihat alert/warning badge pada tournament tersebut |

---

### US-SA-04: Mengelola Pendaftaran Club Baru [M]

> Sebagai **Super Admin**, saya ingin **memverifikasi dan menyetujui pendaftaran club baru** agar hanya club yang legitimate terdaftar di platform.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Ada 5 pengajuan club baru (status: pending) | Super Admin buka halaman verifikasi club | Melihat daftar club pending dengan: nama, domisili, SK pendirian, pengaju |
| AC2 | Super Admin menyetujui club X | Status club berubah ke active | Pengaju otomatis menjadi Admin Club dari club tersebut, notifikasi dikirim |
| AC3 | Super Admin menolak club Y dengan alasan | Status club berubah ke rejected | Pengaju menerima notifikasi penolakan beserta alasan |

---

### US-SA-05: Melihat Audit Log Seluruh Aktivitas [S]

> Sebagai **Super Admin**, saya ingin **menelusuri audit log aktivitas platform** agar saya bisa menginvestigasi insiden atau perubahan data yang mencurigakan.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Ada berbagai aktivitas tercatat | Super Admin buka halaman Audit Log | Melihat log kronologis: timestamp, aktor, aksi, entitas, old value, new value |
| AC2 | Super Admin filter by "scoring" + tanggal tertentu | Menerapkan filter | Hanya log terkait scoring pada tanggal tersebut yang ditampilkan |
| AC3 | Ada perubahan skor yang dilakukan oleh admin (override) | Super Admin lihat detail | Terlihat: skor lama, skor baru, alasan override, siapa yang mengubah |

---

### US-SA-06: Mengelola Role & Permission Secara Dinamis [M]

> Sebagai **Super Admin**, saya ingin **membuat, mengedit, dan menghapus role serta permission** agar saya bisa menyesuaikan akses tanpa perubahan kode.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Super Admin buka halaman Role Management | Membuat role baru "chief-judge" | Role tersimpan dan bisa di-assign ke user |
| AC2 | Role "chief-judge" dibuat | Super Admin menambahkan permission "scores.resolve-dispute" | User dengan role tersebut bisa mengakses endpoint resolve dispute |
| AC3 | Coba hapus role "super-admin" | Super Admin klik delete | Sistem menolak: role super-admin protected dari penghapusan |

---

## 2. User Stories — Admin Club (AC)

### US-AC-01: Mendaftarkan Club Baru [M]

> Sebagai **Atlet yang ingin menjadi Admin Club**, saya ingin **mengajukan pendaftaran club panahan baru** agar club saya diakui secara resmi di platform.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | User terdaftar sebagai Atlet | Mengisi form pendaftaran club (nama, domisili, SK, logo, lokasi latihan, bank) | Pengajuan tersimpan dengan status `pending`, notifikasi ke Super Admin |
| AC2 | Nama club sudah ada di database | Submit form dengan nama yang sama | Validasi gagal: "Nama club sudah terdaftar" |
| AC3 | Format logo bukan JPG/PNG atau ukuran >2MB | Upload logo | Validasi gagal dengan pesan spesifik |

---

### US-AC-02: Mengelola Profil Club [M]

> Sebagai **Admin Club**, saya ingin **memperbarui informasi profil club** (slogan, deskripsi, logo, lokasi latihan, pengurus) **agar** informasi club selalu up-to-date.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Admin Club login | Membuka halaman profil club-nya | Melihat seluruh informasi club yang bisa di-edit |
| AC2 | Admin Club mengubah logo club | Upload logo baru | Logo lama terhapus (soft delete), logo baru aktif, perubahan tercatat di audit log |
| AC3 | Admin Club A mencoba edit profil Club B | Akses endpoint update profil Club B | API return 403 Forbidden |

---

### US-AC-03: Mengelola Anggota Club [M]

> Sebagai **Admin Club**, saya ingin **menyetujui/menolak pendaftaran anggota baru, mengeluarkan anggota, dan memproses mutasi** agar keanggotaan club terkelola dengan baik.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Ada 3 permintaan masuk anggota baru (status: pending) | Admin Club buka halaman anggota | Melihat daftar pending dengan data biodata lengkap |
| AC2 | Admin Club menyetujui anggota X | Klik approve | Status anggota = active, KTA digital ter-generate, notifikasi ke anggota |
| AC3 | Admin Club mengeluarkan anggota Y | Klik remove dengan alasan | Status anggota = inactive, KTA di-revoke, notifikasi ke anggota |
| AC4 | Anggota Z mengajukan mutasi ke Club lain | Admin Club asal menyetujui | Anggota berpindah ke club tujuan (pending approval Admin Club tujuan), histori mutasi tercatat |

---

### US-AC-04: Mengelola Iuran Keanggotaan [S]

> Sebagai **Admin Club**, saya ingin **memverifikasi pembayaran iuran anggota dan memperbarui masa aktif keanggotaan** agar administrasi keuangan club terkelola.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Anggota upload bukti bayar iuran | Admin Club lihat daftar pembayaran pending | Melihat: nama anggota, nominal, bukti transfer (gambar), tanggal upload |
| AC2 | Admin Club verifikasi pembayaran valid | Klik approve | Masa aktif keanggotaan anggota di-extend sesuai periode, notifikasi ke anggota |
| AC3 | Admin Club menolak pembayaran | Klik reject dengan alasan | Status pembayaran = rejected, anggota menerima notifikasi penolakan |

---

### US-AC-05: Mencatat Prestasi & Sertifikasi Anggota [S]

> Sebagai **Admin Club**, saya ingin **merekam prestasi tournament dan sertifikasi** anggota **agar** pencapaian anggota terdokumentasi.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Anggota X memenangkan tournament eksternal | Admin Club input prestasi (nama event, tanggal, peringkat, bukti) | Prestasi tersimpan di profil anggota dan bisa dilihat publik |
| AC2 | Anggota Y mendapat sertifikasi wasit | Admin Club input sertifikasi (nama, lembaga, tanggal, nomor sertifikat) | Sertifikasi tersimpan dan terverifikasi oleh admin |

---

### US-AC-06: Menilai Tingkat Kemahiran Anggota [S]

> Sebagai **Admin Club**, saya ingin **mencatat hasil uji kenaikan tingkat kemahiran (mastery level)** anggota **agar** progress kemampuan panahan anggota terdokumentasi.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Club memiliki daftar level kemahiran (pemula, menengah, mahir, master) | Admin input hasil tes anggota X (jarak tembak, skor, hasil lulus/gagal) | Jika lulus: level anggota naik. Jika gagal: tetap di level saat ini, histori percobaan tercatat |
| AC2 | Anggota X lihat profil | Buka halaman profil | Melihat level kemahiran saat ini beserta histori kenaikan |

---

## 3. User Stories — Pengurus (PG)

### US-PG-01: Memonitor Club di Wilayah [S]

> Sebagai **Pengurus (Pengprov/Pengda)**, saya ingin **melihat daftar dan status club di wilayah saya** agar saya bisa memantau perkembangan panahan di daerah.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Pengurus Jawa Timur login | Buka halaman monitoring | Melihat daftar club di provinsi Jawa Timur: nama, jumlah anggota aktif, tanggal berdiri, status |
| AC2 | Pengurus klik detail Club X | Buka detail | Melihat: profil club, jumlah anggota, statistik keikutsertaan tournament |
| AC3 | Pengurus Jawa Timur coba akses data Club di Jawa Barat | Hit API | 403 Forbidden — data di luar wilayahnya |

---

### US-PG-02: Memvalidasi Pendaftaran Club Baru di Wilayah [S]

> Sebagai **Pengurus**, saya ingin **mereview dan memberikan rekomendasi atas pendaftaran club baru di wilayah saya** agar proses verifikasi club lebih terstruktur.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Club baru di-submit dari wilayah Pengurus | Pengurus mendapat notifikasi | Bisa review kelengkapan data dan memberikan rekomendasi (approve/reject) sebelum masuk ke Super Admin |
| AC2 | Pengurus merekomendasikan penolakan | Super Admin melihat pengajuan | Status pengajuan menampilkan catatan dari Pengurus beserta alasan |

---

### US-PG-03: Melihat Laporan Performa Wilayah [C]

> Sebagai **Pengurus**, saya ingin **melihat laporan agregat performa atlet di wilayah saya** agar saya bisa mengambil keputusan pembinaan.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Ada data tournament 6 bulan terakhir | Pengurus buka halaman laporan | Melihat: jumlah atlet aktif, jumlah keikutsertaan tournament, medali per club |

---

### US-PG-04: Melihat Daftar Anggota di Wilayah [S]

> Sebagai **Pengurus**, saya ingin **melihat data anggota terdaftar di wilayah saya** agar saya bisa memverifikasi legitimasi peserta tournament tingkat daerah.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Pengurus login | Buka halaman daftar anggota wilayah | Melihat anggota dari club-club di wilayahnya, dengan filter: club, status, level kemahiran |
| AC2 | Pengurus export data anggota | Klik export | Mendapat file Excel/CSV berisi data anggota wilayah |

---

### US-PG-05: Menerima Notifikasi Aktivitas Wilayah [S]

> Sebagai **Pengurus**, saya ingin **mendapat notifikasi saat ada perubahan penting di wilayah saya** (club baru, tournament besar, dll) agar saya tetap informed.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Club baru terdaftar di wilayah Pengurus | Otomatis | Pengurus menerima push notification |
| AC2 | Tournament besar (>100 peserta) dibuat di wilayah Pengurus | Otomatis | Pengurus menerima push notification |

---

## 4. User Stories — Admin Tournament (AT)

### US-AT-01: Membuat Tournament Baru [M]

> Sebagai **Admin Tournament**, saya ingin **membuat tournament panahan baru** dengan seluruh konfigurasi yang dibutuhkan **agar** peserta bisa mendaftar dan tournament bisa dijalankan.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Admin Tournament login | Mengisi form tournament (judul, banner, tanggal, lokasi, kuota, biaya registrasi, batas pendaftaran, kontak panitia) | Tournament tersimpan dengan status `draft` |
| AC2 | Tournament status = draft | Admin mengubah status ke `open_registration` | Peserta bisa mulai mendaftar, tournament tampil di list publik |
| AC3 | Tanggal batas pendaftaran terlewat | Sistem otomatis | Status berubah ke `registration_closed` (atau bisa di-trigger manual oleh admin) |
| AC4 | Field wajib tidak diisi | Submit form | Validasi gagal per-field dengan pesan spesifik |

---

### US-AT-02: Mengatur Kategori Lomba [M]

> Sebagai **Admin Tournament**, saya ingin **membagi tournament ke dalam kategori lomba** (jenis busur, gender, umur, jarak tembak) **agar** peserta mendaftar sesuai kelas yang tepat.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Tournament sudah dibuat | Admin tambah kategori: "Barebow Putra Dewasa 20m" | Kategori tersimpan dengan: jenis busur, gender, batasan umur, jarak, biaya registrasi kategori, tipe penilaian (kualifikasi/eliminasi) |
| AC2 | Admin buat 5 kategori | Lihat daftar kategori | Semua 5 kategori tampil dengan kuota peserta masing-masing |
| AC3 | Tournament sudah ongoing (scoring dimulai) | Admin coba tambah/edit kategori | Peringatan: "Perubahan kategori saat tournament berlangsung dapat mempengaruhi data yang sudah ada" — butuh konfirmasi eksplisit |

---

### US-AT-03: Mengatur Bantalan & Shoot Order [M]

> Sebagai **Admin Tournament**, saya ingin **memetakan peserta ke bantalan dan menentukan urutan menembak** agar hari-H berjalan terorganisir.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Ada 20 peserta terverifikasi di kategori X | Admin trigger auto-assign bantalan | Peserta terdistribusi ke bantalan (maks 4 per bantalan) dengan kode: 01A, 01B, 01C, 01D |
| AC2 | Admin ingin manual override | Pindahkan peserta A dari bantalan 01 ke bantalan 03 | Peserta berpindah, shoot order di kedua bantalan ter-update |
| AC3 | Peserta yang belum terverifikasi | Tidak masuk dalam assignment bantalan | Hanya peserta status = verified yang di-assign |

---

### US-AT-04: Memverifikasi Pendaftaran Peserta [M]

> Sebagai **Admin Tournament**, saya ingin **memeriksa dan memvalidasi pendaftaran peserta** (kelengkapan dokumen, pembayaran, kesesuaian kategori) **agar** hanya peserta yang sah berpartisipasi.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Peserta X mendaftar ke kategori Barebow Putra | Admin buka detail pendaftaran | Melihat: data diri, KTA, bukti pembayaran, surat rekomendasi club, kesesuaian umur vs batasan kategori |
| AC2 | Semua dokumen lengkap | Admin approve | Status peserta = `verified`, notifikasi ke peserta |
| AC3 | Umur peserta tidak sesuai batasan kategori | Sistem menampilkan warning | Admin bisa tetap approve (override) atau reject dengan alasan |
| AC4 | Bukti pembayaran belum diupload | Admin coba approve | Validasi gagal: "Bukti pembayaran belum diunggah" |

---

### US-AT-05: Mengelola Bagan Eliminasi (Aduan) [M]

> Sebagai **Admin Tournament**, saya ingin **men-generate bagan eliminasi (bracket Olympic Round) dari hasil kualifikasi** dan **mengelola pertandingan aduan** agar babak eliminasi berjalan sesuai aturan.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Babak kualifikasi selesai, 32 peserta ter-ranking | Admin trigger generate bracket | Bagan eliminasi 1v32, 2v31, ..., 16v17 ter-generate otomatis. Peserta ranking lebih tinggi melawan ranking lebih rendah |
| AC2 | Bracket ter-generate untuk 16 besar | Babak aduan dimulai | Wasit bisa input skor set point (Win/Lose per set) di setiap pertandingan |
| AC3 | Pertandingan seri (set point sama) | Wasit input shoot-off | Pemenang shoot-off maju ke babak berikutnya |
| AC4 | Jumlah peserta kualifikasi bukan power of 2 (misal 30) | Admin trigger generate bracket | Sistem memberi bye kepada peserta ranking teratas sesuai aturan |
| AC5 | Babak semifinal selesai | Admin lihat bracket | Otomatis generate pertandingan Final dan Perebutan Perunggu |

---

### US-AT-06: Assign Scorer ke Tournament [M]

> Sebagai **Admin Tournament**, saya ingin **menunjuk user tertentu sebagai Scorer untuk tournament ini** agar wasit resmi bisa input skor.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Admin Tournament buka halaman Scorer | Mencari user by nama/KTA | Melihat daftar user yang bisa di-assign |
| AC2 | Admin assign user X sebagai Scorer bantalan 01-05 | Simpan assignment | User X mendapat role Scorer pada tournament ini, bisa akses endpoint scoring untuk bantalan 01-05 |
| AC3 | User X (bukan Scorer) coba akses endpoint scoring | POST /scores | 403 Forbidden — bukan Scorer yang ter-assign |
| AC4 | Tournament selesai | Lihat role user X | Role Scorer di-revoke otomatis (atau bisa di-revoke manual oleh Admin) |

---

### US-AT-07: Mengelola Absensi Hari-H [S]

> Sebagai **Admin Tournament**, saya ingin **mencatat kehadiran fisik peserta pada hari pertandingan** agar hanya peserta hadir yang tampil di lembar penilaian.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Ada 50 peserta verified | Admin buka halaman absensi | Melihat daftar peserta dengan checkbox hadir/tidak hadir |
| AC2 | Admin check-in peserta X | Update status kehadiran | Peserta X muncul di daftar scoring bantalan yang sudah di-assign |
| AC3 | Peserta Y tidak check-in | Scorer buka daftar bantalan | Peserta Y tidak muncul di lembar scoring (atau muncul dengan status "absent") |

---

## 5. User Stories — Scorer / Wasit (SC)

### US-SC-01: Input Skor Per Rambahan (Online) [M]

> Sebagai **Scorer**, saya ingin **menginput skor setiap arrow untuk archer di bantalan saya** secepat mungkin **agar** proses scoring tidak menghambat jalannya tournament.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Scorer login dan memilih bantalan 03 | Buka halaman scoring | Melihat daftar archer di bantalan 03 dengan urutan shoot order |
| AC2 | Scorer pilih Archer A, rambahan ke-3 | Input 6 arrow: 10, 9, 8, X, 7, M | Total rambahan = 44 (X dihitung 10, M dihitung 0), skor tersimpan dengan status `pending_validation` |
| AC3 | Scorer input arrow value di luar range (misal 11) | Submit | Validasi gagal: "Nilai arrow harus antara 0–10, X, atau M" |
| AC4 | Scorer coba input rambahan yang sudah ada | Submit | Sistem mengembalikan error: "Rambahan ke-3 untuk Archer A sudah diinput" (idempotency — kalau client_ref sama, return success; kalau beda, return conflict) |
| AC5 | Skor berhasil di-submit | Leaderboard endpoint di-hit | Leaderboard sudah ter-update dalam <3 detik (setelah cache invalidation) |

---

### US-SC-02: Input Skor Saat Offline (Offline-Tolerant) [M]

> Sebagai **Scorer di lapangan dengan sinyal buruk**, saya ingin **tetap bisa input skor meskipun koneksi internet putus** dan skor **otomatis tersinkron saat koneksi kembali** agar tournament tidak terhenti.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Scorer sedang online, lalu koneksi putus | Indikator status di app berubah | App menampilkan indikator "Offline — skor akan disimpan lokal" |
| AC2 | Scorer dalam mode offline | Input skor rambahan ke-4 | Skor tersimpan di queue lokal device, UI menampilkan skor dengan badge "menunggu sync" |
| AC3 | Scorer input 3 rambahan saat offline | Koneksi kembali | App auto-sync 3 rambahan tersebut ke server dalam urutan kronologis (by device_submitted_at), indikator berubah ke "synced" per entry |
| AC4 | Sync gagal untuk 1 dari 3 entry (server error) | Retry otomatis | App retry dengan exponential backoff, entry yang berhasil tetap "synced", yang gagal tetap "pending" |
| AC5 | Scorer input rambahan yang sama secara online, lalu retry masuk (duplikat) | Server menerima client_ref yang sama | Server return success tanpa insert ulang (idempotent), tidak ada data duplikat |

---

### US-SC-03: Koreksi Skor yang Sudah Diinput [M]

> Sebagai **Scorer**, saya ingin **mengoreksi skor yang sudah saya input** jika ada kesalahan **agar** data skor akurat.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Skor rambahan ke-2 Archer A sudah di-input (status: pending_validation) | Scorer edit arrow ke-3 dari 8 menjadi 9 | Skor lama disimpan di histori (audit trail), skor baru menjadi versi aktif, `client_ref` baru di-generate |
| AC2 | Skor rambahan sudah di-confirm (status: confirmed) | Scorer coba edit | Tidak bisa edit — harus eskalasi ke Admin Tournament/Chief Judge untuk override |
| AC3 | Skor rambahan status: disputed | Scorer coba edit | Tidak bisa edit — sedang dalam proses dispute resolution |

---

### US-SC-04: Validasi Skor oleh Wasit Kedua [M]

> Sebagai **Scorer kedua (validator)**, saya ingin **menginput skor secara independen dari scorer utama** agar skor bisa divalidasi melalui dual-entry comparison.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Scorer A (primary) sudah input rambahan ke-3 Archer X | Scorer B (validator) input rambahan yang sama | Kedua entry tersimpan terpisah dengan `scorer_id` masing-masing |
| AC2 | Skor Scorer A dan Scorer B identik | Otomatis | Status rambahan berubah ke `confirmed`, skor masuk ke leaderboard |
| AC3 | Skor Scorer A dan Scorer B berbeda (misal arrow ke-5: 7 vs 8) | Otomatis | Status berubah ke `disputed`, notifikasi ke Admin Tournament/Chief Judge |
| AC4 | Hanya satu Scorer yang submit (Scorer B belum input) | Setelah timeout X menit | Status tetap `pending_validation` — skor tetap masuk leaderboard sebagai "provisional" |

> **Catatan desain**: Validasi dual-entry bersifat async. Scorer A dan B tidak harus bersamaan. Ini mengakomodasi realitas lapangan dimana validator bisa terlambat menginput.

---

### US-SC-05: Melihat Skor Bantalan Saya [M]

> Sebagai **Scorer**, saya ingin **melihat ringkasan skor seluruh archer di bantalan yang saya tangani** agar saya bisa mengecek kelengkapan input.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Scorer buka halaman bantalan 03 | Lihat ringkasan | Tabel: Archer, Total Skor Kumulatif, Rambahan Terakhir, Status (synced/pending/disputed) |
| AC2 | Ada 1 rambahan yang masih pending sync | Tampilan | Badge "1 menunggu sync" terlihat jelas |

---

### US-SC-06: Input Skor Aduan / Eliminasi [M]

> Sebagai **Scorer**, saya ingin **menginput skor pertandingan aduan (set point system)** agar babak eliminasi Olympic Round bisa dijalankan.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Pertandingan aduan: Archer A vs Archer B | Scorer input skor set 1 (masing-masing 3 arrow) | Pemenang set mendapat 2 point, seri 1-1. Running total set point ditampilkan |
| AC2 | Setelah 5 set, skor set point seri 5-5 | Sistem menampilkan opsi Shoot-Off | Scorer input 1 arrow masing-masing untuk shoot-off, pemenang ditentukan oleh skor + jarak ke tengah (closest to center) |
| AC3 | Pertandingan selesai (salah satu mencapai 6 set point) | Otomatis | Pemenang maju ke bracket berikutnya, bracket terupdate |

---

## 6. User Stories — Atlet / Anggota (AN)

### US-AN-01: Mendaftar Akun Baru [M]

> Sebagai **calon atlet**, saya ingin **mendaftar akun di platform** agar saya bisa bergabung ke club dan mengikuti tournament.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Guest buka app | Mengisi form register (nama, email, phone, password, gender, tanggal lahir, alamat) | Akun tersimpan, email verifikasi terkirim |
| AC2 | Email sudah terdaftar | Submit form | Validasi gagal: "Email sudah terdaftar" |
| AC3 | Password kurang dari 8 karakter | Submit form | Validasi gagal: "Password minimal 8 karakter" |

---

### US-AN-02: Bergabung ke Club [M]

> Sebagai **Atlet**, saya ingin **mengajukan keanggotaan ke club panahan** agar saya terdaftar secara resmi.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Atlet cari club "Club Jaya Archery" | Kirim permintaan bergabung | Permintaan tersimpan (status: pending), notifikasi ke Admin Club |
| AC2 | Atlet sudah menjadi anggota aktif di club lain | Kirim permintaan ke club baru | Permintaan diterima — tapi ini adalah proses mutasi, perlu persetujuan admin club asal dan tujuan |
| AC3 | Admin Club approve permintaan | Otomatis | Atlet menjadi anggota aktif, KTA digital ter-generate |

---

### US-AN-03: Mendaftar Tournament [M]

> Sebagai **Atlet**, saya ingin **mendaftar ke tournament panahan yang tersedia** agar saya bisa berkompetisi.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Tournament X status = open_registration | Atlet pilih kategori "Barebow Putra" dan upload dokumen (KTA, bukti bayar, surat rekomendasi) | Pendaftaran tersimpan (status: pending_verification), notifikasi ke Admin Tournament |
| AC2 | Kuota kategori sudah penuh | Atlet coba daftar | Tidak bisa daftar: "Kuota kategori penuh" |
| AC3 | Batas pendaftaran sudah lewat | Atlet coba daftar | Tidak bisa daftar: "Pendaftaran sudah ditutup" |
| AC4 | Atlet belum punya KTA aktif | Coba daftar tournament yang wajib KTA | Warning: "KTA belum aktif — hubungi Admin Club Anda" |

---

### US-AN-04: Melihat Skor dan Ranking Saya [M]

> Sebagai **Atlet**, saya ingin **melihat skor saya per rambahan dan ranking saya di leaderboard** agar saya tahu posisi saya di tournament.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Atlet sudah selesai 5 rambahan | Buka halaman skor | Melihat: skor per rambahan (detail per arrow), total kumulatif, rata-rata, ranking sementara |
| AC2 | Atlet di ranking #3 dari 50 | Buka leaderboard | Melihat posisi #3 dengan highlight pada baris sendiri |
| AC3 | Skor rambahan ke-3 status = disputed | Buka halaman skor | Melihat badge "Skor sedang dalam verifikasi" pada rambahan tersebut |

---

### US-AN-05: Melihat & Mengunduh KTA Digital [M]

> Sebagai **Anggota Club**, saya ingin **melihat dan menampilkan KTA digital saya** (yang bisa di-scan QR) **agar** saya bisa membuktikan keanggotaan di lapangan.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Anggota aktif di club | Buka halaman KTA | Melihat kartu digital: nama, nomor KTA, foto, club, QR code |
| AC2 | Panitia tournament scan QR code | QR mengandung link verifikasi | Halaman verifikasi menampilkan: status keanggotaan aktif/tidak, data anggota |
| AC3 | Anggota tidak aktif (dikeluarkan) | Buka halaman KTA | KTA tidak ditampilkan, pesan: "Keanggotaan tidak aktif" |

---

### US-AN-06: Melihat Profil dan Histori Tournament [S]

> Sebagai **Atlet**, saya ingin **melihat histori keikutsertaan dan performa saya di tournament sebelumnya** agar saya bisa melacak perkembangan.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Atlet sudah ikut 3 tournament | Buka halaman histori | Melihat daftar tournament: nama, tanggal, kategori, ranking akhir, total skor |
| AC2 | Atlet klik detail tournament tertentu | Buka detail | Melihat: skor per rambahan, statistik (rata-rata, highest end, X-count) |

---

### US-AN-07: Menerima Notifikasi Tournament [S]

> Sebagai **Atlet**, saya ingin **menerima notifikasi terkait tournament yang saya ikuti** (jadwal, perubahan, hasil) agar saya selalu terinformasi.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Atlet terdaftar di Tournament X | Tournament X mulai | Atlet menerima push notification: "Tournament X dimulai hari ini" |
| AC2 | Bantalan assignment di-publish | Otomatis | Atlet menerima notif: "Anda di bantalan 05A, urutan tembak ke-2" |
| AC3 | Hasil akhir tournament di-publish | Otomatis | Atlet menerima notif: "Hasil final Tournament X — lihat ranking Anda" |

---

## 7. User Stories — Penonton / Publik (PN)

### US-PN-01: Melihat Live Leaderboard [M]

> Sebagai **Penonton**, saya ingin **melihat ranking peserta secara real-time** tanpa harus login **agar** saya bisa mengikuti jalannya tournament.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Tournament X sedang berlangsung | Penonton buka halaman leaderboard (tanpa login) | Melihat ranking: posisi, nama, club, total skor, rambahan terakhir |
| AC2 | Ada skor baru masuk ke server | Penonton refresh halaman | Data ter-update (maks delay 5 detik dari waktu server terima skor) |
| AC3 | 5.000 penonton akses bersamaan | p95 response time | <500ms (served from Redis cache) |
| AC4 | Penonton coba akses endpoint scoring (POST) | Tanpa token | 401 Unauthorized |

---

### US-PN-02: Melihat Daftar Tournament Publik [M]

> Sebagai **Penonton**, saya ingin **melihat daftar tournament yang akan datang dan sedang berjalan** agar saya bisa merencanakan untuk menonton.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Ada 3 tournament (1 upcoming, 1 ongoing, 1 completed) | Penonton buka list tournament | Melihat ketiga tournament dengan status, tanggal, lokasi, jumlah peserta |
| AC2 | Penonton filter "upcoming" | Apply filter | Hanya tournament upcoming yang ditampilkan |
| AC3 | Penonton klik detail tournament | Buka detail | Melihat: kategori lomba, jadwal, lokasi maps, jumlah peserta per kategori |

---

### US-PN-03: Melihat Bagan Eliminasi [M]

> Sebagai **Penonton**, saya ingin **melihat bagan eliminasi (bracket)** tournament **agar** saya bisa mengikuti jalur pertandingan aduan.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Bracket 16 besar sudah di-generate | Penonton buka halaman bracket | Melihat tree bracket: siapa vs siapa, skor (jika sudah selesai), pemenang |
| AC2 | Pertandingan 8 besar sedang berlangsung | Penonton lihat bracket | Pertandingan yang sedang berlangsung diberi highlight/indikator "LIVE" |
| AC3 | Bracket kategori "Barebow Putra" | Penonton filter by kategori | Hanya bracket kategori tersebut yang tampil |

---

### US-PN-04: Melihat Profil Peserta Tournament [S]

> Sebagai **Penonton**, saya ingin **melihat profil singkat peserta** (nama, club, ranking) **agar** saya bisa mengenal atlet yang bertanding.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Penonton klik nama atlet di leaderboard | Buka profil publik | Melihat: nama, club, foto, level kemahiran, prestasi publik |
| AC2 | Data pribadi atlet (email, phone, alamat) | Tidak tampil | Hanya data publik yang ditampilkan |

---

### US-PN-05: Melihat Hasil Tournament yang Sudah Selesai [S]

> Sebagai **Penonton**, saya ingin **melihat hasil final tournament yang sudah selesai** (pemenang, ranking, statistik) **agar** saya bisa melihat rekap.

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| AC1 | Tournament X status = completed | Penonton buka halaman hasil | Melihat: podium (emas, perak, perunggu) per kategori, ranking final, statistik top scorer |
| AC2 | Penonton buka statistik kategori | Detail view | Melihat: skor tertinggi per rambahan, rata-rata skor, total X-count per atlet |

---

## 8. Use Case Diagram (Format Teks)

### 8.1 Diagram Utama — Modul Tournament & Scoring

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           SYSTEM: MANAHPRO                              │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                  TOURNAMENT MANAGEMENT                       │       │
│  │                                                              │       │
│  │  (UC-T01) Buat Tournament ◄─────────── Admin Tournament     │       │
│  │  (UC-T02) Atur Kategori Lomba ◄──────── Admin Tournament    │       │
│  │  (UC-T03) Atur Bantalan & Shoot Order ◄── Admin Tournament  │       │
│  │  (UC-T04) Verifikasi Peserta ◄──────── Admin Tournament     │       │
│  │  (UC-T05) Generate Bagan Eliminasi ◄── Admin Tournament     │       │
│  │  (UC-T06) Assign Scorer ◄───────────── Admin Tournament     │       │
│  │  (UC-T07) Kelola Absensi ◄──────────── Admin Tournament     │       │
│  │  (UC-T08) Daftar Tournament ◄──────── Atlet                 │       │
│  │                                                              │       │
│  └──────────────────────────────────────────────────────────────┘       │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                  SCORING ENGINE                              │       │
│  │                                                              │       │
│  │  (UC-S01) Input Skor Kualifikasi ◄──── Scorer               │       │
│  │       ├── «include» Idempotency Check                        │       │
│  │       └── «extend» Offline Queue (jika koneksi putus)        │       │
│  │  (UC-S02) Input Skor Eliminasi/Aduan ◄── Scorer             │       │
│  │       └── «include» Set Point Calculation                    │       │
│  │  (UC-S03) Validasi Skor (Dual Entry) ◄── Scorer (Validator) │       │
│  │       └── «extend» Flag Dispute (jika skor berbeda)          │       │
│  │  (UC-S04) Koreksi Skor ◄──────────── Scorer                 │       │
│  │  (UC-S05) Resolve Dispute ◄─────────── Admin / Chief Judge  │       │
│  │  (UC-S06) Override Skor ◄───────────── Super Admin          │       │
│  │                                                              │       │
│  └──────────────────────────────────────────────────────────────┘       │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                  LEADERBOARD & RESULTS                       │       │
│  │                                                              │       │
│  │  (UC-L01) Lihat Live Leaderboard ◄──── Penonton (Public)    │       │
│  │  (UC-L02) Lihat Bagan Eliminasi ◄───── Penonton (Public)    │       │
│  │  (UC-L03) Lihat Skor Pribadi ◄──────── Atlet                │       │
│  │  (UC-L04) Lihat Hasil Final ◄──────── Penonton (Public)     │       │
│  │                                                              │       │
│  └──────────────────────────────────────────────────────────────┘       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 8.2 Diagram — Modul Club & Anggota

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           SYSTEM: MANAHPRO                              │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                  CLUB MANAGEMENT                             │       │
│  │                                                              │       │
│  │  (UC-C01) Daftarkan Club Baru ◄──────── Atlet               │       │
│  │       └── «include» Verifikasi oleh Super Admin              │       │
│  │  (UC-C02) Kelola Profil Club ◄────────── Admin Club         │       │
│  │  (UC-C03) Kelola Anggota ◄────────────── Admin Club         │       │
│  │       ├── «include» Approve/Reject Pendaftaran               │       │
│  │       ├── «extend» Proses Mutasi                             │       │
│  │       └── «extend» Generate KTA                              │       │
│  │  (UC-C04) Kelola Iuran ◄─────────────── Admin Club         │       │
│  │  (UC-C05) Catat Prestasi ◄────────────── Admin Club         │       │
│  │  (UC-C06) Nilai Kemahiran ◄───────────── Admin Club         │       │
│  │                                                              │       │
│  └──────────────────────────────────────────────────────────────┘       │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                  MEMBER SELF-SERVICE                         │       │
│  │                                                              │       │
│  │  (UC-M01) Register Akun ◄──────────── Guest                 │       │
│  │  (UC-M02) Login / Auth ◄──────────── Guest                  │       │
│  │  (UC-M03) Gabung Club ◄────────────── Atlet                 │       │
│  │  (UC-M04) Lihat KTA ◄──────────────── Atlet                 │       │
│  │  (UC-M05) Lihat Profil & Histori ◄── Atlet                  │       │
│  │                                                              │       │
│  └──────────────────────────────────────────────────────────────┘       │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                  ORGANIZATIONAL OVERSIGHT                    │       │
│  │                                                              │       │
│  │  (UC-O01) Monitor Club Wilayah ◄────── Pengurus             │       │
│  │  (UC-O02) Validasi Club Baru ◄──────── Pengurus             │       │
│  │  (UC-O03) Lihat Anggota Wilayah ◄───── Pengurus             │       │
│  │  (UC-O04) Lihat Laporan Wilayah ◄──── Pengurus              │       │
│  │                                                              │       │
│  └──────────────────────────────────────────────────────────────┘       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 8.3 Diagram — Scoring Flow (Offline-Tolerant)

```
                    ┌──────────┐
                    │  Scorer  │
                    └────┬─────┘
                         │
                    Input Skor
                         │
                   ┌─────▼──────┐
                   │  Online?   │
                   └──┬─────┬───┘
                  YES │     │ NO
                      │     │
            ┌─────────▼┐  ┌─▼──────────────┐
            │ POST API │  │ Queue Lokal     │
            │ /scores  │  │ (Flutter/SQLite)│
            └─────┬────┘  └───────┬─────────┘
                  │               │
                  │         Koneksi Kembali
                  │               │
                  │         ┌─────▼────┐
                  │         │Auto Sync │
                  │         │(retry +  │
                  │         │ dedup)   │
                  │         └─────┬────┘
                  │               │
                  └───────┬───────┘
                          │
                  ┌───────▼────────┐
                  │ Server:        │
                  │ Idempotency    │
                  │ Check          │
                  │ (client_ref)   │
                  └───────┬────────┘
                     NEW  │  DUPLICATE
                   ┌──────┴────────┐
                   │               │
             ┌─────▼─────┐  ┌─────▼─────┐
             │ Simpan    │  │ Return    │
             │ Score     │  │ Success   │
             │ Entry     │  │ (no-op)   │
             └─────┬─────┘  └───────────┘
                   │
            ┌──────▼──────┐
            │ Ada entry   │
            │ validator?  │
            └──┬──────┬───┘
           YES │      │ NO
               │      │
         ┌─────▼────┐ │
         │ Compare  │ └─── Status: pending_validation
         │ scores   │
         └──┬───┬───┘
        SAME│   │DIFF
            │   │
   ┌────────▼┐ ┌▼───────────┐
   │Confirmed│ │Disputed    │
   │         │ │→ Notify    │
   │         │ │  Admin     │
   └─────────┘ └────────────┘
```

---

## 9. Matriks Aktor vs Use Case

| Use Case | SA | AC | PG | AT | SC | AN | PN |
|----------|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Register akun | | | | | | ● | |
| Login / Auth | ● | ● | ● | ● | ● | ● | |
| Kelola user & role | ● | | | | | | |
| Kelola konfigurasi global | ● | | | | | | |
| Audit log | ● | | | | | | |
| Daftarkan club | | | | | | ● | |
| Verifikasi club baru | ● | | ○ | | | | |
| Kelola profil club | | ● | | | | | |
| Kelola anggota club | | ● | | | | | |
| Kelola iuran | | ● | | | | ○ | |
| Catat prestasi | | ● | | | | | |
| Nilai kemahiran | | ● | | | | | |
| Gabung club | | | | | | ● | |
| Lihat KTA | | | | | | ● | |
| Monitor club wilayah | | | ● | | | | |
| Validasi club wilayah | | | ● | | | | |
| Lihat anggota wilayah | | | ● | | | | |
| Buat tournament | | | | ● | | | |
| Atur kategori lomba | | | | ● | | | |
| Atur bantalan | | | | ● | | | |
| Verifikasi peserta | | | | ● | | | |
| Assign scorer | | | | ● | | | |
| Generate bagan eliminasi | | | | ● | | | |
| Kelola absensi | | | | ● | | | |
| Daftar tournament | | | | | | ● | |
| Input skor kualifikasi | | | | | ● | | |
| Input skor eliminasi | | | | | ● | | |
| Validasi skor (dual-entry) | | | | | ● | | |
| Koreksi skor | | | | | ● | | |
| Resolve dispute | ● | | | ● | | | |
| Lihat leaderboard | ○ | ○ | ○ | ○ | ○ | ○ | ● |
| Lihat bagan eliminasi | ○ | ○ | ○ | ○ | ○ | ○ | ● |
| Lihat skor pribadi | | | | | | ● | |
| Lihat hasil final | ○ | ○ | ○ | ○ | ○ | ○ | ● |
| Lihat profil peserta | | | | | | | ● |
| Notifikasi | ○ | ○ | ○ | ○ | ○ | ● | |

**Legenda**: ● = aktor utama, ○ = aktor sekunder (bisa akses tapi bukan primary user)

---

## 10. Catatan Konsistensi dengan Dokumen Lain

| Keputusan di Dokumen Ini | Referensi PRD | Implikasi ke Dokumen Selanjutnya |
|--------------------------|--------------|----------------------------------|
| Validasi dual-entry async (scorer tidak menunggu validator) | PRD M10 | **ERD**: tabel scoring harus support multiple entries per rambahan per archer (per scorer_id). **Arsitektur**: comparison logic di service layer |
| Leaderboard max 5 detik stale | PRD T-05, constraint performa | **Arsitektur**: Redis cache TTL = 3-5 detik. **Testing**: load test harus verifikasi staleness |
| Perubahan setup setelah tournament start = restricted | Konflik aktor #3 | **ERD**: field `started_at` di tournament. **API**: middleware check tournament status sebelum allow edit |
| Timeout untuk validasi (provisional score) | US-SC-04 AC4 | **Arsitektur**: perlu mekanisme timeout — bisa cron job atau event-based. **Konfigurasi**: timeout duration harus configurable |
| Scorer di-assign ke bantalan spesifik | US-AT-06 | **ERD**: tabel pivot scorer↔tournament↔bantalan. **API**: endpoint scoring harus validate scorer punya akses ke bantalan tersebut |
| Penonton tidak perlu login untuk leaderboard | US-PN-01 | **Arsitektur**: endpoint leaderboard = public, rate-limited. **API**: throttle ketat untuk unauthenticated |
