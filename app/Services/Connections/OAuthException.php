<?php

namespace App\Services\Connections;

use RuntimeException;

/** Message is a short code safe to show and log (never a provider response body). */
final class OAuthException extends RuntimeException {}
