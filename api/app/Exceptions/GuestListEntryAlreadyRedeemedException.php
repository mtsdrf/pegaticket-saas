<?php

namespace App\Exceptions;

/**
 * Token de convite já resgatado — mesmo motivo de InvalidTicketStateException
 * pra não misturar com \RuntimeException genérica.
 */
class GuestListEntryAlreadyRedeemedException extends \RuntimeException {}
