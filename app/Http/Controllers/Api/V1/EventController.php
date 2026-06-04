<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Requests\Api\V1\UpdateEventRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use App\Services\EventService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    /** Display a listing of events with filters and search. */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $events = QueryBuilder::for(Event::query())
            ->allowedFilters(
                AllowedFilter::exact('province'),
                AllowedFilter::exact('city'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('tier'),
                AllowedFilter::exact('format'),
                AllowedFilter::scope('search'),
            )
            ->with(['organization', 'creator', 'divisions'])
            ->allowedSorts('title', 'starts_at', 'created_at')
            ->defaultSort('-starts_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(EventResource::collection($events));
    }

    /** Store a newly created event. */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $this->eventService->create($request->user(), $request->validated());

        return ApiResponse::success(
            new EventResource($event->load(['organization', 'creator', 'divisions'])),
            'Event created successfully',
            201
        );
    }

    /** Display the specified event. */
    public function show(Event $event): JsonResponse
    {
        return ApiResponse::success(
            new EventResource($event->load(['organization', 'creator', 'divisions']))
        );
    }

    /** Update the specified event. */
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        if (! $this->eventService->canManage($request->user(), $event)) {
            return ApiResponse::error('Unauthorized to manage this event', 403);
        }

        $updatedEvent = $this->eventService->update($event, $request->validated());

        return ApiResponse::success(
            new EventResource($updatedEvent->load(['organization', 'creator', 'divisions'])),
            'Event updated successfully'
        );
    }

    /** Remove the specified event. */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        if (! $this->eventService->canManage($request->user(), $event)) {
            return ApiResponse::error('Unauthorized to manage this event', 403);
        }

        $event->delete();

        return ApiResponse::success(null, 'Event deleted successfully');
    }

    /** Get events created by the authenticated user. */
    public function myEvents(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $events = Event::query()
            ->where('created_by', $request->user()->id)
            ->with(['organization', 'creator', 'divisions'])
            ->orderByDesc('starts_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(EventResource::collection($events));
    }
}
