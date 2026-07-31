<?php

namespace App\Exceptions;

/**
 * jti do token consta em TokenBlacklist (logout explícito) — ver
 * CustomerTokenResolver.
 */
class CustomerTokenBlacklistedException extends \RuntimeException
{
}
