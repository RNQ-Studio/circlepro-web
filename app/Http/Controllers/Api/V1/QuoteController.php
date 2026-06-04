<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Models\Quote;
use App\Models\QuoteLove;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $sort = $request->input('sort');
        $isRandom = ($sort === 'random' || $sort === '-random');

        if ($isRandom) {
            $seed = $request->input('seed');
            if ($seed !== null) {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    // Ensure seed is mapped to a float between -1.0 and 1.0 for Postgres setseed
                    $hash = crc32((string) $seed);
                    $floatSeed = ($hash / 4294967295.0) * 2.0 - 1.0;
                    DB::statement('SELECT setseed('.(float) $floatSeed.')');
                    $base->inRandomOrder();
                } elseif ($driver === 'mysql') {
                    $base->inRandomOrder($seed);
                } else {
                    $base->inRandomOrder();
                }
            } else {
                $base->inRandomOrder();
            }

            // Clear sort parameter to prevent Spatie QueryBuilder from throwing an exception or overriding order
            $request->query->remove('sort');
            $request->request->remove('sort');
        }

        $builder = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::partial('text'),
                AllowedFilter::partial('author'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('author', 'is_active', 'love_count', 'created_at', 'updated_at');

        if (! $isRandom) {
            $builder->defaultSort('-created_at');
        }

        $quotes = $builder->paginate($perPage)
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
