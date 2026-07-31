<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant\Tenant;
use Illuminate\Support\Collection;

interface TenantRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $data): Tenant;

    /**
     * Buscar funcionalidade por slug
     *
     * @param string $slug
     * @return Tenant|null
     */
    public function findBySlug(string $slug): ?Tenant;

    /**
     * Buscar funcionalidades ativas
     * 
     * @return Collection
     */
    public function getActiveTenants(): Collection;

    /**
     * Buscar ID de funcionalidade por slug
     * 
     * @param string $slug
     * @return int|null
     */
    public function getIdBySlug(string $slug): ?int;
    public function getIdByUuid(string $uuid): ?int;

    /**
     * Verificar se slug já existe (para validação unique)
     * 
     * @param string $slug
     * @param int|null $excludeId
     * @return bool
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool;
}
