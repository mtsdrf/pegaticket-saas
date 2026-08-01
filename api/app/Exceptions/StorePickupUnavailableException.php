<?php

namespace App\Exceptions;

/**
 * Guard do checkout público para retirada na loja quando o tenant não
 * habilitou `tenant_settings.allow_store_pickup`. Distinta de
 * \RuntimeException genérica para não ser capturada por engano junto de
 * exceções HTTP do Symfony.
 */
class StorePickupUnavailableException extends \RuntimeException
{
}
