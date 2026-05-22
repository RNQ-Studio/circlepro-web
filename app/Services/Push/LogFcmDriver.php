<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;

class LogFcmDriver implements FcmDriverInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        Log::info('FCM (log driver)', compact('token', 'title', 'body', 'data'));

        return true;
    }
}
