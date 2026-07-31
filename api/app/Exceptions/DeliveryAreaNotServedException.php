<?php

namespace App\Exceptions;

/**
 * Guard novo do checkout público (roadmap Delivery, Fase 2) — bairro do
 * endereço de entrega sem taxa cadastrada pro tenant
 * (StoreDeliveryFeeService::findFee() retornou null). Decisão travada com o
 * usuário: bairro sem taxa BLOQUEIA a entrega, nunca assume frete
 * grátis/padrão por omissão.
 */
class DeliveryAreaNotServedException extends \RuntimeException
{
}
