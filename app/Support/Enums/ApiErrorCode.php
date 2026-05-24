<?php

namespace App\Support\Enums;

enum ApiErrorCode: string
{
    case AuthInvalidCredentials = 'AUTH_INVALID_CREDENTIALS';
    case AuthInactiveAccount = 'AUTH_INACTIVE_ACCOUNT';
    case AuthTokenExpired = 'AUTH_TOKEN_EXPIRED';
    case ValidationFailed = 'VALIDATION_FAILED';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case RateLimitExceeded = 'RATE_LIMIT_EXCEEDED';
    case MaintenanceMode = 'MAINTENANCE_MODE';
    case ServerError = 'SERVER_ERROR';
}
