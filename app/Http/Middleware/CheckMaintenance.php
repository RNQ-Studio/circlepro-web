<?php

namespace App\Http\Middleware;

use App\Models\AppConfig;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) AppConfig::get('maintenance_mode', false)) {
            $message = (string) AppConfig::get('maintenance_message', 'Service temporarily unavailable.');

            return ApiResponse::error($message, 503);
        }

        return $next($request);
    }
}
