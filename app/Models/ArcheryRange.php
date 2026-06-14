<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $address
 * @property string|null $city
 * @property string|null $province
 * @property float|null $latitude
 * @property float|null $longitude
 * @property array|null $facilities
 * @property string|null $phone
 * @property float $price_per_hour
 * @property string|null $image_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property float|null $distance transient: km from a query point (geo sort), not persisted
 */
class ArcheryRange extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'description',
        'address',
        'city',
        'province',
        'latitude',
        'longitude',
        'facilities',
        'phone',
        'price_per_hour',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'price_per_hour' => 'float',
        ];
    }
}
