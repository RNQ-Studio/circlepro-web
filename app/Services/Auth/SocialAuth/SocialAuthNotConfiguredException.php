<?php

namespace App\Services\Auth\SocialAuth;

use RuntimeException;

/**
 * Thrown when a social provider (e.g. Google) has no credentials configured.
 * Mapped to HTTP 501 SOCIAL_AUTH_NOT_CONFIGURED by the controller.
 */
class SocialAuthNotConfiguredException extends RuntimeException {}
