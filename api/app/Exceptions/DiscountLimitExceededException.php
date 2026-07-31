<?php

namespace App\Exceptions;

/**
 * Desconto manual (override de items[].unit_price abaixo do preço
 * resolvido) ultrapassa o discount_limit_percent configurado pro perfil
 * do ator autenticado — roadmap A1.5. Distinta de \RuntimeException
 * genérica pelo mesmo motivo de DuplicateNameException/InvalidOrderStateException.
 */
class DiscountLimitExceededException extends \RuntimeException
{
}
