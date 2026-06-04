<?php

namespace App\Policies;

use App\Models\TargetFace;
use App\Models\User;

class TargetFacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('target_faces.viewAny');
    }

    public function view(User $user, TargetFace $targetFace): bool
    {
        return $user->can('target_faces.view');
    }

    public function create(User $user): bool
    {
        return $user->can('target_faces.create');
    }

    public function update(User $user, TargetFace $targetFace): bool
    {
        return $user->can('target_faces.update');
    }

    public function delete(User $user, TargetFace $targetFace): bool
    {
        return $user->can('target_faces.delete');
    }

    public function restore(User $user, TargetFace $targetFace): bool
    {
        return $user->can('target_faces.update');
    }

    public function forceDelete(User $user, TargetFace $targetFace): bool
    {
        return $user->can('target_faces.delete');
    }
}
