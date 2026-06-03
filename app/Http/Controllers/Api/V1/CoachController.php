<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CoachProfileResource;
use App\Models\CoachProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CoachProfile::query()
            ->where('is_verified', true)
            ->with(['user.profile']);

        $filters = $request->input('filter', []);


        if (!empty($filters['specialty'])) {
            $specialty = $filters['specialty'];
            if (\DB::connection()->getDriverName() === 'sqlite') {
                $query->where('specialties', 'like', "%{$specialty}%");
            } else {
                $query->whereJsonContains('specialties', $specialty);
            }

        }

        if (!empty($filters['city'])) {
            $city = $filters['city'];
            $query->whereHas('user.profile', function ($q) use ($city) {
                $q->where('city', 'like', "%{$city}%");
            });
        }

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhereHas('profile', function ($qp) use ($term) {
                      $qp->where('full_name', 'like', "%{$term}%");
                  });
            });
        }



        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $coaches = $query->paginate($perPage);

        return ApiResponse::success(CoachProfileResource::collection($coaches));
    }

    public function show(CoachProfile $coach): JsonResponse
    {
        return ApiResponse::success(new CoachProfileResource($coach->load(['user.profile'])));
    }

    public function store(Request $request): JsonResponse
    {
        // Check if user already has a coach profile
        $existing = CoachProfile::query()->where('user_id', $request->user()->id)->first();
        if ($existing) {
            return ApiResponse::error('Anda sudah terdaftar sebagai pelatih.', 422);
        }

        $validated = $request->validate([
            'bio' => 'required|string',
            'specialties' => 'required|array',
            'specialties.*' => 'string',
            'certification' => 'nullable|string|max:150',
            'experience_years' => 'required|integer|min:0',
            'hourly_rate' => 'required|numeric|min:0',
            'whatsapp_number' => 'nullable|string|max:20',
            'availability' => 'nullable|array',
        ]);

        $coach = CoachProfile::query()->create([
            'user_id' => $request->user()->id,
            'bio' => $validated['bio'],
            'specialties' => $validated['specialties'],
            'certification' => $validated['certification'] ?? null,
            'experience_years' => $validated['experience_years'],
            'hourly_rate' => $validated['hourly_rate'],
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
            'is_verified' => true, // Default true for sandbox ease
            'availability' => $validated['availability'] ?? null,
        ]);

        return ApiResponse::success(new CoachProfileResource($coach->load('user.profile')), 'Profil pelatih berhasil dibuat.', 201);
    }

    public function update(Request $request, CoachProfile $coach): JsonResponse
    {
        abort_unless($coach->user_id === $request->user()->id, 403, 'Anda tidak memiliki akses ke profil ini.');

        $validated = $request->validate([
            'bio' => 'required|string',
            'specialties' => 'required|array',
            'specialties.*' => 'string',
            'certification' => 'nullable|string|max:150',
            'experience_years' => 'required|integer|min:0',
            'hourly_rate' => 'required|numeric|min:0',
            'whatsapp_number' => 'nullable|string|max:20',
            'availability' => 'nullable|array',
        ]);

        $coach->update([
            'bio' => $validated['bio'],
            'specialties' => $validated['specialties'],
            'certification' => $validated['certification'] ?? null,
            'experience_years' => $validated['experience_years'],
            'hourly_rate' => $validated['hourly_rate'],
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
            'availability' => $validated['availability'] ?? null,
        ]);

        return ApiResponse::success(new CoachProfileResource($coach->load('user.profile')), 'Profil pelatih berhasil diperbarui.');
    }
}
