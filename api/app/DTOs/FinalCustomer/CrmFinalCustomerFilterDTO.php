<?php

namespace App\DTOs\FinalCustomer;

/**
 * Filtros de segmentação básica do CRM de compradores (Fase 6, fatia final)
 * — reaproveita os totais já agregados por `crmSummaryForTenant()`
 * (total gasto/quantidade de compras/última compra), sem motor de regras
 * salvas em banco (deliberadamente fora de escopo nesta rodada, ver
 * roadmap). Todos os filtros são opcionais e combináveis.
 */
class CrmFinalCustomerFilterDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly int $perPage,
        // "Gastou acima de X" — soma de Sale.total_amount paga neste tenant.
        public readonly ?float $minSpent,
        // "Comprou pelo menos N vez(es)" — contagem de vendas pagas.
        public readonly ?int $minPurchases,
        // "Não compra há mais de N dias" — dias desde a última venda paga.
        public readonly ?int $inactiveDays,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            perPage: (int) ($data['per_page'] ?? 15),
            minSpent: isset($data['min_spent']) ? (float) $data['min_spent'] : null,
            minPurchases: isset($data['min_purchases']) ? (int) $data['min_purchases'] : null,
            inactiveDays: isset($data['inactive_days']) ? (int) $data['inactive_days'] : null,
        );
    }
}
