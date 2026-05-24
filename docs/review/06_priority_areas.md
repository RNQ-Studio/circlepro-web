# Deep Dive Area Prioritas

Dokumen ini berisi analisis sangat mendalam terhadap 5 area prioritas utama pada proyek **Laravel Starter**, dilengkapi dengan temuan kode spesifik, masalah potensial, kode perbaikan konkret, dan estimasi tingkat effort perbaikan.

---

## 1. Auth & Authorization (Estimasi Effort: S - Small)

### Kode yang Ditemukan
Di dalam [app/Services/Auth/AuthService.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/app/Services/Auth/AuthService.php#L131-L146) pada method `issueToken`:

```php
    private function issueToken(array $params): array
    {
        $params = [
            'client_id' => (string) config('passport.password_client.id'),
            'client_secret' => (string) config('passport.password_client.secret'),
            'scope' => '',
            ...$params,
        ];

        $request = Request::create('/oauth/token', 'POST', $params);
        $response = app()->handle($request);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true) ?: [];

        if ($response->getStatusCode() !== 200) {
            throw new AuthenticationException('Invalid credentials.');
        }
        // ...
```

### Masalah Spesifik
Ketika `client_id` atau `client_secret` Passport Password Client tidak diset di `.env` (atau database belum terinstall Passport client melalui `php artisan passport:client --password`), endpoint login akan mengembalikan status `401` dengan pesan **"Invalid credentials."**. 

Ini adalah **kesalahan masking**. Dari sudut pandang pengguna/developer, ini seolah-olah terjadi salah ketik password (user error), padahal aslinya adalah **kesalahan konfigurasi server (configuration error)**. Masking ini membuat proses debugging di tingkat staging/production menjadi sangat membingungkan.

### Rekomendasi Perbaikan
Bedakan response error ketika status code bukan `400`/`401` (invalid username/password) dengan error internal server (seperti client secret tidak cocok/tidak ada).

```php
        if ($response->getStatusCode() !== 200) {
            $errorMsg = $data['error'] ?? 'Authentication failed';
            
            // Jika dalam mode debug, bocorkan detail internal error untuk DX yang lebih baik
            if (config('app.debug') && in_array($errorMsg, ['unsupported_grant_type', 'invalid_client'])) {
                throw new \RuntimeException("Passport configuration error: {$errorMsg}. Did you run: php artisan passport:client --password?");
            }

            throw new AuthenticationException('Invalid credentials.');
        }
```

---

## 2. Multi-tenancy (Estimasi Effort: L - Large)

### Kode yang Ditemukan
Tidak ada kode multi-tenancy saat ini (proyek terkonfigurasi sebagai single-tenant, sengaja dihindari sesuai keputusan desain arsitektur).

### Masalah Spesifik
Jika di masa depan proyek ini beralih menjadi sistem SaaS / Multi-tenant dengan arsitektur **Shared Database (Single DB, Column `tenant_id`)**, kerentanan terbesar adalah kebocoran data lintas-tenant (**cross-tenant data leakage**). Developer bisa saja lupa menambahkan klausa `->where('tenant_id', $tenantId)` secara manual pada setiap query Eloquent.

### Rekomendasi Perbaikan
Gunakan mekanisme **Global Query Scope** pada Laravel. Setiap kali ada model Eloquent yang bersifat multi-tenant, model tersebut harus menyertakan Trait `BelongsToTenant` yang mengaplikasikan scope penyaringan secara otomatis.

1. **Buat TenantScope**:
```php
namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && auth()->user()->tenant_id !== null) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    }
}
```

2. **Buat Trait `BelongsToTenant`**:
```php
namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            if (auth()->check() && auth()->user()->tenant_id !== null) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

---

## 3. API Versioning & Response (Estimasi Effort: M - Medium)

### Kode yang Ditemukan
Di [app/Http/Controllers/Api/V1/CategoryController.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/app/Http/Controllers/Api/V1/CategoryController.php#L35-L45) pada method `index`:

```php
        return ApiResponse::success(
            data: CategoryResource::collection($categories->getCollection())->resolve($request),
            meta: [
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                ],
            ],
        );
```

### Masalah Spesifik
Terjadi **duplikasi kode boilerplate pagination** pada setiap controller API index. Hal ini disebabkan karena pembungkus `ApiResponse::success` di [ApiResponse.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/app/Support/ApiResponse.php#L22-L24) hanya mendeteksi instance `AbstractPaginator` murni untuk otomatisasi metadata pagination:

```php
        if ($data instanceof AbstractPaginator) {
            $payload['data'] = $data->items();
            $meta = ['pagination' => self::paginationMeta($data)] + $meta;
        }
```
Namun, di controller, developer terpaksa memanggil `CategoryResource::collection($categories->getCollection())->resolve()` yang menghasilkan tipe data **Array murni**, sehingga deteksi `AbstractPaginator` di helper `ApiResponse` terlewati.

### Rekomendasi Perbaikan
Meningkatkan helper `ApiResponse::success` agar mendukung tipe data `AnonymousResourceCollection` yang menyimpan instance paginator asli di dalam properti `$data->resource`.

Ubah [app/Support/ApiResponse.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/app/Support/ApiResponse.php#L22-L27) menjadi:

```php
        use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

        if ($data instanceof AnonymousResourceCollection && $data->resource instanceof AbstractPaginator) {
            $payload['data'] = $data->resolve();
            $meta = ['pagination' => self::paginationMeta($data->resource)] + $meta;
        } elseif ($data instanceof AbstractPaginator) {
            $payload['data'] = $data->items();
            $meta = ['pagination' => self::paginationMeta($data)] + $meta;
        } else {
            $payload['data'] = $data;
        }
```

Dengan peningkatan ini, pemanggilan di `CategoryController` menjadi sangat singkat dan bersih, bebas dari duplikasi pagination:

```php
        return ApiResponse::success(CategoryResource::collection($categories));
```

---

## 4. Filament Panel (Estimasi Effort: S - Small)

### Kode yang Ditemukan
Di dalam [app/Providers/Filament/AdminPanelProvider.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/app/Providers/Filament/AdminPanelProvider.php#L31-L34):

```php
            ->brandName('Laravel Starter')
            ->colors([
                'primary' => Color::Emerald,
            ])
```

### Masalah Spesifik
Tampilan back-office Filament masih terlihat generik. Untuk memberikan kesan premium (**premium design aesthetics**) sejak pandangan pertama bagi pengguna starter, branding default perlu dimaksimalkan dengan logo kustom (SVG/PNG), dark-mode switcher otomatis yang sleek, serta default favicon.

### Rekomendasi Perbaikan
Maksimalkan konfigurasi brand di `AdminPanelProvider`:

```php
            ->brandName('Antigravity Starter')
            ->brandLogo(asset('images/logo-light.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Indigo, // Indigo memberikan kesan lebih premium/modern
                'gray' => Color::Slate,
            ])
            ->databaseNotifications() // Aktifkan database notifications bawaan Filament
```

---

## 5. Testing Setup (Estimasi Effort: M - Medium)

### Kode yang Ditemukan
Di dalam [tests/Feature/RegionSeederTest.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/tests/Feature/RegionSeederTest.php#L103-L122) pada method `dataFilesExist`:

```php
    private function dataFilesExist(): bool
    {
        $required = [
            storage_path('app/regions/dr5hn/countries.json'),
            storage_path('app/regions/dr5hn/states.json'),
            // ...
        ];

        foreach ($required as $file) {
            if (! file_exists($file)) {
                return false;
            }
        }

        return true;
    }
```

### Masalah Spesifik
Test suite regional Indonesia berukuran raksasa dan bergantung sepenuhnya pada berkas JSON eksternal yang diunduh secara manual (`php artisan regions:download`). Jika file-file ini belum diunduh, tes dilewati (**skipped**). Namun, pada environment **CI (Continuous Integration)** baru (seperti GitHub Actions), proses pengunduhan langsung via internet dapat menyebabkan keterlambatan testing atau kegagalan acak akibat rate limit API eksternal.

### Rekomendasi Perbaikan
Gunakan **Mocking / Fixtures** khusus untuk testing.
Buat file data dummy berukuran mini (fixtures) di dalam folder `tests/Fixtures/regions/` yang berisi 2-3 baris negara/provinsi/kota. Di dalam `RegionSeeder` dan pengujian, deteksi mode testing untuk mengalihkan pemindaian ke direktori fixtures lokal ini alih-alih data asli sebesar ratusan megabyte.

```php
    protected function getSourcePath(string $filename): string
    {
        if (app()->environment('testing')) {
            return base_path("tests/Fixtures/regions/{$filename}");
        }

        return storage_path("app/regions/{$filename}");
    }
```
Ini akan menjamin testing suite berjalan instan (di bawah 1 detik) baik di lokal maupun di server CI tanpa perlu mengunduh file asli terlebih dahulu.
