<?php

namespace App\Exceptions;

/**
 * Operação de estoque referenciando um StockMovement em estado/tipo
 * inválido para a ação pedida (ex.: reserve-cancel apontando para um
 * movimento que não é do tipo `reserve`).
 */
class InvalidStockMovementException extends \RuntimeException
{
}
