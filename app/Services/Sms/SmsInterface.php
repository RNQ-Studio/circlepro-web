<?php

namespace App\Services\Sms;

interface SmsInterface
{
    public function send(string $phone, string $message): bool;
}
