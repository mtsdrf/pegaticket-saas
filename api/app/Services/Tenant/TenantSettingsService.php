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
    ) {
    }

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
                'send_tracking_link_whatsapp' => $dto->sendTrackingLinkWhatsapp,
                'block_order_without_stock' => $dto->blockOrderWithoutStock,
                'minimum_order_value' => $dto->minimumOrderValue,
                'estimated_preparation_minutes' => $dto->estimatedPreparationMinutes,
                'cashback_enabled' => $dto->cashbackEnabled,
                'cashback_percentage' => $dto->cashbackPercentage,
                'cashback_max_per_order' => $dto->cashbackMaxPerOrder,
                'cashback_hold_days' => $dto->cashbackHoldDays,
                'cashback_expiration_days' => $dto->cashbackExpirationDays,
                'cashback_redeem_max_percentage' => $dto->cashbackRedeemMaxPercentage,
                'cashback_name' => $dto->cashbackName,
                'accepted_payment_methods' => $dto->acceptedPaymentMethods,
                'payment_receiving_method' => $dto->paymentReceivingMethod,
                'payment_pix_key' => $dto->paymentPixKey,
                'allow_store_pickup' => $dto->allowStorePickup,
                'allow_delivery' => $dto->allowDelivery,
                'storefront_enabled' => $dto->storefrontEnabled,
                'catalog_layout' => $dto->catalogLayout,
            ]);

            $changes = array_diff_assoc($settings->getAttributes(), $original);

            if (!empty($changes)) {
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
