<?php

namespace App\Repositories\Contracts;

use App\Models\FinalCustomer\FinalCustomer;

/**
 * Contrato dedicado, NÃO estende BaseRepositoryInterface: `final_customers`
 * não tem `deleted_at` (sem soft delete, ver migration), e todo método
 * herdado da base assume `whereNull('deleted_at')` em toda query — mesmo
 * desvio já documentado em AuditLogRepositoryInterface.
 */
interface FinalCustomerRepositoryInterface
{
    public function findByEmail(string $email): ?FinalCustomer;

    public function findById(int $id): ?FinalCustomer;

    public function create(array $data): FinalCustomer;
}
