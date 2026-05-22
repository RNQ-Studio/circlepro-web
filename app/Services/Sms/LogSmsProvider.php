<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('SMS (log driver)', ['to' => $phone, 'message' => $message]);

        return true;
    }
}
