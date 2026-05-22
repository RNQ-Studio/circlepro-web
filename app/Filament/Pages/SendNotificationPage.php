<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\PushNotificationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SendNotificationPage extends Page
{
    protected string $view = 'filament.pages.send-notification';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'App Management';

    protected static ?string $navigationLabel = 'Send Notification';

    protected static ?string $title = 'Send Push Notification';

    public ?string $title_input = null;

    public ?string $body = null;

    public ?string $type = 'system';

    public bool $sendToAll = true;

    public ?int $userId = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('sendToAll')
                ->label('Send to all users')
                ->default(true)
                ->live(),
            Select::make('userId')
                ->label('Target user')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->limit(20)
                    ->pluck('name', 'id')
                    ->toArray()
                )
                ->hidden(fn (): bool => $this->sendToAll),
            Select::make('type')
                ->options(['system' => 'System', 'promo' => 'Promo', 'info' => 'Info'])
                ->default('system')
                ->required(),
            TextInput::make('title_input')
                ->label('Title')
                ->required()
                ->maxLength(100),
            Textarea::make('body')
                ->label('Message')
                ->required()
                ->rows(3),
        ]);
    }

    public function send(PushNotificationService $pushService): void
    {
        $this->validate([
            'title_input' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string'],
            'type' => ['required', 'string'],
        ]);

        $recipients = $this->sendToAll
            ? User::query()->get()
            : User::query()->where('id', $this->userId)->get();

        if ($recipients->isEmpty()) {
            Notification::make()->title('No recipients found.')->warning()->send();

            return;
        }

        $pushService->send($recipients, (string) $this->title_input, (string) $this->body, [], (string) $this->type);

        Notification::make()
            ->title('Notification sent to '.$recipients->count().' user(s).')
            ->success()
            ->send();

        $this->title_input = null;
        $this->body = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Send Notification')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->action('send'),
        ];
    }
}
