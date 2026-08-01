<?php

namespace App\Exceptions;

/**
 * Pedido já tem uma avaliação registrada (unique em order_ratings.order_id)
 * — checada explicitamente em SaleRatingService::rate() antes de deixar o
 * DB estourar UniqueConstraintViolationException, mesmo cuidado já usado em
 * DuplicateNameException/CouponUsageLimitReachedException.
 */
class SaleAlreadyRatedException extends \RuntimeException
{
}
