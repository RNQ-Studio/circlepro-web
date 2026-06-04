<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStoryRequest;
use App\Http\Resources\Api\V1\StoryGroupResource;
use App\Http\Resources\Api\V1\StoryResource;
use App\Models\Story;
use App\Models\User;
use App\Services\AssetDeletionService;
use App\Services\AssetUploadService;
use App\Support\ApiResponse;
use App\Support\Enums\MediaType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function __construct(
        private readonly AssetUploadService $assetUploadService,
        private readonly AssetDeletionService $assetDeletionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->whereHas('stories', function ($query) {
                $query->active();
            })
            ->with(['profile', 'stories' => function ($query) {
                $query->active()->latest();
            }])
            ->get();

        // Urutkan grup user berdasarkan story terbaru yang mereka buat
        $sortedUsers = $users->sortByDesc(function ($user) {
            return $user->stories->max('created_at');
        })->values();

        return ApiResponse::success(StoryGroupResource::collection($sortedUsers));
    }

    public function store(StoreStoryRequest $request): JsonResponse
    {
        $file = $request->file('file');

        // Unggah ke storage dengan retensi 24 jam
        $asset = $this->assetUploadService->upload(
            file: $file,
            type: 'story',
            userId: $request->user()->id,
            retainUntil: now()->addHours(24)
        );

        $mediaType = str_starts_with($asset->mime_type, 'video/') ? MediaType::Video : MediaType::Image;

        $story = Story::query()->create([
            'user_id' => $request->user()->id,
            'asset_id' => $asset->id,
            'media_type' => $mediaType,
            'media_url' => $asset->getPublicUrl(),
            'expires_at' => now()->addHours(24),
        ]);

        return ApiResponse::success(
            new StoryResource($story),
            'Story created',
            201
        );
    }

    public function destroy(Request $request, Story $story): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403, 'Not your story.');

        // Hapus asset fisik dan database asset
        if ($story->asset) {
            $this->assetDeletionService->hardDelete($story->asset);
        }

        $story->delete();

        return ApiResponse::success(null, 'Story deleted');
    }
}
