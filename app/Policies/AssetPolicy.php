<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    // Semua pengecekan memakai permission `assets.*`. Role super-admin
    // otomatis lolos lewat Gate::before (AppServiceProvider), sedangkan
    // role lain tidak diberi permission ini sehingga menu hanya muncul
    // untuk super-admin.

    public function viewAny(User $user): bool
    {
        return $user->can('assets.viewAny');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('assets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('assets.create');
    }

    public function update(User $user, Asset $asset): bool
    {
        return false;
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('assets.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('assets.delete');
    }

    public function restore(User $user, Asset $asset): bool
    {
        return $user->can('assets.restore');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('assets.restore');
    }

    public function forceDelete(User $user, Asset $asset): bool
    {
        return $user->can('assets.forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('assets.forceDelete');
    }
}
