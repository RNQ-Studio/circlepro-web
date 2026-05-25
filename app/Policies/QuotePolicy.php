<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotes.viewAny');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->can('quotes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotes.create');
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->can('quotes.update');
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->can('quotes.delete');
    }

    public function restore(User $user, Quote $quote): bool
    {
        return $user->can('quotes.update');
    }

    public function forceDelete(User $user, Quote $quote): bool
    {
        return $user->can('quotes.delete');
    }
}
