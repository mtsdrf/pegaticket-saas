<?php

namespace App\DTOs\Product;

/**
 * Entrada mutável do commit de importação de produtos (roadmap A2). `rows`
 * reproduz o mesmo shape retornado por ProductImportService::preview()
 * (nome, categoria, tipo, preco, descricao, sku, disponivel por linha) —
 * o frontend reenvia as linhas já validadas no preview, sem estado de
 * sessão guardado no servidor. Cada linha é revalidada do zero no Service
 * (nunca confiar só no preview do cliente).
 */
class CommitProductImportDTO
{
    public function __construct(
        public readonly array $rows,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rows: $data['rows'] ?? [],
        );
    }
}
