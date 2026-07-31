<?php

namespace App\Exceptions;

/**
 * Código OTP inexistente, incorreto, expirado ou já consumido. Mesma
 * granularidade de InvalidInviteTokenException — mensagem varia por caso,
 * uma exceção só, código de erro único (INVALID_OTP) no Controller.
 */
class InvalidOtpException extends \RuntimeException
{
}
