<?php

namespace App\Repositories\Contracts;

use App\Models\Functionality\Functionality;
use Illuminate\Support\Collection;

/**
 * Interface FunctionalityRepositoryInterface
 * 
 * Contrato específico para operações com Functionality.
 */
interface FunctionalityRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Buscar funcionalidade por slug
     * 
     * @param string $slug
     * @return Functionality|null
     */
    public function findBySlug(string $slug): ?Functionality;

    /**
     * Buscar funcionalidades ativas
     * 
     * @return Collection
     */
    public function getActiveFunctionalities(): Collection;

    /**
     * Buscar ID de funcionalidade por slug
     * 
     * @param string $slug
     * @return int|null
     */
    public function getIdBySlug(string $slug): ?int;

    /**
     * Verificar se slug já existe (para validação unique)
     * 
     * @param string $slug
     * @param int|null $excludeId
     * @return bool
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool;
}