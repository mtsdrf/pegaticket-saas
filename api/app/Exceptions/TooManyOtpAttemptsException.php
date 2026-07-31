<?php

namespace App\Exceptions;

/**
 * Excedeu o número máximo de tentativas erradas para o código OTP vigente
 * (proteção contra força bruta). Distinta de InvalidOtpException porque o
 * contrato HTTP é diferente (o cliente precisa saber que precisa pedir um
 * código novo, não só tentar de novo).
 */
class TooManyOtpAttemptsException extends \RuntimeException
{
}
