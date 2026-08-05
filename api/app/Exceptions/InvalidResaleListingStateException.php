<?php

namespace App\Exceptions;

/**
 * Ação inválida sobre um anúncio de revenda (comprar/cancelar um anúncio
 * que não está mais `listado`, preço acima do teto, etc.) — mesmo motivo de
 * InvalidTicketStateException para não misturar com \RuntimeException
 * genérica.
 */
class InvalidResaleListingStateException extends \RuntimeException {}
