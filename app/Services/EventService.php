<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\Organization;
use App\Models\User;
use App\Support\Enums\MemberRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EventService centralises Event CRUD operations, division management,
 * and authorization checks.
 */
class EventService
{
    /**
     * Create an event with its divisions in a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $creator, array $data): Event
    {
        return DB::transaction(function () use ($creator, $data): Event {
            $divisionsData = $data['divisions'] ?? [];
            unset($data['divisions']);

            $event = Event::query()->create([
                ...$data,
                'created_by' => $creator->id,
                'slug' => $this->uniqueSlug($data['title']),
            ]);

            foreach ($divisionsData as $div) {
                $event->divisions()->create($div);
            }

            return $event->load('divisions');
        });
    }

    /**
     * Update an event and sync its divisions in a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data): Event {
            $divisionsData = $data['divisions'] ?? null;
            unset($data['divisions']);

            $event->update($data);

            if ($divisionsData !== null) {
                $existingIds = [];
                foreach ($divisionsData as $div) {
                    if (isset($div['id'])) {
                        $division = EventDivision::query()
                            ->where('event_id', $event->id)
                            ->findOrFail($div['id']);
                        $division->update($div);
                        $existingIds[] = $division->id;
                    } else {
                        $newDivision = $event->divisions()->create($div);
                        $existingIds[] = $newDivision->id;
                    }
                }
                // Delete divisions not present in request
                $event->divisions()->whereNotIn('id', $existingIds)->delete();
            }

            return $event->load('divisions');
        });
    }

    /**
     * Check if a user can administer the event (is creator, organization owner, or organization admin).
     */
    public function canManage(User $user, Event $event): bool
    {
        if ($event->created_by === $user->id) {
            return true;
        }

        $organization = $event->organization;
        if ($organization === null) {
            return false;
        }

        $membership = $user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->first();

        if ($membership === null) {
            return false;
        }

        return in_array($membership->role, [MemberRole::Owner, MemberRole::Admin], true);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (Event::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
