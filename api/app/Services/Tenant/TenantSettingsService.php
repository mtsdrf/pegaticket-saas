<?php

namespace App\Services\Tenant;

use App\DTOs\TenantSettings\UpdateTenantSettingsDTO;
use App\Events\TenantSettings\TenantSettingsUpdated;
use App\Models\Tenant\TenantSettings;
use App\Repositories\Contracts\TenantSettingsRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantSettingsService
{
    public function __construct(
        private TenantSettingsRepositoryInterface $repository
    ) {}

    public function getForTenant(int $tenantId): TenantSettings
    {
        return $this->repository->findOrCreateForTenant($tenantId);
    }

    public function update(int $tenantId, UpdateTenantSettingsDTO $dto): TenantSettings
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            $settings = $this->repository->findOrCreateForTenant($tenantId);

            $original = $settings->getOriginal();

            $settings = $this->repository->update($settings, [
                'accepted_payment_methods' => $dto->acceptedPaymentMethods,
                'payment_receiving_method' => $dto->paymentReceivingMethod,
                'payment_pix_key' => $dto->paymentPixKey,
                'pagbank_integration_mode' => $dto->pagBankIntegrationMode,
                'pagbank_environment' => $dto->pagBankEnvironment,
                'pagbank_access_token' => $dto->hasPagBankAccessTokenInput
                    ? $dto->pagBankAccessToken
                    : $settings->pagbank_access_token,
                'pagbank_receiver_account_id' => $dto->pagBankReceiverAccountId,
                'storefront_enabled' => $dto->storefrontEnabled,
                'catalog_layout' => $dto->catalogLayout,
                'hold_duration_minutes' => $dto->holdDurationMinutes,
                'affiliate_default_commission_percentage' => $dto->affiliateDefaultCommissionPercentage,
            ]);

            $changes = array_diff_assoc($settings->getAttributes(), $original);

            if (! empty($changes)) {
                event(new TenantSettingsUpdated(
                    tenantId: $tenantId,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $settings;
        });
    }
}
