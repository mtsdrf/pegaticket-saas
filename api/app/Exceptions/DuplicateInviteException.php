<?php

namespace App\Exceptions;

/**
 * Já existe um convite pendente (não expirado, não aceito) para o mesmo
 * e-mail na mesma tenant.
 */
class DuplicateInviteException extends \RuntimeException
{
}
