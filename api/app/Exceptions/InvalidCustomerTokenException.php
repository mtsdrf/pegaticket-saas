<?php

namespace App\Exceptions;

/**
 * Token JWT válido mas de outra identidade (não FinalCustomer) ou sem claim
 * `jti` — ver CustomerTokenResolver.
 */
class InvalidCustomerTokenException extends \RuntimeException
{
}
