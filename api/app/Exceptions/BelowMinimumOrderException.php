<?php

namespace App\Exceptions;

/**
 * Guard novo do checkout público (roadmap Delivery, Fase 2) — soma dos
 * itens do carrinho abaixo de tenant_settings.minimum_order_value (quando
 * configurado).
 */
class BelowMinimumOrderException extends \RuntimeException
{
}
