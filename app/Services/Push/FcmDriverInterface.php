<?php

namespace App\Services\Push;

interface FcmDriverInterface
{
    /**
     * Send a push notification to a single FCM token.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): bool;
}
