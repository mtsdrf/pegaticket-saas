<?php

namespace App\Exceptions\Product;

/**
 * Importação de produtos (CSV) excedeu o limite de linhas por operação
 * (ProductImportService::MAX_ROWS) — roadmap A2. Evita parsing/preview de
 * planilhas arbitrariamente grandes numa única requisição síncrona.
 */
class ProductImportLimitExceededException extends \RuntimeException
{
    public function __construct(
        public readonly int $rowCount,
        public readonly int $maxRows
    ) {
        parent::__construct(__('messages.product_import.limit_exceeded', [
            'count' => $rowCount,
            'max' => $maxRows,
        ]));
    }
}
