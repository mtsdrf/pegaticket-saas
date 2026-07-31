<?php

namespace App\Services\Tenant;

use App\DTOs\Tenant\CreateTenantDTO;
use App\DTOs\Tenant\UpdateTenantDTO;
use App\DTOs\Tenant\UpdateTenantProfileDTO;
use App\Events\Tenant\TenantCreated;
use App\Events\Tenant\TenantUpdated;
use App\Events\Tenant\TenantDeleted;
use App\Models\Tenant\Tenant;
use App\Models\Plan\Plan;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Media\MediaStorageService;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class TenantService
 * 
 * Camada de serviço para Tenant.
 * Orquestra regras de negócio e delega persistência ao Repository.
 */
class TenantService
{
    /**
     * @var TenantRepositoryInterface
     */
    protected TenantRepositoryInterface $repository;

    /**
     * TenantService constructor.
     */
    public function __construct(
        TenantRepositoryInterface $repository,
        private TenantProvisioningService $provisioningService,
        private MediaStorageService $mediaStorage
    )
    {
        $this->repository = $repository;
    }

    /**
     * Listar tenants paginados
     */
    public function paginate(
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'name' => 'tenants.name',
            'slug' => 'tenants.slug',
            'plan_name' => 'plans.name',
            'is_active' => 'tenants.is_active',
        ];

        $query = Tenant::query()
            ->leftJoin('plans', 'plans.id', '=', 'tenants.plan_id')
            ->select([
                'tenants.id',
                'tenants.uuid',
                'tenants.name',
                'tenants.slug',
                'tenants.plan_id',
                'tenants.logo_path',
                'tenants.logo_mime',
                'tenants.logo_updated_at',
                'tenants.is_active',
                'tenants.trial_ends_at',
                'tenants.created_at',
                'plans.uuid as plan_uuid',
                'plans.name as plan_name',
            ])
            ->whereNull('tenants.deleted_at');

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'tenants.name',
            'slug' => 'tenants.slug',
            'plan_name' => 'plans.name',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'tenants.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'tenants.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    /**
     * Criar novo tenant
     */
    public function create(CreateTenantDTO $dto, ?int $ownerUserId = null, ?int $actorId = null): Tenant
    {
        $newMedia = null;

        try {
            return DB::transaction(function () use ($dto, $ownerUserId, $actorId, &$newMedia) {
                $data = [
                    'name' => $dto->name,
                    'slug' => $dto->slug,
                    'plan_id' => $this->resolvePlanId($dto->planUuid),
                    'is_active' => $dto->isActive,
                    'trial_ends_at' => $dto->trialEndsAt,
                ];

                $tenant = $this->repository->create($data);

                if ($dto->logo) {
                    $newMedia = $this->mediaStorage->storePublicImage($dto->logo, $this->resolveTenantMediaDirectory($tenant), 'tenant');
                    $logoData = [];
                    $this->applyLogoUpload($logoData, $dto->logo, $newMedia);
                    $tenant = $this->repository->update($tenant, $logoData);
                }

                $effectiveOwnerUserId = $ownerUserId ?? Auth::id();
                $effectiveActorId = $actorId ?? Auth::id() ?? $effectiveOwnerUserId;

                if (!$effectiveOwnerUserId || !$effectiveActorId) {
                    throw new \RuntimeException(__('messages.tenant.owner_required'));
                }

                $ownerRole = $this->provisioningService->createOwnerRole($tenant);
                $this->provisioningService->attachOwnerUser($tenant, $effectiveOwnerUserId, $ownerRole);
                $this->provisioningService->syncOwnerRolePermissions($tenant, $ownerRole);

                event(new TenantCreated(
                    tenantUuid: $tenant->uuid,
                    actorId: $effectiveActorId
                ));

                return $tenant;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'tenant');
            }

            throw $e;
        }
    }

    /**
     * Atualizar tenant
     */
    public function update(Tenant $tenant, UpdateTenantDTO $dto): Tenant
    {
        $newMedia = null;
        $oldPath = $tenant->logo_path;

        if ($dto->logo) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->logo, $this->resolveTenantMediaDirectory($tenant), 'tenant');
        }

        try {
            return DB::transaction(function () use ($tenant, $dto, $newMedia, $oldPath) {

                $original = $tenant->getOriginal();

                $data = array_filter([
                    'name' => $dto->name,
                    'plan_id' => $this->resolvePlanId($dto->planUuid),
                    'is_active' => $dto->isActive,
                ], fn($v) => !is_null($v));

                $this->applyLogoUpload($data, $dto->logo, $newMedia);

                if (!empty($data)) {
                    $tenant = $this->repository->update($tenant, $data);

                    if ($newMedia && $oldPath && $oldPath !== $newMedia['path']) {
                        DB::afterCommit(fn() => $this->mediaStorage->deletePublicMedia($oldPath, 'tenant'));
                    }

                    $changes = array_diff_assoc($tenant->getAttributes(), $original);

                    event(new TenantUpdated(
                        tenantUuid: $tenant->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }

                return $tenant;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'tenant');
            }

            throw $e;
        }
    }

    /**
     * Deletar tenant (soft delete)
     */
    public function delete(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $this->repository->delete($tenant);

            event(new TenantDeleted(
                tenantUuid: $tenant->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Atualização do próprio perfil pelo dono da empresa: apenas nome e logo.
     * slug/plano/status são deliberadamente inacessíveis por aqui.
     */
    public function updateOwnProfile(Tenant $tenant, UpdateTenantProfileDTO $dto): Tenant
    {
        $newMedia = null;
        $oldPath = $tenant->logo_path;

        if ($dto->logo) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->logo, $this->resolveTenantMediaDirectory($tenant), 'tenant');
        }

        try {
            return DB::transaction(function () use ($tenant, $dto, $newMedia, $oldPath) {
                $original = $tenant->getOriginal();

                $data = [
                    'name' => $dto->name,
                    'cnpj' => $this->normalizeDigits($dto->cnpj, 14),
                    'ie' => $dto->ie,
                    'im' => $dto->im,
                    'cnae' => $dto->cnae,
                    'tax_regime' => $dto->taxRegime,
                    'fiscal_environment' => $dto->fiscalEnvironment,
                    'ibge_city_code' => $dto->ibgeCityCode,
                    'fiscal_provider' => $dto->fiscalProvider,
                    'fiscal_nfe_series' => $dto->fiscalNfeSeries,
                    'fiscal_nfce_series' => $dto->fiscalNfceSeries,
                    'fiscal_nfse_series' => $dto->fiscalNfseSeries,
                    'fiscal_next_nfe_number' => $dto->fiscalNextNfeNumber,
                    'fiscal_next_nfce_number' => $dto->fiscalNextNfceNumber,
                    'fiscal_next_nfse_number' => $dto->fiscalNextNfseNumber,
                    'fiscal_nfce_csc_id' => $dto->fiscalNfceCscId,
                ];

                if ($dto->clearFiscalNfceCscCode) {
                    $data['fiscal_nfce_csc_code'] = null;
                } elseif ($dto->fiscalNfceCscCode !== null) {
                    $data['fiscal_nfce_csc_code'] = $dto->fiscalNfceCscCode;
                }

                if ($dto->clearFiscalProviderApiToken) {
                    $data['fiscal_provider_api_token'] = null;
                } elseif ($dto->fiscalProviderApiToken !== null) {
                    $data['fiscal_provider_api_token'] = $dto->fiscalProviderApiToken;
                }

                if ($dto->clearFiscalCertificateA1) {
                    $data['fiscal_certificate_a1_data'] = null;
                    $data['fiscal_certificate_a1_name'] = null;
                    $data['fiscal_certificate_a1_mime'] = null;
                    $data['fiscal_certificate_a1_password'] = null;
                    $data['fiscal_certificate_a1_updated_at'] = null;
                }

                if ($dto->fiscalCertificateA1) {
                    $data['fiscal_certificate_a1_data'] = file_get_contents($dto->fiscalCertificateA1->getRealPath());
                    $data['fiscal_certificate_a1_name'] = $dto->fiscalCertificateA1->getClientOriginalName();
                    $data['fiscal_certificate_a1_mime'] = $dto->fiscalCertificateA1->getMimeType();
                    $data['fiscal_certificate_a1_updated_at'] = now();
                }

                if ($dto->fiscalCertificateA1Password !== null) {
                    $data['fiscal_certificate_a1_password'] = $dto->fiscalCertificateA1Password;
                }

                $this->applyLogoUpload($data, $dto->logo, $newMedia);

                $tenant = $this->repository->update($tenant, $data);

                if ($newMedia && $oldPath && $oldPath !== $newMedia['path']) {
                    DB::afterCommit(fn() => $this->mediaStorage->deletePublicMedia($oldPath, 'tenant'));
                }

                $changes = array_diff_assoc($tenant->getAttributes(), $original);

                event(new TenantUpdated(
                    tenantUuid: $tenant->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));

                return $tenant;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'tenant');
            }

            throw $e;
        }
    }

    /**
     * Logo guardado direto no banco (LONGBLOB) — basta sobrescrever a coluna,
     * sem arquivo antigo pra remover. Reaproveitado em create/update/updateOwnProfile.
     */
    private function applyLogoUpload(array &$data, ?UploadedFile $logo, ?array $storedMedia = null): void
    {
        if (!$logo) {
            return;
        }

        $data['logo_path'] = $storedMedia['path'] ?? null;
        $data['logo_data'] = null;
        $data['logo_mime'] = $storedMedia['mime'] ?? $logo->getMimeType();
        $data['logo_updated_at'] = $storedMedia['updated_at'] ?? now();
    }

    private function resolvePlanId(?string $planUuid): ?int
    {
        if (!$planUuid) {
            return Plan::query()
                ->whereNull('deleted_at')
                ->where('slug', 'pegaticket')
                ->where('is_active', true)
                ->value('id');
        }

        return Plan::query()
            ->whereNull('deleted_at')
            ->where('uuid', $planUuid)
            ->where('is_active', true)
            ->value('id');
    }

    private function resolveTenantMediaDirectory(Tenant $tenant): string
    {
        return $tenant->uuid;
    }

    private function normalizeDigits(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        return substr($digits, 0, $maxLength);
    }
}
