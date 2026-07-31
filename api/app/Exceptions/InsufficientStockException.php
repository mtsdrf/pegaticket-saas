<?php

namespace App\Exceptions;

/**
 * Saldo disponível insuficiente para a operação (exit/adjustment
 * decrease/transfer/block/reserve) — distinta de \RuntimeException
 * genérica pelo mesmo motivo de DuplicateNameException: exceções HTTP do
 * Symfony (abort()/ModelNotFoundException) também estendem
 * \RuntimeException e não podem ser capturadas por engano junto.
 */
class InsufficientStockException extends \RuntimeException
{
}
