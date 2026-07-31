<?php

namespace App\Services\Fiscal;

use App\DTOs\Fiscal\CreateFiscalOperationProfileDTO;
use App\DTOs\Fiscal\UpdateFiscalOperationProfileDTO;
use App\Models\Fiscal\FiscalOperationProfile;
use App\Repositories\Contracts\FiscalOperationProfileRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FiscalOperationProfileService
{
    public function __construct(
        private FiscalOperationProfileRepositoryInterface $repository
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function create(int $tenantId, CreateFiscalOperationProfileDTO $dto): FiscalOperationProfile
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            return $this->repository->create([
                'tenant_id' => $tenantId,
                'name' => $dto->name,
                'operation_nature' => $dto->operationNature,
                'document_type' => $dto->documentType,
                'default_cfop' => $dto->defaultCfop,
                'scope' => $dto->scope,
                'description' => $dto->description,
                'is_active' => $dto->isActive,
            ]);
        });
    }

    public function update(int $tenantId, string $uuid, UpdateFiscalOperationProfileDTO $dto): FiscalOperationProfile
    {
        return DB::transaction(function () use ($tenantId, $uuid, $dto) {
            $profile = $this->findScopedOrFail($tenantId, $uuid);

            return $this->repository->update($profile, [
                'name' => $dto->name,
                'operation_nature' => $dto->operationNature,
                'document_type' => $dto->documentType,
                'default_cfop' => $dto->defaultCfop,
                'scope' => $dto->scope,
                'description' => $dto->description,
                'is_active' => $dto->isActive,
            ]);
        });
    }

    public function delete(int $tenantId, string $uuid): void
    {
        DB::transaction(function () use ($tenantId, $uuid) {
            $profile = $this->findScopedOrFail($tenantId, $uuid);
            $this->repository->delete($profile);
        });
    }

    private function findScopedOrFail(int $tenantId, string $uuid): FiscalOperationProfile
    {
        return FiscalOperationProfile::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }
}
