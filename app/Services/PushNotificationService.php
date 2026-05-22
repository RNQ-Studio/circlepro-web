<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Push\FcmDriverInterface;
use Illuminate\Database\Eloquent\Collection;

class PushNotificationService
{
    public function __construct(private readonly FcmDriverInterface $fcm) {}

    /**
     * Send a push notification to one or many users and persist a record.
     *
     * @param  User|Collection<int, User>  $recipients
     * @param  array<string, mixed>  $data
     */
    public function send(
        User|Collection $recipients,
        string $title,
        string $body,
        array $data = [],
        string $type = 'system',
    ): void {
        $users = $recipients instanceof User ? collect([$recipients]) : $recipients;

        foreach ($users as $user) {
            $notification = Notification::create([
                'user_id' => $user->getKey(),
                'title' => $title,
                'body' => $body,
                'data' => $data ?: null,
                'type' => $type,
            ]);

            $this->dispatchToDevices($user, $notification, $title, $body, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatchToDevices(User $user, Notification $notification, string $title, string $body, array $data): void
    {
        $devices = UserDevice::query()
            ->where('user_id', $user->getKey())
            ->withPushToken()
            ->get();

        if ($devices->isEmpty()) {
            $notification->update(['sent_at' => now()]);

            return;
        }

        $anySuccess = false;

        foreach ($devices as $device) {
            $success = $this->fcm->send((string) $device->push_token, $title, $body, $data);

            if (! $success) {
                // Invalid token — clear it to avoid future failed sends
                $device->update(['push_token' => null]);
            } else {
                $anySuccess = true;
            }
        }

        $notification->update($anySuccess
            ? ['sent_at' => now()]
            : ['failed_at' => now()]
        );
    }
}
