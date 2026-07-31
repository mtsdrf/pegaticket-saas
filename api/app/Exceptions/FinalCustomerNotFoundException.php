<?php

namespace App\Exceptions;

/**
 * Token válido, subject correto, sem blacklist — mas o `sub` não corresponde
 * a nenhum FinalCustomer (registro removido após o token ser emitido). Ver
 * CustomerTokenResolver.
 */
class FinalCustomerNotFoundException extends \RuntimeException
{
}
