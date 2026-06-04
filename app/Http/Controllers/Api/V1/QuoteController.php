<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Models\Quote;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class QuoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $quotes = QueryBuilder::for(Quote::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::partial('text'),
                AllowedFilter::partial('author'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('author', 'is_active', 'created_at', 'updated_at')
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(QuoteResource::collection($quotes));
    }

    public function show(Quote $quote): JsonResponse
    {
        return ApiResponse::success(new QuoteResource($quote));
    }
}

