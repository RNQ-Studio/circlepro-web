<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadAssetRequest;
use App\Http\Resources\Api\V1\AssetResource;
use App\Services\AssetUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetUploadService $uploadService,
    ) {}

    public function upload(UploadAssetRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $asset = $this->uploadService->upload(
            file: $file,
            morphableType: $request->string('morphable_type')->toString(),
            morphableId: $request->integer('morphable_id'),
            userId: $request->user()?->getKey(),
            retainUntil: $request->date('retain_until'),
            isProtected: $request->boolean('is_protected'),
        );

        return ApiResponse::success(new AssetResource($asset), 'Asset uploaded', 201);
    }
}
