<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Models\Quote;
use App\Models\QuoteLove;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class QuoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 1000);

        $base = Quote::query();

        // If the user is authenticated, load whether they loved each quote
        $user = $request->user();
        if ($user) {
            $base->withExists(['loves as is_loved' => fn ($q) => $q->where('user_id', $user->id)]);
        }

        $quotes = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::partial('text'),
                AllowedFilter::partial('author'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('author', 'is_active', 'love_count', 'created_at', 'updated_at')
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(QuoteResource::collection($quotes));
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $quote->loadExists(['loves as is_loved' => fn ($q) => $q->where('user_id', $user->id)]);
        }

        return ApiResponse::success(new QuoteResource($quote));
    }

    public function love(Request $request, Quote $quote): JsonResponse
    {
        $love = QuoteLove::query()->firstOrCreate([
            'quote_id' => $quote->id,
            'user_id' => $request->user()->id,
        ]);

        if ($love->wasRecentlyCreated) {
            $quote->increment('love_count');
        }

        return ApiResponse::success([
            'loved' => true,
            'love_count' => $quote->refresh()->love_count,
        ]);
    }

    public function unlove(Request $request, Quote $quote): JsonResponse
    {
        $deleted = QuoteLove::query()
            ->where('quote_id', $quote->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($deleted > 0) {
            $quote->decrement('love_count');
        }

        return ApiResponse::success([
            'loved' => false,
            'love_count' => $quote->refresh()->love_count,
        ]);
    }
}
