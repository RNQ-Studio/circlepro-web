<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterEventRequest;
use App\Http\Resources\Api\V1\EventRegistrationResource;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Services\EventService;
use App\Support\ApiResponse;
use App\Support\Enums\RegistrationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventRegistrationController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    /** Register the authenticated user to an event division (free/bypass payment). */
    public function register(RegisterEventRequest $request, Event $event): JsonResponse
    {
        $validated = $request->validated();
        $divisionId = $validated['event_division_id'];

        // Retrieve division and ensure it belongs to the event
        $division = EventDivision::query()
            ->where('event_id', $event->id)
            ->findOrFail($divisionId);

        $userId = $request->user()->id;

        // Check if already registered
        $existing = EventRegistration::query()
            ->where('user_id', $userId)
            ->where('event_division_id', $divisionId)
            ->first();

        if ($existing !== null) {
            return ApiResponse::error('Anda sudah terdaftar di divisi ini.', 422);
        }

        // Check capacity
        if ($division->capacity !== null && $division->num_participants >= $division->capacity) {
            return ApiResponse::error('Kuota divisi ini sudah penuh.', 422);
        }

        // Create registration in transaction
        $registration = DB::transaction(function () use ($division, $userId): EventRegistration {
            $registrationId = (string) Str::ulid();

            // Generate BIB number
            $count = EventRegistration::query()
                ->where('event_division_id', $division->id)
                ->count();
            $sequence = str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
            $bowClassCode = strtoupper(substr($division->bow_class->value, 0, 3));
            $ageGroupCode = strtoupper(substr($division->age_group->value, 0, 3));
            $bibNumber = "{$bowClassCode}-{$ageGroupCode}-{$sequence}";

            // Generate QR token
            $qrCode = 'REG-'.Str::random(10).'-'.strtoupper(Str::random(4));

            $reg = EventRegistration::query()->create([
                'id' => $registrationId,
                'event_division_id' => $division->id,
                'user_id' => $userId,
                'status' => RegistrationStatus::Confirmed, // Directly confirmed
                'bib_number' => $bibNumber,
                'qr_code' => $qrCode,
            ]);

            $division->increment('num_participants');

            return $reg;
        });

        return ApiResponse::success(
            new EventRegistrationResource($registration->load(['division.event', 'user'])),
            'Pendaftaran berhasil.',
            201
        );
    }

    /** Display active tickets (registrations) for the authenticated athlete. */
    public function myTickets(Request $request): JsonResponse
    {
        $registrations = EventRegistration::query()
            ->where('user_id', $request->user()->id)
            ->with(['division.event.organization', 'user'])
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(EventRegistrationResource::collection($registrations));
    }

    /** Display all registrations for an event (organizer only). */
    public function participants(Request $request, Event $event): JsonResponse
    {
        if (! $this->eventService->canManage($request->user(), $event)) {
            return ApiResponse::error('Unauthorized to view participants.', 403);
        }

        $registrations = EventRegistration::query()
            ->whereIn('event_division_id', $event->divisions->pluck('id'))
            ->with(['division', 'user'])
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success(EventRegistrationResource::collection($registrations));
    }

    /** Check-in a participant (organizer only). */
    public function checkIn(Request $request, EventRegistration $registration): JsonResponse
    {
        $event = $registration->division->event;

        if (! $this->eventService->canManage($request->user(), $event)) {
            return ApiResponse::error('Unauthorized to manage participants.', 403);
        }

        if ($registration->status === RegistrationStatus::CheckedIn) {
            return ApiResponse::error('Peserta sudah melakukan check-in.', 422);
        }

        $registration->update([
            'status' => RegistrationStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);

        return ApiResponse::success(
            new EventRegistrationResource($registration->load(['division.event', 'user'])),
            'Check-in berhasil.'
        );
    }

    /** Update registration status (organizer only). */
    public function updateStatus(Request $request, EventRegistration $registration): JsonResponse
    {
        $event = $registration->division->event;

        if (! $this->eventService->canManage($request->user(), $event)) {
            return ApiResponse::error('Unauthorized to manage participants.', 403);
        }

        $request->validate([
            'status' => ['required', 'string', 'in:pending,waitlisted,confirmed,checked_in,cancelled,no_show'],
        ]);

        $newStatus = RegistrationStatus::from($request->string('status')->toString());
        $oldStatus = $registration->status;

        if ($newStatus === $oldStatus) {
            return ApiResponse::success(new EventRegistrationResource($registration->load(['division', 'user'])));
        }

        DB::transaction(function () use ($registration, $oldStatus, $newStatus): void {
            $registration->update([
                'status' => $newStatus,
                'checked_in_at' => $newStatus === RegistrationStatus::CheckedIn ? now() : null,
            ]);

            // Adjust division participants count
            $division = $registration->division;
            $isOldCancelled = in_array($oldStatus, [RegistrationStatus::Cancelled, RegistrationStatus::NoShow], true);
            $isNewCancelled = in_array($newStatus, [RegistrationStatus::Cancelled, RegistrationStatus::NoShow], true);

            if (! $isOldCancelled && $isNewCancelled) {
                $division->decrement('num_participants');
            } elseif ($isOldCancelled && ! $isNewCancelled) {
                $division->increment('num_participants');
            }
        });

        return ApiResponse::success(
            new EventRegistrationResource($registration->load(['division.event', 'user'])),
            'Status pendaftaran berhasil diperbarui.'
        );
    }
}
