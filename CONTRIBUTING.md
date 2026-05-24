# PANDUAN KONTRIBUSI (CONTRIBUTING)

Panduan kontribusi untuk **Laravel Starter** — berfokus pada **Manajemen Git Best Practice** menggunakan **Git Flow**, alur kerja kolaborasi, kualitas kode, dan konvensi commit. Dokumen ini mengikat untuk seluruh kontributor, termasuk sesi pengembangan oleh developer maupun asisten AI.

Repository: `https://github.com/ramadhanrosihadi/laravel-starter`

---

## 1. Alur Kerja Branch (Git Flow)

Proyek ini mengadopsi alur kerja **Git Flow** standar industri, yang sangat cocok untuk rilis terjadwal (*Scheduled Releases*) dan pengembangan skala enterprise yang memerlukan isolasi ketat antar-lingkungan pengembangan.

### Struktur Branch Utama:

1. **Branch Utama (`main`)**:
   - Berisi kode versi *production* yang sangat stabil dan siap pakai oleh pengguna akhir.
   - Hanya menerima penggabungan (*merge*) dari branch **`release/*`** dan **`hotfix/*`**.
   - Setiap penggabungan ke `main` wajib disertai pembuatan **Tag** versi (misal: `v1.0.0`).
   - **Dilarang keras melakukan commit atau merge langsung** dari branch fitur ke `main`.

2. **Branch Integrasi (`develop`)**:
   - Menjadi pusat integrasi dari seluruh fitur baru yang sedang dikembangkan.
   - Seluruh branch **`feature/*`** dan **`bugfix/*`** dicabangkan dari `develop` dan wajib digabungkan kembali ke `develop`.
   - Kode di `develop` harus selalu dalam kondisi bisa di-build dan seluruh automated test wajib hijau (lulus 100%).

---

### Struktur Branch Pendukung (Temporary Branches):

#### A. Branch Fitur (`feature/*`)
* **Kegunaan**: Pengembangan fitur baru secara terisolasi.
* **Dicabangkan dari**: `develop`
* **Digabungkan ke**: `develop` (melalui Pull Request / PR)
* **Penamaan**: `feature/<nama-fitur>` (contoh: `feature/oauth-google`)

#### B. Branch Perbaikan Bug (`bugfix/*`)
* **Kegunaan**: Perbaikan bug non-kritis yang ditemukan di lingkungan development/staging sebelum rilis.
* **Dicabangkan dari**: `develop`
* **Digabungkan ke**: `develop` (melalui Pull Request / PR)
* **Penamaan**: `bugfix/<nama-bug>` (contoh: `bugfix/email-verification-timeout`)

#### C. Branch Persiapan Rilis (`release/*`)
* **Kegunaan**: Fase stabilisasi sebelum rilis ke production (hanya boleh berisi perbaikan bug minor dan pembaruan versi/dokumentasi, dilarang menambah fitur baru).
* **Dicabangkan dari**: `develop`
* **Digabungkan ke**: `main` (dengan Tag versi) dan `develop` (agar perbaikan bug rilis terintegrasi kembali)
* **Penamaan**: `release/<versi-rilis>` (contoh: `release/v1.0.0`)

#### D. Branch Perbaikan Cepat Staging/Production (`hotfix/*`)
* **Kegunaan**: Perbaikan darurat/kritis langsung pada sistem production yang aktif.
* **Dicabangkan dari**: `main`
* **Digabungkan ke**: `main` (dengan Tag versi baru) dan `develop` (atau branch `release` aktif jika ada)
* **Penamaan**: `hotfix/<nama-hotfix>` (contoh: `hotfix/payment-gateway-error`)

---

## 2. Konvensi Pesan Commit (Conventional Commits)

Kami menggunakan standar **[Conventional Commits](https://www.conventionalcommits.org/)** untuk menjaga kerapian riwayat git proyek (*git log*) agar mudah dibaca manusia dan mesin (untuk auto-generate changelog).

### Format Pesan Commit:
```
<type>(<scope opsional>): <subjek singkat, imperatif, ≤72 karakter>

[body opsional: deskripsi detail perubahan, alasan, dan konteks tambahan]

[footer opsional: referensi issue/task, misal: Closes #12, BREAKING CHANGE]
```

### Tipe Commit (`type`):
* `feat`: Fitur baru untuk pengguna aplikasi.
* `fix`: Perbaikan bug atau error.
* `chore`: Tugas administratif, update package/dependency, konfigurasi tooling.
* `docs`: Pembaruan dokumentasi (markdown, panduan).
* `refactor`: Perubahan struktur kode (bukan memperbaiki bug maupun menambah fitur baru).
* `style`: Perubahan visual, whitespace, semi-colon, formatting (Pint) tanpa mengubah logika program.
* `perf`: Peningkatan performa kode.
* `test`: Menambah, mengubah, atau memperbaiki berkas pengujian (automated tests).

### Prinsip Menulis Commit:
1. **Atomic Commits**: Buat commit kecil yang fokus pada **satu perubahan logis**. Jangan mencampur perbaikan bug, penulisan tes, dan update dokumentasi dalam satu commit raksasa di akhir hari.
2. **Gunakan Kalimat Imperatif**: Tulis subjek commit dalam bentuk perintah masa kini (misal: `feat(auth): add passport login` bukan `added passport login` atau `adds passport login`).
3. **Gaya Penulisan**: Huruf pertama subjek menggunakan huruf kecil, dan **jangan** akhiri subjek dengan tanda titik `.`.
4. **Keamanan Kode**: Jangan pernah men-stage atau meng-commit file rahasia (`.env`, private keys, password). Pastikan file tersebut sudah terdaftar di `.gitignore`.

---

## 3. Gerbang Kualitas Sebelum Commit (Quality Gate)

Sebelum Anda melakukan commit atau push, Anda **wajib** memastikan kode Anda bersih dan tidak merusak sistem dengan menjalankan perintah berikut secara lokal:

```bash
# 1. Format kode otomatis (sesuai PSR-12 & Laravel standards)
vendor/bin/pint

# 2. Analisis kode statis (memastikan tidak ada error tipe/logika terselubung)
vendor/bin/phpstan analyse

# 3. Jalankan seluruh automated tests (wajib lulus 100%)
php artisan test
```

> [!IMPORTANT]
> Jika salah satu dari proses di atas gagal atau menghasilkan warna merah, **perbaiki kodenya terlebih dahulu**. Jangan pernah memaksa commit kode yang rusak ke repository.

---

## 4. Proses Pull Request (PR) & Code Review

Pull Request (PR) adalah gerbang utama pertahanan kualitas kode di repository ini.

### Praktik Terbaik Pull Request:
1. **Buat PR yang Kecil dan Fokus**: Usahakan ukuran PR berada di bawah **200 baris kode** (LOC). PR yang kecil lebih cepat direview, lebih mudah dipahami, dan meminimalisir kemungkinan masuknya bug baru.
2. **Target Merge yang Sesuai**:
   - Untuk branch `feature/*` dan `bugfix/*`, pastikan target penggabungannya adalah branch **`develop`** (BUKAN `main`).
   - Untuk branch `release/*` dan `hotfix/*`, target penggabungannya adalah branch **`main`** dan branch **`develop`**.
3. **Gunakan Judul PR yang Jelas**: Samakan judul PR dengan format Conventional Commits (misal: `feat(auth): implement passport login flow`).
4. **Tulis Deskripsi PR secara Detail**:
   - **Tujuan**: Apa yang diselesaikan oleh PR ini?
   - **Perubahan Utama**: File mana saja yang dimodifikasi secara signifikan?
   - **Cara Menguji**: Langkah demi langkah untuk memverifikasi perubahan Anda.
   - **Visual/Media**: Tambahkan screenshot/GIF jika ada perubahan tampilan (UI).
5. **Gunakan Rebase untuk Sinkronisasi**:
   Sebelum meminta review, pastikan branch fitur Anda sinkron dengan branch `develop` terbaru menggunakan rebase:
   ```bash
   git checkout develop
   git pull origin develop
   git checkout <nama-branch-fitur>
   git rebase develop
   ```
   *Jika terjadi konflik, selesaikan konfliknya, kemudian lanjutkan rebase dengan `git rebase --continue`.*
6. **Wajib Code Review**: Setiap PR harus mendapatkan persetujuan (*Approved*) minimal dari **1 reviewer** (atau disepakati oleh tim/asisten AI) sebelum digabungkan.
7. **Hapus Branch Pasca-Merge**: Begitu PR sukses digabungkan (*merged*), segera hapus branch fitur tersebut baik di remote maupun lokal untuk menjaga kebersihan repositori.

---

## 5. Alur Kerja Commit & Push yang Benar

Jalankan perintah ini secara berurutan saat mengembangkan fitur menggunakan alur kerja Git Flow:

```bash
# 1. Tarik pembaruan terbaru dari develop sebelum membuat branch baru
git checkout develop
git pull origin develop

# 2. Buat branch fitur baru yang dicabangkan dari develop
git checkout -b feature/add-email-verification

# 3. Lakukan coding, lalu verifikasi Quality Gate (§3)
vendor/bin/pint && vendor/bin/phpstan analyse && php artisan test

# 4. Stage perubahan secara spesifik (hindari git add . secara membabi-buta)
git add app/Http/Controllers/Auth/ app/Models/User.php

# 5. Lakukan commit dengan pesan konvensional
git commit -m "feat(auth): add email verification logic & controller"

# 6. Push ke remote repository
git push -u origin feature/add-email-verification
```

### Aturan Tambahan Penggunaan Git:
- **Jangan pernah menggunakan `git push --force`** pada branch utama (`main`/`develop`) atau branch bersama. Jika Anda perlu memperbarui branch fitur pribadi setelah rebase, gunakan opsi yang lebih aman: `git push --force-with-lease`.
- Lakukan **push secara berkala** sepanjang sesi kerja Anda. Jangan menumpuk perubahan selama berhari-hari di lokal tanpa di-push ke remote.

---

## 6. Checklist Cepat Sebelum Mengakhiri Sesi Kerja

Sebelum Anda meninggalkan sesi kerja atau menandai tugas selesai, pastikan Anda mencentang semua hal berikut:

- [ ] Seluruh **Quality Gate** sudah berjalan dengan hasil hijau bersih (Pint, Larastan, Test).
- [ ] Riwayat commit dibuat secara **atomik** (kecil dan bertahap) dengan standar **Conventional Commits**.
- [ ] Tidak ada file kredensial/rahasia (`.env`, file log, certificate) yang tidak sengaja ter-commit.
- [ ] File `.env.example` telah diperbarui jika ada variabel environment baru yang ditambahkan.
- [ ] Dokumentasi terkait (seperti berkas API atau README) telah diperbarui sesuai perubahan kode.
- [ ] Seluruh kode lokal telah sukses di-`push` ke remote repository.
