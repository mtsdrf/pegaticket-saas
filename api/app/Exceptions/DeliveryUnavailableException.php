<?php

namespace App\Exceptions;

/**
 * Guard simétrico de StorePickupUnavailableException — cliente pediu
 * fulfillment_type=delivery mas o tenant desabilitou explicitamente
 * tenant_settings.allow_delivery. Distinta de \RuntimeException genérica
 * para não ser capturada por engano junto de exceções HTTP do Symfony.
 */
class DeliveryUnavailableException extends \RuntimeException
{
}
