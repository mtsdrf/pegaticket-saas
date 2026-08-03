<?php

namespace App\Exceptions;

/**
 * Ação de ingresso inválida para o estado atual (transferir um ingresso já
 * usado/cancelado/estornado/bloqueado) — mesmo motivo de
 * InvalidSaleStateException/InvalidCashSessionStateException para não
 * misturar com \RuntimeException genérica.
 */
class InvalidTicketStateException extends \RuntimeException {}
