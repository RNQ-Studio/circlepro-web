# Use Case Documentation — Manahpro

## Ringkasan Project

**Manahpro** adalah platform digital berbasis web dan API (Laravel + PostgreSQL) yang dirancang secara khusus untuk memfasilitasi tata kelola ekosistem olahraga panahan tradisional secara komprehensif, terstruktur, dan modern. Domain bisnis utama platform ini meliputi manajemen turnamen panahan terstandarisasi (mulai dari pendaftaran, pengaturan bantalan, pencatatan skor real-time, hingga bagan aduan Olympic Round), manajemen klub panahan tradisional (administrasi anggota, iuran keuangan, tingkat kemahiran, serta sertifikasi prestasi), hingga fitur terintegrasi lainnya seperti manajemen donasi sosial, marketplace merchant, fitur Stories interaktif, dan watchlist emiten saham. Platform ini bertujuan untuk mendigitalisasi olahraga panahan tradisional agar lebih profesional, transparan, dan terukur bagi seluruh pegiat panahan.

## Tech Stack
- **Backend Framework**: Laravel (PHP)
- **Database**: PostgreSQL (dengan dukungan tata kelola migrasi skema relasional yang kompleks)
- **Autentikasi & SSO**: Laravel Passport (OAuth2) & Firebase Authentication (untuk integrasi multi-platform)
- **Penyimpanan Berkas**: Local Storage & Google Cloud Storage (GCS)
- **Integrasi Pihak Ketiga**: Firebase (Push Notifications, SSO Web & Mobile)
- **Pertukaran & Import Data**: Microsoft Excel Spreadsheet (.xlsx via Laravel Excel untuk import/export data massal)

## Daftar Actor

| Actor | Deskripsi |
|-------|-----------|
| **Guest / Pengunjung** | Pengguna yang belum terautentikasi. Dapat melihat informasi turnamen publik, leaderboard, lokasi, serta merchant shop. |
| **User / Archer (Anggota)** | Pegiat panahan terdaftar yang dapat mengelola profil pribadi, mendaftar ke turnamen, bergabung ke klub panahan, mencatat sesi scoring mandiri, berdonasi, menulis stories, dan mengisi saran. |
| **Admin Turnamen** | Pengelola turnamen yang berwenang membuat turnamen, memvalidasi pendaftaran peserta, mengonfigurasi kategori lomba, mengelola babak eliminasi/aduan, dan menerbitkan pemenang. |
| **Scorer (Pencatat Skor)** | Petugas lapangan yang diberikan hak akses oleh Admin Turnamen untuk memasukkan dan memperbarui poin tembakan (skor/arrow) peserta pada bantalan tertentu secara real-time. |
| **Admin Klub** | Pengelola klub panahan tradisional yang bertugas mengelola data administratif klub, mengonfirmasi pendaftaran anggota baru klub, mencatat prestasi/sertifikasi, dan memverifikasi iuran bulanan anggota. |
| **Pengurus Organisasi (Pengprov/Pengda)** | Pengurus di tingkat Provinsi atau Daerah yang memonitor performa klub-klub di bawah naungannya, melihat laporan klub, dan memvalidasi pendaftaran klub baru di wilayahnya. |
| **Merchant / Penjual** | Pemilik toko/kios digital terdaftar di platform yang dapat memposting produk, memperbarui detail merchant, serta berinteraksi dengan pembeli. |
| **Administrator Pusat (Superadmin)** | Pengelola sistem tertinggi yang memegang kontrol penuh atas pengaturan global aplikasi, verifikasi donasi, audit log, perbaikan data pengembang, serta konfigurasi server. |

---

## Ringkasan Use Case (Overview Table)

| No | Modul | Actor | Use Case | Deskripsi Singkat |
|----|-------|-------|----------|-------------------|
| **UC-01** | Autentikasi | Guest | Mendaftar Akun Baru | Guest mendaftar akun pengguna baru menggunakan form signup standar. |
| **UC-02** | Autentikasi | Guest | Masuk ke Sistem | Pengguna masuk ke sistem menggunakan kombinasi email/username dan kata sandi. |
| **UC-03** | Autentikasi | Guest | Masuk via Firebase | Pengguna masuk menggunakan Firebase SSO (Google/Mobile UID) yang terintegrasi. |
| **UC-04** | Akun Pengguna | User / Archer | Memperbarui Profil Pribadi | Pengguna mengubah informasi data diri, foto profil, dan preferensi broadcast. |
| **UC-05** | Akun Pengguna | User / Archer | Mengirimkan Kotak Saran | Pengguna mengirimkan saran, umpan balik, atau keluhan ke pengembang platform. |
| **UC-06** | Akun Pengguna | User / Archer | Mengakses KTA | Pengguna mengunduh atau menampilkan Kartu Tanda Anggota (KTA) resmi. |
| **UC-07** | Turnamen Panahan | Admin Turnamen | Membuat Turnamen Baru | Admin merancang event turnamen panahan baru beserta data penyelenggara. |
| **UC-08** | Turnamen Panahan | Admin Turnamen | Mengatur Kategori Turnamen | Admin membagi kelas turnamen berdasarkan jenis busur, gender, umur, dan mixbow. |
| **UC-09** | Turnamen Panahan | Admin Turnamen | Mengatur Target Bantalan | Admin memetakan kode bantalan dan shoot order untuk para peserta turnamen. |
| **UC-10** | Turnamen Panahan | User / Archer | Mendaftar sebagai Peserta | Peserta mendaftarkan diri pada salah satu kategori turnamen panahan aktif. |
| **UC-11** | Turnamen Panahan | Admin Turnamen | Verifikasi Registrasi Peserta | Admin memeriksa keabsahan syarat pendaftaran peserta dan memvalidasinya. |
| **UC-12** | Turnamen Panahan | Scorer / Admin | Mencatat & Mengupdate Skor | Scorer menginput skor poin tembakan per arrow peserta di lapangan secara berkala. |
| **UC-13** | Turnamen Panahan | User / Admin | Mengelola Regu (Beregu) | Menghimpun peserta ke dalam satu tim/regu mewakili daerah atau klub. |
| **UC-14** | Turnamen Panahan | Admin Turnamen | Mengelola Bagan Aduan | Membuat bagan eliminasi pertandingan (1 vs 16, dll) model Olympic Round. |
| **UC-15** | Turnamen Panahan | Admin / Scorer | Mengelola Absensi Turnamen | Mencatat kehadiran fisik peserta di lokasi turnamen sebelum pertandingan dimulai. |
| **UC-16** | Turnamen Panahan | User / Archer | Mengklaim Hadiah / Bebungah | Pemenang turnamen melakukan klaim hadiah (bebungah) berdasarkan antrean skor. |
| **UC-17** | Turnamen Panahan | Admin Turnamen | Import/Export Data Turnamen | Mengunggah atau mengunduh data peserta, bantalan, dan hasil via Excel. |
| **UC-18** | Klub & Organisasi | User / Archer | Mendaftarkan Klub Baru | Archer mengajukan pembuatan registrasi klub panahan baru ke sistem. |
| **UC-19** | Klub & Organisasi | Admin Klub | Mengelola Profil Klub | Admin memperbarui slogan, logo, dan deskripsi detail klub di peta digital. |
| **UC-20** | Klub & Organisasi | Admin Klub | Mengelola Anggota Klub | Admin menyetujui, menolak pendaftaran anggota baru, atau melakukan mutasi. |
| **UC-21** | Klub & Organisasi | Admin Klub / User | Mengelola Iuran & Pembayaran | User membayar iuran keanggotaan klub, dan Admin memverifikasi transaksi tersebut. |
| **UC-22** | Klub & Organisasi | Admin Klub | Penilaian Tingkat Kemahiran | Menguji tingkat kemahiran panahan anggota (Mastery Level) dengan kalibrasi target. |
| **UC-23** | Klub & Organisasi | Admin Klub | Mencatat Prestasi & Sertifikasi | Admin merekam penghargaan prestasi turnamen eksternal atau sertifikat keahlian anggota. |
| **UC-24** | Klub & Organisasi | Admin Klub | Mengelola Rencana Kegiatan | Admin merancang agenda kerja atau rencana kegiatan klub per tahun anggaran. |
| **UC-25** | Klub & Organisasi | Admin Klub / User | Mengelola Acara Klub | Admin merilis acara pertemuan klub dan merekam absensi kehadiran anggota. |
| **UC-26** | Modul Sayembara | Admin Pusat | Mengelola Sayembara Baru | Membuat event sayembara/kontes panahan mandiri berhadiah saldo atau barang. |
| **UC-27** | Modul Sayembara | User / Archer | Mendaftar Peserta Sayembara | Mengikuti sayembara aktif dengan mendaftarkan informasi kesiapan panahan. |
| **UC-28** | Modul Sayembara | Admin / Scorer | Menginput Hasil Sayembara | Mencatat skor tembakan peserta sayembara untuk menentukan peringkat hadiah. |
| **UC-29** | Modul Sayembara | User / Admin | Mengelola Dompet Sayembara | Mengakses transaksi keuangan masuk/keluar (Wallet) terkait sayembara. |
| **UC-30** | Fitur Pendukung | User / Donatur | Berdonasi Sosial | Pengguna menyalurkan dana donasi sosial secara reguler atau anonim. |
| **UC-31** | Fitur Pendukung | User / Archer | Berbagi Cerita (Stories) | Mengunggah cerita/story pendek, melihat story orang lain, dan memberi reaksi. |
| **UC-32** | Fitur Pendukung | User / Archer | Mengelola Lokasi & Kunjungan | Menambahkan lokasi latihan panahan, mencatat kunjungan (visit), dan berkomentar. |
| **UC-33** | Fitur Pendukung | Merchant / User | Transaksi Merchant Shop | Merchant mengunggah produk shop, dan User memberikan rating ulasan produk. |
| **UC-34** | Fitur Pendukung | User / Archer | Watchlist Emiten Saham | Mengelola daftar pantau emiten saham syariah dan menghitung hisab jumal nama. |

---

## Detail Use Case per Modul

### Modul 1: Autentikasi & Akun Pengguna

| Actor | Use Case | Deskripsi |
|-------|----------|-----------|
| **Guest** | **UC-01**: Mendaftar Akun Baru | Guest mengisi form pendaftaran yang meminta username, email, name, phone, password, gender, birth date, dan alamat lengkap. Sistem menyimpan data ke tabel `users` dengan status default tidak aktif/perlu konfirmasi. |
| **Guest / User** | **UC-02**: Masuk ke Sistem | Guest memasukkan username/email/no handphone beserta password. Sistem memeriksa kecocokan di database. Jika sukses, Laravel Passport menerbitkan OAuth Access Token untuk sesi API selanjutnya. Sesi login dicatat di server. |
| **Guest / User** | **UC-03**: Masuk via Firebase | Guest melakukan autentikasi SSO pihak ketiga (Google / Firebase Auth) di client mobile/web, kemudian mengirimkan token UID Firebase ke backend (`signin-firebase`). Backend memverifikasi UID tersebut dan mengaitkannya dengan akun user lokal. |
| **User / Archer** | **UC-04**: Memperbarui Profil Pribadi | User mengubah informasi foto profil (diunggah ke `documents` dan disimpan di GCS), akun instagram, alamat pengiriman barang, ukuran baju/kaos (untuk turnamen), serta mengatur toggle kesediaan menerima siaran pesan broadcast. |
| **User / Archer** | **UC-05**: Mengirimkan Kotak Saran | User menulis saran, masukan kritis, atau pesan keluhan melalui modul Kotak Saran (`kotak_sarans`). Saran ini akan masuk ke database admin untuk ditindaklanjuti demi perbaikan kualitas layanan aplikasi. |
| **User / Archer** | **UC-06**: Mengakses KTA | User melihat kartu tanda keanggotaan digital (KTA) yang memuat nama, nomor keanggotaan unik, asal daerah, pas foto, serta scan QR Code untuk integrasi verifikasi fisik di lapangan/lomba. |

### Modul 2: Manajemen Turnamen Panahan

| Actor | Use Case | Deskripsi |
|-------|----------|-----------|
| **Admin Turnamen** | **UC-07**: Membuat Turnamen Baru | Admin menginput judul turnamen, logo, banner promo, tautan rundow/handbook (THB), kuota maksimal peserta total, tanggal batas registrasi, lokasi Google Maps, nomor kontak panitia, nominal biaya pembayaran turnamen, serta status publikasi. |
| **Admin Turnamen** | **UC-08**: Mengatur Kategori Turnamen | Admin membagi turnamen ke dalam beberapa `tournament_category_details`. Admin mengesahkan jarak tembak (misal: 15m, 20m), jenis busur (Barebow, Horsebow, Recurve, Compound), batasan umur maksimal, harga registrasi kategori, dan jenis penilaian (kualifikasi/eliminasi). |
| **Admin Turnamen** | **UC-09**: Mengatur Target Bantalan | Admin memetakan pembagian kode bantalan peserta (misal: bantalan 01A, 01B, 02A, dll) serta menentukan urutan menembak peserta (shoot order) agar tidak terjadi bentrokan saat pengambilan nilai skor di lapangan. |
| **User / Archer** | **UC-10**: Mendaftar sebagai Peserta | User memilih turnamen aktif, memilih salah satu kategori lomba yang sesuai dengan jenis busurnya, memasukkan KTA (jika diwajibkan), mengunggah berkas surat rekomendasi dari klub/daerah, dan mengunggah bukti pembayaran turnamen. |
| **Admin Turnamen** | **UC-11**: Verifikasi Registrasi Peserta | Admin meninjau data pendaftaran peserta lomba (`tournament_participants`). Admin memeriksa kelengkapan administrasi, surat rekomendasi, kesesuaian umur, dan status pembayaran. Setelah valid, admin mengonfirmasi peserta menjadi status **Verified**. |
| **Scorer / Admin** | **UC-12**: Mencatat & Mengupdate Skor | Scorer/Admin menginput poin tembakan per arrow (misal: 10, 9, 8, M, X) di lapangan secara langsung. Skor yang dimasukkan akan diakumulasikan secara real-time ke dalam ranking klasemen sementara kualifikasi turnamen. |
| **User / Admin** | **UC-13**: Mengelola Regu (Beregu) | Menyatukan 3 archer dengan klub/provinsi yang sama untuk didaftarkan sebagai regu tim panahan (`tournament_regus`). Admin dapat men-generate regu secara otomatis berdasarkan akumulasi skor kualifikasi per wilayah/provinsi. |
| **Admin Turnamen** | **UC-14**: Mengelola Bagan Aduan | Admin memicu generator sistem untuk membuat bagan eliminasi *Matchplay* berbasis FITA (1 vs 16, 8 Besar, Semifinal, hingga Perebutan Emas/Perunggu). Sistem otomatis menarik pemenang babak kualifikasi ke dalam slot bracket dan mencatat skor aduan (*Set Point*). |
| **Admin / Scorer** | **UC-15**: Mengelola Absensi Turnamen | Admin mencatat kehadiran fisik peserta di meja registrasi ulang pada hari pertandingan. Status absensi ini disinkronisasikan ke sistem sebagai syarat utama agar nama peserta muncul di lembar penilaian lapangan scorer. |
| **User / Archer** | **UC-16**: Mengklaim Hadiah / Bebungah | Archer yang berhasil mencapai target minimal poin (skor prestasi khusus) mengklaim hadiah (*bebungah*) lewat antrean antarmuka pengguna. Sistem mengonfirmasi kesediaan hadiah dan mencatat riwayat klaim pemenang. |
| **Admin Turnamen** | **UC-17**: Import/Export Data Turnamen | Admin mendownload template pengisian peserta massal, mengunggah data archer via Excel, serta mengekspor lembar penilaian (*score sheet*), hasil akumulasi juara umum, dan grafik pencapaian medali daerah dalam format Excel. |

### Modul 3: Manajemen Klub & Organisasi Panahan

| Actor | Use Case | Deskripsi |
|-------|----------|-----------|
| **User / Archer** | **UC-18**: Mendaftarkan Klub Baru | Pengguna mengajukan izin pendaftaran klub panahan tradisional baru. Mengisi nama klub, kepengurusan, domisili provinsi/kota, nomor SK pendirian, koordinat GMaps lokasi latihan, bank klub, serta mengunggah logo klub. |
| **Admin Klub** | **UC-19**: Mengelola Profil Klub | Admin Klub memperbarui slogan motivasi klub, deskripsi profil sejarah klub, mengedit logo, tautan maps tempat latihan, dan informasi pengurus inti klub. |
| **Admin Klub** | **UC-20**: Mengelola Anggota Klub | Admin Klub mengurusi alur masuk anggota baru klub (`club_members`). Memvalidasi berkas biodata, menerbitkan kartu anggota klub, mengeluarkan anggota, atau melakukan mutasi kepindahan klub bagi atlet. |
| **Admin Klub / User** | **UC-21**: Mengelola Iuran & Pembayaran | Anggota mengirimkan bukti bayar iuran bulanan klub atau biaya pendaftaran klub. Admin Klub memverifikasi bukti bayar tersebut dan memperbarui masa aktif status keanggotaan atlet hingga tanggal yang disepakati. |
| **Admin Klub** | **UC-22**: Penilaian Tingkat Kemahiran | Pengurus klub melakukan uji kenaikan tingkat kemahiran panahan anggota (Mastery Level / Club Level). Meliputi kalibrasi jarak tembak, penilaian lulus obyektif target panahan, dan pencatatan kelulusan (graduation). |
| **Admin Klub** | **UC-23**: Mencatat Prestasi & Sertifikasi | Admin merekam jejak prestasi kejuaraan panahan di luar platform yang diikuti anggota serta merekam pencapaian sertifikasi kepelatihan/wasit panahan anggota untuk meningkatkan gengsi poin klub. |
| **Admin Klub** | **UC-24**: Mengelola Rencana Kegiatan | Admin merancang agenda kerja atau rencana kegiatan klub per tahun anggaran, seperti rencana mengadakan latber (latihan bersama), tournament internal, atau upgrading peralatan panahan. |
| **Admin Klub / User** | **UC-25**: Mengelola Acara Klub | Admin Klub membuat event latber atau rapat klub (`club_events`). Anggota dapat memberikan konfirmasi kehadiran, dan admin menginput log presensi absensi kehadiran secara offline/online. |

### Modul 4: Modul Sayembara & Kontes

| Actor | Use Case | Deskripsi |
|-------|----------|-----------|
| **Admin Pusat** | **UC-26**: Mengelola Sayembara Baru | Admin merancang event sayembara/kontes panahan terbuka dengan hadiah menarik (`sayembaras`). Menentukan batasan kategori jarak sayembara, pembagian alokasi hadiah pemenang, aturan poin minimal, dan masa aktif kontes. |
| **User / Archer** | **UC-27**: Mendaftarkan Peserta Sayembara | User mendaftar ke sayembara yang sedang berlangsung. Menyetujui syarat & ketentuan sayembara, serta mengunci nomor target tembak sayembara miliknya. |
| **Admin / Scorer** | **UC-28**: Menginput Hasil Sayembara | Scorer menginput lembar skor tembakan peserta sayembara. Sistem akan secara otomatis menyusun tabel skor global sayembara untuk memperebutkan hadiah utama sesuai aturan panitia. |
| **User / Admin** | **UC-29**: Mengelola Dompet Sayembara | User melacak transaksi keuangan masuk (wallet in) dari hadiah sayembara, atau biaya keluar (wallet out) untuk registrasi sayembara lewat tabel mutasi dompet digital. |

### Modul 5: Fitur Pendukung

| Actor | Use Case | Deskripsi |
|-------|----------|-----------|
| **User / Donatur** | **UC-30**: Melakukan Donasi Sosial | Donatur mengisi nominal donasi, memilih program donasi sosial aktif, memilih opsi donatur anonim (tanpa nama), mengunggah bukti transfer bank, dan Administrator Pusat melakukan verifikasi validasi keuangan donasi. |
| **User / Archer** | **UC-31**: Membuat & Berbagi Cerita | User mengunggah status cerita (*Stories*) dalam bentuk teks atau gambar berdurasi terbatas. Pengguna lain dapat melihat cerita, merekam view counts, dan memberikan reaction emotikon interaktif (react/unreact). |
| **User / Archer** | **UC-32**: Mengelola Lokasi & Kunjungan | User menandai peta digital titik latihan panahan (lokasi baru), menulis ulasan komentar lokasi, mengunggah foto lokasi, mencatat riwayat kunjungan (check-in visit), dan memverifikasi keaslian tempat latihan. |
| **Merchant / User** | **UC-33**: Transaksi Merchant Shop | Merchant mengunggah produk peralatan panahan, busur, kaos, anak panah, dll. Pembeli (User) meninjau daftar produk, menulis ulasan bintang penilaian, serta memberikan komentar dan sub-komentar produk. |
| **User / Archer** | **UC-34**: Watchlist Emiten Saham | User mengelola portofolio pantauan saham syariah pribadi (watchlist emiten), mengelompokkan emiten saham berdasarkan kategori industri, serta memanfaatkan kalkulator numerik tradisional hisab jumal huruf nama pengguna. |

---

## Catatan Temuan Tambahan

1. **Kompleksitas Logika Penilaian (Scoring & Bagan Aduan)**:
   - File controller turnamen panahan (`TournamentController.php` dan `TournamentAduanController.php`) menangani perhitungan poin yang sangat rumit seperti eliminasi model *Olympic Round* dengan penanganan tembakan penentu (*Shoot Off*) dan akumulasi poin regu/wilayah (*set point / total point*). Modul ini sangat dinamis karena harus mendukung berbagai variasi rules olahraga panahan tradisional.
   - Ditemukan kode generator otomatis turnamen yang sangat besar di `TournamentGeneratorController.php` (mencapai 429 KB) untuk mengotomatisasi pembuatan kategori turnamen spesifik regional (misal: "perpani", "fornas7", "surabaya-pesta-pora", "manahpro-2023", dll). Hal ini menandakan platform ini sering digunakan sebagai sistem inti pada berbagai event olahraga panahan besar di Indonesia.

2. **Kombinasi Domain Bisnis Multi-Tenant**:
   - Di dalam satu codebase yang sama, terdapat penyatuan domain bisnis yang sangat unik dan beragam (Archery, Stock Emiten Syariah, Donasi Sosial, dan Marketplace Merchant). Hal ini menunjukkan platform Manahpro berperan sebagai super-app komunitas bagi suatu ekosistem keagamaan dan olahraga tradisional secara bersamaan.

3. **Perhitungan Hisab Jumal**:
   - Terdapat fungsi unik `hitungHisabJumal` pada `EmitenController.php` yang mengindikasikan adanya metode perhitungan numerologi huruf arab tradisional (Abjad / Jumal) yang diintegrasikan dengan modul pemantauan pasar modal syariah (emiten saham).

4. **Integrasi Media Cloud & GCS**:
   - Aplikasi menggunakan penanganan dokumen yang terstandar dengan integrasi Google Cloud Storage (`gcs_status` pada berkas `documents`) untuk menjamin kecepatan pemuatan gambar KTA, bukti iuran klub, surat rekomendasi, status story, dan produk toko merchant.
