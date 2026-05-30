<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEquipmentProfileRequest;
use App\Http\Requests\Api\V1\UpdateEquipmentProfileRequest;
use App\Http\Resources\Api\V1\EquipmentProfileResource;
use App\Models\EquipmentProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD for per-user equipment/bow profiles (Module 1, task 1.11a).
 */
class EquipmentProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profiles = $request->user()->equipmentProfiles()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(EquipmentProfileResource::collection($profiles));
    }

    public function store(StoreEquipmentProfileRequest $request): JsonResponse
    {
        $profile = DB::transaction(function () use ($request): EquipmentProfile {
            $data = $request->validated();
            $data['user_id'] = $request->user()->id;

            $profile = EquipmentProfile::query()->create($data);

            if ($profile->is_default) {
                $this->clearOtherDefaults($request->user()->id, $profile->id);
            }

            return $profile;
        });

        return ApiResponse::success(new EquipmentProfileResource($profile), 'Equipment profile created', 201);
    }

    public function show(Request $request, EquipmentProfile $equipmentProfile): JsonResponse
    {
        $this->authorizeOwner($request, $equipmentProfile);

        return ApiResponse::success(new EquipmentProfileResource($equipmentProfile));
    }

    public function update(UpdateEquipmentProfileRequest $request, EquipmentProfile $equipmentProfile): JsonResponse
    {
        $this->authorizeOwner($request, $equipmentProfile);

        DB::transaction(function () use ($request, $equipmentProfile): void {
            $equipmentProfile->update($request->validated());

            if ($equipmentProfile->is_default) {
                $this->clearOtherDefaults($request->user()->id, $equipmentProfile->id);
            }
        });

        return ApiResponse::success(new EquipmentProfileResource($equipmentProfile->refresh()), 'Equipment profile updated');
    }

    public function destroy(Request $request, EquipmentProfile $equipmentProfile): JsonResponse
    {
        $this->authorizeOwner($request, $equipmentProfile);

        $equipmentProfile->delete();

        return ApiResponse::success(null, 'Equipment profile deleted');
    }

    private function authorizeOwner(Request $request, EquipmentProfile $profile): void
    {
        abort_unless($profile->user_id === $request->user()->id, 404, 'Resource not found.');
    }

    private function clearOtherDefaults(int $userId, string $keepId): void
    {
        EquipmentProfile::query()
            ->where('user_id', $userId)
            ->where('id', '!=', $keepId)
            ->update(['is_default' => false]);
    }
}
