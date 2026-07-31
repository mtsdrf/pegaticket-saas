<?php

namespace App\Services\Marketplace;

use App\Exceptions\Marketplace\MarketplaceIntegrationException;
use App\Models\Marketplace\MarketplaceIntegration;
use App\Models\Marketplace\MarketplaceMerchant;
use App\Services\Storefront\StoreBusinessHoursService;

class MarketplaceMerchantAvailabilityService
{
    private const IFOOD_DAY_MAP = [
        0 => 'SUNDAY',
        1 => 'MONDAY',
        2 => 'TUESDAY',
        3 => 'WEDNESDAY',
        4 => 'THURSDAY',
        5 => 'FRIDAY',
        6 => 'SATURDAY',
    ];

    public function __construct(
        private MarketplaceProviderRegistry $registry,
        private StoreBusinessHoursService $storeBusinessHoursService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(MarketplaceIntegration $integration): array
    {
        $merchant = $this->resolveMerchant($integration);
        $provider = $this->registry->for($integration->provider);

        return [
            'merchant' => $this->serializeMerchant($merchant),
            'status' => $provider->fetchMerchantStatus($integration, $merchant),
            'interruptions' => $provider->listInterruptions($integration, $merchant),
            'last_opening_hours_sync_at' => data_get($integration->settings, 'ifood_opening_hours_last_synced_at'),
            'last_opening_hours_shift_count' => data_get($integration->settings, 'ifood_opening_hours_shift_count'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createInterruption(MarketplaceIntegration $integration, array $payload): array
    {
        $merchant = $this->resolveMerchant($integration);
        $provider = $this->registry->for($integration->provider);

        return [
            'merchant' => $this->serializeMerchant($merchant),
            'interruption' => $provider->createInterruption($integration, $merchant, [
                'description' => (string) $payload['description'],
                'duration' => (int) $payload['duration'],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteInterruption(MarketplaceIntegration $integration, string $interruptionId): array
    {
        $merchant = $this->resolveMerchant($integration);
        $provider = $this->registry->for($integration->provider);

        $provider->deleteInterruption($integration, $merchant, $interruptionId);

        return [
            'merchant' => $this->serializeMerchant($merchant),
            'deleted' => true,
            'interruption_id' => $interruptionId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncOpeningHours(MarketplaceIntegration $integration): array
    {
        $merchant = $this->resolveMerchant($integration);
        $provider = $this->registry->for($integration->provider);
        $shifts = $this->buildOpeningHoursShifts($integration->tenant_id);

        if ($shifts === []) {
            throw new MarketplaceIntegrationException(__('messages.marketplace.no_business_hours_configured'));
        }

        $provider->replaceOpeningHours($integration, $merchant, ['shifts' => $shifts]);

        $syncedAt = now()->toIso8601String();
        $integration->forceFill([
            'settings' => array_merge($integration->settings ?? [], [
                'ifood_opening_hours_last_synced_at' => $syncedAt,
                'ifood_opening_hours_shift_count' => count($shifts),
            ]),
        ])->save();

        return [
            'merchant' => $this->serializeMerchant($merchant),
            'shifts_count' => count($shifts),
            'synced_at' => $syncedAt,
            'shifts' => $shifts,
        ];
    }

    private function resolveMerchant(MarketplaceIntegration $integration): MarketplaceMerchant
    {
        $query = MarketplaceMerchant::query()
            ->where('integration_id', $integration->id)
            ->where('tenant_id', $integration->tenant_id)
            ->where('is_active', true);

        $merchant = null;

        if (filled($integration->merchant_id)) {
            $merchant = (clone $query)
                ->where('external_id', $integration->merchant_id)
                ->first();
        }

        $merchant ??= (clone $query)->orderBy('name')->first();

        if (!$merchant) {
            throw new MarketplaceIntegrationException(__('messages.marketplace.merchant_not_found'));
        }

        return $merchant;
    }

    /**
     * @return array<int, array{dayOfWeek:string,start:string,duration:int}>
     */
    private function buildOpeningHoursShifts(int $tenantId): array
    {
        return $this->storeBusinessHoursService
            ->getForTenant($tenantId)
            ->flatMap(function ($shift) {
                if ($shift->is_closed || !$shift->opens_at || !$shift->closes_at) {
                    return [];
                }

                $day = (int) $shift->day_of_week;
                $start = substr((string) $shift->opens_at, 0, 8);
                $end = substr((string) $shift->closes_at, 0, 8);

                if ($start === $end) {
                    return [];
                }

                $startMinutes = $this->minutesOfDay($start);
                $endMinutes = $this->minutesOfDay($end);

                if ($endMinutes > $startMinutes) {
                    return [[
                        'dayOfWeek' => self::IFOOD_DAY_MAP[$day],
                        'start' => $start,
                        'duration' => $endMinutes - $startMinutes,
                    ]];
                }

                $minutesUntilMidnight = (24 * 60) - $startMinutes;
                $nextDay = ($day + 1) % 7;

                return [
                    [
                        'dayOfWeek' => self::IFOOD_DAY_MAP[$day],
                        'start' => $start,
                        'duration' => $minutesUntilMidnight,
                    ],
                    [
                        'dayOfWeek' => self::IFOOD_DAY_MAP[$nextDay],
                        'start' => '00:00:00',
                        'duration' => $endMinutes,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function minutesOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hours * 60) + $minutes;
    }

    /**
     * @return array<string, string>
     */
    private function serializeMerchant(MarketplaceMerchant $merchant): array
    {
        return [
            'uuid' => $merchant->uuid,
            'external_id' => $merchant->external_id,
            'name' => $merchant->name,
        ];
    }
}
