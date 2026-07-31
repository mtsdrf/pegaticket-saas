<?php

namespace App\Exceptions\Product;

/**
 * Arquivo CSV de importação de produtos malformado (sem cabeçalho válido,
 * coluna obrigatória ausente, arquivo vazio/ilegível) — roadmap A2.
 * Distinta de \RuntimeException genérica (mesmo espírito de
 * DuplicateNameException) para não ser capturada por engano junto de
 * exceções HTTP do Symfony.
 */
class InvalidProductImportFileException extends \RuntimeException
{
}
