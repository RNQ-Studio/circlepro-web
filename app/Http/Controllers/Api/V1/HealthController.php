<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'status' => 'ok',
                'app' => config('app.name'),
                'time' => now()->toIso8601String(),
            ],
            message: 'Service is healthy',
        );
    }
}
