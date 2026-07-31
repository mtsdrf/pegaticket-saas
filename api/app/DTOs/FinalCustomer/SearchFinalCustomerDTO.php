<?php

namespace App\DTOs\FinalCustomer;

/**
 * Filtros da busca de compradores (FinalCustomerTenantLink) pelo staff,
 * usada no fluxo de pedido manual (OrderFormPage). Segue o mesmo padrão de
 * DTO de listagem/filtro do restante do projeto (ex.: nenhum outro módulo
 * de listagem usa DTO — ProductType/ProductCategory passam array direto do
 * Request pro Service — mas aqui optamos por DTO por já haver mais de um
 * campo estruturado e por ser o padrão pedido para entrada mutável).
 */
class SearchFinalCustomerDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly int $perPage,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            perPage: (int) ($data['per_page'] ?? 15),
        );
    }
}
