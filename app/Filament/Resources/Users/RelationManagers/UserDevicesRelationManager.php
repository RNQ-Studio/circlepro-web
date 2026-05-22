<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserDevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'Devices';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'android' => 'success',
                        'ios' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('device_name')
                    ->label('Device')
                    ->default('—'),
                TextColumn::make('app_version')
                    ->label('App Version')
                    ->default('—'),
                TextColumn::make('os_version')
                    ->label('OS Version')
                    ->default('—'),
                IconColumn::make('push_token')
                    ->label('Push Token')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => ! empty($record->push_token)),
                TextColumn::make('last_active_at')
                    ->label('Last Active')
                    ->dateTime()
                    ->since()
                    ->default('Never'),
            ])
            ->defaultSort('last_active_at', 'desc')
            ->actions([
                DeleteAction::make()->label('Revoke'),
            ]);
    }
}
