<?php

namespace App\Exceptions;

/**
 * Guard novo do checkout público (roadmap Delivery, retirada na loja) —
 * cliente pediu fulfillment_type=pickup mas o tenant não habilitou
 * tenant_settings.allow_store_pickup, ou habilitou mas ainda não tem
 * endereço próprio configurado (StoreAddressService::getForTenant()==null),
 * necessário como endereço do Client quando o cliente final não informa um
 * (clients.endereco_id é NOT NULL). Mesmo espírito de StoreClosedException:
 * distinta de \RuntimeException genérica para não ser capturada por engano
 * junto de exceções HTTP do Symfony.
 */
class StorePickupUnavailableException extends \RuntimeException
{
}
