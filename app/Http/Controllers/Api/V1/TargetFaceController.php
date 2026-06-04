<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TargetFaceResource;
use App\Models\TargetFace;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TargetFaceController extends Controller
{
    /**
     * Get list of all target faces.
     */
    public function index(): JsonResponse
    {
        $targetFaces = TargetFace::all();

        return ApiResponse::success(TargetFaceResource::collection($targetFaces));
    }

    /**
     * Get categorized bow classes (Traditional vs Modern).
     */
    public function bowClasses(): JsonResponse
    {
        return ApiResponse::success([
            'traditional' => [
                ['value' => 'horsebow', 'label' => 'Horsebow'],
                ['value' => 'jemparingan', 'label' => 'Jemparingan'],
                ['value' => 'barebow_standard', 'label' => 'Barebow Standard'],
                ['value' => 'barebow_tradisional', 'label' => 'Barebow Tradisional'],
            ],
            'modern' => [
                ['value' => 'recurve', 'label' => 'Recurve'],
                ['value' => 'compound', 'label' => 'Compound'],
            ],
        ]);
    }
}
