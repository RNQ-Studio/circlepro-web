# DOKUMENTASI FITUR UTAMA (LARAVEL STARTER)

Selamat datang di Laravel Starter! Dokumen ini dirancang khusus untuk membantu tim pengembang yang baru saja melakukan *clone* pada repositori ini. Starter project ini dibangun dengan standar arsitektur monolitik modern, berfokus pada performa tinggi, keamanan tingkat tinggi, integrasi *mobile client* (Flutter) yang mulus, serta *Developer Experience* (DX) berkelas premium.

Berikut adalah katalog lengkap fitur, arsitektur, dan utilitas yang siap digunakan untuk mempercepat proses pengembangan Anda.

---

## 1. Sistem Autentikasi, Otorisasi, & Sesi (Multi-Guard & RBAC)

Starter project ini mendukung dua jalur autentikasi yang berbeda di atas satu basis data pengguna dan logika bisnis yang sama.

```
┌────────────────────────────────────────────────────────────────────────┐
│                          LARAVEL APPLICATION                           │
│                                                                        │
│  ┌────────────────────────┐          ┌──────────────────────────────┐  │
│  │   API Routes (Mobile)  │          │   Web Routes (Back-office)   │  │
│  │   routes/api.php       │          │   Filament Panel             │  │
│  │   guard: api (Passport)│          │   guard: web (session)       │  │
│  └───────────┬────────────┘          └───────────────┬──────────────┘  │
│              │                                       │                 │
│              ▼                                       ▼                 │
│  ┌────────────────────────┐          ┌──────────────────────────────┐  │
│  │   API Controllers      │          │  Filament Resources & Pages  │  │
│  │   (Stateless Client)   │          │  (Stateful Admin)            │  │
│  └───────────┬────────────┘          └───────────────┬──────────────┘  │
│              │                                       │                 │
│              └───────────────────┬───────────────────┘                 │
│                                  ▼                                     │
│                      ┌───────────────────────┐                         │
│                      │      SERVICE LAYER    │ ◄── Logika Bisnis       │
│                      │      app/Services/*   │     Bersama             │
│                      └───────────┬───────────┘                         │
│                                  ▼                                     │
│                      ┌───────────────────────┐                         │
│                      │    ELOQUENT MODELS    │ ◄── Layer Akses Data    │
│                      │    app/Models/*       │                         │
│                      └───────────────────────┘                         │
└────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Autentikasi API via Laravel Passport (OAuth2)
Untuk kebutuhan integrasi aplikasi seluler (Flutter) yang aman, sistem menggunakan **Laravel Passport** dengan alur **Password Grant (Proxy Pattern)**.
* **Proxy Pattern**: Endpoint API tidak mengekspos rute bawaan `/oauth/token` secara langsung kepada publik. Sebaliknya, `AuthController` menerima permintaan, dan `AuthService` memanggil grant internal untuk menyembunyikan detail sensitif klien (*Client ID* & *Secret*) di tingkat server.
* **Token Lifecycle**: Konfigurasi masa aktif token diatur secara otomatis di `AppServiceProvider` (Access Token aktif selama **8 jam**, Refresh Token aktif selama **30 hari**).
* **Fitur Utama API Auth**:
  * **Registrasi Mandiri**: `POST /api/v1/auth/register` dengan validasi terstruktur dan proteksi *rate-limiting*.
  * **Login Tradisional**: `POST /api/v1/auth/login` menghasilkan `access_token`, `refresh_token`, dan `expires_in`.
  * **Refresh Token**: `POST /api/v1/auth/refresh` untuk memperbarui sesi tanpa memaksa pengguna masuk ulang.
  * **Logout Tunggal**: `POST /api/v1/auth/logout` yang mencabut (*revoke*) token aktif saat ini secara aman.
  * **Logout Massal (Logout All)**: `POST /api/v1/auth/logout-all` yang mencabut semua token aktif di seluruh perangkat dan membersihkan push tokens dari database jika perangkat pengguna hilang.
  * **Profil Akun**: `GET /api/v1/auth/me` untuk mengambil profil pengguna, `PUT /api/v1/auth/me` untuk memperbarui data pribadi, dan `POST /api/v1/auth/avatar` untuk mengunggah foto profil secara aman.

### 1.2 Otentikasi OTP (One-Time Password)
Untuk kebutuhan login tanpa kata sandi atau verifikasi telepon, sistem menyediakan layanan OTP terintegrasi:
* **Alur OTP Terstandardisasi**: Rute `POST /api/v1/auth/otp/send` dan `POST /api/v1/auth/otp/verify` yang diproteksi *rate-limiting* ketat untuk mencegah serangan *brute-force* sms/whatsapp.
* **Token Sesi Penuh**: Proses verifikasi OTP sukses mengembalikan respons token OAuth2 standar (`access_token` + `refresh_token`) lewat sistem proxy Passport, memberikan pengalaman sesi berkelanjutan yang aman kepada pengguna.

### 1.3 Verifikasi Email & Pemulihan Sandi Mandiri
* **Verifikasi Email**: Pengguna baru wajib memverifikasi alamat email mereka melalui token sebelum diizinkan mengakses resource terproteksi API. Layanan verifikasi dikelola via `POST /api/v1/auth/email/send-verification` dan `POST /api/v1/auth/email/verify`.
* **Pemulihan Password**: Sistem menyediakan endpoint API lupa sandi (`POST /api/v1/auth/forgot-password`) yang mengirimkan tautan token verifikasi aman ke email pengguna, dan endpoint reset (`POST /api/v1/auth/reset-password`) untuk memperbarui sandi baru.

### 1.4 Kontrol Akses berbasis Peran (Multi-Guard RBAC)
Sistem otorisasi dikelola secara dinamis menggunakan paket **`spatie/laravel-permission`** yang dikonfigurasi secara harmonis untuk **Multi-Guard**:
* **Keselarasan Guard**: Seluruh *role* dan *permission* didefinisikan pada guard `web` standar. Namun, karena guard `web` (session) dan `api` (Passport) merujuk pada provider user yang sama (`User` model), metode pengecekan seperti `$user->hasRole()` atau `$user->can()` bekerja dengan sempurna dan konsisten baik di Panel Admin Filament maupun di Controller API.
* **Bypass Super-Admin**: Menggunakan `Gate::before` di `AuthServiceProvider` (atau `AppServiceProvider` di Laravel 11) untuk mengizinkan pengguna dengan peran `super-admin` melewati seluruh pengecekan otorisasi tanpa pendaftaran *permission* manual.
* **Daftar Role Bawaan Seeder**:
  * `super-admin`: Memiliki hak akses penuh tanpa batas di seluruh sistem.
  * `admin`: Mengelola pengguna, konfigurasi, data master kategori, namun hanya memiliki hak baca (*read-only*) pada manajemen peran.
  * `staff`: Memiliki akses terbatas untuk mengelola konfigurasi aplikasi dan kategori data master tanpa hak hapus (*no delete permission*).

---

## 2. Standardisasi API Response & Centralized Error Handling

Untuk memastikan integrasi client Flutter berjalan mulus tanpa kegagalan parsing JSON, starter ini mendefinisikan standardisasi kontrak response yang konsisten.

### 2.1 Envelope JSON Seragam
Seluruh respons API dibungkus oleh helper terpusat `app/Support/ApiResponse.php`.

* **Format Respons Sukses (`ApiResponse::success`)**:
  ```json
  {
    "success": true,
    "message": "OK",
    "data": {
      "id": 1,
      "name": "Jane Doe"
    }
  }
  ```

* **Format Respons Gagal (`ApiResponse::error`)**:
  ```json
  {
    "success": false,
    "message": "The given data was invalid.",
    "code": "VALIDATION_FAILED",
    "errors": {
      "email": [
        "The email field is required."
      ]
    }
  }
  ```

### 2.2 Resolusi Metadata Pagination Otomatis
Developer tidak perlu lagi menulis kode berulang (*boilerplate*) untuk memecah data paginasi Eloquent di controller.
> [!TIP]
> Helper `ApiResponse::success()` secara cerdas mendeteksi instansi data bertipe `AnonymousResourceCollection` atau `AbstractPaginator` dan otomatis meresolusinya ke format envelope standar dengan menyematkan objek `meta.pagination`.

```json
{
  "success": true,
  "message": "Categories retrieved successfully.",
  "data": [ ... ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 45,
      "last_page": 3
    }
  }
}
```

### 2.3 Centralized Exception Handler
Seluruh kesalahan (*exception*) tak tertangani yang terjadi pada jalur rute API ditangkap secara global di `bootstrap/app.php` dan dikembalikan dalam bentuk JSON terstruktur:
* `ValidationException` → Mengembalikan HTTP `422` dengan objek `errors` detail per-kolom kustom dan kode `VALIDATION_ERROR`.
* `AuthenticationException` → Mengembalikan HTTP `401` dengan pesan terstandar dan kode `UNAUTHENTICATED`.
* `AuthorizationException` → Mengembalikan HTTP `403` dengan pesan terstandar dan kode `FORBIDDEN`.
* `ModelNotFoundException` atau `NotFoundHttpException` → Mengembalikan HTTP `404` dan kode `NOT_FOUND`.
* `HttpExceptionInterface` → Mengembalikan status HTTP asli dari kesalahan server dengan kode `HTTP_ERROR`.
* `Throwable` (Error 500 lainnya) → Mengembalikan HTTP `500`. Jika aplikasi berada pada mode debug (`APP_DEBUG=true`), pesan detail *error* ditampilkan; jika di production, disembunyikan sebagai pesan umum `"Server error."` demi alasan keamanan.

### 2.4 Standardisasi Backed Enum Kode Error API
Untuk memudahkan Flutter *client* dalam melakukan percabangan logika (*branching error handling*) tanpa melakukan pencocokan string teks pesan secara manual, sistem mendefinisikan backed enum kaku `App\Support\Enums\ApiErrorCode`:

| Kasus Enum | Nilai String | Deskripsi Penggunaan |
|---|---|---|
| `AuthInvalidCredentials` | `AUTH_INVALID_CREDENTIALS` | Kredensial login email/password salah |
| `AuthInactiveAccount` | `AUTH_INACTIVE_ACCOUNT` | Akun dinonaktifkan (`is_active = false`) |
| `AuthTokenExpired` | `AUTH_TOKEN_EXPIRED` | Sesi token akses OAuth kedaluwarsa |
| `ValidationFailed` | `VALIDATION_FAILED` | Payload input tidak lolos validasi Form Request |
| `ResourceNotFound` | `RESOURCE_NOT_FOUND` | ID entitas tidak ditemukan di database |
| `RateLimitExceeded` | `RATE_LIMIT_EXCEEDED` | Batas maksimum request dilewati (Throttle) |
| `MaintenanceMode` | `MAINTENANCE_MODE` | Aplikasi sedang dalam masa pemeliharaan |
| `ServerError` | `SERVER_ERROR` | Terjadi kegagalan fatal pada server internal |

---

## 3. Desain Pola Modular & Data Master Generik

Project ini menerapkan arsitektur bersih yang sangat ramah terhadap pengembangan paralel. Setiap modul mengikuti alur pemrosesan data: **Routes → Controller → Service → Eloquent Model → Database**.

### 3.1 Pola CRUD Data Master (Referensi Modul `Category`)
Sebagai contoh acuan pengembang baru (*reference template*), modul `Category` diimplementasikan secara komprehensif:
* **Eloquent Model**: Menggunakan *Soft Deletes* bawaan, enkapsulasi status `is_active`, dan pencarian slug otomatis.
* **Integrasi `spatie/laravel-query-builder`**: Menyediakan API pencarian data master yang sangat fleksibel dengan proteksi keamanan injeksi SQL:
  * **Whitelist Filtering**: Pengguna API dapat menyaring kategori berdasarkan kolom tertentu (mis. `filter[is_active]=true`).
  * **Whitelist Sorting**: Pengurutan data yang aman (mis. `sort=-name` untuk desending).
  * **Searching**: Dukungan pencarian teks parsial secara instan.

### 3.2 Database Geografis Indonesia Master (`Region`)
Starter project ini menyertakan skema basis data wilayah administratif Indonesia terlengkap (~245.000 data rekursif dari Provinsi hingga Kelurahan/Desa) dengan struktur hirarki *self-referencing parent-child*:
* **Seeder Wilayah Offline**:
  > [!IMPORTANT]
  > Guna menghindari kegagalan proses seeding di lingkungan CI/CD terisolasi akibat gangguan koneksi server pihak ketiga, seeding wilayah dikonfigurasi untuk membaca dari berkas **JSON Fixtures lokal** di storage.
  * Pengembang dapat menggunakan perintah console kustom `app/Console/Commands/RegionsDownloadCommand.php` untuk mengunduh berkas fisik wilayah secara lokal terlebih dahulu.

---

## 4. Sistem Notifikasi Asinkron & FCM (Firebase Cloud Messaging)

Sistem pengiriman notifikasi dibangun dengan fokus pada performa respons endpoint API agar aplikasi mobile tetap terasa sangat responsif dan hemat baterai.

```
                  [ Flutter / Mobile Client ]
                               ▲
                               │ Push Notification (FCM)
                               │
                       ┌───────┴───────┐
                       │ Firebase Cloud│
                       │   Messaging   │
                       └───────▲───────┘
                               │
    [ API Client ]             │ (Asynchronous Job)
          │                    │
          ▼                    │
    ┌───────────┐        ┌─────┴───────────────┐
    │Controller │ ──────►│SendPushNotification │ (Diproses oleh Worker)
    └───────────┘        │         Job         │
    (Respon Instan)      └───────▲─────────────┘
                                 │
                                 │ (Simpan data log)
                                 ▼
                         ┌──────────────┐
                         │ Database Logs│
                         │(Notifications│
                         │    Table)    │
                         └──────────────┘
```

* **Antrean Latar Belakang (Queue Worker)**:
  > [!TIP]
  > Proses pengiriman notifikasi ke Firebase FCM diproses secara asinkron menggunakan antrean latar belakang `SendPushNotificationJob`. Ini memotong penundaan respons HTTP API (hemat 2-5 detik per request) karena controller tidak perlu menunggu transaksi eksternal Firebase selesai.
* **Pelacakan Perangkat (`UserDevice`)**: Setiap pengguna dapat memiliki banyak perangkat (*multi-devices*). Sistem secara otomatis mengelola registrasi ID perangkat unik, sistem operasi (`android` / `ios`), versi aplikasi, dan token pendaftaran push FCM terbaru.
* **Riwayat Log Notifikasi (`Notification`)**: Setiap notifikasi yang terkirim/gagal disimpan secara terstruktur di database dengan skema tipe, data payload kustom, dan status baca `read_at` untuk manajemen notifikasi *in-app* di Flutter.

---

## 5. Panel Administrasi Premium (Filament v3)

Back-office dikelola secara eksklusif menggunakan **Filament v3** dengan kustomisasi visual premium untuk memberikan kesan produk berkelas tinggi dan siap produksi.

* **Tema Visual Premium**: Panel admin dihias menggunakan palet warna dasar elegan `Color::Indigo` yang modern dan nyaman di mata.
* **Branding Adaptif Dark/Light Mode**: Menggunakan logo kustom premium (`logo-light.svg` dan `logo-dark.svg`) yang secara dinamis beradaptasi dengan preferensi tema sistem pengguna atau browser.
* **Keamanan Menu & Akses Granular**:
  > [!CAUTION]
  > Panel admin dilindungi secara ketat di tingkat resource. Filament Resources (Users, Roles, Configs, dll.) wajib terikat dengan Spatie Policy masing-masing. User dengan tingkat peran rendah (`staff`) tidak akan dapat melihat menu administratif di panel samping, memblokir upaya bypass CRUD secara ilegal.
* **Fitur-fitur Admin Panel yang Tersedia**:
  * **User Management**: CRUD data user lengkap, aktivasi/deaktivasi status akun, dan penetapan peran (*role assignment*).
  * **Role & Permission Management**: Pengelolaan peran Spatie secara visual lengkap dengan pemilihan daftar hak akses granular.
  * **App Configs Management**: Manajemen konfigurasi aplikasi dinamis (seperti pengaturan mode pemeliharaan *maintenance*, dll.).
  * **App Versions Management**: Kontrol pembaruan aplikasi mobile Android & iOS (menegakkan pembaruan paksa *force update*).
  * **Interactive Notification Sender**: Halaman khusus admin untuk mengirimkan notifikasi *push* secara manual ke pengguna tertentu atau siaran massal (*broadcast*).
  * **Activity Log Viewer**: Antarmuka visual untuk melacak audit trail aktivitas perubahan data master.

---

## 6. Fitur Keamanan & Audit Trail Internal

* **Enforce HTTPS di Production**: Sistem otomatis mendeteksi environment aktif. Saat berada di mode `production`, rute dan seluruh aset dipaksa menggunakan skema HTTPS aman (`URL::forceScheme('https')` di `AppServiceProvider`) untuk menggagalkan serangan MITM.
* **Database Transactions pada Concurrency Tinggi**:
  > [!IMPORTANT]
  > Operasi sensitif seperti inisialisasi ganda perangkat baru (*device registration upsert*) saat login berbarengan dibungkus dalam transaksi database aman guna menangkap `UniqueConstraintViolationException` dan mengalihkan ke proses *graceful update* untuk mencegah database crash akibat *race condition*.
* **Audit Trail Lengkap Spatie Activitylog**:
  Seluruh aktivitas penambahan, perubahan, dan penghapusan data master kritis (`User`, `Category`, `AppConfig`, `AppVersion`) dicatat secara otomatis ke dalam database audit log. Informasi mencakup pelaku perubahan (*causer*), target data (*subject*), serta perbandingan nilai lama dan nilai baru (*old vs new values*).
* **Konfigurasi CORS Eksplisit**: Berkas `config/cors.php` telah dipublikasikan dan dikonfigurasi dengan aman secara mendalam untuk mencegah serangan CORS ilegal dari domain tidak terdaftar.

---

## 7. Developer Experience (DX) & Tooling Premium

Kami percaya bahwa kualitas kode yang baik berawal dari alat bantu (*tooling*) yang hebat bagi para developer.

### 7.1 Containerization Sail & Docker
Developer tidak perlu melakukan instalasi manual PHP, database, atau redis di sistem operasi lokal mereka. Starter ini menyediakan berkas `compose.yaml` siap pakai yang terintegrasi dengan **Laravel Sail**:
* **Layanan Kontainer**:
  * PHP 8.3 & Node.js terbaru
  * PostgreSQL 18 sebagai database utama
  * Redis sebagai driver cache & antrean (*queue*)
  * Mailpit sebagai server penguji pengiriman email lokal

### 7.2 Alur Perintah Universal (Composer / Makefile)
Untuk memudahkan operasional di semua sistem operasi (termasuk Windows, macOS, dan Linux), starter ini dilengkapi dengan perintah berbasis **Composer** sebagai perintah utama, serta **Makefile** sebagai jalan pintas opsional:

| Pintasan Makefile | Perintah Utama (Universal) | Fungsi Utama |
|---|---|---|
| `make setup` | `composer run setup` | Melakukan instalasi paket, menyalin `.env`, key-gen, migrasi, dan build aset |
| `make dev` | `composer run dev` | Menjalankan server dev, antrean queue, log pail, dan vite secara paralel |
| `make test` | `composer test` | Menjalankan seluruh pengujian unit dan fitur (PHPUnit) |
| `make lint` | `composer lint` | Memformat gaya penulisan kode sesuai aturan PSR-12 secara otomatis (Pint) |
| `make analyse` | `composer analyse` | Menjalankan analisis statis kode tingkat ketat (PHPStan/Larastan) |
| `make fresh` | `php artisan migrate:fresh --seed` | Menyegarkan database lokal dan mengisi ulang data awal (seeder) |
| `make quality` | `composer lint && composer analyse && composer test` | Melakukan audit kualitas kode lengkap (linting + analisis + testing) |

### 7.3 Infrastruktur Testing & Fallback Dinamis
* **Cakupan Tes Tinggi**: Pengujian otomatis mencakup Feature Tests dan Unit Tests dengan tingkat *coverage* Filament Back-Office mencapai **~85%** dan Services Layer diuji secara terisolasi murni dengan melatih *Mocking* dependencies FCM dan SMS.
* **Dual Database Engine Testing**:
  > [!TIP]
  > Untuk menyamakan environment testing dengan produksi, pengujian berjalan di atas database PostgreSQL terpisah secara bawaan (`phpunit.xml`). Namun, jika PostgreSQL lokal mati, sistem memiliki **fallback dinamis otomatis ke SQLite `:memory:`** agar pengujian tetap dapat berjalan lancar di komputer developer.

### 7.4 Pipeline CI/CD GitHub Actions
Setiap *Pull Request* dan *push* ke cabang utama divalidasi secara otomatis melalui berkas workflow `.github/workflows/ci.yml`. Pipeline akan menolak kode jika:
1. Format koding melanggar aturan Pint (Linter).
2. Terdapat bias tipe data di Larastan level 5 (Static Analysis).
3. Ada satu atau lebih tes yang gagal di PHPUnit.

### 7.5 Template Kolaborasi GitHub
Untuk menjaga kualitas laporan dan kontribusi pengembang eksternal tetap terstruktur:
* **Template Issue**: Terpasang otomatis berkas formulir `bug_report.md` dan `feature_request.md` di `.github/ISSUE_TEMPLATE/`.
* **Template Pull Request**: Terpasang berkas petunjuk pengisian perubahan `pull_request_template.md` di `.github/`.

---

Selamat berkolaborasi dan mengembangkan aplikasi hebat di atas fondasi kokoh Laravel Starter ini! Untuk panduan instalasi cepat dan cara menjalankan proyek, silakan merujuk langsung ke berkas utama [README.md](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/README.md). Jika Anda menemukan celah keamanan sensitif, harap membaca instruksi pelaporan privat di file [SECURITY.md](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/SECURITY.md).
