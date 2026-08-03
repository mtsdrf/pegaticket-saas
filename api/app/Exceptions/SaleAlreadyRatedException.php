<?php

namespace App\Exceptions;

/**
 * Venda já tem uma avaliação registrada (unique em sale_ratings.sale_id)
 * — checada explicitamente em SaleRatingService::rate() antes de deixar o
 * DB estourar UniqueConstraintViolationException, mesmo cuidado já usado em
 * DuplicateNameException/CouponUsageLimitReachedException.
 */
class SaleAlreadyRatedException extends \RuntimeException
{
}
