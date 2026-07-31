<?php

namespace App\Exceptions;

/**
 * Cupom não existe/inativo, fora da janela de datas ou carrinho abaixo do
 * mínimo exigido pelo cupom (roadmap Delivery, Fase 3) —
 * CouponService::validateForCheckout(). Distinta de
 * CouponUsageLimitReachedException (mesmo espírito de
 * StoreClosedException/BelowMinimumOrderException: nunca capturada por
 * engano junto de \RuntimeException genérica/exceções HTTP do Symfony).
 */
class InvalidCouponException extends \RuntimeException
{
}
