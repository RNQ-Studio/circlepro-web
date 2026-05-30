<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Enums\MemberRole;
use App\Support\Enums\OrganizationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clubs are organizations of type=club; membership lives in
 * organization_members (Module 0, task 2.7). This service centralises club
 * creation, membership and role checks.
 */
class ClubService
{
    /** Roles allowed to administer a club. */
    public const ADMIN_ROLES = [MemberRole::Owner, MemberRole::Admin];

    /**
     * Create a club and make the creator its owner.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($owner, $data): Organization {
            $club = Organization::query()->create([
                ...$data,
                'type' => OrganizationType::Club->value,
                'slug' => $this->uniqueSlug($data['name']),
            ]);

            OrganizationMember::query()->create([
                'organization_id' => $club->id,
                'user_id' => $owner->id,
                'role' => MemberRole::Owner->value,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $club;
        });
    }

    /** Join a club (idempotent). Returns the membership. */
    public function join(User $user, Organization $club): OrganizationMember
    {
        return OrganizationMember::query()->firstOrCreate(
            ['organization_id' => $club->id, 'user_id' => $user->id],
            ['role' => MemberRole::Member->value, 'status' => 'active', 'joined_at' => now()],
        );
    }

    public function leave(User $user, Organization $club): void
    {
        OrganizationMember::query()
            ->where('organization_id', $club->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    public function membershipOf(User $user, Organization $club): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->where('organization_id', $club->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function isAdmin(User $user, Organization $club): bool
    {
        $membership = $this->membershipOf($user, $club);

        return $membership !== null && in_array($membership->role, self::ADMIN_ROLES, true);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (Organization::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
