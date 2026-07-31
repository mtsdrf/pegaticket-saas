<?php

namespace App\Exceptions;

/**
 * Inconsistência na cadeia Estado → Cidade → Bairro (ex.: bairro informado
 * não pertence à cidade informada). Distinta de \RuntimeException genérica
 * pelo mesmo motivo de DuplicateNameException — exceções HTTP do Symfony
 * (incluindo o abort(404) do guard de tenant) também estendem \RuntimeException.
 */
class LocationChainException extends \RuntimeException
{
}
