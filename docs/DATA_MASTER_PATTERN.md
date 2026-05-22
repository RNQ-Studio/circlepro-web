# Data Master Pattern

Panduan ringkas untuk menambah modul data master baru dengan pola yang sama seperti `Category`.

## 1. Database dan Model

1. Buat migration dengan kolom inti: `id`, field bisnis, `is_active` bila perlu, `timestamps`, dan `softDeletes` bila record perlu bisa dipulihkan.
2. Buat model di `app/Models` dengan `HasFactory`, `SoftDeletes` bila perlu, atribut fillable lewat `#[Fillable([...])]`, dan cast untuk boolean atau tipe non-string lain.
3. Buat factory di `database/factories` untuk data test dan seeder.

## 2. RBAC dan Policy

1. Tambahkan nama resource ke `RolePermissionSeeder::RESOURCES`.
2. Jalankan ulang seeder di environment lokal/test.
3. Buat policy di `app/Policies` dengan ability standar: `viewAny`, `view`, `create`, `update`, dan `delete`.
4. Setiap method policy membaca permission dengan format `{resource}.{ability}`, misalnya `categories.viewAny`.

## 3. API

1. Buat controller di `app/Http/Controllers/Api/V1`.
2. Untuk list endpoint, gunakan `spatie/laravel-query-builder` dengan whitelist eksplisit: `allowedFilters(...)`, `allowedSorts(...)`, dan `defaultSort(...)`.
3. Batasi `per_page` agar tidak melebihi batas wajar, misalnya 100.
4. Buat form request `Store...Request` dan `Update...Request`.
5. Authorization create/update sebaiknya berada di form request, sedangkan list/show/delete dipanggil dari controller.
6. Buat API resource di `app/Http/Resources/Api/V1`.
7. Daftarkan route di `routes/api.php` di dalam middleware `auth:api`:

```php
Route::apiResource('categories', CategoryController::class);
```

## 4. Back-office

1. Buat Filament resource di `app/Filament/Resources/{PluralName}`.
2. Pisahkan konfigurasi form dan table ke folder `Schemas`, `Tables`, dan `Pages`.
3. Minimal form berisi field bisnis, toggle `is_active`, dan validasi unique `slug`.
4. Minimal table berisi kolom utama, status aktif, search, sort, filter, edit, dan delete.
5. Gunakan navigation group `Data Master` agar resource sejenis terkumpul.

## 5. Tests

1. Tambahkan feature test API untuk guest ditolak, list dengan filter/sort/pagination, create/show/update/delete, dan user tanpa permission mendapat 403.
2. Tambahkan smoke test back-office untuk halaman index/create/edit serta user tanpa permission.
3. Jalankan quality gate:

```bash
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```
