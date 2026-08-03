<?php

namespace App\Exceptions;

/**
 * Ação de Venda inválida para o estado atual (já cancelado, já pago,
 * parcela já paga, ação de parcela chamada numa venda não parcelado,
 * reserva de estoque ausente, etc.) — distinta de \RuntimeException
 * genérica pelo mesmo motivo de DuplicateNameException/InsufficientStockException:
 * exceções HTTP do Symfony (abort()/ModelNotFoundException) também
 * estendem \RuntimeException e não podem ser capturadas por engano junto.
 */
class InvalidSaleStateException extends \RuntimeException
{
}
