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
                'meta_pixel_id' => $dto->metaPixelId,
                'google_analytics_id' => $dto->googleAnalyticsId,
            ]);

            $changes = $this->detectChangedAttributes($settings->getAttributes(), $original);

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

    /**
     * `array_diff_assoc()` quebra com atributos array/JSON como
     * `accepted_payment_methods`. Aqui normalizamos esses valores para uma
     * comparação estável sem perder quais chaves mudaram.
     *
     * @return array<string, mixed>
     */
    private function detectChangedAttributes(array $current, array $original): array
    {
        $changes = [];

        foreach ($current as $key => $value) {
            $normalizedCurrent = $this->normalizeAttributeValue($value);
            $normalizedOriginal = $this->normalizeAttributeValue($original[$key] ?? null);

            if ($normalizedCurrent !== $normalizedOriginal) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }

    private function normalizeAttributeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }
}
