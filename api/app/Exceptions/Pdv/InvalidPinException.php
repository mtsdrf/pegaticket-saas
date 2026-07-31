<?php

namespace App\Exceptions\Pdv;

/**
 * PIN incorreto/inexistente no tenant, ou PIN duplicado dentro do mesmo
 * tenant ao cadastrar/trocar. Distinta de \RuntimeException genérica pelo
 * mesmo motivo de DuplicateNameException.
 */
class InvalidPinException extends \RuntimeException
{
}
