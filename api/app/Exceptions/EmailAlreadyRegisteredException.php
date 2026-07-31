<?php

namespace App\Exceptions;

/**
 * Já existe um User global com o e-mail informado — bloqueia convite/aceite
 * em vez de vincular silenciosamente. Distinta de \RuntimeException
 * genérica pelo mesmo motivo documentado em DuplicateNameException.
 */
class EmailAlreadyRegisteredException extends \RuntimeException
{
}
