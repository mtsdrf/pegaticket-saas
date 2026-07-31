<?php

namespace App\Exceptions;

/**
 * Limite de uso do cupom esgotado — total (max_uses_total) ou por cliente
 * (max_uses_per_customer), CouponService::validateForCheckout() (roadmap
 * Delivery, Fase 3). Distinta de InvalidCouponException: o cupom em si é
 * válido/ativo, só o limite de uso já foi atingido.
 */
class CouponUsageLimitReachedException extends \RuntimeException
{
}
