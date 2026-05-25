<?php

namespace App\Filament\Resources\Quotes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('text')
                    ->required()
                    ->minLength(5)
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                TextInput::make('source')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
