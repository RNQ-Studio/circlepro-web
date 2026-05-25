# Panduan Lengkap: Menambahkan API Baru di Laravel Starter (Best Practice)

Dokumen ini adalah panduan **step-by-step** untuk menambahkan modul API baru di proyek **Laravel Starter**, mulai dari **Step 0 (belum ada tabel sama sekali)** hingga API ter-dokumentasi, teruji, dan lolos quality gate.

Tujuannya: siapa pun yang mengerjakan modul baru **tidak perlu menebak-nebak** — cukup ikuti pola yang sudah baku di proyek ini sehingga hasilnya konsisten, aman, dan mudah dirawat.

Sebagai contoh kasus nyata, kita akan membangun **Quotes Management API** (Manajemen Kutipan). API ini mendukung:

| Operasi | Endpoint | Keterangan |
|---|---|---|
| **Create** | `POST /api/v1/quotes` | Menambah kutipan baru |
| **Get All / List** | `GET /api/v1/quotes` | Daftar kutipan + pagination |
| **Get / Show** | `GET /api/v1/quotes/{quote}` | Detail satu kutipan |
| **Update** | `PUT/PATCH /api/v1/quotes/{quote}` | Mengubah kutipan |
| **Delete** | `DELETE /api/v1/quotes/{quote}` | Hapus kutipan (soft delete) |
| **Search** | `GET /api/v1/quotes?filter[search]=einstein` | Cari di teks **atau** penulis |
| **Filter & Sort** | `GET /api/v1/quotes?filter[is_active]=1&sort=-created_at` | Filter + pengurutan ter-whitelist |

---

## 📋 Daftar Isi

1. [Prasyarat & Prinsip Arsitektur](#-prasyarat--prinsip-arsitektur)
2. [Peta Alur Request → Response](#-peta-alur-request--response)
3. [Catatan Lingkungan Lokal (Windows)](#-catatan-lingkungan-lokal-windows)
4. [Step 0 — Migrasi Database](#step-0--migrasi-database)
5. [Step 1 — Model & Factory](#step-1--model--factory)
6. [Step 2 — RBAC & Policy](#step-2--rbac--policy)
7. [Step 3 — Form Requests (Validasi + Otorisasi Write)](#step-3--form-requests-validasi--otorisasi-write)
8. [Step 4 — API Resource (Transformasi Output)](#step-4--api-resource-transformasi-output)
9. [Step 5 — Controller Tipis + Spatie Query Builder](#step-5--controller-tipis--spatie-query-builder)
10. [Step 6 — Registrasi Route](#step-6--registrasi-route)
11. [Step 7 — Bentuk Response Envelope (Kontrak API)](#step-7--bentuk-response-envelope-kontrak-api)
12. [Step 8 — Dokumentasi API Otomatis (Scramble)](#step-8--dokumentasi-api-otomatis-scramble)
13. [Step 9 — Filament Back-Office (Opsional)](#step-9--filament-back-office-opsional)
14. [Step 10 — Feature Test API](#step-10--feature-test-api)
15. [Step 11 — Test Back-Office](#step-11--test-back-office)
16. [Step 12 — Quality Gates](#step-12--quality-gates)
17. [Step 13 — Commit, CHANGELOG & Git Flow](#step-13--commit-changelog--git-flow)
18. [✅ Checklist Final](#-checklist-final)
19. [📁 Lampiran: Ringkasan File](#-lampiran-ringkasan-file)

---

## 🏗 Prasyarat & Prinsip Arsitektur

Sebelum menyentuh kode, pahami dulu **aturan main** proyek ini. Semua ini **OVERRIDE** preferensi pribadi — ikuti apa adanya.

| Prinsip | Penjelasan |
|---|---|
| **Tanpa Repository Pattern** | Akses Eloquent **langsung** di Controller/Service. Jangan buat interface/class repository. |
| **Controller Super Tipis** | Idealnya < 20 baris per method. Tidak ada logika bisnis, tidak ada query kompleks, tidak ada `try-catch`. |
| **Service hanya bila perlu** | Buat `app/Services/*` **hanya** jika ada logika bisnis nyata (lintas tabel, kalkulasi, integrasi pihak ke-3, trigger event). **CRUD murni seperti Quote TIDAK perlu Service.** |
| **Form Request untuk write** | Validasi input **dan** otorisasi `create`/`update` dilakukan di Form Request, bukan di controller. |
| **API Resource untuk output** | **Jangan pernah** mengembalikan model Eloquent mentah. Selalu lewat API Resource. |
| **Envelope JSON standar** | Semua response lewat `App\Support\ApiResponse::success()` / `::error()`. |
| **Exception terpusat** | Error dipetakan global di `bootstrap/app.php`. Jangan tangani manual di controller. |
| **RBAC wajib** | Tiap resource didaftarkan di `RolePermissionSeeder` + dilindungi Policy berbasis `spatie/laravel-permission`. |
| **PostgreSQL** | DB proyek adalah PostgreSQL 16/17. Pertimbangkan perilaku spesifik Postgres (mis. `ILIKE` untuk pencarian case-insensitive). |

### Dokumen yang wajib dibaca

- **`CLAUDE.md`** — pedoman agent & konvensi inti.
- **`docs/architecture.md`** — arsitektur, layering, standar response.
- **`docs/data_master_pattern.md`** — ringkasan pola data master (acuan langkah ini).
- **`CONTRIBUTING.md`** — Git Flow, Conventional Commits, quality gate, **kewajiban update CHANGELOG**.

### Blueprint resmi: `Category`

Model **`Category`** beserta seluruh artefaknya (migration, model, factory, policy, form request, resource, controller, Filament resource, test) adalah **blueprint resmi**. Saat ragu, buka file `Category` yang setara dan tiru polanya. Tutorial ini pada dasarnya mereplikasi `Category` → `Quote` sambil menambahkan fitur **search**.

---

## 🗺 Peta Alur Request → Response

Setiap layer punya satu tanggung jawab dan **tidak boleh** melanggar batas layer lain.

```
        HTTP Request
             │
             ▼
   routes/api.php  ──────────────►  middleware: auth:api, check.maintenance
             │
             ▼
   Form Request (Store/Update)  ──►  rules() = validasi  |  authorize() = cek Policy (create/update)
             │  (data sudah bersih & terotorisasi)
             ▼
   Controller (TIPIS)  ───────────►  read/delete: $this->authorize(...)
             │                        panggil Model (atau Service bila kompleks)
             ▼
   Eloquent Model  ───────────────►  scope, cast, relasi, SoftDeletes, ActivityLog
             │
             ▼
   API Resource  ─────────────────►  Model → array JSON (kontrak stabil)
             │
             ▼
   ApiResponse::success()/error()  ►  { success, message, data, meta }
             │
             ▼
        HTTP Response
```

| Layer | Tugas | ❌ Dilarang |
|---|---|---|
| **Route** | Definisi endpoint + middleware + route binding | Logika apa pun |
| **Form Request** | `rules()`, `authorize()` (create/update) | Side-effect / query bisnis |
| **Controller** | Otorisasi read/delete, panggil model/service, balas via Resource+ApiResponse | Logika bisnis, query kompleks, try-catch |
| **Service** *(opsional)* | Logika bisnis, transaksi, orkestrasi | Tahu soal HTTP request/response |
| **Model** | Skema, cast, relasi, **scope** | Logika bisnis lintas-entitas |
| **API Resource** | Transformasi model → JSON | Query (memicu N+1) |
| **Policy** | Aturan otorisasi per-ability | — |

> [!IMPORTANT]
> **Kapan butuh Service?** Hanya jika ada logika nyata: validasi lintas tabel, kalkulasi, panggil API eksternal, kirim notifikasi, transaksi multi-tabel. Untuk Quote (CRUD + search), **tidak perlu Service**. Jangan buat Service kosong yang cuma meneruskan panggilan — itu menambah lapisan tanpa manfaat.

---

## 💻 Catatan Lingkungan Lokal (Windows)

> [!NOTE]
> Bagian ini spesifik untuk mesin dev Windows proyek ini. Jika Anda di Linux/Mac/Sail, abaikan dan gunakan perintah standar (`php artisan ...`).

- **PHP**: `php` default di PATH bisa jadi versi lama (7.4). Laravel 13 butuh **PHP 8.3+**. Gunakan interpreter 8.3, contoh (Git Bash): `export PATH="/c/php8.3.6:$PATH"` sebelum menjalankan `php`/`composer`.
- **Database test**: PHPUnit berjalan pada database PostgreSQL terpisah **`laravel_starter_test`** (lihat `phpunit.xml`). Build PHP lokal **tidak punya driver SQLite**, jadi `:memory:` tidak bisa dipakai. Pastikan DB test sudah dibuat sekali sebelum `php artisan test`.
- **Static analysis**: Larastan butuh memori lebih besar — selalu pakai `--memory-limit=1G` (sudah otomatis di `composer analyse`).

Sepanjang tutorial, perintah ditulis dalam bentuk **kanonik** (`php artisan ...`, `vendor/bin/...`, atau shortcut `composer ...`). Sesuaikan prefix PHP 8.3 bila perlu di mesin Anda.

---

## Step 0 — Migrasi Database

Mulai dari fondasi: definisikan tabel.

### 0.1 Generate file migrasi

```bash
php artisan make:migration create_quotes_table
```

Akan terbentuk `database/migrations/YYYY_MM_DD_HHMMSS_create_quotes_table.php`.

### 0.2 Desain skema

Buka file tersebut dan isi seperti berikut. Pola kolom mengikuti blueprint `categories`: `id`, kolom bisnis, `is_active`, `timestamps`, `softDeletes`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->text('text');                          // isi kutipan (bisa panjang → text)
            $table->string('author');                      // nama penulis
            $table->string('source')->nullable();          // sumber: buku, pidato, dll (opsional)
            $table->boolean('is_active')->default(true);   // status tayang
            $table->timestamps();                          // created_at, updated_at
            $table->softDeletes();                         // deleted_at (soft delete)

            // Index untuk kolom yang sering difilter/diurutkan.
            $table->index('author');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
```

> [!TIP]
> **Pilih tipe kolom dengan sadar.** `text` untuk konten panjang (isi kutipan), `string` (VARCHAR 255) untuk nama/sumber. Tambahkan `index()` pada kolom yang akan jadi target filter/sort (`author`, `is_active`) agar query tidak melakukan *full table scan*.

### 0.3 Jalankan migrasi

```bash
php artisan migrate
```

---

## Step 1 — Model & Factory

### 1.1 Model `app/Models/Quote.php`

> [!IMPORTANT]
> **Aturan model di proyek ini (wajib):**
> 1. **PHPDoc `@property`** di atas kelas — deklarasikan setiap kolom + tipenya. Membantu IDE & Larastan (acuan: `User.php`).
> 2. **Attribute `#[Fillable([...])]`** (PHP Attribute modern), **bukan** property `$fillable`.
> 3. Trait **`LogsActivity`** (audit trail otomatis) + **`SoftDeletes`** (karena migrasi pakai `softDeletes()`).
> 4. Method **`casts()`** untuk tipe non-string (boolean, date, dst.).
> 5. Hint factory: `/** @use HasFactory<QuoteFactory> */`.
>
> Untuk fitur **search**, kita tambahkan satu **query scope** `scopeSearch()` — scope adalah tanggung jawab model yang sah, dan menjaga controller tetap tipis.

```php
<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $text
 * @property string $author
 * @property string|null $source
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['text', 'author', 'source', 'is_active'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Cari quote berdasarkan teks ATAU nama penulis.
     * Memakai ILIKE (PostgreSQL) agar pencarian case-insensitive.
     *
     * @param  Builder<Quote>  $query
     * @return Builder<Quote>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term): void {
            $query->where('text', 'ilike', "%{$term}%")
                ->orWhere('author', 'ilike', "%{$term}%");
        });
    }
}
```

**Kenapa begini:**

| Bagian | Alasan |
|---|---|
| `@property` docblock | Menghilangkan warning "magic property" & memberi autocomplete. |
| `#[Fillable([...])]` | Whitelist mass-assignment. **Jangan** masukkan `id`, `created_at`, `updated_at`, `deleted_at`. |
| `LogsActivity` | Setiap create/update/delete tercatat ke `activity_log`. |
| `SoftDeletes` | `delete()` hanya mengisi `deleted_at`; data tetap bisa dipulihkan. |
| `casts(): is_active => boolean` | Response selalu `true/false`, bukan `1/0`. |
| `scopeSearch()` | Pencarian gabungan `text` + `author` dalam satu parameter. `ILIKE` = LIKE case-insensitive khas Postgres. Dipanggil oleh Query Builder lewat `AllowedFilter::scope('search')` (Step 5). |

> [!NOTE]
> Berbeda dari `Category`, `Quote` **tidak punya `slug`**, sehingga tidak ada `prepareForValidation()` untuk auto-slug maupun aturan `unique`. Hanya tiru bagian yang relevan dengan domain Anda — jangan menyalin membabi buta.

### 1.2 Factory `database/factories/QuoteFactory.php`

```bash
php artisan make:factory QuoteFactory --model=Quote
```

```php
<?php

namespace Database\Factories;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'text' => fake()->paragraph(2),
            'author' => fake()->name(),
            'source' => fake()->optional(0.7)->sentence(3),
            'is_active' => true,
        ];
    }

    /**
     * State: kutipan non-aktif.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
```

- `@extends Factory<Quote>` — generics agar Larastan paham tipe.
- Default `is_active => true` → data hasil factory langsung siap dipakai test tanpa setup tambahan.
- `inactive()` state → dipakai saat test butuh data non-aktif: `Quote::factory()->inactive()->create()`.
- `fake()->optional(0.7)` → 30% menghasilkan `null`, sekaligus menguji kolom nullable.

> [!TIP]
> **Seeder itu opsional.** `Category` punya `CategorySeeder` yang terdaftar di `DatabaseSeeder`. Buat `QuoteSeeder` serupa **hanya** jika Anda ingin data contoh saat `migrate --seed`. Untuk testing, factory sudah cukup.

---

## Step 2 — RBAC & Policy

Setiap resource API **wajib** dilindungi RBAC (`spatie/laravel-permission`).

### 2.1 Daftarkan resource di `RolePermissionSeeder`

Buka `database/seeders/RolePermissionSeeder.php`.

**(a)** Tambahkan `'quotes'` ke konstanta `RESOURCES`:

```php
/** @var list<string> */
private const RESOURCES = ['users', 'roles', 'categories', 'quotes', 'app_configs', 'app_versions', 'notifications'];
```

Karena `ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete']`, seeder otomatis membuat 5 permission: `quotes.viewAny`, `quotes.view`, `quotes.create`, `quotes.update`, `quotes.delete`.

**(b)** Beri permission ke role yang sesuai (di method `run()`):

```php
$admin->syncPermissions([
    // ... permission lain yang sudah ada ...
    'quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
]);

$staff->syncPermissions([
    // ... permission lain yang sudah ada ...
    'quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update',
    // staff sengaja TIDAK diberi quotes.delete
]);
```

> [!NOTE]
> Role **`super-admin`** memakai `Permission::all()` **dan** di-*bypass* lewat `Gate::before` di `AppServiceProvider` (`hasRole('super-admin') ? true : null`). Jadi super-admin otomatis dapat semua permission baru — tidak perlu didaftarkan manual.

### 2.2 Policy `app/Policies/QuotePolicy.php`

Laravel auto-discovery memetakan `Quote` → `QuotePolicy` berdasarkan konvensi nama (tidak perlu registrasi manual, beda dengan `RolePolicy` yang kelas modelnya di namespace vendor).

```bash
php artisan make:policy QuotePolicy --model=Quote
```

```php
<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotes.viewAny');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->can('quotes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotes.create');
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->can('quotes.update');
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->can('quotes.delete');
    }

    public function restore(User $user, Quote $quote): bool
    {
        return $user->can('quotes.update');
    }

    public function forceDelete(User $user, Quote $quote): bool
    {
        return $user->can('quotes.delete');
    }
}
```

Pola: setiap method membaca permission `{resource}.{ability}`. `restore` mengikuti `update`, `forceDelete` mengikuti `delete`.

### 2.3 Terapkan permission baru

```bash
# Lokal/dev — reset penuh & seed ulang
php artisan migrate:fresh --seed
```

Jika tidak ingin reset, jalankan seeder spesifik (idempotent karena `findOrCreate` + `syncPermissions`):

```bash
php artisan db:seed --class=RolePermissionSeeder
```

> [!CAUTION]
> `migrate:fresh --seed` **menghapus semua data**. Hanya untuk lokal/dev. Di staging/production, tambahkan permission lewat migration/seeder incremental.

---

## Step 3 — Form Requests (Validasi + Otorisasi Write)

Validasi input **tidak pernah** di controller. Operasi **write** (create/update) menggabungkan validasi + otorisasi di Form Request.

### 3.1 `app/Http/Requests/Api/V1/StoreQuoteRequest.php`

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Quote::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:5'],
            'author' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

### 3.2 `app/Http/Requests/Api/V1/UpdateQuoteRequest.php`

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            && ($this->user()?->can('update', $quote) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['sometimes', 'required', 'string', 'min:5'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

| Aspek | Store | Update |
|---|---|---|
| `authorize()` | `can('create', Quote::class)` (tanpa instance) | `can('update', $quote)` (dengan instance dari route) |
| `text`, `author` | `required` | `sometimes, required` → boleh tidak dikirim, tapi jika dikirim wajib valid |

> [!TIP]
> **Kenapa `sometimes` + `required` di Update?** Agar **partial update** aman: client boleh mengirim `is_active` saja tanpa `text`/`author`. `sometimes` = "validasi hanya jika field ada"; `required` = "jika ada, tidak boleh kosong".

> [!IMPORTANT]
> **Pembagian otorisasi (pola baku proyek):**
> - **Write** (create/update) → otorisasi di **Form Request** (`authorize()`), sekaligus validasi input.
> - **Read & delete** (index/show/destroy) → tidak ada input untuk divalidasi → otorisasi langsung di **Controller** via `$this->authorize()`.

---

## Step 4 — API Resource (Transformasi Output)

Resource memisahkan format JSON dari struktur DB internal → kontrak stabil untuk konsumen (mis. app Flutter). Ganti nama kolom DB? Cukup ubah di Resource, konsumen tidak terdampak.

### `app/Http/Resources/Api/V1/QuoteResource.php`

```php
<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'author' => $this->author,
            'source' => $this->source,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

Aturan:
1. **`@mixin Quote`** → autocomplete property model di dalam resource.
2. **Tanggal `->toIso8601String()`** → format standar `2026-05-25T10:00:00+00:00`, mudah diparse di mobile.
3. **`?->`** pada tanggal (bisa `null` sebelum tersimpan).
4. **Tidak menyertakan `deleted_at`** (urusan internal).
5. **Tidak ada query** di dalam resource (cegah N+1). Eager-load relasi di controller bila perlu.

---

## Step 5 — Controller Tipis + Spatie Query Builder

Controller hanya: (1) otorisasi read/delete, (2) terima data tervalidasi, (3) panggil model, (4) balas via Resource + ApiResponse.

### `app/Http/Controllers/Api/V1/QuoteController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQuoteRequest;
use App\Http\Requests\Api\V1\UpdateQuoteRequest;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Models\Quote;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class QuoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $quotes = QueryBuilder::for(Quote::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),       // ?filter[search]=... → scopeSearch (text OR author)
                AllowedFilter::partial('text'),       // ?filter[text]=...   → khusus teks
                AllowedFilter::partial('author'),     // ?filter[author]=... → khusus penulis
                AllowedFilter::exact('is_active'),    // ?filter[is_active]=1
            )
            ->allowedSorts('author', 'is_active', 'created_at', 'updated_at')
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(QuoteResource::collection($quotes));
    }

    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $quote = Quote::query()->create($request->validated());

        return ApiResponse::success(new QuoteResource($quote), 'Quote created', 201);
    }

    public function show(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        return ApiResponse::success(new QuoteResource($quote));
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): JsonResponse
    {
        $quote->update($request->validated());

        return ApiResponse::success(new QuoteResource($quote->refresh()), 'Quote updated');
    }

    public function destroy(Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return ApiResponse::success(null, 'Quote deleted');
    }
}
```

### Penjelasan `index()` (List + Search + Filter + Sort)

```php
// 1) Otorisasi daftar.
$this->authorize('viewAny', Quote::class);

// 2) Batasi per_page: minimal 1, maksimal 100 (cegah abuse per_page=999999).
$perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

// 3) Hanya filter & sort yang DI-WHITELIST yang diizinkan.
QueryBuilder::for(Quote::class)
    ->allowedFilters(                      // variadic (string|AllowedFilter ...), bukan array — sesuai blueprint & lolos Larastan
        AllowedFilter::scope('search'),    // gabungan text + author (scopeSearch)
        AllowedFilter::partial('text'),    // LOWER(text) LIKE LOWER('%..%') → case-insensitive
        AllowedFilter::partial('author'),
        AllowedFilter::exact('is_active'),
    )
    ->allowedSorts('author', 'is_active', 'created_at', 'updated_at')
    ->defaultSort('-created_at')           // default: terbaru di atas
    ->paginate($perPage)
    ->appends($request->query());          // pertahankan query string di link pagination
```

**Contoh URL untuk konsumen:**

| Tujuan | URL |
|---|---|
| List default (15/halaman, terbaru dulu) | `GET /api/v1/quotes` |
| **Search** gabungan (teks **atau** penulis) | `GET /api/v1/quotes?filter[search]=einstein` |
| Cari hanya pada teks | `GET /api/v1/quotes?filter[text]=code` |
| Cari hanya pada penulis | `GET /api/v1/quotes?filter[author]=torvalds` |
| Filter aktif + urut nama penulis | `GET /api/v1/quotes?filter[is_active]=1&sort=author` |
| Pagination | `GET /api/v1/quotes?per_page=10&page=2` |
| Kombinasi | `GET /api/v1/quotes?filter[search]=clean&filter[is_active]=1&sort=-created_at&per_page=5` |

> [!WARNING]
> **Whitelist itu fitur keamanan.** Tanpa `allowedFilters`/`allowedSorts`, client bisa memfilter/mengurutkan kolom apa pun. Spatie Query Builder **menolak** parameter yang tidak terdaftar. (Catatan teknis: `AllowedFilter::partial` membentuk `LOWER(kolom) LIKE LOWER(?)`, jadi pencarian sudah otomatis case-insensitive di semua driver.)

**Method lain singkat:**
- `store()` → balas **HTTP 201**. Otorisasi + validasi sudah di `StoreQuoteRequest`.
- `show()` → route-model-binding `{quote}`; jika tidak ada otomatis 404 (ditangani `bootstrap/app.php`).
- `update()` → `->refresh()` agar response mencerminkan data terbaru.
- `destroy()` → `delete()` = soft delete (mengisi `deleted_at`).

---

## Step 6 — Registrasi Route

Buka `routes/api.php`.

**(a)** Tambahkan import di atas:

```php
use App\Http\Controllers\Api\V1\QuoteController;
```

**(b)** Daftarkan di dalam group `['auth:api', 'check.maintenance']` (sejajar dengan `categories`):

```php
Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
    Route::post('assets/upload', [AssetController::class, 'upload'])->middleware('throttle:30,1');

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('quotes', QuoteController::class);   // ← tambahkan baris ini

    Route::prefix('notifications')->group(function (): void {
        // ... route yang sudah ada ...
    });
});
```

`apiResource` menghasilkan 5 route: `index`, `store`, `show`, `update`, `destroy`.

**(c)** Verifikasi:

```bash
php artisan route:list --path=api/v1/quotes
```

```
GET|HEAD   api/v1/quotes ............ quotes.index   › Api\V1\QuoteController@index
POST       api/v1/quotes ............ quotes.store   › Api\V1\QuoteController@store
GET|HEAD   api/v1/quotes/{quote} .... quotes.show    › Api\V1\QuoteController@show
PUT|PATCH  api/v1/quotes/{quote} .... quotes.update  › Api\V1\QuoteController@update
DELETE     api/v1/quotes/{quote} .... quotes.destroy › Api\V1\QuoteController@destroy
```

> [!NOTE]
> Endpoint berada di bawah prefix `v1` (lihat `Route::prefix('v1')` di puncak file) sehingga URL final adalah `/api/v1/quotes`. Auth memakai **Passport Bearer token** (`auth:api`).

---

## Step 7 — Bentuk Response Envelope (Kontrak API)

Semua response melewati `ApiResponse`. Berikut bentuk konkret yang akan diterima konsumen — gunakan sebagai acuan saat mengembangkan client.

**List (`GET /api/v1/quotes`)** — perhatikan `meta.pagination`:

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 12,
      "text": "Talk is cheap. Show me the code.",
      "author": "Linus Torvalds",
      "source": "LKML",
      "is_active": true,
      "created_at": "2026-05-25T10:00:00+00:00",
      "updated_at": "2026-05-25T10:00:00+00:00"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
  }
}
```

**Single (`GET /api/v1/quotes/{id}`)** — `data` berupa objek:

```json
{ "success": true, "message": "OK", "data": { "id": 12, "text": "...", "author": "Linus Torvalds", "is_active": true } }
```

**Validasi gagal (HTTP 422)** — dipetakan otomatis dari `ValidationException`:

```json
{
  "success": false,
  "message": "The text field is required.",
  "code": "VALIDATION_ERROR",
  "errors": { "text": ["The text field is required."] }
}
```

**Tidak login (401)** → `{ "success": false, "message": "Unauthenticated.", "code": "UNAUTHENTICATED" }`
**Tidak berhak (403)** → `code: "FORBIDDEN"` · **Tidak ditemukan (404)** → `code: "NOT_FOUND"`

> [!NOTE]
> Anda **tidak menulis** kode envelope/error ini per-endpoint. `ApiResponse` membentuk `success/message/data/meta`; pemetaan exception → `code` dilakukan terpusat di `bootstrap/app.php`. Daftar `code` standar ada di enum `App\Support\Enums\ApiErrorCode`.

---

## Step 8 — Dokumentasi API Otomatis (Scramble)

Proyek ini memakai **`dedoc/scramble`** untuk meng-generate dokumentasi OpenAPI **otomatis** dari kode. Konfigurasi (`config/scramble.php`) men-scan semua route dengan prefix **`api/v1`** — jadi `quotes` Anda **langsung muncul** tanpa konfigurasi tambahan.

- **UI dokumentasi**: buka `/docs/api` di browser (mis. `http://localhost:8000/docs/api`). Tersedia fitur **Try It**.
- **Spesifikasi mentah**: `/docs/api.json`.

> [!TIP]
> **Bikin dokumentasi makin kaya tanpa usaha ekstra.** Scramble membaca tipe dari signature & Form Request Anda:
> - **Validasi** di `StoreQuoteRequest`/`UpdateQuoteRequest` otomatis jadi skema request body + daftar parameter.
> - **`QuoteResource`** otomatis jadi skema response.
> - Tambahkan **PHPDoc** di method controller untuk deskripsi yang lebih ramah, contoh:
>
> ```php
> /**
>  * List quotes
>  *
>  * Mengembalikan daftar quote dengan dukungan search (`filter[search]`),
>  * filter `is_active`, sort, dan pagination.
>  */
> public function index(Request $request): JsonResponse
> ```
>
> Karena `ApiResponse::success()` mengembalikan `response()->json(...)` generik, Scramble mungkin tidak selalu menebak skema `data` secara sempurna. Itu wajar — prioritaskan PHPDoc yang jelas, dan verifikasi tampilan akhir di `/docs/api`.

Akses dokumentasi dibatasi `RestrictedDocsAccess` (default hanya environment `local`). Jadi aman: tidak bocor di production kecuali Anda buka sengaja.

---

## Step 9 — Filament Back-Office (Opsional)

Lewati step ini jika resource hanya untuk API. Jika perlu dikelola admin di `/admin`, ikuti **struktur modular** proyek (bukan satu file raksasa):

```
app/Filament/Resources/Quotes/
├── QuoteResource.php          # definisi utama
├── Schemas/QuoteForm.php      # form create/edit
├── Tables/QuotesTable.php     # konfigurasi tabel
└── Pages/
    ├── ListQuotes.php
    ├── CreateQuote.php
    └── EditQuote.php
```

> [!TIP]
> Generate kerangka lalu rapikan ke struktur modular, atau salin dari folder `app/Filament/Resources/Categories/`:
> ```bash
> php artisan make:filament-resource Quote --generate
> ```

**`QuoteResource.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes;

use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Resources\Quotes\Schemas\QuoteForm;
use App\Filament\Resources\Quotes\Tables\QuotesTable;
use App\Models\Quote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $recordTitleAttribute = 'author';

    public static function form(Schema $schema): Schema
    {
        return QuoteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotes::route('/'),
            'create' => CreateQuote::route('/create'),
            'edit' => EditQuote::route('/{record}/edit'),
        ];
    }
}
```

**`Schemas/QuoteForm.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('text')
                    ->required()
                    ->minLength(5)
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                TextInput::make('source')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
```

**`Tables/QuotesTable.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class QuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

**`Pages/ListQuotes.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

**`Pages/CreateQuote.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;
}
```

**`Pages/EditQuote.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

> [!NOTE]
> Filament otomatis menghormati `QuotePolicy` — entri sidebar & tombol akan tersembunyi untuk user tanpa permission `quotes.*`. Navigation group **`Data Master`** mengelompokkan resource sejenis (sama seperti `Category`).

---

## Step 10 — Feature Test API

Tidak ada fitur yang boleh masuk codebase tanpa test. Minimal **4 skenario**:

| # | Skenario | Tujuan |
|---|---|---|
| 1 | Guest ditolak (401) | Endpoint terproteksi auth |
| 2 | List + search + filter + sort + pagination | Query Builder & scope `search` bekerja |
| 3 | Siklus CRUD penuh | create → show → update → delete end-to-end |
| 4 | Staff tanpa izin delete → 403 | RBAC berjalan |

### `tests/Feature/Api/QuoteTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Quote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_quotes(): void
    {
        $this->getJson('/api/v1/quotes')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_admin_can_search_filter_sort_and_paginate_quotes(): void
    {
        Passport::actingAs($this->userWithRole('admin'));

        Quote::factory()->create(['text' => 'Stay hungry, stay foolish.', 'author' => 'Steve Jobs', 'is_active' => true]);
        Quote::factory()->create(['text' => 'Imagination is more important.', 'author' => 'Albert Einstein', 'is_active' => true]);
        Quote::factory()->create(['text' => 'Old inactive quote.', 'author' => 'Anonymous', 'is_active' => false]);

        // Search gabungan (cocok di kolom author) + filter aktif + sort + pagination.
        $this->getJson('/api/v1/quotes?filter[search]=einstein&filter[is_active]=1&sort=author&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.author', 'Albert Einstein')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_admin_can_create_show_update_and_delete_quote(): void
    {
        Passport::actingAs($this->userWithRole('admin'));

        $quoteId = $this->postJson('/api/v1/quotes', [
            'text' => 'Talk is cheap. Show me the code.',
            'author' => 'Linus Torvalds',
            'source' => 'LKML',
        ])
            ->assertCreated()
            ->assertJsonPath('data.author', 'Linus Torvalds')
            ->json('data.id');

        $this->getJson("/api/v1/quotes/{$quoteId}")
            ->assertOk()
            ->assertJsonPath('data.text', 'Talk is cheap. Show me the code.');

        // Partial update: hanya kirim is_active; author tetap.
        $this->putJson("/api/v1/quotes/{$quoteId}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.author', 'Linus Torvalds');

        $this->deleteJson("/api/v1/quotes/{$quoteId}")
            ->assertOk()
            ->assertJson(['message' => 'Quote deleted']);

        $this->assertSoftDeleted('quotes', ['id' => $quoteId]);
    }

    public function test_staff_cannot_delete_quote(): void
    {
        Passport::actingAs($this->userWithRole('staff'));
        $quote = Quote::factory()->create();

        $this->deleteJson("/api/v1/quotes/{$quote->getKey()}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('quotes', ['id' => $quote->getKey()]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
```

| Pola | Arti |
|---|---|
| `RefreshDatabase` | Tiap test di DB bersih (migrasi + rollback). |
| `seed(RolePermissionSeeder::class)` di `setUp()` | Permission/role harus ada sebelum test. |
| `Passport::actingAs(...)` | Simulasi Bearer token OAuth2 yang valid. |
| `assertJsonPath('data.0.author', ...)` | Cek nilai dalam JSON ber-nested. |
| `assertSoftDeleted` / `assertNotSoftDeleted` | Verifikasi `deleted_at` terisi / tetap null. |

---

## Step 11 — Test Back-Office

Hanya jika Anda membuat Filament Resource (Step 9).

### `tests/Feature/BackOffice/QuoteManagementTest.php`

```php
<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_quote_management_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $quote = Quote::factory()->create();

        $this->actingAs($admin)->get(QuoteResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(QuoteResource::getUrl('create'))->assertOk();
        $this->actingAs($admin)->get(QuoteResource::getUrl('edit', ['record' => $quote]))->assertOk();
    }

    public function test_user_without_quote_permission_cannot_access_quote_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(QuoteResource::getUrl('index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
```

> [!NOTE]
> Beda autentikasi: **API** pakai `Passport::actingAs()` (Bearer token); **Back-office** pakai `$this->actingAs()` (session/cookie).

---

## Step 12 — Quality Gates

**Wajib lolos ketiganya** sebelum commit (lihat `CONTRIBUTING.md` §3). Tersedia dua cara: shortcut `composer` atau binary langsung.

```bash
# 1) Formatter (PSR-12) — memformat otomatis
composer lint            # = vendor/bin/pint

# 2) Static analysis (Larastan) — target: [OK] No errors
composer analyse         # = vendor/bin/phpstan analyse --memory-limit=1G

# 3) Seluruh test suite — wajib hijau 100%
composer test            # = php artisan config:clear && php artisan test
```

Error PHPStan yang sering muncul saat menambah model baru:

| Pesan | Solusi |
|---|---|
| `Access to an undefined property App\Models\Quote::$author` | Lengkapi `@property` docblock di model. |
| `Method ...scopeSearch() ... return type` | Pastikan `@param Builder<Quote>` & `@return Builder<Quote>` ada pada scope. |
| `Parameter ... expects ...` | Perbaiki tipe argumen. |

> [!CAUTION]
> **Jangan pernah commit kode yang gagal quality gate.** Jika salah satu merah, perbaiki dulu. Jangan pakai `--no-verify` untuk menembus hook.

---

## Step 13 — Commit, CHANGELOG & Git Flow

Ikuti Git Flow + Conventional Commits (`CONTRIBUTING.md`).

### 13.1 Branch dari `develop`

```bash
git checkout develop
git pull origin develop
git checkout -b feature/quotes-api
```

> [!WARNING]
> `feature/*` **selalu** dicabangkan dari & di-merge kembali ke **`develop`**, bukan `main`. Dilarang commit/merge langsung ke `main`.

### 13.2 Update CHANGELOG.md (WAJIB)

Buka `CHANGELOG.md`, tambahkan di bawah `## [Unreleased]` → `### Added`:

```markdown
### Added
- Quotes Management API: CRUD penuh, pencarian gabungan (`filter[search]`), filter `is_active`, sort, dan pagination, di bawah prefix `api/v1` dengan proteksi RBAC.
- Filament back-office resource untuk Quotes (grup navigasi Data Master).
- Feature test API & back-office untuk Quotes.
```

> [!IMPORTANT]
> Melewatkan update CHANGELOG dapat membuat kontribusi **ditolak saat review** (`CONTRIBUTING.md` §3).

### 13.3 Commit atomik (Conventional Commits)

Subjek: imperatif, huruf kecil di awal, tanpa titik di akhir, ≤72 karakter.

```bash
git add database/migrations/*create_quotes_table* app/Models/Quote.php database/factories/QuoteFactory.php
git commit -m "feat(quotes): add migration, model, factory"

git add database/seeders/RolePermissionSeeder.php app/Policies/QuotePolicy.php
git commit -m "feat(quotes): add RBAC permissions and policy"

git add app/Http/Requests/Api/V1/StoreQuoteRequest.php app/Http/Requests/Api/V1/UpdateQuoteRequest.php \
        app/Http/Resources/Api/V1/QuoteResource.php app/Http/Controllers/Api/V1/QuoteController.php routes/api.php
git commit -m "feat(quotes): add API controller, requests, resource, routes"

git add app/Filament/Resources/Quotes/
git commit -m "feat(quotes): add Filament back-office resource"

git add tests/Feature/Api/QuoteTest.php tests/Feature/BackOffice/QuoteManagementTest.php
git commit -m "test(quotes): add API and back-office feature tests"

git add CHANGELOG.md
git commit -m "docs(changelog): record quotes API under unreleased"
```

### 13.4 Push & Pull Request

```bash
git push -u origin feature/quotes-api
```

Buat PR **ke `develop`**, kecil & fokus (idealnya < 200 LOC), judul gaya Conventional Commits, lengkap dengan deskripsi tujuan + cara uji.

> [!CAUTION]
> Jangan pernah `git push --force` ke `main`/`develop`. Untuk sinkronisasi branch pribadi setelah rebase, pakai `git push --force-with-lease`.

---

## ✅ Checklist Final

**Database & Model**
- [ ] Migrasi: tipe kolom tepat, `is_active` default `true`, `timestamps()`, `softDeletes()`, index pada kolom filter/sort
- [ ] Model: `@property` lengkap, `#[Fillable([...])]`, trait `HasFactory` + `LogsActivity` + `SoftDeletes`, `casts()`, `getActivitylogOptions()`
- [ ] `scopeSearch()` ber-generics `Builder<Quote>` (untuk fitur search)
- [ ] Factory: data realistis + state `inactive()`

**RBAC & Policy**
- [ ] `'quotes'` ditambahkan ke `RolePermissionSeeder::RESOURCES`
- [ ] Permission diberikan ke `admin` (penuh) & `staff` (tanpa delete)
- [ ] `QuotePolicy` lengkap: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`
- [ ] Seeder dijalankan ulang

**API**
- [ ] StoreRequest: `required` + `authorize()` `can('create', Quote::class)`
- [ ] UpdateRequest: `sometimes,required` + `authorize()` `can('update', $quote)`
- [ ] Resource: `@mixin Quote`, tanggal ISO-8601, tanpa query, tanpa `deleted_at`
- [ ] Controller tipis: read/delete via `$this->authorize()`, write via Form Request
- [ ] `index()`: QueryBuilder whitelist (`search` scope + partial + exact), `per_page` di-clamp `min(max(.,1),100)`, `defaultSort('-created_at')`
- [ ] `store()` balas 201; `update()` pakai `->refresh()`
- [ ] Semua response via `ApiResponse`

**Route & Docs**
- [ ] `Route::apiResource('quotes', ...)` di dalam group `['auth:api','check.maintenance']` + import
- [ ] Diverifikasi `php artisan route:list --path=api/v1/quotes`
- [ ] Cek tampilan di `/docs/api` (Scramble) — request body & response schema masuk akal

**Filament (opsional)**
- [ ] Struktur modular (`QuoteResource` + `Schemas/` + `Tables/` + `Pages/`), grup `Data Master`

**Test & Quality Gate**
- [ ] Test API: guest 401, search+filter+sort+pagination, CRUD penuh, staff 403
- [ ] Test back-office: smoke index/create/edit + user tanpa izin 403
- [ ] `composer lint` bersih · `composer analyse` `[OK]` · `composer test` hijau

**Git & Dokumentasi**
- [ ] Branch `feature/quotes-api` dari `develop`
- [ ] Commit atomik Conventional Commits
- [ ] `CHANGELOG.md` di-update di `## [Unreleased]`
- [ ] Tidak ada file rahasia ter-commit; PR ke `develop`

---

## 📁 Lampiran: Ringkasan File

**File baru:**

| File | Tipe |
|---|---|
| `database/migrations/xxxx_create_quotes_table.php` | Migration |
| `app/Models/Quote.php` | Model (+ `scopeSearch`) |
| `database/factories/QuoteFactory.php` | Factory |
| `app/Policies/QuotePolicy.php` | Policy |
| `app/Http/Requests/Api/V1/StoreQuoteRequest.php` | Form Request |
| `app/Http/Requests/Api/V1/UpdateQuoteRequest.php` | Form Request |
| `app/Http/Resources/Api/V1/QuoteResource.php` | API Resource |
| `app/Http/Controllers/Api/V1/QuoteController.php` | Controller |
| `app/Filament/Resources/Quotes/QuoteResource.php` | Filament (opsional) |
| `app/Filament/Resources/Quotes/Schemas/QuoteForm.php` | Filament (opsional) |
| `app/Filament/Resources/Quotes/Tables/QuotesTable.php` | Filament (opsional) |
| `app/Filament/Resources/Quotes/Pages/{List,Create,Edit}Quote.php` | Filament (opsional) |
| `tests/Feature/Api/QuoteTest.php` | Test API |
| `tests/Feature/BackOffice/QuoteManagementTest.php` | Test back-office |

**File dimodifikasi:**

| File | Perubahan |
|---|---|
| `database/seeders/RolePermissionSeeder.php` | `+'quotes'` di `RESOURCES`, permission ke `admin` & `staff` |
| `routes/api.php` | `+Route::apiResource('quotes', QuoteController::class)` + import |
| `CHANGELOG.md` | Entry baru di `## [Unreleased]` |

---

## 🎓 Alur Berpikir (Ringkas)

```
0. Rancang tabel       → Migration (skema + index)
1. Representasi data   → Model (@property, Fillable, casts, scopeSearch) + Factory
2. Siapa boleh apa?    → RolePermissionSeeder + Policy
3. Input valid?        → Form Request (Store + Update)
4. Output rapi?        → API Resource (ISO-8601, @mixin)
5. Orkestrasi?         → Controller TIPIS + Query Builder (search/filter/sort)
6. Akses di mana?      → routes/api.php (auth:api)
7. Kontrak konsumen?   → ApiResponse envelope
8. Dokumentasi?        → Scramble /docs/api (otomatis)
9. Back-office?        → Filament modular (opsional)
10-11. Buktikan benar! → Feature Test (API + Back-Office)
12. Kode berkualitas?  → Pint + Larastan + PHPUnit
13. Riwayat rapi?      → Git Flow + Conventional Commits + CHANGELOG
```

Selamat — modul API baru Anda kini lengkap, aman (RBAC + whitelist), ter-dokumentasi otomatis, teruji, dan mengikuti seluruh best practice Laravel Starter. 🚀
