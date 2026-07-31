<?php

namespace App\Exceptions;

/**
 * Violação de unicidade de nome/slug dentro do tenant — distinta de
 * \RuntimeException genérica para não ser capturada por engano junto com
 * exceções HTTP (NotFoundHttpException/ModelNotFoundException também
 * estendem \RuntimeException).
 */
class DuplicateNameException extends \RuntimeException
{
}
