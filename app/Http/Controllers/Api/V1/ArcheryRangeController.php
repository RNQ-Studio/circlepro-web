<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArcheryRangeResource;
use App\Models\ArcheryRange;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ArcheryRangeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ArcheryRange::query();
        $filters = $request->input('filter', []);

        // 1. Search filter (name, city, province)
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('city', 'like', "%{$term}%")
                  ->orWhere('province', 'like', "%{$term}%");
            });
        }

        // 2. Facility filter
        if (!empty($filters['facility'])) {
            $facility = $filters['facility'];
            if (\DB::connection()->getDriverName() === 'sqlite') {
                $query->where('facilities', 'like', "%{$facility}%");
            } else {
                $query->whereJsonContains('facilities', $facility);
            }
        }

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        if ($latitude !== null && $longitude !== null) {
            $lat = (float) $latitude;
            $lng = (float) $longitude;

            if (\DB::connection()->getDriverName() === 'sqlite') {
                // SQLite has no math functions by default, calculate in PHP
                $items = $query->get()->map(function ($item) use ($lat, $lng) {
                    if ($item->latitude !== null && $item->longitude !== null) {
                        $item->distance = $this->calculateHaversine($lat, $lng, $item->latitude, $item->longitude);
                    } else {
                        $item->distance = null;
                    }
                    return $item;
                })->sortBy('distance')->values();

                $page = LengthAwarePaginator::resolveCurrentPage();
                $slice = $items->slice(($page - 1) * $perPage, $perPage);
                $paginated = new LengthAwarePaginator($slice, $items->count(), $perPage, $page, [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                ]);

                return ApiResponse::success(ArcheryRangeResource::collection($paginated));
            } else {
                // Production database (MySQL/PostgreSQL) calculation
                $query->selectRaw(
                    "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                    [$lat, $lng, $lat]
                )->orderBy('distance', 'asc');

                $ranges = $query->paginate($perPage);
                return ApiResponse::success(ArcheryRangeResource::collection($ranges));
            }
        }

        $ranges = $query->paginate($perPage);
        return ApiResponse::success(ArcheryRangeResource::collection($ranges));
    }

    public function show(ArcheryRange $range): JsonResponse
    {
        return ApiResponse::success(new ArcheryRangeResource($range));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'phone' => 'nullable|string|max:20',
            'price_per_hour' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|url',
        ]);

        $range = ArcheryRange::query()->create($validated);

        return ApiResponse::success(new ArcheryRangeResource($range), 'Lapangan panahan berhasil ditambahkan.', 201);
    }

    public function update(Request $request, ArcheryRange $range): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'phone' => 'nullable|string|max:20',
            'price_per_hour' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|url',
        ]);

        $range->update($validated);

        return ApiResponse::success(new ArcheryRangeResource($range), 'Lapangan panahan berhasil diperbarui.');
    }

    public function destroy(ArcheryRange $range): JsonResponse
    {
        $range->delete();
        return ApiResponse::success(null, 'Lapangan panahan berhasil dihapus.');
    }

    private function calculateHaversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
