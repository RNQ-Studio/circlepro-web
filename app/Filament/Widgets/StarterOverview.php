<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Role;

class StarterOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $users = User::query()->count();
        $activeUsers = User::query()->where('is_active', true)->count();
        $categories = Category::query()->count();
        $activeCategories = Category::query()->where('is_active', true)->count();

        return [
            Stat::make('Users', number_format($users))
                ->description(number_format($activeUsers).' active')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
            Stat::make('Roles', number_format(Role::query()->count()))
                ->description('Access profiles')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('warning'),
            Stat::make('Categories', number_format($categories))
                ->description(number_format($activeCategories).' active')
                ->descriptionIcon('heroicon-m-tag')
                ->color('success'),
        ];
    }
}
